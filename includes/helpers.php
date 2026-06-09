<?php
/**
 * Helper functions: date math, money formatting, ACF option getters.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

abstract class Helpers {

	private const DAYS = [
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
		'sunday'    => 7,
	];

	/**
	 * Get the next $count occurrences of $weekday (e.g. 'sunday') as Y-m-d strings,
	 * starting from "now" in the site timezone. Skips today if its $start_time has already passed.
	 *
	 * @param string                $weekday    e.g. 'sunday'
	 * @param string                $start_time HH:MM (24h)
	 * @param int                   $count      How many occurrences
	 * @param array<int, string>    $skip_dates Y-m-d strings to skip (cancelled)
	 * @return array<int, string>               Y-m-d strings
	 */
	public static function next_weekday_occurrences( string $weekday, string $start_time, int $count, array $skip_dates = [] ): array {
		$weekday = strtolower( trim( $weekday ) );
		if ( ! isset( self::DAYS[ $weekday ] ) ) {
			return [];
		}

		try {
			$tz   = wp_timezone();
			$now  = new \DateTimeImmutable( 'now', $tz );
			$walk = $now->setTime( 0, 0, 0 );
		} catch ( \Exception $e ) {
			return [];
		}

		$results        = [];
		$todays_weekday = (int) $now->format( 'N' );
		$target_weekday = self::DAYS[ $weekday ];

		// If today matches the weekday, only include it if the class hasn't started yet.
		$today_is_target = ( $todays_weekday === $target_weekday );
		if ( $today_is_target ) {
			$today_class_start = $now->modify( $start_time );
			if ( $today_class_start && $now < $today_class_start ) {
				$candidate = $walk->format( 'Y-m-d' );
				if ( ! in_array( $candidate, $skip_dates, true ) ) {
					$results[] = $candidate;
				}
			}
			$walk = $walk->modify( '+1 day' );
		}

		$max_iterations = 365;
		while ( count( $results ) < $count && $max_iterations-- > 0 ) {
			$walk_weekday = (int) $walk->format( 'N' );
			if ( $walk_weekday === $target_weekday ) {
				$candidate = $walk->format( 'Y-m-d' );
				if ( ! in_array( $candidate, $skip_dates, true ) ) {
					$results[] = $candidate;
				}
				$walk = $walk->modify( '+7 days' );
			} else {
				$diff = ( $target_weekday - $walk_weekday + 7 ) % 7;
				$walk = $walk->modify( "+{$diff} days" );
			}
		}

		return $results;
	}

	/**
	 * Get upcoming dates inside a one-off event date range.
	 *
	 * @param array<int, string> $skip_dates Y-m-d strings to skip.
	 * @return array<int, string>
	 */
	public static function date_range_occurrences( string $start_date, string $end_date, string $start_time, int $count, array $skip_dates = [] ): array {
		$start_date = self::normalise_date_string( $start_date );
		$end_date   = self::normalise_date_string( $end_date );
		if ( '' === $start_date ) {
			return [];
		}
		if ( '' === $end_date ) {
			$end_date = $start_date;
		}

		try {
			$tz    = wp_timezone();
			$now   = new \DateTimeImmutable( 'now', $tz );
			$walk  = new \DateTimeImmutable( $start_date, $tz );
			$end   = new \DateTimeImmutable( $end_date, $tz );
		} catch ( \Exception $e ) {
			return [];
		}

		if ( $end < $walk ) {
			return [];
		}

		$results        = [];
		$max_iterations = 366;
		while ( count( $results ) < $count && $walk <= $end && $max_iterations-- > 0 ) {
			$candidate       = $walk->format( 'Y-m-d' );
			$candidate_start = $walk->modify( $start_time ?: '00:00' );
			if ( $candidate_start && $candidate_start > $now && ! in_array( $candidate, $skip_dates, true ) ) {
				$results[] = $candidate;
			}
			$walk = $walk->modify( '+1 day' );
		}

		return $results;
	}

	/**
	 * Format Y-m-d as e.g. "Sun 17 May 2026".
	 */
	public static function format_date( string $ymd ): string {
		try {
			$dt = new \DateTimeImmutable( $ymd, wp_timezone() );
			return wp_date( 'D j M Y', $dt->getTimestamp() );
		} catch ( \Exception $e ) {
			return $ymd;
		}
	}

	/**
	 * Format a one-off event date range.
	 */
	public static function format_date_range( string $start_date, string $end_date = '' ): string {
		$start_date = self::normalise_date_string( $start_date );
		$end_date   = self::normalise_date_string( $end_date );
		if ( '' === $start_date ) {
			return '';
		}
		if ( '' === $end_date || $end_date === $start_date ) {
			return self::format_date( $start_date );
		}

		return self::format_date( $start_date ) . ' - ' . self::format_date( $end_date );
	}

	/**
	 * Format HH:MM as 12-hour (e.g. "10:15 AM"). Falls back to input.
	 */
	public static function format_time( string $hhmm ): string {
		$ts = strtotime( $hhmm );
		if ( false === $ts ) {
			return $hhmm;
		}
		return wp_date( 'g:i A', $ts );
	}

	/**
	 * Sanitize HTML for the waiver checkbox label (post-like tags, including links).
	 */
	public static function waiver_label_kses( string $html ): string {
		$out = wp_kses_post( $html );
		return apply_filters( 'clasbowi_waiver_label_kses', $out, $html );
	}

	/**
	 * Format pounds (float or string) as "£15.00".
	 */
	public static function format_price( $amount ): string {
		return '£' . number_format( (float) $amount, 2 );
	}

	/**
	 * Convert pounds to integer pence for Stripe.
	 */
	public static function to_pence( $amount ): int {
		return (int) round( ( (float) $amount ) * 100 );
	}

	/**
	 * Get an ACF option, falling back if ACF isn't available.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get_option( string $key, $default = '' ) {
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $key, 'clasbowi_options' );
			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}
		return $default;
	}

	/**
	 * Get the active Stripe secret key based on the configured mode.
	 */
	public static function stripe_secret_key(): string {
		$mode = self::get_option( 'stripe_mode', 'test' );
		$key  = 'live' === $mode
			? self::get_option( 'stripe_secret_key_live', '' )
			: self::get_option( 'stripe_secret_key_test', '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Get the active Stripe publishable key.
	 */
	public static function stripe_publishable_key(): string {
		$mode = self::get_option( 'stripe_mode', 'test' );
		$key  = 'live' === $mode
			? self::get_option( 'stripe_pub_key_live', '' )
			: self::get_option( 'stripe_pub_key_test', '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	public static function stripe_webhook_secret(): string {
		$key = self::get_option( 'stripe_webhook_secret', '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Number of upcoming dates shown in the booking form dropdown for a class.
	 *
	 * @param array<string, mixed> $class_data Shape from {@see get_class_data()}.
	 */
	public static function class_upcoming_dates_count( array $class_data ): int {
		if ( ! empty( $class_data['is_one_off_event'] ) ) {
			return 1;
		}
		$n = isset( $class_data['upcoming_dates_count'] ) ? (int) $class_data['upcoming_dates_count'] : 3;
		return max( 1, min( 12, $n ) );
	}

	/**
	 * Read class fields off a clasbowi_class post in a uniform shape.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_class_data( int $class_id ): ?array {
		$post = get_post( $class_id );
		if ( ! $post || 'clasbowi_class' !== $post->post_type ) {
			return null;
		}

		$cancelled_dates = self::read_cancelled_dates( $class_id );

		$start_time = (string) ( function_exists( 'get_field' ) ? get_field( 'start_time', $class_id ) : '' );
		// ACF time field saves as H:i:s — keep first 5 chars.
		if ( $start_time && strlen( $start_time ) > 5 ) {
			$start_time = substr( $start_time, 0, 5 );
		}

		$schedule_type   = function_exists( 'get_field' ) ? (string) get_field( 'schedule_type', $class_id ) : 'recurring';
		$legacy_external = (bool) get_post_meta( $class_id, 'use_external_link', true );
		if ( 'external' === $schedule_type || $legacy_external ) {
			$schedule_type = 'external';
		} elseif ( 'one_off' !== $schedule_type ) {
			$schedule_type = 'recurring';
		}

		$start_date = function_exists( 'get_field' ) ? self::normalise_date_string( (string) get_field( 'start_date', $class_id ) ) : '';
		$end_date   = function_exists( 'get_field' ) ? self::normalise_date_string( (string) get_field( 'end_date', $class_id ) ) : '';
		if ( '' === $end_date ) {
			$end_date = $start_date;
		}

		$class_image = function_exists( 'get_field' ) ? get_field( 'class_image', $class_id ) : null;
		$image_id    = 0;
		if ( is_numeric( $class_image ) ) {
			$image_id = (int) $class_image;
		} elseif ( is_array( $class_image ) && ! empty( $class_image['ID'] ) ) {
			$image_id = (int) $class_image['ID'];
		}

		$upcoming_raw = function_exists( 'get_field' ) ? get_field( 'upcoming_dates_count', $class_id ) : null;
		$upcoming_n   = ( null !== $upcoming_raw && '' !== $upcoming_raw && false !== $upcoming_raw ) ? (int) $upcoming_raw : 3;
		$upcoming_n   = max( 1, min( 12, $upcoming_n ) );

		return [
			'id'              => $class_id,
			'name'            => get_the_title( $class_id ),
			'description'     => function_exists( 'get_field' ) ? (string) get_field( 'description', $class_id ) : '',
			'image_id'        => $image_id,
			'use_external_link' => 'external' === $schedule_type,
			'external_link_url' => function_exists( 'get_field' ) ? esc_url_raw( (string) get_field( 'external_link_url', $class_id ) ) : '',
			'location'        => function_exists( 'get_field' ) ? (string) get_field( 'location', $class_id ) : '',
			'schedule_type'   => $schedule_type,
			'is_one_off_event' => 'one_off' === $schedule_type,
			'day_of_week'     => function_exists( 'get_field' ) ? (string) get_field( 'day_of_week', $class_id ) : '',
			'start_date'      => $start_date,
			'end_date'        => $end_date,
			'start_time'      => $start_time,
			'duration'        => function_exists( 'get_field' ) ? (int) get_field( 'duration_minutes', $class_id ) : 0,
			'price'           => function_exists( 'get_field' ) ? (float) get_field( 'price_gbp', $class_id ) : 0.0,
			'capacity'        => function_exists( 'get_field' ) ? (int) get_field( 'capacity', $class_id ) : 0,
			'show_seats_remaining' => function_exists( 'get_field' ) ? (bool) get_field( 'show_seats_remaining', $class_id ) : true,
			'upcoming_dates_count' => $upcoming_n,
			'class_active'    => function_exists( 'get_field' ) ? (bool) get_field( 'class_active', $class_id ) : true,
			'cancelled_dates' => array_values( $cancelled_dates ),
		];
	}

	/**
	 * Read cancelled dates from either:
	 * - Pro repeater field "cancelled_dates"
	 * - Free fallback textarea field "cancelled_dates_fallback"
	 *
	 * @return array<int,string>
	 */
	private static function read_cancelled_dates( int $class_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return [];
		}

		$cancelled_dates = [];

		$repeater_value = get_field( 'cancelled_dates', $class_id );
		if ( is_array( $repeater_value ) ) {
			foreach ( $repeater_value as $row ) {
				if ( is_array( $row ) && ! empty( $row['date'] ) ) {
					$cancelled_dates[] = self::normalise_date_string( (string) $row['date'] );
				}
			}
		}

		$fallback_value = get_field( 'cancelled_dates_fallback', $class_id );
		if ( is_string( $fallback_value ) && '' !== trim( $fallback_value ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $fallback_value ) ?: [];
			foreach ( $lines as $line ) {
				$raw = trim( (string) $line );
				if ( '' === $raw ) {
					continue;
				}

				// Allow optional note after pipe, e.g. 2026-12-24|Holiday.
				$date_part = trim( explode( '|', $raw, 2 )[0] ?? '' );
				if ( '' === $date_part ) {
					continue;
				}

				$cancelled_dates[] = self::normalise_date_string( $date_part );
			}
		}

		$cancelled_dates = array_values( array_unique( array_filter( $cancelled_dates ) ) );
		sort( $cancelled_dates );

		return $cancelled_dates;
	}

	/**
	 * Coerce common date formats into Y-m-d.
	 */
	public static function normalise_date_string( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		// ACF stores as Ymd by default; tolerate other formats.
		if ( preg_match( '/^\d{8}$/', $value ) ) {
			return substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 );
		}
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return '';
		}
		return wp_date( 'Y-m-d', $ts );
	}

	/**
	 * Sanitise a redirect target so we never bounce off-site.
	 */
	public static function sanitise_internal_url( string $url, string $fallback = '' ): string {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return $fallback;
		}
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		if ( $url_host && $home_host && strtolower( $url_host ) !== strtolower( $home_host ) ) {
			return $fallback;
		}
		return $url;
	}

	/**
	 * Write a diagnostic line when WP_DEBUG and WP_DEBUG_LOG are enabled.
	 *
	 * @param string $message Log message.
	 */
	public static function debug_log( string $message ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only when site owner enables debug logging.
		error_log( $message );
	}
}
