=== Class Bookings with Stripe ===
Contributors: ioroot
Tags: stripe, booking, classes, checkout, elementor
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stripe Checkout booking for classes: capacity-aware dates, soft holds, webhooks, emails, reports, Elementor widget, and shortcode.

== Description ==

**Class Bookings with Stripe** helps studios, instructors, and venues sell places on scheduled classes using **Stripe Checkout** (Stripe’s hosted payment page). Customers pick a date, choose seats, and pay securely without WooCommerce or a custom cart.

The plugin uses **Advanced Custom Fields (ACF)**. Each bookable class has schedule, price, capacity, and optional extras. Embed the form with a shortcode or Elementor widget. When a customer clicks **Book & pay with Stripe**, the plugin creates a soft-hold, opens a Stripe Checkout Session, and redirects to Stripe. A signed webhook marks the booking paid and can send customer and admin emails.

= Video tutorials — get started =

**New to the plugin?** Watch these first:

* [Quick start: install, API keys & first booking](https://youtu.be/8B6TxXcDt2E)
* [Full installation & setup guide](https://youtu.be/7HBBGPZfZL0)

= Features =

* **Classes** — Weekly recurring classes, one-off events, or external booking links.
* **Capacity** — Per class and date; counts paid bookings and active soft-holds.
* **Cancelled dates** — Block individual dates without disabling the class.
* **Bookings** — Pending, paid, and expired statuses with Stripe session references.
* **Reports** — Yearly charts, upcoming attendance, and guest lists.
* **Form extras** — Waiver, Mailchimp opt-in, and custom ACF fields.
* **Emails** — Customer and admin templates with merge tags via `wp_mail()`.
* **Result pages** — Booking Confirmed, Cancelled, and Error pages on activation.

= Stripe Checkout =

The plugin uses the bundled **stripe-php** SDK. It does not embed card fields on your site. Checkout Sessions use inline `price_data` (GBP), session metadata, and a 30-minute soft-hold aligned with session expiry. Configure webhooks for `checkout.session.completed`, `checkout.session.expired`, and `checkout.session.async_payment_failed`.

= Shortcodes =

* `[stripe_booking class_id="123"]`
* `[stripe_booking_status type="success"]` (or `cancelled`, `error`)
* Legacy aliases: `[yoga_booking]`, `[yoga_booking_status]`

= Requirements =

* WordPress 6.0+
* Stripe account (keys and webhook signing secret)
* ACF Free or Pro (bundled ACF Free loads if ACF is not active)
* Elementor (optional, for the Elementor widget)

= Video tutorials — full series =

Step-by-step guides on YouTube (IORoot):

* [Quick start: install, API keys & first booking](https://youtu.be/8B6TxXcDt2E)
* [Full installation & setup guide](https://youtu.be/7HBBGPZfZL0)
* [Stripe webhook setup](https://youtu.be/54MQBsW8qWA)
* [Result pages, Developer & Help tabs](https://youtu.be/8mMCkKxIH-s)
* [Creating a weekly repeating class](https://youtu.be/k5dlDzCyvoA)
* [One-off events & workshops](https://youtu.be/gzN3yzXWajo)
* [Extra fields & ACF on the booking form](https://youtu.be/BivPyMuCGbQ)
* [Email setup](https://youtu.be/dqg_DweIVAo)
* [Bookings list & reports](https://youtu.be/D2UpGlkhJWs)

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install via **Plugins → Add New**.
2. Activate **Class Bookings with Stripe**.
3. Open **Class Bookings with Stripe → Settings** in the admin menu.
4. Enter Stripe publishable and secret keys (test or live).
5. In Stripe Dashboard → **Developers → Webhooks**, add an endpoint: `https://yoursite.com/wp-json/stripe-bookings/v1/stripe-webhook` with events `checkout.session.completed`, `checkout.session.expired`, and `checkout.session.async_payment_failed`.
6. Paste the webhook signing secret into plugin settings.
7. Add classes under **Class Bookings with Stripe → Classes**.
8. Place `[stripe_booking class_id="123"]` on a page or add the **Class Booking with Stripe** Elementor widget.

== Frequently Asked Questions ==

= Does this plugin store credit card numbers? =

No. Card data is handled on Stripe Checkout. WordPress stores booking metadata only.

= Do I need WooCommerce? =

No.

= Do I need ACF Pro? =

No. ACF Free or Pro works; bundled ACF Free loads if ACF is not already active.

= Do I need Stripe Products? =

No. Checkout uses inline `price_data` from each class price.

= Which webhook events are required? =

`checkout.session.completed`, `checkout.session.expired`, and `checkout.session.async_payment_failed`.

= How long is a seat held? =

30 minutes, matching the Checkout Session expiry. Stale holds are cleared by webhook and cron.

= Which currency is supported? =

GBP (pence) by default.

= Does it work with Elementor? =

Yes. Use the **Class Booking with Stripe** widget or the shortcode.

== Screenshots ==

1. Booking form on the front end.
2. Class Bookings with Stripe settings.
3. Reports dashboard.

== Changelog ==

= 1.0.0 =

* Initial public release.
* Class and booking custom post types with ACF-driven fields.
* Stripe Checkout Sessions via bundled stripe-php SDK.
* REST checkout, signed webhooks, status polling, and availability API.
* Soft-holds, capacity limits, and cron expiry.
* Shortcodes, Elementor widget, result pages, and email templates.
* Reports, waiver, Mailchimp opt-in, and custom form fields.
* Bundled ACF Free when ACF is not active.

== Upgrade Notice ==

= 1.0.0 =

Initial release.
