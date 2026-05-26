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

define( 'IOROOT_YB_VERSION', '1.0.0' );
define( 'IOROOT_YB_TEXT_DOMAIN', 'class-bookings-with-stripe' );
define( 'IOROOT_YB_FILE', __FILE__ );
define( 'IOROOT_YB_DIR', plugin_dir_path( __FILE__ ) );
define( 'IOROOT_YB_URL', plugin_dir_url( __FILE__ ) );
define( 'IOROOT_YB_REST_NS', 'stripe-bookings/v1' );
define( 'IOROOT_YB_HOLD_SECONDS', 30 * MINUTE_IN_SECONDS );

require_once IOROOT_YB_DIR . 'includes/class-acf-dependency.php';
\IORoot_Yoga_Bookings\ACF_Dependency::init();
require_once IOROOT_YB_DIR . 'vendor/stripe/stripe-php/init.php';
require_once IOROOT_YB_DIR . 'includes/helpers.php';
require_once IOROOT_YB_DIR . 'includes/class-cpt.php';
require_once IOROOT_YB_DIR . 'includes/class-acf-fields.php';
require_once IOROOT_YB_DIR . 'includes/class-bookings.php';
require_once IOROOT_YB_DIR . 'includes/class-extra-fields.php';
require_once IOROOT_YB_DIR . 'includes/class-stripe-service.php';
require_once IOROOT_YB_DIR . 'includes/class-mailchimp.php';
require_once IOROOT_YB_DIR . 'includes/class-emails.php';
require_once IOROOT_YB_DIR . 'includes/class-rest.php';
require_once IOROOT_YB_DIR . 'includes/class-shortcode.php';
require_once IOROOT_YB_DIR . 'includes/class-result-pages.php';
require_once IOROOT_YB_DIR . 'includes/class-reports.php';
require_once IOROOT_YB_DIR . 'includes/class-elementor.php';
require_once IOROOT_YB_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'IORoot_Yoga_Bookings\\Result_Pages', 'on_activate' ] );
register_activation_hook( __FILE__, [ 'IORoot_Yoga_Bookings\\Bookings', 'on_activate' ] );
register_deactivation_hook( __FILE__, [ 'IORoot_Yoga_Bookings\\Bookings', 'on_deactivate' ] );

add_action( 'plugins_loaded', static function () {
	\IORoot_Yoga_Bookings\Plugin::instance()->init();
} );
