<?php
/**
 * Capture REST route.
 *
 * @package WPAnchorBay\CartBay\Api\Routes
 */

namespace WPAnchorBay\CartBay\Api\Routes;

use WPAnchorBay\CartBay\Core\Settings;
use WPAnchorBay\CartBay\Recovery\CaptureService;
use WPAnchorBay\CartBay\Utils\Logger;
use WPAnchorBay\CartBay\Utils\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST route: capture a cart session.
 *
 * @since 1.0.0
 */
class CaptureRoute {

	/**
	 * Capture service instance.
	 *
	 * @since 1.0.0
	 *
	 * @var CaptureService
	 */
	private CaptureService $capture_service;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param CaptureService $capture_service Capture service.
	 */
	public function __construct( CaptureService $capture_service ) {
		$this->capture_service = $capture_service;
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
			'/capture',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				// Intentionally public. This endpoint lets an anonymous guest opt
				// their OWN checkout cart into recovery emails during guest
				// checkout, where there is no logged-in user to authorize — the
				// same model as the WooCommerce Store API cart routes, which are
				// also public. It is therefore not gated by a capability or a
				// nonce (a REST nonce is not meaningful authorization for a
				// logged-out shopper). Instead the endpoint protects the DATA:
				// creating a session requires a genuine server-side WooCommerce
				// cart (the client-supplied cart is not trusted, so a request
				// carrying no active cart cannot enroll anything) plus explicit
				// consent and a valid email; updating or deleting an existing
				// session requires a per-session capability token issued to the
				// shopper at create time and verified with hash_equals (a bearer
				// secret, like the Store API cart token — a supplied email is NOT
				// accepted as proof of ownership); and every request is per-IP
				// rate limited. See handle() and CaptureService.
				'permission_callback' => '__return_true',
				'args'                => array(
					'email'         => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'default'           => '',
					),
					'consent'       => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'cart'          => array(
						'required' => true,
						'type'     => 'object',
					),
					'source'        => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'classic', 'block' ),
					),
					'session_id'    => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'capture_token' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
				),
			)
		);
	}

	/**
	 * Verify the request originated from a CartBay storefront page (CSRF guard).
	 *
	 * This is defense-in-depth, NOT authorization: the endpoint is public by
	 * design (guest checkout). A wp_rest nonce is printed onto the checkout page
	 * and sent back with the request; verifying it blunts blind off-site POSTs
	 * without pretending to authenticate the shopper. The meaningful protection
	 * is in the handler and capture service (consent, server-side cart, a
	 * per-session capability token for mutations, rate limiting).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request data.
	 *
	 * @return bool True when the request carries a valid same-origin nonce.
	 */
	private function has_valid_origin( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		return is_string( $nonce ) && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Handle the capture request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full request data.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// CSRF/origin guard (defense-in-depth for this public endpoint).
		if ( ! $this->has_valid_origin( $request ) ) {
			return new WP_Error(
				'cartbay_invalid_origin',
				__( 'Invalid or expired request. Please reload the checkout page and try again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		// Rate limit check.
		if ( ! RateLimiter::check( 'capture' ) ) {
			Logger::warning( 'Capture API rate limit exceeded.', array( 'endpoint' => 'capture' ), 'capture' );
			$response = new WP_REST_Response(
				array(
					'code'    => 'rate_limited',
					'message' => __( 'Too many requests. Please try again later.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					'data'    => array( 'status' => 429 ),
				),
				429
			);
			$response->header( 'Retry-After', '600' );

			return $response;
		}

		$email         = sanitize_email( $request->get_param( 'email' ) );
		$consent       = (bool) $request->get_param( 'consent' );
		$source        = sanitize_text_field( $request->get_param( 'source' ) );
		$cart          = (array) $request->get_param( 'cart' );
		$session_id    = absint( $request->get_param( 'session_id' ) );
		$capture_token = sanitize_text_field( (string) $request->get_param( 'capture_token' ) );

		if ( ! $consent ) {
			Logger::info( 'Capture API consent withdrawal received.', array( 'session_id' => $session_id ), 'capture' );

			// Deletion mutates an existing session, so it requires proof of
			// ownership: the per-session capability token issued at create time.
			// A bare session_id (a guessable order ID) or a supplied email is not
			// accepted. Shoppers who have left checkout withdraw consent through
			// the tokenised unsubscribe link carried by every recovery email.
			if ( $session_id <= 0 || '' === $capture_token ) {
				Logger::error( 'Capture API consent withdrawal rejected: missing session or token.', array( 'session_id' => $session_id ), 'capture' );
				return new WP_Error(
					'cartbay_invalid_origin',
					__( 'Invalid or expired request. Please reload the checkout page and try again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					array( 'status' => 403 )
				);
			}

			$deleted = $this->capture_service->delete_after_consent_withdrawal( $session_id, $capture_token );

			return new WP_REST_Response(
				array(
					'success' => true,
					'deleted' => $deleted,
				),
				200
			);
		}

		if ( ! is_email( $email ) ) {
			Logger::error( 'Capture API rejected invalid email.', array(), 'capture' );
			return new WP_Error(
				'invalid_email',
				__( 'Invalid email address.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 422 )
			);
		}

		if ( ! Settings::is_capture_enabled() ) {
			Logger::info( 'Capture API ignored request because capture is disabled.', array(), 'capture' );
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Capture disabled.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				),
				200
			);
		}

		$result = $this->capture_service->capture( $email, $cart, $source, $session_id, $capture_token );

		if ( is_wp_error( $result ) ) {
			Logger::error(
				'Capture API failed to capture session.',
				array(
					'code' => $result->get_error_code(),
				),
				'capture'
			);
			$status = $result->get_error_data()['status'] ?? 400;

			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		$response = array(
			'success'    => true,
			'session_id' => absint( $result['session_id'] ?? 0 ),
		);

		// Return the ownership token only when one was issued (create). The client
		// stores it and must present it to later update or delete this session; it
		// is never returned again on update.
		if ( ! empty( $result['capture_token'] ) ) {
			$response['capture_token'] = (string) $result['capture_token'];
		}

		return new WP_REST_Response( $response, 201 );
	}
}
