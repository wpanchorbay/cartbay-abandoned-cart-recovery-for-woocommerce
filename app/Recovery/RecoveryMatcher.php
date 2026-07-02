<?php
/**
 * Recovery matcher.
 *
 * @package WPAnchorBay\CartBay\Recovery
 */

namespace WPAnchorBay\CartBay\Recovery;

use WC_Order;
use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Data\SessionRepository;
use WPAnchorBay\CartBay\Utils\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Matches completed WooCommerce orders to abandoned CartBay sessions.
 *
 * When a shopper completes a purchase with the same email as an abandoned
 * session, this service marks the session as recovered and cancels pending
 * email jobs.
 *
 * @since 1.0.0
 */
class RecoveryMatcher {

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
	 * Register WooCommerce hooks for recovery detection.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_checkout_attribution' ), 20 );
	}

	/**
	 * Attach CartBay restore identity from the WooCommerce session to the checkout order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order Checkout order being created.
	 *
	 * @return void
	 */
	public function attach_checkout_attribution( WC_Order $order ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$session_id      = absint( WC()->session->get( 'cartbay_restored_session_id' ) );
		$token_hash      = sanitize_text_field( (string) WC()->session->get( 'cartbay_restore_token_hash' ) );
		$notification_id = sanitize_key( (string) WC()->session->get( 'cartbay_notification_id' ) );

		if ( $session_id > 0 ) {
			$order->update_meta_data( '_cartbay_session_id', $session_id );
		}

		if ( '' !== $token_hash ) {
			$order->update_meta_data( '_cartbay_restore_token_hash', $token_hash );
		}

		if ( '' !== $notification_id ) {
			$order->update_meta_data( '_cartbay_notification_id', $notification_id );
		}
	}

	/**
	 * Handle payment complete event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return void
	 */
	public function handle_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$status = $order->get_status();
		if ( in_array( $status, array( 'processing', 'completed' ), true ) ) {
			$this->match( $order );
		}
	}

	/**
	 * Handle order status change to processing/completed.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Previous status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    WC order object.
	 *
	 * @return void
	 */
	public function handle_status_change( int $order_id, string $from, string $to, WC_Order $order ): void {
		unset( $order_id, $from );

		if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
			$this->match( $order );
		}
	}

	/**
	 * Match an order to an abandoned session by billing email.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order Completed WooCommerce order.
	 *
	 * @return void
	 */
	private function match( WC_Order $order ): void {
		// Skip CartBay session orders.
		if ( strpos( $order->get_status(), 'cartbay' ) !== false ) {
			return;
		}

		$email          = $order->get_billing_email();
		$settings       = get_option( 'cartbay_settings', array() );
		$retention_days = absint( $settings['data_retention_days'] ?? 30 );

		$attribution_source = 'email_hash';
		$session            = $this->find_attributed_session( $order, $email, $retention_days, $attribution_source );

		if ( ! $session ) {
			return;
		}

		$abandoned_at = absint( $session->get_meta( '_cartbay_abandoned_at', true ) );
		if ( 'cartbay-abandoned' !== $session->get_status() && 0 === $abandoned_at ) {
			$this->sessions->add_event(
				$session->get_id(),
				'completed_before_abandonment',
				array(
					'order_id' => $order->get_id(),
					'revenue'  => $order->get_total(),
				)
			);
			return;
		}

		$notification_id = sanitize_key( (string) $order->get_meta( '_cartbay_notification_id', true ) );
		if ( '' === $notification_id ) {
			$notification_id = $this->notifications->mark_recovered( $session->get_id(), $order->get_id() );
		}

		// Mark as recovered.
		$session->set_status( 'wc-cartbay-recovered' );
		$session->update_meta_data( '_cartbay_recovered_at', time() );
		$session->update_meta_data( '_cartbay_recovered_order_id', $order->get_id() );
		$session->update_meta_data( '_cartbay_recovered_revenue', floatval( $order->get_total() ) );
		$session->update_meta_data( '_cartbay_attribution_source', $attribution_source );
		$session->update_meta_data( '_cartbay_matched_email_hash', hash( 'sha256', strtolower( trim( sanitize_email( $email ) ) ) ) );
		$session->update_meta_data( '_cartbay_recovered_notification_id', $notification_id );
		$session->update_meta_data( '_cartbay_restored', (bool) $session->get_meta( '_cartbay_restore_clicked_at', true ) );
		$session->save();

		// Cancel pending email jobs for this session.
		$this->cancel_pending_email_jobs( $session->get_id() );
		$this->notifications->cancel_pending_for_session( $session->get_id(), 'recovered' );

		Logger::info(
			sprintf( 'Session recovered. Order #%d. Revenue: %s.', $order->get_id(), $order->get_total() ),
			array(
				'session_id' => $session->get_id(),
				'order_id'   => $order->get_id(),
			),
			'recovery'
		);

		$this->sessions->add_event(
			$session->get_id(),
			'recovered',
			array(
				'order_id'           => $order->get_id(),
				'revenue'            => $order->get_total(),
				'attribution_source' => $attribution_source,
				'notification_id'    => $notification_id,
			)
		);
		AnalyticsService::invalidate_cache();

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( 'cartbay_restored_session_id' );
			WC()->session->__unset( 'cartbay_restore_token_hash' );
			WC()->session->__unset( 'cartbay_notification_id' );
			WC()->session->__unset( 'cartbay_restored_email' );
		}
	}

	/**
	 * Find the best attributed CartBay session for an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order              WooCommerce order.
	 * @param string   $email              Billing email.
	 * @param int      $retention_days     Retention window.
	 * @param string   $attribution_source Attribution source output.
	 *
	 * @return WC_Order|null Matching session.
	 */
	private function find_attributed_session( WC_Order $order, string $email, int $retention_days, string &$attribution_source ): ?WC_Order {
		$session_id = absint( $order->get_meta( '_cartbay_session_id', true ) );
		if ( $session_id > 0 ) {
			$session = $this->sessions->find_by_session_id( $session_id );
			if ( $session ) {
				$attribution_source = 'session_id';
				return $session;
			}
		}

		$token_hash = sanitize_text_field( (string) $order->get_meta( '_cartbay_restore_token_hash', true ) );
		if ( '' !== $token_hash ) {
			$session = $this->sessions->find_by_token_hash( $token_hash );
			if ( $session ) {
				$attribution_source = 'restore_token';
				return $session;
			}
		}

		$session = $this->sessions->find_latest_recoverable_by_email( $email, $retention_days );
		if ( $session ) {
			$attribution_source = 'email_hash';
		}

		return $session;
	}

	/**
	 * Cancel all pending recovery email actions for a session.
	 *
	 * Action Scheduler stores args as JSON like [291,0] which doesn't match
	 * the simple array(291) arg, so we query and delete directly.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id CartBay session order ID.
	 *
	 * @return void
	 */
	private function cancel_pending_email_jobs( int $session_id ): void {
		global $wpdb;

		$pattern = '%' . $wpdb->esc_like( '[' . $session_id . ',' ) . '%';
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
	}
}
