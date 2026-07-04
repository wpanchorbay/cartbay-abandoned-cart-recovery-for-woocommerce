<?php
/**
 * Settings section contract.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for a CartBay WooCommerce settings section.
 *
 * @since 1.0.0
 */
interface SettingsSectionInterface {
	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string;

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string;

	/**
	 * Get WooCommerce settings API fields for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Section fields.
	 */
	public function fields(): array;

	/**
	 * Render the section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void;

	/**
	 * Save section-specific data after WooCommerce updates settings API fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void;
}
