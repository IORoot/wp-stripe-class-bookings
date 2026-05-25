# Developer notes — Class Bookings with Stripe

WordPress.org listing copy lives in `readme.txt` (required). Submission notes: `docs/wordpress-details.md`.

## Local setup

1. Activate the plugin.
2. **Class Bookings with Stripe → Settings** — Stripe keys and webhook secret.
3. Webhook URL: `/wp-json/stripe-bookings/v1/stripe-webhook`
4. Add classes, embed `[stripe_booking class_id="123"]` or the Elementor widget.

## Capacity

Seats taken = `paid` + pending holds where `expires_at > now`. Five-minute cron expires stale holds; webhooks are authoritative for paid/expired sessions.

## Email merge tags

`{customer_name} {customer_email} {class_name} {class_date} {class_time} {location} {duration} {price} {seats} {amount_total} {booking_id} {description}`

## Key files

```
includes/class-plugin.php
includes/class-rest.php
includes/class-stripe-service.php
includes/class-bookings.php
templates/booking-form.php
vendor/stripe/stripe-php/
```
