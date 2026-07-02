<?php
/**
 * Plugin settings helpers.
 *
 * @package WPAnchorBay\CartBay\Core
 */

namespace WPAnchorBay\CartBay\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and normalizes CartBay settings.
 *
 * @since 1.0.0
 */
class Settings {
	/**
	 * Determine whether checkout capture is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether new checkout capture should run.
	 */
	public static function is_capture_enabled(): bool {
		$settings = get_option( 'cartbay_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( ! array_key_exists( 'capture_enabled', $settings ) ) {
			return true;
		}

		return self::normalize_boolean( $settings['capture_enabled'] );
	}

	/**
	 * Determine whether the WooCommerce admin menu shortcut is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the WooCommerce menu shortcut should be shown.
	 */
	public static function is_wc_menu_enabled(): bool {
		$settings = get_option( 'cartbay_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( ! array_key_exists( 'wc_menu_enabled', $settings ) ) {
			return true;
		}

		return self::normalize_boolean( $settings['wc_menu_enabled'] );
	}

	/**
	 * Normalize stored checkbox-like values to a boolean.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Stored setting value.
	 *
	 * @return bool Normalized boolean value.
	 */
	private static function normalize_boolean( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 1 === absint( $value );
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );

			return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}
}
