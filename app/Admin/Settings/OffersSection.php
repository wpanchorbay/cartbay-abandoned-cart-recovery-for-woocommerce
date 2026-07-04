<?php
/**
 * Offers settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Offers settings section: a single static recovery coupon code.
 *
 * Dynamic per-session coupon generation (unique single-use codes, expiry, and
 * auto-apply on restore) is a CartBay Pro feature. The free plugin lets a
 * merchant reference one coupon code they created in WooCommerce, which is
 * included as plain text in recovery emails where the step opts in.
 *
 * @since 1.0.0
 */
class OffersSection extends AbstractSettingsSection {

	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string {
		return 'offers';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Offers', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
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
				'title' => __( 'Recovery Coupon', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Reference a coupon you created under WooCommerce &rarr; Marketing &rarr; Coupons. When a recovery email step has its coupon enabled, this code is included in the email as plain text for shoppers to apply at checkout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'    => 'cartbay_offer_settings',
			),
			array(
				'title'    => __( 'Coupon Code', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc_tip' => __( 'Enter an existing WooCommerce coupon code. Leave blank to send recovery emails without a discount code.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc'     => __( 'CartBay does not create or expire this coupon — manage it in WooCommerce &rarr; Marketing &rarr; Coupons.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'       => 'cartbay_settings[static_coupon_code]',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'min-width:220px;',
			),
			array(
				'title' => __( 'Want unique, expiring coupons?', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'type'  => 'cartbay_static_row',
				'value' => esc_html__( 'CartBay Pro generates a unique, single-use coupon per shopper, sets an expiry, and auto-applies it when the cart is restored — so discount codes can\'t leak or be reused.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'    => 'cartbay_offer_pro_hint',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cartbay_offer_settings',
			),
		);
	}
}
