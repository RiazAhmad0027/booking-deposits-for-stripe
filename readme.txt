=== Booking Deposits for Stripe ===
Contributors: riazahmad2211
Tags: booking, appointments, stripe, deposit, scheduling
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple appointment booking with a deposit paid through Stripe Checkout. Time slots, double-booking protection and confirmation emails.

== Description ==

Booking Deposits for Stripe adds a small booking form to any page. Customers choose a date and a time slot, then pay a deposit through Stripe Checkout. The booking is confirmed only after Stripe says the payment is real.

It is made for small businesses that take appointments: clinics, salons, tutors, consultants, photographers and repair shops.

**Features**

* Booking form with a shortcode: `[bdfs_booking]`
* Your own time slots, fee, deposit percent and currency
* Protection against double booking
* Deposit paid through Stripe Checkout (cards, and other methods you turn on in Stripe)
* Bookings are confirmed in two safe ways: the return page checks the payment with the Stripe API, and a Stripe webhook (with signature check) confirms it even if the customer closes the browser
* Unpaid bookings hold the slot for a short time, then free it automatically
* Confirmation email to the customer and to the site owner, never sent twice
* Bookings page in WP Admin with status and a Cancel button
* Works in Stripe test mode, so you can try everything before taking real payments

== External services ==

This plugin connects to the Stripe API (https://api.stripe.com) to take deposit payments. Stripe is a payment service by Stripe, Inc.

* When a customer submits the booking form, the plugin sends Stripe: the customer's email address, the deposit amount and currency, the booking date and time (in the product name), the booking number, and the return addresses of your site. Stripe then shows its payment page to the customer.
* When the customer comes back, the plugin asks Stripe if that payment session is paid.
* Stripe sends webhook events (payment completed, payment page expired) to your site at `/wp-json/booking-deposits-for-stripe/v1/stripe-webhook`.

No data is sent to Stripe until you add your own Stripe secret key in the settings.

Stripe terms: https://stripe.com/legal/ssa
Stripe privacy policy: https://stripe.com/privacy

== Installation ==

1. Install and activate the plugin.
2. Go to **Bookings → Settings**.
3. Add your Stripe secret key (Stripe Dashboard → Developers → API keys). Start with a test key (`sk_test_...`).
4. Set your currency, fee, deposit percent and time slots, then click **Save Changes**.
5. In Stripe, add a webhook endpoint with the URL shown on the settings page, for the events `checkout.session.completed` and `checkout.session.expired`. Copy its signing secret (`whsec_...`) into the settings.
6. Add the shortcode `[bdfs_booking]` to any page.

You can also set the keys in `wp-config.php` instead of the settings page:

`define( 'BDFS_STRIPE_SECRET_KEY', 'sk_test_...' );`
`define( 'BDFS_STRIPE_WEBHOOK_SECRET', 'whsec_...' );`

== Frequently Asked Questions ==

= Do I need a Stripe account? =

Yes. A Stripe account is free to create. Stripe charges a fee for each payment.

= Can I test it without real money? =

Yes. Use your Stripe test key and the test card 4242 4242 4242 4242 with any future date and any CVC.

= Does cancelling a booking refund the deposit? =

No. Cancelling in WP Admin only frees the time slot. Make refunds in your Stripe Dashboard.

= Can I test webhooks on my local computer? =

Yes, with the Stripe CLI: `stripe listen --forward-to http://your-site.local/wp-json/booking-deposits-for-stripe/v1/stripe-webhook`. Use the signing secret it shows.

= Which currencies work? =

Any currency your Stripe account supports. Currencies without decimals (like JPY) are handled correctly.

== Screenshots ==

1. Bookings page in WP Admin.
2. The booking form.
3. Settings page.

== Changelog ==

= 1.0.0 =
* First release.
