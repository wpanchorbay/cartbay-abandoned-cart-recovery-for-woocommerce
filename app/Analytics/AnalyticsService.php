<?php
/**
 * Analytics service.
 *
 * @package WPAnchorBay\CartBay\Analytics
 */

namespace WPAnchorBay\CartBay\Analytics;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and caches CartBay's core recovery analytics aggregates.
 *
 * This service intentionally computes only the headline recovery metrics that
 * the free plugin renders (tracked, abandoned, recovered, abandoned value,
 * recovered revenue, recovery rate, and the basic email-delivery counts).
 * Deeper reporting (per-step performance, restore-click funnels, shopper
 * behaviour, etc.) is provided by extensions through the documented
 * `cartbay_overview_metric_cards` and `cartbay_notifications_after_summary`
 * hooks, which receive the raw session data and compute their own figures.
 *
 * @since 1.0.0
 */
class AnalyticsService {

	/**
	 * Transient cache key.
	 *
	 * @since 1.0.0
	 */
	private const CACHE_KEY = 'cartbay_analytics_cache';

	/**
	 * Cache TTL in seconds.
	 *
	 * @since 1.0.0
	 */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Get analytics data for the given period.
	 *
	 * @since 1.0.0
	 *
	 * @param int $days Period length: 7, 30, or 90.
	 *
	 * @return array Analytics data.
	 */
	public function get( int $days = 30 ): array {
		$cache     = get_transient( self::CACHE_KEY );
		$cache_key = "period_{$days}";

		if ( is_array( $cache ) && isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$data = $this->build( $days );

		$all_cache               = is_array( $cache ) ? $cache : array();
		$all_cache[ $cache_key ] = $data;
		set_transient( self::CACHE_KEY, $all_cache, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Force refresh all cached periods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function refresh(): void {
		self::invalidate_cache();
		foreach ( array( 7, 30, 90 ) as $days ) {
			$this->get( $days );
		}
	}

	/**
	 * Invalidate cached analytics aggregates.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function invalidate_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Build raw analytics for a period.
	 *
	 * @since 1.0.0
	 *
	 * @param int $days Period length in days.
	 *
	 * @return array
	 */
	private function build( int $days ): array {
		$since_timestamp    = time() - ( $days * DAY_IN_SECONDS );
		$abandoned_sessions = $this->get_sessions_by_meta_timestamp(
			array( 'wc-cartbay-abandoned', 'wc-cartbay-recovered' ),
			'_cartbay_abandoned_at',
			$since_timestamp
		);
		$recovered_sessions = $this->get_sessions_by_meta_timestamp(
			array( 'wc-cartbay-recovered' ),
			'_cartbay_recovered_at',
			$since_timestamp
		);

		// Count tracked carts on the same CartBay meta-timestamp basis as the
		// abandoned/recovered funnel (capture time), rather than order
		// date_created, so the funnel metrics stay consistent.
		$captured        = count(
			$this->get_sessions_by_meta_timestamp(
				array( 'wc-cartbay-captured', 'wc-cartbay-abandoned', 'wc-cartbay-recovered' ),
				'_cartbay_captured_at',
				$since_timestamp
			)
		);
		$abandoned       = count( $abandoned_sessions );
		$recovered       = count( $recovered_sessions );
		$abandoned_value = $this->sum_abandoned_value( $abandoned_sessions );
		$revenue         = $this->sum_recovered_revenue( $recovered_sessions );
		$rate            = $abandoned > 0 ? round( ( $recovered / $abandoned ) * 100, 1 ) : 0.0;
		$email_funnel    = $this->build_email_funnel( $abandoned_sessions );
		$send_rate       = $email_funnel['attempted'] > 0 ? round( ( $email_funnel['sent'] / $email_funnel['attempted'] ) * 100, 1 ) : 0.0;

		return array(
			'tracked'         => $captured,
			'abandoned'       => $abandoned,
			'recovered'       => $recovered,
			'abandoned_value' => $abandoned_value,
			'revenue'         => $revenue,
			'recovery_rate'   => $rate,
			'emails_queued'   => $email_funnel['queued'],
			'emails_sent'     => $email_funnel['sent'],
			'emails_failed'   => $email_funnel['failed'],
			'email_send_rate' => $send_rate,
			'period_days'     => $days,
		);
	}

	/**
	 * Build the basic recovery-email delivery counts for a period.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $abandoned_sessions Abandoned sessions.
	 *
	 * @return array<string, int> Funnel counts: queued, attempted, sent, failed.
	 */
	private function build_email_funnel( array $abandoned_sessions ): array {
		$queued    = 0;
		$attempted = 0;
		$sent      = 0;
		$failed    = 0;

		foreach ( $abandoned_sessions as $session ) {
			foreach ( $this->get_notifications( $session ) as $notification ) {
				$status = sanitize_key( (string) ( $notification['status'] ?? '' ) );
				if ( in_array( $status, array( 'queued', 'retry_queued' ), true ) ) {
					++$queued;
				}

				if ( absint( $notification['attempts'] ?? 0 ) > 0 || in_array( $status, array( 'attempted', 'sent', 'failed', 'delivered' ), true ) ) {
					++$attempted;
				}

				if ( in_array( $status, array( 'sent', 'delivered' ), true ) ) {
					++$sent;
				}

				if ( 'failed' === $status ) {
					++$failed;
				}
			}
		}

		return array(
			'queued'    => $queued,
			'attempted' => $attempted,
			'sent'      => $sent,
			'failed'    => $failed,
		);
	}

	/**
	 * Get sessions with a timestamp meta value inside the reporting period.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $statuses        WC order statuses.
	 * @param string             $meta_key        Timestamp meta key.
	 * @param int                $since_timestamp Period start timestamp.
	 *
	 * @return array<int, WC_Order> Matching session orders.
	 */
	private function get_sessions_by_meta_timestamp( array $statuses, string $meta_key, int $since_timestamp ): array {
		$sessions = $this->get_sessions_by_statuses( $statuses );

		return array_values(
			array_filter(
				$sessions,
				static function ( WC_Order $session ) use ( $meta_key, $since_timestamp ): bool {
					$event_timestamp = absint( $session->get_meta( $meta_key, true ) );

					return $event_timestamp >= $since_timestamp;
				}
			)
		);
	}

	/**
	 * Get sessions by status.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $statuses WC order statuses.
	 *
	 * @return array<int, WC_Order> Matching session orders.
	 */
	private function get_sessions_by_statuses( array $statuses ): array {
		$sessions = wc_get_orders(
			array(
				'status' => $statuses,
				'limit'  => -1,
				'return' => 'objects',
			)
		);

		return array_values( $sessions );
	}

	/**
	 * Sum recovered revenue from recovered session orders.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions Recovered session orders.
	 *
	 * @return float Recovered revenue total.
	 */
	private function sum_recovered_revenue( array $sessions ): float {
		$total = 0.0;
		foreach ( $sessions as $session ) {
			$recovered_order_id = absint( $session->get_meta( '_cartbay_recovered_order_id', true ) );
			$recovered_order    = $recovered_order_id > 0 ? wc_get_order( $recovered_order_id ) : null;

			if ( $recovered_order instanceof WC_Order ) {
				$total += (float) $recovered_order->get_total();
				continue;
			}

			$total += floatval( $session->get_meta( '_cartbay_recovered_revenue', true ) );
		}

		return $total;
	}

	/**
	 * Sum value of carts that became abandoned in the period.
	 *
	 * Includes both currently abandoned and recovered sessions, because recovered
	 * sessions were abandoned before recovery.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions Abandoned session orders.
	 *
	 * @return float Abandoned cart value total.
	 */
	private function sum_abandoned_value( array $sessions ): float {
		$total = 0.0;
		foreach ( $sessions as $session ) {
			$total += floatval( $session->get_meta( '_cartbay_cart_total', true ) );
		}

		return $total;
	}

	/**
	 * Get notifications from a session.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $session Session order.
	 *
	 * @return array<int, array<string, mixed>> Notifications.
	 */
	private function get_notifications( WC_Order $session ): array {
		$notifications = $session->get_meta( '_cartbay_notifications', true );

		return is_array( $notifications ) ? array_values( array_filter( $notifications, 'is_array' ) ) : array();
	}
}
