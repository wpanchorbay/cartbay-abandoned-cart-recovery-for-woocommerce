<?php
/**
 * Test mode REST route.
 *
 * @package WPAnchorBay\CartBay\Api\Routes
 */

namespace WPAnchorBay\CartBay\Api\Routes;

use WPAnchorBay\CartBay\Core\Settings;
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

		if ( ! Settings::is_test_mode_enabled() ) {
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

		// Add a real product so the recovery email's restore link rebuilds a
		// genuine cart. A `_cartbay_cart_snapshot` is written alongside the line
		// item so restore takes the same snapshot path a real captured cart does
		// (RestoreService::get_restore_items()), not the order-item fallback.
		$sample_product = $this->get_sample_product();
		if ( $sample_product instanceof \WC_Product ) {
			$session->add_product( $sample_product, 1 );
			$session->calculate_totals( false );
			$session->update_meta_data( '_cartbay_cart_snapshot', $this->build_test_snapshot( $sample_product ) );
		} else {
			Logger::warning(
				'Test flow: no purchasable product found; the restore link will be empty.',
				array(),
				'test'
			);
		}

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

	/**
	 * Find a purchasable, in-stock product to seed the test cart.
	 *
	 * The recovery email's restore link only rebuilds a cart when the session
	 * has a restorable line item, so the test flow needs a real product. Only
	 * directly purchasable products qualify, which skips variable parents (a
	 * variation, not its parent, is what a shopper buys) and returns the first
	 * simple purchasable, in-stock product instead.
	 *
	 * @since 1.0.1
	 *
	 * @return \WC_Product|null A restorable product, or null when the store has none.
	 */
	private function get_sample_product(): ?\WC_Product {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return null;
		}

		$products = wc_get_products(
			array(
				'status'       => 'publish',
				'stock_status' => 'instock',
				'limit'        => 20,
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		foreach ( $products as $product ) {
			if ( $product instanceof \WC_Product && $product->is_purchasable() && $product->is_in_stock() ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Build a cart snapshot for the test session.
	 *
	 * Mirrors the item shape produced by
	 * CaptureService::build_server_cart_snapshot() closely enough for the
	 * restore path — RestoreService reads product_id/variation_id/quantity plus
	 * line_subtotal (for price-change detection) — so the test flow exercises
	 * the production snapshot path rather than the order-item fallback.
	 *
	 * @since 1.0.1
	 *
	 * @param \WC_Product $product Seed product.
	 *
	 * @return array<string, mixed> Cart snapshot.
	 */
	private function build_test_snapshot( \WC_Product $product ): array {
		$price = (float) $product->get_price();

		return array(
			'items'           => array(
				array(
					'product_id'     => $product->get_id(),
					'variation_id'   => 0,
					'quantity'       => 1,
					'variation'      => array(),
					'cart_item_data' => array(),
					'product_name'   => $product->get_name(),
					'line_subtotal'  => $price,
					'line_total'     => $price,
				),
			),
			'currency'        => get_woocommerce_currency(),
			'cart_item_count' => 1,
			'grand_total'     => $price,
			'captured_at'     => time(),
		);
	}
}
