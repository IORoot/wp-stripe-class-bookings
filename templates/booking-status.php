<?php
/**
 * Booking status layout — HTML structure lives here; components render content fragments only.
 *
 * @var \IOROOT_STRIPE_BOOKINGS\Booking_Status_View $view
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View layout; variables extracted from $view.

do_action( 'clasbowi_status_template_start', $type, $booking );

$status_class = $view->get_status_class();
?>
<div class="cbfs-status cbfs-status--layout-modern cbfs-status--<?php echo esc_attr( $status_class ); ?>"<?php echo 'success' === $view->type ? ' data-cbfs-session="' . esc_attr( $view->session_id ) . '" data-cbfs-token="' . esc_attr( $view->status_token ) . '"' : ''; ?>>
	<?php $view->render( 'title' ); ?>
	<?php $view->render( 'lede' ); ?>
	<?php $view->render( 'details-list' ); ?>
	<?php $view->render( 'pending-spinner' ); ?>
	<?php $view->render( 'try-again-button' ); ?>
	<?php $view->render( 'error-detail' ); ?>
	<?php $view->render( 'hint' ); ?>
</div>
<?php
do_action( 'clasbowi_status_template_end', $type, $booking );
