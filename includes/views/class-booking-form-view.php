<?php
/**
 * Booking form view — composable fields and extras for [clasbowi_booking].
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

class Booking_Form_View extends Abstract_View {

	/** @var array<string, mixed> */
	public array $class_data;

	/** @var array<int, array{date: string, label: string, remaining: int, cancelled?: bool, selectable?: bool}> */
	public array $dates;

	public bool $show_heading;

	public int $max_seats_today;

	/** @var array<string, string> */
	public array $atts;

	public bool $is_active;

	public bool $has_dates;

	public bool $use_external_link;

	public string $external_link_url;

	public string $origin;

	public bool $show_waiver;

	public string $waiver_page_url;

	public bool $show_mailchimp_optin;

	/** @var array<int, mixed> */
	public array $extra_fields;

	public bool $show_seats_remaining;

	public bool $is_one_off_fixed_date;

	/** @var array{date: string, label: string, remaining: int, cancelled?: bool, selectable?: bool}|null */
	public ?array $primary_date;

	/** @var array<string, string> */
	public array $labels;

	/**
	 * @param array<string, mixed> $args
	 */
	public function __construct( array $args ) {
		$this->class_data      = (array) ( $args['class_data'] ?? [] );
		$this->dates           = (array) ( $args['dates'] ?? [] );
		$this->show_heading    = (bool) ( $args['show_heading'] ?? true );
		$this->max_seats_today = (int) ( $args['max_seats_today'] ?? 0 );
		$this->atts            = (array) ( $args['atts'] ?? [] );

		$this->is_active            = ! empty( $this->class_data['class_active'] );
		$this->use_external_link    = ! empty( $this->class_data['use_external_link'] );
		$this->external_link_url    = esc_url( (string) ( $this->class_data['external_link_url'] ?? '' ) );
		$this->origin               = esc_url( wp_get_referer() ?: home_url( add_query_arg( null, null ) ) );
		$this->show_waiver          = (bool) Helpers::get_option( 'enable_waiver', false );
		$this->waiver_page_url      = $this->show_waiver
			? esc_url( (string) Helpers::get_option( 'waiver_page_url', '' ) )
			: '';
		$this->show_mailchimp_optin = (bool) Helpers::get_option( 'enable_mailchimp_optin', false );
		$this->extra_fields         = Extra_Fields::get_fields_for_class( (int) ( $this->class_data['id'] ?? 0 ) );
		$this->show_seats_remaining = ! array_key_exists( 'show_seats_remaining', $this->class_data )
			|| ! empty( $this->class_data['show_seats_remaining'] );
		$this->is_one_off_fixed_date = ! empty( $this->class_data['is_one_off_event'] );
		$this->primary_date          = null;
		$this->has_dates             = $this->is_active && ! empty( $this->dates );

		if ( $this->is_one_off_fixed_date && ! empty( $this->dates ) ) {
			foreach ( $this->dates as $d ) {
				if ( empty( $d['cancelled'] ) ) {
					$this->primary_date = $d;
					break;
				}
			}
			if ( ! $this->primary_date ) {
				$this->primary_date = $this->dates[0];
			}
			if ( ! empty( $this->primary_date['cancelled'] ) ) {
				$this->has_dates = false;
			}
		}

		$this->labels = apply_filters(
			'clasbowi_booking_labels',
			[
				'name'                  => __( 'Your name', CLASBOWI_TEXT_DOMAIN ),
				'email'                 => __( 'Email address', CLASBOWI_TEXT_DOMAIN ),
				'date'                  => __( 'Choose a date', CLASBOWI_TEXT_DOMAIN ),
				'event_date'            => __( 'Event date', CLASBOWI_TEXT_DOMAIN ),
				'seats'                 => __( 'How many people?', CLASBOWI_TEXT_DOMAIN ),
				'total'                 => __( 'Total', CLASBOWI_TEXT_DOMAIN ),
				'book_button'           => __( 'Book & pay with Stripe', CLASBOWI_TEXT_DOMAIN ),
				'external_button'       => __( 'Continue to booking', CLASBOWI_TEXT_DOMAIN ),
				'external_hint'         => __( 'This class uses an external booking page.', CLASBOWI_TEXT_DOMAIN ),
				'invalid_external_hint' => __( 'External booking is enabled, but no URL has been set for this class yet.', CLASBOWI_TEXT_DOMAIN ),
				'inactive_hint'         => __( 'Booking is currently unavailable for this class.', CLASBOWI_TEXT_DOMAIN ),
				'no_dates_hint'         => __( 'No upcoming dates available — please check back soon or contact us.', CLASBOWI_TEXT_DOMAIN ),
				'redirect_hint'         => __( 'You will be redirected to Stripe to complete your payment securely.', CLASBOWI_TEXT_DOMAIN ),
				'waiver_label'          => (string) Helpers::get_option(
					'waiver_label',
					__( 'I confirm I have read and accept the class waiver and participate at my own risk.', CLASBOWI_TEXT_DOMAIN )
				),
				'waiver_page_link_text' => __( 'View full waiver', CLASBOWI_TEXT_DOMAIN ),
				'mailchimp_optin_label' => (string) Helpers::get_option(
					'mailchimp_optin_label',
					__( 'Yes, I would like to join the mailing list for class updates and news.', CLASBOWI_TEXT_DOMAIN )
				),
			],
			$this->class_data,
			$this->dates
		);
	}

	protected function get_layout_name(): string {
		return 'booking-form';
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_required_js_markers(): array {
		return [
			'name-field'     => 'name="customer_name"',
			'email-field'    => 'name="customer_email"',
			'date-field'     => 'name="class_date"',
			'seats-field'    => 'name="seats"',
			'total-row'      => 'cbfs-form__total',
			'submit-button'  => 'cbfs-form__button',
			'form-messages'  => 'cbfs-form__error',
			'waiver'         => 'data-cbfs-waiver-group',
		];
	}

	public function has_bookable_form(): bool {
		return $this->is_active && ! $this->use_external_link && $this->has_dates;
	}

	/**
	 * @param list<string> $missing
	 */
	public static function validate_js_contract( string $html, self $view, array &$missing = [] ): bool {
		if ( ! $view->has_bookable_form() ) {
			return true;
		}

		$required = [
			'cbfs-form__form',
			'name="customer_name"',
			'name="customer_email"',
			'name="class_date"',
			'name="seats"',
			'cbfs-form__total',
			'cbfs-form__button',
			'cbfs-form__error',
		];

		if ( $view->show_waiver ) {
			$required[] = 'data-cbfs-waiver-group';
			$required[] = 'name="waiver_accepted"';
		}

		$missing = [];
		foreach ( $required as $marker ) {
			if ( ! str_contains( $html, $marker ) ) {
				$missing[] = $marker;
			}
		}

		return [] === $missing;
	}

	public function should_render( string $slug ): bool {
		return match ( $slug ) {
			'extra-fields'    => ! empty( $this->extra_fields ),
			'waiver'          => $this->show_waiver,
			'mailchimp-optin' => $this->show_mailchimp_optin,
			default           => true,
		};
	}

	public function get_title(): string {
		return (string) apply_filters(
			'clasbowi_booking_title',
			(string) ( $this->class_data['name'] ?? '' ),
			$this->class_data
		);
	}

	/**
	 * @return list<string>
	 */
	public function get_meta_bits(): array {
		if ( $this->use_external_link ) {
			return [];
		}

		$show_price_badge = ! $this->use_external_link && $this->is_active && ! empty( $this->class_data['price'] );
		$meta_parts       = [
			$this->class_data['location'] ?? '',
			! empty( $this->class_data['is_one_off_event'] )
				? Helpers::format_date_range(
					(string) ( $this->class_data['start_date'] ?? '' ),
					(string) ( $this->class_data['end_date'] ?? '' )
				)
				: ( ! empty( $this->class_data['day_of_week'] ) ? ucfirst( (string) $this->class_data['day_of_week'] ) . 's' : '' ),
			! empty( $this->class_data['start_time'] ) ? Helpers::format_time( (string) $this->class_data['start_time'] ) : '',
			! empty( $this->class_data['duration'] ) ? sprintf( '%d min', (int) $this->class_data['duration'] ) : '',
		];

		if ( ! $show_price_badge ) {
			$meta_parts[] = Helpers::format_price( (float) ( $this->class_data['price'] ?? 0 ) );
		}

		return array_values( array_filter( $meta_parts ) );
	}

	public function get_meta_text(): string {
		$meta_bits = $this->get_meta_bits();

		return (string) apply_filters(
			'clasbowi_booking_meta_text',
			implode( ' · ', $meta_bits ),
			$meta_bits,
			$this->class_data
		);
	}

	public function show_price_badge(): bool {
		return ! $this->use_external_link && $this->is_active && ! empty( $this->class_data['price'] );
	}

	public function get_description(): string {
		return (string) apply_filters(
			'clasbowi_booking_description',
			(string) ( $this->class_data['description'] ?? '' ),
			$this->class_data
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function get_layout_vars(): array {
		return [
			'class_data'      => $this->class_data,
			'dates'           => $this->dates,
			'show_heading'    => $this->show_heading,
			'max_seats_today' => $this->max_seats_today,
			'atts'            => $this->atts,
			'is_active'       => $this->is_active,
			'has_dates'       => $this->has_dates,
			'use_external_link' => $this->use_external_link,
			'external_link_url' => $this->external_link_url,
			'origin'          => $this->origin,
			'show_waiver'     => $this->show_waiver,
			'waiver_page_url' => $this->waiver_page_url,
			'show_mailchimp_optin' => $this->show_mailchimp_optin,
			'extra_fields'    => $this->extra_fields,
			'show_seats_remaining' => $this->show_seats_remaining,
			'is_one_off_fixed_date' => $this->is_one_off_fixed_date,
			'primary_date'    => $this->primary_date,
			'labels'          => $this->labels,
		];
	}

	/**
	 * @param array<string, mixed> $template_args
	 */
	public static function render_html( array $template_args ): string {
		$view        = new self( $template_args );
		$layout_path = Template_Loader::locate_layout( $view->get_layout_name(), 'booking' );

		ob_start();
		if ( is_readable( $layout_path ) ) {
			do_action( 'clasbowi_before_render_booking_template', $template_args, $layout_path );
			$view->include_layout( $layout_path );
			do_action( 'clasbowi_after_render_booking_template', $template_args, $layout_path );
		}
		$html = (string) ob_get_clean();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$missing = [];
			if ( ! self::validate_js_contract( $html, $view, $missing ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'[clasbowi] Booking form missing required JS markup: %s',
						implode( ', ', $missing )
					)
				);
			}
		}

		return (string) apply_filters( 'clasbowi_booking_html', $html, $template_args, $layout_path );
	}
}
