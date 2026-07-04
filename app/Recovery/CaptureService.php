<?php
/**
 * Capture service.
 *
 * @package WPAnchorBay\CartBay\Recovery
 */

namespace WPAnchorBay\CartBay\Recovery;

use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Core\Settings;
use WPAnchorBay\CartBay\Data\SessionRepository;
use WPAnchorBay\CartBay\Utils\Logger;
use WPAnchorBay\CartBay\Utils\TokenHelper;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles cart session capture after consent.
 *
 * @since 1.0.0
 */
class CaptureService {

	/**
	 * Session repository instance.
	 *
	 * @since 1.0.0
	 *
	 * @var SessionRepository
	 */
	private SessionRepository $sessions;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SessionRepository $sessions Session repository.
	 */
	public function __construct( SessionRepository $sessions ) {
		$this->sessions = $sessions;
	}

	/**
	 * Capture or update a cart session.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email      Sanitized, validated email.
	 * @param array  $cart_data  Cart snapshot: hash, total, currency.
	 * @param string $source     'classic' or 'block'.
	 * @param int    $session_id Optional existing session ID for update.
	 *
	 * @return int|WP_Error Session ID on success.
	 */
	public function capture( string $email, array $cart_data, string $source, int $session_id = 0 ): int|WP_Error {
		if ( ! Settings::is_capture_enabled() ) {
			return new WP_Error(
				'capture_disabled',
				__( 'Capture is disabled.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		// 1. Check suppression.
		if ( $this->is_suppressed( $email ) ) {
			return new WP_Error( 'suppressed', __( 'Email is suppressed.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		// 2. Get settings.
		$settings       = get_option( 'cartbay_settings', array() );
		$retention_days = absint( $settings['data_retention_days'] ?? 30 );
		$consent_text   = isset( $settings['consent_text'] ) ? sanitize_textarea_field( $settings['consent_text'] ) : '';

		$snapshot = $this->build_server_cart_snapshot();
		if ( ! $this->snapshot_has_items( $snapshot ) ) {
			$snapshot = $this->build_client_cart_snapshot( $cart_data );
		}

		$cart_fingerprint = $this->build_cart_fingerprint( $snapshot );

		// 3. Find existing session by cart identity, not shopper identity alone.
		$existing = null;
		if ( $session_id > 0 ) {
			$order = $this->sessions->get( $session_id );
			if ( $order && $this->can_update_session( $order, $cart_fingerprint, $email ) ) {
				$existing = $order;
			}
		}
		if ( ! $existing ) {
			$existing = $this->sessions->find_active_by_email_and_cart_fingerprint( $email, $cart_fingerprint, $retention_days );
		}

		if ( ! $this->snapshot_has_items( $snapshot ) && $existing instanceof WC_Order ) {
			$existing_snapshot = $existing->get_meta( '_cartbay_cart_snapshot', true );
			if ( is_array( $existing_snapshot ) && $this->snapshot_has_items( $existing_snapshot ) ) {
				$snapshot         = $existing_snapshot;
				$cart_fingerprint = $this->build_cart_fingerprint( $snapshot );
			}
		}

		$meta = array(
			'email'            => $email,
			'consent'          => true,
			'consent_text'     => $consent_text,
			'consent_at'       => time(),
			'source'           => $source,
			'cart_hash'        => sanitize_text_field( $snapshot['cart_hash'] ?? ( $cart_data['hash'] ?? '' ) ),
			'cart_fingerprint' => $cart_fingerprint,
			'cart_total'       => floatval( $snapshot['grand_total'] ?? ( $cart_data['total'] ?? 0 ) ),
			'currency'         => sanitize_text_field( $snapshot['currency'] ?? ( $cart_data['currency'] ?? get_woocommerce_currency() ) ),
			'cart_snapshot'    => $snapshot,
			'cart_item_count'  => absint( $snapshot['cart_item_count'] ?? 0 ),
			'last_activity_at' => time(),
		);

		if ( $existing ) {
			// Update billing email if it changed.
			if ( $email !== $existing->get_billing_email() ) {
				$existing->set_billing_email( $email );
				$existing->save();
			}

			$this->sessions->update( $existing->get_id(), $meta );
			$this->schedule_abandonment_check( $existing->get_id() );
			if ( $this->snapshot_has_items( $snapshot ) ) {
				$this->persist_session_items( $existing, $snapshot );
			}
			$this->sessions->add_event( $existing->get_id(), 'updated', array( 'source' => $source ) );
			AnalyticsService::invalidate_cache();
			Logger::info(
				'Cart session updated.',
				array(
					'session_id' => $existing->get_id(),
					'source'     => $source,
				),
				'capture'
			);

			return $existing->get_id();
		}

		$session_id = $this->sessions->create( $email, $meta );

		if ( is_wp_error( $session_id ) ) {
			return $session_id;
		}

		Logger::info(
			'Cart session created.',
			array(
				'session_id' => $session_id,
				'source'     => $source,
			),
			'capture'
		);

		$this->sessions->add_event( $session_id, 'captured', array( 'source' => $source ) );
		AnalyticsService::invalidate_cache();
		$this->schedule_abandonment_check( $session_id );

		$session = $this->sessions->get( $session_id );
		if ( $session instanceof WC_Order && $this->snapshot_has_items( $snapshot ) ) {
			$this->persist_session_items( $session, $snapshot );
		}

		return $session_id;
	}

	/**
	 * Determine whether a session can be updated by the incoming cart capture.
	 *
	 * A client-supplied session_id is a guessable WooCommerce order ID and must
	 * never authorize a cross-shopper update on its own. The incoming email is
	 * required to match the session's stored email as proof of ownership.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $session          CartBay session order.
	 * @param string   $cart_fingerprint Incoming cart fingerprint.
	 * @param string   $email            Incoming validated shopper email.
	 *
	 * @return bool True when the capture belongs to the same cart session.
	 */
	private function can_update_session( WC_Order $session, string $cart_fingerprint, string $email ): bool {
		// Ownership proof first: reject any session_id whose stored email does
		// not match the request email, defeating order-ID enumeration attacks.
		if ( ! $this->session_email_matches( $session, $email ) ) {
			return false;
		}

		$status = $session->get_status();
		if ( 'cartbay-captured' === $status ) {
			return true;
		}

		if ( 'cartbay-abandoned' !== $status ) {
			return false;
		}

		$stored_fingerprint = sanitize_text_field( (string) $session->get_meta( '_cartbay_cart_fingerprint', true ) );

		return '' !== $cart_fingerprint && hash_equals( $stored_fingerprint, $cart_fingerprint );
	}

	/**
	 * Verify the request email matches the session's stored billing email.
	 *
	 * Used as the ownership proof for unauthenticated capture-time updates and
	 * consent-withdrawal deletes, where a bare session_id (a guessable order ID)
	 * cannot be trusted.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $session CartBay session order.
	 * @param string   $email   Incoming validated shopper email.
	 *
	 * @return bool True when both emails are present and equal (case-insensitive).
	 */
	private function session_email_matches( WC_Order $session, string $email ): bool {
		$request_email = strtolower( sanitize_email( $email ) );
		$stored_email  = strtolower( sanitize_email( (string) $session->get_billing_email() ) );

		if ( '' === $request_email || '' === $stored_email ) {
			return false;
		}

		return hash_equals( $stored_email, $request_email );
	}

	/**
	 * Build a restore-ready cart snapshot from the server-side WooCommerce cart.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Sanitized cart snapshot.
	 */
	private function build_server_cart_snapshot(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'] ?? null;
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$product_id   = absint( $cart_item['product_id'] ?? $product->get_id() );
			$variation_id = absint( $cart_item['variation_id'] ?? 0 );
			$permalink    = $product->is_visible() ? $product->get_permalink() : '';
			$image_id     = absint( $product->get_image_id() );

			$items[] = array(
				'cart_item_key'     => sanitize_key( (string) $cart_item_key ),
				'product_id'        => $product_id,
				'variation_id'      => $variation_id,
				'quantity'          => max( 1, absint( $cart_item['quantity'] ?? 1 ) ),
				'variation'         => $this->sanitize_snapshot_array( $cart_item['variation'] ?? array() ),
				'cart_item_data'    => $this->get_safe_cart_item_data( $cart_item ),
				'product_name'      => sanitize_text_field( $product->get_name() ),
				'sku'               => sanitize_text_field( $product->get_sku() ),
				'permalink'         => esc_url_raw( $permalink ),
				'image_id'          => $image_id,
				'image_url'         => $image_id ? esc_url_raw( (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) ) : '',
				'line_subtotal'     => floatval( $cart_item['line_subtotal'] ?? 0 ),
				'line_subtotal_tax' => floatval( $cart_item['line_subtotal_tax'] ?? 0 ),
				'line_total'        => floatval( $cart_item['line_total'] ?? 0 ),
				'line_tax'          => floatval( $cart_item['line_tax'] ?? 0 ),
				'line_tax_data'     => $this->sanitize_snapshot_array( $cart_item['line_tax_data'] ?? array() ),
				'currency'          => get_woocommerce_currency(),
			);
		}

		return array(
			'items'           => $items,
			'currency'        => get_woocommerce_currency(),
			'applied_coupons' => array_values( array_map( 'sanitize_text_field', WC()->cart->get_applied_coupons() ) ),
			'cart_subtotal'   => (float) WC()->cart->get_subtotal(),
			'discount_total'  => (float) WC()->cart->get_discount_total(),
			'tax_total'       => (float) WC()->cart->get_total_tax(),
			'shipping_total'  => (float) WC()->cart->get_shipping_total(),
			'grand_total'     => (float) WC()->cart->get_total( 'edit' ),
			'cart_hash'       => sanitize_text_field( WC()->cart->get_cart_hash() ),
			'cart_item_count' => absint( WC()->cart->get_cart_contents_count() ),
			'captured_at'     => time(),
		);
	}

	/**
	 * Build a restore-ready snapshot from sanitized client cart data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $cart_data Client cart data.
	 *
	 * @return array<string, mixed> Sanitized cart snapshot.
	 */
	private function build_client_cart_snapshot( array $cart_data ): array {
		$raw_items = isset( $cart_data['items'] ) && is_array( $cart_data['items'] ) ? $cart_data['items'] : array();
		$items     = array();

		foreach ( $raw_items as $raw_item ) {
			if ( ! is_array( $raw_item ) ) {
				continue;
			}

			$product_id   = absint( $raw_item['product_id'] ?? $raw_item['id'] ?? 0 );
			$variation_id = absint( $raw_item['variation_id'] ?? 0 );
			if ( $product_id <= 0 ) {
				continue;
			}

			$product   = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
			$parent_id = $product && is_callable( array( $product, 'get_parent_id' ) ) ? absint( call_user_func( array( $product, 'get_parent_id' ) ) ) : 0;
			if ( $parent_id > 0 && 0 === $variation_id ) {
				$variation_id = $product->get_id();
				$product_id   = $parent_id;
			}
			$items[] = array(
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'quantity'       => max( 1, absint( $raw_item['quantity'] ?? 1 ) ),
				'variation'      => isset( $raw_item['variation'] ) && is_array( $raw_item['variation'] ) ? $this->sanitize_snapshot_array( $raw_item['variation'] ) : array(),
				'cart_item_data' => isset( $raw_item['cart_item_data'] ) && is_array( $raw_item['cart_item_data'] ) ? $this->sanitize_snapshot_array( $raw_item['cart_item_data'] ) : array(),
				'product_name'   => $product ? sanitize_text_field( $product->get_name() ) : sanitize_text_field( (string) ( $raw_item['product_name'] ?? $raw_item['name'] ?? '' ) ),
				'permalink'      => $product ? esc_url_raw( $product->get_permalink() ) : '',
				'image_id'       => $product ? absint( $product->get_image_id() ) : 0,
				'currency'       => sanitize_text_field( $cart_data['currency'] ?? get_woocommerce_currency() ),
			);
		}

		return array(
			'items'           => $items,
			'currency'        => sanitize_text_field( $cart_data['currency'] ?? get_woocommerce_currency() ),
			'applied_coupons' => isset( $cart_data['applied_coupons'] ) && is_array( $cart_data['applied_coupons'] ) ? array_values( array_map( 'sanitize_text_field', $cart_data['applied_coupons'] ) ) : array(),
			'grand_total'     => floatval( $cart_data['total'] ?? $cart_data['grand_total'] ?? 0 ),
			'cart_hash'       => sanitize_text_field( $cart_data['hash'] ?? $cart_data['cart_hash'] ?? '' ),
			'cart_item_count' => absint( $cart_data['cart_item_count'] ?? count( $items ) ),
			'captured_at'     => time(),
		);
	}

	/**
	 * Determine whether a snapshot contains restorable items.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $snapshot Cart snapshot.
	 *
	 * @return bool True when at least one item is present.
	 */
	private function snapshot_has_items( array $snapshot ): bool {
		return ! empty( $snapshot['items'] ) && is_array( $snapshot['items'] );
	}

	/**
	 * Build a stable cart fingerprint from item identity, not shopper email.
	 *
	 * Quantity and prices are intentionally excluded so the same cart can be
	 * updated as shoppers adjust quantities before recovery.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $snapshot Cart snapshot.
	 *
	 * @return string SHA-256 cart fingerprint, or empty string when no items exist.
	 */
	private function build_cart_fingerprint( array $snapshot ): string {
		$items = isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? $snapshot['items'] : array();
		if ( empty( $items ) ) {
			return '';
		}

		$fingerprint_items = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$variation      = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $this->sanitize_snapshot_array( $item['variation'] ) : array();
			$cart_item_data = isset( $item['cart_item_data'] ) && is_array( $item['cart_item_data'] ) ? $this->sanitize_snapshot_array( $item['cart_item_data'] ) : array();
			ksort( $variation );
			ksort( $cart_item_data );

			$fingerprint_items[] = array(
				'product_id'     => absint( $item['product_id'] ?? 0 ),
				'variation_id'   => absint( $item['variation_id'] ?? 0 ),
				'variation'      => $variation,
				'cart_item_data' => $cart_item_data,
			);
		}

		if ( empty( $fingerprint_items ) ) {
			return '';
		}

		usort(
			$fingerprint_items,
			static fn ( array $a, array $b ): int => strcmp( (string) wp_json_encode( $a ), (string) wp_json_encode( $b ) )
		);

		return hash( 'sha256', (string) wp_json_encode( $fingerprint_items ) );
	}

	/**
	 * Persist cart contents as WooCommerce order line items on the session order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $session  CartBay session order.
	 * @param array<string, mixed> $snapshot Cart snapshot.
	 *
	 * @return void
	 */
	private function persist_session_items( WC_Order $session, array $snapshot ): void {
		foreach ( $session->get_items() as $item_id => $item ) {
			unset( $item );
			$session->remove_item( $item_id );
		}

		$items = isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? $snapshot['items'] : array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$restore_product_id = absint( $item['variation_id'] ?? 0 );
			if ( 0 === $restore_product_id ) {
				$restore_product_id = absint( $item['product_id'] ?? 0 );
			}

			$product = wc_get_product( $restore_product_id );
			if ( ! $product ) {
				continue;
			}

			$item_id = $session->add_product(
				$product,
				max( 1, absint( $item['quantity'] ?? 1 ) ),
				array(
					'subtotal'     => floatval( $item['line_subtotal'] ?? 0 ),
					'total'        => floatval( $item['line_total'] ?? 0 ),
					'subtotal_tax' => floatval( $item['line_subtotal_tax'] ?? 0 ),
					'total_tax'    => floatval( $item['line_tax'] ?? 0 ),
				)
			);

			$order_item = $session->get_item( $item_id );
			if ( $order_item ) {
				foreach ( (array) ( $item['variation'] ?? array() ) as $key => $value ) {
					$order_item->add_meta_data( sanitize_key( (string) $key ), sanitize_text_field( (string) $value ), true );
				}
			}
		}

		$session->calculate_totals( false );
		$session->save();
	}

	/**
	 * Keep only scalar/array cart item data safe to pass back to add_to_cart().
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $cart_item WooCommerce cart item.
	 *
	 * @return array<string, mixed> Safe custom cart item data.
	 */
	private function get_safe_cart_item_data( array $cart_item ): array {
		$blocked = array( 'data', 'product_id', 'variation_id', 'variation', 'quantity', 'line_subtotal', 'line_subtotal_tax', 'line_total', 'line_tax', 'line_tax_data' );
		$safe    = array();

		foreach ( $cart_item as $key => $value ) {
			if ( ! is_string( $key ) || in_array( $key, $blocked, true ) || is_object( $value ) || is_resource( $value ) ) {
				continue;
			}

			$safe[ sanitize_key( $key ) ] = $this->sanitize_snapshot_value( $value );
		}

		return $safe;
	}

	/**
	 * Sanitize a snapshot array recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return mixed Sanitized value.
	 */
	private function sanitize_snapshot_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return $this->sanitize_snapshot_array( $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Sanitize a snapshot array recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $value Raw array.
	 *
	 * @return array<mixed> Sanitized array.
	 */
	private function sanitize_snapshot_array( array $value ): array {
		$sanitized = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) ) {
				$sanitized[ sanitize_key( $key ) ] = $this->sanitize_snapshot_value( $item );
				continue;
			}

			$sanitized[] = $this->sanitize_snapshot_value( $item );
		}

		return $sanitized;
	}

	/**
	 * Delete a captured cart session after consent is withdrawn.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email      Sanitized email address, when available.
	 * @param int    $session_id Optional existing session ID.
	 *
	 * @return bool Whether a session was deleted.
	 */
	public function delete_after_consent_withdrawal( string $email = '', int $session_id = 0 ): bool {
		$settings       = get_option( 'cartbay_settings', array() );
		$retention_days = absint( $settings['data_retention_days'] ?? 30 );
		$existing       = null;

		// Resolve by session_id only when the request proves ownership of it.
		// session_id is a guessable order ID, so the email must match the
		// stored session; otherwise fall through to the email lookup below.
		if ( $session_id > 0 && '' !== $email ) {
			$order = $this->sessions->get( $session_id );
			if ( $order
				&& in_array( $order->get_status(), array( 'cartbay-captured', 'cartbay-abandoned' ), true )
				&& $this->session_email_matches( $order, $email )
			) {
				$existing = $order;
			}
		}

		if ( ! $existing && '' !== $email ) {
			$existing = $this->sessions->find_active_by_email( $email, $retention_days );
		}

		if ( ! $existing ) {
			return false;
		}

		$deleted_session_id = $existing->get_id();

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'cartbay_detect_session_abandonment', array( $deleted_session_id ), 'cartbay' );
		}

		// Cancel pending recovery email jobs. Action Scheduler stores
		// args as [session_id, step_index], so we use a direct DB query
		// to match all steps for this session (same approach as RecoveryMatcher).
		global $wpdb;
		$pattern = '%' . $wpdb->esc_like( '[' . $deleted_session_id . ',' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}actionscheduler_actions SET status = %s WHERE hook = %s AND status = %s AND args LIKE %s",
				'canceled',
				'cartbay_send_recovery_email',
				'pending',
				$pattern
			)
		);

		$existing->delete( true );
		AnalyticsService::invalidate_cache();

		Logger::info(
			'Cart session deleted after consent withdrawal.',
			array(
				'session_id' => $deleted_session_id,
			),
			'capture'
		);

		return true;
	}

	/**
	 * Schedule an exact abandonment check for a captured session.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id Session order ID.
	 *
	 * @return void
	 */
	private function schedule_abandonment_check( int $session_id ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		$settings     = get_option( 'cartbay_settings', array() );
		$timeout      = max( 1, absint( $settings['abandonment_timeout'] ?? 30 ) );
		$scheduled_at = time() + ( $timeout * MINUTE_IN_SECONDS );

		as_unschedule_all_actions( 'cartbay_detect_session_abandonment', array( $session_id ), 'cartbay' );
		as_schedule_single_action(
			$scheduled_at,
			'cartbay_detect_session_abandonment',
			array( $session_id ),
			'cartbay'
		);
	}

	/**
	 * Check whether an email is in the suppression list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 *
	 * @return bool
	 */
	public function is_suppressed( string $email ): bool {
		$hash = TokenHelper::hash_email( $email );
		$post = get_page_by_path( $hash, OBJECT, 'cartbay_suppressed' );

		return null !== $post;
	}
}
