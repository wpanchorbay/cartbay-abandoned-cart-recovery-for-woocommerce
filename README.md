<div align="center">

# CartBay — Abandoned Cart Recovery for WooCommerce

**Recover lost WooCommerce checkout revenue with a focused 3-email recovery sequence. Sets up in under 5 minutes.**

[![Docs](https://img.shields.io/badge/Docs-Live-2ea44f?style=for-the-badge&logo=readthedocs&logoColor=white)](https://docs.wpanchorbay.com/cartbay/)

---

![Version](https://img.shields.io/badge/version-1.0.0-blue?style=flat-square)
![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-21759b?style=flat-square&logo=wordpress&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-10.7%2B-96588a?style=flat-square&logo=woocommerce&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL--2.0%2B-orange?style=flat-square)

</div>

---

Every abandoned checkout is revenue that almost happened. **CartBay** quietly captures consented shopper emails at checkout, notices when a cart is left behind, and follows up with a short, timed email sequence that brings shoppers back to finish their order — all through the email setup you already use.

No new database tables, no bloat, no complicated funnels. Install it, run the 5-minute wizard, and let it work.

## What CartBay does

- **Captures emails with consent** on both classic and WooCommerce Block checkouts
- **Detects abandoned carts** after a timeout you choose
- **Sends up to 3 recovery emails** through native WooCommerce email (your existing SMTP)
- **Includes a coupon** you created in WooCommerce, right in the recovery email
- **Rebuilds the shopper's cart** with a secure one-click restore link
- **Marks carts as recovered** automatically when the order completes
- **Shows a clear analytics overview** — tracked, abandoned, and recovered carts, recovered revenue, and recovery rate
- **Checks email health** with SMTP detection and a built-in test-email tool
- **Stores everything in WooCommerce-native orders** (HPOS compatible) — no custom tables

### What CartBay doesn't do (by design)

Kept lean on purpose — no SMS or push notifications, no drag-and-drop email builder, no A/B testing, and no exit-intent popups.

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.9+ |
| WooCommerce | 10.7+ |
| PHP | 8.2+ |
| Email | A configured SMTP plugin for reliable delivery |

## Getting started

1. Install **CartBay** from **Plugins → Add New** (or upload the plugin ZIP).
2. Activate the plugin.
3. Open the setup wizard at **WooCommerce → CartBay**.
4. Set your consent text, recovery timing, and email delivery — then launch your campaign.

That's it — CartBay starts capturing carts and sending recovery emails right away.

> **Want to test first?** Enable **Test Mode** in **WooCommerce → CartBay → Settings**, then trigger a shortened 30-second recovery flow from the **Templates** section to preview your emails before going live.

## Privacy & your data

CartBay keeps your store's data on your store. When a shopper consents at checkout, CartBay stores their email, a cart snapshot, checkout source, timestamps, and recovery history inside WooCommerce-native order records. The free plugin does **not** contact WPAnchorBay licensing services — capture requests go only to your own site's REST endpoint (`/wp-json/cartbay/v1/capture`), and recovery emails send through your own WordPress/WooCommerce mail setup.

## Frequently asked questions

**Do I need WooCommerce?**
Yes — CartBay requires WooCommerce 10.7 or higher.

**Does it work with the Block Checkout?**
Yes, both classic and WooCommerce Block checkouts are supported natively.

**Does it work with WooCommerce Subscriptions?**
Yes. Capture and recovery work normally. Any coupon you configure is included in the email as plain text for the shopper to enter at checkout.

**How do coupons work?**
CartBay Free lets you reference a coupon you created in **WooCommerce → Marketing → Coupons** and include it in recovery emails as plain text. Automatically generated, unique, single-use expiring coupons that auto-apply at checkout are part of CartBay Pro.

**Does CartBay use a separate database?**
No. It uses WooCommerce-native order storage and is fully compatible with HPOS (High-Performance Order Storage).


## Support

Found a bug or have a suggestion? Please open an issue on the [GitHub Issue Tracker](https://github.com/wpanchorbay/cartbay-abandoned-cart-recovery-for-woocommerce/issues) and include:

1. A clear description of the problem
2. Steps to reproduce it
3. Screenshots or error logs, if available

Full documentation: **[docs.wpanchorbay.com/cartbay](https://docs.wpanchorbay.com/cartbay/)**

## License

CartBay is free software, licensed under the [GPL v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

**Copyright © WPAnchorBay** — [wpanchorbay.com](https://wpanchorbay.com/)

---

<details>
<summary><strong>For developers</strong> — build, test &amp; project layout</summary>

<br/>

**Prerequisites:** PHP 8.2+, Composer, and [Bun](https://bun.sh) for JS builds.

### Setup

```bash
composer install
bun install
```

### Build

```bash
bun run build           # compile src/ → assets/
bun run i18n:make-pot   # update languages/cartbay.pot
```

### Code quality

```bash
composer phpcs          # PHPCS — zero violations
composer phpstan        # PHPStan level 5 — zero errors
```

### Project structure

```
app/Admin/Pages/    — admin page controllers
app/Admin/Wizard/   — setup wizard steps
app/Api/Routes/     — REST route classes
app/Core/           — bootstrap, container, installer
app/Data/           — SessionRepository
app/Email/          — WC_Email subclasses (3 recovery emails)
app/Recovery/       — capture, scheduling, email sequence, coupons, restore, matcher
app/Analytics/      — analytics service
app/Utils/          — TokenHelper, RateLimiter, Logger
src/                — React/TS source (dashboard, capture, block)
templates/emails/   — WC email HTML templates
tests/              — PHPUnit tests
```

### Architecture notes

- **Storage:** WooCommerce-native order objects as sessions (HPOS compatible) — no custom tables.
- **Background jobs:** Action Scheduler for abandonment detection and email scheduling.
- **Capture:** REST endpoint `cartbay/v1/capture`, shared by classic and block checkout.
- **Free/Pro:** This free plugin owns the host recovery engine and extension points; CartBay Pro owns licensing and private updates.
- **i18n:** Text domain `cartbay`; run `bun run i18n:make-pot` after adding translatable strings.

</details>
