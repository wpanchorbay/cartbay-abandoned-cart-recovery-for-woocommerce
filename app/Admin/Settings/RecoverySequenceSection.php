<?php
/**
 * Recovery sequence settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

use WPAnchorBay\CartBay\Recovery\SequenceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Recovery sequence settings section.
 *
 * @since 1.0.0
 */
class RecoverySequenceSection extends AbstractSettingsSection {
	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string {
		return 'sequence';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Recovery Sequence', 'cartbay' );
	}

	/**
	 * Get WooCommerce settings API fields for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Section fields.
	 */
	public function fields(): array {
		$campaign = get_option( 'cartbay_campaign_settings', array() );
		$campaign = SequenceSettings::normalize( is_array( $campaign ) ? $campaign : array() );

		return array(
			array(
				'title' => __( 'Recovery Sequence', 'cartbay' ),
				'type'  => 'title',
				'desc'  => __( 'Configure when each recovery email is sent and whether individual steps include an incentive.', 'cartbay' ),
				'id'    => 'cartbay_sequence_settings',
			),
			array(
				'id'       => 'cartbay_sequence_designer',
				'type'     => 'cartbay_sequence_designer',
				'campaign' => $campaign,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cartbay_sequence_settings',
			),
		);
	}

	/**
	 * Save the recovery sequence settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handled by WooCommerce settings save.
		$posted_campaign = isset( $_POST['cartbay_campaign_settings'] ) ? map_deep( wp_unslash( $_POST['cartbay_campaign_settings'] ), 'sanitize_text_field' ) : array();
		$campaign        = SequenceSettings::normalize( is_array( $posted_campaign ) ? $posted_campaign : array() );

		update_option( 'cartbay_campaign_settings', $campaign );
	}

	/**
	 * Render the guided recovery sequence designer.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @return void
	 */
	public function render_sequence_designer_field( array $field ): void {
		$campaign = SequenceSettings::normalize( isset( $field['campaign'] ) && is_array( $field['campaign'] ) ? $field['campaign'] : array() );
		$steps    = SequenceSettings::get_step_blueprint();

		echo '<tr valign="top" id="cartbay_sequence_designer">';
		echo '<th scope="row" class="titledesc">';
		echo '<label for="cartbay_campaign_enabled">' . esc_html__( 'Email sending', 'cartbay' ) . '</label>';
		echo '</th>';
		echo '<td class="forminp">';
		echo '<div class="cartbay-sequence-enable">';
		echo '<div class="cartbay-sequence-enable__content">';
		echo '<span class="cartbay-sequence-enable__eyebrow">' . esc_html__( 'Recommended', 'cartbay' ) . '</span>';
		echo '<h3 class="cartbay-sequence-enable__title">' . esc_html__( 'Send the 3-email recovery sequence', 'cartbay' ) . '</h3>';
		echo '<p class="description cartbay-sequence-enable__description">' . esc_html__( 'When enabled, CartBay sends the sequence below after a cart becomes abandoned. Turn this off to keep cart capture active without sending recovery emails.', 'cartbay' ) . '</p>';
		echo '</div>';
		echo '<label class="cartbay-toggle cartbay-sequence-enable__control">';
		echo '<input type="hidden" name="cartbay_campaign_settings[enabled]" value="0" />';
		echo '<input type="checkbox" id="cartbay_campaign_enabled" name="cartbay_campaign_settings[enabled]" value="1"' . checked( ! empty( $campaign['enabled'] ), true, false ) . ' />';
		echo '<span>' . esc_html__( 'Enable sending', 'cartbay' ) . '</span>';
		echo '</label>';
		echo '</div>';

		echo '<div class="cartbay-sequence-builder" aria-label="' . esc_attr__( 'Recovery sequence steps', 'cartbay' ) . '">';

		for ( $index = 0; $index < 3; $index++ ) {
			$step        = $campaign['steps'][ $index ] ?? array();
			$step_copy   = $steps[ $index ] ?? array();
			$delay_parts = SequenceSettings::get_delay_parts( absint( $step['delay_minutes'] ?? 0 ) );
			$why         = sanitize_text_field( (string) ( $step_copy['why'] ?? '' ) );

			echo '<section class="cartbay-sequence-card" data-step-index="' . esc_attr( (string) $index ) . '">';
			/* translators: %d: recovery email step number */
			$fallback_label = sprintf( __( 'Email %d', 'cartbay' ), $index + 1 );
			echo '<div class="cartbay-sequence-card__header">';
			echo '<span class="cartbay-sequence-card__number">' . esc_html( (string) ( $index + 1 ) ) . '</span>';
			echo '<div class="cartbay-sequence-card__title-group">';
			echo '<p class="cartbay-sequence-step-label">' . esc_html( $step_copy['label'] ?? $fallback_label ) . '</p>';
			echo '<h3 class="cartbay-sequence-step-name">' . esc_html( $step_copy['name'] ?? '' ) . '</h3>';
			echo '<p class="description">' . esc_html( $step_copy['when'] ?? '' ) . '</p>';
			echo '</div>';
			echo '</div>';

			echo '<div class="cartbay-sequence-card__body">';
			echo '<div class="cartbay-sequence-group">';
			echo '<h4 class="cartbay-sequence-group__title">' . esc_html__( 'Timing', 'cartbay' ) . '</h4>';
			echo '<div class="cartbay-sequence-card__controls">';
			echo '<label class="screen-reader-text" for="cartbay_delay_value_' . esc_attr( (string) $index ) . '">' . esc_html__( 'Send after', 'cartbay' ) . '</label>';
			echo '<input type="number" class="small-text cartbay-delay-value" id="cartbay_delay_value_' . esc_attr( (string) $index ) . '" name="cartbay_campaign_settings[steps][' . esc_attr( (string) $index ) . '][delay_value]" min="1" max="999" value="' . esc_attr( (string) $delay_parts['value'] ) . '" />';
			echo '<select class="cartbay-delay-unit" name="cartbay_campaign_settings[steps][' . esc_attr( (string) $index ) . '][delay_unit]">';
			echo '<option value="minutes"' . selected( 'minutes', $delay_parts['unit'], false ) . '>' . esc_html__( 'minutes', 'cartbay' ) . '</option>';
			echo '<option value="hours"' . selected( 'hours', $delay_parts['unit'], false ) . '>' . esc_html__( 'hours', 'cartbay' ) . '</option>';
			echo '<option value="days"' . selected( 'days', $delay_parts['unit'], false ) . '>' . esc_html__( 'days', 'cartbay' ) . '</option>';
			echo '</select>';
			echo '</div>';
			echo '<p class="description cartbay-sequence-summary"><strong>' . esc_html__( 'Sends after:', 'cartbay' ) . '</strong> <span class="cartbay-sequence-summary__value">' . esc_html( SequenceSettings::format_delay( absint( $step['delay_minutes'] ?? 0 ) ) ) . '</span></p>';
			echo '</div>';

			echo '<div class="cartbay-sequence-group">';
			echo '<h4 class="cartbay-sequence-group__title">' . esc_html__( 'Message focus', 'cartbay' ) . '</h4>';
			if ( '' !== $why ) {
				echo '<p class="description cartbay-sequence-group__copy">' . esc_html( $why ) . '</p>';
			}
			$expected_behavior = sanitize_text_field( (string) ( $step_copy['expected_behavior'] ?? '' ) );
			if ( '' !== $expected_behavior ) {
				echo '<p class="description cartbay-sequence-group__copy">' . esc_html( $expected_behavior ) . '</p>';
			}
			echo '</div>';

			echo '<div class="cartbay-sequence-group">';
			echo '<h4 class="cartbay-sequence-group__title">' . esc_html__( 'Coupon', 'cartbay' ) . '</h4>';
			echo '<label class="cartbay-toggle cartbay-toggle--inline">';
			echo '<input type="hidden" name="cartbay_campaign_settings[steps][' . esc_attr( (string) $index ) . '][coupon_enabled]" value="0" />';
			echo '<input type="checkbox" name="cartbay_campaign_settings[steps][' . esc_attr( (string) $index ) . '][coupon_enabled]" value="1"' . checked( ! empty( $step['coupon_enabled'] ), true, false ) . ' />';
			echo '<span>' . esc_html__( 'Include a recovery coupon', 'cartbay' ) . '</span>';
			echo '</label>';
			echo '<p class="description cartbay-sequence-group__copy">' . esc_html( $step_copy['coupon_guidance'] ?? '' ) . '</p>';
			echo '</div>';
			echo '</div>';
			echo '</section>';
		}

		echo '</div>';
		echo '<p class="description cartbay-sequence-footer-note">' . esc_html__( 'CartBay keeps the sequence in order. If a later email is scheduled too close to an earlier one, it will be moved forward automatically.', 'cartbay' ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}
}
