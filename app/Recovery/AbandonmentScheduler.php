<?php
/**
 * Abandonment scheduler.
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
 * Action Scheduler job: detect and mark abandoned sessions.
 *
 * @since 1.0.0
 */
class AbandonmentScheduler {

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
	 * @param SessionRepository   $sessions      Session repository.
	 * @param NotificationService $notifications Notification tracking service.
	 */
	public function __construct( SessionRepository $sessions, NotificationService $notifications ) {
		$this->sessions      = $sessions;
		$this->notifications = $notifications;
	}

	/**
	 * Main job callback. Runs every 5 minutes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		$settings = get_option( 'cartbay_settings', array() );
		$timeout  = absint( $settings['abandonment_timeout'] ?? 30 );

		$inactive = $this->sessions->get_inactive_captured( $timeout );

		foreach ( $inactive as $session ) {
			$this->mark_abandoned( $session );
		}
	}

	/**
	 * Check one captured session at its scheduled abandonment boundary.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id CartBay session order ID.
	 *
	 * @return void
	 */
	public function run_for_session( int $session_id ): void {
		$session = $this->sessions->get( $session_id );

		if ( ! $session instanceof WC_Order || 'cartbay-captured' !== $session->get_status() ) {
			return;
		}

		$settings         = get_option( 'cartbay_settings', array() );
		$timeout_seconds  = absint( $settings['abandonment_timeout'] ?? 30 ) * MINUTE_IN_SECONDS;
		$last_activity_at = absint( $session->get_meta( '_cartbay_last_activity_at', true ) );
		if ( 0 === $last_activity_at ) {
			$created_at       = $session->get_date_created();
			$last_activity_at = $created_at ? $created_at->getTimestamp() : time();
		}

		$scheduled_at = $last_activity_at + $timeout_seconds;
		if ( $scheduled_at > time() ) {
			$this->schedule_session_check( $session->get_id(), $scheduled_at );
			return;
		}

		$this->mark_abandoned( $session );
	}

	/**
	 * Transition a captured session to abandoned and schedule the recovery sequence.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $session WC order representing the cart session.
	 *
	 * @return void
	 */
	private function mark_abandoned( WC_Order $session ): void {
		$campaign = get_option( 'cartbay_campaign_settings', array() );

		$session->set_status( 'wc-cartbay-abandoned' );
		$session->update_meta_data( '_cartbay_abandoned_at', time() );
		$session->save();

		Logger::info( 'Session marked as abandoned.', array( 'session_id' => $session->get_id() ), 'abandonment' );

		$this->sessions->add_event( $session->get_id(), 'abandoned' );
		AnalyticsService::invalidate_cache();

		if ( ! empty( $campaign['enabled'] ) ) {
			$this->schedule_sequence( $session->get_id(), $campaign['steps'] ?? array() );
		}
	}

	/**
	 * Schedule Action Scheduler jobs for each recovery email step.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $session_id Session order ID.
	 * @param array $steps       Recovery step configurations.
	 *
	 * @return void
	 */
	private function schedule_sequence( int $session_id, array $steps ): void {
		foreach ( $steps as $index => $step ) {
			$delay_seconds = absint( $step['delay_minutes'] ?? 60 ) * MINUTE_IN_SECONDS;
			$scheduled_at  = time() + $delay_seconds;

			// Prevent duplicate scheduling.
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'cartbay_send_recovery_email', array( $session_id, $index ), 'cartbay' ) ) {
				continue;
			}

			if ( ! function_exists( 'as_schedule_single_action' ) ) {
				continue;
			}

			as_schedule_single_action(
				$scheduled_at,
				'cartbay_send_recovery_email',
				array( $session_id, $index ),
				'cartbay'
			);

			$this->notifications->queue( $session_id, (int) $index, $scheduled_at, 'abandonment_sequence' );
		}
	}

	/**
	 * Schedule a precise abandonment check for one session.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id   Session order ID.
	 * @param int $scheduled_at Scheduled timestamp.
	 *
	 * @return void
	 */
	private function schedule_session_check( int $session_id, int $scheduled_at ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( 'cartbay_detect_session_abandonment', array( $session_id ), 'cartbay' ) ) {
			return;
		}

		as_schedule_single_action(
			$scheduled_at,
			'cartbay_detect_session_abandonment',
			array( $session_id ),
			'cartbay'
		);
	}
}
