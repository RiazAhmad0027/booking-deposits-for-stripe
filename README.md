# Clinic Booking

A custom WordPress plugin for appointment booking with a deposit payment through Stripe. Built from scratch in PHP, with no third-party booking plugin.

**Status:** working in Stripe test mode. A learning and portfolio project.

## Features

- Booking form with a shortcode: `[clinic_booking]`
- Fixed time slots, with protection against double booking
- 20% deposit paid through Stripe Checkout (fee and deposit are easy to change)
- The payment is checked with Stripe before a booking is confirmed (the plugin never trusts the URL alone)
- Stripe webhook with signature verification: bookings are confirmed even if the customer closes the browser
- Unpaid bookings hold the slot for 35 minutes, then free it automatically (expired Stripe sessions are cancelled)
- Confirmation emails to the customer and the site owner, with no double emails
- Admin page (WP Admin → Bookings) with status colors and a Cancel button
- Security: nonces, sanitizing and validating input, escaping output, prepared SQL queries, capability checks

## How it works

1. The customer fills in the form → the booking is saved as **pending**.
2. The customer pays the deposit on Stripe Checkout.
3. The booking becomes **confirmed** in two independent ways (whichever comes first):
   - the return page asks the Stripe API whether the session is paid
   - the Stripe webhook (`checkout.session.completed`) confirms it
4. A database update that only changes unconfirmed rows makes sure emails are sent once.

## Files

- `clinic-booking.php`: main file, autoloader, database table on activation
- `includes/BookingForm.php`: form, validation, double-booking check, Stripe Checkout
- `includes/Booking.php`: shared database rules (confirm, cancel, find)
- `includes/StripeApi.php`: small Stripe API client
- `includes/Webhook.php`: REST endpoint with Stripe signature verification
- `includes/Emails.php`: confirmation emails
- `includes/AdminPage.php`: admin bookings table

## Setup

1. Upload the plugin to `wp-content/plugins/` and activate it.
2. Add your keys to `wp-config.php` (never put keys in the plugin code):

   ```php
   define( 'CB_STRIPE_SECRET_KEY', 'sk_test_...' );
   define( 'CB_STRIPE_WEBHOOK_SECRET', 'whsec_...' );
   ```

3. In Stripe, add a webhook pointing to
   `https://yoursite.com/wp-json/clinic-booking/v1/stripe-webhook`
   with the events `checkout.session.completed` and `checkout.session.expired`.
4. Create a page and add the shortcode `[clinic_booking]`.

## Tested with

WordPress 7.1, PHP 8.3, Stripe test mode (PKR), Stripe CLI for local webhooks.

## Author

Riaz Ahmad, WordPress and WooCommerce developer

## License

GPL-2.0-or-later
