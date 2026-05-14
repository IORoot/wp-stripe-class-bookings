<?php
/**
 * Shortcodes: [stripe_booking] and [stripe_booking_status].
 *
 * @package IORoot_Yoga_Bookings
 */

namespace IORoot_Yoga_Bookings;

defined( 'ABSPATH' ) || exit;

abstract class Shortcode {

	public static function init(): void {
		add_shortcode( 'stripe_booking', [ self::class, 'render_booking' ] );
		add_shortcode( 'stripe_booking_status', [ self::class, 'render_status' ] );
		// Backward compatibility aliases.
		add_shortcode( 'yoga_booking', [ self::class, 'render_booking' ] );
		add_shortcode( 'yoga_booking_status', [ self::class, 'render_status' ] );
	}

	/**
	 * @param array<string, string> $atts
	 */
	public static function render_booking( $atts = [] ): string {
		$atts = shortcode_atts( [
			'class_id'           => '0',
			'class_slug'         => '',
			'stripe_booking_id'  => '',
			'class_stripe_id'    => '',
			'heading'            => '1',
		], (array) $atts, 'stripe_booking' );
		$atts = apply_filters( 'ioroot_sb_shortcode_booking_atts', $atts );

		$class_id = (int) $atts['class_id'];
		if ( ! $class_id && '' !== (string) $atts['stripe_booking_id'] ) {
			$class_id = absint( ltrim( (string) $atts['stripe_booking_id'], '#' ) );
		}
		if ( ! $class_id && '' !== (string) $atts['class_stripe_id'] ) {
			// Legacy alias.
			$class_id = absint( ltrim( (string) $atts['class_stripe_id'], '#' ) );
		}
		if ( ! $class_id && $atts['class_slug'] ) {
			$post = get_page_by_path( sanitize_title( $atts['class_slug'] ), OBJECT, CPT::CLASS_PT );
			if ( $post ) {
				$class_id = (int) $post->ID;
			}
		}

		$class_data = $class_id ? Helpers::get_class_data( $class_id ) : null;
		if ( ! $class_data ) {
			return '<div class="yb-form yb-form--error">' . esc_html__( 'No class selected.', 'ioroot-yoga-bookings' ) . '</div>';
		}
		$class_data = apply_filters( 'ioroot_sb_booking_class_data', $class_data, $atts );

		wp_enqueue_style( 'ioroot-yb' );
		wp_enqueue_script( 'ioroot-yb' );

		$dates_count     = Helpers::upcoming_dates_count();
		$dates           = Bookings::next_available_dates( $class_data, $dates_count );
		$show_heading    = '1' === (string) $atts['heading'];
		$max_seats_today = $dates ? (int) $dates[0]['remaining'] : 0;
		$max_seats_today = max( 0, min( 4, $max_seats_today ) );
		$template_args   = apply_filters(
			'ioroot_sb_booking_template_args',
			[
				'class_data'      => $class_data,
				'dates'           => $dates,
				'show_heading'    => $show_heading,
				'max_seats_today' => $max_seats_today,
				'atts'            => $atts,
			],
			$atts
		);

		$class_data      = $template_args['class_data'] ?? $class_data;
		$dates           = $template_args['dates'] ?? $dates;
		$show_heading    = (bool) ( $template_args['show_heading'] ?? $show_heading );
		$max_seats_today = (int) ( $template_args['max_seats_today'] ?? $max_seats_today );
		$atts            = $template_args['atts'] ?? $atts;
		$template_path   = self::get_template_path( 'booking-form.php', 'booking' );

		ob_start();
		if ( is_readable( $template_path ) ) {
			do_action( 'ioroot_sb_before_render_booking_template', $template_args, $template_path );
			include $template_path;
			do_action( 'ioroot_sb_after_render_booking_template', $template_args, $template_path );
		}
		$html = (string) ob_get_clean();
		return (string) apply_filters( 'ioroot_sb_booking_html', $html, $template_args, $template_path );
	}

	/**
	 * @param array<string, string> $atts
	 */
	public static function render_status( $atts = [] ): string {
		$atts = shortcode_atts( [
			'type' => 'success',
		], (array) $atts, 'stripe_booking_status' );
		$atts = apply_filters( 'ioroot_sb_shortcode_status_atts', $atts );

		$type = in_array( $atts['type'], [ 'success', 'cancelled', 'error' ], true ) ? $atts['type'] : 'success';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$session_id = isset( $_GET['booking'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['booking'] ) ) : '';
		$reason     = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( (string) $_GET['reason'] ) ) : '';
		$msg        = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['msg'] ) ) : '';
		$origin     = isset( $_GET['from'] ) ? Helpers::sanitise_internal_url( wp_unslash( (string) $_GET['from'] ), home_url( '/' ) ) : home_url( '/' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		wp_enqueue_style( 'ioroot-yb' );
		if ( 'success' === $type ) {
			wp_enqueue_script( 'ioroot-yb' );
		}

		$booking = null;
		if ( 'success' === $type && $session_id ) {
			$booking_id = Bookings::find_by_stripe_session( $session_id );
			if ( $booking_id ) {
				$meta       = Bookings::get_meta( $booking_id );
				$class_data = Helpers::get_class_data( $meta['class_id'] );
				$booking    = [
					'status'        => $meta['status'],
					'booking_id'    => $booking_id,
					'class_name'    => $class_data['name'] ?? '',
					'class_date'    => Helpers::format_date( $meta['class_date'] ),
					'class_time'    => Helpers::format_time( $class_data['start_time'] ?? '' ),
					'location'      => $class_data['location'] ?? '',
					'duration'      => (int) ( $class_data['duration'] ?? 0 ),
					'seats'         => $meta['seats'],
					'amount_total'  => Helpers::format_price( $meta['amount_total_pence'] / 100 ),
					'customer_name' => $meta['customer_name'],
					'extra_fields'  => Extra_Fields::display_rows( (int) $meta['class_id'], (string) ( $meta['extra_fields_json'] ?? '' ) ),
				];
			}
		}

		$template_args = apply_filters(
			'ioroot_sb_status_template_args',
			[
				'type'       => $type,
				'session_id' => $session_id,
				'reason'     => $reason,
				'msg'        => $msg,
				'origin'     => $origin,
				'booking'    => $booking,
				'atts'       => $atts,
			],
			$atts
		);

		$type       = (string) ( $template_args['type'] ?? $type );
		$session_id = (string) ( $template_args['session_id'] ?? $session_id );
		$reason     = (string) ( $template_args['reason'] ?? $reason );
		$msg        = (string) ( $template_args['msg'] ?? $msg );
		$origin     = (string) ( $template_args['origin'] ?? $origin );
		$booking    = $template_args['booking'] ?? $booking;
		$atts       = $template_args['atts'] ?? $atts;
		$template_path = self::get_template_path( 'booking-status.php', 'status' );

		ob_start();
		if ( is_readable( $template_path ) ) {
			do_action( 'ioroot_sb_before_render_status_template', $template_args, $template_path );
			include $template_path;
			do_action( 'ioroot_sb_after_render_status_template', $template_args, $template_path );
		}
		$html = (string) ob_get_clean();
		return (string) apply_filters( 'ioroot_sb_status_html', $html, $template_args, $template_path );
	}

	/**
	 * Resolve template path: theme override first, then plugin default.
	 */
	private static function get_template_path( string $relative, string $context = '' ): string {
		$relative = ltrim( $relative, '/' );
		$theme    = locate_template( [ 'ioroot-stripe-bookings/' . $relative, 'ioroot-yoga-bookings/' . $relative ], false, false );
		$path     = $theme ?: IOROOT_YB_DIR . 'templates/' . $relative;
		return (string) apply_filters( 'ioroot_sb_template_path', $path, $relative, $context );
	}
}
