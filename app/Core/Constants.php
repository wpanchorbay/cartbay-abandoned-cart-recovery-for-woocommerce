<?php
/**
 * Global constants registry.
 *
 * @package WPAnchorBay\CartBay\Core
 */

namespace WPAnchorBay\CartBay\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin-wide constants from one place.
 *
 * @since 1.0.0
 */
class Constants {
	/**
	 * Register all constants.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file Main plugin file path.
	 *
	 * @return void
	 */
	public static function register( string $plugin_file ): void {
		self::define( 'CARTBAY_VERSION', '1.1.0' );
		self::define( 'CARTBAY_SLUG', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		self::define( 'CARTBAY_DIR', plugin_dir_path( $plugin_file ) );
		self::define( 'CARTBAY_URL', plugin_dir_url( $plugin_file ) );
		self::define( 'CARTBAY_BASENAME', plugin_basename( $plugin_file ) );

		// External URLs.
		self::define( 'CARTBAY_PLUGIN_URL', 'https://wpanchorbay.com/plugins/cartbay/' );
		self::define( 'CARTBAY_AUTHOR_URL', 'https://wpanchorbay.com/' );
		self::define( 'CARTBAY_GET_PRO_URL', 'https://wpanchorbay.com/plugins/cartbay/' );
		self::define( 'CARTBAY_DOCS_URL', 'https://docs.wpanchorbay.com/cartbay' );
		self::define( 'CARTBAY_DOCS_EMAIL_SETUP_URL', 'https://docs.wpanchorbay.com/cartbay/getting-started/email-delivery-setup/' );

		// Internal URLs.
		self::define( 'CARTBAY_SETTINGS_URL', 'admin.php?page=wc-settings&tab=cartbay&section=settings' );
	}

	/**
	 * Define a constant if not already defined.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $cartbay_name  Constant name.
	 * @param string|int|bool|null $cartbay_value Constant value.
	 *
	 * @return void
	 */
	private static function define( string $cartbay_name, string|int|bool|null $cartbay_value ): void {
		if ( ! defined( $cartbay_name ) ) {
			define( $cartbay_name, $cartbay_value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound
		}
	}
}
