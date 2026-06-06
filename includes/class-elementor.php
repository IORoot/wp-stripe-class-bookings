<?php
/**
 * Registers the Elementor widget.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

abstract class Elementor_Integration {

	public static function init(): void {
		add_action( 'elementor/widgets/register', [ self::class, 'register_widget' ] );
	}

	public static function register_widget( $widgets_manager ): void {
		require_once CLASBOWI_DIR . 'widgets/cbfs-booking-widget.php';
		if ( class_exists( '\IOROOT_STRIPE_BOOKINGS\Widgets\Widget_Stripe_Booking' ) ) {
			$widgets_manager->register( new \IOROOT_STRIPE_BOOKINGS\Widgets\Widget_Stripe_Booking() );
		}
	}
}
