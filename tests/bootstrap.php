<?php
/**
 * PHPUnit bootstrap for CartBay tests.
 *
 * @package WPAnchorBay\CartBay\Tests
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/../' );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'CARTBAY_DOCS_URL' ) || define( 'CARTBAY_DOCS_URL', 'https://docs.wpanchorbay.com/cartbay' );
defined( 'CARTBAY_SETTINGS_URL' ) || define( 'CARTBAY_SETTINGS_URL', 'admin.php?page=wc-settings&tab=cartbay&section=settings' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error implementation for unit tests.
	 *
	 * @since 1.0.0
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private string $code;

		/**
		 * Error message.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Create a test error.
		 *
		 * @since 1.0.0
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code, string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Return the error code.
		 *
		 * @since 1.0.0
		 *
		 * @return string Error code.
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Return the error message.
		 *
		 * @since 1.0.0
		 *
		 * @return string Error message.
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}
