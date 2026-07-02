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
 * Builds and caches CartBay analytics aggregates.
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
		$event_sessions     = $this->get_sessions_by_statuses(
			array( 'wc-cartbay-captured', 'wc-cartbay-abandoned', 'wc-cartbay-recovered', 'wc-cartbay-suppressed' )
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
		$email_funnel    = $this->build_email_funnel( $abandoned_sessions, $recovered_sessions );
		$restore_clicks  = $this->count_events_since( $event_sessions, 'restore_clicked', $since_timestamp );
		$failed_restores = $this->count_events_since( $event_sessions, 'cart_restore_failed', $since_timestamp );
		$restored        = $this->count_restored_sessions( $recovered_sessions );
		$pre_abandonment = $this->count_events( $this->get_sessions_by_meta_timestamp( array( 'wc-cartbay-captured' ), '_cartbay_captured_at', $since_timestamp ), 'completed_before_abandonment' );
		$click_rate      = $restore_clicks > 0 ? round( ( $restored / $restore_clicks ) * 100, 1 ) : 0.0;
		$send_rate       = $email_funnel['attempted'] > 0 ? round( ( $email_funnel['sent'] / $email_funnel['attempted'] ) * 100, 1 ) : 0.0;

		return array(
			'tracked'                        => $captured,
			'abandoned'                      => $abandoned,
			'recovered'                      => $recovered,
			'restored'                       => $restored,
			'completed_before_abandonment'   => $pre_abandonment,
			'abandoned_value'                => $abandoned_value,
			'revenue'                        => $revenue,
			'recovery_rate'                  => $rate,
			'restore_clicks'                 => $restore_clicks,
			'click_to_recovery_rate'         => $click_rate,
			'emails_queued'                  => $email_funnel['queued'],
			'emails_sent'                    => $email_funnel['sent'],
			'emails_failed'                  => $email_funnel['failed'],
			'email_send_rate'                => $send_rate,
			'revenue_by_step'                => $email_funnel['revenue_by_step'],
			'recoveries_by_step'             => $email_funnel['recoveries_by_step'],
			'best_step'                      => $email_funnel['best_step'],
			'average_time_to_recovery'       => $this->average_time_to_recovery( $recovered_sessions ),
			'returning_shopper_count'        => $this->count_returning_shoppers( $abandoned_sessions ),
			'repeat_abandoned_shopper_count' => $this->count_repeat_abandoned_shoppers( $abandoned_sessions ),
			'failed_restore_count'           => $failed_restores,
			'period_days'                    => $days,
		);
	}

	/**
	 * Build email funnel and sequence performance metrics.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $abandoned_sessions Abandoned sessions.
	 * @param array<int, WC_Order> $recovered_sessions Recovered sessions.
	 *
	 * @return array<string, mixed> Funnel data.
	 */
	private function build_email_funnel( array $abandoned_sessions, array $recovered_sessions ): array {
		$queued             = 0;
		$attempted          = 0;
		$sent               = 0;
		$failed             = 0;
		$revenue_by_step    = array(
			0 => 0.0,
			1 => 0.0,
			2 => 0.0,
		);
		$recoveries_by_step = array(
			0 => 0,
			1 => 0,
			2 => 0,
		);

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

		foreach ( $recovered_sessions as $session ) {
			$step    = $this->get_recovery_step( $session );
			$revenue = floatval( $session->get_meta( '_cartbay_recovered_revenue', true ) );

			$revenue_by_step[ $step ]    = ( $revenue_by_step[ $step ] ?? 0 ) + $revenue;
			$recoveries_by_step[ $step ] = ( $recoveries_by_step[ $step ] ?? 0 ) + 1;
		}

		arsort( $revenue_by_step );
		$best_step = key( $revenue_by_step );
		ksort( $revenue_by_step );

		return array(
			'queued'             => $queued,
			'attempted'          => $attempted,
			'sent'               => $sent,
			'failed'             => $failed,
			'revenue_by_step'    => $revenue_by_step,
			'recoveries_by_step' => $recoveries_by_step,
			'best_step'          => absint( $best_step ) + 1,
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
	 * Count event occurrences in session logs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions Sessions.
	 * @param string               $event    Event name.
	 *
	 * @return int Event count.
	 */
	private function count_events( array $sessions, string $event ): int {
		$count = 0;
		foreach ( $sessions as $session ) {
			foreach ( $this->get_events( $session ) as $entry ) {
				if ( sanitize_key( (string) ( $entry['event'] ?? '' ) ) === $event ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Count event occurrences inside the reporting period.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions        Sessions.
	 * @param string               $event           Event name.
	 * @param int                  $since_timestamp Period start timestamp.
	 *
	 * @return int Event count.
	 */
	private function count_events_since( array $sessions, string $event, int $since_timestamp ): int {
		$count = 0;
		foreach ( $sessions as $session ) {
			foreach ( $this->get_events( $session ) as $entry ) {
				if ( sanitize_key( (string) ( $entry['event'] ?? '' ) ) !== $event ) {
					continue;
				}

				if ( absint( $entry['timestamp'] ?? 0 ) < $since_timestamp ) {
					continue;
				}

				++$count;
			}
		}

		return $count;
	}

	/**
	 * Count sessions restored before recovery.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions Sessions.
	 *
	 * @return int Restored session count.
	 */
	private function count_restored_sessions( array $sessions ): int {
		$count = 0;
		foreach ( $sessions as $session ) {
			if ( $session->get_meta( '_cartbay_restore_clicked_at', true ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Calculate average seconds from abandonment to recovery.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions Sessions.
	 *
	 * @return int Average seconds.
	 */
	private function average_time_to_recovery( array $sessions ): int {
		$total = 0;
		$count = 0;
		foreach ( $sessions as $session ) {
			$abandoned_at = absint( $session->get_meta( '_cartbay_abandoned_at', true ) );
			$recovered_at = absint( $session->get_meta( '_cartbay_recovered_at', true ) );
			if ( $abandoned_at > 0 && $recovered_at > $abandoned_at ) {
				$total += $recovered_at - $abandoned_at;
				++$count;
			}
		}

		return $count > 0 ? absint( round( $total / $count ) ) : 0;
	}

	/**
	 * Count shoppers with more than one activity event.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions Sessions.
	 *
	 * @return int Returning shopper count.
	 */
	private function count_returning_shoppers( array $sessions ): int {
		$count = 0;
		foreach ( $sessions as $session ) {
			if ( count( $this->get_events( $session ) ) > 1 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Count email hashes with repeated abandoned sessions.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, WC_Order> $sessions Sessions.
	 *
	 * @return int Repeat abandoned shopper count.
	 */
	private function count_repeat_abandoned_shoppers( array $sessions ): int {
		$hashes = array();
		foreach ( $sessions as $session ) {
			$hash = sanitize_text_field( (string) $session->get_meta( '_cartbay_email_hash', true ) );
			if ( '' !== $hash ) {
				$hashes[ $hash ] = ( $hashes[ $hash ] ?? 0 ) + 1;
			}
		}

		return count( array_filter( $hashes, static fn ( int $count ): bool => $count > 1 ) );
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

	/**
	 * Get events from a session.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $session Session order.
	 *
	 * @return array<int, array<string, mixed>> Events.
	 */
	private function get_events( WC_Order $session ): array {
		$events = $session->get_meta( '_cartbay_events', true );

		return is_array( $events ) ? array_values( array_filter( $events, 'is_array' ) ) : array();
	}

	/**
	 * Resolve the email step that most likely recovered the session.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $session Session order.
	 *
	 * @return int Zero-based step index.
	 */
	private function get_recovery_step( WC_Order $session ): int {
		$notification_id = sanitize_key( (string) $session->get_meta( '_cartbay_recovered_notification_id', true ) );
		foreach ( $this->get_notifications( $session ) as $notification ) {
			if ( sanitize_key( (string) ( $notification['id'] ?? '' ) ) !== $notification_id ) {
				continue;
			}

			return absint( $notification['step_index'] ?? 0 );
		}

		$sent_steps = (array) $session->get_meta( '_cartbay_sent_steps', true );
		$sent_steps = array_map( 'absint', $sent_steps );

		return empty( $sent_steps ) ? 0 : max( $sent_steps );
	}
}
