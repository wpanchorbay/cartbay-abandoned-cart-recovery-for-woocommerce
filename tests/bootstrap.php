<?php
/**
 * PHPUnit bootstrap for CartBay tests.
 *
 * @package WPAnchorBay\CartBay\Tests
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/../' );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'CARTBAY_DOCS_URL' ) || define( 'CARTBAY_DOCS_URL', 'https://docs.wpanchorbay.com/cartbay' );
defined( 'CARTBAY_DOCS_EMAIL_SETUP_URL' ) || define( 'CARTBAY_DOCS_EMAIL_SETUP_URL', 'https://docs.wpanchorbay.com/cartbay/getting-started/email-delivery-setup/' );
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
		 * Error data.
		 *
		 * @since 1.0.0
		 *
		 * @var mixed
		 */
		private mixed $data;

		/**
		 * Create a test error.
		 *
		 * @since 1.0.0
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( string $code, string $message = '', mixed $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
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

		/**
		 * Return the error data.
		 *
		 * @since 1.0.0
		 *
		 * @return mixed Error data.
		 */
		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal WP_REST_Response implementation for unit tests.
	 *
	 * @since 1.0.0
	 */
	class WP_REST_Response {
		/**
		 * Response data.
		 *
		 * @since 1.0.0
		 *
		 * @var array<string, mixed>
		 */
		private array $data;

		/**
		 * HTTP status code.
		 *
		 * @since 1.0.0
		 *
		 * @var int
		 */
		private int $status;

		/**
		 * Create a test REST response.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data   Response data.
		 * @param int                  $status HTTP status code.
		 */
		public function __construct( array $data = array(), int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Return the response data.
		 *
		 * @since 1.0.0
		 *
		 * @return array<string, mixed> Response data.
		 */
		public function get_data(): array {
			return $this->data;
		}

		/**
		 * Return the HTTP status code.
		 *
		 * @since 1.0.0
		 *
		 * @return int HTTP status code.
		 */
		public function get_status(): int {
			return $this->status;
		}
	}
}
