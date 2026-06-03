<?php
/**
 * Output for [clasbowi_booking_status type="success|cancelled|error"].
 *
 * @var string                                                    $type
 * @var string                                                    $session_id
 * @var string                                                    $reason
 * @var string                                                    $msg
 * @var string                                                    $origin
 * @var array<string, mixed>|null                                 $booking
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template; variables are local to this include.

use IOROOT_STRIPE_BOOKINGS\Helpers;

$reason_messages = [
	'capacity_full'   => __( "Sorry — that class just filled up while you were booking. Please try a different date.", 'class-bookings-with-stripe' ),
	'class_inactive'  => __( 'Bookings for this class are currently unavailable. Please check back soon.', 'class-bookings-with-stripe' ),
	'date_invalid'    => __( 'That date is no longer available. Please choose another.', 'class-bookings-with-stripe' ),
	'class_not_found' => __( 'We could not find that class. It may have been removed.', 'class-bookings-with-stripe' ),
	'stripe_error'    => __( 'We could not connect to our payment provider. Please try again in a moment.', 'class-bookings-with-stripe' ),
	'validation'      => __( 'Some details were missing or invalid. Please check the form and try again.', 'class-bookings-with-stripe' ),
	'internal'        => __( 'Something went wrong on our end. Please try again — your card has not been charged.', 'class-bookings-with-stripe' ),
];
$reason_messages = apply_filters( 'clasbowi_status_reason_messages', $reason_messages, $type, $reason, $booking );

do_action( 'clasbowi_status_template_start', $type, $booking );
?>

<?php if ( 'success' === $type ) : ?>

	<div class="yb-status yb-status--layout-modern yb-status--<?php echo esc_attr( $booking['status'] ?? 'pending' ); ?>" data-yb-session="<?php echo esc_attr( $session_id ); ?>">
		<?php if ( $booking && 'paid' === $booking['status'] ) : ?>
			<?php do_action( 'clasbowi_status_before_success_paid', $booking ); ?>
			<h2 class="yb-status__title"><?php esc_html_e( 'Booking confirmed', 'class-bookings-with-stripe' ); ?></h2>
			<p class="yb-status__lede">
				<?php
				printf(
					/* translators: %s: customer name */
					esc_html__( 'Thanks %s — we can\'t wait to see you on the mat.', 'class-bookings-with-stripe' ),
					esc_html( $booking['customer_name'] ?: __( 'there', 'class-bookings-with-stripe' ) )
				);
				?>
			</p>
			<dl class="yb-status__details">
				<dt><?php esc_html_e( 'Class', 'class-bookings-with-stripe' ); ?></dt>
				<dd><?php echo esc_html( $booking['class_name'] ); ?></dd>
				<dt><?php esc_html_e( 'When', 'class-bookings-with-stripe' ); ?></dt>
				<dd><?php echo esc_html( $booking['class_date'] . ' · ' . $booking['class_time'] ); ?></dd>
				<dt><?php esc_html_e( 'Where', 'class-bookings-with-stripe' ); ?></dt>
				<dd><?php echo esc_html( $booking['location'] ); ?></dd>
				<dt><?php esc_html_e( 'Seats', 'class-bookings-with-stripe' ); ?></dt>
				<dd><?php echo esc_html( (string) $booking['seats'] ); ?></dd>
				<dt><?php esc_html_e( 'Total', 'class-bookings-with-stripe' ); ?></dt>
				<dd><?php echo esc_html( $booking['amount_total'] ); ?></dd>
				<dt><?php esc_html_e( 'Reference', 'class-bookings-with-stripe' ); ?></dt>
				<dd><code>#<?php echo esc_html( (string) $booking['booking_id'] ); ?></code></dd>
				<?php if ( ! empty( $booking['extra_fields'] ) && is_array( $booking['extra_fields'] ) ) : ?>
					<?php foreach ( $booking['extra_fields'] as $extra ) : ?>
						<dt><?php echo esc_html( (string) ( $extra['label'] ?? '' ) ); ?></dt>
						<dd><?php echo esc_html( (string) ( $extra['value'] ?? '' ) ); ?></dd>
					<?php endforeach; ?>
				<?php endif; ?>
			</dl>
			<p class="yb-status__hint"><?php esc_html_e( "We've sent a confirmation email — check your inbox.", 'class-bookings-with-stripe' ); ?></p>
			<?php do_action( 'clasbowi_status_after_success_paid', $booking ); ?>
		<?php elseif ( $session_id ) : ?>
			<?php do_action( 'clasbowi_status_before_success_pending', $session_id, $booking ); ?>
			<h2 class="yb-status__title"><?php esc_html_e( 'Confirming your booking…', 'class-bookings-with-stripe' ); ?></h2>
			<p class="yb-status__lede"><?php esc_html_e( "Stripe is letting us know about your payment. This usually takes only a few seconds.", 'class-bookings-with-stripe' ); ?></p>
			<div class="yb-status__pending" aria-live="polite">
				<span class="yb-form__spinner" aria-hidden="true"></span>
				<span class="yb-status__pending-text"><?php esc_html_e( 'Waiting for Stripe…', 'class-bookings-with-stripe' ); ?></span>
			</div>
			<?php do_action( 'clasbowi_status_after_success_pending', $session_id, $booking ); ?>
		<?php else : ?>
			<h2 class="yb-status__title"><?php esc_html_e( 'Thanks for your booking', 'class-bookings-with-stripe' ); ?></h2>
			<p class="yb-status__lede"><?php esc_html_e( "We've sent your confirmation by email.", 'class-bookings-with-stripe' ); ?></p>
		<?php endif; ?>
	</div>

<?php elseif ( 'cancelled' === $type ) : ?>

	<div class="yb-status yb-status--layout-modern yb-status--cancelled">
		<?php do_action( 'clasbowi_status_before_cancelled', $origin ); ?>
		<h2 class="yb-status__title"><?php esc_html_e( 'Booking cancelled', 'class-bookings-with-stripe' ); ?></h2>
		<p class="yb-status__lede"><?php esc_html_e( "No problem — you haven't been charged. Whenever you're ready, you can pick up where you left off.", 'class-bookings-with-stripe' ); ?></p>
		<p class="yb-status__actions">
			<a class="yb-status__button" href="<?php echo esc_url( $origin ); ?>"><?php esc_html_e( 'Try again', 'class-bookings-with-stripe' ); ?></a>
		</p>
		<?php do_action( 'clasbowi_status_after_cancelled', $origin ); ?>
	</div>

<?php else : // error ?>

	<div class="yb-status yb-status--layout-modern yb-status--error">
		<?php do_action( 'clasbowi_status_before_error', $reason, $msg, $origin ); ?>
		<h2 class="yb-status__title"><?php esc_html_e( "We couldn't take your booking", 'class-bookings-with-stripe' ); ?></h2>
		<p class="yb-status__lede">
			<?php
			$reason_msg = $reason_messages[ $reason ] ?? __( 'Something went wrong while taking your booking. Please try again.', 'class-bookings-with-stripe' );
			echo esc_html( $reason_msg );
			?>
		</p>
		<?php if ( $msg ) : ?>
			<p class="yb-status__detail"><?php echo esc_html( $msg ); ?></p>
		<?php endif; ?>
		<p class="yb-status__actions">
			<a class="yb-status__button" href="<?php echo esc_url( $origin ); ?>"><?php esc_html_e( 'Try again', 'class-bookings-with-stripe' ); ?></a>
		</p>
		<p class="yb-status__hint"><?php esc_html_e( "Your card has not been charged. If the problem keeps happening, please email us and we'll book you in by hand.", 'class-bookings-with-stripe' ); ?></p>
		<?php do_action( 'clasbowi_status_after_error', $reason, $msg, $origin ); ?>
	</div>

<?php endif; ?>
<?php do_action( 'clasbowi_status_template_end', $type, $booking ); ?>
