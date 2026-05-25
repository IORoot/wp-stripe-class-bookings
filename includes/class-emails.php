<?php
/**
 * Email rendering: merge-tag substitution, wp_mail dispatch.
 *
 * @package IORoot_Yoga_Bookings
 */

namespace IORoot_Yoga_Bookings;

defined( 'ABSPATH' ) || exit;

abstract class Emails {

	public static function init(): void {
		// reserved for future hooks (e.g. resend on demand)
	}

	/**
	 * Send customer + admin emails for a paid booking. Idempotent within the same request.
	 */
	public static function send_for_booking( int $booking_id ): void {
		static $sent = [];
		if ( isset( $sent[ $booking_id ] ) ) {
			return;
		}
		$sent[ $booking_id ] = true;

		$tags = self::merge_tags( $booking_id );
		if ( empty( $tags ) ) {
			return;
		}

		self::send_customer( $tags );
		self::send_admin( $tags );
	}

	/**
	 * Build merge-tag values for a booking.
	 *
	 * @return array<string, string>|null
	 */
	private static function merge_tags( int $booking_id ): ?array {
		$meta = Bookings::get_meta( $booking_id );
		$class_data = Helpers::get_class_data( $meta['class_id'] );
		if ( ! $class_data ) {
			return null;
		}

		return [
			'{customer_name}'  => (string) $meta['customer_name'],
			'{customer_email}' => (string) $meta['customer_email'],
			'{class_name}'     => (string) ( $class_data['name'] ?? '' ),
			'{class_date}'     => Helpers::format_date( (string) $meta['class_date'] ),
			'{class_time}'     => Helpers::format_time( (string) ( $class_data['start_time'] ?? '' ) ),
			'{location}'       => (string) ( $class_data['location'] ?? '' ),
			'{duration}'       => (string) ( $class_data['duration'] ?? '' ),
			'{price}'          => Helpers::format_price( (float) ( $class_data['price'] ?? 0 ) ),
			'{seats}'          => (string) (int) $meta['seats'],
			'{amount_total}'   => Helpers::format_price( $meta['amount_total_pence'] / 100 ),
			'{booking_id}'     => '#' . $booking_id,
			'{description}'    => wp_strip_all_tags( (string) ( $class_data['description'] ?? '' ) ),
		] + Extra_Fields::build_merge_tags( (int) $meta['class_id'], (string) ( $meta['extra_fields_json'] ?? '' ) );
	}

	/**
	 * @param array<string, string> $tags
	 */
	private static function send_customer( array $tags ): void {
		$email = $tags['{customer_email}'] ?? '';
		if ( ! $email || ! is_email( $email ) ) {
			return;
		}

		$subject_tpl = (string) Helpers::get_option( 'customer_email_subject', '' );
		$body_tpl    = (string) Helpers::get_option( 'customer_email_body', '' );

		if ( '' === $subject_tpl ) {
			$subject_tpl = self::default_customer_subject();
		}
		if ( '' === $body_tpl ) {
			$body_tpl = self::load_template_file( 'email-customer.php' );
		}

		$subject = self::apply_tags( $subject_tpl, $tags );
		$body    = self::apply_tags( $body_tpl, $tags );

		self::send( $email, $subject, $body );
	}

	/**
	 * @param array<string, string> $tags
	 */
	private static function send_admin( array $tags ): void {
		$admin_email = (string) Helpers::get_option( 'admin_email', '' );
		if ( '' === $admin_email ) {
			$admin_email = (string) get_option( 'admin_email' );
		}
		if ( ! $admin_email || ! is_email( $admin_email ) ) {
			return;
		}

		$subject_tpl = (string) Helpers::get_option( 'admin_email_subject', '' );
		$body_tpl    = (string) Helpers::get_option( 'admin_email_body', '' );

		if ( '' === $subject_tpl ) {
			$subject_tpl = self::default_admin_subject();
		}
		if ( '' === $body_tpl ) {
			$body_tpl = self::load_template_file( 'email-admin.php' );
		}

		$subject = self::apply_tags( $subject_tpl, $tags );
		$body    = self::apply_tags( $body_tpl, $tags );

		self::send( $admin_email, $subject, $body );
	}

	/**
	 * @param array<string, string> $tags
	 */
	private static function apply_tags( string $template, array $tags ): string {
		return strtr( $template, $tags );
	}

	private static function send( string $to, string $subject, string $body ): void {
		$headers   = [];
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$body_html = self::to_html( $body );
		wp_mail( $to, wp_strip_all_tags( $subject ), $body_html, $headers );
	}

	/**
	 * Convert a plain-or-rich body to HTML email markup.
	 */
	private static function to_html( string $body ): string {
		$looks_like_html = ( false !== stripos( $body, '<p' ) || false !== stripos( $body, '<br' ) );
		if ( ! $looks_like_html ) {
			$body = wpautop( $body );
		}
		$style = '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;color:#222;line-height:1.5;} .yb-mail{max-width:560px;margin:0 auto;padding:24px;background:#fff;border-radius:14px;} dt{color:#666;} dd{margin:0 0 6px;font-weight:600;}</style>';
		return '<!doctype html><html><head><meta charset="utf-8">' . $style . '</head><body><div class="yb-mail">' . $body . '</div></body></html>';
	}

	private static function load_template_file( string $relative ): string {
		$path = IOROOT_YB_DIR . 'templates/' . ltrim( $relative, '/' );
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return self::strip_template_php_guard( $raw );
	}

	/**
	 * Remove optional ABSPATH guard from template files (not part of email body).
	 */
	private static function strip_template_php_guard( string $raw ): string {
		if ( preg_match( '/\A<\?php\b.*?\?>\s*/s', $raw ) ) {
			return (string) preg_replace( '/\A<\?php\b.*?\?>\s*/s', '', $raw, 1 );
		}
		return $raw;
	}

	private static function default_customer_subject(): string {
		return 'Your booking is confirmed: {class_name} on {class_date}';
	}

	private static function default_admin_subject(): string {
		return 'New booking: {customer_name} for {class_name} on {class_date}';
	}
}
