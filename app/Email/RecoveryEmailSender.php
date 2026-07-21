<?php
/**
 * Recovery email sender resolver.
 *
 * @package WPAnchorBay\CartBay\Email
 */

namespace WPAnchorBay\CartBay\Email;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the "From" name and address CartBay recovery emails are sent with.
 *
 * Recovery emails are WooCommerce emails (WC_Email), so their From header comes
 * from WooCommerce's email sender options — not the WordPress wp_mail_from
 * defaults. Centralizing the lookup keeps the delivery-test display and the
 * test send in sync with what buyers actually receive.
 *
 * @since 1.0.1
 */
class RecoveryEmailSender {

	/**
	 * Resolve the From address and name for recovery emails.
	 *
	 * Mirrors what WC_Email::get_from_address()/get_from_name() read: the
	 * WooCommerce email sender options, falling back to the WordPress admin
	 * email and site title (the same values WooCommerce itself defaults these
	 * options to on install).
	 *
	 * @since 1.0.1
	 *
	 * @return array{email: string, name: string} From address and display name.
	 */
	public static function resolve(): array {
		$from_email = sanitize_email( (string) get_option( 'woocommerce_email_from_address' ) );
		if ( '' === $from_email ) {
			$from_email = sanitize_email( (string) get_option( 'admin_email' ) );
		}

		$from_name = trim( (string) get_option( 'woocommerce_email_from_name' ) );
		if ( '' === $from_name ) {
			$from_name = trim( (string) get_option( 'blogname' ) );
		}

		return array(
			'email' => $from_email,
			'name'  => $from_name,
		);
	}
}
