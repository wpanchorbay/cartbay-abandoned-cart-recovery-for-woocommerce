<?php
/**
 * Tests for the test-email REST route.
 *
 * @package WPAnchorBay\CartBay\Tests\Api\Routes
 */

namespace WPAnchorBay\CartBay\Tests\Api\Routes;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Response;
use WPAnchorBay\CartBay\Api\Routes\TestEmailRoute;

/**
 * Test-email REST route tests.
 *
 * @since 1.0.0
 */
class TestEmailRouteTest extends TestCase {

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

		Functions\when( '__' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'sanitize_email' )->alias(
			static fn ( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : ''
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static fn ( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : ''
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( mixed $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', is_scalar( $value ) ? (string) $value : '' ) ?? '' )
		);
		Functions\when( 'wp_get_current_user' )->alias(
			static fn (): object => (object) array( 'user_email' => 'owner@example.test' )
		);
		// Disable CartBay's own file logging so Logger::error()/info() short-circuit
		// before touching the filesystem (wp_upload_dir() etc. aren't stubbed here;
		// logging behavior is orthogonal to what this test verifies).
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $default = false ): mixed {
				if ( 'cartbay_settings' === $key ) {
					return array( 'log_enabled' => false );
				}

				return 'admin_email' === $key ? 'admin@example.test' : $default;
			}
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
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The basic (no step) test email should surface the real wp_mail_failed reason
	 * instead of a generic message, and clean up its temporary listener.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_basic_test_email_returns_the_real_failure_reason(): void {
		$captured_capture = null;

		Actions\expectAdded( 'wp_mail_failed' )->once()->whenHappen(
			static function ( callable $capture ) use ( &$captured_capture ): void {
				$captured_capture = $capture;
			}
		);
		Actions\expectRemoved( 'wp_mail_failed' )->once();

		Functions\when( 'wp_mail' )->alias(
			static function () use ( &$captured_capture ): bool {
				// Simulate WordPress core firing wp_mail_failed synchronously before returning false.
				( $captured_capture )( new WP_Error( 'wp_mail_failed', 'SMTP Error: Could not authenticate.' ) );

				return false;
			}
		);

		$result = ( new TestEmailRoute() )->handle( $this->request( array( 'email' => 'shopper@example.test' ) ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'SMTP Error: Could not authenticate.', $result->get_error_data()['reason'] );
		self::assertSame( 500, $result->get_error_data()['status'] );
	}

	/**
	 * A successful basic test email should return a success response with no reason.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_basic_test_email_success_response(): void {
		Actions\expectAdded( 'wp_mail_failed' )->once();
		Actions\expectRemoved( 'wp_mail_failed' )->once();

		Functions\when( 'wp_mail' )->justReturn( true );

		$result = ( new TestEmailRoute() )->handle( $this->request( array( 'email' => 'shopper@example.test' ) ) );

		self::assertInstanceOf( WP_REST_Response::class, $result );
		self::assertTrue( $result->get_data()['success'] );
	}

	/**
	 * The step-based preview email should also surface the real wp_mail_failed reason.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_preview_email_returns_the_real_failure_reason(): void {
		$captured_capture = null;

		Actions\expectAdded( 'wp_mail_failed' )->once()->whenHappen(
			static function ( callable $capture ) use ( &$captured_capture ): void {
				$captured_capture = $capture;
			}
		);
		Actions\expectRemoved( 'wp_mail_failed' )->once();

		$email = new FailingPreviewEmail(
			static function () use ( &$captured_capture ): void {
				( $captured_capture )( new WP_Error( 'wp_mail_failed', 'Connection timed out.' ) );
			}
		);

		Functions\when( 'WC' )->justReturn( $this->wc_with_emails( array( 'CartBay_Email_Recovery_1' => $email ) ) );

		$result = ( new TestEmailRoute() )->handle(
			$this->request(
				array(
					'email' => 'shopper@example.test',
					'step'  => 0,
				)
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'Connection timed out.', $result->get_error_data()['reason'] );
	}

	/**
	 * Build a REST request double.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $params Request parameters.
	 *
	 * @return \WP_REST_Request Request double.
	 */
	private function request( array $params ): \WP_REST_Request {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )->andReturnUsing(
			static fn ( string $key ): mixed => $params[ $key ] ?? null
		);
		$request->shouldReceive( 'has_param' )->andReturnUsing(
			static fn ( string $key ): bool => array_key_exists( $key, $params )
		);

		return $request;
	}

	/**
	 * Build a WC() double exposing a mailer with the given email instances.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, object> $emails Email instances keyed by class name.
	 *
	 * @return object WC() double.
	 */
	private function wc_with_emails( array $emails ): object {
		$mailer = Mockery::mock();
		$mailer->shouldReceive( 'get_emails' )->andReturn( $emails );

		$wc = Mockery::mock();
		$wc->shouldReceive( 'mailer' )->andReturn( $mailer );

		return $wc;
	}
}

/**
 * A fake recovery email whose preview send fails after firing wp_mail_failed.
 *
 * Standing in for a WC_Email subclass here since only send_preview() is called.
 *
 * @since 1.0.0
 */
class FailingPreviewEmail {
	/**
	 * Failure simulator, invoked during send_preview() before it returns false.
	 *
	 * @since 1.0.0
	 *
	 * @var callable
	 */
	private $on_send;

	/**
	 * @since 1.0.0
	 *
	 * @param callable $on_send Callback invoked during send_preview().
	 */
	public function __construct( callable $on_send ) {
		$this->on_send = $on_send;
	}

	/**
	 * Simulate a failed preview send.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Recipient email.
	 *
	 * @return bool Always false.
	 */
	public function send_preview( string $email ): bool {
		unset( $email );
		( $this->on_send )();

		return false;
	}
}
