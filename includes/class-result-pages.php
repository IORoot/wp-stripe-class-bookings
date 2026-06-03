<?php
/**
 * Auto-create + look up Success / Cancelled / Error pages.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

abstract class Result_Pages {

	private const SLUGS = [
		'success'   => [
			'slug'      => 'booking-confirmed',
			'title'     => 'Booking Confirmed',
			'option'    => 'success_page',
			'field_key' => 'field_clasbowi_success_page',
			'meta'      => '_clasbowi_result_page_success',
			'content'   => '[clasbowi_booking_status type="success"]',
		],
		'cancelled' => [
			'slug'      => 'booking-cancelled',
			'title'     => 'Booking Cancelled',
			'option'    => 'cancel_page',
			'field_key' => 'field_clasbowi_cancel_page',
			'meta'      => '_clasbowi_result_page_cancel',
			'content'   => '[clasbowi_booking_status type="cancelled"]',
		],
		'error'     => [
			'slug'      => 'booking-error',
			'title'     => 'Booking Error',
			'option'    => 'error_page',
			'field_key' => 'field_clasbowi_error_page',
			'meta'      => '_clasbowi_result_page_error',
			'content'   => '[clasbowi_booking_status type="error"]',
		],
	];

	private const ACF_POST_ID = 'clasbowi_options';

	public static function init(): void {
		// Allow the admin to point at custom pages via ACF settings; otherwise fall back to auto-created.
	}

	/**
	 * Run on activation: ensure pages exist, store IDs in ACF options.
	 */
	public static function on_activate(): void {
		foreach ( self::SLUGS as $key => $cfg ) {
			$page_id = self::ensure_page( $cfg['slug'], $cfg['title'], $cfg['content'], $cfg['meta'] );
			if ( $page_id <= 0 ) {
				continue;
			}
			$option_key   = self::ACF_POST_ID . '_' . $cfg['option'];
			$ref_key      = '_' . $option_key;
			$existing     = get_option( $option_key, 0 );
			if ( ! $existing ) {
				update_option( $option_key, $page_id );
				update_option( $ref_key, $cfg['field_key'] );
			}
		}
	}

	private static function ensure_page( string $slug, string $title, string $content, string $marker_meta ): int {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing instanceof \WP_Post ) {
			return (int) $existing->ID;
		}

		$page_id = wp_insert_post( [
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => $content,
			'meta_input'   => [
				$marker_meta => 1,
			],
		] );

		return is_wp_error( $page_id ) ? 0 : (int) $page_id;
	}

	public static function success_page_id(): int {
		$id = (int) Helpers::get_option( 'success_page', 0 );
		if ( $id > 0 ) {
			return $id;
		}
		$page = get_page_by_path( self::SLUGS['success']['slug'], OBJECT, 'page' );
		return $page ? (int) $page->ID : 0;
	}

	public static function cancel_page_id(): int {
		$id = (int) Helpers::get_option( 'cancel_page', 0 );
		if ( $id > 0 ) {
			return $id;
		}
		$page = get_page_by_path( self::SLUGS['cancelled']['slug'], OBJECT, 'page' );
		return $page ? (int) $page->ID : 0;
	}

	public static function error_page_id(): int {
		$id = (int) Helpers::get_option( 'error_page', 0 );
		if ( $id > 0 ) {
			return $id;
		}
		$page = get_page_by_path( self::SLUGS['error']['slug'], OBJECT, 'page' );
		return $page ? (int) $page->ID : 0;
	}

	public static function success_url( string $session_token = '{CHECKOUT_SESSION_ID}', string $origin = '' ): string {
		$id   = self::success_page_id();
		$base = $id ? get_permalink( $id ) : home_url( '/' );

		// Build with origin first so add_query_arg can url-encode it safely, then append
		// the Stripe placeholder literally so Stripe can substitute {CHECKOUT_SESSION_ID}.
		$url = $base;
		if ( $origin ) {
			$url = add_query_arg( [ 'from' => $origin ], $url );
		}
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'booking=' . $session_token;
		return $url;
	}

	public static function cancel_url( string $origin = '' ): string {
		$id   = self::cancel_page_id();
		$base = $id ? get_permalink( $id ) : home_url( '/' );
		$args = $origin ? [ 'from' => $origin ] : [];
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	public static function error_url( string $reason, string $message = '', string $origin = '' ): string {
		$id   = self::error_page_id();
		$base = $id ? get_permalink( $id ) : home_url( '/' );
		$args = [ 'reason' => $reason ];
		if ( $message ) {
			$args['msg'] = $message;
		}
		if ( $origin ) {
			$args['from'] = $origin;
		}
		return add_query_arg( $args, $base );
	}
}
