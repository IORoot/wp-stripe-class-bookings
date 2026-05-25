<?php
/**
 * Booking form output for [stripe_booking] and the Elementor widget.
 *
 * @var array<string, mixed>                                                $class_data
 * @var array<int, array{date: string, label: string, remaining: int, cancelled?: bool, selectable?: bool}> $dates
 * @var bool                                                                $show_heading
 * @var int                                                                 $max_seats_today
 *
 * @package IORoot_Yoga_Bookings
 */

defined( 'ABSPATH' ) || exit;

$is_active = ! empty( $class_data['class_active'] );
$has_dates = $is_active && ! empty( $dates );
$use_external_link = ! empty( $class_data['use_external_link'] );
$external_link_url = esc_url( (string) ( $class_data['external_link_url'] ?? '' ) );
$origin    = esc_url( wp_get_referer() ?: home_url( add_query_arg( null, null ) ) );
$show_waiver = (bool) \IORoot_Yoga_Bookings\Helpers::get_option( 'enable_waiver', false );
$waiver_page_url = $show_waiver
	? esc_url( (string) \IORoot_Yoga_Bookings\Helpers::get_option( 'waiver_page_url', '' ) )
	: '';
$show_mailchimp_optin = (bool) \IORoot_Yoga_Bookings\Helpers::get_option( 'enable_mailchimp_optin', false );
$extra_fields = \IORoot_Yoga_Bookings\Extra_Fields::get_fields_for_class( (int) $class_data['id'] );
$show_seats_remaining = ! array_key_exists( 'show_seats_remaining', $class_data ) || ! empty( $class_data['show_seats_remaining'] );
$is_one_off_fixed_date  = ! empty( $class_data['is_one_off_event'] );
$primary_date           = null;
if ( $is_one_off_fixed_date && ! empty( $dates ) ) {
	foreach ( $dates as $d ) {
		if ( empty( $d['cancelled'] ) ) {
			$primary_date = $d;
			break;
		}
	}
	if ( ! $primary_date ) {
		$primary_date = $dates[0];
	}
	if ( ! empty( $primary_date['cancelled'] ) ) {
		$has_dates = false;
	}
}
$labels = apply_filters(
	'ioroot_sb_booking_labels',
	[
		'name'                  => __( 'Your name', 'wp-stripe-class-bookings' ),
		'email'                 => __( 'Email address', 'wp-stripe-class-bookings' ),
		'date'                  => __( 'Choose a date', 'wp-stripe-class-bookings' ),
		'event_date'            => __( 'Event date', 'wp-stripe-class-bookings' ),
		'seats'                 => __( 'How many people?', 'wp-stripe-class-bookings' ),
		'total'                 => __( 'Total', 'wp-stripe-class-bookings' ),
		'book_button'           => __( 'Book & pay with Stripe', 'wp-stripe-class-bookings' ),
		'external_button'       => __( 'Continue to booking', 'wp-stripe-class-bookings' ),
		'external_hint'         => __( 'This class uses an external booking page.', 'wp-stripe-class-bookings' ),
		'invalid_external_hint' => __( 'External booking is enabled, but no URL has been set for this class yet.', 'wp-stripe-class-bookings' ),
		'inactive_hint'         => __( 'Booking is currently unavailable for this class.', 'wp-stripe-class-bookings' ),
		'no_dates_hint'         => __( 'No upcoming dates available — please check back soon or contact us.', 'wp-stripe-class-bookings' ),
		'redirect_hint'         => __( 'You will be redirected to Stripe to complete your payment securely.', 'wp-stripe-class-bookings' ),
		'waiver_label'          => (string) \IORoot_Yoga_Bookings\Helpers::get_option( 'waiver_label', __( 'I confirm I have read and accept the class waiver and participate at my own risk.', 'wp-stripe-class-bookings' ) ),
		'waiver_page_link_text' => __( 'View full waiver', 'wp-stripe-class-bookings' ),
		'mailchimp_optin_label' => (string) \IORoot_Yoga_Bookings\Helpers::get_option( 'mailchimp_optin_label', __( 'Yes, I would like to join the mailing list for class updates and news.', 'wp-stripe-class-bookings' ) ),
	],
	$class_data,
	$dates
);

do_action( 'ioroot_sb_booking_template_start', $class_data, $dates );
?>
<div class="yb-form yb-form--layout-modern" data-yb-class-id="<?php echo esc_attr( (string) $class_data['id'] ); ?>" data-yb-origin="<?php echo esc_attr( $origin ); ?>">
	<div class="yb-form__surface">
		<?php if ( $show_heading ) : ?>
			<?php do_action( 'ioroot_sb_booking_before_heading', $class_data ); ?>
			<header class="yb-form__hero">
				<div class="yb-form__hero-main">
					<h3 class="yb-form__title"><?php echo esc_html( apply_filters( 'ioroot_sb_booking_title', (string) $class_data['name'], $class_data ) ); ?></h3>
					<?php
					$show_price_badge = ! $use_external_link && $is_active && ! empty( $class_data['price'] );
					$meta_bits          = [];
					if ( ! $use_external_link ) {
						$meta_parts = [
							$class_data['location'] ?? '',
							! empty( $class_data['is_one_off_event'] )
								? \IORoot_Yoga_Bookings\Helpers::format_date_range( (string) ( $class_data['start_date'] ?? '' ), (string) ( $class_data['end_date'] ?? '' ) )
								: ( $class_data['day_of_week'] ? ucfirst( $class_data['day_of_week'] ) . 's' : '' ),
							$class_data['start_time'] ? \IORoot_Yoga_Bookings\Helpers::format_time( $class_data['start_time'] ) : '',
							! empty( $class_data['duration'] ) ? sprintf( '%d min', $class_data['duration'] ) : '',
						];
						if ( ! $show_price_badge ) {
							$meta_parts[] = \IORoot_Yoga_Bookings\Helpers::format_price( $class_data['price'] );
						}
						$meta_bits = array_filter( $meta_parts );
					}
					?>
					<?php if ( ! empty( $meta_bits ) ) : ?>
						<p class="yb-form__meta">
							<?php echo esc_html( apply_filters( 'ioroot_sb_booking_meta_text', implode( ' · ', $meta_bits ), $meta_bits, $class_data ) ); ?>
						</p>
					<?php endif; ?>
				</div>
				<?php if ( $show_price_badge ) : ?>
					<div class="yb-form__price-badge">
						<span class="yb-form__price-badge-label"><?php esc_html_e( 'From', 'wp-stripe-class-bookings' ); ?></span>
						<span class="yb-form__price-badge-value"><?php echo esc_html( \IORoot_Yoga_Bookings\Helpers::format_price( (float) $class_data['price'] ) ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $class_data['description'] ) ) : ?>
					<div class="yb-form__description"><?php echo wp_kses_post( apply_filters( 'ioroot_sb_booking_description', (string) $class_data['description'], $class_data ) ); ?></div>
				<?php endif; ?>
			</header>
			<?php do_action( 'ioroot_sb_booking_after_heading', $class_data ); ?>
		<?php endif; ?>

		<div class="yb-form__body">
	<?php if ( ! $is_active ) : ?>
		<p class="yb-form__notice yb-form__notice--warn"><?php echo esc_html( $labels['inactive_hint'] ); ?></p>
	<?php elseif ( $use_external_link && $external_link_url ) : ?>
		<?php do_action( 'ioroot_sb_booking_before_external_link', $class_data, $external_link_url ); ?>
		<a class="yb-form__button yb-form__button--link" href="<?php echo esc_url( $external_link_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo esc_html( $labels['external_button'] ); ?>
		</a>
		<p class="yb-form__hint"><?php echo esc_html( $labels['external_hint'] ); ?></p>
		<?php do_action( 'ioroot_sb_booking_after_external_link', $class_data, $external_link_url ); ?>
	<?php elseif ( $use_external_link ) : ?>
		<p class="yb-form__notice yb-form__notice--warn"><?php echo esc_html( $labels['invalid_external_hint'] ); ?></p>
	<?php elseif ( ! $has_dates ) : ?>
		<p class="yb-form__notice yb-form__notice--warn"><?php echo esc_html( $labels['no_dates_hint'] ); ?></p>
	<?php else : ?>
		<?php do_action( 'ioroot_sb_booking_before_form', $class_data, $dates ); ?>
		<form class="yb-form__form" novalidate data-yb-show-seats-remaining="<?php echo $show_seats_remaining ? '1' : '0'; ?>"<?php echo $is_one_off_fixed_date ? ' data-yb-one-off-date="1"' : ''; ?>>
			<?php do_action( 'ioroot_sb_booking_form_top', $class_data, $dates ); ?>
			<div class="yb-form__card">
			<div class="yb-form__grid yb-form__grid--2">
			<div class="yb-form__row">
				<label class="yb-form__label" for="yb-name-<?php echo esc_attr( (string) $class_data['id'] ); ?>"><?php echo esc_html( $labels['name'] ); ?></label>
				<input class="yb-form__input" id="yb-name-<?php echo esc_attr( (string) $class_data['id'] ); ?>" type="text" name="customer_name" autocomplete="name" required>
			</div>

			<div class="yb-form__row">
				<label class="yb-form__label" for="yb-email-<?php echo esc_attr( (string) $class_data['id'] ); ?>"><?php echo esc_html( $labels['email'] ); ?></label>
				<input class="yb-form__input" id="yb-email-<?php echo esc_attr( (string) $class_data['id'] ); ?>" type="email" name="customer_email" autocomplete="email" required>
			</div>
			</div>

			<div class="yb-form__row">
				<?php if ( $is_one_off_fixed_date && $primary_date ) : ?>
					<span class="yb-form__label" id="yb-date-label-<?php echo esc_attr( (string) $class_data['id'] ); ?>"><?php echo esc_html( $labels['event_date'] ); ?></span>
					<?php if ( ! empty( $primary_date['cancelled'] ) ) : ?>
						<p class="yb-form__date-fixed yb-form__date-fixed--cancelled" aria-labelledby="yb-date-label-<?php echo esc_attr( (string) $class_data['id'] ); ?>">
							<?php
							echo esc_html__( 'Cancelled — ', 'wp-stripe-class-bookings' );
							echo esc_html( (string) $primary_date['label'] );
							?>
						</p>
					<?php else : ?>
						<p class="yb-form__date-fixed" data-yb-date-display aria-labelledby="yb-date-label-<?php echo esc_attr( (string) $class_data['id'] ); ?>">
							<?php
							echo esc_html( (string) $primary_date['label'] );
							if ( $show_seats_remaining ) {
								echo ' · ';
								echo esc_html(
									sprintf(
										/* translators: %d: seats remaining */
										_n( '%d seat left', '%d seats left', (int) $primary_date['remaining'], 'wp-stripe-class-bookings' ),
										(int) $primary_date['remaining']
									)
								);
							}
							?>
						</p>
						<input
							type="hidden"
							id="yb-date-<?php echo esc_attr( (string) $class_data['id'] ); ?>"
							name="class_date"
							value="<?php echo esc_attr( (string) $primary_date['date'] ); ?>"
							data-remaining="<?php echo esc_attr( (string) (int) $primary_date['remaining'] ); ?>"
							data-cancelled="0"
						>
					<?php endif; ?>
				<?php else : ?>
					<label class="yb-form__label" for="yb-date-<?php echo esc_attr( (string) $class_data['id'] ); ?>"><?php echo esc_html( $labels['date'] ); ?></label>
					<select class="yb-form__input yb-form__select" id="yb-date-<?php echo esc_attr( (string) $class_data['id'] ); ?>" name="class_date" data-yb-dates="<?php echo esc_attr( wp_json_encode( $dates ) ); ?>">
						<?php foreach ( $dates as $i => $d ) : ?>
							<?php $is_cancelled = ! empty( $d['cancelled'] ); ?>
							<option value="<?php echo esc_attr( $d['date'] ); ?>" data-remaining="<?php echo esc_attr( (string) $d['remaining'] ); ?>" data-cancelled="<?php echo $is_cancelled ? '1' : '0'; ?>"<?php echo $is_cancelled ? ' disabled class="yb-form__option--cancelled"' : ''; ?>>
								<?php
								if ( $is_cancelled ) {
									echo esc_html__( 'Cancelled — ', 'wp-stripe-class-bookings' );
									echo esc_html( $d['label'] );
								} else {
									echo esc_html( $d['label'] );
									if ( $show_seats_remaining ) {
										echo ' · ';
										echo esc_html(
											sprintf(
												/* translators: %d: seats remaining */
												_n( '%d seat left', '%d seats left', $d['remaining'], 'wp-stripe-class-bookings' ),
												$d['remaining']
											)
										);
									}
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>

			<div class="yb-form__row">
				<label class="yb-form__label" for="yb-seats-<?php echo esc_attr( (string) $class_data['id'] ); ?>"><?php echo esc_html( $labels['seats'] ); ?></label>
				<select class="yb-form__input yb-form__select" id="yb-seats-<?php echo esc_attr( (string) $class_data['id'] ); ?>" name="seats">
					<?php for ( $i = 1; $i <= max( 1, $max_seats_today ); $i++ ) : ?>
						<option value="<?php echo esc_attr( (string) $i ); ?>"><?php echo esc_html( (string) $i ); ?></option>
					<?php endfor; ?>
				</select>
			</div>

			<div class="yb-form__row yb-form__row--total">
				<span class="yb-form__total-label"><?php echo esc_html( $labels['total'] ); ?></span>
				<span class="yb-form__total" data-yb-unit-price="<?php echo esc_attr( (string) $class_data['price'] ); ?>">
					<?php echo esc_html( \IORoot_Yoga_Bookings\Helpers::format_price( (float) $class_data['price'] ) ); ?>
				</span>
			</div>
			<?php if ( ! empty( $extra_fields ) ) : ?>
				<?php do_action( 'ioroot_sb_booking_before_extra_fields', $class_data, $extra_fields ); ?>
				<?php \IORoot_Yoga_Bookings\Extra_Fields::render_fields( $extra_fields ); ?>
				<?php do_action( 'ioroot_sb_booking_after_extra_fields', $class_data, $extra_fields ); ?>
			<?php endif; ?>
			<?php if ( $show_waiver ) : ?>
				<?php
				$waiver_input_id = 'yb-waiver-' . (int) $class_data['id'];
				$waiver_desc_id  = 'yb-waiver-desc-' . (int) $class_data['id'];
				$waiver_html     = \IORoot_Yoga_Bookings\Helpers::waiver_label_kses(
					(string) apply_filters( 'ioroot_sb_waiver_label_html', $labels['waiver_label'], $class_data )
				);
				?>
				<div class="yb-form__row yb-form__row--check yb-form__row--waiver">
					<div class="yb-form__waiver" data-yb-waiver-group>
						<input
							class="yb-form__check-input"
							type="checkbox"
							name="waiver_accepted"
							id="<?php echo esc_attr( $waiver_input_id ); ?>"
							value="1"
							required
							aria-label="<?php echo esc_attr__( 'Accept the waiver', 'wp-stripe-class-bookings' ); ?>"
							aria-describedby="<?php echo esc_attr( $waiver_desc_id ); ?>"
						>
						<div class="yb-form__waiver-body" id="<?php echo esc_attr( $waiver_desc_id ); ?>" data-yb-waiver-label>
							<?php echo $waiver_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- waiver_label_kses(). ?>
						</div>
					</div>
					<?php if ( $waiver_page_url ) : ?>
						<p class="yb-form__waiver-page-link">
							<a class="yb-form__link" href="<?php echo esc_url( $waiver_page_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( apply_filters( 'ioroot_sb_waiver_page_link_text', $labels['waiver_page_link_text'], $class_data ) ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $show_mailchimp_optin ) : ?>
				<div class="yb-form__row yb-form__row--check">
					<label class="yb-form__check">
						<input class="yb-form__check-input" type="checkbox" name="mailchimp_opt_in" value="1">
						<span class="yb-form__check-label"><?php echo wp_kses_post( $labels['mailchimp_optin_label'] ); ?></span>
					</label>
				</div>
			<?php endif; ?>

			<button type="submit" class="yb-form__button">
				<span class="yb-form__button-logo" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.594-7.305h.003z"></path>
					</svg>
				</span>
				<span class="yb-form__button-label"><?php echo esc_html( $labels['book_button'] ); ?></span>
				<span class="yb-form__spinner" aria-hidden="true"></span>
			</button>

			<p class="yb-form__error" role="alert" hidden></p>
			<p class="yb-form__hint"><?php echo esc_html( $labels['redirect_hint'] ); ?></p>
			<?php do_action( 'ioroot_sb_booking_form_bottom', $class_data, $dates ); ?>
			</div>
		</form>
		<?php do_action( 'ioroot_sb_booking_after_form', $class_data, $dates ); ?>
	<?php endif; ?>
		</div>
	</div>
</div>
<?php do_action( 'ioroot_sb_booking_template_end', $class_data, $dates ); ?>
