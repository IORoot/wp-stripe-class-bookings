<?php
/**
 * Prefixed identifiers for Class Bookings with Stripe (clasbowi).
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

/**
 * Central prefix and slug constants (min. 4-character prefix per WP plugin guidelines).
 */
abstract class Constants {

	public const PREFIX = 'clasbowi';

	public const CPT_CLASS   = 'clasbowi_class';
	public const CPT_BOOKING = 'clasbowi_booking';

	public const OPTIONS_POST_ID = 'clasbowi_options';

	public const MENU_SETTINGS = 'clasbowi-settings';
	public const MENU_REPORTS  = 'clasbowi-reports';

	public const REST_NAMESPACE = 'clasbowi/v1';

	public const CRON_HOOK            = 'clasbowi_expire_holds';
	public const CRON_SCHEDULE        = 'clasbowi_five_minutes';
	public const CRON_INTERVAL_OPTION = 'clasbowi_cron_interval_registered';

	public const SHORTCODE_BOOKING = 'clasbowi_booking';
	public const SHORTCODE_STATUS  = 'clasbowi_booking_status';

	public const SCRIPT_FRONTEND        = 'clasbowi';
	public const SCRIPT_ADMIN_SETTINGS  = 'clasbowi-admin-settings';
	public const SCRIPT_CANCELLED_DATES = 'clasbowi-cancelled-dates';
	public const SCRIPT_CLASS_METABOX   = 'clasbowi-class-metabox';
	public const SCRIPT_REPORTS_CHART   = 'clasbowi-reports-chart';
	public const STYLE_ADMIN_SETTINGS   = 'clasbowi-admin-settings';
	public const STYLE_REPORTS          = 'clasbowi-reports';

	public const META_PREFIX = '_clasbowi_';

	public const ELEMENTOR_WIDGET = 'clasbowi-booking';

	/** @var string[] Legacy shortcodes kept for backward compatibility. */
	public const LEGACY_SHORTCODES_BOOKING = [ 'stripe_booking', 'yoga_booking' ];

	/** @var string[] Legacy shortcodes kept for backward compatibility. */
	public const LEGACY_SHORTCODES_STATUS = [ 'stripe_booking_status', 'yoga_booking_status' ];
}
