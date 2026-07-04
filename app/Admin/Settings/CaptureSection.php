<?php
/**
 * Capture settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Capture settings section.
 *
 * @since 1.0.0
 */
class CaptureSection extends AbstractSettingsSection {
	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string {
		return 'capture';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Capture', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
	}

	/**
	 * Get WooCommerce settings API fields for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Section fields.
	 */
	public function fields(): array {
		return array(
			array(
				'title' => __( 'Capture Settings', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Configure how CartBay captures email and cart data at checkout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'    => 'cartbay_capture_settings',
			),
			array(
				'title'    => __( 'Enable Capture', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc'     => __( 'Capture email and cart data at checkout', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc_tip' => __( 'Turns guest cart capture on for both classic checkout and WooCommerce Blocks checkout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'tooltip'  => __( 'When enabled, CartBay loads the checkout capture scripts and accepts consented capture requests. Turn this off to pause new cart and email capture without deleting existing sessions.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'       => 'cartbay_settings[capture_enabled]',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Consent Text', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc'     => __( 'Text shown next to the consent checkbox on checkout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc_tip' => __( 'Keep this short and explicit so shoppers understand you may email them about an unfinished checkout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'       => 'cartbay_settings[consent_text]',
				'default'  => '',
				'type'     => 'textarea',
				'css'      => 'width:400px;height:60px;',
			),
			array(
				'title'    => __( 'Consent Checkbox Default State', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc'     => __( 'Choose whether the checkout consent checkbox starts checked or unchecked.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc_tip' => __( 'Shoppers can still change the checkbox at checkout. CartBay only captures while it is checked.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'       => 'cartbay_settings[consent_default_state]',
				'default'  => 'checked',
				'type'     => 'select',
				'options'  => array(
					'checked'   => __( 'Checked', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					'unchecked' => __( 'Unchecked', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				),
			),
			array(
				'title'             => __( 'Abandonment Timeout (minutes)', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc'              => __( 'Minutes of inactivity before a cart is marked as abandoned.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc_tip'          => __( 'CartBay waits this long after the shopper stops interacting before the recovery sequence becomes eligible.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'                => 'cartbay_settings[abandonment_timeout]',
				'default'           => 30,
				'type'              => 'number',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min' => 5,
					'max' => 1440,
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cartbay_capture_settings',
			),
		);
	}
}
