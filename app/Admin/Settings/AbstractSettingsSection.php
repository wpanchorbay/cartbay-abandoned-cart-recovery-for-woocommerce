<?php
/**
 * Base settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Base implementation for WooCommerce settings API backed sections.
 *
 * @since 1.0.0
 */
abstract class AbstractSettingsSection implements SettingsSectionInterface {
	/**
	 * Render the section using WooCommerce's settings API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		woocommerce_admin_fields( $this->fields() );
	}

	/**
	 * Format a Unix timestamp in the site timezone.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $format    Date format string.
	 *
	 * @return string Formatted local datetime.
	 */
	protected function format_local_datetime( int $timestamp, string $format = 'Y-m-d H:i' ): string {
		if ( $timestamp <= 0 ) {
			return '—';
		}

		return wp_date( $format, $timestamp );
	}

	/**
	 * Render a metric card with an optional explanatory tooltip.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label      Metric label.
	 * @param string $value      Metric value.
	 * @param string $tooltip    Tooltip text.
	 * @param bool   $allow_html Whether the value contains safe HTML.
	 * @param string $class_name Extra CSS classes for the card.
	 *
	 * @return void
	 */
	protected function render_metric_card( string $label, string $value, string $tooltip = '', bool $allow_html = false, string $class_name = '' ): void {
		$class_parts   = preg_split( '/\s+/', $class_name );
		$extra_classes = array_filter( array_map( 'sanitize_html_class', false === $class_parts ? array() : $class_parts ) );
		$classes       = trim( 'cartbay-metric-card ' . implode( ' ', $extra_classes ) );

		echo '<div class="' . esc_attr( $classes ) . '">';
		if ( '' !== $tooltip && function_exists( 'wc_help_tip' ) ) {
			echo '<span class="cartbay-metric-card__help">' . wp_kses_post( wc_help_tip( $tooltip, false ) ) . '</span>';
		}

		echo '<div class="cartbay-metric-card__value">' . ( $allow_html ? $value : esc_html( $value ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div class="cartbay-metric-card__label">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	/**
	 * Save section-specific data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void {}
}
