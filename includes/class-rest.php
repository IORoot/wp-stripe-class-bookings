<?php
/**
 * REST endpoints: create checkout, Stripe webhook, booking status poll.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

abstract class REST {

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register' ] );
	}

	public static function register(): void {
		register_rest_route( CLASBOWI_REST_NS, '/checkout', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'create_checkout' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'class_id'       => [ 'required' => true, 'sanitize_callback' => 'absint' ],
				'class_date'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				'seats'          => [ 'required' => true, 'sanitize_callback' => 'absint' ],
				'customer_name'  => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
				'customer_email' => [ 'required' => false, 'sanitize_callback' => 'sanitize_email' ],
				'origin_url'     => [ 'required' => false, 'sanitize_callback' => 'esc_url_raw' ],
				'waiver_accepted' => [ 'required' => false ],
				'mailchimp_opt_in' => [ 'required' => false ],
				'extra_fields'   => [ 'required' => false ],
			],
		] );

		register_rest_route( CLASBOWI_REST_NS, '/stripe-webhook', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_webhook' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( CLASBOWI_REST_NS, '/booking-status', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'booking_status' ],
			'permission_callback' => [ self::class, 'can_view_booking_status' ],
			'args'                => [
				'session' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				'token'   => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );

		register_rest_route( CLASBOWI_REST_NS, '/availability', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'availability' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'class_id' => [ 'required' => true, 'sanitize_callback' => 'absint' ],
			],
		] );
	}

	/**
	 * POST /checkout — validates, creates a pending booking with soft-hold, then a Stripe Checkout Session.
	 */
	public static function create_checkout( \WP_REST_Request $request ) {
		$class_id   = (int) $request['class_id'];
		$class_date = Helpers::normalise_date_string( (string) $request['class_date'] );
		$seats      = max( 1, (int) $request['seats'] );
		$name       = (string) ( $request['customer_name'] ?? '' );
		$email      = (string) ( $request['customer_email'] ?? '' );
		$origin     = Helpers::sanitise_internal_url( (string) ( $request['origin_url'] ?? '' ), home_url( '/' ) );
		$waiver_accepted  = rest_sanitize_boolean( $request['waiver_accepted'] ?? false );
		$mailchimp_opt_in = rest_sanitize_boolean( $request['mailchimp_opt_in'] ?? false );
		$extra_fields_raw = is_array( $request['extra_fields'] ?? null ) ? (array) $request['extra_fields'] : [];

		if ( $class_id <= 0 ) {
			return self::error( 422, 'validation', __( 'Missing class.', 'class-bookings-with-stripe' ) );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return self::error( 422, 'validation', __( 'Please enter a valid email address.', 'class-bookings-with-stripe' ), [ 'field' => 'customer_email' ] );
		}
		if ( '' === $name ) {
			return self::error( 422, 'validation', __( 'Please enter your name.', 'class-bookings-with-stripe' ), [ 'field' => 'customer_name' ] );
		}
		if ( (bool) Helpers::get_option( 'enable_waiver', false ) && ! $waiver_accepted ) {
			return self::error( 422, 'validation', __( 'Please accept the waiver before continuing to payment.', 'class-bookings-with-stripe' ), [ 'field' => 'waiver_accepted' ] );
		}

		$class_data = Helpers::get_class_data( $class_id );
		if ( ! $class_data ) {
			return self::error( 404, 'class_not_found', __( 'That class could not be found.', 'class-bookings-with-stripe' ) );
		}

		if ( empty( $class_data['class_active'] ) ) {
			return self::error( 409, 'class_inactive', __( 'Bookings for this class are currently unavailable.', 'class-bookings-with-stripe' ) );
		}
		$extra_fields = Extra_Fields::validate_submission( $class_id, $extra_fields_raw );
		if ( is_wp_error( $extra_fields ) ) {
			return self::error(
				422,
				'validation',
				$extra_fields->get_error_message(),
				[ 'field' => (string) ( $extra_fields->get_error_data()['field'] ?? '' ) ]
			);
		}

		$reason = Bookings::validate_date( $class_data, $class_date );
		if ( '' !== $reason ) {
			return self::error( 409, $reason, __( 'That date is no longer available. Please choose another.', 'class-bookings-with-stripe' ) );
		}

		$remaining = Bookings::seats_remaining( $class_data, $class_date );
		if ( $remaining <= 0 ) {
			return self::error( 409, 'capacity_full', __( 'Sorry, this class just filled up. Please choose another date.', 'class-bookings-with-stripe' ) );
		}
		if ( $seats < 1 || $seats > $remaining ) {
			return self::error(
				409,
				'capacity_full',
				sprintf(
					/* translators: %d: seats remaining */
					_n( 'Only %d seat is left for that date.', 'Only %d seats are left for that date.', $remaining, 'class-bookings-with-stripe' ),
					$remaining
				),
				[ 'remaining' => $remaining ]
			);
		}

		if ( '' === Helpers::stripe_secret_key() ) {
			return self::error( 502, 'stripe_error', __( 'Payments are not configured. Please contact us.', 'class-bookings-with-stripe' ) );
		}

		$unit_pence  = Helpers::to_pence( $class_data['price'] );
		$amount_total = $unit_pence * $seats;

		$booking_id = Bookings::create_pending_booking( [
			'class_id'       => $class_id,
			'class_date'     => $class_date,
			'seats'          => $seats,
			'customer_name'  => $name,
			'customer_email' => $email,
			'amount_pence'   => $amount_total,
			'waiver_accepted' => $waiver_accepted ? 1 : 0,
			'mailchimp_opt_in' => $mailchimp_opt_in ? 1 : 0,
			'extra_fields'    => $extra_fields,
		] );

		if ( is_wp_error( $booking_id ) ) {
			return self::error( 500, 'internal', __( 'Could not start a booking. Please try again.', 'class-bookings-with-stripe' ) );
		}

		$status_token = (string) get_post_meta( $booking_id, '_clasbowi_status_token', true );
		$success_url  = Result_Pages::success_url( '{CHECKOUT_SESSION_ID}', $origin, $status_token );
		$cancel_url   = Result_Pages::cancel_url( $origin );

		try {
			$session = Stripe_Service::create_checkout_session(
				$class_data,
				$class_date,
				$seats,
				$unit_pence,
				$email,
				$name,
				$booking_id,
				$success_url,
				$cancel_url
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			Helpers::debug_log( '[class-bookings-with-stripe] Stripe API error: ' . $e->getMessage() );
			Bookings::set_status( $booking_id, Bookings::STATUS_EXPIRED );
			return self::error( 502, 'stripe_error', __( 'Could not connect to Stripe. Please try again.', 'class-bookings-with-stripe' ) );
		} catch ( \Throwable $e ) {
			Helpers::debug_log( '[class-bookings-with-stripe] Checkout error: ' . $e->getMessage() );
			Bookings::set_status( $booking_id, Bookings::STATUS_EXPIRED );
			return self::error( 502, 'stripe_error', __( 'Could not start the payment. Please try again.', 'class-bookings-with-stripe' ) );
		}

		Bookings::attach_stripe_session( $booking_id, $session->id );

		return new \WP_REST_Response( [
			'url'        => $session->url,
			'booking_id' => $booking_id,
		], 200 );
	}

	/**
	 * POST /stripe-webhook — verifies signature and dispatches events.
	 */
	public static function handle_webhook( \WP_REST_Request $request ) {
		$payload    = $request->get_body();
		$sig_header = $request->get_header( 'stripe_signature' );
		if ( ! $sig_header ) {
			$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] )
				? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_STRIPE_SIGNATURE'] ) )
				: '';
		}

		try {
			$event = Stripe_Service::verify_webhook( $payload, (string) $sig_header );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			Helpers::debug_log( '[class-bookings-with-stripe] Webhook signature failed: ' . $e->getMessage() );
			return new \WP_REST_Response( [ 'error' => 'invalid_signature' ], 400 );
		} catch ( \Throwable $e ) {
			Helpers::debug_log( '[class-bookings-with-stripe] Webhook error: ' . $e->getMessage() );
			return new \WP_REST_Response( [ 'error' => 'invalid_payload' ], 400 );
		}

		switch ( $event->type ) {
			case 'checkout.session.completed':
				self::handle_session_completed( $event->data->object );
				break;
			case 'checkout.session.expired':
			case 'checkout.session.async_payment_failed':
				self::handle_session_expired( $event->data->object );
				break;
		}

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	private static function handle_session_completed( $session ): void {
		$booking_id = self::resolve_booking_from_session( $session );
		if ( ! $booking_id ) {
			$session_id = is_object( $session ) ? (string) ( $session->id ?? '' ) : '';
			$meta_booking_id = is_object( $session ) && isset( $session->metadata->booking_id ) ? (string) $session->metadata->booking_id : '';
			Helpers::debug_log(
				'[class-bookings-with-stripe] checkout.session.completed could not resolve booking. session_id=' .
				$session_id .
				' metadata.booking_id=' .
				$meta_booking_id
			);
			return;
		}
		if ( Bookings::STATUS_PAID === Bookings::get_status( $booking_id ) ) {
			Helpers::debug_log( '[class-bookings-with-stripe] checkout.session.completed already paid. booking_id=' . $booking_id );
			return; // idempotent
		}

		$payment_status = is_object( $session ) ? ( $session->payment_status ?? '' ) : '';
		if ( 'paid' !== $payment_status && 'no_payment_required' !== $payment_status ) {
			Helpers::debug_log(
				'[class-bookings-with-stripe] checkout.session.completed ignored due to payment_status=' .
				(string) $payment_status .
				' booking_id=' .
				$booking_id
			);
			return;
		}

		// Pull customer details from Stripe (Checkout collects them on hosted page).
		$name = (string) get_post_meta( $booking_id, '_clasbowi_customer_name', true );
		$email = (string) get_post_meta( $booking_id, '_clasbowi_customer_email', true );

		$details = is_object( $session ) ? ( $session->customer_details ?? null ) : null;
		if ( $details ) {
			if ( ! empty( $details->name ) ) {
				$name = sanitize_text_field( $details->name );
			}
			if ( ! empty( $details->email ) ) {
				$email = sanitize_email( $details->email );
			}
		}

		update_post_meta( $booking_id, '_clasbowi_customer_name', $name );
		update_post_meta( $booking_id, '_clasbowi_customer_email', $email );

		$amount_total = is_object( $session ) ? (int) ( $session->amount_total ?? 0 ) : 0;
		if ( $amount_total > 0 ) {
			update_post_meta( $booking_id, '_clasbowi_amount_total', $amount_total );
		}

		$payment_intent = '';
		if ( is_object( $session ) ) {
			$payment_intent = is_string( $session->payment_intent ?? null )
				? $session->payment_intent
				: ( is_object( $session->payment_intent ?? null ) ? $session->payment_intent->id : '' );
		}
		if ( $payment_intent ) {
			update_post_meta( $booking_id, '_clasbowi_stripe_payment_intent', $payment_intent );
		}

		// Update post title to reflect customer.
		wp_update_post( [
			'ID'         => $booking_id,
			'post_title' => sprintf(
				'%s · %s · %s',
				$name ?: __( 'Customer', 'class-bookings-with-stripe' ),
				get_the_title( (int) get_post_meta( $booking_id, '_clasbowi_class_id', true ) ),
				Helpers::format_date( (string) get_post_meta( $booking_id, '_clasbowi_class_date', true ) )
			),
		] );

		Bookings::set_status( $booking_id, Bookings::STATUS_PAID );
		Mailchimp::subscribe_booking( $booking_id );

		Emails::send_for_booking( $booking_id );
		Helpers::debug_log( '[class-bookings-with-stripe] checkout.session.completed marked paid. booking_id=' . $booking_id );
	}

	private static function handle_session_expired( $session ): void {
		$booking_id = self::resolve_booking_from_session( $session );
		if ( ! $booking_id ) {
			return;
		}
		if ( Bookings::STATUS_PAID === Bookings::get_status( $booking_id ) ) {
			return;
		}
		Bookings::set_status( $booking_id, Bookings::STATUS_EXPIRED );
	}

	private static function resolve_booking_from_session( $session ): int {
		if ( ! is_object( $session ) ) {
			return 0;
		}
		$session_id = (string) ( $session->id ?? '' );
		$booking_id = Bookings::find_by_stripe_session( $session_id );
		if ( $booking_id ) {
			return $booking_id;
		}
		// Fallback: metadata.booking_id
		$meta = $session->metadata ?? null;
		if ( $meta && ! empty( $meta->booking_id ) ) {
			$candidate = (int) $meta->booking_id;
			$post = get_post( $candidate );
			if ( $post && CPT::BOOKING_PT === $post->post_type ) {
				return $candidate;
			}
		}
		return 0;
	}

	/**
	 * Authorize booking-status: site admins, or holder of the per-booking status token.
	 */
	public static function can_view_booking_status( \WP_REST_Request $request ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$session_id = (string) $request->get_param( 'session' );
		$token      = (string) $request->get_param( 'token' );
		if ( '' === $session_id || '' === $token ) {
			return false;
		}

		$booking_id = Bookings::find_by_stripe_session( $session_id );
		if ( ! $booking_id ) {
			return false;
		}

		return Bookings::verify_status_token( $booking_id, $token );
	}

	/**
	 * GET /booking-status?session=cs_...&token=...
	 */
	public static function booking_status( \WP_REST_Request $request ) {
		$session_id = (string) $request['session'];
		$booking_id = Bookings::find_by_stripe_session( $session_id );
		if ( ! $booking_id || Bookings::STATUS_PENDING === Bookings::get_status( $booking_id ) ) {
			$booking_id = self::reconcile_session_from_stripe( $session_id ) ?: $booking_id;
		}
		if ( ! $booking_id ) {
			return new \WP_REST_Response( [ 'status' => 'pending' ], 200 );
		}

		$meta       = Bookings::get_meta( $booking_id );
		$class_data = Helpers::get_class_data( $meta['class_id'] );

		return new \WP_REST_Response( [
			'status'        => $meta['status'],
			'booking_id'    => $booking_id,
			'class_name'    => $class_data['name'] ?? '',
			'class_date'    => Helpers::format_date( $meta['class_date'] ),
			'class_time'    => Helpers::format_time( $class_data['start_time'] ?? '' ),
			'location'      => $class_data['location'] ?? '',
			'seats'         => $meta['seats'],
			'amount_total'  => Helpers::format_price( $meta['amount_total_pence'] / 100 ),
			'customer_name' => $meta['customer_name'],
		], 200 );
	}

	/**
	 * Fallback when webhooks are delayed/missed: fetch session directly from Stripe
	 * and apply the same completion logic.
	 */
	private static function reconcile_session_from_stripe( string $session_id ): ?int {
		if ( '' === trim( $session_id ) ) {
			return null;
		}
		try {
			$session = Stripe_Service::retrieve_checkout_session( $session_id );
			if ( ! $session ) {
				return null;
			}
			$payment_status = is_object( $session ) ? (string) ( $session->payment_status ?? '' ) : '';
			$booking_id = self::resolve_booking_from_session( $session );
			if ( ! $booking_id ) {
				return null;
			}
			if ( 'paid' === $payment_status || 'no_payment_required' === $payment_status ) {
				self::handle_session_completed( $session );
			}
			return $booking_id;
		} catch ( \Throwable $e ) {
			Helpers::debug_log( '[class-bookings-with-stripe] Stripe status reconcile failed: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * GET /availability?class_id=123 — used by the form to refresh after errors.
	 */
	public static function availability( \WP_REST_Request $request ) {
		$class_id   = (int) $request['class_id'];
		$class_data = Helpers::get_class_data( $class_id );
		if ( ! $class_data ) {
			return new \WP_REST_Response( [ 'error' => 'not_found' ], 404 );
		}
		$dates_count = ! empty( $class_data['is_one_off_event'] )
			? 1
			: Helpers::class_upcoming_dates_count( $class_data );
		$dates       = Bookings::next_available_dates( $class_data, $dates_count );
		return new \WP_REST_Response( [
			'class_active' => (bool) $class_data['class_active'],
			'dates'        => $dates,
		], 200 );
	}

	private static function error( int $status, string $reason, string $message, array $extra = [] ): \WP_REST_Response {
		return new \WP_REST_Response( array_merge( [
			'error'   => true,
			'reason'  => $reason,
			'message' => $message,
		], $extra ), $status );
	}
}
