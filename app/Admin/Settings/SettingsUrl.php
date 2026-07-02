<?php
/**
 * CartBay settings URL helpers.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and inspects CartBay WooCommerce settings URLs.
 *
 * @since 1.0.0
 */
class SettingsUrl {
	/**
	 * Build a CartBay settings section URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section Section identifier.
	 *
	 * @return string Admin URL.
	 */
	public function section( string $section ): string {
		return admin_url( 'admin.php?page=wc-settings&tab=cartbay&section=' . sanitize_key( $section ) );
	}

	/**
	 * Determine whether the current request is CartBay's WooCommerce settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether this is the CartBay settings tab.
	 */
	public function is_wc_settings_cartbay_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 'wc-settings' === $page && 'cartbay' === $tab;
	}

	/**
	 * Redirect to the Settings section with a WooCommerce notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice  Notice type.
	 * @param string $message Notice message.
	 *
	 * @return void
	 */
	public function redirect_with_notice( string $notice, string $message ): void {
		$query_arg = 'error' === $notice ? 'wc_error' : 'wc_message';
		$url       = add_query_arg(
			array(
				'page'     => 'wc-settings',
				'tab'      => 'cartbay',
				'section'  => 'settings',
				$query_arg => sanitize_text_field( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
