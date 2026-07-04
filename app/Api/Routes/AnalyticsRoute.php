<?php
/**
 * Analytics REST route.
 *
 * @package WPAnchorBay\CartBay\Api\Routes
 */

namespace WPAnchorBay\CartBay\Api\Routes;

use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST route: GET /cartbay/v1/analytics
 *
 * @since 1.0.0
 */
class AnalyticsRoute {

	/**
	 * Analytics service instance.
	 *
	 * @since 1.0.0
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param AnalyticsService $analytics Analytics service instance.
	 */
	public function __construct( AnalyticsService $analytics ) {
		$this->analytics = $analytics;
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
			'/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'days' => array(
						'type'    => 'integer',
						'default' => 30,
						'enum'    => array( 7, 30, 90 ),
					),
				),
			)
		);
	}

	/**
	 * Check that the current user can view analytics.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view analytics.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Handle the analytics request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$days = absint( $request->get_param( 'days' ) );
		Logger::info( 'Analytics API requested.', array( 'days' => $days ), 'analytics' );
		return new WP_REST_Response( $this->analytics->get( $days ), 200 );
	}
}
