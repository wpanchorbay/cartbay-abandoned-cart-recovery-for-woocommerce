<?php
/**
 * Test email REST route.
 *
 * @package WPAnchorBay\CartBay\Api\Routes
 */

namespace WPAnchorBay\CartBay\Api\Routes;

use WPAnchorBay\CartBay\Utils\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST route: POST /cartbay/v1/test/email
 *
 * Sends a simple test email to the admin address.
 *
 * @since 1.0.0
 */
class TestEmailRoute {

	/**
	 * Register the REST route.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			'cartbay/v1',
			'/test/email',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check that the current user can send test emails.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to send test emails.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Send email through a callable, capturing the real wp_mail_failed reason.
	 *
	 * WordPress fires `wp_mail_failed` synchronously, with the underlying
	 * PHPMailer/SMTP error message, before `wp_mail()` returns false. Capturing
	 * it here lets the caller surface a specific reason instead of a generic
	 * failure message.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $sender Callable that triggers a `wp_mail()`-backed send and returns bool.
	 *
	 * @return array{0: bool, 1: string} Whether the send succeeded, and the captured failure reason.
	 */
	private function attempt_send( callable $sender ): array {
		$reason  = '';
		$capture = function ( WP_Error $error ) use ( &$reason ) {
			$reason = $error->get_error_message();
		};

		add_action( 'wp_mail_failed', $capture );

		try {
			$sent = (bool) $sender();
		} finally {
			remove_action( 'wp_mail_failed', $capture );
		}

		return array( $sent, $reason );
	}

	/**
	 * Handle the test email request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		Logger::info( 'Test email API requested.', array(), 'test' );

		$request_email = $request->get_param( 'email' );
		if ( is_string( $request_email ) && '' !== $request_email ) {
			$target_email = sanitize_email( $request_email );
		} else {
			$target_email = sanitize_email( (string) wp_get_current_user()->user_email );
			if ( '' === $target_email ) {
				$target_email = sanitize_email( (string) get_option( 'admin_email' ) );
			}
		}

		if ( empty( $target_email ) ) {
			Logger::error( 'Test email API failed: target email unavailable.', array(), 'test' );
			return new WP_Error(
				'no_email',
				__( 'No email address found or provided.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		$has_step   = $request->has_param( 'step' );
		$step_index = absint( $request->get_param( 'step' ) );

		if ( $has_step && $step_index <= 2 ) {
			$email_class_map = array(
				0 => 'CartBay_Email_Recovery_1',
				1 => 'CartBay_Email_Recovery_2',
				2 => 'CartBay_Email_Recovery_3',
			);
			$email_class     = $email_class_map[ $step_index ] ?? '';
			$emails          = WC()->mailer()->get_emails();

			if ( '' !== $email_class && isset( $emails[ $email_class ] ) && method_exists( $emails[ $email_class ], 'send_preview' ) ) {
				list( $sent, $reason ) = $this->attempt_send(
					static fn (): bool => (bool) $emails[ $email_class ]->send_preview( $target_email )
				);

				if ( ! $sent ) {
					Logger::error(
						'Test email API failed to send preview email.',
						array(
							'step'   => $step_index,
							'reason' => $reason,
						),
						'test'
					);
					return new WP_Error(
						'email_failed',
						__( 'Failed to send the preview email. Check your WordPress email configuration.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
						array(
							'status' => 500,
							'reason' => $reason,
						)
					);
				}

				Logger::info( 'Test email API sent preview email.', array( 'step' => $step_index ), 'test' );

				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => sprintf(
							/* translators: %1$d: step number, %2$s: recipient email */
							__( 'Preview email for step %1$d sent to %2$s.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
							$step_index + 1,
							$target_email
						),
					),
					200
				);
			}
		}

		$subject = __( 'CartBay Test Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$message = __( 'This is a test email from CartBay. If you received this, your email delivery is working correctly.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );

		list( $sent, $reason ) = $this->attempt_send(
			static fn (): bool => wp_mail( $target_email, $subject, $message )
		);

		if ( ! $sent ) {
			Logger::error( 'Test email API failed to send basic test email.', array( 'reason' => $reason ), 'test' );
			return new WP_Error(
				'email_failed',
				__( 'Failed to send test email. Check your WordPress email configuration.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array(
					'status' => 500,
					'reason' => $reason,
				)
			);
		}

		Logger::info( 'Test email API sent basic test email.', array(), 'test' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: recipient email address */
					__( 'Test email sent to %s. Check your inbox.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					$target_email
				),
			),
			200
		);
	}
}
