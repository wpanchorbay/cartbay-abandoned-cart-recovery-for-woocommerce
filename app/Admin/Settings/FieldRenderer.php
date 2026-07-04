<?php
/**
 * WooCommerce settings field renderers.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders custom CartBay WooCommerce settings fields.
 *
 * @since 1.0.0
 */
class FieldRenderer {
	/**
	 * Render a static WooCommerce settings row.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @return void
	 */
	public function render_static_row( array $field ): void {
		$field_id    = isset( $field['id'] ) ? sanitize_html_class( (string) $field['id'] ) : '';
		$title       = isset( $field['title'] ) ? (string) $field['title'] : '';
		$value       = isset( $field['value'] ) ? (string) $field['value'] : '';
		$description = isset( $field['description'] ) ? (string) $field['description'] : '';
		$tooltip     = isset( $field['tooltip'] ) ? (string) $field['tooltip'] : '';

		echo '<tr valign="top" id="' . esc_attr( $field_id ) . '">';
		echo '<th scope="row" class="titledesc">' . $this->get_title_html( $title, $tooltip ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<td class="forminp forminp-text">' . wp_kses_post( $value );

		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render a WooCommerce settings row that contains action buttons.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @return void
	 */
	public function render_action_row( array $field ): void {
		$field_id    = isset( $field['id'] ) ? sanitize_html_class( (string) $field['id'] ) : '';
		$title       = isset( $field['title'] ) ? (string) $field['title'] : '';
		$tooltip     = isset( $field['tooltip'] ) ? (string) $field['tooltip'] : '';
		$description = isset( $field['description'] ) ? (string) $field['description'] : '';
		$actions     = isset( $field['actions'] ) && is_array( $field['actions'] ) ? $field['actions'] : array();

		echo '<tr valign="top" id="' . esc_attr( $field_id ) . '">';
		echo '<th scope="row" class="titledesc">' . $this->get_title_html( $title, $tooltip ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<td class="forminp">';

		foreach ( $actions as $action_html ) {
			echo '<span class="cartbay-settings-action">' . wp_kses_post( (string) $action_html ) . '</span>';
		}

		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Build a WooCommerce help-tip HTML fragment when available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tooltip Tooltip content.
	 *
	 * @return string Help tip HTML.
	 */
	public function get_help_tip_html( string $tooltip ): string {
		if ( '' === $tooltip || ! function_exists( 'wc_help_tip' ) ) {
			return '';
		}

		return wc_help_tip( $tooltip, false );
	}

	/**
	 * Build consistent title-cell markup for custom WooCommerce settings rows.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title     Row title text.
	 * @param string $tooltip   Tooltip content.
	 * @param string $label_for Optional input ID for label binding.
	 *
	 * @return string Title HTML.
	 */
	public function get_title_html( string $title, string $tooltip, string $label_for = '' ): string {
		$tooltip_html = wp_kses_post( $this->get_help_tip_html( $tooltip ) );
		$for_attr     = '' !== $label_for ? ' for="' . esc_attr( $label_for ) . '"' : '';

		return '<label' . $for_attr . '>' . esc_html( $title ) . ' ' . $tooltip_html . '</label>';
	}
}
