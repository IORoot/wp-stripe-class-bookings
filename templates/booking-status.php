<?php
/**
 * Output for [stripe_booking_status type="success|cancelled|error"].
 *
 * @var string                                                    $type
 * @var string                                                    $session_id
 * @var string                                                    $reason
 * @var string                                                    $msg
 * @var string                                                    $origin
 * @var array<string, mixed>|null                                 $booking
 *
 * @package IORoot_Yoga_Bookings
 */

defined( 'ABSPATH' ) || exit;

use IORoot_Yoga_Bookings\Helpers;

$reason_messages = [
	'capacity_full'   => __( "Sorry — that class just filled up while you were booking. Please try a different date.", 'ioroot-yoga-bookings' ),
	'class_inactive'  => __( 'Bookings for this class are currently unavailable. Please check back soon.', 'ioroot-yoga-bookings' ),
	'date_invalid'    => __( 'That date is no longer available. Please choose another.', 'ioroot-yoga-bookings' ),
	'class_not_found' => __( 'We could not find that class. It may have been removed.', 'ioroot-yoga-bookings' ),
	'stripe_error'    => __( 'We could not connect to our payment provider. Please try again in a moment.', 'ioroot-yoga-bookings' ),
	'validation'      => __( 'Some details were missing or invalid. Please check the form and try again.', 'ioroot-yoga-bookings' ),
	'internal'        => __( 'Something went wrong on our end. Please try again — your card has not been charged.', 'ioroot-yoga-bookings' ),
];
$reason_messages = apply_filters( 'ioroot_sb_status_reason_messages', $reason_messages, $type, $reason, $booking );

do_action( 'ioroot_sb_status_template_start', $type, $booking );
?>

<?php if ( 'success' === $type ) : ?>

	<div class="yb-status yb-status--layout-modern yb-status--<?php echo esc_attr( $booking['status'] ?? 'pending' ); ?>" data-yb-session="<?php echo esc_attr( $session_id ); ?>">
		<?php if ( $booking && 'paid' === $booking['status'] ) : ?>
			<?php do_action( 'ioroot_sb_status_before_success_paid', $booking ); ?>
			<h2 class="yb-status__title"><?php esc_html_e( 'Booking confirmed', 'ioroot-yoga-bookings' ); ?></h2>
			<p class="yb-status__lede">
				<?php
				printf(
					/* translators: %s: customer name */
					esc_html__( 'Thanks %s — we can\'t wait to see you on the mat.', 'ioroot-yoga-bookings' ),
					esc_html( $booking['customer_name'] ?: __( 'there', 'ioroot-yoga-bookings' ) )
				);
				?>
			</p>
			<dl class="yb-status__details">
				<dt><?php esc_html_e( 'Class', 'ioroot-yoga-bookings' ); ?></dt>
				<dd><?php echo esc_html( $booking['class_name'] ); ?></dd>
				<dt><?php esc_html_e( 'When', 'ioroot-yoga-bookings' ); ?></dt>
				<dd><?php echo esc_html( $booking['class_date'] . ' · ' . $booking['class_time'] ); ?></dd>
				<dt><?php esc_html_e( 'Where', 'ioroot-yoga-bookings' ); ?></dt>
				<dd><?php echo esc_html( $booking['location'] ); ?></dd>
				<dt><?php esc_html_e( 'Seats', 'ioroot-yoga-bookings' ); ?></dt>
				<dd><?php echo esc_html( (string) $booking['seats'] ); ?></dd>
				<dt><?php esc_html_e( 'Total', 'ioroot-yoga-bookings' ); ?></dt>
				<dd><?php echo esc_html( $booking['amount_total'] ); ?></dd>
				<dt><?php esc_html_e( 'Reference', 'ioroot-yoga-bookings' ); ?></dt>
				<dd><code>#<?php echo esc_html( (string) $booking['booking_id'] ); ?></code></dd>
				<?php if ( ! empty( $booking['extra_fields'] ) && is_array( $booking['extra_fields'] ) ) : ?>
					<?php foreach ( $booking['extra_fields'] as $extra ) : ?>
						<dt><?php echo esc_html( (string) ( $extra['label'] ?? '' ) ); ?></dt>
						<dd><?php echo esc_html( (string) ( $extra['value'] ?? '' ) ); ?></dd>
					<?php endforeach; ?>
				<?php endif; ?>
			</dl>
			<p class="yb-status__hint"><?php esc_html_e( "We've sent a confirmation email — check your inbox.", 'ioroot-yoga-bookings' ); ?></p>
			<?php do_action( 'ioroot_sb_status_after_success_paid', $booking ); ?>
		<?php elseif ( $session_id ) : ?>
			<?php do_action( 'ioroot_sb_status_before_success_pending', $session_id, $booking ); ?>
			<h2 class="yb-status__title"><?php esc_html_e( 'Confirming your booking…', 'ioroot-yoga-bookings' ); ?></h2>
			<p class="yb-status__lede"><?php esc_html_e( "Stripe is letting us know about your payment. This usually takes only a few seconds.", 'ioroot-yoga-bookings' ); ?></p>
			<div class="yb-status__pending" aria-live="polite">
				<span class="yb-form__spinner" aria-hidden="true"></span>
				<span class="yb-status__pending-text"><?php esc_html_e( 'Waiting for Stripe…', 'ioroot-yoga-bookings' ); ?></span>
			</div>
			<?php do_action( 'ioroot_sb_status_after_success_pending', $session_id, $booking ); ?>
		<?php else : ?>
			<h2 class="yb-status__title"><?php esc_html_e( 'Thanks for your booking', 'ioroot-yoga-bookings' ); ?></h2>
			<p class="yb-status__lede"><?php esc_html_e( "We've sent your confirmation by email.", 'ioroot-yoga-bookings' ); ?></p>
		<?php endif; ?>
	</div>

<?php elseif ( 'cancelled' === $type ) : ?>

	<div class="yb-status yb-status--layout-modern yb-status--cancelled">
		<?php do_action( 'ioroot_sb_status_before_cancelled', $origin ); ?>
		<h2 class="yb-status__title"><?php esc_html_e( 'Booking cancelled', 'ioroot-yoga-bookings' ); ?></h2>
		<p class="yb-status__lede"><?php esc_html_e( "No problem — you haven't been charged. Whenever you're ready, you can pick up where you left off.", 'ioroot-yoga-bookings' ); ?></p>
		<p class="yb-status__actions">
			<a class="yb-status__button" href="<?php echo esc_url( $origin ); ?>"><?php esc_html_e( 'Try again', 'ioroot-yoga-bookings' ); ?></a>
		</p>
		<?php do_action( 'ioroot_sb_status_after_cancelled', $origin ); ?>
	</div>

<?php else : // error ?>

	<div class="yb-status yb-status--layout-modern yb-status--error">
		<?php do_action( 'ioroot_sb_status_before_error', $reason, $msg, $origin ); ?>
		<h2 class="yb-status__title"><?php esc_html_e( "We couldn't take your booking", 'ioroot-yoga-bookings' ); ?></h2>
		<p class="yb-status__lede">
			<?php
			$reason_msg = $reason_messages[ $reason ] ?? __( 'Something went wrong while taking your booking. Please try again.', 'ioroot-yoga-bookings' );
			echo esc_html( $reason_msg );
			?>
		</p>
		<?php if ( $msg ) : ?>
			<p class="yb-status__detail"><?php echo esc_html( $msg ); ?></p>
		<?php endif; ?>
		<p class="yb-status__actions">
			<a class="yb-status__button" href="<?php echo esc_url( $origin ); ?>"><?php esc_html_e( 'Try again', 'ioroot-yoga-bookings' ); ?></a>
		</p>
		<p class="yb-status__hint"><?php esc_html_e( "Your card has not been charged. If the problem keeps happening, please email us and we'll book you in by hand.", 'ioroot-yoga-bookings' ); ?></p>
		<?php do_action( 'ioroot_sb_status_after_error', $reason, $msg, $origin ); ?>
	</div>

<?php endif; ?>
<?php do_action( 'ioroot_sb_status_template_end', $type, $booking ); ?>
