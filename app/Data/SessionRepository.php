<?php
/**
 * Session repository.
 *
 * @package WPAnchorBay\CartBay\Data
 */

namespace WPAnchorBay\CartBay\Data;

use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * HPOS-safe repository for CartBay session orders.
 *
 * @since 1.0.0
 */
class SessionRepository {
	/**
	 * Create a new captured session.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Sanitized email address.
	 * @param array  $meta  Session metadata array.
	 *
	 * @return int|WP_Error WC order ID on success.
	 */
	public function create( string $email, array $meta ): int|WP_Error {
		$sanitized_email = sanitize_email( $email );

		if ( '' === $sanitized_email ) {
			return new WP_Error( 'cartbay_invalid_email', __( 'A valid email address is required to create a CartBay session.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		if ( ! function_exists( 'wc_create_order' ) ) {
			return new WP_Error( 'cartbay_wc_unavailable', __( 'WooCommerce order creation is unavailable.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		try {
			$order = wc_create_order();
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return new WP_Error( 'cartbay_session_create_failed', __( 'CartBay could not create a session order.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'cartbay_session_create_failed', __( 'WooCommerce did not return a valid order object.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		$order->set_status( 'cartbay-captured' );
		$order->set_billing_email( $sanitized_email );
		$order->set_created_via( 'cartbay' );
		$order->update_meta_data( '_cartbay_session_id', $order->get_id() );

		$default_meta = array(
			'email'            => $sanitized_email,
			'captured_at'      => time(),
			'last_activity_at' => time(),
			'sequence_step'    => 0,
			'token_hash'       => '',
		);

		foreach ( array_merge( $default_meta, $this->sanitize_meta_array( $meta ) ) as $meta_key => $meta_value ) {
			$order->update_meta_data( '_cartbay_' . $meta_key, $meta_value );
		}

		$order->save();

		return $order->get_id();
	}

	/**
	 * Update session metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $session_id WC order ID.
	 * @param array $meta       Metadata to merge.
	 *
	 * @return bool
	 */
	public function update( int $session_id, array $meta ): bool {
		$order = $this->get( $session_id );

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		if ( ! in_array( $order->get_status(), array( 'cartbay-captured', 'cartbay-abandoned', 'cartbay-recovered', 'cartbay-expired', 'cartbay-suppressed' ), true ) ) {
			return false;
		}

		$sanitized_meta = $this->sanitize_meta_array( $meta );

		if ( isset( $sanitized_meta['email'] ) && is_string( $sanitized_meta['email'] ) && '' !== $sanitized_meta['email'] ) {
			$order->set_billing_email( sanitize_email( $sanitized_meta['email'] ) );
		}

		foreach ( $sanitized_meta as $meta_key => $meta_value ) {
			$order->update_meta_data( '_cartbay_' . $meta_key, $meta_value );
		}

		$order->save();

		return true;
	}

	/**
	 * Get a session by WooCommerce order ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id WooCommerce order ID.
	 *
	 * @return WC_Order|null
	 */
	public function get( int $session_id ): ?WC_Order {
		$order = wc_get_order( absint( $session_id ) );

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Get captured sessions that have been inactive beyond the abandonment timeout.
	 *
	 * @since 1.0.0
	 *
	 * @param int $timeout_minutes Timeout in minutes.
	 *
	 * @return array<int, WC_Order>
	 */
	public function get_inactive_captured( int $timeout_minutes ): array {
		$cutoff = time() - ( absint( $timeout_minutes ) * MINUTE_IN_SECONDS );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WC CRUD lookup by CartBay-owned activity timestamp.
		$orders = $this->query_orders(
			array(
				'status'       => array( 'wc-cartbay-captured' ),
				'meta_key'     => '_cartbay_last_activity_at',
				'meta_value'   => $cutoff,
				'meta_compare' => '<',
				'meta_type'    => 'NUMERIC',
				'limit'        => 100,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		return $orders;
	}

	/**
	 * Find an active session by email within the retention window.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email          Email address.
	 * @param int    $retention_days Retention window in days.
	 *
	 * @return WC_Order|null
	 */
	public function find_active_by_email( string $email, int $retention_days ): ?WC_Order {
		$sanitized_email = sanitize_email( $email );

		if ( '' === $sanitized_email ) {
			return null;
		}

		$orders = $this->get_recent_sessions_by_email( $sanitized_email, array( 'wc-cartbay-captured', 'wc-cartbay-abandoned' ), $retention_days, 1 );

		return isset( $orders[0] ) ? $orders[0] : null;
	}

	/**
	 * Find an active session by shopper email and cart fingerprint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email            Email address.
	 * @param string $cart_fingerprint Cart fingerprint hash.
	 * @param int    $retention_days   Retention window in days.
	 *
	 * @return WC_Order|null Matching session.
	 */
	public function find_active_by_email_and_cart_fingerprint( string $email, string $cart_fingerprint, int $retention_days ): ?WC_Order {
		$sanitized_email  = sanitize_email( $email );
		$cart_fingerprint = sanitize_text_field( $cart_fingerprint );

		if ( '' === $sanitized_email || '' === $cart_fingerprint ) {
			return null;
		}

		foreach ( $this->get_recent_sessions_by_email( $sanitized_email, array( 'wc-cartbay-captured', 'wc-cartbay-abandoned' ), $retention_days ) as $session ) {
			$stored_fingerprint = sanitize_text_field( (string) $session->get_meta( '_cartbay_cart_fingerprint', true ) );
			if ( '' !== $stored_fingerprint && hash_equals( $stored_fingerprint, $cart_fingerprint ) ) {
				return $session;
			}
		}

		return null;
	}

	/**
	 * Find the latest recoverable session for a shopper email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email          Email address.
	 * @param int    $retention_days Retention window in days.
	 *
	 * @return WC_Order|null Matching session.
	 */
	public function find_latest_recoverable_by_email( string $email, int $retention_days ): ?WC_Order {
		$sanitized_email = sanitize_email( $email );

		if ( '' === $sanitized_email ) {
			return null;
		}

		$abandoned = $this->get_recent_sessions_by_email( $sanitized_email, array( 'wc-cartbay-abandoned' ), $retention_days, 1 );
		if ( isset( $abandoned[0] ) ) {
			return $abandoned[0];
		}

		$captured = $this->get_recent_sessions_by_email( $sanitized_email, array( 'wc-cartbay-captured' ), $retention_days, 1 );

		return $captured[0] ?? null;
	}

	/**
	 * Find a CartBay session by its session ID meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id CartBay session ID.
	 *
	 * @return WC_Order|null Matching session.
	 */
	public function find_by_session_id( int $session_id ): ?WC_Order {
		if ( $session_id <= 0 ) {
			return null;
		}

		$order = $this->get( $session_id );
		if ( $order instanceof WC_Order && str_starts_with( $order->get_status(), 'cartbay-' ) ) {
			return $order;
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WC CRUD lookup by legacy CartBay session ID meta.
		$orders = $this->query_orders(
			array(
				'status'     => array( 'wc-cartbay-captured', 'wc-cartbay-abandoned', 'wc-cartbay-recovered', 'wc-cartbay-suppressed' ),
				'meta_key'   => '_cartbay_session_id',
				'meta_value' => absint( $session_id ),
				'limit'      => 1,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		return $orders[0] ?? null;
	}

	/**
	 * Find a CartBay session by restore token hash.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token_hash Restore token hash.
	 *
	 * @return WC_Order|null Matching session.
	 */
	public function find_by_token_hash( string $token_hash ): ?WC_Order {
		$token_hash = sanitize_text_field( $token_hash );
		if ( '' === $token_hash ) {
			return null;
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WC CRUD lookup by CartBay-owned restore token hash.
		$orders = $this->query_orders(
			array(
				'status'     => array( 'wc-cartbay-captured', 'wc-cartbay-abandoned', 'wc-cartbay-recovered', 'wc-cartbay-suppressed' ),
				'meta_key'   => '_cartbay_token_hash',
				'meta_value' => $token_hash,
				'limit'      => 1,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		return $orders[0] ?? null;
	}

	/**
	 * Get transient sessions past the retention window.
	 *
	 * Recovered sessions are intentionally excluded: they are the historical
	 * recovered-revenue record and must never be auto-deleted by retention
	 * cleanup, or reporting silently loses revenue attribution.
	 *
	 * @since 1.0.0
	 *
	 * @param int $retention_days Retention window in days.
	 * @param int $limit          Maximum results.
	 *
	 * @return array<int, WC_Order>
	 */
	public function get_expired_sessions( int $retention_days, int $limit = 100 ): array {
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( absint( $retention_days ) * DAY_IN_SECONDS ) );

		return $this->query_orders(
			array(
				'status'       => array( 'wc-cartbay-captured', 'wc-cartbay-abandoned', 'wc-cartbay-suppressed' ),
				'date_created' => '<' . $threshold,
				'limit'        => absint( $limit ),
			)
		);
	}

	/**
	 * Record a session lifecycle event by firing the `cartbay_session_event` hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id WC order ID.
	 * @param string $event       Event name (e.g. 'captured', 'abandoned', 'recovered').
	 * @param array  $data        Optional event data.
	 *
	 * @return bool True when the session exists and the event was dispatched.
	 */
	public function add_event( int $session_id, string $event, array $data = array() ): bool {
		if ( ! $this->get( $session_id ) instanceof WC_Order ) {
			return false;
		}

		/**
		 * Fires when a CartBay session records a lifecycle event.
		 *
		 * The free plugin does not itself persist a per-session event history;
		 * extensions can hook this to maintain their own audit trail or activity
		 * analytics from the raw event stream.
		 *
		 * @since 1.0.0
		 *
		 * @param int                  $session_id Session order ID.
		 * @param string               $event      Event name (e.g. 'captured', 'abandoned', 'recovered').
		 * @param array<string, mixed> $data       Optional event payload.
		 */
		do_action( 'cartbay_session_event', $session_id, sanitize_key( $event ), $data );

		return true;
	}

	/**
	 * Delete expired sessions and cancel their pending Action Scheduler jobs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prune_expired(): void {
		$settings       = get_option( 'cartbay_settings', array() );
		$retention_days = isset( $settings['data_retention_days'] ) ? absint( $settings['data_retention_days'] ) : 30;

		foreach ( $this->get_expired_sessions( $retention_days ) as $order ) {
			// Cancel any pending recovery email actions for this session.
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( 'cartbay_send_recovery_email', array( $order->get_id() ), 'cartbay' );
			}

			// Force delete (skip trash).
			$order->delete( true );
		}
	}

	/**
	 * Get recent sessions for a shopper email and status set.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $email          Sanitized email address.
	 * @param array<int, string> $statuses       WC order statuses.
	 * @param int                $retention_days Retention window in days.
	 * @param int                $limit          Maximum results.
	 *
	 * @return array<int, WC_Order> Matching sessions, newest first.
	 */
	private function get_recent_sessions_by_email( string $email, array $statuses, int $retention_days, int $limit = -1 ): array {
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( absint( $retention_days ) * DAY_IN_SECONDS ) );

		return $this->query_orders(
			array(
				'billing_email' => sanitize_email( $email ),
				'status'        => $statuses,
				'date_created'  => '>' . $threshold,
				'orderby'       => 'date',
				'order'         => 'DESC',
				'limit'         => $limit,
			)
		);
	}

	/**
	 * Query WooCommerce orders using shared defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return array<int, WC_Order>
	 */
	private function query_orders( array $args ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array_merge(
				array(
					'type'   => 'shop_order',
					'return' => 'objects',
				),
				$args
			)
		);

		return array_values( $orders );
	}

	/**
	 * Sanitize session metadata recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meta Raw metadata.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_meta_array( array $meta ): array {
		$sanitized = array();

		foreach ( $meta as $meta_key => $meta_value ) {
			if ( ! is_string( $meta_key ) ) {
				continue;
			}

			$sanitized[ sanitize_key( $meta_key ) ] = $this->sanitize_meta_value( $meta_value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a session metadata value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return mixed
	 */
	private function sanitize_meta_value( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $nested_key => $nested_value ) {
				if ( is_string( $nested_key ) ) {
					$sanitized[ sanitize_key( $nested_key ) ] = $this->sanitize_meta_value( $nested_value );
					continue;
				}

				$sanitized[] = $this->sanitize_meta_value( $nested_value );
			}

			return $sanitized;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return '';
	}
}
