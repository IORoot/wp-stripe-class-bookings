<?php
/**
 * Intro / welcome panel for Class Bookings with Stripe → Settings (admin).
 *
 * Edit this file to change the HTML shown above the settings tabs. You can
 * also override it in your theme as:
 *   class-bookings-with-stripe/admin-settings-intro.php
 *
 * This file is included in an admin context; output is not escaped by the
 * plugin — use only trusted markup.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

defined( 'ABSPATH' ) || exit;

$clasbowi_settings_url = admin_url( 'edit.php?post_type=' . \IOROOT_STRIPE_BOOKINGS\CPT::CLASS_PT . '&page=clasbowi-settings' );
$clasbowi_reports_url  = admin_url( 'edit.php?post_type=' . \IOROOT_STRIPE_BOOKINGS\CPT::CLASS_PT . '&page=clasbowi-reports' );
$clasbowi_new_class_url = admin_url( 'post-new.php?post_type=' . \IOROOT_STRIPE_BOOKINGS\CPT::CLASS_PT );
?>
<div class="clasbowi-welcome" id="clasbowi-welcome-panel" role="region" aria-labelledby="clasbowi-welcome-heading">
	<div class="clasbowi-welcome__toolbar">
		<p class="clasbowi-welcome__toolbar-summary">
			<?php esc_html_e( 'Class Bookings with Stripe overview is hidden. Expand to see getting started steps and shortcuts.', 'class-bookings-with-stripe' ); ?>
		</p>
		<button type="button" class="button clasbowi-welcome__panel-toggle" id="clasbowi-welcome-toggle" aria-expanded="true" aria-controls="clasbowi-welcome-expandable" aria-label="<?php esc_attr_e( 'Hide overview panel', 'class-bookings-with-stripe' ); ?>" data-clasbowi-aria-expanded="<?php esc_attr_e( 'Hide overview panel', 'class-bookings-with-stripe' ); ?>" data-clasbowi-aria-collapsed="<?php esc_attr_e( 'Show overview panel', 'class-bookings-with-stripe' ); ?>">
			<span class="clasbowi-welcome__panel-toggle-label clasbowi-welcome__panel-toggle-label--expanded"><?php esc_html_e( 'Hide panel', 'class-bookings-with-stripe' ); ?></span>
			<span class="clasbowi-welcome__panel-toggle-label clasbowi-welcome__panel-toggle-label--collapsed"><?php esc_html_e( 'Show panel', 'class-bookings-with-stripe' ); ?></span>
			<span class="clasbowi-welcome__panel-toggle-chevron dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
		</button>
	</div>
	<div id="clasbowi-welcome-expandable" class="clasbowi-welcome__expandable" aria-hidden="false">
	<div class="clasbowi-welcome__bg" aria-hidden="true">
		<span class="clasbowi-welcome__blob clasbowi-welcome__blob--1"></span>
		<span class="clasbowi-welcome__blob clasbowi-welcome__blob--2"></span>
		<span class="clasbowi-welcome__blob clasbowi-welcome__blob--3"></span>
		<span class="clasbowi-welcome__grid-dots"></span>
	</div>

	<div class="clasbowi-welcome__shell">
		<div class="clasbowi-welcome__layout">
			<div class="clasbowi-welcome__hero">
				<div class="clasbowi-welcome__badges">
					<span class="clasbowi-welcome__pill clasbowi-welcome__pill--brand">
						<span class="clasbowi-welcome__pill-dot" aria-hidden="true"></span>
						<?php esc_html_e( 'IORoot', 'class-bookings-with-stripe' ); ?>
					</span>
					<span class="clasbowi-welcome__pill">
						<?php esc_html_e( 'Getting started', 'class-bookings-with-stripe' ); ?>
					</span>
				</div>

				<div class="clasbowi-welcome__title-row">
					<div class="clasbowi-welcome__logo-wrap">
						<img
							class="clasbowi-welcome__logo"
							src="<?php echo esc_url( CLASBOWI_URL . 'assets/logo_plugin.svg' ); ?>"
							width="88"
							height="74"
							alt=""
							decoding="async"
							loading="lazy"
						/>
					</div>
					<h2 id="clasbowi-welcome-heading" class="clasbowi-welcome__title">
						<span class="clasbowi-welcome__title-line"><?php esc_html_e( 'Welcome to', 'class-bookings-with-stripe' ); ?></span>
						<span class="clasbowi-welcome__title-accent"><?php esc_html_e( 'Class Bookings with Stripe', 'class-bookings-with-stripe' ); ?></span>
					</h2>
				</div>

				<p class="clasbowi-welcome__lede">
					<?php esc_html_e( 'Work through the steps below once. When you are ready for day-to-day tasks, use the shortcuts on the right.', 'class-bookings-with-stripe' ); ?>
				</p>

				<ol class="clasbowi-welcome__timeline">
					<li class="clasbowi-welcome__timeline-item">
						<span class="clasbowi-welcome__timeline-marker" aria-hidden="true">1</span>
						<div class="clasbowi-welcome__timeline-body">
							<strong><?php esc_html_e( 'Connect Stripe keys and webhook', 'class-bookings-with-stripe' ); ?></strong>
							<span><?php esc_html_e( 'Open the Stripe tab, add your publishable and secret keys, set test or live mode, and register the webhook signing secret from your Stripe dashboard.', 'class-bookings-with-stripe' ); ?></span>
						</div>
					</li>
					<li class="clasbowi-welcome__timeline-item">
						<span class="clasbowi-welcome__timeline-marker" aria-hidden="true">2</span>
						<div class="clasbowi-welcome__timeline-body">
							<strong><?php esc_html_e( 'Add custom fields and embed the booking form', 'class-bookings-with-stripe' ); ?></strong>
							<span><?php esc_html_e( 'Optional extras live under Form extras (ACF). Then place the Elementor block or shortcode on the page where customers should book.', 'class-bookings-with-stripe' ); ?></span>
						</div>
					</li>
					<li class="clasbowi-welcome__timeline-item">
						<span class="clasbowi-welcome__timeline-marker" aria-hidden="true">3</span>
						<div class="clasbowi-welcome__timeline-body">
							<strong><?php esc_html_e( 'Set up emails', 'class-bookings-with-stripe' ); ?></strong>
							<span><?php esc_html_e( 'Use the Emails tab for subjects and bodies, merge tags, and admin notifications (emails always use WordPress wp_mail()).', 'class-bookings-with-stripe' ); ?></span>
						</div>
					</li>
				</ol>
			</div>

			<aside class="clasbowi-welcome__aside" aria-label="<?php esc_attr_e( 'Quick reference', 'class-bookings-with-stripe' ); ?>">
				<div class="clasbowi-welcome__bento clasbowi-welcome__bento--stack">
					<div class="clasbowi-welcome__tile clasbowi-welcome__tile--wide clasbowi-welcome__tile--cta-row">
						<div class="clasbowi-welcome__tile-icon-wrap clasbowi-welcome__tile-icon-wrap--violet">
							<span class="clasbowi-welcome__tile-icon dashicons dashicons-email" aria-hidden="true"></span>
						</div>
						<div class="clasbowi-welcome__tile-copy">
							<strong><?php esc_html_e( 'Email Templates', 'class-bookings-with-stripe' ); ?></strong>
							<p><?php esc_html_e( 'Edit customer and admin messages, merge tags, and admin notification address. Mail is sent with WordPress wp_mail().', 'class-bookings-with-stripe' ); ?></p>
						</div>
						<div class="clasbowi-welcome__tile-aside">
							<a class="button clasbowi-welcome__tile-action clasbowi-welcome__tile-action--violet" href="<?php echo esc_url( $clasbowi_settings_url . '#clasbowi-tab-field_clasbowi_tab_emails' ); ?>">
								<?php esc_html_e( 'Open Emails tab', 'class-bookings-with-stripe' ); ?>
							</a>
						</div>
					</div>
					<div class="clasbowi-welcome__tile clasbowi-welcome__tile--wide clasbowi-welcome__tile--cta-row">
						<div class="clasbowi-welcome__tile-icon-wrap">
							<span class="clasbowi-welcome__tile-icon dashicons dashicons-chart-area" aria-hidden="true"></span>
						</div>
						<div class="clasbowi-welcome__tile-copy">
							<strong><?php esc_html_e( 'Reports', 'class-bookings-with-stripe' ); ?></strong>
							<p><?php esc_html_e( 'Historic trends, upcoming attendance, and guest lists by class.', 'class-bookings-with-stripe' ); ?></p>
						</div>
						<div class="clasbowi-welcome__tile-aside">
							<a class="button clasbowi-welcome__tile-action clasbowi-welcome__tile-action--teal" href="<?php echo esc_url( $clasbowi_reports_url ); ?>">
								<?php esc_html_e( 'Open reports', 'class-bookings-with-stripe' ); ?>
							</a>
						</div>
					</div>
					<div class="clasbowi-welcome__tile clasbowi-welcome__tile--wide clasbowi-welcome__tile--cta-row">
						<div class="clasbowi-welcome__tile-icon-wrap clasbowi-welcome__tile-icon-wrap--amber">
							<span class="clasbowi-welcome__tile-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						</div>
						<div class="clasbowi-welcome__tile-copy">
							<strong><?php esc_html_e( 'Weekly Classes / One-off Events / External Links', 'class-bookings-with-stripe' ); ?></strong>
							<p><?php esc_html_e( 'Add a Class, set schedule or single dates, price, capacity, or an external booking URL.', 'class-bookings-with-stripe' ); ?></p>
						</div>
						<div class="clasbowi-welcome__tile-aside">
							<a class="button clasbowi-welcome__tile-action clasbowi-welcome__tile-action--amber" href="<?php echo esc_url( $clasbowi_new_class_url ); ?>">
								<?php esc_html_e( 'Add new class', 'class-bookings-with-stripe' ); ?>
							</a>
						</div>
					</div>
					<div class="clasbowi-welcome__tile clasbowi-welcome__tile--wide clasbowi-welcome__tile--cta-row">
						<div class="clasbowi-welcome__tile-icon-wrap clasbowi-welcome__tile-icon-wrap--emerald">
							<span class="clasbowi-welcome__tile-icon dashicons dashicons-admin-plugins" aria-hidden="true"></span>
						</div>
						<div class="clasbowi-welcome__tile-copy">
							<strong><?php esc_html_e( 'Extend with ACF', 'class-bookings-with-stripe' ); ?></strong>
							<p><?php esc_html_e( 'Form Extras: waiver, Mailchimp opt-in, and custom ACF fields on the booking form.', 'class-bookings-with-stripe' ); ?></p>
						</div>
						<div class="clasbowi-welcome__tile-aside">
							<a class="button clasbowi-welcome__tile-action clasbowi-welcome__tile-action--emerald" href="<?php echo esc_url( $clasbowi_settings_url . '#clasbowi-tab-field_clasbowi_tab_pages' ); ?>">
								<?php esc_html_e( 'Open Form extras tab', 'class-bookings-with-stripe' ); ?>
							</a>
						</div>
					</div>
				</div>
			</aside>
		</div>

	</div>
	</div>
</div>
