# Stripe Class Bookings

A small Stripe Checkout booking system for classes, built around ACF Pro.

## What it does

- One **Stripe Class** post per class type (location, day, start time, duration, price, capacity, description) — managed via ACF.
- A `[stripe_booking class_id="123"]` shortcode (and matching Elementor widget) renders a booking form: the next available dates (count set per class on the Stripe Class edit screen), a quantity picker (1–4, capped by remaining seats), name + email fields, and a "Book & pay with Stripe" button.
- Pressing the button creates a 30-minute soft-hold booking and a Stripe Checkout Session via the official `stripe-php` SDK with an inline product (`price_data`), then redirects the customer to Stripe.
- A signed Stripe webhook flips the booking to `paid`, captures the customer name/email Stripe collected, and fires a customer + admin email.
- Three result pages are auto-created on activation: **Booking Confirmed**, **Booking Cancelled**, **Booking Error** — driven by `[stripe_booking_status]`.

## Requirements

- WordPress 6.x
- ACF Pro (already active in this project)
- The official `stripe-php` SDK is bundled in `vendor/stripe/stripe-php/` — no Composer step needed.

## Setup

1. Activate **Stripe Class Bookings** in Plugins.
2. Visit **Stripe Classes → Settings** in the admin sidebar.
3. Paste your **Stripe test** publishable key and secret key.
4. In Stripe Dashboard → Developers → Webhooks, add an endpoint pointing at the URL shown on the settings page (`/wp-json/stripe-bookings/v1/stripe-webhook`) listening for:
   - `checkout.session.completed`
   - `checkout.session.expired`
   - `checkout.session.async_payment_failed`
5. Paste the webhook signing secret into the plugin settings.
6. Add classes under **Stripe Classes**.
7. Drop the **Stripe Class Booking** Elementor widget on a page (or use `[stripe_booking class_id="123"]`).

## Cancelling

- **Cancel one date**: open the class, add the date to the **Cancelled dates** repeater. The frontend skips it.
- **Pause a class entirely**: toggle **Class is active (bookable)** off.

## Capacity

For each (class, date), seats taken = `paid` bookings + currently-active soft-holds (pending bookings with `expires_at > now`). A 5-minute cron also expires stale holds; the Stripe webhook is the authoritative path.

## Files

```
includes/
  class-plugin.php           bootstrap singleton
  class-cpt.php              class + booking CPTs
  class-acf-fields.php       field groups + options page
  class-bookings.php         capacity, soft-holds, status transitions
  class-stripe-service.php   stripe-php wrapper
  class-rest.php             /checkout, /stripe-webhook, /booking-status, /availability
  class-shortcode.php        [stripe_booking] + [stripe_booking_status]
  class-elementor.php        widget registrar
  class-emails.php           merge tags + wp_mail
  class-result-pages.php     activation: ensure success/cancel/error pages exist
  helpers.php                date / money / option helpers
widgets/yoga-booking-widget.php
assets/yoga-booking.{css,js}
templates/{booking-form,booking-status,email-customer,email-admin}.php
vendor/stripe/stripe-php/    bundled SDK
```

## Email merge tags

`{customer_name} {customer_email} {class_name} {class_date} {class_time} {location} {duration} {price} {seats} {amount_total} {booking_id} {description}`
