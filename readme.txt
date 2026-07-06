=== CartBay — Abandoned Cart Recovery for WooCommerce ===
Contributors: wpanchorbay, forhadkhan
Tags: abandoned cart, cart recovery, woocommerce, boost sales, cart abandonment
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.2
WC requires at least: 10.7
WC tested up to: 10.7
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover WooCommerce abandoned carts with a focused 3-email sequence. Setup takes under 5 minutes.

== Description ==

CartBay captures guest checkout emails (with consent), detects abandoned carts, and sends a configured 3-step recovery email sequence through your existing WooCommerce email setup. Designed for performance and simplicity, CartBay uses WooCommerce-native storage to ensure your database remains clean and optimized.

**What CartBay does:**

* Captures consented email addresses on both classic and WooCommerce block checkouts
* Detects abandoned carts after a configurable timeout period
* Sends up to 3 recovery emails through native WooCommerce email (uses your existing SMTP)
* Lets you include a coupon code you created in WooCommerce in your recovery emails
* Rebuilds the shopper's cart via a secure restore link
* Detects completed orders and accurately marks sessions as recovered
* Shows a clear analytics overview: tracked, abandoned, and recovered carts, recovered revenue, and recovery rate
* Includes email delivery health checks (SMTP detection) and a built-in test-email tool
* Stores everything using WooCommerce-native order objects (HPOS compatible) — no custom database tables

**What CartBay does NOT do** (by design):

* No SMS or push notifications
* No visual drag-and-drop email builder
* No A/B testing
* No exit-intent popups

== Installation ==

1. Install CartBay from Plugins > Add New.
2. Activate the plugin.
3. Follow the setup wizard located at: WooCommerce > CartBay.
4. Configure your consent text, recovery timing, email delivery, and launch the campaign.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

Yes. CartBay requires WooCommerce 10.7 or higher.

= Does CartBay work with the Block Checkout? =

Yes. CartBay natively supports both classic checkout and the WooCommerce Block Checkout.

= Does CartBay work with WooCommerce Subscriptions? =

Yes. Capture and recovery work normally. CartBay Free does not auto-apply coupons; any coupon code you configure is included in the recovery email as plain text for the shopper to enter at checkout.

= How do coupons work? =

CartBay Free lets you reference a coupon you created in WooCommerce > Marketing > Coupons and include it in your recovery emails as plain text. Automatically generated, unique, single-use expiring coupons with auto-apply at checkout are part of CartBay Pro.

= Does CartBay use a separate database? =

No. CartBay strictly uses WooCommerce-native order storage and is fully compatible with WooCommerce HPOS (High-Performance Order Storage).

= How do I test CartBay before going live? =

Enable Test Mode in **WooCommerce > CartBay > Settings**. You can then trigger a shortened recovery flow (30-second delay) directly from the **Templates** section to verify your emails.

= Does CartBay contact external services? =

The free plugin does not contact WPAnchorBay licensing services. CartBay sends recovery emails through your WordPress/WooCommerce mail setup and sends capture requests only to your own site's local REST API endpoint at `/wp-json/cartbay/v1/capture`.

= Why didn't my recovery email send? =

CartBay hands recovery emails to WordPress and WooCommerce for delivery — it does not send email itself. If a send fails, the cause is almost always your site's mail configuration (no SMTP plugin, misconfigured SMTP credentials, or your host blocking PHP mail()), not CartBay. Use the built-in "Send Test Email" tool in the setup wizard or under WooCommerce > CartBay > Notifications to check your configuration, and see https://docs.wpanchorbay.com/cartbay/getting-started/email-delivery-setup/ for help setting up reliable email delivery.

= What personal data does CartBay store? =

When a shopper gives consent at checkout, CartBay stores the email address, cart snapshot, checkout source, timestamps, notification history, and recovery metadata in WooCommerce-native order records. This data stays on your WordPress site unless your mail provider processes outgoing recovery emails.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
