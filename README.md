## RiazAhmad Booking Deposits for Stripe

**Now live on WordPress.org:** https://wordpress.org/plugins/riazahmad-booking-deposits-for-stripe/

A WordPress plugin for appointment booking with a deposit paid through Stripe Checkout. Built from scratch in PHP, with no third-party booking plugin.

**Status:** version 1.0.0. Passes the official WordPress Plugin Check with no errors or warnings. Being prepared for the WordPress.org plugin directory.

## Features

- Booking form with a shortcode: `[bdfs_booking]`
- Settings page: Stripe keys, currency, fee, deposit percent, time slots, hold time
- Double-booking protection
- Deposit paid through Stripe Checkout (any Stripe currency, including zero-decimal ones like JPY)
- The payment is checked with the Stripe API before a booking is confirmed
- Stripe webhook with signature verification and replay protection
- Unpaid bookings hold the slot for a set time, then free it automatically
- Confirmation emails to the customer and the site owner, never sent twice
- Admin bookings page with status and a Cancel button
- Translation-ready, and uninstall removes all plugin data

## How it works

1. The customer fills in the form → the booking is saved as **pending**.
2. The customer pays the deposit on Stripe Checkout.
3. The booking becomes **confirmed** in two independent ways (whichever comes first):
   - the return page asks the Stripe API whether the session is paid
   - the Stripe webhook (`checkout.session.completed`) confirms it
4. The database update only changes unconfirmed rows, so emails are sent once.

## Files

- `booking-deposits-for-stripe.php`: main file, autoloader, database table
- `includes/Settings.php`: settings page and all settings
- `includes/BookingForm.php`: form, validation, Stripe Checkout
- `includes/Booking.php`: database rules (confirm, cancel, slot check)
- `includes/Webhook.php`: REST endpoint with Stripe signature verification
- `includes/StripeApi.php`, `includes/Money.php`, `includes/Emails.php`, `includes/AdminPage.php`, `includes/Log.php`
- `uninstall.php`: removes the table and settings
- `readme.txt`: WordPress.org readme

## Setup

1. Install and activate the plugin.
2. Go to **Bookings → Settings** and add your Stripe secret key (`sk_test_...` for testing).
3. In Stripe, add a webhook to the URL shown on the settings page, with the events `checkout.session.completed` and `checkout.session.expired`, and paste its signing secret (`whsec_...`).
4. Add `[bdfs_booking]` to any page.

## Tested with

WordPress 7.1 and 7.2-alpha, PHP 8.3, Stripe test mode, Stripe CLI. Automated tests cover booking, double booking, payment checks, webhook signatures, expiry and emails.

## Author

Riaz Ahmad, WordPress and WooCommerce developer

## License

GPL-2.0-or-later
