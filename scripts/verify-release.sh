#!/usr/bin/env bash
set -e

ZIP="cartbay-abandoned-cart-recovery-for-woocommerce.zip"
TMP="/tmp/cartbay-release-verify"

echo "Verifying release ZIP: $ZIP"

if [ ! -f "$ZIP" ]; then
    echo "❌ ZIP not found: $ZIP"
    exit 1
fi

rm -rf "$TMP" && mkdir -p "$TMP"
unzip -q "$ZIP" -d "$TMP"

PLUGIN="$TMP/cartbay-abandoned-cart-recovery-for-woocommerce"

# Required files — representative sample from each ship directory.
REQUIRED=(
    "cartbay-abandoned-cart-recovery-for-woocommerce.php"
    "uninstall.php"
    "readme.txt"
    "LICENSE.txt"
    "app/Core/Plugin.php"
    "app/Admin/Wizard/WizardController.php"
    "app/Recovery/CaptureService.php"
    "app/Data/SessionRepository.php"
    "app/Analytics/AnalyticsService.php"
    "app/Utils/Logger.php"
    "app/Email/AbstractCartBayRecoveryEmail.php"
    "assets/js/cartbay-capture.js"
    "assets/js/cartbay-capture.asset.php"
    "assets/js/cartbay-block.js"
    "assets/js/cartbay-block.asset.php"
    "templates/emails/recovery-email-1.php"
    "vendor/autoload.php"
    "languages/cartbay-abandoned-cart-recovery-for-woocommerce.pot"
)

for f in "${REQUIRED[@]}"; do
    if [ ! -e "$PLUGIN/$f" ]; then
        echo "❌ MISSING required file: $f"
        exit 1
    fi
    echo "✅ Found: $f"
done

# Forbidden files/dirs.
FORBIDDEN=(
    "src"
    "node_modules"
    "AGENTS.md"
    "GEMINI.md"
    "tasks"
    "phpcs.xml"
    "phpstan.neon"
    "phpunit.xml"
    "webpack.config.js"
    "package.json"
    "scripts"
    ".git"
    ".env"
    ".history"
    ".agents"
    ".codex"
    ".kilo"
    ".gemini"
    ".playwright"
    ".github"
    ".graphifyignore"
)

for f in "${FORBIDDEN[@]}"; do
    if [ -e "$PLUGIN/$f" ]; then
        echo "❌ FORBIDDEN file/dir present: $f"
        exit 1
    fi
    echo "✅ Absent (correct): $f"
done

# Check plugin header version matches CARTBAY_VERSION constant.
HEADER_VERSION=$(grep "Version:" "$PLUGIN/cartbay-abandoned-cart-recovery-for-woocommerce.php" | head -1 | awk '{print $NF}' | tr -d '[:space:]')
CONST_VERSION=$(grep "define.*CARTBAY_VERSION" "$PLUGIN/app/Core/Constants.php" | grep -o "'[0-9.]*'" | tr -d "'")

if [ "$HEADER_VERSION" != "$CONST_VERSION" ]; then
    echo "❌ Version mismatch: header=$HEADER_VERSION constant=$CONST_VERSION"
    exit 1
fi
echo "✅ Version consistent: $HEADER_VERSION"

rm -rf "$TMP"
echo ""
echo "✅ Release verification passed."
