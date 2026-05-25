<?php
/**
 * Thin wrapper around the official stripe-php SDK.
 *
 * @package IORoot_Yoga_Bookings
 */

namespace IORoot_Yoga_Bookings;

defined( 'ABSPATH' ) || exit;

abstract class Stripe_Service {

	private static ?\Stripe\StripeClient $client = null;

	public static function client(): ?\Stripe\StripeClient {
		$secret = Helpers::stripe_secret_key();
		if ( '' === $secret ) {
			return null;
		}
		if ( ! self::$client || self::$client->getApiKey() !== $secret ) {
			self::$client = new \Stripe\StripeClient( [
				'api_key'        => $secret,
				'stripe_version' => '2024-04-10',
			] );
		}
		return self::$client;
	}

	/**
	 * Create a Checkout Session with an inline product (price_data).
	 *
	 * @return \Stripe\Checkout\Session
	 * @throws \Stripe\Exception\ApiErrorException
	 */
	public static function create_checkout_session(
		array $class_data,
		string $class_date,
		int $seats,
		int $unit_amount_pence,
		string $customer_email,
		string $customer_name,
		int $booking_id,
		string $success_url,
		string $cancel_url
	): \Stripe\Checkout\Session {
		$client = self::client();
		if ( ! $client ) {
			throw new \RuntimeException( 'Stripe secret key is not configured.' );
		}

		$date_human = Helpers::format_date( $class_date );
		$time_human = Helpers::format_time( $class_data['start_time'] ?? '' );

		$product_name = self::resolve_product_name(
			$class_data,
			$class_date,
			$date_human,
			$time_human,
			$seats,
			$customer_name,
			$booking_id
		);

		$description_parts = array_filter( [
			$class_data['location'] ?? '',
			! empty( $class_data['duration'] ) ? sprintf( '%d min', (int) $class_data['duration'] ) : '',
		] );
		$product_description = implode( ' · ', $description_parts );

		$params = [
			'mode'         => 'payment',
			'success_url'  => $success_url,
			'cancel_url'   => $cancel_url,
			'expires_at'   => time() + IOROOT_YB_HOLD_SECONDS,
			'line_items'   => [
				[
					'quantity'   => $seats,
					'price_data' => [
						'currency'     => 'gbp',
						'unit_amount'  => $unit_amount_pence,
						'product_data' => [
							'name'        => $product_name,
							'description' => $product_description ?: null,
						],
					],
				],
			],
			'metadata'     => [
				'booking_id' => (string) $booking_id,
				'class_id'   => (string) ( $class_data['id'] ?? 0 ),
				'class_date' => $class_date,
				'seats'      => (string) $seats,
			],
			'payment_intent_data' => [
				'metadata' => [
					'booking_id' => (string) $booking_id,
				],
			],
		];

		if ( $customer_email && is_email( $customer_email ) ) {
			$params['customer_email'] = $customer_email;
		}

		$params['line_items'][0]['price_data']['product_data'] = array_filter(
			$params['line_items'][0]['price_data']['product_data'],
			static fn( $v ) => null !== $v && '' !== $v
		);

		return $client->checkout->sessions->create( $params );
	}

	/**
	 * Build Stripe line-item title from settings template + placeholders.
	 */
	private static function resolve_product_name(
		array $class_data,
		string $class_date,
		string $date_human,
		string $time_human,
		int $seats,
		string $customer_name,
		int $booking_id
	): string {
		$tpl = (string) Helpers::get_option( 'stripe_item_title_template', '' );
		if ( '' === trim( $tpl ) ) {
			$tpl = '{class_name} — {class_date}, {class_time}';
		}

		$tags = [
			'{class_name}'    => (string) ( $class_data['name'] ?? __( 'Yoga class', 'wp-stripe-class-bookings' ) ),
			'{class_date}'    => $date_human,
			'{class_time}'    => $time_human,
			'{location}'      => (string) ( $class_data['location'] ?? '' ),
			'{seats}'         => (string) $seats,
			'{customer_name}' => $customer_name,
			'{booking_id}'    => '#' . $booking_id,
			'{class_date_raw}' => $class_date,
		];
		$title = trim( preg_replace( '/\s+/', ' ', strtr( $tpl, $tags ) ) ?? '' );
		if ( '' === $title ) {
			$title = (string) ( $class_data['name'] ?? __( 'Yoga class', 'wp-stripe-class-bookings' ) );
		}
		return substr( $title, 0, 127 );
	}

	/**
	 * Verify and parse an incoming webhook payload.
	 *
	 * @throws \UnexpectedValueException|\Stripe\Exception\SignatureVerificationException
	 */
	public static function verify_webhook( string $payload, string $sig_header ): \Stripe\Event {
		$secret = Helpers::stripe_webhook_secret();
		if ( '' === $secret ) {
			throw new \RuntimeException( 'Stripe webhook secret is not configured.' );
		}
		return \Stripe\Webhook::constructEvent( $payload, $sig_header, $secret );
	}

	/**
	 * Retrieve a Checkout Session by id.
	 *
	 * @throws \Stripe\Exception\ApiErrorException
	 */
	public static function retrieve_checkout_session( string $session_id ): ?\Stripe\Checkout\Session {
		$client = self::client();
		if ( ! $client || '' === trim( $session_id ) ) {
			return null;
		}
		return $client->checkout->sessions->retrieve( $session_id, [] );
	}
}
