# CartBay

Abandoned Cart Recovery for WooCommerce — free host plugin that recovers lost checkout revenue via a 3-email sequence. CartBay Pro is a separate paid add-on.

## Requirements

- PHP 8.3+
- WordPress 6.9+
- WooCommerce 10.7+
- A SMTP plugin (configured) for sending emails
- Composer (for PHP deps)
- Bun (for JS builds)

## Setup

```bash
composer install
bun install
```

## Build

```bash
bun run build           # compile src/ → assets/
bun run i18n:make-pot   # update languages/cartbay.pot
```

## Code Quality

```bash
composer phpcs          # PHPCS (zero violations)
composer phpstan        # PHPStan level 5 (zero errors)
```

## Project Structure

```
app/Admin/Pages/        — admin page controllers
app/Admin/Wizard/       — setup wizard steps
app/Api/Routes/         — REST route classes
app/Core/               — bootstrap, container, installer
app/Data/               — SessionRepository
app/Email/              — WC_Email subclasses (3 recovery emails)
app/Recovery/           — capture, scheduling, email sequence, coupons, restore, matcher
app/Analytics/          — analytics service
app/Utils/              — TokenHelper, RateLimiter, Logger
src/                    — React/TS source (dashboard, capture, block)
templates/emails/       — WC email HTML templates
tests/                  — PHPUnit tests
```

## Architecture

- **Storage:** WooCommerce-native order objects as sessions. Compatible with HPOS. No custom tables.
- **Background jobs:** Action Scheduler for abandonment detection and email scheduling.
- **Capture:** REST endpoint (`cartbay/v1/capture`) — shared by classic and block checkout.
- **Free/Pro:** This free plugin owns the host recovery engine and extension points. CartBay Pro owns licensing and private updates.
- **i18n:** Text domain `cartbay`. Run `bun run i18n:make-pot` after adding translatable strings.

See [AGENTS.md](AGENTS.md) for detailed architecture constants and coding conventions.
