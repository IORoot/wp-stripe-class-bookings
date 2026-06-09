<?php
/**
 * Register ACF field groups and the options page.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

abstract class ACF_Fields {

	private const SETTINGS_MENU_SLUG = 'clasbowi-settings';
	private const SETTINGS_POST_ID   = 'clasbowi_options';

	public static function init(): void {
		add_action( 'acf/init', [ self::class, 'register_options_page' ] );
		add_action( 'acf/include_fields', [ self::class, 'register_field_groups' ] );
		add_filter( 'acf/load_field/key=field_clasbowi_b_summary', [ self::class, 'filter_booking_summary_field_format' ], 5 );
		add_filter( 'acf/load_field/key=field_clasbowi_b_summary', [ self::class, 'populate_booking_summary_field' ], 10 );
		add_filter( 'acf/load_value/name=schedule_type', [ self::class, 'load_schedule_type_value' ], 10, 3 );
		add_filter( 'acf/update_value/name=schedule_type', [ self::class, 'sync_use_external_link_from_schedule_type' ], 10, 3 );
		add_action( 'acf/render_field/key=field_clasbowi_cancelled_dates_fallback', [ self::class, 'render_cancelled_dates_quick_add' ] );

		$settings_screen_hook = CPT::CLASS_PT . '_page_' . self::SETTINGS_MENU_SLUG;
		add_action( 'load-' . $settings_screen_hook, [ self::class, 'on_load_booking_settings_screen' ] );

		add_action( 'admin_enqueue_scripts', [ self::class, 'maybe_enqueue_stripe_class_edit_admin' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'maybe_enqueue_booking_edit_admin' ] );

		// ACF Free has no built-in options pages, so provide a native admin fallback.
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			add_action( 'admin_menu', [ self::class, 'register_native_settings_page' ], 20 );
		}
	}

	public static function register_options_page(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}
		acf_add_options_page(
			[
				'page_title' => __( 'Settings', 'class-bookings-with-stripe' ),
				'menu_title' => __( 'Settings', 'class-bookings-with-stripe' ),
				'menu_slug'  => self::SETTINGS_MENU_SLUG,
				'parent_slug' => 'edit.php?post_type=' . CPT::CLASS_PT,
				'capability' => 'manage_options',
				'post_id'    => self::SETTINGS_POST_ID,
				'autoload'   => true,
			]
		);
	}

	public static function register_native_settings_page(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=' . CPT::CLASS_PT,
			__( 'Settings', 'class-bookings-with-stripe' ),
			__( 'Settings', 'class-bookings-with-stripe' ),
			'manage_options',
			self::SETTINGS_MENU_SLUG,
			[ self::class, 'render_native_settings_page' ]
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, [ self::class, 'on_load_native_settings_screen' ] );
		}
	}

	public static function on_load_native_settings_screen(): void {
		if ( function_exists( 'acf_form_head' ) ) {
			acf_form_head();
		}
		self::enqueue_booking_settings_admin_assets();
	}

	public static function render_native_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Settings', 'class-bookings-with-stripe' ) . '</h1>';

		if ( ! function_exists( 'acf_form' ) ) {
			echo '<p>' . esc_html__( 'ACF form renderer is unavailable. Please activate Advanced Custom Fields.', 'class-bookings-with-stripe' ) . '</p>';
			echo '</div>';
			return;
		}

		self::output_settings_intro_panel( 'native' );

		acf_form(
			[
				'post_id'               => self::SETTINGS_POST_ID,
				'field_groups'          => [ 'group_clasbowi_settings' ],
				'form_attributes'       => [ 'class' => 'acf-form clasbowi-settings-form' ],
				'html_submit_button'    => '<input type="submit" class="button button-primary button-large" value="%s" />',
				'html_submit_spinner'   => '<span class="spinner"></span>',
				'updated_message'       => __( 'Settings saved.', 'class-bookings-with-stripe' ),
				'submit_value'          => __( 'Save Settings', 'class-bookings-with-stripe' ),
				'instruction_placement' => 'field',
				'label_placement'       => 'top',
				'field_el'              => 'div',
				'kses'                  => true,
			]
		);

		echo '</div>';
	}

	/**
	 * Settings screen: body class (reliable CSS scope), assets, intro meta box.
	 *
	 * Runs on load-{screen} so we never depend on get_current_screen() inside acf/input/admin_head.
	 */
	public static function on_load_booking_settings_screen(): void {
		self::enqueue_booking_settings_admin_assets();

		if ( function_exists( 'acf_add_options_page' ) ) {
			add_meta_box(
				'clasbowi-settings-intro',
				'',
				[ self::class, 'render_settings_intro_metabox' ],
				'acf_options_page',
				'normal',
				'high'
			);
		}
	}

	/**
	 * Enqueue Settings screen styles and scripts (ACF options + native fallback).
	 */
	public static function enqueue_booking_settings_admin_assets(): void {
		add_filter( 'admin_body_class', [ self::class, 'filter_booking_settings_body_class' ] );

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'clasbowi-admin-settings',
			CLASBOWI_URL . 'assets/cbfs-booking-admin-settings.css',
			[],
			CLASBOWI_VERSION
		);
		wp_enqueue_script(
			'clasbowi-admin-settings',
			CLASBOWI_URL . 'assets/cbfs-booking-admin-settings.js',
			[],
			CLASBOWI_VERSION,
			true
		);
	}

	/**
	 * @param string $classes Space-prefixed admin body classes.
	 */
	public static function filter_booking_settings_body_class( string $classes ): string {
		return $classes . ' clasbowi-booking-settings';
	}

	/**
	 * Class add/edit screen: same admin design language as Settings.
	 */
	public static function maybe_enqueue_stripe_class_edit_admin(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || CPT::CLASS_PT !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
			return;
		}

		add_filter( 'admin_body_class', [ self::class, 'filter_stripe_class_edit_body_class' ] );

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'clasbowi-admin-settings',
			CLASBOWI_URL . 'assets/cbfs-booking-admin-settings.css',
			[],
			CLASBOWI_VERSION
		);
		wp_enqueue_script(
			'clasbowi-cancelled-dates',
			CLASBOWI_URL . 'assets/cbfs-booking-cancelled-dates.js',
			[],
			CLASBOWI_VERSION,
			true
		);
		wp_enqueue_script(
			'clasbowi-class-metabox',
			CLASBOWI_URL . 'assets/cbfs-booking-class-metabox.js',
			[ 'jquery' ],
			CLASBOWI_VERSION,
			true
		);
	}

	/**
	 * @param string $classes Space-prefixed admin body classes.
	 */
	public static function filter_stripe_class_edit_body_class( string $classes ): string {
		return $classes . ' clasbowi-class-edit';
	}

	/**
	 * Booking (clasbowi_booking) edit screen: same admin chrome + booking summary card styles.
	 */
	public static function maybe_enqueue_booking_edit_admin(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || CPT::BOOKING_PT !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
			return;
		}

		add_filter( 'admin_body_class', [ self::class, 'filter_booking_edit_body_class' ] );

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'clasbowi-admin-settings',
			CLASBOWI_URL . 'assets/cbfs-booking-admin-settings.css',
			[],
			CLASBOWI_VERSION
		);
	}

	/**
	 * @param string $classes Space-prefixed admin body classes.
	 */
	public static function filter_booking_edit_body_class( string $classes ): string {
		return $classes . ' clasbowi-booking-edit';
	}

	/**
	 * Path to the admin settings intro template (theme override, then plugin default).
	 */
	public static function get_settings_intro_template_path(): string {
		$relative = 'admin-settings-intro.php';
		$theme    = locate_template(
			[
				'class-bookings-with-stripe/' . $relative
			],
			false,
			false
		);
		$path = $theme ? (string) $theme : CLASBOWI_DIR . 'templates/' . $relative;

		/**
		 * Filter the path to the Settings intro template.
		 *
		 * @param string $path Absolute filesystem path.
		 */
		return (string) apply_filters( 'clasbowi_settings_intro_template_path', $path );
	}

	/**
	 * @param mixed $post Post object (unused on options screen).
	 * @param mixed $args Meta box args.
	 */
	public static function render_settings_intro_metabox( $post = null, $args = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		self::output_settings_intro_panel( 'metabox' );
	}

	/**
	 * Output the intro panel from the template file.
	 *
	 * @param 'metabox'|'native' $context Where the panel is rendered.
	 */
	public static function output_settings_intro_panel( string $context = 'native' ): void {
		$path = self::get_settings_intro_template_path();
		if ( ! is_readable( $path ) ) {
			return;
		}

		if ( 'native' === $context ) {
			echo '<div id="clasbowi-settings-intro-native" class="postbox clasbowi-settings-intro-postbox">';
			echo '<div class="inside">';
		}

		include $path;

		if ( 'native' === $context ) {
			echo '</div></div>';
		}
	}

	/**
	 * Render one-click upcoming date links under the ACF Free cancelled-dates textarea.
	 *
	 * @param array<string,mixed> $field
	 */
	public static function render_cancelled_dates_quick_add( array $field ): void {
		$post_id = 0;
		if ( isset( $field['post_id'] ) && is_numeric( $field['post_id'] ) ) {
			$post_id = (int) $field['post_id'];
		}
		if ( $post_id <= 0 ) {
			$post_id = (int) get_the_ID();
		}
		if ( $post_id <= 0 || CPT::CLASS_PT !== get_post_type( $post_id ) ) {
			return;
		}

		$class_data = Helpers::get_class_data( $post_id );
		if ( ! $class_data || empty( $class_data['start_time'] ) ) {
			return;
		}

		$dates = ! empty( $class_data['is_one_off_event'] )
			? Helpers::date_range_occurrences( (string) $class_data['start_date'], (string) $class_data['end_date'], (string) $class_data['start_time'], 5, [] )
			: Helpers::next_weekday_occurrences( (string) $class_data['day_of_week'], (string) $class_data['start_time'], 5, [] );
		if ( empty( $dates ) ) {
			return;
		}

		$field_key = isset( $field['key'] ) ? (string) $field['key'] : '';
		if ( '' === $field_key ) {
			return;
		}

		echo '<div class="clasbowi-cancelled-dates-helper">';
		echo '<strong>' . esc_html__( 'Quick add upcoming dates:', 'class-bookings-with-stripe' ) . '</strong> ';
		foreach ( $dates as $date ) {
			echo '<a href="#" class="button button-secondary button-small clasbowi-add-cancelled-date" data-field-key="' . esc_attr( $field_key ) . '" data-date="' . esc_attr( $date ) . '">' . esc_html( Helpers::format_date( (string) $date ) ) . '</a>';
		}
		echo '<p class="description">' . esc_html__( 'Click a date to append it to the cancelled dates textarea (one per line).', 'class-bookings-with-stripe' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Legacy classes stored external booking as a separate toggle; map that to schedule type.
	 *
	 * @param mixed $value
	 * @param int|string $post_id
	 * @param array<string,mixed> $field
	 * @return mixed
	 */
	public static function load_schedule_type_value( $value, $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
		if ( $post_id <= 0 || CPT::CLASS_PT !== get_post_type( $post_id ) ) {
			return $value;
		}
		if ( 'external' === $value ) {
			return $value;
		}
		if ( (bool) get_post_meta( $post_id, 'use_external_link', true ) ) {
			return 'external';
		}
		return $value;
	}

	/**
	 * Keep legacy use_external_link meta in sync when schedule type changes.
	 *
	 * @param mixed $value
	 * @param int|string $post_id
	 * @param array<string,mixed> $field
	 * @return mixed
	 */
	public static function sync_use_external_link_from_schedule_type( $value, $post_id, array $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
		if ( $post_id <= 0 || CPT::CLASS_PT !== get_post_type( $post_id ) ) {
			return $value;
		}
		update_post_meta( $post_id, 'use_external_link', 'external' === (string) $value ? 1 : 0 );
		return $value;
	}

	public static function register_field_groups(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$cancelled_dates_field = [
			'key'          => 'field_clasbowi_cancelled_dates_fallback',
			'label'        => __( 'Cancelled dates', 'class-bookings-with-stripe' ),
			'name'         => 'cancelled_dates_fallback',
			'type'         => 'textarea',
			'rows'         => 4,
			'new_lines'    => '',
			'instructions' => __( 'Enter one date per line (YYYY-MM-DD). Optional reason after "|" is allowed, e.g. 2026-12-24|Holiday.', 'class-bookings-with-stripe' ),
			'conditional_logic' => [
				[
					[
						'field'    => 'field_clasbowi_schedule_type',
						'operator' => '!=',
						'value'    => 'external',
					],
				],
			],
		];

		$internal_booking_condition = [
			[
				[
					'field'    => 'field_clasbowi_schedule_type',
					'operator' => '!=',
					'value'    => 'external',
				],
			],
		];

		$external_booking_condition = [
			[
				[
					'field'    => 'field_clasbowi_schedule_type',
					'operator' => '==',
					'value'    => 'external',
				],
			],
		];

		$recurring_booking_condition = [
			[
				[
					'field'    => 'field_clasbowi_schedule_type',
					'operator' => '==',
					'value'    => 'recurring',
				],
			],
		];

		$one_off_booking_condition = [
			[
				[
					'field'    => 'field_clasbowi_schedule_type',
					'operator' => '==',
					'value'    => 'one_off',
				],
			],
		];

		// --- Yoga class fields ---
		acf_add_local_field_group(
			[
				'key'      => 'group_clasbowi_class',
				'title'    => __( 'Class details', 'class-bookings-with-stripe' ),
				'fields'   => [
					[
						'key'           => 'field_clasbowi_schedule_type',
						'label'         => __( 'Schedule type', 'class-bookings-with-stripe' ),
						'name'          => 'schedule_type',
						'type'          => 'button_group',
						'choices'       => [
							'recurring' => __( 'Weekly class', 'class-bookings-with-stripe' ),
							'one_off'   => __( 'One-off event', 'class-bookings-with-stripe' ),
							'external'  => __( 'External link', 'class-bookings-with-stripe' ),
						],
						'default_value' => 'recurring',
						'allow_null'    => 0,
						'required'      => 1,
						'instructions'  => __( 'Choose a weekly class, a one-off event, or an external booking link instead of the built-in form.', 'class-bookings-with-stripe' ),
						'wrapper'       => [
							'width' => '75',
						],
					],
					[
						'key'           => 'field_clasbowi_class_active',
						'label'         => __( 'Class is active (bookable)', 'class-bookings-with-stripe' ),
						'name'          => 'class_active',
						'type'          => 'true_false',
						'default_value' => 1,
						'ui'            => 1,
						'instructions'  => __( 'Toggle off to suspend all bookings for this class.', 'class-bookings-with-stripe' ),
						'wrapper'       => [
							'width' => '25',
						],
					],
					[
						'key'               => 'field_clasbowi_external_link_url',
						'label'             => __( 'External booking URL', 'class-bookings-with-stripe' ),
						'name'              => 'external_link_url',
						'type'              => 'url',
						'instructions'      => __( 'For example: ClassFor, Eventbrite, or any custom external destination.', 'class-bookings-with-stripe' ),
						'placeholder'       => 'https://',
						'conditional_logic' => $external_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_day',
						'label'         => __( 'Day of week', 'class-bookings-with-stripe' ),
						'name'          => 'day_of_week',
						'type'          => 'select',
						'choices'       => [
							'monday'    => __( 'Monday', 'class-bookings-with-stripe' ),
							'tuesday'   => __( 'Tuesday', 'class-bookings-with-stripe' ),
							'wednesday' => __( 'Wednesday', 'class-bookings-with-stripe' ),
							'thursday'  => __( 'Thursday', 'class-bookings-with-stripe' ),
							'friday'    => __( 'Friday', 'class-bookings-with-stripe' ),
							'saturday'  => __( 'Saturday', 'class-bookings-with-stripe' ),
							'sunday'    => __( 'Sunday', 'class-bookings-with-stripe' ),
						],
						'default_value' => 'sunday',
						'instructions'   => __( 'Repeating Day.', 'class-bookings-with-stripe' ),
						'allow_null'    => 0,
						'required'      => 1,
						'wrapper'       => [
							'width' => '25',
						],
						'conditional_logic' => $recurring_booking_condition,
					],
					[
						'key'               => 'field_clasbowi_start_date',
						'label'             => __( 'Start date', 'class-bookings-with-stripe' ),
						'name'              => 'start_date',
						'type'              => 'date_picker',
						'display_format'    => 'Y-m-d',
						'return_format'     => 'Y-m-d',
						'first_day'         => 1,
						'required'          => 1,
						'instructions'      => __( 'First date this one-off event can be booked.', 'class-bookings-with-stripe' ),
						'wrapper'           => [
							'width' => '25',
						],
						'conditional_logic' => $one_off_booking_condition,
					],
					[
						'key'               => 'field_clasbowi_end_date',
						'label'             => __( 'End date', 'class-bookings-with-stripe' ),
						'name'              => 'end_date',
						'type'              => 'date_picker',
						'display_format'    => 'Y-m-d',
						'return_format'     => 'Y-m-d',
						'first_day'         => 1,
						'required'          => 1,
						'instructions'      => __( 'Use the same date as the start date for a single-day event.', 'class-bookings-with-stripe' ),
						'wrapper'           => [
							'width' => '25',
						],
						'conditional_logic' => $one_off_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_start_time',
						'label'         => __( 'Start time', 'class-bookings-with-stripe' ),
						'name'          => 'start_time',
						'type'          => 'time_picker',
						'display_format' => 'H:i',
						'return_format'  => 'H:i',
						'instructions'   => __( '24-hour format, e.g. 10:15.', 'class-bookings-with-stripe' ),
						'required'       => 1,
						'wrapper'        => [
							'width' => '25',
						],
						'conditional_logic' => $internal_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_duration',
						'label'         => __( 'Duration (minutes)', 'class-bookings-with-stripe' ),
						'name'          => 'duration_minutes',
						'type'          => 'number',
						'instructions'   => __( 'In minutes.', 'class-bookings-with-stripe' ),
						'default_value' => 45,
						'min'           => 1,
						'required'      => 1,
						'wrapper'       => [
							'width' => '25',
						],
						'conditional_logic' => $internal_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_price',
						'label'         => __( 'Price (£)', 'class-bookings-with-stripe' ),
						'name'          => 'price_gbp',
						'type'          => 'number',
						'default_value' => 15,
						'min'           => 0,
						'step'          => 0.01,
						'required'      => 1,
						'wrapper'       => [
							'width' => '25',
						],
						'conditional_logic' => $internal_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_capacity',
						'label'         => __( 'Capacity (max attendees)', 'class-bookings-with-stripe' ),
						'name'          => 'capacity',
						'type'          => 'number',
						'default_value' => 20,
						'min'           => 1,
						'required'      => 1,
						'wrapper'       => [
							'width' => '25',
						],
						'conditional_logic' => $internal_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_show_seats_remaining',
						'label'         => __( 'Show seats remaining in date picker', 'class-bookings-with-stripe' ),
						'name'          => 'show_seats_remaining',
						'type'          => 'true_false',
						'default_value' => 1,
						'ui'            => 1,
						'instructions'  => __( 'When enabled, each date includes the number of seats left.', 'class-bookings-with-stripe' ),
						'wrapper'       => [
							'width' => '25',
						],
						'conditional_logic' => $internal_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_class_upcoming_dates_count',
						'label'         => __( 'Dates shown in booking dropdown', 'class-bookings-with-stripe' ),
						'name'          => 'upcoming_dates_count',
						'type'          => 'number',
						'default_value' => 3,
						'min'           => 1,
						'step'          => 1,
						'instructions'  => __( 'How many upcoming dates appear in this class’s booking form (independent per class).', 'class-bookings-with-stripe' ),
						'wrapper'       => [
							'width' => '25',
						],
						'conditional_logic' => $recurring_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_location',
						'label'         => __( 'Location description', 'class-bookings-with-stripe' ),
						'name'          => 'location',
						'type'          => 'text',
						'instructions'  => __( 'Optional, e.g. "Orpington Studio".', 'class-bookings-with-stripe' ),
						'required'      => 0,
						'wrapper'       => [
							'width' => '25',
						],
						'conditional_logic' => $internal_booking_condition,
					],
					[
						'key'           => 'field_clasbowi_description',
						'label'         => __( 'Description', 'class-bookings-with-stripe' ),
						'name'          => 'description',
						'type'          => 'wysiwyg',
						'instructions'  => __( 'Shown on the booking form and in confirmation emails.', 'class-bookings-with-stripe' ),
						'tabs'          => 'visual',
						'toolbar'       => 'basic',
						'media_upload'  => 0,
						'conditional_logic' => $internal_booking_condition,
					],
					$cancelled_dates_field,
				],
				'location' => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => CPT::CLASS_PT,
						],
					],
				],
				'menu_order' => 0,
				'position'   => 'normal',
			]
		);

		// --- Class listing image (sidebar, own metabox) ---
		acf_add_local_field_group(
			[
				'key'             => 'group_clasbowi_class_sidebar_image',
				'title'           => __( 'Listing image', 'class-bookings-with-stripe' ),
				'fields'          => [
					[
						'key'           => 'field_clasbowi_class_image',
						'label'         => __( 'Image', 'class-bookings-with-stripe' ),
						'name'          => 'class_image',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
						'instructions'  => __( 'Optional. Used only in the WordPress admin — appears in the Classes list table.', 'class-bookings-with-stripe' ),
					],
				],
				'location'        => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => CPT::CLASS_PT,
						],
					],
				],
				'menu_order'      => 0,
				'position'        => 'side',
				'style'           => 'default',
				'label_placement' => 'top',
			]
		);

		// --- Booking detail (read-only-ish) ---
		acf_add_local_field_group(
			[
				'key'      => 'group_clasbowi_booking',
				'title'    => __( 'Booking details', 'class-bookings-with-stripe' ),
				'fields'   => [
					[
						'key'       => 'field_clasbowi_b_summary',
						'label'     => __( 'Summary', 'class-bookings-with-stripe' ),
						'name'      => '_clasbowi_summary',
						'type'      => 'message',
						'message'   => '',
						'new_lines' => '',
						'esc_html'  => 0,
					],
				],
				'location' => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => CPT::BOOKING_PT,
						],
					],
				],
			]
		);

		// --- Settings options page ---
		acf_add_local_field_group(
			[
				'key'      => 'group_clasbowi_settings',
				'title'    => __( 'Settings', 'class-bookings-with-stripe' ),
				'fields'   => [
					[
						'key'   => 'field_clasbowi_tab_stripe',
						'label' => __( 'Stripe', 'class-bookings-with-stripe' ),
						'type'  => 'tab',
					],
					[
						'key'           => 'field_clasbowi_stripe_mode',
						'label'         => __( 'Mode', 'class-bookings-with-stripe' ),
						'name'          => 'stripe_mode',
						'type'          => 'select',
						'choices'       => [
							'test' => __( 'Test', 'class-bookings-with-stripe' ),
							'live' => __( 'Live', 'class-bookings-with-stripe' ),
						],
						'default_value' => 'test',
						'allow_null'    => 0,
					],
					[
						'key'           => 'field_clasbowi_stripe_item_title_template',
						'label'         => __( 'Stripe item title template', 'class-bookings-with-stripe' ),
						'name'          => 'stripe_item_title_template',
						'type'          => 'text',
						'default_value' => '{class_name} — {class_date}, {class_time}',
						'instructions'  => __( 'Supports placeholders: {class_name}, {class_date}, {class_time}, {location}, {seats}, {customer_name}, {booking_id}.', 'class-bookings-with-stripe' ),
					],
					[
						'key'     => 'field_clasbowi_pub_test',
						'label'   => __( 'Publishable key (test)', 'class-bookings-with-stripe' ),
						'name'    => 'stripe_pub_key_test',
						'type'    => 'text',
						'wrapper' => [
							'width' => '50',
						],
					],
					[
						'key'     => 'field_clasbowi_secret_test',
						'label'   => __( 'Secret key (test)', 'class-bookings-with-stripe' ),
						'name'    => 'stripe_secret_key_test',
						'type'    => 'password',
						'wrapper' => [
							'width' => '50',
						],
					],
					[
						'key'     => 'field_clasbowi_pub_live',
						'label'   => __( 'Publishable key (live)', 'class-bookings-with-stripe' ),
						'name'    => 'stripe_pub_key_live',
						'type'    => 'text',
						'wrapper' => [
							'width' => '50',
						],
					],
					[
						'key'     => 'field_clasbowi_secret_live',
						'label'   => __( 'Secret key (live)', 'class-bookings-with-stripe' ),
						'name'    => 'stripe_secret_key_live',
						'type'    => 'password',
						'wrapper' => [
							'width' => '50',
						],
					],
					[
						'key'          => 'field_clasbowi_webhook_secret',
						'label'        => __( 'Webhook signing secret', 'class-bookings-with-stripe' ),
						'name'         => 'stripe_webhook_secret',
						'type'         => 'password',
						'instructions' => sprintf(
							/* translators: %s: REST webhook URL */
							__( 'Paste the signing secret from Stripe after you add the webhook endpoint. Endpoint URL: %s (see Help → Stripe webhooks for full steps).', 'class-bookings-with-stripe' ),
							rest_url( CLASBOWI_REST_NS . '/stripe-webhook' )
						),
					],
					[
						'key'          => 'field_clasbowi_webhook_url',
						'label'        => __( 'Webhook endpoint URL', 'class-bookings-with-stripe' ),
						'name'         => '_clasbowi_webhook_url_display',
						'type'         => 'message',
						'message'      => self::webhook_url_message(),
					],
					[
						'key'   => 'field_clasbowi_tab_emails',
						'label' => __( 'Emails', 'class-bookings-with-stripe' ),
						'type'  => 'tab',
					],
					[
						'key'           => 'field_clasbowi_admin_email',
						'label'         => __( 'Admin notification email', 'class-bookings-with-stripe' ),
						'name'          => 'admin_email',
						'type'          => 'email',
						'instructions'  => __( 'Where booking notifications are sent. Defaults to the WordPress admin email.', 'class-bookings-with-stripe' ),
					],
					[
						'key'     => 'field_clasbowi_email_wp_mail_note',
						'label'   => __( 'How emails are sent', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_email_wp_mail_note',
						'type'    => 'message',
						'message' => '<p class="acf-message">' . esc_html__( 'Booking messages are always sent with WordPress wp_mail(). Configure From address and SMTP through your mail plugin or wp_mail filters; this plugin does not set its own From headers.', 'class-bookings-with-stripe' ) . '</p>',
					],
					[
						'key'          => 'field_clasbowi_merge_tags',
						'label'        => __( 'Available merge tags', 'class-bookings-with-stripe' ),
						'name'         => '_clasbowi_merge_tags',
						'type'         => 'message',
						'message'      => self::merge_tags_message(),
					],
					[
						'key'           => 'field_clasbowi_cust_subject',
						'label'         => __( 'Customer email subject', 'class-bookings-with-stripe' ),
						'name'          => 'customer_email_subject',
						'type'          => 'text',
						'default_value' => 'Your booking is confirmed: {class_name} on {class_date}',
					],
					[
						'key'           => 'field_clasbowi_cust_body',
						'label'         => __( 'Customer email body', 'class-bookings-with-stripe' ),
						'name'          => 'customer_email_body',
						'type'          => 'wysiwyg',
						'tabs'          => 'visual',
						'toolbar'       => 'basic',
						'media_upload'  => 0,
						'default_value' => self::default_customer_email(),
					],
					[
						'key'           => 'field_clasbowi_admin_subject',
						'label'         => __( 'Admin email subject', 'class-bookings-with-stripe' ),
						'name'          => 'admin_email_subject',
						'type'          => 'text',
						'default_value' => 'New booking: {customer_name} for {class_name} on {class_date}',
					],
					[
						'key'           => 'field_clasbowi_admin_body',
						'label'         => __( 'Admin email body', 'class-bookings-with-stripe' ),
						'name'          => 'admin_email_body',
						'type'          => 'wysiwyg',
						'tabs'          => 'visual',
						'toolbar'       => 'basic',
						'media_upload'  => 0,
						'default_value' => self::default_admin_email(),
					],
					[
						'key'   => 'field_clasbowi_tab_pages',
						'label' => __( 'Form extras', 'class-bookings-with-stripe' ),
						'type'  => 'tab',
					],
					[
						'key'     => 'field_clasbowi_form_extras_note',
						'label'   => __( 'Using ACF fields in booking forms', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_form_extras_note',
						'type'    => 'message',
						'message' => sprintf(
							'%s<br><br><strong>%s</strong><br><code>%s</code>',
							esc_html__( 'You can add custom ACF fields to a booking form by creating a new ACF Field Group and setting its Location Rule to "Class Bookings with Stripe → Booking form class ID". Then choose the class ID the field group should appear on.', 'class-bookings-with-stripe' ),
							esc_html__( 'Tip:', 'class-bookings-with-stripe' ),
							esc_html__( 'Supported field types include text, email, number, url, textarea, select, radio and true/false.', 'class-bookings-with-stripe' )
						),
					],
					[
						'key'           => 'field_clasbowi_enable_waiver',
						'label'         => __( 'Require waiver acceptance', 'class-bookings-with-stripe' ),
						'name'          => 'enable_waiver',
						'type'          => 'true_false',
						'default_value' => 0,
						'ui'            => 1,
						'instructions'  => __( 'Adds a required checkbox to the booking form. Customers must accept before payment.', 'class-bookings-with-stripe' ),
					],
					[
						'key'               => 'field_clasbowi_waiver_label',
						'label'             => __( 'Waiver checkbox label', 'class-bookings-with-stripe' ),
						'name'              => 'waiver_label',
						'type'              => 'wysiwyg',
						'default_value'     => __( 'I confirm I have read and accept the class waiver and participate at my own risk.', 'class-bookings-with-stripe' ),
						'tabs'              => 'visual',
						'toolbar'           => 'basic',
						'media_upload'      => 0,
						'instructions'        => __( 'HTML is allowed (links, lists, emphasis). Shown next to the waiver checkbox on the booking form.', 'class-bookings-with-stripe' ),
						'conditional_logic' => [
							[
								[
									'field'    => 'field_clasbowi_enable_waiver',
									'operator' => '==',
									'value'    => '1',
								],
							],
						],
					],
					[
						'key'               => 'field_clasbowi_waiver_page_url',
						'label'             => __( 'Waiver page URL', 'class-bookings-with-stripe' ),
						'name'              => 'waiver_page_url',
						'type'              => 'url',
						'placeholder'       => 'https://',
						'instructions'      => __( 'Optional full waiver policy page. Shown as a separate link below the checkbox label.', 'class-bookings-with-stripe' ),
						'conditional_logic' => [
							[
								[
									'field'    => 'field_clasbowi_enable_waiver',
									'operator' => '==',
									'value'    => '1',
								],
							],
						],
					],
					[
						'key'           => 'field_clasbowi_enable_mailchimp_optin',
						'label'         => __( 'Show Mailchimp opt-in checkbox', 'class-bookings-with-stripe' ),
						'name'          => 'enable_mailchimp_optin',
						'type'          => 'true_false',
						'default_value' => 0,
						'ui'            => 1,
						'instructions'  => __( 'Adds an optional mailing-list consent checkbox on the booking form.', 'class-bookings-with-stripe' ),
					],
					[
						'key'               => 'field_clasbowi_mailchimp_optin_label',
						'label'             => __( 'Mailchimp opt-in label', 'class-bookings-with-stripe' ),
						'name'              => 'mailchimp_optin_label',
						'type'              => 'textarea',
						'default_value'     => __( 'Yes, I would like to join the mailing list for class updates and news.', 'class-bookings-with-stripe' ),
						'rows'              => 3,
						'new_lines'         => '',
						'conditional_logic' => [
							[
								[
									'field'    => 'field_clasbowi_enable_mailchimp_optin',
									'operator' => '==',
									'value'    => '1',
								],
							],
						],
					],
					[
						'key'               => 'field_clasbowi_mailchimp_api_key',
						'label'             => __( 'Mailchimp API key', 'class-bookings-with-stripe' ),
						'name'              => 'mailchimp_api_key',
						'type'              => 'password',
						'instructions'      => __( 'From Mailchimp account settings. Format typically ends with datacenter suffix, e.g. us6.', 'class-bookings-with-stripe' ),
						'conditional_logic' => [
							[
								[
									'field'    => 'field_clasbowi_enable_mailchimp_optin',
									'operator' => '==',
									'value'    => '1',
								],
							],
						],
					],
					[
						'key'               => 'field_clasbowi_mailchimp_audience_id',
						'label'             => __( 'Mailchimp Audience ID', 'class-bookings-with-stripe' ),
						'name'              => 'mailchimp_audience_id',
						'type'              => 'text',
						'instructions'      => __( 'The Audience/List ID to subscribe customers into.', 'class-bookings-with-stripe' ),
						'conditional_logic' => [
							[
								[
									'field'    => 'field_clasbowi_enable_mailchimp_optin',
									'operator' => '==',
									'value'    => '1',
								],
							],
						],
					],
					[
						'key'               => 'field_clasbowi_mailchimp_double_optin',
						'label'             => __( 'Mailchimp double opt-in', 'class-bookings-with-stripe' ),
						'name'              => 'mailchimp_double_optin',
						'type'              => 'true_false',
						'default_value'     => 1,
						'ui'                => 1,
						'instructions'      => __( 'If enabled, contacts are added as pending and must confirm by email.', 'class-bookings-with-stripe' ),
						'conditional_logic' => [
							[
								[
									'field'    => 'field_clasbowi_enable_mailchimp_optin',
									'operator' => '==',
									'value'    => '1',
								],
							],
						],
					],
					[
						'key'   => 'field_clasbowi_tab_pages_2',
						'label' => __( 'Result pages', 'class-bookings-with-stripe' ),
						'type'  => 'tab',
					],
					[
						'key'           => 'field_clasbowi_success_page',
						'label'         => __( 'Booking Confirmed page', 'class-bookings-with-stripe' ),
						'name'          => 'success_page',
						'type'          => 'post_object',
						'post_type'     => [ 'page' ],
						'return_format' => 'id',
						'allow_null'    => 1,
						'instructions'  => __( 'Customer is redirected here after a successful Stripe payment. Auto-created on activation.', 'class-bookings-with-stripe' ),
					],
					[
						'key'           => 'field_clasbowi_cancel_page',
						'label'         => __( 'Booking Cancelled page', 'class-bookings-with-stripe' ),
						'name'          => 'cancel_page',
						'type'          => 'post_object',
						'post_type'     => [ 'page' ],
						'return_format' => 'id',
						'allow_null'    => 1,
					],
					[
						'key'           => 'field_clasbowi_error_page',
						'label'         => __( 'Booking Error page', 'class-bookings-with-stripe' ),
						'name'          => 'error_page',
						'type'          => 'post_object',
						'post_type'     => [ 'page' ],
						'return_format' => 'id',
						'allow_null'    => 1,
					],
					[
						'key'   => 'field_clasbowi_tab_developer',
						'label' => __( 'Developer', 'class-bookings-with-stripe' ),
						'type'  => 'tab',
					],
					[
						'key'     => 'field_clasbowi_dev_webhooks',
						'label'   => __( 'Webhooks and payment state', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_dev_webhooks',
						'type'    => 'message',
						'message' => self::developer_webhooks_message(),
					],
					[
						'key'     => 'field_clasbowi_dev_templates',
						'label'   => __( 'Template overrides', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_dev_templates',
						'type'    => 'message',
						'message' => self::developer_templates_message(),
					],
					[
						'key'     => 'field_clasbowi_dev_hooks',
						'label'   => __( 'Hooks and extension points', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_dev_hooks',
						'type'    => 'message',
						'message' => self::developer_hooks_message(),
					],
					[
						'key'   => 'field_clasbowi_tab_help',
						'label' => __( 'Help', 'class-bookings-with-stripe' ),
						'type'  => 'tab',
					],
					[
						'key'     => 'field_clasbowi_help_intro',
						'label'   => __( 'Overview', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_help_intro',
						'type'    => 'message',
						'message' => self::help_intro_message(),
					],
					[
						'key'     => 'field_clasbowi_help_stripe_keys',
						'label'   => __( 'Stripe API keys', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_help_stripe_keys',
						'type'    => 'message',
						'message' => self::help_stripe_keys_message(),
					],
					[
						'key'     => 'field_clasbowi_help_webhooks',
						'label'   => __( 'Stripe webhooks', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_help_webhooks',
						'type'    => 'message',
						'message' => self::help_webhooks_detail_message(),
					],
					[
						'key'     => 'field_clasbowi_help_email_smtp',
						'label'   => __( 'Email & WP Mail SMTP', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_help_email_smtp',
						'type'    => 'message',
						'message' => self::help_email_smtp_message(),
					],
					[
						'key'     => 'field_clasbowi_help_next_steps',
						'label'   => __( 'Classes & publishing', 'class-bookings-with-stripe' ),
						'name'    => '_clasbowi_help_next_steps',
						'type'    => 'message',
						'message' => self::help_next_steps_message(),
					],
				],
				'location' => [
					[
						[
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => self::SETTINGS_MENU_SLUG,
						],
					],
				],
			]
		);
	}

	private static function webhook_url_message(): string {
		$url = rest_url( CLASBOWI_REST_NS . '/stripe-webhook' );
		return sprintf(
			'<code style="user-select:all;">%s</code><br><small>%s</small>',
			esc_html( $url ),
			esc_html__( 'Add this URL to Stripe → Developers → Webhooks. Listen for events: checkout.session.completed, checkout.session.expired, checkout.session.async_payment_failed.', 'class-bookings-with-stripe' )
		);
	}

	private static function merge_tags_message(): string {
		$tags = [
			'{customer_name}',
			'{customer_email}',
			'{class_name}',
			'{class_date}',
			'{class_time}',
			'{location}',
			'{duration}',
			'{price}',
			'{seats}',
			'{amount_total}',
			'{booking_id}',
			'{description}',
			'{extra_fields}',
			'{acf:field_xxxxx}',
		];
		return '<code>' . esc_html( implode( '   ', $tags ) ) . '</code><br><small>' . esc_html__( 'For booking-form ACF extras, use {acf:FIELD_KEY} (or {FIELD_KEY}). Example: {acf:field_abc123}.', 'class-bookings-with-stripe' ) . '</small>';
	}

	private static function default_customer_email(): string {
		return "Hi {customer_name},\n\nThanks for booking — we can't wait to see you!\n\nYour booking:\n• Class: {class_name}\n• When: {class_date} at {class_time}\n• Where: {location}\n• Duration: {duration} minutes\n• Seats: {seats}\n• Total paid: {amount_total}\n\nBooking reference: {booking_id}\n\nIf you need to cancel or change anything, just reply to this email.\n\nNamaste,\nSoulful Yoga";
	}

	private static function default_admin_email(): string {
		return "New booking received.\n\n• Customer: {customer_name} <{customer_email}>\n• Class: {class_name}\n• When: {class_date} at {class_time}\n• Where: {location}\n• Seats: {seats}\n• Total: {amount_total}\n• Booking reference: {booking_id}";
	}

	/**
	 * Message fields default to wpautop(), which wraps our hero markup in &lt;p&gt; and breaks the layout.
	 *
	 * @param array<string, mixed> $field
	 * @return array<string, mixed>
	 */
	public static function filter_booking_summary_field_format( array $field ): array {
		$field['new_lines'] = '';
		$field['esc_html']  = false;
		return $field;
	}

	/**
	 * Inject a read-only booking summary into the booking CPT edit screen.
	 *
	 * @param array<string,mixed> $field
	 * @return array<string,mixed>
	 */
	public static function populate_booking_summary_field( array $field ): array {
		$post_id = get_the_ID();
		if ( ! $post_id || CPT::BOOKING_PT !== get_post_type( $post_id ) ) {
			return $field;
		}

		$meta        = Bookings::get_meta( (int) $post_id );
		$class_id    = (int) $meta['class_id'];
		$class_title = $class_id ? get_the_title( $class_id ) : __( 'Unknown class', 'class-bookings-with-stripe' );
		$class_data  = $class_id ? Helpers::get_class_data( $class_id ) : null;
		$status_raw  = (string) ( $meta['status'] ?: '' );
		$status_slug = sanitize_html_class( $status_raw ?: 'unknown' );

		if ( Bookings::STATUS_PAID === $status_raw ) {
			$status_label = __( 'Paid', 'class-bookings-with-stripe' );
		} elseif ( Bookings::STATUS_PENDING === $status_raw ) {
			$status_label = __( 'Pending', 'class-bookings-with-stripe' );
		} elseif ( Bookings::STATUS_EXPIRED === $status_raw ) {
			$status_label = __( 'Expired', 'class-bookings-with-stripe' );
		} elseif ( Bookings::STATUS_REFUNDED === $status_raw ) {
			$status_label = __( 'Refunded', 'class-bookings-with-stripe' );
		} elseif ( '' !== $status_raw ) {
			$status_label = ucfirst( $status_raw );
		} else {
			$status_label = __( 'Unknown', 'class-bookings-with-stripe' );
		}

		$class_edit = $class_id && current_user_can( 'edit_post', $class_id )
			? get_edit_post_link( $class_id, 'raw' )
			: '';
		$mailto     = (string) $meta['customer_email'] !== ''
			? 'mailto:' . sanitize_email( (string) $meta['customer_email'] )
			: '';

		ob_start();
		?>
		<div class="cbfs-admin-summary cbfs-admin-summary--modern">
			<header class="cbfs-admin-summary__hero">
				<div class="cbfs-admin-summary__hero-main">
					<p class="cbfs-admin-summary__kicker"><?php esc_html_e( 'Booking reference', 'class-bookings-with-stripe' ); ?></p>
					<p class="cbfs-admin-summary__id">#<?php echo esc_html( (string) (int) $post_id ); ?></p>
				</div><span class="cbfs-admin-summary__status cbfs-admin-summary__status--<?php echo esc_attr( $status_slug ); ?>"><?php echo esc_html( $status_label ); ?></span>
			</header>

			<div class="cbfs-admin-summary__kv">
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Class', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value">
						<?php if ( $class_edit ) : ?>
							<a href="<?php echo esc_url( $class_edit ); ?>"><?php echo esc_html( (string) $class_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( (string) $class_title ); ?>
						<?php endif; ?>
					</div>
				</div>
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'When', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value">
						<?php
						$date_fmt = Helpers::format_date( (string) $meta['class_date'] );
						$time_fmt = ( $class_data && ! empty( $class_data['start_time'] ) )
							? Helpers::format_time( (string) $class_data['start_time'] )
							: '';
						if ( '' !== $time_fmt ) {
							echo esc_html(
								sprintf(
									/* translators: 1: formatted date, 2: formatted time */
									__( '%1$s at %2$s', 'class-bookings-with-stripe' ),
									$date_fmt,
									$time_fmt
								)
							);
						} else {
							echo esc_html( $date_fmt );
						}
						?>
					</div>
				</div>
				<?php if ( $class_data && ! empty( $class_data['location'] ) ) : ?>
					<div class="cbfs-admin-summary__kv-row">
						<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Where', 'class-bookings-with-stripe' ); ?></span>
						<div class="cbfs-admin-summary__kv-value"><?php echo esc_html( (string) $class_data['location'] ); ?></div>
					</div>
				<?php endif; ?>
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Seats', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value"><?php echo esc_html( (string) (int) $meta['seats'] ); ?></div>
				</div>
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Total', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value cbfs-admin-summary__amount"><?php echo esc_html( Helpers::format_price( ( (int) $meta['amount_total_pence'] ) / 100 ) ); ?></div>
				</div>
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Customer', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value"><?php echo esc_html( (string) $meta['customer_name'] ); ?></div>
				</div>
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Email', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value">
						<?php if ( $mailto ) : ?>
							<a href="<?php echo esc_url( $mailto ); ?>"><?php echo esc_html( (string) $meta['customer_email'] ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</div>
				</div>
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Waiver', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value"><?php echo esc_html( ! empty( $meta['waiver_accepted'] ) ? __( 'Accepted', 'class-bookings-with-stripe' ) : __( 'Not recorded', 'class-bookings-with-stripe' ) ); ?></div>
				</div>
				<div class="cbfs-admin-summary__kv-row">
					<span class="cbfs-admin-summary__kv-label"><?php esc_html_e( 'Mailing list', 'class-bookings-with-stripe' ); ?></span>
					<div class="cbfs-admin-summary__kv-value"><?php echo esc_html( ! empty( $meta['mailchimp_opt_in'] ) ? __( 'Opted in', 'class-bookings-with-stripe' ) : __( 'No', 'class-bookings-with-stripe' ) ); ?></div>
				</div>
			</div>

			<div class="cbfs-admin-summary__stripe">
				<h4 class="cbfs-admin-summary__stripe-title"><?php esc_html_e( 'Stripe', 'class-bookings-with-stripe' ); ?></h4>
				<div class="cbfs-admin-summary__mono-block">
					<span class="cbfs-admin-summary__mono-label"><?php esc_html_e( 'Session', 'class-bookings-with-stripe' ); ?></span>
					<code class="cbfs-admin-summary__code"><?php echo esc_html( (string) $meta['stripe_session_id'] ) ?: '—'; ?></code>
				</div>
				<div class="cbfs-admin-summary__mono-block">
					<span class="cbfs-admin-summary__mono-label"><?php esc_html_e( 'Payment intent', 'class-bookings-with-stripe' ); ?></span>
					<code class="cbfs-admin-summary__code"><?php echo esc_html( (string) $meta['stripe_payment_intent'] ) ?: '—'; ?></code>
				</div>
			</div>

			<?php
			$extra_rows = Extra_Fields::display_rows( $class_id, (string) ( $meta['extra_fields_json'] ?? '' ) );
			if ( ! empty( $extra_rows ) ) :
				?>
				<div class="cbfs-admin-summary__extras">
					<h4 class="cbfs-admin-summary__extras-title"><?php esc_html_e( 'Additional fields', 'class-bookings-with-stripe' ); ?></h4>
					<table class="cbfs-admin-summary__table">
						<tbody>
						<?php foreach ( $extra_rows as $row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></th>
								<td><?php echo esc_html( (string) ( $row['value'] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
		$field['message'] = (string) ob_get_clean();
		return $field;
	}

	private static function developer_webhooks_message(): string {
		$home        = home_url( '/' );
		$rest_base   = rest_url( CLASBOWI_REST_NS );
		$webhook_url = rest_url( CLASBOWI_REST_NS . '/stripe-webhook' );
		$checkout    = rest_url( CLASBOWI_REST_NS . '/checkout' );

		$ex_stripe_cli = 'stripe listen --forward-to ' . $webhook_url;

		ob_start();
		?>
<div class="clasbowi-doc">
	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Payment flow', 'class-bookings-with-stripe' ); ?></h3>
	<ol class="clasbowi-doc__ol">
		<li><?php esc_html_e( 'The booking form POSTs to the REST checkout route with customer and class details.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'The plugin creates a pending booking (soft hold) and a Stripe Checkout Session, then returns a redirect URL.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'After payment, Stripe sends webhook events to your site; the plugin marks the booking paid and sends emails.', 'class-bookings-with-stripe' ); ?></li>
	</ol>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'REST routes (namespace clasbowi/v1)', 'class-bookings-with-stripe' ); ?></h3>
	<table class="clasbowi-doc__table">
		<thead><tr><th><?php esc_html_e( 'Method', 'class-bookings-with-stripe' ); ?></th><th><?php esc_html_e( 'Path', 'class-bookings-with-stripe' ); ?></th><th><?php esc_html_e( 'Role', 'class-bookings-with-stripe' ); ?></th></tr></thead>
		<tbody>
			<tr><td>POST</td><td><code>/checkout</code></td><td><?php esc_html_e( 'Create session (browser / frontend).', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td>POST</td><td><code>/stripe-webhook</code></td><td><?php esc_html_e( 'Stripe-signed events only.', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td>GET</td><td><code>/booking-status</code></td><td><?php esc_html_e( 'Poll booking state after redirect. Requires session ID and the per-booking status token from the success URL (or manage_options).', 'class-bookings-with-stripe' ); ?></td></tr>
		</tbody>
	</table>
	<p class="clasbowi-doc__note"><?php esc_html_e( 'Full base URL (copy-friendly):', 'class-bookings-with-stripe' ); ?></p>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( untrailingslashit( $rest_base ) ); ?></code></pre>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Webhook endpoint', 'class-bookings-with-stripe' ); ?></h3>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $webhook_url ); ?></code></pre>
	<p class="clasbowi-doc__p"><strong><?php esc_html_e( 'Required event types:', 'class-bookings-with-stripe' ); ?></strong>
		<code>checkout.session.completed</code>,
		<code>checkout.session.expired</code>,
		<code>checkout.session.async_payment_failed</code>
	</p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Local testing with Stripe CLI', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Forward Stripe webhook traffic to your local or tunnelled WordPress URL:', 'class-bookings-with-stripe' ); ?></p>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $ex_stripe_cli ); ?></code></pre>
	<p class="clasbowi-doc__muted"><?php esc_html_e( 'Use the signing secret the CLI prints (starts with whsec_) in this plugin’s Webhook signing secret field while testing, or create a separate test endpoint in the Dashboard.', 'class-bookings-with-stripe' ); ?></p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Example: probing checkout (expect validation errors without a full body)', 'class-bookings-with-stripe' ); ?></h3>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( 'curl -i -X POST ' . $checkout . " \\\n  -H 'Content-Type: application/json' \\\n  -d '{}'" ); ?></code></pre>
	<p class="clasbowi-doc__muted"><?php esc_html_e( 'A real request is issued by the plugin’s JavaScript with class ID, date, seats, nonce, etc. Use this only to verify the route responds on your host.', 'class-bookings-with-stripe' ); ?></p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Site URL dependency', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Stripe return URLs and webhook targets are built from your WordPress site URL. Ensure Settings → General has the correct address for the environment you are testing.', 'class-bookings-with-stripe' ); ?></p>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $home ); ?></code></pre>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function developer_templates_message(): string {
		$filter_layout = <<<'PHP'
add_filter( 'clasbowi_template_path', function ( $path, $relative, $context ) {
	if ( 'booking' === $context && 'booking-form.php' === $relative ) {
		return get_stylesheet_directory() . '/class-bookings-with-stripe/booking-form.php';
	}
	return $path;
}, 10, 3 );
PHP;
		$filter_component = <<<'PHP'
add_filter( 'clasbowi_component_path', function ( $path, $layout, $slug ) {
	if ( 'booking-form' === $layout && 'email-field' === $slug ) {
		return get_stylesheet_directory() . '/class-bookings-with-stripe/booking-form/email-field.php';
	}
	return $path;
}, 10, 3 );
PHP;
		$layout_example = <<<'PHP'
// booking-form.php — layout HTML with components embedded:
<form class="cbfs-form__form">
	<div class="cbfs-form__grid cbfs-form__grid--2">
		<?php $view->render( 'name-field' ); ?>
		<?php $view->render( 'email-field' ); ?>
	</div>
	<?php $view->render( 'date-field' ); ?>
</form>
PHP;

		ob_start();
		?>
<div class="clasbowi-doc">
	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Component-based templates', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Layout files contain the HTML structure. Components render a single field or content block only — place $view->render() calls where you want each piece in the layout.', 'class-bookings-with-stripe' ); ?></p>
	<ul class="clasbowi-doc__ul">
		<li><code>class-bookings-with-stripe/booking-form.php</code> — <?php esc_html_e( 'Form layout HTML (grid, card, form tags).', 'class-bookings-with-stripe' ); ?></li>
		<li><code>class-bookings-with-stripe/booking-form/{slug}.php</code> — <?php esc_html_e( 'Components: name-field, email-field, submit-button, waiver, etc.', 'class-bookings-with-stripe' ); ?></li>
		<li><code>class-bookings-with-stripe/booking-status.php</code> — <?php esc_html_e( 'Status layout HTML.', 'class-bookings-with-stripe' ); ?></li>
		<li><code>class-bookings-with-stripe/booking-status/{slug}.php</code> — <?php esc_html_e( 'Status components: title, lede, details-list, hint, etc.', 'class-bookings-with-stripe' ); ?></li>
		<li><code>class-bookings-with-stripe/assets/components/booking-form/{slug}.css</code> — <?php esc_html_e( 'Optional per-component CSS (auto-enqueued).', 'class-bookings-with-stripe' ); ?></li>
	</ul>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Layout example', 'class-bookings-with-stripe' ); ?></h3>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $layout_example ); ?></code></pre>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Filter: clasbowi_template_path', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Override the layout file path. Arguments: $path, $relative, $context (booking | status).', 'class-bookings-with-stripe' ); ?></p>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $filter_layout ); ?></code></pre>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Filter: clasbowi_component_path', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Override a single component partial. Arguments: $path, $layout (booking-form | booking-status), $slug.', 'class-bookings-with-stripe' ); ?></p>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $filter_component ); ?></code></pre>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function developer_hooks_message(): string {
		$ex_filter_html = <<<'PHP'
add_filter( 'clasbowi_booking_html', function ( $html, $template_args, $template_path ) {
	// Inspect: $template_args['class_data'], ['dates'], ['atts'].
	return $html;
}, 10, 3 );
PHP;
		$ex_filter_labels = <<<'PHP'
add_filter( 'clasbowi_booking_labels', function ( $labels, $class_data, $dates ) {
	$labels['book_button'] = __( 'Pay securely', 'your-textdomain' );
	return $labels;
}, 10, 3 );
PHP;
		$ex_action = <<<'PHP'
add_action( 'clasbowi_booking_template_start', function ( $class_data, $dates ) {
	// Runs once before the form layout renders.
}, 10, 2 );
PHP;

		ob_start();
		?>
<div class="clasbowi-doc">
	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Filters (modify data or output)', 'class-bookings-with-stripe' ); ?></h3>
	<table class="clasbowi-doc__table">
		<thead><tr><th><?php esc_html_e( 'Hook', 'class-bookings-with-stripe' ); ?></th><th><?php esc_html_e( 'Typical use', 'class-bookings-with-stripe' ); ?></th></tr></thead>
		<tbody>
			<tr><td><code>clasbowi_booking_template_args</code></td><td><?php esc_html_e( 'Adjust $class_data, $dates, or shortcode $atts before the template loads.', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td><code>clasbowi_status_template_args</code></td><td><?php esc_html_e( 'Same for booking status / result pages.', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td><code>clasbowi_booking_html</code></td><td><?php esc_html_e( 'Replace or wrap final booking form HTML.', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td><code>clasbowi_status_html</code></td><td><?php esc_html_e( 'Replace or wrap status page HTML.', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td><code>clasbowi_booking_labels</code></td><td><?php esc_html_e( 'Change button copy, hints, field labels (3rd param: $dates).', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td><code>clasbowi_booking_title</code></td><td><?php esc_html_e( 'Filter heading text; receives title string + $class_data.', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td><code>clasbowi_component_path</code></td><td><?php esc_html_e( 'Point a component slug at a custom PHP partial.', 'class-bookings-with-stripe' ); ?></td></tr>
			<tr><td><code>clasbowi_template_path</code></td><td><?php esc_html_e( 'Override layout template path (booking-form.php, booking-status.php).', 'class-bookings-with-stripe' ); ?></td></tr>
		</tbody>
	</table>

	<h4 class="clasbowi-doc__h4"><?php esc_html_e( 'Example: wrap booking HTML', 'class-bookings-with-stripe' ); ?></h4>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $ex_filter_html ); ?></code></pre>

	<h4 class="clasbowi-doc__h4"><?php esc_html_e( 'Example: rename the pay button', 'class-bookings-with-stripe' ); ?></h4>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $ex_filter_labels ); ?></code></pre>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Actions (layout boundaries)', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><code>clasbowi_booking_template_start</code>, <code>clasbowi_booking_template_end</code>, <code>clasbowi_status_template_start</code>, <code>clasbowi_status_template_end</code> — <?php esc_html_e( 'fire once around each screen layout. For field order or structure, override booking-form.php; for a single field, override its component file.', 'class-bookings-with-stripe' ); ?></p>
	<h4 class="clasbowi-doc__h4"><?php esc_html_e( 'Example: run code before the form layout', 'class-bookings-with-stripe' ); ?></h4>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $ex_action ); ?></code></pre>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'ACF fields on the booking form', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Create a Field Group in ACF and set the location rule to “Class Bookings with Stripe → Booking form class ID”, then pick the Class post ID. Supported types include text, email, number, textarea, select, radio, true/false.', 'class-bookings-with-stripe' ); ?></p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Email merge tags in templates', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'See the Emails tab for the full list. For ACF extras on the form:', 'class-bookings-with-stripe' ); ?> <code>{acf:field_xxxxx}</code>, <code>{field_xxxxx}</code>, <?php esc_html_e( 'or', 'class-bookings-with-stripe' ); ?> <code>{extra_fields}</code> <?php esc_html_e( 'for a summary block.', 'class-bookings-with-stripe' ); ?></p>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function help_intro_message(): string {
		return self::help_plugin_meta_message()
			. '<div class="clasbowi-doc"><p class="clasbowi-doc__lead">'
			. esc_html__( 'Use the Help sections below for Stripe keys, webhooks, reliable email delivery, then publish your classes. The Developer tab documents REST routes, hooks, and theme overrides for custom builds.', 'class-bookings-with-stripe' )
			. '</p></div>';
	}

	private static function help_stripe_keys_message(): string {
		$dash_test = 'https://dashboard.stripe.com/test/apikeys';
		$dash_live = 'https://dashboard.stripe.com/apikeys';

		ob_start();
		?>
<div class="clasbowi-doc">
	<p class="clasbowi-doc__lead"><?php esc_html_e( 'Stripe has separate keys for test and live mode. This plugin’s “Mode” setting (Stripe tab) decides which pair is used for Checkout and API calls.', 'class-bookings-with-stripe' ); ?></p>
	<ol class="clasbowi-doc__ol clasbowi-doc__ol--spaced">
		<li>
			<strong><?php esc_html_e( 'Open the Stripe Dashboard', 'class-bookings-with-stripe' ); ?></strong>
			— <?php esc_html_e( 'Log in at stripe.com and ensure you are in the correct account.', 'class-bookings-with-stripe' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Turn on Test mode', 'class-bookings-with-stripe' ); ?></strong>
			— <?php esc_html_e( 'Use the “Test mode” toggle in the Dashboard while developing. Test card numbers (e.g. 4242…) only work when test mode is on.', 'class-bookings-with-stripe' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Go to Developers → API keys', 'class-bookings-with-stripe' ); ?></strong>
			— <?php esc_html_e( 'Test:', 'class-bookings-with-stripe' ); ?>
			<a href="<?php echo esc_url( $dash_test ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $dash_test ); ?></a>.
			<?php esc_html_e( 'Live:', 'class-bookings-with-stripe' ); ?>
			<a href="<?php echo esc_url( $dash_live ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $dash_live ); ?></a>.
		</li>
		<li>
			<strong><?php esc_html_e( 'Copy Publishable key and Secret key', 'class-bookings-with-stripe' ); ?></strong>
			— <?php esc_html_e( 'Publishable keys start with pk_test_ or pk_live_. Secret keys start with sk_test_ or sk_live_. Never expose the secret key in frontend code or public repos.', 'class-bookings-with-stripe' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Paste into WordPress', 'class-bookings-with-stripe' ); ?></strong>
			— <?php esc_html_e( 'Stripe tab → “Publishable key (test)” + “Secret key (test)” (or the live fields). Set “Mode” to Test while developing.', 'class-bookings-with-stripe' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Save settings', 'class-bookings-with-stripe' ); ?></strong>
			— <?php esc_html_e( 'Click Update / Save on this options page. Switch to Live mode only when you are ready for real charges and you have pasted live keys.', 'class-bookings-with-stripe' ); ?>
		</li>
	</ol>
	<p class="clasbowi-doc__note"><?php esc_html_e( 'If Checkout fails with an authentication error, double-check that the mode matches the keys (test keys only with Mode = Test).', 'class-bookings-with-stripe' ); ?></p>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function help_webhooks_detail_message(): string {
		$webhook_url = rest_url( CLASBOWI_REST_NS . '/stripe-webhook' );

		ob_start();
		?>
<div class="clasbowi-doc">
	<p class="clasbowi-doc__lead"><?php esc_html_e( 'Webhooks let Stripe notify WordPress when payment succeeds, sessions expire, or async payment fails. Without a working webhook and signing secret, bookings may stay “pending” after payment.', 'class-bookings-with-stripe' ); ?></p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Your endpoint URL (exact)', 'class-bookings-with-stripe' ); ?></h3>
	<pre class="clasbowi-doc__pre"><code><?php echo esc_html( $webhook_url ); ?></code></pre>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Troubleshooting', 'class-bookings-with-stripe' ); ?></h3>
	<ul class="clasbowi-doc__ul">
		<li>
			<strong><?php esc_html_e( 'Apache HTML 404 on /wp-json/…', 'class-bookings-with-stripe' ); ?></strong>
			<?php esc_html_e( 'If curl or Stripe gets an HTML “Not Found” page from Apache (not JSON from WordPress), the request never reached WordPress. Common causes:', 'class-bookings-with-stripe' ); ?>
			<ul class="clasbowi-doc__ul">
				<li><?php esc_html_e( 'Permalinks are set to Plain, or rewrite rules were never saved — go to Settings → Permalinks, choose something other than Plain (e.g. Post name), and click Save Changes once. That refreshes rules so /wp-json/… is routed to WordPress.', 'class-bookings-with-stripe' ); ?></li>
				<li><?php esc_html_e( 'Missing or ignored .htaccess rewrites in Docker — ensure the web server allows overrides (AllowOverride) for the document root so WordPress can write rewrite rules.', 'class-bookings-with-stripe' ); ?></li>
			</ul>
			<?php esc_html_e( 'Always paste the URL shown above: rest_url() matches your permalink structure (pretty /wp-json/… vs ?rest_route=… on Plain).', 'class-bookings-with-stripe' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( '400 JSON { "error": "invalid_signature" }', 'class-bookings-with-stripe' ); ?></strong>
			<?php esc_html_e( 'The route is working; Stripe must send a signed payload. A manual curl with an empty body will fail signature verification. Use Stripe CLI forward or a real Dashboard test event.', 'class-bookings-with-stripe' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Wrong host in redirects or Link headers', 'class-bookings-with-stripe' ); ?></strong>
			<?php esc_html_e( 'Settings → General → WordPress Address and Site Address should match the URL Stripe and browsers use (public hostname, tunnel URL, or IP:port). Mismatches break checkout return URLs and can confuse which webhook URL to register.', 'class-bookings-with-stripe' ); ?>
		</li>
	</ul>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Steps in Stripe Dashboard', 'class-bookings-with-stripe' ); ?></h3>
	<ol class="clasbowi-doc__ol clasbowi-doc__ol--spaced">
		<li><?php esc_html_e( 'Open Developers → Webhooks.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Click “Add endpoint” (or “+ Add” depending on UI).', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Endpoint URL: paste the URL above (must be publicly reachable over HTTPS in production).', 'class-bookings-with-stripe' ); ?></li>
		<li>
			<?php esc_html_e( 'Under “Events to send”, choose these event types (or an equivalent custom selection that includes them):', 'class-bookings-with-stripe' ); ?>
			<ul class="clasbowi-doc__ul">
				<li><code>checkout.session.completed</code></li>
				<li><code>checkout.session.expired</code></li>
				<li><code>checkout.session.async_payment_failed</code></li>
			</ul>
		</li>
		<li><?php esc_html_e( 'Save the endpoint. Open it and click “Reveal” under Signing secret — copy the whsec_… value.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'In WordPress → this page → Stripe tab → “Webhook signing secret”, paste that value and save.', 'class-bookings-with-stripe' ); ?></li>
	</ol>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Test vs live webhooks', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Stripe keeps separate webhook configurations for test and live. Create endpoints in both if you use both modes, each with its own signing secret, and paste the matching secret when you switch Mode in this plugin.', 'class-bookings-with-stripe' ); ?></p>

	<?php echo wp_kses_post( self::help_webhooks_localhost_message() ); ?>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Verify delivery', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'In Stripe → Webhooks → your endpoint → “Attempts” / logs, you should see 2xx responses after a test payment. If you see 403 or signature errors, the signing secret in WordPress does not match that endpoint.', 'class-bookings-with-stripe' ); ?></p>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Help tab: localhost / tunnel guidance for Stripe webhooks (HTML fragment).
	 */
	private static function help_webhooks_localhost_message(): string {
		$webhook_full         = rest_url( CLASBOWI_REST_NS . '/stripe-webhook' );
		$webhook_path_parsed  = wp_parse_url( $webhook_full, PHP_URL_PATH );
		$webhook_path_example = is_string( $webhook_path_parsed ) && '' !== $webhook_path_parsed
			? $webhook_path_parsed
			: '/wp-json/' . CLASBOWI_REST_NS . '/stripe-webhook';

		ob_start();
		?>
	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Localhost', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'For local development, Stripe’s servers cannot reach http://localhost or http://127.0.0.1 on your machine. You need a publicly reachable HTTPS URL that forwards traffic to the WordPress site that loads this plugin.', 'class-bookings-with-stripe' ); ?></p>

	<h4 class="clasbowi-doc__h4"><?php esc_html_e( 'Tunnels (recommended)', 'class-bookings-with-stripe' ); ?></h4>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'A tunnel gives you a temporary public hostname with HTTPS. Point your Stripe webhook endpoint at the tunnel URL plus the same REST path WordPress uses (see “Your endpoint URL” above).', 'class-bookings-with-stripe' ); ?></p>
	<ul class="clasbowi-doc__ul">
		<li>
			<strong>ngrok</strong> —
			<?php esc_html_e( 'Install ngrok, then forward the port where you can open WordPress in a browser (Docker example below uses 8101).', 'class-bookings-with-stripe' ); ?>
			<pre class="clasbowi-doc__pre"><code>ngrok http 8101</code></pre>
			<?php
			printf(
				'<p class="clasbowi-doc__muted">%s</p>',
				sprintf(
					/* translators: %s: REST webhook path (e.g. /wp-json/clasbowi/v1/stripe-webhook) */
					esc_html__( 'ngrok prints an https://….ngrok-free.app (or similar) URL. In Stripe, set the endpoint to that origin plus your webhook path, e.g. https://abc123.ngrok-free.app%s', 'class-bookings-with-stripe' ),
					esc_html( $webhook_path_example )
				)
			);
			?>
		</li>
		<li>
			<strong>Cloudflare Tunnel (cloudflared)</strong> —
			<?php esc_html_e( 'Useful for longer-lived dev URLs and teams.', 'class-bookings-with-stripe' ); ?>
			<pre class="clasbowi-doc__pre"><code>cloudflared tunnel --url http://localhost:8101</code></pre>
		</li>
		<li>
			<strong>localtunnel</strong> —
			<pre class="clasbowi-doc__pre"><code>npx localtunnel --port 8101</code></pre>
		</li>
		<li>
			<strong>nip.io / sslip.io</strong> —
			<?php esc_html_e( 'These map a hostname to an IP address (e.g. 127.0.0.1.nip.io). They help WordPress see a stable hostname, but Stripe’s dashboard still expects a proper HTTPS endpoint. Use them together with a reverse proxy or TLS terminator, or prefer ngrok / Cloudflare Tunnel for webhooks.', 'class-bookings-with-stripe' ); ?>
		</li>
	</ul>
	<p class="clasbowi-doc__note"><?php esc_html_e( 'Also see the Developer tab → “Local testing with Stripe CLI”: stripe listen forwards webhooks without exposing WordPress, which is ideal for verifying handler code.', 'class-bookings-with-stripe' ); ?></p>

	<h4 class="clasbowi-doc__h4"><?php esc_html_e( 'Port forwarding and “which port?”', 'class-bookings-with-stripe' ); ?></h4>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Your tunnel must target the host port that actually reaches WordPress—not necessarily port 80 inside a container.', 'class-bookings-with-stripe' ); ?></p>
	<ul class="clasbowi-doc__ul">
		<li><?php esc_html_e( 'Native PHP / local server: if the site runs at http://localhost:8080, run ngrok (or similar) against 8080.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Docker Desktop: if compose maps 8101 on your Mac to port 80 in the container (e.g. "8101:80"), tunnel to 8101 on the host.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Home/office router: only needed if you intentionally expose a machine to the internet without a tunnel. Stripe will hit your public IP: ensure the router forwards the chosen external port to your dev PC’s LAN IP and that a web server answers there.', 'class-bookings-with-stripe' ); ?></li>
	</ul>
	<pre class="clasbowi-doc__pre"><code># Example docker-compose port publish (host:container)
ports:
  - "8101:80"</code></pre>

	<h4 class="clasbowi-doc__h4"><?php esc_html_e( 'Docker: publish and reach the right interface', 'class-bookings-with-stripe' ); ?></h4>
	<ul class="clasbowi-doc__ul">
		<li><?php esc_html_e( 'Publish ports explicitly. Without a ports: mapping, nothing on the host can reach the container’s web server.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Bind to all interfaces when you need access from another device or a tunnel helper: use 0.0.0.0 in the mapping (Docker defaults this for host ports in many setups).', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'If a second process (tunnel, reverse proxy) runs in another container and must reach WordPress on the host, Docker Desktop often provides host.docker.internal as the host gateway.', 'class-bookings-with-stripe' ); ?></li>
	</ul>
	<pre class="clasbowi-doc__pre"><code>docker compose ps
curl -I http://127.0.0.1:8101/wp-json/</code></pre>

	<h4 class="clasbowi-doc__h4"><?php esc_html_e( 'Firewalls (host and container)', 'class-bookings-with-stripe' ); ?></h4>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'Tunnels usually work outbound-only (your machine initiates to ngrok), so home routers are fine—but corporate networks may block tunnel domains or non-standard TLS.', 'class-bookings-with-stripe' ); ?></p>
	<ul class="clasbowi-doc__ul">
		<li><?php esc_html_e( 'macOS: System Settings → Network → Firewall — allow incoming for your local web server or Docker if you expose ports directly.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Windows: Windows Security → Firewall & network protection — allow the app (e.g. Docker Desktop) or the inbound port.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Linux (ufw): allow the published host port, e.g.', 'class-bookings-with-stripe' ); ?> <code>sudo ufw allow 8101/tcp</code> <?php esc_html_e( 'then reload rules.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Cloud VPS: add a security group / firewall rule allowing HTTPS (443) from the internet to the instance running WordPress (or to the tunnel endpoint if the tunnel runs on the server).', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Container-only firewalls (iptables/nftables inside a custom image) are rare on dev images but can block traffic; test with curl from the host into the published port first.', 'class-bookings-with-stripe' ); ?></li>
	</ul>
	<p class="clasbowi-doc__muted"><?php esc_html_e( 'After the tunnel is up, confirm WordPress “Site Address (URL)” in Settings → General matches what customers use (often the tunnel URL while testing), so rest_url() and Stripe return URLs stay consistent.', 'class-bookings-with-stripe' ); ?></p>
		<?php
		return (string) ob_get_clean();
	}

	private static function help_email_smtp_message(): string {
		ob_start();
		?>
<div class="clasbowi-doc">
	<p class="clasbowi-doc__lead"><?php esc_html_e( 'This plugin sends booking emails through WordPress’s standard wp_mail() function (see Emails tab). On many hosts, PHP mail is unreliable: messages bounce, land in spam, or never leave the server.', 'class-bookings-with-stripe' ); ?></p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Why use WP Mail SMTP (or similar)?', 'class-bookings-with-stripe' ); ?></h3>
	<ul class="clasbowi-doc__ul">
		<li><?php esc_html_e( 'Deliverability: send through a real SMTP provider (Google Workspace, SendGrid, Mailgun, Amazon SES, Postmark, etc.) with proper SPF/DKIM.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Reliability: avoids the host’s default mail() limits and silent failures.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Debugging: popular plugins log errors and offer “send test email” so you can confirm configuration before customers book.', 'class-bookings-with-stripe' ); ?></li>
	</ul>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'A widely used free option is “WP Mail SMTP” (WPForms). Other SMTP plugins work too if they hook wp_mail the same way.', 'class-bookings-with-stripe' ); ?></p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Recommended setup outline', 'class-bookings-with-stripe' ); ?></h3>
	<ol class="clasbowi-doc__ol clasbowi-doc__ol--spaced">
		<li><?php esc_html_e( 'Install and activate WP Mail SMTP (or your preferred SMTP plugin).', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Complete the wizard: choose your mailer (e.g. SendGrid API, Other SMTP, Google).', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Set the From Email to an address your provider authorizes (often your domain).', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Send a test email from the SMTP plugin and confirm it arrives in your inbox.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Customer and admin booking emails use WordPress wp_mail() automatically; set subjects and bodies on the Emails tab.', 'class-bookings-with-stripe' ); ?></li>
	</ol>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function help_next_steps_message(): string {
		ob_start();
		?>
<div class="clasbowi-doc">
	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Quick checklist', 'class-bookings-with-stripe' ); ?></h3>
	<ol class="clasbowi-doc__ol">
		<li><?php esc_html_e( 'Create Classes (menu on the left) with schedule, price, and capacity.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Assign result pages under Result pages if the defaults are not suitable.', 'class-bookings-with-stripe' ); ?></li>
		<li><?php esc_html_e( 'Place the Elementor “Class Booking with Stripe” widget or shortcode on a page.', 'class-bookings-with-stripe' ); ?></li>
	</ol>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Shortcode', 'class-bookings-with-stripe' ); ?></h3>
	<pre class="clasbowi-doc__pre"><code>[clasbowi_booking class_id="123"]</code></pre>
	<p class="clasbowi-doc__muted"><?php esc_html_e( 'Replace 123 with the numeric ID shown in the Classes list or in the class edit screen.', 'class-bookings-with-stripe' ); ?></p>

	<h3 class="clasbowi-doc__h"><?php esc_html_e( 'Elementor: current post field', 'class-bookings-with-stripe' ); ?></h3>
	<p class="clasbowi-doc__p"><?php esc_html_e( 'For loops or class cards, add an ACF (or meta) field on your content post with the internal name', 'class-bookings-with-stripe' ); ?> <code>clasbowi_class_stripe_id</code> <?php esc_html_e( '(Class ID). Point the widget at “Current post field”.', 'class-bookings-with-stripe' ); ?></p>
	<p class="clasbowi-doc__muted"><?php esc_html_e( 'Legacy field name clasbowi_class_stripe_id is still read if present.', 'class-bookings-with-stripe' ); ?></p>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function help_plugin_meta_message(): string {
		$version = defined( 'CLASBOWI_VERSION' ) ? (string) CLASBOWI_VERSION : 'unknown';
		$developer = 'IORoot.com';
		$website = 'https://ioroot.com';
		return sprintf(
			'<div class="clasbowi-doc clasbowi-doc--compact"><p class="clasbowi-doc__meta"><strong>%s</strong> %s &nbsp;·&nbsp; <strong>%s</strong> %s &nbsp;·&nbsp; <strong>%s</strong> <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p></div>',
			esc_html__( 'Version:', 'class-bookings-with-stripe' ),
			esc_html( $version ),
			esc_html__( 'Developer:', 'class-bookings-with-stripe' ),
			esc_html( $developer ),
			esc_html__( 'Website:', 'class-bookings-with-stripe' ),
			esc_url( $website ),
			esc_html( $website )
		);
	}
}
