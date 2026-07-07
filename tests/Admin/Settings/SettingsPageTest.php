<?php
/**
 * Tests for the CartBay settings page's global SMTP warning notice.
 *
 * @package WPAnchorBay\CartBay\Tests\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Tests\Admin\Settings;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WPAnchorBay\CartBay\Admin\Settings\AdminEnvironment;
use WPAnchorBay\CartBay\Admin\Settings\SettingsPage;
use WPAnchorBay\CartBay\Admin\Settings\SettingsUrl;

/**
 * SettingsPage::show_smtp_warning() gating tests.
 *
 * Covers the fix for a bug where the global SMTP notice fired on every setup
 * wizard step instead of only the Email Delivery step.
 *
 * @since 1.0.0
 */
class SettingsPageTest extends TestCase {
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

		$_GET = array();

		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( mixed $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', is_scalar( $value ) ? (string) $value : '' ) ?? '' )
		);
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
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
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The warning should render on the wizard's Email Delivery step.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_warning_shows_on_wizard_email_step(): void {
		$_GET = array(
			'page' => 'cartbay-wizard',
			'step' => '3',
		);

		$html = $this->render_smtp_warning( $this->undelivered_environment() );

		self::assertStringContainsString( 'No SMTP plugin detected.', $html );
	}

	/**
	 * The warning should not render on the wizard's other steps, since those
	 * steps don't need a duplicate of the Email Delivery step's own notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_warning_hidden_on_other_wizard_steps(): void {
		foreach ( array( '1', '2', '4' ) as $step ) {
			$_GET = array(
				'page' => 'cartbay-wizard',
				'step' => $step,
			);

			$html = $this->render_smtp_warning( $this->undelivered_environment() );

			self::assertSame( '', $html, "Step {$step} should not show the global SMTP warning." );
		}
	}

	/**
	 * The warning should never render on the CartBay WooCommerce settings tab,
	 * which already shows its own inline delivery-test notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_warning_hidden_on_wc_settings_cartbay_page(): void {
		$_GET = array(
			'page' => 'wc-settings',
			'tab'  => 'cartbay',
		);

		$html = $this->render_smtp_warning( $this->undelivered_environment(), true );

		self::assertSame( '', $html );
	}

	/**
	 * When Pro injects its own wizard step via `cartbay_wizard_steps`, the
	 * Email Delivery step shifts position — the gate must follow the step
	 * key, not a hardcoded step number.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_warning_follows_email_step_when_pro_injects_a_license_step(): void {
		Filters\expectApplied( 'cartbay_wizard_steps' )->once()->andReturnUsing(
			static function ( array $steps ): array {
				// Mirrors cartbay-pro's LicenseSettings::add_wizard_step(): inserted right after 'welcome'.
				$with_license = array( 'welcome' => $steps['welcome'] );
				$with_license['license'] = 'License';
				foreach ( $steps as $key => $label ) {
					if ( 'welcome' !== $key ) {
						$with_license[ $key ] = $label;
					}
				}

				return $with_license;
			}
		);

		// With the injected step, order is: welcome(1), license(2), consent(3), email(4), launch(5).
		$_GET = array(
			'page' => 'cartbay-wizard',
			'step' => '4',
		);

		$html = $this->render_smtp_warning( $this->undelivered_environment() );

		self::assertStringContainsString( 'No SMTP plugin detected.', $html );
	}

	/**
	 * Render show_smtp_warning() and capture its output.
	 *
	 * @since 1.0.0
	 *
	 * @param AdminEnvironment $environment          Admin environment double.
	 * @param bool             $is_wc_settings_cartbay Whether the URL double should report the CartBay settings tab.
	 *
	 * @return string Captured output.
	 */
	private function render_smtp_warning( AdminEnvironment $environment, bool $is_wc_settings_cartbay = false ): string {
		$url = Mockery::mock( SettingsUrl::class );
		$url->shouldReceive( 'is_wc_settings_cartbay_page' )->andReturn( $is_wc_settings_cartbay );

		$settings_page = ( new \ReflectionClass( SettingsPage::class ) )->newInstanceWithoutConstructor();

		$environment_property = new \ReflectionProperty( SettingsPage::class, 'environment' );
		$environment_property->setAccessible( true );
		$environment_property->setValue( $settings_page, $environment );

		$url_property = new \ReflectionProperty( SettingsPage::class, 'url' );
		$url_property->setAccessible( true );
		$url_property->setValue( $settings_page, $url );

		ob_start();
		$settings_page->show_smtp_warning();

		return (string) ob_get_clean();
	}

	/**
	 * Create an AdminEnvironment double reporting no mail delivery detected.
	 *
	 * @since 1.0.0
	 *
	 * @return AdminEnvironment Admin environment double.
	 */
	private function undelivered_environment(): AdminEnvironment {
		$environment = Mockery::mock( AdminEnvironment::class );
		$environment->shouldReceive( 'is_cartbay_admin_page' )->andReturn( true );
		$environment->shouldReceive( 'get_mail_environment_status' )->andReturn(
			array(
				'has_delivery' => false,
				'has_logger'   => false,
				'delivery'     => array(
					'source'     => '',
					'detail'     => '',
					'confidence' => '',
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
