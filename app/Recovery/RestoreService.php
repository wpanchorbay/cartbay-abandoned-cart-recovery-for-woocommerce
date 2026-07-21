<?php
/**
 * Restore service.
 *
 * @package WPAnchorBay\CartBay\Recovery
 */

namespace WPAnchorBay\CartBay\Recovery;

use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Data\SessionRepository;
use WPAnchorBay\CartBay\Utils\Logger;
use WPAnchorBay\CartBay\Utils\RateLimiter;
use WPAnchorBay\CartBay\Utils\TokenHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Handles secure cart restore link validation and cart rebuild.
 *
 * @since 1.0.0
 */
class RestoreService {

	/**
	 * Session repository instance.
	 *
	 * @since 1.0.0
	 *
	 * @var SessionRepository
	 */
	private SessionRepository $sessions;

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
	 * @param SessionRepository   $sessions      Session repository instance.
	 * @param NotificationService $notifications Notification tracking service.
	 */
	public function __construct( SessionRepository $sessions, NotificationService $notifications ) {
		$this->sessions      = $sessions;
		$this->notifications = $notifications;
	}

	/**
	 * Handle a restore request from URL token.
	 *
	 * Validates the token, rebuilds the cart with the session's items,
	 * and redirects to checkout.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Plain token from URL.
	 *
	 * @return void Redirects on success or failure.
	 */
	public function handle( string $token ): void {
		$token = sanitize_text_field( $token );

		// Public endpoint: throttle restore attempts per IP to blunt
		// brute-force and resource amplification against the token lookup.
		if ( ! RateLimiter::check( 'restore' ) ) {
			Logger::warning( 'Restore link rate limit exceeded.', array(), 'restore' );
			$this->redirect_with_error( 'rate_limited' );
			return;
		}

		if ( ! $this->ensure_cart_ready() ) {
			$this->redirect_with_error( 'invalid' );
			return;
		}

		// Find session by token hash.
		$token_hash = TokenHelper::hash( $token );
		$session    = $this->find_session_by_restore_token_hash( $token_hash );

		if ( ! $session ) {
			$this->redirect_with_error( 'invalid' );
			return;
		}

		if ( ! TokenHelper::validate_restore_token( $session, $token ) ) {
			$this->redirect_with_error( 'expired' );
			return;
		}

		$items = $this->get_restore_items( $session );

		// Update last activity timestamp.
		$session->update_meta_data( '_cartbay_last_activity_at', time() );
		$session->update_meta_data( '_cartbay_restore_clicked_at', time() );
		$session->save();

		$notification_id = $this->notifications->latest_sent_notification_id( $session->get_id() );
		$this->sessions->add_event(
			$session->get_id(),
			'restore_clicked',
			array(
				'notification_id' => $notification_id,
			)
		);
		$this->store_restore_identity( $session->get_id(), $token_hash, $notification_id );
		AnalyticsService::invalidate_cache();

		if ( empty( $items ) ) {
			$this->record_restore_result( $session->get_id(), 0, 0, 0, array() );
			$this->redirect_with_error( 'empty' );
			return;
		}

		// Rebuild cart.
		WC()->cart->empty_cart();
		$this->sessions->add_event( $session->get_id(), 'cart_restore_started' );
		$added       = 0;
		$failed      = 0;
		$failures    = array();
		$total_items = count( $items );

		foreach ( $items as $item ) {
			$result = $this->restore_item( $item );
			if ( true === $result ) {
				++$added;
				continue;
			}

			++$failed;
			$failures[] = $result;
		}

		$this->record_restore_result( $session->get_id(), $total_items, $added, $failed, $failures );

		// Pre-fill the checkout billing email from the restored session.
		$session_email = $session->get_billing_email();
		if ( ! empty( $session_email ) && function_exists( 'WC' ) && WC()->customer ) {
			WC()->customer->set_billing_email( $session_email );
		}

		/**
		 * Fires after a restored cart is rebuilt, before redirecting to checkout.
		 *
		 * Free does not auto-apply discounts — it uses a static coupon the
		 * shopper enters manually. CartBay Pro hooks here to auto-apply the
		 * session's generated coupon.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order $session The restored CartBay session order.
		 */
		do_action( 'cartbay_restore_apply_discounts', $session );

		if ( $added > 0 ) {
			wc_add_notice( __( 'Your saved cart has been restored.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'success' );
		}

		Logger::info(
			'Restore link clicked and cart rebuilt.',
			array( 'session_id' => $session->get_id() ),
			'restore'
		);

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Ensure WooCommerce cart and session are available before restore.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when cart operations are available.
	 */
	private function ensure_cart_ready(): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		if ( null === WC()->session && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		return (bool) WC()->cart;
	}

	/**
	 * Find a session by current or historical restore token hash.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token_hash Restore token hash.
	 *
	 * @return \WC_Order|null Matching session.
	 */
	private function find_session_by_restore_token_hash( string $token_hash ): ?\WC_Order {
		$session = $this->sessions->find_by_token_hash( $token_hash );
		if ( $session ) {
			return $session;
		}

		$candidates = wc_get_orders(
			array(
				'status' => array( 'wc-cartbay-captured', 'wc-cartbay-abandoned', 'wc-cartbay-recovered', 'wc-cartbay-suppressed' ),
				'limit'  => 100,
				'return' => 'objects',
			)
		);

		foreach ( $candidates as $candidate ) {
			if ( ! $candidate instanceof \WC_Order ) {
				continue;
			}

			$tokens = $candidate->get_meta( '_cartbay_token_hashes', true );
			if ( ! is_array( $tokens ) ) {
				continue;
			}

			foreach ( $tokens as $token_data ) {
				if ( is_array( $token_data ) && hash_equals( sanitize_text_field( (string) ( $token_data['hash'] ?? '' ) ), $token_hash ) ) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Get restore items from snapshot meta with order-item fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $session CartBay session order.
	 *
	 * @return array<int, array<string, mixed>> Restore items.
	 */
	private function get_restore_items( \WC_Order $session ): array {
		$snapshot = $session->get_meta( '_cartbay_cart_snapshot', true );
		if ( is_array( $snapshot ) && isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ) {
			return array_values( array_filter( $snapshot['items'], 'is_array' ) );
		}

		$items = array();
		foreach ( $session->get_items() as $item ) {
			$variation = array();
			foreach ( $item->get_meta_data() as $meta ) {
				$key = sanitize_key( (string) $meta->key );
				if ( str_starts_with( $key, 'pa_' ) || str_starts_with( $key, 'attribute_' ) ) {
					$variation[ $key ] = sanitize_text_field( (string) $meta->value );
				}
			}

			$items[] = array(
				'product_id'     => $item->get_product_id(),
				'variation_id'   => $item->get_variation_id(),
				'quantity'       => $item->get_quantity(),
				'variation'      => $variation,
				'cart_item_data' => array(),
			);
		}

		return $items;
	}

	/**
	 * Restore a single cart item.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $item Restore item.
	 *
	 * @return true|array<string, mixed> True on success or safe failure details.
	 */
	private function restore_item( array $item ): bool|array {
		$product_id   = absint( $item['product_id'] ?? 0 );
		$variation_id = absint( $item['variation_id'] ?? 0 );
		$restore_id   = $variation_id > 0 ? $variation_id : $product_id;
		$product      = wc_get_product( $restore_id );

		if ( ! $product ) {
			return $this->item_failure( $product_id, $variation_id, 'missing_product' );
		}

		if ( ! $product->is_purchasable() ) {
			return $this->item_failure( $product_id, $variation_id, 'not_purchasable' );
		}

		if ( ! $product->is_in_stock() ) {
			return $this->item_failure( $product_id, $variation_id, 'out_of_stock' );
		}

		$quantity       = max( 1, absint( $item['quantity'] ?? 1 ) );
		$variation      = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $this->sanitize_restore_array( $item['variation'] ) : array();
		$cart_item_data = isset( $item['cart_item_data'] ) && is_array( $item['cart_item_data'] ) ? $this->sanitize_restore_array( $item['cart_item_data'] ) : array();

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );

		return $cart_item_key ? true : $this->item_failure( $product_id, $variation_id, 'add_to_cart_failed' );
	}

	/**
	 * Build safe restore failure details.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID.
	 * @param string $reason       Safe reason code.
	 *
	 * @return array<string, mixed> Failure details.
	 */
	private function item_failure( int $product_id, int $variation_id, string $reason ): array {
		return array(
			'product_id'   => absint( $product_id ),
			'variation_id' => absint( $variation_id ),
			'reason'       => sanitize_key( $reason ),
		);
	}

	/**
	 * Record restore outcome and shopper notices.
	 *
	 * @since 1.0.0
	 *
	 * @param int               $session_id  Session order ID.
	 * @param int               $total_items Total restore item count.
	 * @param int               $added       Added item count.
	 * @param int               $failed      Failed item count.
	 * @param array<int, mixed> $failures    Safe failure details.
	 *
	 * @return void
	 */
	private function record_restore_result( int $session_id, int $total_items, int $added, int $failed, array $failures ): void {
		$session = $this->sessions->get( $session_id );
		if ( $session ) {
			$session->update_meta_data(
				'_cartbay_restore_result',
				array(
					'total_items' => absint( $total_items ),
					'added'       => absint( $added ),
					'failed'      => absint( $failed ),
					'failures'    => $failures,
					'recorded_at' => time(),
				)
			);
			$session->save();
		}

		if ( 0 === $total_items || 0 === $added ) {
			$this->sessions->add_event( $session_id, 'cart_restore_failed', array( 'failures' => $failures ) );
			Logger::error(
				'Cart restore failed.',
				array(
					'session_id' => $session_id,
					'failures'   => $failures,
				),
				'restore'
			);
			wc_add_notice( __( 'We could not restore the items from this recovery link. Please add them to your cart again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'error' );
			return;
		}

		if ( $failed > 0 ) {
			$this->sessions->add_event(
				$session_id,
				'cart_restore_partial',
				array(
					'added'    => $added,
					'failed'   => $failed,
					'failures' => $failures,
				)
			);
			Logger::warning(
				'Cart restore completed partially.',
				array(
					'session_id' => $session_id,
					'added'      => $added,
					'failed'     => $failed,
					'failures'   => $failures,
				),
				'restore'
			);
			wc_add_notice( __( 'Some items from your saved cart are no longer available and could not be restored.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'notice' );
			return;
		}

		$this->sessions->add_event( $session_id, 'cart_restored', array( 'added' => $added ) );
	}

	/**
	 * Store restore identity in the WooCommerce session for checkout attribution.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param string $token_hash      Restore token hash.
	 * @param string $notification_id Notification ID.
	 *
	 * @return void
	 */
	private function store_restore_identity( int $session_id, string $token_hash, string $notification_id ): void {
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->set( 'cartbay_restored_session_id', $session_id );
		WC()->session->set( 'cartbay_restore_token_hash', $token_hash );
		WC()->session->set( 'cartbay_notification_id', $notification_id );
		$session = $this->sessions->get( $session_id );
		WC()->session->set( 'cartbay_restored_email', $session ? sanitize_email( $session->get_billing_email() ) : '' );
	}

	/**
	 * Sanitize restore item arrays recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $value Raw array.
	 *
	 * @return array<mixed> Sanitized array.
	 */
	private function sanitize_restore_array( array $value ): array {
		$sanitized = array();
		foreach ( $value as $key => $item ) {
			$sanitized_key               = is_string( $key ) ? sanitize_key( $key ) : $key;
			$sanitized[ $sanitized_key ] = is_array( $item ) ? $this->sanitize_restore_array( $item ) : sanitize_text_field( (string) $item );
		}

		return $sanitized;
	}

	/**
	 * Redirect with an error query parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $reason Error reason ('invalid' or 'expired').
	 *
	 * @return void
	 */
	private function redirect_with_error( string $reason ): void {
		$url = add_query_arg( 'cartbay_restore_error', $reason, home_url( '/' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
