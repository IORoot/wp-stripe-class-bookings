<?php
/**
 * Intro / welcome panel for Stripe Class Bookings → Settings (admin).
 *
 * Edit this file to change the HTML shown above the settings tabs. You can
 * also override it in your theme as:
 *   ioroot-stripe-bookings/admin-settings-intro.php
 *   ioroot-yoga-bookings/admin-settings-intro.php
 *
 * This file is included in an admin context; output is not escaped by the
 * plugin — use only trusted markup.
 *
 * @package IORoot_Yoga_Bookings
 */

defined( 'ABSPATH' ) || exit;

$ioroot_yb_settings_url = admin_url( 'edit.php?post_type=' . \IORoot_Yoga_Bookings\CPT::CLASS_PT . '&page=stripe-bookings-settings' );
$ioroot_yb_reports_url  = admin_url( 'edit.php?post_type=' . \IORoot_Yoga_Bookings\CPT::CLASS_PT . '&page=stripe-bookings-reports' );
$ioroot_yb_new_class_url = admin_url( 'post-new.php?post_type=' . \IORoot_Yoga_Bookings\CPT::CLASS_PT );
?>
<div class="ioroot-yb-welcome" id="ioroot-yb-welcome-panel" role="region" aria-labelledby="ioroot-yb-welcome-heading">
	<div class="ioroot-yb-welcome__toolbar">
		<p class="ioroot-yb-welcome__toolbar-summary">
			<?php esc_html_e( 'Stripe Class Bookings overview is hidden. Expand to see getting started steps and shortcuts.', 'ioroot-yoga-bookings' ); ?>
		</p>
		<button type="button" class="button ioroot-yb-welcome__panel-toggle" id="ioroot-yb-welcome-toggle" aria-expanded="true" aria-controls="ioroot-yb-welcome-expandable" aria-label="<?php esc_attr_e( 'Hide overview panel', 'ioroot-yoga-bookings' ); ?>" data-ioroot-yb-aria-expanded="<?php esc_attr_e( 'Hide overview panel', 'ioroot-yoga-bookings' ); ?>" data-ioroot-yb-aria-collapsed="<?php esc_attr_e( 'Show overview panel', 'ioroot-yoga-bookings' ); ?>">
			<span class="ioroot-yb-welcome__panel-toggle-label ioroot-yb-welcome__panel-toggle-label--expanded"><?php esc_html_e( 'Hide panel', 'ioroot-yoga-bookings' ); ?></span>
			<span class="ioroot-yb-welcome__panel-toggle-label ioroot-yb-welcome__panel-toggle-label--collapsed"><?php esc_html_e( 'Show panel', 'ioroot-yoga-bookings' ); ?></span>
			<span class="ioroot-yb-welcome__panel-toggle-chevron dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
		</button>
	</div>
	<div id="ioroot-yb-welcome-expandable" class="ioroot-yb-welcome__expandable" aria-hidden="false">
	<div class="ioroot-yb-welcome__bg" aria-hidden="true">
		<span class="ioroot-yb-welcome__blob ioroot-yb-welcome__blob--1"></span>
		<span class="ioroot-yb-welcome__blob ioroot-yb-welcome__blob--2"></span>
		<span class="ioroot-yb-welcome__blob ioroot-yb-welcome__blob--3"></span>
		<span class="ioroot-yb-welcome__grid-dots"></span>
	</div>

	<div class="ioroot-yb-welcome__shell">
		<div class="ioroot-yb-welcome__layout">
			<div class="ioroot-yb-welcome__hero">
				<div class="ioroot-yb-welcome__badges">
					<span class="ioroot-yb-welcome__pill ioroot-yb-welcome__pill--brand">
						<span class="ioroot-yb-welcome__pill-dot" aria-hidden="true"></span>
						<?php esc_html_e( 'IORoot', 'ioroot-yoga-bookings' ); ?>
					</span>
					<span class="ioroot-yb-welcome__pill">
						<?php esc_html_e( 'Getting started', 'ioroot-yoga-bookings' ); ?>
					</span>
				</div>

				<div class="ioroot-yb-welcome__title-row">
					<div class="ioroot-yb-welcome__logo-wrap">
						<img
							class="ioroot-yb-welcome__logo"
							src="<?php echo esc_url( IOROOT_YB_URL . 'assets/logo_plugin.svg' ); ?>"
							width="88"
							height="74"
							alt=""
							decoding="async"
							loading="lazy"
						/>
					</div>
					<h2 id="ioroot-yb-welcome-heading" class="ioroot-yb-welcome__title">
						<span class="ioroot-yb-welcome__title-line"><?php esc_html_e( 'Welcome to', 'ioroot-yoga-bookings' ); ?></span>
						<span class="ioroot-yb-welcome__title-accent"><?php esc_html_e( 'Stripe Class Bookings', 'ioroot-yoga-bookings' ); ?></span>
					</h2>
				</div>

				<p class="ioroot-yb-welcome__lede">
					<?php esc_html_e( 'Work through the steps below once. When you are ready for day-to-day tasks, use the shortcuts on the right.', 'ioroot-yoga-bookings' ); ?>
				</p>

				<ol class="ioroot-yb-welcome__timeline">
					<li class="ioroot-yb-welcome__timeline-item">
						<span class="ioroot-yb-welcome__timeline-marker" aria-hidden="true">1</span>
						<div class="ioroot-yb-welcome__timeline-body">
							<strong><?php esc_html_e( 'Connect Stripe keys and webhook', 'ioroot-yoga-bookings' ); ?></strong>
							<span><?php esc_html_e( 'Open the Stripe tab, add your publishable and secret keys, set test or live mode, and register the webhook signing secret from your Stripe dashboard.', 'ioroot-yoga-bookings' ); ?></span>
						</div>
					</li>
					<li class="ioroot-yb-welcome__timeline-item">
						<span class="ioroot-yb-welcome__timeline-marker" aria-hidden="true">2</span>
						<div class="ioroot-yb-welcome__timeline-body">
							<strong><?php esc_html_e( 'Add custom fields and embed the booking form', 'ioroot-yoga-bookings' ); ?></strong>
							<span><?php esc_html_e( 'Optional extras live under Form extras (ACF). Then place the Elementor block or shortcode on the page where customers should book.', 'ioroot-yoga-bookings' ); ?></span>
						</div>
					</li>
					<li class="ioroot-yb-welcome__timeline-item">
						<span class="ioroot-yb-welcome__timeline-marker" aria-hidden="true">3</span>
						<div class="ioroot-yb-welcome__timeline-body">
							<strong><?php esc_html_e( 'Set up emails', 'ioroot-yoga-bookings' ); ?></strong>
							<span><?php esc_html_e( 'Use the Emails tab for subjects and bodies, merge tags, and admin notifications (emails always use WordPress wp_mail()).', 'ioroot-yoga-bookings' ); ?></span>
						</div>
					</li>
				</ol>
			</div>

			<aside class="ioroot-yb-welcome__aside" aria-label="<?php esc_attr_e( 'Quick reference', 'ioroot-yoga-bookings' ); ?>">
				<div class="ioroot-yb-welcome__bento ioroot-yb-welcome__bento--stack">
					<div class="ioroot-yb-welcome__tile ioroot-yb-welcome__tile--wide ioroot-yb-welcome__tile--cta-row">
						<div class="ioroot-yb-welcome__tile-icon-wrap ioroot-yb-welcome__tile-icon-wrap--violet">
							<span class="ioroot-yb-welcome__tile-icon dashicons dashicons-email" aria-hidden="true"></span>
						</div>
						<div class="ioroot-yb-welcome__tile-copy">
							<strong><?php esc_html_e( 'Email Templates', 'ioroot-yoga-bookings' ); ?></strong>
							<p><?php esc_html_e( 'Edit customer and admin messages, merge tags, and admin notification address. Mail is sent with WordPress wp_mail().', 'ioroot-yoga-bookings' ); ?></p>
						</div>
						<div class="ioroot-yb-welcome__tile-aside">
							<a class="button ioroot-yb-welcome__tile-action ioroot-yb-welcome__tile-action--violet" href="<?php echo esc_url( $ioroot_yb_settings_url . '#yb-tab-field_yb_tab_emails' ); ?>">
								<?php esc_html_e( 'Open Emails tab', 'ioroot-yoga-bookings' ); ?>
							</a>
						</div>
					</div>
					<div class="ioroot-yb-welcome__tile ioroot-yb-welcome__tile--wide ioroot-yb-welcome__tile--cta-row">
						<div class="ioroot-yb-welcome__tile-icon-wrap">
							<span class="ioroot-yb-welcome__tile-icon dashicons dashicons-chart-area" aria-hidden="true"></span>
						</div>
						<div class="ioroot-yb-welcome__tile-copy">
							<strong><?php esc_html_e( 'Reports', 'ioroot-yoga-bookings' ); ?></strong>
							<p><?php esc_html_e( 'Historic trends, upcoming attendance, and guest lists by class.', 'ioroot-yoga-bookings' ); ?></p>
						</div>
						<div class="ioroot-yb-welcome__tile-aside">
							<a class="button ioroot-yb-welcome__tile-action ioroot-yb-welcome__tile-action--teal" href="<?php echo esc_url( $ioroot_yb_reports_url ); ?>">
								<?php esc_html_e( 'Open reports', 'ioroot-yoga-bookings' ); ?>
							</a>
						</div>
					</div>
					<div class="ioroot-yb-welcome__tile ioroot-yb-welcome__tile--wide ioroot-yb-welcome__tile--cta-row">
						<div class="ioroot-yb-welcome__tile-icon-wrap ioroot-yb-welcome__tile-icon-wrap--amber">
							<span class="ioroot-yb-welcome__tile-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						</div>
						<div class="ioroot-yb-welcome__tile-copy">
							<strong><?php esc_html_e( 'Weekly Classes / One-off Events / External Links', 'ioroot-yoga-bookings' ); ?></strong>
							<p><?php esc_html_e( 'Add a Stripe Class, set schedule or single dates, price, capacity, or an external booking URL.', 'ioroot-yoga-bookings' ); ?></p>
						</div>
						<div class="ioroot-yb-welcome__tile-aside">
							<a class="button ioroot-yb-welcome__tile-action ioroot-yb-welcome__tile-action--amber" href="<?php echo esc_url( $ioroot_yb_new_class_url ); ?>">
								<?php esc_html_e( 'Add new class', 'ioroot-yoga-bookings' ); ?>
							</a>
						</div>
					</div>
					<div class="ioroot-yb-welcome__tile ioroot-yb-welcome__tile--wide ioroot-yb-welcome__tile--cta-row">
						<div class="ioroot-yb-welcome__tile-icon-wrap ioroot-yb-welcome__tile-icon-wrap--emerald">
							<span class="ioroot-yb-welcome__tile-icon dashicons dashicons-admin-plugins" aria-hidden="true"></span>
						</div>
						<div class="ioroot-yb-welcome__tile-copy">
							<strong><?php esc_html_e( 'Extend with ACF', 'ioroot-yoga-bookings' ); ?></strong>
							<p><?php esc_html_e( 'Form Extras: waiver, Mailchimp opt-in, and custom ACF fields on the booking form.', 'ioroot-yoga-bookings' ); ?></p>
						</div>
						<div class="ioroot-yb-welcome__tile-aside">
							<a class="button ioroot-yb-welcome__tile-action ioroot-yb-welcome__tile-action--emerald" href="<?php echo esc_url( $ioroot_yb_settings_url . '#yb-tab-field_yb_tab_pages' ); ?>">
								<?php esc_html_e( 'Open Form extras tab', 'ioroot-yoga-bookings' ); ?>
							</a>
						</div>
					</div>
				</div>
			</aside>
		</div>

	</div>
	</div>
	<script>
	(function () {
		var LS_KEY = 'ioroot_yb_welcome_intro_collapsed';
		var root = document.getElementById('ioroot-yb-welcome-panel');
		var toggle = document.getElementById('ioroot-yb-welcome-toggle');
		var expandable = document.getElementById('ioroot-yb-welcome-expandable');
		if (root && toggle && expandable) {
			function setCollapsed(collapsed) {
				root.classList.toggle('is-collapsed', collapsed);
				toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
				var ax = collapsed ? toggle.getAttribute('data-ioroot-yb-aria-collapsed') : toggle.getAttribute('data-ioroot-yb-aria-expanded');
				if (ax) {
					toggle.setAttribute('aria-label', ax);
				}
				expandable.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
				try {
					localStorage.setItem(LS_KEY, collapsed ? '1' : '0');
				} catch (e) {}
			}
			try {
				if (localStorage.getItem(LS_KEY) === '1') {
					setCollapsed(true);
				}
			} catch (e) {}
			toggle.addEventListener('click', function () {
				setCollapsed(!root.classList.contains('is-collapsed'));
			});
		}

		function iorootYbOpenSettingsTabFromHash() {
			var h = window.location.hash || '';
			if (h.indexOf('#yb-tab-') !== 0) {
				return;
			}
			var key = h.slice('#yb-tab-'.length);
			if (!/^field_yb_tab_[a-z0-9_]+$/i.test(key)) {
				return;
			}
			var btn = document.querySelector('.acf-tab-button[data-key="' + key + '"]');
			if (btn && typeof btn.click === 'function') {
				btn.click();
			}
		}
		function iorootYbScheduleTabFromHash() {
			iorootYbOpenSettingsTabFromHash();
			window.setTimeout(iorootYbOpenSettingsTabFromHash, 0);
			window.setTimeout(iorootYbOpenSettingsTabFromHash, 250);
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', iorootYbScheduleTabFromHash);
		} else {
			iorootYbScheduleTabFromHash();
		}
		window.addEventListener('hashchange', iorootYbScheduleTabFromHash);
	})();
	</script>
</div>
