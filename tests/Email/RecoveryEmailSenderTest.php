<?php
/**
 * Tests for the recovery email sender resolver.
 *
 * @package WPAnchorBay\CartBay\Tests\Email
 */

namespace WPAnchorBay\CartBay\Tests\Email;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPAnchorBay\CartBay\Email\RecoveryEmailSender;

/**
 * Recovery email sender resolver tests.
 *
 * @since 1.0.1
 */
class RecoveryEmailSenderTest extends TestCase {

	/**
	 * Prepare WordPress function doubles.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'sanitize_email' )->alias(
			static fn ( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : ''
		);
	}

	/**
	 * Clean up WordPress function doubles.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The WooCommerce sender options are used when configured.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function test_resolve_uses_woocommerce_sender_options(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, mixed $default = false ): mixed => match ( $key ) {
				'woocommerce_email_from_address' => 'store@example.test',
				'woocommerce_email_from_name'    => 'My Store',
				default                          => $default,
			}
		);

		$sender = RecoveryEmailSender::resolve();

		self::assertSame( 'store@example.test', $sender['email'] );
		self::assertSame( 'My Store', $sender['name'] );
	}

	/**
	 * When the WooCommerce sender options are empty, fall back to the admin
	 * email and site title (the same values WooCommerce defaults them to).
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public function test_resolve_falls_back_to_admin_email_and_blogname(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, mixed $default = false ): mixed => match ( $key ) {
				'admin_email' => 'admin@example.test',
				'blogname'    => 'Example Site',
				default       => $default,
			}
		);

		$sender = RecoveryEmailSender::resolve();

		self::assertSame( 'admin@example.test', $sender['email'] );
		self::assertSame( 'Example Site', $sender['name'] );
	}
}
