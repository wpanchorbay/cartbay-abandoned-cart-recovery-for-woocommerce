<?php
/**
 * Tests for the setup wizard controller.
 *
 * @package WPAnchorBay\CartBay\Tests\Admin
 */

namespace WPAnchorBay\CartBay\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WPAnchorBay\CartBay\Admin\Settings\AdminEnvironment;
use WPAnchorBay\CartBay\Admin\Wizard\WizardController;
use WPAnchorBay\CartBay\Core\Container;

/**
 * Setup wizard controller tests.
 *
 * @since 1.0.0
 */
class WizardControllerTest extends TestCase {
	/**
	 * Test option storage.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Updated options captured during a test.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	private array $updates = array();

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

		$_GET          = array();
		$_POST         = array();
		$this->options = array(
			'cartbay_settings'          => array(
				'consent_text'        => 'Save my email to recover my cart if I leave.',
				'abandonment_timeout' => 30,
			),
			'cartbay_campaign_settings' => array(
				'enabled' => true,
				'steps'   => array(
					array(
						'delay_minutes'  => 45,
						'template_id'    => 0,
						'coupon_enabled' => false,
					),
					array(
						'delay_minutes'  => 1440,
						'template_id'    => 0,
						'coupon_enabled' => false,
					),
					array(
						'delay_minutes'  => 4320,
						'template_id'    => 0,
						'coupon_enabled' => true,
					),
				),
			),
			'admin_email'               => 'admin@example.test',
		);
		$this->updates = array();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_attr__' )->returnArg();
		Functions\when( 'esc_js' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_textarea' )->returnArg();
		Functions\when( 'esc_attr' )->alias( static fn ( mixed $value ): string => is_scalar( $value ) ? (string) $value : '' );
		Functions\when( 'esc_html' )->alias( static fn ( mixed $value ): string => is_scalar( $value ) ? (string) $value : '' );
		Functions\when( 'esc_attr_e' )->alias(
			static function ( string $text ): void {
				echo esc_attr( $text );
			}
		);
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo esc_html( $text );
			}
		);
		Functions\when( '_n' )->alias(
			static fn ( string $single, string $plural, int $number ): string => 1 === $number ? $single : $plural
		);
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'sanitize_text_field' )->alias(
			static fn ( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : ''
		);
		Functions\when( 'sanitize_email' )->alias(
			static fn ( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : ''
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( mixed $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', is_scalar( $value ) ? (string) $value : '' ) ?? '' )
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'map_deep' )->alias(
			static function ( mixed $value, callable|string $callback ): mixed {
				if ( is_array( $value ) ) {
					return array_map(
						static fn ( mixed $item ): mixed => map_deep( $item, $callback ),
						$value
					);
				}

				return is_callable( $callback ) ? $callback( $value ) : $value;
			}
		);
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://store.test/wp-admin/' . $path );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ): string {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'rest_url' )->alias( static fn ( string $path = '' ): string => 'https://store.test/wp-json/' . $path );
		Functions\when( 'checked' )->alias(
			static function ( mixed $checked, mixed $current = true, bool $display = true ): string {
				unset( $display );

				return $checked === $current ? ' checked="checked"' : '';
			}
		);
		Functions\when( 'selected' )->alias(
			static function ( mixed $selected, mixed $current = true, bool $display = true ): string {
				unset( $display );

				return $selected === $current ? ' selected="selected"' : '';
			}
		);
		Functions\when( 'get_option' )->alias( fn ( string $key, mixed $fallback = null ): mixed => $this->options[ $key ] ?? $fallback );
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->updates[ $key ] = $value;
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_get_current_user' )->alias(
			static fn (): object => (object) array( 'user_email' => 'owner@example.test' )
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
		$_GET  = array();
		$_POST = array();
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Wizard notices should render as inline messages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_wizard_renders_success_action_notice(): void {
		$_GET = array(
			'step'       => '2',
			'wc_message' => 'Settings saved.',
		);

		$html = $this->render_wizard_step( 2 );

		self::assertStringContainsString( 'class="notice notice-success inline"', $html );
		self::assertStringContainsString( '<p>Settings saved.</p>', $html );
	}

	/**
	 * Wizard errors should render as inline messages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_wizard_renders_error_action_notice(): void {
		$_GET = array(
			'step'     => '2',
			'wc_error' => 'Please review the settings.',
		);

		$html = $this->render_wizard_step( 2 );

		self::assertStringContainsString( 'class="notice notice-error inline"', $html );
		self::assertStringContainsString( '<p>Please review the settings.</p>', $html );
	}

	/**
	 * Email delivery setup should let the merchant choose the test recipient.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_email_delivery_step_includes_recipient_input(): void {
		$html = $this->render_wizard_step( 3, $this->admin_environment() );

		self::assertStringContainsString( 'id="cartbay-test-email-address"', $html );
		self::assertStringContainsString( 'value="owner@example.test"', $html );
		// The send button is rendered here; its click handler is enqueued via
		// wp_add_inline_script rather than printed inline in the page markup.
		self::assertStringContainsString( 'id="cartbay-test-email"', $html );
		self::assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Consent and timing setup should persist recovery email sequence delays.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_consent_step_persists_recovery_email_delays(): void {
		$_POST = array(
			'cartbay_consent_text'        => 'Recover my cart.',
			'cartbay_abandonment_timeout' => '25',
			'cartbay_campaign_settings'   => array(
				'steps' => array(
					array(
						'delay_value' => '30',
						'delay_unit'  => 'minutes',
					),
					array(
						'delay_value' => '2',
						'delay_unit'  => 'hours',
					),
					array(
						'delay_value' => '2',
						'delay_unit'  => 'days',
					),
				),
			),
		);

		$next_step = $this->process_step( 2 );

		self::assertSame( 3, $next_step );
		self::assertSame( 'Recover my cart.', $this->updates['cartbay_settings']['consent_text'] );
		self::assertSame( 25, $this->updates['cartbay_settings']['abandonment_timeout'] );
		self::assertSame( 30, $this->updates['cartbay_campaign_settings']['steps'][0]['delay_minutes'] );
		self::assertSame( 120, $this->updates['cartbay_campaign_settings']['steps'][1]['delay_minutes'] );
		self::assertSame( 2880, $this->updates['cartbay_campaign_settings']['steps'][2]['delay_minutes'] );
	}

	/**
	 * Consent and timing setup should explain each control through tooltips.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_consent_step_includes_tooltips_for_each_control(): void {
		$html = $this->render_wizard_step( 2 );

		self::assertSame( 5, substr_count( $html, 'cartbay-wizard-help-tip' ) );
		self::assertStringContainsString( 'This text appears beside the checkout consent checkbox.', $html );
		self::assertStringContainsString( 'CartBay waits this long after the shopper last interacts with checkout before marking the cart abandoned.', $html );
		self::assertStringContainsString( 'The first recovery email should arrive while the shopper still remembers the cart.', $html );
		self::assertStringContainsString( 'The second email gives shoppers time to compare options before following up.', $html );
		self::assertStringContainsString( 'The final email is the later reminder and usually carries the strongest recovery message.', $html );
	}

	/**
	 * get_steps() must stay public and static: SettingsPage::is_wizard_email_step()
	 * calls it directly to resolve the current step key without depending on a
	 * hardcoded step number, since Pro shifts the order by injecting its own step.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_steps_is_public_static_and_ordered(): void {
		self::assertSame(
			array( 'welcome', 'consent', 'email', 'launch' ),
			array_keys( WizardController::get_steps() )
		);
	}

	/**
	 * Render a wizard step.
	 *
	 * @since 1.0.0
	 *
	 * @param int                   $step              Step number.
	 * @param AdminEnvironment|null $admin_environment Admin environment double.
	 *
	 * @return string Rendered HTML.
	 */
	private function render_wizard_step( int $step, ?AdminEnvironment $admin_environment = null ): string {
		$_GET = array_merge( $_GET, array( 'step' => (string) $step ) );

		ob_start();
		$this->controller( $admin_environment )->render();

		return (string) ob_get_clean();
	}

	/**
	 * Process a wizard step.
	 *
	 * @since 1.0.0
	 *
	 * @param int $step Step number.
	 *
	 * @return int Next step.
	 */
	private function process_step( int $step ): int {
		$controller = $this->controller();

		return $this->invoke_process_step( $controller, $step );
	}

	/**
	 * Invoke the private wizard step processor.
	 *
	 * @since 1.0.0
	 *
	 * @param WizardController $controller Wizard controller.
	 * @param int              $step       Step number.
	 *
	 * @return int Next step.
	 */
	private function invoke_process_step( WizardController $controller, int $step ): int {
		$method     = new \ReflectionMethod( WizardController::class, 'process_step' );
		$method->setAccessible( true );
		$step_keys = array( 'welcome', 'consent', 'email', 'launch' );

		return (int) $method->invoke( $controller, $step_keys[ $step - 1 ], $step, count( $step_keys ) );
	}

	/**
	 * Invoke the private wizard step URL builder.
	 *
	 * @since 1.0.0
	 *
	 * @param WizardController $controller Wizard controller.
	 * @param int              $step       Step number.
	 *
	 * @return string Wizard step URL.
	 */
	private function invoke_wizard_step_url( WizardController $controller, int $step ): string {
		$method = new \ReflectionMethod( WizardController::class, 'wizard_step_url' );
		$method->setAccessible( true );

		return (string) $method->invoke( $controller, $step );
	}

	/**
	 * Create a wizard controller.
	 *
	 * @since 1.0.0
	 *
	 * @param AdminEnvironment|null $admin_environment Admin environment double.
	 *
	 * @return WizardController Wizard controller.
	 */
	private function controller( ?AdminEnvironment $admin_environment = null ): WizardController {
		$container = new Container();

		if ( null !== $admin_environment ) {
			$container->bind( AdminEnvironment::class, static fn (): AdminEnvironment => $admin_environment );
		}

		return new WizardController( $container );
	}

	/**
	 * Create an admin environment double.
	 *
	 * @since 1.0.0
	 *
	 * @return AdminEnvironment Admin environment double.
	 */
	private function admin_environment(): AdminEnvironment {
		$environment = Mockery::mock( AdminEnvironment::class );
		$environment->shouldReceive( 'get_mail_environment_status' )->andReturn(
			array(
				'has_delivery' => true,
				'has_logger'   => false,
				'delivery'     => array(
					'source'     => 'smtp',
					'detail'     => 'SMTP',
					'confidence' => 'high',
				),
				'logger'       => array(
					'source'     => '',
					'detail'     => '',
					'confidence' => '',
				),
			)
		);

		return $environment;
	}
}
