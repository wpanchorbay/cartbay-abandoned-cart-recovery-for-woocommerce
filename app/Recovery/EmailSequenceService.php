<?php
/**
 * Email sequence service.
 *
 * @package WPAnchorBay\CartBay\Recovery
 */

namespace WPAnchorBay\CartBay\Recovery;

use WPAnchorBay\CartBay\Data\SessionRepository;
use WPAnchorBay\CartBay\Utils\Logger;
use WPAnchorBay\CartBay\Utils\TokenHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Handles resolution and sending of a recovery email sequence step.
 *
 * @since 1.0.0
 */
class EmailSequenceService {

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
	 * Send a specific step in the recovery sequence.
	 *
	 * Called by the cartbay_send_recovery_email Action Scheduler action.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id CartBay session order ID.
	 * @param int $step_index 0-based step index.
	 *
	 * @return void
	 */
	public function send_step( int $session_id, int $step_index ): void {
		$session = wc_get_order( $session_id );

		if ( ! $session ) {
			return;
		}

		// Guard: only send if still abandoned. WC_Order::get_status() is unprefixed.
		if ( 'cartbay-abandoned' !== $session->get_status() ) {
			$this->notifications->cancel_pending_for_session( $session_id, 'status_changed' );
			$this->sessions->add_event(
				$session_id,
				'email_skipped',
				array(
					'step'   => $step_index,
					'reason' => 'status_changed',
					'status' => $session->get_status(),
				)
			);
			Logger::info(
				"Step {$step_index} skipped: session not abandoned.",
				array(
					'session_id' => $session_id,
					'status'     => $session->get_status(),
				),
				'emails'
			);
			return;
		}

		// Guard: check suppression.
		$email            = $session->get_billing_email();
		$suppression_hash = TokenHelper::hash_email( $email );
		if ( get_page_by_path( $suppression_hash, OBJECT, 'cartbay_suppressed' ) ) {
			$this->notifications->cancel_pending_for_session( $session_id, 'suppressed' );
			$this->sessions->add_event(
				$session_id,
				'email_skipped',
				array(
					'step'   => $step_index,
					'reason' => 'suppressed',
				)
			);
			Logger::info(
				"Step {$step_index} skipped: email suppressed.",
				array( 'session_id' => $session_id ),
				'emails'
			);
			return;
		}

		// Guard: check if step already sent.
		$sent_steps = (array) $session->get_meta( '_cartbay_sent_steps', true );
		if ( in_array( $step_index, $sent_steps, true ) ) {
			$this->sessions->add_event(
				$session_id,
				'email_skipped',
				array(
					'step'   => $step_index,
					'reason' => 'duplicate_step',
				)
			);
			return;
		}

		$notification = $this->notifications->get_notification_for_step( $session_id, $step_index );

		if ( ! is_array( $notification ) ) {
			$notification_id = $this->notifications->queue( $session_id, $step_index, time(), 'manual_rebuild' );
		} else {
			$notification_id = sanitize_key( (string) ( $notification['id'] ?? '' ) );
		}

		$campaign      = get_option( 'cartbay_campaign_settings', array() );
		$step_config   = $campaign['steps'][ $step_index ] ?? array();
		$coupon_code   = '';
		$coupon_expiry = '';

		// Include the configured static coupon code when the step opts in.
		// Dynamic per-session coupon generation (unique codes, expiry, auto-apply)
		// is a CartBay Pro feature added via the `cartbay_email_coupon` filter.
		if ( ! empty( $step_config['coupon_enabled'] ) ) {
			$settings    = get_option( 'cartbay_settings', array() );
			$static_code = isset( $settings['static_coupon_code'] ) ? sanitize_text_field( (string) $settings['static_coupon_code'] ) : '';

			/**
			 * Filter the coupon included in a recovery email step.
			 *
			 * Free supplies the configured static coupon code with no expiry.
			 * CartBay Pro filters this to generate a unique, expiring,
			 * per-session coupon and return its code and expiry.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, string> $coupon     Coupon data: 'code' and 'expiry'.
			 * @param int                   $session_id CartBay session ID.
			 * @param int                   $step_index Zero-based sequence step index.
			 */
			$coupon = apply_filters(
				'cartbay_email_coupon',
				array(
					'code'   => $static_code,
					'expiry' => '',
				),
				$session_id,
				$step_index
			);

			$coupon_code   = isset( $coupon['code'] ) ? sanitize_text_field( (string) $coupon['code'] ) : '';
			$coupon_expiry = isset( $coupon['expiry'] ) ? sanitize_text_field( (string) $coupon['expiry'] ) : '';
		}

		// Generate restore token.
		$token       = TokenHelper::create_restore_token( $session_id );
		$restore_url = add_query_arg( array( 'cartbay_restore' => $token ), home_url( '/' ) );

		// Generate unsubscribe token.
		$unsub_token = TokenHelper::generate();
		$session->update_meta_data( '_cartbay_unsub_token_hash', TokenHelper::hash( $unsub_token ) );
		$session->save();
		$unsubscribe_url = add_query_arg( array( 'cartbay_unsubscribe' => $unsub_token ), home_url( '/' ) );

		// Get the email class for this step.
		$email_class_map = array(
			0 => 'CartBay_Email_Recovery_1',
			1 => 'CartBay_Email_Recovery_2',
			2 => 'CartBay_Email_Recovery_3',
		);
		$email_class     = $email_class_map[ $step_index ] ?? null;
		if ( ! $email_class ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();
		if ( ! isset( $emails[ $email_class ] ) ) {
			$this->notifications->mark_failed( $session_id, $notification_id, __( 'Recovery email class is unavailable.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
			Logger::error(
				'Recovery email class is unavailable.',
				array(
					'session_id' => $session_id,
					'step'       => $step_index,
				),
				'emails'
			);
			return;
		}

		$this->notifications->mark_attempted( $session_id, $notification_id );

		if ( property_exists( $emails[ $email_class ], 'notification_id' ) ) {
			$emails[ $email_class ]->notification_id = $notification_id;
		}

		$sent = (bool) $emails[ $email_class ]->trigger( $session_id, $restore_url, $coupon_code, $coupon_expiry, $unsubscribe_url );

		if ( ! $sent ) {
			$this->notifications->mark_failed( $session_id, $notification_id, __( 'WordPress mail did not confirm the email send.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
			Logger::error(
				'Recovery email send failed.',
				array(
					'session_id'      => $session_id,
					'step'            => $step_index,
					'notification_id' => $notification_id,
				),
				'emails'
			);
			$this->sessions->add_event(
				$session_id,
				'email_failed',
				array(
					'step'            => $step_index,
					'notification_id' => $notification_id,
				)
			);
			$this->maybe_schedule_retry( $session_id, $step_index, $notification_id );
			return;
		}

		$this->notifications->mark_sent( $session_id, $notification_id );

		// Mark step as sent.
		$sent_steps[] = $step_index;
		$sent_steps   = array_values( array_unique( array_map( 'absint', $sent_steps ) ) );
		$session->update_meta_data( '_cartbay_sent_steps', $sent_steps );
		$session->save();

		$this->sessions->add_event(
			$session_id,
			'email_sent',
			array(
				'step'            => $step_index,
				'notification_id' => $notification_id,
			)
		);

		Logger::info(
			"Recovery email step {$step_index} sent.",
			array(
				'session_id'      => $session_id,
				'notification_id' => $notification_id,
			),
			'emails'
		);
	}

	/**
	 * Queue a retry attempt for a failed recovery email when limits allow.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      Session order ID.
	 * @param int    $step_index      Recovery step index.
	 * @param string $notification_id Notification identifier.
	 *
	 * @return void
	 */
	private function maybe_schedule_retry( int $session_id, int $step_index, string $notification_id ): void {
		$notification = $this->notifications->get_notification_for_step( $session_id, $step_index );
		$attempts     = is_array( $notification ) ? absint( $notification['attempts'] ?? 0 ) : 0;

		if ( $attempts >= 3 || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$scheduled_at = time() + ( 15 * $attempts * MINUTE_IN_SECONDS );
		as_schedule_single_action(
			$scheduled_at,
			'cartbay_send_recovery_email',
			array( $session_id, $step_index ),
			'cartbay'
		);
		$this->notifications->mark_retry_queued( $session_id, $notification_id, $scheduled_at );
	}
}
