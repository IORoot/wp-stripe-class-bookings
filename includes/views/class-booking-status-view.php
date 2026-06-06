<?php
/**
 * Booking status view — composable blocks for [clasbowi_booking_status].
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

class Booking_Status_View extends Abstract_View {

	public string $type;

	public string $session_id;

	public string $status_token;

	public string $reason;

	public string $msg;

	public string $origin;

	/** @var array<string, mixed>|null */
	public ?array $booking;

	/** @var array<string, string> */
	public array $atts;

	/** @var array<string, string> */
	public array $reason_messages;

	/**
	 * @param array<string, mixed> $args
	 */
	public function __construct( array $args ) {
		$this->type         = (string) ( $args['type'] ?? 'success' );
		$this->session_id   = (string) ( $args['session_id'] ?? '' );
		$this->status_token = (string) ( $args['status_token'] ?? '' );
		$this->reason       = (string) ( $args['reason'] ?? '' );
		$this->msg          = (string) ( $args['msg'] ?? '' );
		$this->origin       = (string) ( $args['origin'] ?? '' );
		$this->booking      = isset( $args['booking'] ) && is_array( $args['booking'] ) ? $args['booking'] : null;
		$this->atts         = (array) ( $args['atts'] ?? [] );

		$this->reason_messages = [
			'capacity_full'   => __( "Sorry — that class just filled up while you were booking. Please try a different date.", CLASBOWI_TEXT_DOMAIN ),
			'class_inactive'  => __( 'Bookings for this class are currently unavailable. Please check back soon.', CLASBOWI_TEXT_DOMAIN ),
			'date_invalid'    => __( 'That date is no longer available. Please choose another.', CLASBOWI_TEXT_DOMAIN ),
			'class_not_found' => __( 'We could not find that class. It may have been removed.', CLASBOWI_TEXT_DOMAIN ),
			'stripe_error'    => __( 'We could not connect to our payment provider. Please try again in a moment.', CLASBOWI_TEXT_DOMAIN ),
			'validation'      => __( 'Some details were missing or invalid. Please check the form and try again.', CLASBOWI_TEXT_DOMAIN ),
			'internal'        => __( 'Something went wrong on our end. Please try again — your card has not been charged.', CLASBOWI_TEXT_DOMAIN ),
		];
		$this->reason_messages = (array) apply_filters(
			'clasbowi_status_reason_messages',
			$this->reason_messages,
			$this->type,
			$this->reason,
			$this->booking
		);
	}

	protected function get_layout_name(): string {
		return 'booking-status';
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_required_js_markers(): array {
		return [];
	}

	public function get_variant(): string {
		if ( 'cancelled' === $this->type ) {
			return 'cancelled';
		}
		if ( 'error' === $this->type ) {
			return 'error';
		}
		if ( $this->booking && 'paid' === ( $this->booking['status'] ?? '' ) ) {
			return 'success-paid';
		}
		if ( '' !== $this->session_id ) {
			return 'success-pending';
		}

		return 'success-fallback';
	}

	public function get_status_class(): string {
		if ( 'success' !== $this->type ) {
			return $this->type;
		}

		return (string) ( $this->booking['status'] ?? 'pending' );
	}

	public function get_reason_message(): string {
		$default = __( 'Something went wrong while taking your booking. Please try again.', CLASBOWI_TEXT_DOMAIN );

		return (string) ( $this->reason_messages[ $this->reason ] ?? $default );
	}

	public function should_render( string $slug ): bool {
		$variant = $this->get_variant();

		return match ( $slug ) {
			'details-list'      => 'success-paid' === $variant && null !== $this->booking,
			'pending-spinner'   => 'success-pending' === $variant,
			'try-again-button'  => in_array( $variant, [ 'cancelled', 'error' ], true ),
			'error-detail'      => 'error' === $variant && '' !== $this->msg,
			'hint'              => in_array( $variant, [ 'success-paid', 'error' ], true ),
			default             => true,
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	protected function get_layout_vars(): array {
		return [
			'type'             => $this->type,
			'session_id'       => $this->session_id,
			'status_token'     => $this->status_token,
			'reason'           => $this->reason,
			'msg'              => $this->msg,
			'origin'           => $this->origin,
			'booking'          => $this->booking,
			'atts'             => $this->atts,
			'reason_messages'  => $this->reason_messages,
		];
	}

	/**
	 * @param array<string, mixed> $template_args
	 */
	public static function render_html( array $template_args ): string {
		$view        = new self( $template_args );
		$layout_path = Template_Loader::locate_layout( $view->get_layout_name(), 'status' );

		ob_start();
		if ( is_readable( $layout_path ) ) {
			do_action( 'clasbowi_before_render_status_template', $template_args, $layout_path );
			$view->include_layout( $layout_path );
			do_action( 'clasbowi_after_render_status_template', $template_args, $layout_path );
		}
		$html = (string) ob_get_clean();

		return (string) apply_filters( 'clasbowi_status_html', $html, $template_args, $layout_path );
	}
}
