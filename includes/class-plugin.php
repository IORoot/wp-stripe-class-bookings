<?php
/**
 * Main plugin singleton. Wires all components together.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		CPT::init();
		ACF_Fields::init();
		Bookings::init();
		Extra_Fields::init();
		REST::init();
		Shortcode::init();
		Result_Pages::init();
		Reports::init();
		Elementor_Integration::init();
		Emails::init();

		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	public function register_assets(): void {
		wp_register_style(
			'clasbowi',
			CLASBOWI_URL . 'assets/cbfs-booking.css',
			[],
			CLASBOWI_VERSION
		);
		wp_register_script(
			'clasbowi',
			CLASBOWI_URL . 'assets/cbfs-booking.js',
			[],
			CLASBOWI_VERSION,
			true
		);
		wp_localize_script(
			'clasbowi',
			'CLASBOWI',
			[
				'rest_url' => esc_url_raw( rest_url( CLASBOWI_REST_NS . '/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);

		// Enqueue globally so forms injected later (e.g. Elementor off-canvas AJAX content)
		// still have booking handlers attached.
		wp_enqueue_style( 'clasbowi' );
		wp_enqueue_script( 'clasbowi' );
	}
}
