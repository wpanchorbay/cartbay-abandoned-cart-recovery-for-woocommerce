<?php
/**
 * CartBay admin environment helpers.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shared admin request and environment checks.
 *
 * @since 1.0.0
 */
class AdminEnvironment {
	/**
	 * Settings URL helper.
	 *
	 * @since 1.0.0
	 *
	 * @var SettingsUrl
	 */
	private SettingsUrl $url;

	/**
	 * Mail environment detector.
	 *
	 * @since 1.0.0
	 *
	 * @var MailEnvironmentDetector
	 */
	private MailEnvironmentDetector $mail_environment;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SettingsUrl             $url              Settings URL helper.
	 * @param MailEnvironmentDetector $mail_environment Mail environment detector.
	 */
	public function __construct( SettingsUrl $url, MailEnvironmentDetector $mail_environment ) {
		$this->url              = $url;
		$this->mail_environment = $mail_environment;
	}

	/**
	 * Determine whether the current admin request belongs to CartBay.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether this is a CartBay admin page.
	 */
	public function is_cartbay_admin_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $this->url->is_wc_settings_cartbay_page() || 'cartbay-wizard' === $page;
	}

	/**
	 * Get detected mail environment status.
	 *
	 * @since 1.0.0
	 *
	 * @return array{has_delivery: bool, has_logger: bool, delivery: array{source: string, detail: string, confidence: string}, logger: array{source: string, detail: string, confidence: string}} Mail environment status.
	 */
	public function get_mail_environment_status(): array {
		return $this->mail_environment->detect();
	}
}
