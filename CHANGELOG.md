# Changelog

All notable changes to CartBay are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
Versioning: [Semantic Versioning](https://semver.org/)

## [1.0.0] — 2026-06-30

Initial public release on WordPress.org.

### Added
- Guest and logged-in checkout email capture with explicit consent (classic checkout)
- Block Checkout consent field via `woocommerce_register_additional_checkout_field()`
- Cart abandonment detection via Action Scheduler (configurable timeout)
- 3-step recovery email sequence with configurable delays (WC_Email-based)
- Optional static coupon code included in recovery emails
- Secure restore link with a 64-character hashed token and 48-hour TTL
- Cart rebuild from the stored session on restore-link click (including variable product attributes)
- One-click unsubscribe with a hashed suppression list
- Recovery detection via order hooks, with recovered-revenue attribution
- Analytics overview: tracked, abandoned, and recovered carts, recovered revenue, and recovery rate (7/30/90-day filter)
- Session list with status filters and a per-session event log
- Setup wizard for guided configuration
- Test mode with accelerated delivery for previewing the recovery flow
- Email delivery health checks (SMTP detection) and a built-in test-email tool
- Rate-limited public capture and restore endpoints
- HPOS (High-Performance Order Storage) compatibility declared
- PSR-4 autoloading with no custom database tables (WooCommerce-native order storage)
- Full WordPress Coding Standards compliance; i18n-ready (`cartbay` text domain)
