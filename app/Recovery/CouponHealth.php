<?php
/**
 * Recovery coupon configuration health checks.
 *
 * @package WPAnchorBay\CartBay\Recovery
 */

namespace WPAnchorBay\CartBay\Recovery;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates the free-tier recovery coupon configuration for merchant-facing
 * warnings.
 *
 * All checks here are cart-independent: they concern the coupon record and the
 * sequence configuration only. Cart-specific applicability (does this coupon
 * apply to a given shopper's cart) is intentionally left to WooCommerce, which
 * already validates it at checkout against a real cart.
 *
 * @since 1.0.2
 */
class CouponHealth {

	/**
	 * Build the list of coupon configuration issues.
	 *
	 * @since 1.0.2
	 *
	 * @param string $offers_url   Optional link to the Offers settings section.
	 * @param string $sequence_url Optional link to the Recovery Sequence settings section.
	 *
	 * @return array<int, array{type: string, message: string}> Issues to surface.
	 */
	public static function get_issues( string $offers_url = '', string $sequence_url = '' ): array {
		$issues = array();

		$campaign = get_option( 'cartbay_campaign_settings', array() );
		$campaign = SequenceSettings::normalize( is_array( $campaign ) ? $campaign : array() );

		$settings    = get_option( 'cartbay_settings', array() );
		$static_code = is_array( $settings ) && isset( $settings['static_coupon_code'] )
			? (string) $settings['static_coupon_code']
			: '';
		$static_code = trim( $static_code );

		$enabled_labels = self::enabled_step_labels( $campaign );
		$has_enabled    = ! empty( $enabled_labels );

		// Gap: one or more emails include a coupon, but no code is configured.
		if ( $has_enabled && '' === $static_code ) {
			$issues[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: 1: comma-separated list of email step labels, 2: opening link tag to the Offers section, 3: closing link tag. */
					__( '<strong>%1$s</strong> set to include a recovery coupon, but no coupon code is set. Add a code on the %2$sOffers%3$s tab, or those emails will go out without a discount.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					esc_html( self::join_labels( $enabled_labels ) ),
					'' !== $offers_url ? '<a href="' . esc_url( $offers_url ) . '">' : '',
					'' !== $offers_url ? '</a>' : ''
				),
			);

			return $issues;
		}

		// Reverse gap: a code is set but no email includes it.
		if ( '' !== $static_code && ! $has_enabled ) {
			$issues[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: 1: opening link tag to the Recovery Sequence section, 2: closing link tag. */
					__( 'A recovery coupon code is set, but no email step includes it. Turn on the coupon for an email in the %1$sRecovery Sequence%2$s to send it.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					'' !== $sequence_url ? '<a href="' . esc_url( $sequence_url ) . '">' : '',
					'' !== $sequence_url ? '</a>' : ''
				),
			);
		}

		// Validate the coupon record itself when a code is set.
		if ( '' !== $static_code ) {
			$issues = array_merge( $issues, self::validate_coupon_record( $static_code ) );
		}

		return $issues;
	}

	/**
	 * Validate a coupon code against its WooCommerce coupon record.
	 *
	 * @since 1.0.2
	 *
	 * @param string $code Configured coupon code.
	 *
	 * @return array<int, array{type: string, message: string}> Issues found.
	 */
	private static function validate_coupon_record( string $code ): array {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) || ! class_exists( 'WC_Coupon' ) ) {
			return array();
		}

		// WooCommerce matches coupon codes case-insensitively and already trims on
		// save, so the raw stored string is passed through without re-normalizing.
		$coupon_id = wc_get_coupon_id_by_code( $code );

		if ( 0 === $coupon_id ) {
			return array(
				array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: %s: coupon code. */
						__( 'No active WooCommerce coupon matches the code <strong>%s</strong>. Create or publish it under WooCommerce &rarr; Marketing &rarr; Coupons, or recovery emails will send a code shoppers can\'t use.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
						esc_html( $code )
					),
				),
			);
		}

		$coupon = new \WC_Coupon( $coupon_id );
		$issues = array();

		// Expired — mirror WooCommerce's own UTC comparison so this agrees with checkout.
		$expires = $coupon->get_date_expires();
		if ( $expires instanceof \WC_DateTime && time() > $expires->getTimestamp() ) {
			$issues[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: 1: coupon code, 2: expiry date. */
					__( 'Coupon <strong>%1$s</strong> expired on %2$s. Shoppers who receive it will not be able to apply it.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					esc_html( $code ),
					esc_html( $expires->date_i18n( (string) get_option( 'date_format', 'Y-m-d' ) ) )
				),
			);
		}

		// Global usage limit exhausted.
		$usage_limit = $coupon->get_usage_limit();
		if ( $usage_limit > 0 && $coupon->get_usage_count() >= $usage_limit ) {
			$issues[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: coupon code. */
					__( 'Coupon <strong>%s</strong> has reached its usage limit and can no longer be applied.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					esc_html( $code )
				),
			);
		}

		// Email restrictions — near-always fatal for recovery emails.
		$email_restrictions = $coupon->get_email_restrictions();
		if ( ! empty( $email_restrictions ) ) {
			$issues[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: coupon code. */
					__( 'Coupon <strong>%s</strong> is restricted to specific email addresses. Abandoned-cart shoppers rarely match, so it will usually fail. Use an unrestricted coupon for recovery emails.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					esc_html( $code )
				),
			);
		}

		// Cart-dependent restrictions — informational only. WooCommerce enforces
		// these at checkout against the real cart; surfaced so merchants know some
		// shoppers may not qualify.
		if ( self::has_cart_restrictions( $coupon ) ) {
			$issues[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %s: coupon code. */
					__( 'Coupon <strong>%s</strong> has cart restrictions (minimum spend or specific products/categories). Shoppers whose carts don\'t qualify won\'t be able to use it at checkout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					esc_html( $code )
				),
			);
		}

		return $issues;
	}

	/**
	 * Determine whether a coupon carries cart-dependent restrictions.
	 *
	 * @since 1.0.2
	 *
	 * @param \WC_Coupon $coupon Coupon object.
	 *
	 * @return bool True when the coupon restricts which carts can use it.
	 */
	private static function has_cart_restrictions( \WC_Coupon $coupon ): bool {
		$minimum = (float) $coupon->get_minimum_amount();

		return $minimum > 0
			|| ! empty( $coupon->get_product_ids() )
			|| ! empty( $coupon->get_excluded_product_ids() )
			|| ! empty( $coupon->get_product_categories() )
			|| ! empty( $coupon->get_excluded_product_categories() );
	}

	/**
	 * Collect the labels of steps that include a coupon.
	 *
	 * @since 1.0.2
	 *
	 * @param array<string, mixed> $campaign Normalized campaign settings.
	 *
	 * @return array<int, string> Step labels (e.g. "Email 1").
	 */
	private static function enabled_step_labels( array $campaign ): array {
		$blueprint = SequenceSettings::get_step_blueprint();
		$steps     = isset( $campaign['steps'] ) && is_array( $campaign['steps'] ) ? $campaign['steps'] : array();
		$labels    = array();

		foreach ( $steps as $index => $step ) {
			if ( empty( $step['coupon_enabled'] ) ) {
				continue;
			}

			/* translators: %d: recovery email step number. */
			$fallback = sprintf( __( 'Email %d', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), (int) $index + 1 );
			$labels[] = isset( $blueprint[ $index ]['label'] ) ? (string) $blueprint[ $index ]['label'] : $fallback;
		}

		return $labels;
	}

	/**
	 * Join step labels into a human-readable list with correct pluralized verb.
	 *
	 * @since 1.0.2
	 *
	 * @param array<int, string> $labels Step labels.
	 *
	 * @return string Formatted list including a trailing "is"/"are".
	 */
	private static function join_labels( array $labels ): string {
		$count = count( $labels );

		if ( 1 === $count ) {
			/* translators: %s: a single email step label, e.g. "Email 3". */
			return sprintf( __( '%s is', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), $labels[0] );
		}

		/* translators: %s: comma-separated list of email step labels. */
		return sprintf( __( '%s are', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), implode( ', ', $labels ) );
	}
}
