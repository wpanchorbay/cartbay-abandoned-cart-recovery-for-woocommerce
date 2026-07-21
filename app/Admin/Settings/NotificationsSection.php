<?php
/**
 * Notifications settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Admin\Settings\MailEnvironmentDetector;
use WPAnchorBay\CartBay\Email\RecoveryEmailSender;

defined( 'ABSPATH' ) || exit;

/**
 * Notifications section with recovery email activity logs.
 *
 * @since 1.0.0
 */
class NotificationsSection extends AbstractSettingsSection {
	/**
	 * Analytics service.
	 *
	 * @since 1.0.0
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics_service;

	/**
	 * Settings URL helper.
	 *
	 * @since 1.0.0
	 *
	 * @var SettingsUrl
	 */
	private SettingsUrl $url;

	/**
	 * Mail environment detector.
	 *
	 * @since 1.0.0
	 *
	 * @var MailEnvironmentDetector
	 */
	private MailEnvironmentDetector $mail_detector;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SettingsUrl             $url               Settings URL helper.
	 * @param AnalyticsService        $analytics_service Analytics service.
	 * @param MailEnvironmentDetector $mail_detector     Mail environment detector.
	 */
	public function __construct( SettingsUrl $url, AnalyticsService $analytics_service, MailEnvironmentDetector $mail_detector ) {
		$this->url               = $url;
		$this->analytics_service = $analytics_service;
		$this->mail_detector     = $mail_detector;
	}

	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string {
		return 'notifications';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Notifications', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
	}

	/**
	 * Get WooCommerce settings API fields for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Section fields.
	 */
	public function fields(): array {
		return array();
	}

	/**
	 * Render the Notifications section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$period = isset( $_GET['period'] ) ? absint( wp_unslash( $_GET['period'] ) ) : 30;

		if ( ! in_array( $period, array( 7, 30, 90 ), true ) ) {
			$period = 30;
		}

		$analytics = $this->analytics_service->get( $period );
		$base_url  = $this->url->section( 'notifications' );

		echo '<h2>' . esc_html__( 'Notifications', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</h2>';
		echo '<p class="description cartbay-section-description">' . esc_html__( 'Monitor recovery email health: how many emails are queued, sent, and failed, and verify your store can deliver mail. Sent means WordPress/WooCommerce accepted the email; provider-confirmed delivery is reserved for future integrations.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</p>';
		$this->render_period_selector( $base_url, $period );
		echo '<div class="cartbay-card-grid cartbay-notification-stats" aria-label="' . esc_attr__( 'Notification activity summary', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">';
		$this->render_notification_stat_cards( $analytics );
		echo '</div>';

		/**
		 * Fires after the notification health summary, before the delivery test.
		 *
		 * Extensions can render additional notification reporting here (for
		 * example a filterable per-notification lifecycle table, queue inspector,
		 * or manual resend controls). The current view context is passed so
		 * extensions can honour the selected period.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $context Notifications view context (period, base_url).
		 */
		do_action(
			'cartbay_notifications_after_summary',
			array(
				'period'   => $period,
				'base_url' => $base_url,
			)
		);

		$this->render_test_email_delivery();
	}

	/**
	 * Render test email delivery status and send button.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_test_email_delivery(): void {
		$mail_status    = $this->mail_detector->detect();
		$admin_email    = sanitize_email( (string) wp_get_current_user()->user_email );
		$sender         = RecoveryEmailSender::resolve();
		$from_email     = $sender['email'];
		$from_name      = $sender['name'];
		$delivery_label = '';
		$delivery_class = 'notice-warning';

		if ( '' === $admin_email ) {
			$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );
		}

		$learn_more_link = sprintf(
			' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( CARTBAY_DOCS_EMAIL_SETUP_URL ),
			esc_html__( 'Learn how to set up reliable email delivery', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
		);

		if ( ! empty( $mail_status['has_delivery'] ) ) {
			$delivery_class = 'notice-success';
			$delivery_label = __( 'SMTP delivery detected.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
			if ( ! empty( $mail_status['delivery']['detail'] ) ) {
				$delivery_label .= ' ' . $mail_status['delivery']['detail'];
			}
		} elseif ( ! empty( $mail_status['has_logger'] ) ) {
			$delivery_label = __( 'Email logging detected, but no SMTP delivery service. Emails to buyers may not be delivered reliably.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . $learn_more_link;
		} else {
			$delivery_label = __( 'No SMTP plugin detected. Without an SMTP service, recovery emails may land in spam.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . $learn_more_link;
		}
		?>
		<div class="cartbay-test-delivery-section">
			<h3><?php esc_html_e( 'Email Delivery Test', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h3>

			<p class="description"><?php esc_html_e( 'CartBay hands recovery emails to WordPress and WooCommerce for delivery — it does not send email itself. If sending is unreliable, the fix is your site\'s mail setup, not CartBay.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>

			<div class="notice inline <?php echo esc_attr( $delivery_class ); ?>">
				<p><?php echo wp_kses_post( $delivery_label ); ?></p>
			</div>

			<table class="widefat striped" style="margin: 12px 0; max-width: 480px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'From email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
						<td><code><?php echo esc_html( $from_email ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'From name', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $from_name ); ?></td>
					</tr>
					<?php if ( ! empty( $mail_status['delivery']['detail'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Delivery service', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
						<td><?php echo esc_html( $mail_status['delivery']['detail'] ); ?></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="description">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: link to the WooCommerce email settings screen. */
						__( 'Recovery emails are sent with your WooCommerce store sender details. Change the From name and address in %s.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
						sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ),
							esc_html__( 'WooCommerce → Settings → Emails', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
						)
					)
				);
				?>
			</p>

			<p>
				<label for="cartbay-test-email-address"><?php esc_html_e( 'Send to:', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></label>
				<input type="email" id="cartbay-test-email-address" class="regular-text" value="<?php echo esc_attr( $admin_email ); ?>" placeholder="<?php esc_attr_e( 'email@example.com', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>" />
				<button type="button" id="cartbay-test-email-notifications" class="button">
					<?php esc_html_e( 'Send Test Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
				</button>
				<span id="cartbay-test-email-result" class="cartbay-test-email-result"></span>
			</p>
			<p class="description"><?php esc_html_e( 'Enter an email address and click to send a test email and verify delivery.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render notification reporting period selector.
	 *
	 * @since 1.0.0
	 *
	 * @param string $base_url Base section URL.
	 * @param int    $period   Current period.
	 *
	 * @return void
	 */
	private function render_period_selector( string $base_url, int $period ): void {
		$periods = array(
			7  => __( '7 Days', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			30 => __( '30 Days', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			90 => __( '90 Days', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);

		echo '<div class="cartbay-button-group">';
		foreach ( $periods as $days => $label ) {
			$url   = add_query_arg( 'period', $days, $base_url );
			$class = $days === $period ? 'button button-primary' : 'button';
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Render the notification health summary cards.
	 *
	 * Extensions can add deeper reporting (step-level performance, status-level
	 * breakdowns, etc.) via the `cartbay_notifications_after_summary` hook.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $analytics Period-scoped analytics data.
	 *
	 * @return void
	 */
	private function render_notification_stat_cards( array $analytics ): void {
		$cards = array(
			array(
				'label'   => __( 'Pending Queue', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => number_format_i18n( absint( $analytics['emails_queued'] ?? 0 ) ),
				'tooltip' => __( 'Current pending recovery emails: first-time queued notifications plus retry-queued notifications.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
			array(
				'label'   => __( 'Emails Sent', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => number_format_i18n( absint( $analytics['emails_sent'] ?? 0 ) ),
				'tooltip' => __( 'Accepted or provider-delivered recovery emails tied to sessions in the selected period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
			array(
				'label'   => __( 'Emails Failed', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => number_format_i18n( absint( $analytics['emails_failed'] ?? 0 ) ),
				'tooltip' => __( 'Recovery emails tied to sessions in the selected period that ended in failed status.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
			array(
				'label'   => __( 'Email Acceptance Rate', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => (string) ( $analytics['email_send_rate'] ?? 0 ) . '%',
				'tooltip' => __( 'Accepted or delivered recovery emails divided by attempted recovery email sends.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
		);

		foreach ( $cards as $card ) {
			$this->render_metric_card( (string) $card['label'], (string) $card['value'], (string) $card['tooltip'], false, 'cartbay-notification-stat' );
		}
	}
}
