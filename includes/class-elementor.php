<?php
/**
 * Registers the Elementor widget.
 *
 * @package IORoot_Yoga_Bookings
 */

namespace IORoot_Yoga_Bookings;

defined( 'ABSPATH' ) || exit;

abstract class Elementor_Integration {

	public static function init(): void {
		add_action( 'elementor/widgets/register', [ self::class, 'register_widget' ] );
	}

	public static function register_widget( $widgets_manager ): void {
		require_once IOROOT_YB_DIR . 'widgets/yoga-booking-widget.php';
		if ( class_exists( '\IORoot_Yoga_Bookings\Widgets\Widget_Stripe_Booking' ) ) {
			$widgets_manager->register( new \IORoot_Yoga_Bookings\Widgets\Widget_Stripe_Booking() );
		}
	}
}
