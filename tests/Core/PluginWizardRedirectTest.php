<?php
/**
 * Tests for plugin wizard redirects.
 *
 * @package WPAnchorBay\CartBay\Tests\Core
 */

namespace WPAnchorBay\CartBay\Tests\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAnchorBay\CartBay\Core\Plugin;

/**
 * Plugin wizard redirect tests.
 *
 * @since 1.0.0
 */
class PluginWizardRedirectTest extends TestCase {
	/**
	 * Prepare WordPress function doubles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_unslash' )->alias(
			static fn ( mixed $value ): mixed => $value
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( mixed $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', is_scalar( $value ) ? (string) $value : '' ) ?? '' )
		);
	}

	/**
	 * Clean up WordPress function doubles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_GET = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The visible WooCommerce CartBay settings page should trigger the first-run wizard.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_wc_settings_cartbay_page_is_wizard_entry_request(): void {
		self::assertTrue(
			$this->is_cartbay_wizard_entry_request(
				array(
					'page' => 'wc-settings',
					'tab'  => 'cartbay',
				)
			)
		);
	}

	/**
	 * The previous CartBay page request remains supported.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_legacy_cartbay_page_is_wizard_entry_request(): void {
		self::assertTrue(
			$this->is_cartbay_wizard_entry_request(
				array(
					'page' => 'cartbay',
				)
			)
		);
	}

	/**
	 * Other WooCommerce settings tabs should not trigger the CartBay wizard.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_other_wc_settings_tabs_are_not_wizard_entry_requests(): void {
		self::assertFalse(
			$this->is_cartbay_wizard_entry_request(
				array(
					'page' => 'wc-settings',
					'tab'  => 'products',
				)
			)
		);
	}

	/**
	 * Invoke the private wizard entry request classifier.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $query Request query parameters.
	 *
	 * @return bool Whether the request is a CartBay wizard entry point.
	 */
	private function is_cartbay_wizard_entry_request( array $query ): bool {
		$_GET = $query;

		$plugin = ( new \ReflectionClass( Plugin::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( Plugin::class, 'is_cartbay_wizard_entry_request' );
		$method->setAccessible( true );

		return (bool) $method->invoke( $plugin );
	}
}
