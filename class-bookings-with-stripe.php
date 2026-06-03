<?php
/**
 * Plugin Name: Class Bookings with Stripe
 * Description: Class Bookings with Stripe — Stripe Checkout for classes. ACF-driven class types, capacity-aware date dropdowns, customer + admin emails, Elementor widget and shortcode.
 * Plugin URI: https://ioroot.com
 * Version: 1.0.0
 * Author: IORoot.com
 * Text Domain: class-bookings-with-stripe
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 */

defined( 'ABSPATH' ) || exit;

define( 'CLASBOWI_VERSION', '1.0.0' );
define( 'CLASBOWI_TEXT_DOMAIN', 'class-bookings-with-stripe' );
define( 'CLASBOWI_FILE', __FILE__ );
define( 'CLASBOWI_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLASBOWI_URL', plugin_dir_url( __FILE__ ) );
define( 'CLASBOWI_REST_NS', 'clasbowi/v1' );
define( 'CLASBOWI_HOLD_SECONDS', 30 * MINUTE_IN_SECONDS );

/** @deprecated 1.1.0 Use CLASBOWI_* constants. */
if ( ! defined( 'IOROOT_YB_VERSION' ) ) {
	define( 'IOROOT_YB_VERSION', CLASBOWI_VERSION );
	define( 'IOROOT_YB_TEXT_DOMAIN', CLASBOWI_TEXT_DOMAIN );
	define( 'IOROOT_YB_FILE', CLASBOWI_FILE );
	define( 'IOROOT_YB_DIR', CLASBOWI_DIR );
	define( 'IOROOT_YB_URL', CLASBOWI_URL );
	define( 'IOROOT_YB_REST_NS', CLASBOWI_REST_NS );
	define( 'IOROOT_YB_HOLD_SECONDS', CLASBOWI_HOLD_SECONDS );
}

require_once CLASBOWI_DIR . 'includes/class-constants.php';
require_once CLASBOWI_DIR . 'includes/class-migration.php';
require_once CLASBOWI_DIR . 'includes/class-acf-dependency.php';
\IOROOT_STRIPE_BOOKINGS\ACF_Dependency::init();
require_once CLASBOWI_DIR . 'vendor/stripe/stripe-php/init.php';
require_once CLASBOWI_DIR . 'includes/helpers.php';
require_once CLASBOWI_DIR . 'includes/class-cpt.php';
require_once CLASBOWI_DIR . 'includes/class-acf-fields.php';
require_once CLASBOWI_DIR . 'includes/class-bookings.php';
require_once CLASBOWI_DIR . 'includes/class-extra-fields.php';
require_once CLASBOWI_DIR . 'includes/class-stripe-service.php';
require_once CLASBOWI_DIR . 'includes/class-mailchimp.php';
require_once CLASBOWI_DIR . 'includes/class-emails.php';
require_once CLASBOWI_DIR . 'includes/class-rest.php';
require_once CLASBOWI_DIR . 'includes/class-shortcode.php';
require_once CLASBOWI_DIR . 'includes/class-result-pages.php';
require_once CLASBOWI_DIR . 'includes/class-reports.php';
require_once CLASBOWI_DIR . 'includes/class-elementor.php';
require_once CLASBOWI_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'IOROOT_STRIPE_BOOKINGS\\Migration', 'maybe_run' ] );
register_activation_hook( __FILE__, [ 'IOROOT_STRIPE_BOOKINGS\\Result_Pages', 'on_activate' ] );
register_activation_hook( __FILE__, [ 'IOROOT_STRIPE_BOOKINGS\\Bookings', 'on_activate' ] );
register_deactivation_hook( __FILE__, [ 'IOROOT_STRIPE_BOOKINGS\\Bookings', 'on_deactivate' ] );

add_action( 'plugins_loaded', static function () {
	\IOROOT_STRIPE_BOOKINGS\Migration::maybe_run();
	\IOROOT_STRIPE_BOOKINGS\Plugin::instance()->init();
}, 5 );
