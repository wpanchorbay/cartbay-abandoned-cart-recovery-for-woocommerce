<?php
/**
 * Test mode REST route.
 *
 * @package WPAnchorBay\CartBay\Api\Routes
 */

namespace WPAnchorBay\CartBay\Api\Routes;

use WPAnchorBay\CartBay\Recovery\NotificationService;
use WPAnchorBay\CartBay\Utils\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST route: POST /cartbay/v1/test/trigger
 *
 * Creates a test abandoned session and schedules a fast recovery email.
 *
 * @since 1.0.0
 */
class TestRoute {

	/**
	 * Notification tracking service.
	 *
	 * @since 1.0.0
	 *
	 * @var NotificationService
	 */
	private NotificationService $notifications;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param NotificationService $notifications Notification tracking service.
	 */
	public function __construct( NotificationService $notifications ) {
		$this->notifications = $notifications;
	}

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
			'/test/trigger',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check that the current user can trigger test flows.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to trigger test flows.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		$settings = get_option( 'cartbay_settings', array() );
		if ( empty( $settings['test_mode'] ) ) {
			return new WP_Error(
				'test_mode_disabled',
				__( 'Test mode is not enabled. Enable it in CartBay Settings → Debug.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Handle the test trigger request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		unset( $request );

		$current_user = wp_get_current_user();
		$admin_email  = $current_user->user_email;

		if ( empty( $admin_email ) ) {
			Logger::error( 'Test flow API failed: admin email unavailable.', array(), 'test' );
			return new WP_Error(
				'no_admin_email',
				__( 'Could not determine admin email address.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		// Create a dummy session.
		$session = wc_create_order();
		$session->set_billing_email( $admin_email );
		$session->set_status( 'wc-cartbay-abandoned' );
		$session->set_created_via( 'cartbay-test' );
		$session->update_meta_data( '_cartbay_email', $admin_email );
		$session->update_meta_data( '_cartbay_consent', 1 );
		$session->update_meta_data( '_cartbay_source', 'test' );
		$session->update_meta_data( '_cartbay_last_activity_at', time() );
		$session->save();

		// Schedule first email step in 30 seconds.
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$scheduled_at = time() + 30;
			as_schedule_single_action( $scheduled_at, 'cartbay_send_recovery_email', array( $session->get_id(), 0 ), 'cartbay' );
			$this->notifications->queue( $session->get_id(), 0, $scheduled_at, 'test_flow' );
		}

		Logger::info(
			'Test flow API triggered.',
			array( 'session_id' => $session->get_id() ),
			'test'
		);

		return new WP_REST_Response(
			array(
				'success'    => true,
				'session_id' => $session->get_id(),
				'message'    => __( 'Test flow triggered. Email will arrive in ~30 seconds.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
			200
		);
	}
}
