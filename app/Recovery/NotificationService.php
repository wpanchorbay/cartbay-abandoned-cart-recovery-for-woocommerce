<?php
/**
 * Notification tracking service.
 *
 * @package WPAnchorBay\CartBay\Recovery
 */

namespace WPAnchorBay\CartBay\Recovery;

use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Data\SessionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks recovery email notification lifecycle states on each session order.
 *
 * @since 1.0.0
 */
class NotificationService {
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
	 * Create or refresh a queued notification entry for a recovery step.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param int    $step_index      Recovery step index.
	 * @param int    $scheduled_at    Scheduled timestamp.
	 * @param string $trigger_source  Trigger source slug.
	 * @param string $notification_id Optional existing notification ID.
	 *
	 * @return string Notification identifier.
	 */
	public function queue( int $session_id, int $step_index, int $scheduled_at, string $trigger_source, string $notification_id = '' ): string {
		$notification_id = '' !== $notification_id ? sanitize_key( $notification_id ) : sanitize_key( wp_generate_password( 12, false, false ) );

		$session = $this->sessions->get( $session_id );
		$email   = $session ? sanitize_email( $session->get_billing_email() ) : '';

		$this->update_notification(
			$session_id,
			$notification_id,
			function ( array $notification ) use ( $step_index, $scheduled_at, $trigger_source, $notification_id, $email, $session_id ): array {
				$notification['id']             = $notification_id;
				$notification['session_id']     = absint( $session_id );
				$notification['step_index']     = absint( $step_index );
				$notification['email_type']     = 'recovery_' . ( absint( $step_index ) + 1 );
				$notification['recipient_hash'] = '' !== $email ? hash( 'sha256', strtolower( trim( $email ) ) ) : '';
				$notification['recipient_mask'] = $this->mask_email( $email );
				$notification['trigger_source'] = sanitize_key( $trigger_source );
				$notification['status']         = 'queued';
				$notification['queued_at']      = absint( $notification['queued_at'] ?? time() );
				$notification['scheduled_at']   = absint( $scheduled_at );
				$notification['last_error']     = '';
				$notification['updated_at']     = time();
				$notification['events']         = isset( $notification['events'] ) && is_array( $notification['events'] ) ? $notification['events'] : array();
				$notification['events'][]       = array(
					'status'    => 'queued',
					'timestamp' => time(),
				);
				$notification['attempts']       = absint( $notification['attempts'] ?? 0 );
				$notification['retry_count']    = absint( $notification['retry_count'] ?? 0 );

				return $notification;
			}
		);

		set_transient( 'cartbay_notification_ctx_' . $notification_id, array( 'session_id' => $session_id ), $this->context_ttl() );

		return $notification_id;
	}

	/**
	 * Mark a notification as being attempted.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param string $notification_id Notification identifier.
	 *
	 * @return void
	 */
	public function mark_attempted( int $session_id, string $notification_id ): void {
		$this->update_notification(
			$session_id,
			$notification_id,
			function ( array $notification ): array {
				$notification['status']       = 'attempted';
				$notification['attempted_at'] = time();
				$notification['updated_at']   = time();
				$notification['attempts']     = absint( $notification['attempts'] ?? 0 ) + 1;
				$notification['events']       = isset( $notification['events'] ) && is_array( $notification['events'] ) ? $notification['events'] : array();
				$notification['events'][]     = array(
					'status'    => 'attempted',
					'timestamp' => time(),
				);

				return $notification;
			}
		);
	}

	/**
	 * Mark a notification as sent.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param string $notification_id Notification identifier.
	 *
	 * @return void
	 */
	public function mark_sent( int $session_id, string $notification_id ): void {
		$this->update_notification(
			$session_id,
			$notification_id,
			function ( array $notification ): array {
				if ( in_array( sanitize_key( (string) ( $notification['status'] ?? '' ) ), array( 'sent', 'delivered' ), true ) && ! empty( $notification['sent_at'] ) ) {
					return $notification;
				}

				$notification['status']     = 'sent';
				$notification['sent_at']    = time();
				$notification['updated_at'] = time();
				$notification['events']     = isset( $notification['events'] ) && is_array( $notification['events'] ) ? $notification['events'] : array();
				$notification['events'][]   = array(
					'status'    => 'sent',
					'timestamp' => time(),
				);

				return $notification;
			}
		);
	}

	/**
	 * Mark a notification as failed.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param string $notification_id Notification identifier.
	 * @param string $error_message   Error summary.
	 *
	 * @return void
	 */
	public function mark_failed( int $session_id, string $notification_id, string $error_message = '' ): void {
		$this->update_notification(
			$session_id,
			$notification_id,
			function ( array $notification ) use ( $error_message ): array {
				if ( 'failed' === sanitize_key( (string) ( $notification['status'] ?? '' ) ) && '' !== (string) ( $notification['last_error'] ?? '' ) ) {
					return $notification;
				}

				$notification['status']     = 'failed';
				$notification['failed_at']  = time();
				$notification['updated_at'] = time();
				$notification['last_error'] = sanitize_text_field( $error_message );
				$notification['events']     = isset( $notification['events'] ) && is_array( $notification['events'] ) ? $notification['events'] : array();
				$notification['events'][]   = array(
					'status'    => 'failed',
					'timestamp' => time(),
					'message'   => sanitize_text_field( $error_message ),
				);

				return $notification;
			}
		);
	}

	/**
	 * Requeue a failed notification as a retry.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param string $notification_id Notification identifier.
	 * @param int    $scheduled_at    Retry timestamp.
	 *
	 * @return void
	 */
	public function mark_retry_queued( int $session_id, string $notification_id, int $scheduled_at ): void {
		$this->update_notification(
			$session_id,
			$notification_id,
			function ( array $notification ) use ( $scheduled_at ): array {
				$notification['status']       = 'retry_queued';
				$notification['scheduled_at'] = absint( $scheduled_at );
				$notification['updated_at']   = time();
				$notification['retry_count']  = absint( $notification['retry_count'] ?? 0 ) + 1;
				$notification['events']       = isset( $notification['events'] ) && is_array( $notification['events'] ) ? $notification['events'] : array();
				$notification['events'][]     = array(
					'status'    => 'retry_queued',
					'timestamp' => time(),
				);

				return $notification;
			}
		);
	}

	/**
	 * Mark a queued notification as canceled.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id Session order ID.
	 * @param string $reason     Cancellation reason.
	 *
	 * @return void
	 */
	public function cancel_pending_for_session( int $session_id, string $reason ): void {
		$notifications = $this->get_session_notifications( $session_id );

		foreach ( $notifications as $notification ) {
			$status = sanitize_key( $notification['status'] ?? '' );
			if ( ! in_array( $status, array( 'queued', 'attempted', 'retry_queued' ), true ) ) {
				continue;
			}

			$this->update_notification(
				$session_id,
				sanitize_key( (string) ( $notification['id'] ?? '' ) ),
				function ( array $entry ) use ( $reason ): array {
					$entry['status']        = 'canceled';
					$entry['cancel_reason'] = sanitize_key( $reason );
					$entry['canceled_at']   = time();
					$entry['updated_at']    = time();
					$entry['events']        = isset( $entry['events'] ) && is_array( $entry['events'] ) ? $entry['events'] : array();
					$entry['events'][]      = array(
						'status'    => 'canceled',
						'timestamp' => time(),
						'message'   => sanitize_key( $reason ),
					);

					return $entry;
				}
			);
		}
	}

	/**
	 * Mark a notification as delivered by a provider integration.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param string $notification_id Notification identifier.
	 * @param string $provider        Provider slug.
	 *
	 * @return void
	 */
	public function mark_delivered( int $session_id, string $notification_id, string $provider = '' ): void {
		$this->update_notification(
			$session_id,
			$notification_id,
			function ( array $notification ) use ( $provider ): array {
				$notification['status']       = 'delivered';
				$notification['delivered_at'] = time();
				$notification['updated_at']   = time();
				$notification['provider']     = sanitize_key( $provider );
				$notification['events']       = isset( $notification['events'] ) && is_array( $notification['events'] ) ? $notification['events'] : array();
				$notification['events'][]     = array(
					'status'    => 'delivered',
					'timestamp' => time(),
					'message'   => sanitize_key( $provider ),
				);

				return $notification;
			}
		);
	}

	/**
	 * Link a restore click to the latest sent notification for a session.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id Session order ID.
	 *
	 * @return string Notification ID when one was linked.
	 */
	public function mark_restore_clicked( int $session_id ): string {
		$notifications = array_reverse( $this->get_session_notifications( $session_id ) );

		foreach ( $notifications as $notification ) {
			if ( ! is_array( $notification ) || ! in_array( sanitize_key( (string) ( $notification['status'] ?? '' ) ), array( 'sent', 'delivered' ), true ) ) {
				continue;
			}

			$notification_id = sanitize_key( (string) ( $notification['id'] ?? '' ) );
			$this->update_notification(
				$session_id,
				$notification_id,
				static function ( array $entry ): array {
					$entry['restore_clicked_at'] = time();
					$entry['updated_at']         = time();
					$entry['events']             = isset( $entry['events'] ) && is_array( $entry['events'] ) ? $entry['events'] : array();
					$entry['events'][]           = array(
						'status'    => 'restore_clicked',
						'timestamp' => time(),
					);

					return $entry;
				}
			);

			return $notification_id;
		}

		return '';
	}

	/**
	 * Link a recovered order to the latest sent notification for a session.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id Session order ID.
	 * @param int $order_id   Recovered WooCommerce order ID.
	 *
	 * @return string Notification ID when one was linked.
	 */
	public function mark_recovered( int $session_id, int $order_id ): string {
		$notifications = array_reverse( $this->get_session_notifications( $session_id ) );

		foreach ( $notifications as $notification ) {
			if ( ! is_array( $notification ) || ! in_array( sanitize_key( (string) ( $notification['status'] ?? '' ) ), array( 'sent', 'delivered' ), true ) ) {
				continue;
			}

			$notification_id = sanitize_key( (string) ( $notification['id'] ?? '' ) );
			$this->update_notification(
				$session_id,
				$notification_id,
				static function ( array $entry ) use ( $order_id ): array {
					$entry['recovered_order_id'] = absint( $order_id );
					$entry['updated_at']         = time();

					return $entry;
				}
			);

			return $notification_id;
		}

		return '';
	}

	/**
	 * Get all notification entries stored on a session order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id Session order ID.
	 *
	 * @return array<int, array<string, mixed>> Session notifications.
	 */
	public function get_session_notifications( int $session_id ): array {
		$session = $this->sessions->get( $session_id );

		if ( ! $session ) {
			return array();
		}

		$notifications = $session->get_meta( '_cartbay_notifications', true );

		return is_array( $notifications ) ? array_values( $notifications ) : array();
	}

	/**
	 * Get the latest notification entry for a specific recovery step.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id Session order ID.
	 * @param int $step_index Recovery step index.
	 *
	 * @return array<string, mixed>|null Notification entry when found.
	 */
	public function get_notification_for_step( int $session_id, int $step_index ): ?array {
		$notifications = array_reverse( $this->get_session_notifications( $session_id ) );

		foreach ( $notifications as $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			if ( absint( $notification['step_index'] ?? -1 ) !== $step_index ) {
				continue;
			}

			return $notification;
		}

		return null;
	}

	/**
	 * Resolve notification context from a mail header.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notification_id Notification identifier.
	 *
	 * @return array<string, int|string>|null Notification context.
	 */
	public function get_context( string $notification_id ): ?array {
		$context = get_transient( 'cartbay_notification_ctx_' . sanitize_key( $notification_id ) );

		return is_array( $context ) ? $context : null;
	}

	/**
	 * Update a single notification entry in session meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $session_id      Session order ID.
	 * @param string   $notification_id Notification identifier.
	 * @param callable $callback        Callback that returns the updated notification.
	 *
	 * @return void
	 */
	private function update_notification( int $session_id, string $notification_id, callable $callback ): void {
		$session = $this->sessions->get( $session_id );

		if ( ! $session || '' === $notification_id ) {
			return;
		}

		$notifications = $session->get_meta( '_cartbay_notifications', true );
		$notifications = is_array( $notifications ) ? array_values( $notifications ) : array();
		$updated       = false;

		foreach ( $notifications as $index => $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			if ( sanitize_key( (string) ( $notification['id'] ?? '' ) ) !== $notification_id ) {
				continue;
			}

			$notifications[ $index ] = $callback( $notification );
			$updated                 = true;
			break;
		}

		if ( ! $updated ) {
			$notifications[] = $callback( array() );
		}

		$session->update_meta_data( '_cartbay_notifications', $notifications );
		$session->save();
		AnalyticsService::invalidate_cache();
	}

	/**
	 * Get notification context TTL for the campaign and retention window.
	 *
	 * @since 1.0.0
	 *
	 * @return int TTL in seconds.
	 */
	private function context_ttl(): int {
		$settings       = get_option( 'cartbay_settings', array() );
		$retention_days = max( 1, absint( $settings['data_retention_days'] ?? 30 ) );

		return $retention_days * DAY_IN_SECONDS;
	}

	/**
	 * Mask an email address for admin display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 *
	 * @return string Masked email.
	 */
	private function mask_email( string $email ): string {
		if ( '' === $email || false === strpos( $email, '@' ) ) {
			return '';
		}

		list( $local, $domain ) = explode( '@', $email, 2 );
		$local_prefix           = substr( $local, 0, 2 );

		return $local_prefix . '***@' . $domain;
	}
}
