<?php
/**
 * CartBay
 *
 * @package           CartBay
 * @author            WPAnchorBay
 * @copyright         2026 WPAnchorBay
 *
 * Plugin Name:       CartBay - Abandoned Cart Recovery for WooCommerce
 * Plugin URI:        https://wpanchorbay.com/plugins/cartbay/
 * Description:       Recover abandoned WooCommerce checkout revenue with a focused plugin that sets up in minutes.
 * Version:           1.0.0
 * Stable tag:        1.0.0
 * Author:            WPAnchorBay
 * Author URI:        https://wpanchorbay.com/
 * Text Domain:       cartbay-abandoned-cart-recovery-for-woocommerce
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.6
 * Tested up to:      7.0
 * Requires PHP:      8.0
 * WC requires at least: 9.8
 * WC tested up to:   10.9
 * Requires Plugins:  woocommerce
 */

/**
 * Prevent Direct File Access
 * Abort if this file is called directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/app/Core/Constants.php';
\WPAnchorBay\CartBay\Core\Constants::register( __FILE__ );

if ( file_exists( CARTBAY_DIR . 'vendor/autoload.php' ) ) {
	require_once CARTBAY_DIR . 'vendor/autoload.php';
}

/**
 * Initialize the plugin after all plugins are loaded.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_init(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cartbay_woocommerce_missing_notice' );
		return;
	}

	if ( class_exists( '\WPAnchorBay\CartBay\Core\Plugin' ) ) {
		\WPAnchorBay\CartBay\Core\Plugin::instance()->init();
	}
}
add_action( 'plugins_loaded', 'cartbay_init' );

/**
 * Show admin notice when WooCommerce is not active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_woocommerce_missing_notice(): void {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'CartBay requires WooCommerce to be installed and active.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) .
		'</p></div>';
}

if ( class_exists( '\WPAnchorBay\CartBay\Core\Installer' ) ) {
	register_activation_hook( __FILE__, array( \WPAnchorBay\CartBay\Core\Installer::class, 'activate' ) );
	register_deactivation_hook( __FILE__, array( \WPAnchorBay\CartBay\Core\Installer::class, 'deactivate' ) );
}
