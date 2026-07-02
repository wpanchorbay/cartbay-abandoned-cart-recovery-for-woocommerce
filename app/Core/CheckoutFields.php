<?php
/**
 * Checkout field registration.
 *
 * @package WPAnchorBay\CartBay\Core
 */

namespace WPAnchorBay\CartBay\Core;

use WPAnchorBay\CartBay\Utils\Logger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Register and observe WooCommerce checkout fields.
 *
 * @since 1.0.0
 */
class CheckoutFields {
	/**
	 * Block checkout field identifier.
	 *
	 * @since 1.0.0
	 */
	private const FIELD_ID = 'cartbay/marketing-consent';

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'woocommerce_init', array( $this, 'register_marketing_consent_field' ) );
		add_action( 'woocommerce_blocks_validate_location_contact_fields', array( $this, 'log_marketing_consent_value' ), 10, 3 );
		add_filter( 'woocommerce_get_default_value_for_cartbay/marketing-consent', array( $this, 'get_marketing_consent_default_value' ), 10, 3 );
	}

	/**
	 * Register the CartBay marketing consent field in Block Checkout.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_marketing_consent_field(): void {
		if ( ! Settings::is_capture_enabled() ) {
			return;
		}

		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			Logger::warning( 'WooCommerce additional checkout fields API is unavailable.', array(), 'checkout' );
			return;
		}

		$settings         = get_option( 'cartbay_settings', array() );
		$raw_consent_text = isset( $settings['consent_text'] ) && '' !== $settings['consent_text']
			? (string) $settings['consent_text']
			: __( 'Save my email to recover my cart if I leave.', 'cartbay' );

		// This value renders as a checkbox label on the storefront checkout, so
		// force it to plain text — a stored consent string can never inject
		// markup into checkout (defense-in-depth; the field needs no HTML).
		$consent_text = sanitize_text_field( $raw_consent_text );

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => self::FIELD_ID,
				'label'    => $consent_text,
				'location' => 'contact',
				'type'     => 'checkbox',
				'required' => false,
			)
		);

		Logger::info( 'Registered CartBay Block Checkout marketing consent field.', array(), 'checkout' );
	}

	/**
	 * Get the configured default value for the Block Checkout consent checkbox.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value     Existing default value.
	 * @param string $group     Field group identifier.
	 * @param mixed  $wc_object WooCommerce object receiving the default.
	 *
	 * @return bool Whether the checkbox should be checked by default.
	 */
	public function get_marketing_consent_default_value( mixed $value, string $group, mixed $wc_object ): bool {
		unset( $value, $group, $wc_object );

		$settings = get_option( 'cartbay_settings', array() );

		return 'unchecked' !== ( $settings['consent_default_state'] ?? 'checked' );
	}

	/**
	 * Log the submitted marketing consent value during Block Checkout validation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error $errors Validation errors.
	 * @param array    $fields Contact location fields.
	 * @param string   $group  Field group identifier.
	 *
	 * @return void
	 */
	public function log_marketing_consent_value( WP_Error $errors, array $fields, string $group ): void {
		unset( $errors );

		if ( 'other' !== $group || ! array_key_exists( self::FIELD_ID, $fields ) ) {
			return;
		}

		Logger::info(
			'Captured CartBay Block Checkout marketing consent field during validation.',
			array(
				'cartbay_marketing_consent' => (bool) $fields[ self::FIELD_ID ],
			),
			'checkout'
		);
	}
}
