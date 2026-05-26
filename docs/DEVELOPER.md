# Developer notes — Class Bookings with Stripe

WordPress.org listing copy lives in `readme.txt` (required). Submission notes: `docs/wordpress-details.md`.

## Install locally (Docker, no zip upload)

This repo mounts the plugin directly into WordPress:

```text
./class-bookings-with-stripe → wp-content/plugins/class-bookings-with-stripe
```

Restart Docker after `docker-compose.yml` changes, then activate **Class Bookings with Stripe** under Plugins.

## Build a zip for Upload Plugin

From the **repository root** (not from inside the plugin folder):

```bash
./bin/build-plugin-zip.sh
```

This creates `class-bookings-with-stripe/class-bookings-with-stripe.zip` with the correct layout:

```text
class-bookings-with-stripe.zip
└── class-bookings-with-stripe/
    ├── ioroot-stripe-bookings.php
    └── …
```

The script **excludes** `.git`, `docs/`, and `*.zip`. Do not zip from inside the plugin directory (`zip -r . …`) — WordPress will reject or mis-install the plugin.

If upload still says **“The link you followed has expired”**, the zip is usually too large for PHP limits. This project’s `docker-compose.yml` loads `docker/php-uploads.ini` (128M upload). Restart containers: `docker compose up -d`.

## Local setup

1. Activate the plugin.
2. **Stripe Class → Settings** — Stripe keys and webhook secret.
3. Webhook URL: `/wp-json/stripe-bookings/v1/stripe-webhook`
4. Add classes, embed `[stripe_booking class_id="123"]` or the Elementor widget.

## Email merge tags

`{customer_name} {customer_email} {class_name} {class_date} {class_time} {location} {duration} {price} {seats} {amount_total} {booking_id} {description}`
