<?php
/**
 * Booking lifecycle: capacity calculation, soft-hold creation, status transitions, cron cleanup.
 *
 * @package IORoot_Yoga_Bookings
 */

namespace IORoot_Yoga_Bookings;

defined( 'ABSPATH' ) || exit;

abstract class Bookings {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_PAID     = 'paid';
	public const STATUS_EXPIRED  = 'expired';
	public const STATUS_REFUNDED = 'refunded';

	public const CRON_HOOK = 'yoga_bookings_expire_holds';

	public static function init(): void {
		add_filter( 'cron_schedules', [ self::class, 'register_cron_interval' ] );
		add_action( self::CRON_HOOK, [ self::class, 'expire_stale_holds' ] );
		add_action( 'init', [ self::class, 'maybe_schedule_cron' ] );
	}

	public static function register_cron_interval( array $schedules ): array {
		if ( ! isset( $schedules['yb_five_minutes'] ) ) {
			$schedules['yb_five_minutes'] = [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes (Stripe Bookings)', 'ioroot-yoga-bookings' ),
			];
		}
		return $schedules;
	}

	public static function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'yb_five_minutes', self::CRON_HOOK );
		}
	}

	public static function on_activate(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'yb_five_minutes', self::CRON_HOOK );
		}
	}

	public static function on_deactivate(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Count seats currently taken for (class, date) — paid + active soft-holds.
	 */
	public static function seats_taken( int $class_id, string $class_date ): int {
		$now_gmt = current_time( 'mysql', true );

		$query = new \WP_Query( [
			'post_type'      => CPT::BOOKING_PT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => '_yb_class_id',
					'value' => $class_id,
				],
				[
					'key'   => '_yb_class_date',
					'value' => $class_date,
				],
				[
					'relation' => 'OR',
					[
						'key'   => '_yb_status',
						'value' => self::STATUS_PAID,
					],
					[
						'relation' => 'AND',
						[
							'key'   => '_yb_status',
							'value' => self::STATUS_PENDING,
						],
						[
							'key'     => '_yb_expires_at',
							'value'   => $now_gmt,
							'compare' => '>',
							'type'    => 'DATETIME',
						],
					],
				],
			],
		] );

		$total = 0;
		foreach ( $query->posts as $post_id ) {
			$total += (int) get_post_meta( (int) $post_id, '_yb_seats', true );
		}
		return $total;
	}

	/**
	 * @return int Remaining capacity for (class, date), clamped at 0.
	 */
	public static function seats_remaining( array $class_data, string $class_date ): int {
		$capacity = max( 0, (int) ( $class_data['capacity'] ?? 0 ) );
		$taken    = self::seats_taken( (int) $class_data['id'], $class_date );
		return max( 0, $capacity - $taken );
	}

	/**
	 * Validate that a date is bookable for the given class:
	 *  - class is active
	 *  - the date matches the class's day-of-week
	 *  - the date is not in cancelled_dates
	 *  - the date is in the future (or today before start_time)
	 *
	 * @return string '' if valid, otherwise an error reason code.
	 */
	public static function validate_date( array $class_data, string $class_date ): string {
		if ( empty( $class_data['class_active'] ) ) {
			return 'class_inactive';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $class_date ) ) {
			return 'date_invalid';
		}

		try {
			$tz   = wp_timezone();
			$dt   = new \DateTimeImmutable( $class_date, $tz );
			$now  = new \DateTimeImmutable( 'now', $tz );
		} catch ( \Exception $e ) {
			return 'date_invalid';
		}

		if ( ! empty( $class_data['is_one_off_event'] ) ) {
			$start_date = Helpers::normalise_date_string( (string) ( $class_data['start_date'] ?? '' ) );
			$end_date   = Helpers::normalise_date_string( (string) ( $class_data['end_date'] ?? '' ) );
			if ( '' === $start_date ) {
				return 'date_invalid';
			}
			if ( '' === $end_date ) {
				$end_date = $start_date;
			}
			try {
				$range_start = new \DateTimeImmutable( $start_date, $tz );
				$range_end   = new \DateTimeImmutable( $end_date, $tz );
			} catch ( \Exception $e ) {
				return 'date_invalid';
			}
			if ( $range_end < $range_start || $dt < $range_start || $dt > $range_end ) {
				return 'date_invalid';
			}
		} else {
			// Day of week match.
			$weekday = strtolower( $class_data['day_of_week'] ?? '' );
			$want    = strtolower( $dt->format( 'l' ) );
			if ( $weekday !== $want ) {
				return 'date_invalid';
			}
		}

		// Cancelled.
		if ( in_array( $class_date, (array) ( $class_data['cancelled_dates'] ?? [] ), true ) ) {
			return 'date_invalid';
		}

		// Past date — allow today before start_time.
		$class_start = $dt->modify( $class_data['start_time'] ?: '00:00' );
		if ( ! $class_start ) {
			return 'date_invalid';
		}
		if ( $class_start <= $now ) {
			return 'date_invalid';
		}

		return '';
	}

	/**
	 * Get the next $count upcoming dates with availability metadata.
	 *
	 * @return array<int, array{date: string, label: string, remaining: int, cancelled: bool, selectable: bool}>
	 */
	public static function next_available_dates( array $class_data, int $count = 3 ): array {
		if ( empty( $class_data['class_active'] ) ) {
			return [];
		}
		if ( empty( $class_data['start_time'] ) ) {
			return [];
		}

		$is_one_off = ! empty( $class_data['is_one_off_event'] );
		$weekday    = strtolower( $class_data['day_of_week'] ?? '' );
		if ( ! $is_one_off && '' === $weekday ) {
			return [];
		}
		if ( $is_one_off && empty( $class_data['start_date'] ) ) {
			return [];
		}

		$results   = [];
		$attempt   = 0;
		$batch     = $count;
		$cancelled = (array) ( $class_data['cancelled_dates'] ?? [] );

		// Pull a wider window so that fully-booked dates can be skipped while still returning $count items.
		while ( count( $results ) < $count && $attempt < 6 ) {
			$batch += $count;
			$dates = $is_one_off
				? Helpers::date_range_occurrences( (string) $class_data['start_date'], (string) $class_data['end_date'], (string) $class_data['start_time'], $batch, [] )
				: Helpers::next_weekday_occurrences( $weekday, $class_data['start_time'], $batch, [] );
			$results = [];
			foreach ( $dates as $date ) {
				$is_cancelled = in_array( $date, $cancelled, true );
				$remaining    = $is_cancelled ? 0 : self::seats_remaining( $class_data, $date );
				$is_selectable = ( ! $is_cancelled && $remaining > 0 );

				// Keep cancelled dates visible in the dropdown, but continue to hide fully-booked dates.
				if ( ! $is_cancelled && $remaining <= 0 ) {
					continue;
				}

				$results[] = [
					'date'      => $date,
					'label'     => Helpers::format_date( $date ) . ' · ' . Helpers::format_time( $class_data['start_time'] ),
					'remaining' => $remaining,
					'cancelled' => $is_cancelled,
					'selectable' => $is_selectable,
				];
				if ( count( $results ) >= $count ) {
					break;
				}
			}
			$attempt++;
		}

		return $results;
	}

	/**
	 * Create a pending booking with a 30-minute soft-hold.
	 *
	 * @return int|\WP_Error Booking post ID, or WP_Error.
	 */
	public static function create_pending_booking( array $params ) {
		$class_id    = (int) $params['class_id'];
		$class_date  = (string) $params['class_date'];
		$seats       = (int) $params['seats'];
		$name        = sanitize_text_field( (string) ( $params['customer_name'] ?? '' ) );
		$email       = sanitize_email( (string) ( $params['customer_email'] ?? '' ) );
		$amount_pence = (int) $params['amount_pence'];
		$waiver_accepted = ! empty( $params['waiver_accepted'] ) ? 1 : 0;
		$mailchimp_opt_in = ! empty( $params['mailchimp_opt_in'] ) ? 1 : 0;
		$extra_fields = is_array( $params['extra_fields'] ?? null ) ? (array) $params['extra_fields'] : [];

		$post_id = wp_insert_post( [
			'post_type'   => CPT::BOOKING_PT,
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: 1: customer name, 2: class title, 3: date */
				__( '%1$s · %2$s · %3$s', 'ioroot-yoga-bookings' ),
				$name ?: __( 'Pending', 'ioroot-yoga-bookings' ),
				get_the_title( $class_id ),
				Helpers::format_date( $class_date )
			),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + IOROOT_YB_HOLD_SECONDS );

		update_post_meta( $post_id, '_yb_class_id', $class_id );
		update_post_meta( $post_id, '_yb_class_date', $class_date );
		update_post_meta( $post_id, '_yb_seats', $seats );
		update_post_meta( $post_id, '_yb_customer_name', $name );
		update_post_meta( $post_id, '_yb_customer_email', $email );
		update_post_meta( $post_id, '_yb_amount_total', $amount_pence );
		update_post_meta( $post_id, '_yb_waiver_accepted', $waiver_accepted );
		update_post_meta( $post_id, '_yb_mailchimp_opt_in', $mailchimp_opt_in );
		update_post_meta( $post_id, '_yb_extra_fields', wp_json_encode( $extra_fields ) );
		update_post_meta( $post_id, '_yb_status', self::STATUS_PENDING );
		update_post_meta( $post_id, '_yb_expires_at', $expires_at );
		update_post_meta( $post_id, '_yb_created_gmt', gmdate( 'Y-m-d H:i:s' ) );

		return (int) $post_id;
	}

	public static function attach_stripe_session( int $booking_id, string $session_id ): void {
		update_post_meta( $booking_id, '_yb_stripe_session_id', $session_id );
	}

	public static function find_by_stripe_session( string $session_id ): ?int {
		if ( '' === $session_id ) {
			return null;
		}
		$query = new \WP_Query( [
			'post_type'      => CPT::BOOKING_PT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [
				[
					'key'   => '_yb_stripe_session_id',
					'value' => $session_id,
				],
			],
		] );
		if ( empty( $query->posts ) ) {
			return null;
		}
		return (int) $query->posts[0];
	}

	public static function set_status( int $booking_id, string $status ): void {
		update_post_meta( $booking_id, '_yb_status', $status );
		update_post_meta( $booking_id, '_yb_status_updated_gmt', gmdate( 'Y-m-d H:i:s' ) );
	}

	public static function get_status( int $booking_id ): string {
		return (string) get_post_meta( $booking_id, '_yb_status', true );
	}

	public static function get_meta( int $booking_id ): array {
		return [
			'class_id'           => (int) get_post_meta( $booking_id, '_yb_class_id', true ),
			'class_date'         => (string) get_post_meta( $booking_id, '_yb_class_date', true ),
			'seats'              => (int) get_post_meta( $booking_id, '_yb_seats', true ),
			'customer_name'      => (string) get_post_meta( $booking_id, '_yb_customer_name', true ),
			'customer_email'     => (string) get_post_meta( $booking_id, '_yb_customer_email', true ),
			'amount_total_pence' => (int) get_post_meta( $booking_id, '_yb_amount_total', true ),
			'waiver_accepted'    => (int) get_post_meta( $booking_id, '_yb_waiver_accepted', true ),
			'mailchimp_opt_in'   => (int) get_post_meta( $booking_id, '_yb_mailchimp_opt_in', true ),
			'extra_fields_json'  => (string) get_post_meta( $booking_id, '_yb_extra_fields', true ),
			'status'             => (string) get_post_meta( $booking_id, '_yb_status', true ),
			'stripe_session_id'  => (string) get_post_meta( $booking_id, '_yb_stripe_session_id', true ),
			'stripe_payment_intent' => (string) get_post_meta( $booking_id, '_yb_stripe_payment_intent', true ),
		];
	}

	/**
	 * Cron callback — mark stale pending bookings as expired so seats free up promptly.
	 */
	public static function expire_stale_holds(): void {
		$now_gmt = current_time( 'mysql', true );

		$query = new \WP_Query( [
			'post_type'      => CPT::BOOKING_PT,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => '_yb_status',
					'value' => self::STATUS_PENDING,
				],
				[
					'key'     => '_yb_expires_at',
					'value'   => $now_gmt,
					'compare' => '<',
					'type'    => 'DATETIME',
				],
			],
		] );

		foreach ( $query->posts as $post_id ) {
			self::set_status( (int) $post_id, self::STATUS_EXPIRED );
		}
	}
}
