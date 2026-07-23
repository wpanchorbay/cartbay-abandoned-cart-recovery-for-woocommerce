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

		// Idempotency: if this exact recovery link already rebuilt the cart in
		// this browser session, don't record another click or re-add items —
		// just return the shopper to checkout. Prevents a double-click (or an
		// impatient reload) from doubling quantities now that restore merges.
		if ( function_exists( 'WC' ) && WC()->session
			&& hash_equals( (string) WC()->session->get( 'cartbay_restore_token_hash' ), $token_hash ) ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
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
			$this->sessions->add_event( $session->get_id(), 'cart_restore_failed', array( 'reason' => 'empty' ) );
			$this->redirect_with_error( 'empty' );
			return;
		}

		// Rebuild the cart by merging saved items into the live cart rather than
		// emptying it, so a shopper who is mid-purchase keeps what they already
		// added. add_to_cart() merges quantities for identical items.
		$cart_was_empty = WC()->cart->is_empty();
		$this->sessions->add_event( $session->get_id(), 'cart_restore_started' );

		$results = array();
		foreach ( $items as $item ) {
			$results[] = $this->restore_item( $item );
		}

		$this->record_restore_result( $session->get_id(), $results, $cart_was_empty );

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
	 * Restore a single cart item, clamping quantity to available stock.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $item Restore item.
	 *
	 * @return array<string, mixed> Structured restore outcome (see item_result()).
	 */
	private function restore_item( array $item ): array {
		$product_id   = absint( $item['product_id'] ?? 0 );
		$variation_id = absint( $item['variation_id'] ?? 0 );
		$requested    = max( 1, absint( $item['quantity'] ?? 1 ) );
		$restore_id   = $variation_id > 0 ? $variation_id : $product_id;
		$product      = wc_get_product( $restore_id );

		if ( ! $product ) {
			return $this->item_result( 'failed', $product_id, $variation_id, '', $requested, 0, 'missing_product', false );
		}

		$name = $product->get_name();

		if ( ! $product->is_purchasable() ) {
			return $this->item_result( 'failed', $product_id, $variation_id, $name, $requested, 0, 'not_purchasable', false );
		}

		if ( ! $product->is_in_stock() ) {
			return $this->item_result( 'failed', $product_id, $variation_id, $name, $requested, 0, 'out_of_stock', false );
		}

		// Clamp to available stock so a shopper who saved 5 (of which only 2
		// remain) still gets the 2, instead of add_to_cart() rejecting the line.
		$quantity = $requested;
		if ( $product->managing_stock() && ! $product->backorders_allowed() ) {
			$available = $product->get_stock_quantity();
			if ( null !== $available && $available < $quantity ) {
				$quantity = max( 0, (int) $available );
			}
		}

		if ( $quantity < 1 ) {
			return $this->item_result( 'failed', $product_id, $variation_id, $name, $requested, 0, 'out_of_stock', false );
		}

		$variation      = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $this->sanitize_restore_array( $item['variation'] ) : array();
		$cart_item_data = isset( $item['cart_item_data'] ) && is_array( $item['cart_item_data'] ) ? $this->sanitize_restore_array( $item['cart_item_data'] ) : array();

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );

		if ( ! $cart_item_key ) {
			return $this->item_result( 'failed', $product_id, $variation_id, $name, $requested, 0, 'add_to_cart_failed', false );
		}

		$is_partial = $quantity < $requested;

		return $this->item_result(
			$is_partial ? 'partial' : 'added',
			$product_id,
			$variation_id,
			$name,
			$requested,
			$quantity,
			$is_partial ? 'limited_stock' : '',
			$this->item_price_changed( $item, $product, $requested )
		);
	}

	/**
	 * Build a structured restore outcome for one item.
	 *
	 * @since 1.1.0
	 *
	 * @param string $status        'added', 'partial', or 'failed'.
	 * @param int    $product_id    Product ID.
	 * @param int    $variation_id  Variation ID.
	 * @param string $name          Product name (may be empty when unresolved).
	 * @param int    $requested     Quantity the shopper had saved.
	 * @param int    $restored      Quantity actually added to the cart.
	 * @param string $reason        Safe reason code for a partial/failed item.
	 * @param bool   $price_changed Whether the unit price changed since capture.
	 *
	 * @return array<string, mixed> Outcome details.
	 */
	private function item_result( string $status, int $product_id, int $variation_id, string $name, int $requested, int $restored, string $reason, bool $price_changed ): array {
		return array(
			'status'        => sanitize_key( $status ),
			'product_id'    => absint( $product_id ),
			'variation_id'  => absint( $variation_id ),
			'name'          => sanitize_text_field( $name ),
			'requested'     => absint( $requested ),
			'restored'      => absint( $restored ),
			'reason'        => sanitize_key( $reason ),
			'price_changed' => (bool) $price_changed,
		);
	}

	/**
	 * Detect whether an item's unit price changed since it was captured.
	 *
	 * Only snapshot items carry a captured price; order-item fallback restores
	 * (which lack line totals) are treated as unchanged.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $item      Restore item.
	 * @param \WC_Product          $product   Current product.
	 * @param int                  $requested Captured quantity.
	 *
	 * @return bool True when the current unit price differs from the captured one.
	 */
	private function item_price_changed( array $item, \WC_Product $product, int $requested ): bool {
		if ( ! isset( $item['line_subtotal'] ) || $requested < 1 ) {
			return false;
		}

		$captured_unit = (float) $item['line_subtotal'] / $requested;
		$current_unit  = (float) $product->get_price();

		return abs( $captured_unit - $current_unit ) > 0.01;
	}

	/**
	 * Record restore outcome and surface shopper notices.
	 *
	 * @since 1.0.0
	 *
	 * @param int                              $session_id     Session order ID.
	 * @param array<int, array<string, mixed>> $results        Per-item restore outcomes.
	 * @param bool                             $cart_was_empty Whether the live cart was empty before restore.
	 *
	 * @return void
	 */
	private function record_restore_result( int $session_id, array $results, bool $cart_was_empty ): void {
		$added_count   = 0;
		$partial_items = array();
		$failed_items  = array();
		$price_changed = false;

		foreach ( $results as $result ) {
			if ( ! empty( $result['price_changed'] ) ) {
				$price_changed = true;
			}

			$status = isset( $result['status'] ) ? (string) $result['status'] : 'failed';
			if ( 'added' === $status ) {
				++$added_count;
			} elseif ( 'partial' === $status ) {
				$partial_items[] = $result;
			} else {
				$failed_items[] = $result;
			}
		}

		$total   = count( $results );
		$added   = $added_count + count( $partial_items );
		$failed  = count( $failed_items );
		$session = $this->sessions->get( $session_id );

		if ( $session ) {
			$session->update_meta_data(
				'_cartbay_restore_result',
				array(
					'total_items' => absint( $total ),
					'added'       => absint( $added ),
					'partial'     => absint( count( $partial_items ) ),
					'failed'      => absint( $failed ),
					'results'     => $results,
					'recorded_at' => time(),
				)
			);
			$session->save();
		}

		if ( 0 === $added ) {
			$this->sessions->add_event( $session_id, 'cart_restore_failed', array( 'results' => $results ) );
			Logger::error(
				'Cart restore failed.',
				array(
					'session_id' => $session_id,
					'results'    => $results,
				),
				'restore'
			);

			$names = $this->format_item_names( $failed_items );
			wc_add_notice(
				'' !== $names
					? sprintf(
						/* translators: %s: comma-separated product names. */
						__( 'We could not restore these items from your recovery link: %s. Please add them to your cart again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
						$names
					)
					: __( 'We could not restore the items from this recovery link. Please add them to your cart again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'error'
			);
			return;
		}

		wc_add_notice(
			$cart_was_empty
				? __( 'Your saved cart has been restored.', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
				: __( 'We added your saved items to your cart.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'success'
		);

		if ( ! empty( $partial_items ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: comma-separated product names. */
					__( 'We could only restore part of your saved quantity for: %s (limited stock).', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					$this->format_item_names( $partial_items )
				),
				'notice'
			);
		}

		if ( ! empty( $failed_items ) ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: comma-separated product names. */
					__( 'Some saved items are no longer available and could not be restored: %s.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					$this->format_item_names( $failed_items )
				),
				'notice'
			);
		}

		if ( $price_changed ) {
			wc_add_notice( __( 'Some prices have changed since you saved your cart.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'notice' );
		}

		if ( ! empty( $partial_items ) || ! empty( $failed_items ) ) {
			$this->sessions->add_event(
				$session_id,
				'cart_restore_partial',
				array(
					'added'   => $added,
					'partial' => count( $partial_items ),
					'failed'  => $failed,
					'results' => $results,
				)
			);
			Logger::warning(
				'Cart restore completed partially.',
				array(
					'session_id' => $session_id,
					'added'      => $added,
					'failed'     => $failed,
					'results'    => $results,
				),
				'restore'
			);
			return;
		}

		$this->sessions->add_event(
			$session_id,
			'cart_restored',
			array(
				'added'         => $added,
				'price_changed' => $price_changed,
			)
		);
	}

	/**
	 * Format up to a few product names for a shopper notice.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $items Restore outcomes.
	 *
	 * @return string Comma-separated names, or '' when none are known.
	 */
	private function format_item_names( array $items ): string {
		$names = array();
		foreach ( $items as $item ) {
			$name = isset( $item['name'] ) ? trim( (string) $item['name'] ) : '';
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		$names = array_values( array_unique( $names ) );
		if ( empty( $names ) ) {
			return '';
		}

		$max = 5;
		if ( count( $names ) > $max ) {
			$extra   = count( $names ) - $max;
			$names   = array_slice( $names, 0, $max );
			$names[] = sprintf(
				/* translators: %d: number of additional items. */
				_n( '%d more', '%d more', $extra, 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				$extra
			);
		}

		return implode( ', ', $names );
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
