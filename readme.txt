=== CartBay - Abandoned Cart Recovery for WooCommerce ===
Contributors: wpanchorbay, forhadkhan, sankarsan, arifac, shuvendushekhar
Tags: abandoned cart, cart recovery, cart abandonment, boost sales, email reminder
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.0
WC requires at least: 9.8
WC tested up to: 10.9
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover WooCommerce abandoned carts with a focused, consent-based 3-email recovery sequence. No custom tables. Set up in minutes.

== Description ==

[Product Page](https://wpanchorbay.com/plugins/cartbay/) | [Documentation](https://docs.wpanchorbay.com/cartbay/) | [Support](https://wordpress.org/support/plugin/cartbay-abandoned-cart-recovery-for-woocommerce/)

Every abandoned cart is a shopper who already wanted to buy. **CartBay** brings them back with a simple, focused abandoned cart recovery workflow for WooCommerce: it captures consented checkout emails, detects when a cart goes quiet, and sends a configured 3-step recovery email sequence through your existing WooCommerce email setup.

CartBay is built for performance and privacy. It stores everything in **WooCommerce-native order objects** - no custom database tables to bloat your site, and full compatibility with WooCommerce **HPOS** (High-Performance Order Storage). Setup takes under 5 minutes with the built-in wizard, and the whole plugin is open source with un-minified, human-readable code included in the package.

[youtube https://www.youtube.com/watch?v=XnECWpfgf8U]

= How CartBay works =

1. A shopper reaches checkout and sees a clear, consent-based recovery checkbox (classic **and** WooCommerce Block Checkout).
2. When they consent and a valid email is available, CartBay captures the cart as a recovery session.
3. After your configurable inactivity timeout, the session is marked abandoned.
4. CartBay schedules up to **3 recovery emails** with delays you control, sent through WooCommerce's native email system.
5. Each email can include a secure restore link that rebuilds the shopper's exact cart, an unsubscribe link, and an optional coupon code.
6. When the shopper completes checkout, CartBay matches the order back to the abandoned session, attributes the recovered revenue, and cancels any remaining emails.

= Key features =

* **Consent-first email capture** on both classic checkout and the WooCommerce Block Checkout
* **Abandonment detection** via Action Scheduler with a configurable timeout
* **3-step recovery email sequence** with per-step delays, sent through your existing WooCommerce/WordPress mail (your SMTP)
* **Fully editable emails** - customize each email's subject, heading, preheader, body, button text, and additional content in the native WooCommerce email editor, using your store's WooCommerce logo, colors, and footer, with live preview and test send
* **Secure cart restore** - a hashed-token restore link rebuilds the exact cart, including variable product attributes
* **One-click unsubscribe** backed by a hashed suppression list, so opted-out shoppers are never emailed again
* **Coupon in recovery emails** - reference a coupon you created in WooCommerce and include it in your emails as plain text
* **Accurate recovery tracking** - completed orders are matched back to abandoned sessions with recovered-revenue attribution
* **Analytics overview** - tracked, abandoned, and recovered carts, abandoned cart value, recovered revenue, and recovery rate, with 7/30/90-day filters
* **Session list** - filter recovery sessions by status and inspect individual captured carts and their recovery progress
* **Setup wizard** for guided first-run configuration in a few minutes
* **Email delivery health checks** (SMTP detection) plus a built-in **Send Test Email** tool
* **Test Mode** with a one-click Trigger Test Flow that schedules a sample recovery email about 30 seconds out, so you can preview an email and its restore link before going live
* **Privacy-conscious storage** - data stays on your site in WooCommerce-native records; no custom tables, HPOS compatible

= What CartBay does NOT do (by design) =

CartBay intentionally stays focused on doing one job well. It does **not** include:

* SMS or push notifications
* A/B testing
* Exit-intent popups

= Upgrade to CartBay Pro =

Everything above is included in the free plugin. **CartBay Pro** is an optional licensed add-on that installs on top of CartBay and adds:

* **Dynamic recovery coupons** - a unique, single-use, expiring coupon generated for each cart session, instead of one static code
* **Auto-apply on restore** - each coupon is restricted to the shopper's own email and applied automatically when they return via the restore link (subscription carts excluded)
* **Advanced recovery analytics** - restore-link clicks, click-to-recovery rate, failed restores, and per-step email performance, plus CSV export of sessions and recovery emails
* **Licensed automatic updates** delivered outside WordPress.org and tied to your license key
* **License management** in Settings and the setup wizard, plus a REST API for programmatic activation

*On the roadmap for Pro:* a visual drag-and-drop email builder, and more. Learn more at https://wpanchorbay.com/plugins/cartbay/.

= Open source and privacy-friendly =

CartBay is fully GPL, contains no obfuscated code, and ships its JavaScript source in the package. The free plugin does **not** phone home to any WPAnchorBay licensing service and makes no external API calls - capture requests go only to your own site's local REST endpoint. See the FAQ below for exactly what data is stored and where.

== Installation ==

**Prerequisites:**

* [WordPress](https://wordpress.org/) 6.6 or higher
* [WooCommerce](https://wordpress.org/plugins/woocommerce/) 9.8 or higher, installed and active
* PHP 8.0 or higher

1. Install CartBay from **Plugins > Add New**, or upload the plugin ZIP under **Plugins > Add New > Upload Plugin**.
2. Activate the plugin.
3. Open the setup wizard at **WooCommerce > CartBay** and follow the guided steps.
4. Configure your consent text, abandonment timing, recovery emails, and email delivery, then launch the campaign.
5. Before going live, use **Send Test Email** under **WooCommerce > CartBay > Notifications** to confirm your site can deliver mail.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

Yes. CartBay requires WooCommerce 9.8 or higher and WordPress 6.6 or higher.

= Does CartBay work with the Block Checkout? =

Yes. CartBay natively supports both the classic checkout and the WooCommerce Block Checkout. On Block Checkout the consent field appears in the contact section as a WooCommerce additional checkout field.

= Where do I edit the recovery emails? =

Open **WooCommerce > CartBay > Templates** and click **Edit in WooCommerce** for the email you want to change. Each recovery email is a native WooCommerce email, so you can edit its subject, heading, preheader, body, button text, and additional content, and it automatically uses your store's WooCommerce email logo, colors, and footer - with the same preview and test-send tools as your other WooCommerce emails. Supported placeholders include `{restore_url}`, `{coupon_code}`, `{coupon_expiry}`, and `{unsubscribe_url}`.

= Does CartBay use a separate database? =

No. CartBay strictly uses WooCommerce-native order storage and is fully compatible with WooCommerce HPOS (High-Performance Order Storage). There are no custom database tables.

= How do coupons work? =

CartBay Free lets you reference a coupon you created in **WooCommerce > Marketing > Coupons** and include it in your recovery emails as plain text for the shopper to enter at checkout. Automatically generated, unique, single-use expiring coupons that auto-apply at checkout are part of CartBay Pro.

= Does CartBay work with WooCommerce Subscriptions? =

Yes. Capture and recovery work normally. CartBay Free does not auto-apply coupons; any coupon code you configure is included in the recovery email as plain text for the shopper to enter at checkout.

= How do I test CartBay before going live? =

Enable **Test Mode** in **WooCommerce > CartBay > Settings**, then use **Trigger Test Flow** in the **Templates** section to schedule a sample recovery email about 30 seconds out and preview the email and its restore link. Use Test Mode on staging or during controlled QA, and disable it for normal production monitoring.

= Does CartBay contact external services? =

No. The free plugin does not contact WPAnchorBay licensing services or any third-party API. CartBay sends recovery emails through your WordPress/WooCommerce mail setup and sends capture requests only to your own site's local REST API endpoint at `/wp-json/cartbay/v1/capture`.

= Why didn't my recovery email send? =

CartBay hands recovery emails to WordPress and WooCommerce for delivery - it does not send email itself. If a send fails, the cause is almost always your site's mail configuration (no SMTP plugin, misconfigured SMTP credentials, or your host blocking PHP `mail()`), not CartBay. Use the built-in **Send Test Email** tool in the setup wizard or under **WooCommerce > CartBay > Notifications** to check your configuration, and see https://docs.wpanchorbay.com/cartbay/getting-started/email-delivery-setup/ for help setting up reliable delivery (SMTP/transactional email and SPF, DKIM, and DMARC records).

= What does "Sent" mean in Notifications? =

`Sent` means WordPress/WooCommerce accepted the email for delivery. It does not by itself guarantee inbox delivery - that still depends on your SMTP configuration, domain authentication, and the receiving mailbox.

= What personal data does CartBay store? =

When a shopper gives consent at checkout, CartBay stores the email address, a cart snapshot, the checkout source, timestamps, notification history, and recovery metadata in WooCommerce-native order records. Restore and unsubscribe tokens are stored only as SHA-256 hashes, never in raw form. This data stays on your WordPress site unless your mail provider processes outgoing recovery emails. You can enable **Delete Data on Uninstall** in Settings to remove CartBay data when the plugin is deleted.

= Where can I get support? =

Documentation is at https://docs.wpanchorbay.com/cartbay/. For questions, please use the plugin's support forum on WordPress.org.

== Screenshots ==

1. CartBay respects each shopper's choice about whether to allow their cart to be saved.
2. CartBay automatically sends recovery emails to shoppers who abandon their carts before completing a purchase.
3. Recovery analytics overview including tracked, abandoned, and recovered carts, abandoned cart value, recovered revenue, and recovery rate, with 7/30/90-day filters.
4. Configure consent-based cart capture for both Classic Checkout and WooCommerce Block Checkout.
5. Set up a three-step cart recovery email sequence with customizable delays between each email.
6. Customize recovery email templates using WooCommerce's native email settings.
7. Get started quickly with a guided setup wizard for fast, first-time configuration.

== Source Code ==

CartBay is fully open source under GPL-2.0-or-later and contains no obfuscated code. The plugin's JavaScript is written in an un-minified, human-readable form and is **included in this package** under the `src/` directory:

* `src/capture/index.js` - classic checkout capture (compiled to `assets/js/cartbay-capture.js`).
* `src/block/index.js` - block checkout capture (compiled to `assets/js/cartbay-block.js`).

The compiled files in `assets/js/` are generated from these sources with the official WordPress build tooling (@wordpress/scripts, which uses webpack). The build tooling and configuration are also included in this package as `package.json` and `webpack.config.js`.

To regenerate the compiled assets from source, run these commands in the plugin directory:

`npm install`
`npm run build`

== Changelog ==

= 1.1.0 =
* Recovery emails no longer include a coupon by default. Turn a coupon on per email under **WooCommerce > CartBay > Recovery Sequence** when you want one. Existing setups are preserved; only untouched default configurations move to the new recommended default.
* Added coupon setup checks on the **Offers** screen. CartBay now warns when an email is set to include a coupon but no code is entered, and when the configured code has no matching WooCommerce coupon, has expired, has reached its usage limit, or is restricted to specific email addresses.
* Added a developer filter, `cartbay_offers_coupon_notice_relevant`, so extensions can control the new Offers notices.
* Fixed recovery emails showing an empty "Expires ." line when the coupon had no expiry date.

= 1.0.1 =
* Lowered minimum requirements to PHP 8.0, WordPress 6.6, and WooCommerce 9.8.
* Fixed a fatal error when Action Scheduler functions were unavailable.
* Removed registration of a nonexistent order-status filter.
* Improved translation coverage for admin strings.
* Updated compatibility: tested up to WordPress 7.0 and WooCommerce 10.9.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
New coupon setup checks on the Offers screen, and recovery emails no longer include a coupon by default. Existing configurations are preserved.

= 1.0.1 =
Reliability and compatibility fixes, plus lower minimum requirements (PHP 8.0, WordPress 6.6, WooCommerce 9.8).

= 1.0.0 =
Initial release of CartBay - abandoned cart recovery for WooCommerce.
