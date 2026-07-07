<?php
/**
 * Setup wizard controller.
 *
 * @package WPAnchorBay\CartBay\Admin\Wizard
 */

namespace WPAnchorBay\CartBay\Admin\Wizard;

use WPAnchorBay\CartBay\Admin\Settings\AdminEnvironment;
use WPAnchorBay\CartBay\Core\Container;
use WPAnchorBay\CartBay\Recovery\SequenceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Multi-step setup wizard for CartBay.
 *
 * @since 1.0.0
 */
class WizardController {

	/**
	 * Container instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Notice type to add to the wizard redirect URL.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $redirect_notice_type = '';

	/**
	 * Notice message to add to the wizard redirect URL.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $redirect_notice_message = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Render the wizard.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access CartBay.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		$steps      = self::get_steps();
		$step_count = count( $steps );
		$step       = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		$step       = max( 1, min( $step, $step_count ) );
		$step_keys  = array_keys( $steps );
		$step_key   = $step_keys[ $step - 1 ];

		// Handle form submissions.
		if ( isset( $_POST['cartbay_wizard_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cartbay_wizard_nonce'] ) ), 'cartbay_wizard_step' ) ) {
			$next_step = $this->process_step( $step_key, $step, $step_count );
			if ( $step_count === $step && $next_step > $step_count ) {
				// The final step redirects to dashboard in process_step.
				return;
			}
			wp_safe_redirect( $this->wizard_step_url( $next_step ) );
			exit;
		}

		/**
		 * Filter whether the current wizard step should disable the primary action.
		 *
		 * @since 1.0.0
		 *
		 * @param bool             $blocked Whether the current step is blocked.
		 * @param string           $step_key Current wizard step key.
		 * @param int              $step     Current numeric step.
		 * @param WizardController $wizard   Wizard controller instance.
		 */
		$is_step_blocked = (bool) apply_filters( 'cartbay_wizard_step_blocked', false, $step_key, $step, $this );

		?>
		<div class="wrap cartbay-wizard">
			<h1><?php esc_html_e( 'CartBay Setup Wizard', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h1>

			<ol class="cartbay-wizard__steps" aria-label="<?php esc_attr_e( 'Setup progress', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>">
				<?php $step_num = 0; ?>
				<?php foreach ( $steps as $label ) : ?>
					<?php
					++$step_num;
					$is_active   = $step_num === $step;
					$is_complete = $step_num < $step;
					$state       = $is_active ? 'active' : ( $is_complete ? 'complete' : 'upcoming' );
					$status      = $is_active ? __( 'Current', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) : ( $is_complete ? __( 'Complete', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) : __( 'Upcoming', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
					$classes     = array(
						'cartbay-wizard__step',
						'cartbay-wizard__step--' . $state,
					);
					?>
					<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $is_active ? ' aria-current="step"' : ''; ?>>
						<span class="cartbay-wizard__step-number"><?php echo esc_html( (string) $step_num ); ?></span>
						<span class="cartbay-wizard__step-body">
							<span class="cartbay-wizard__step-label"><?php echo esc_html( $label ); ?></span>
							<span class="cartbay-wizard__step-status"><?php echo esc_html( $status ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="cartbay-wizard__content">
				<?php $this->render_wizard_context_notices(); ?>

				<form method="post">
					<input type="hidden" name="cartbay_wizard_nonce" value="<?php echo esc_attr( wp_create_nonce( 'cartbay_wizard_step' ) ); ?>" />
					<input type="hidden" name="cartbay_wizard_step" value="<?php echo esc_attr( (string) $step ); ?>" />

					<?php $this->render_step( $step_key ); ?>

					<p class="cartbay-wizard__actions">
						<?php if ( $step > 1 ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cartbay-wizard&step=' . ( $step - 1 ) ) ); ?>" class="button">
								<?php esc_html_e( 'Back', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
							</a>
						<?php endif; ?>
						<button type="submit" class="button button-primary"<?php echo $is_step_blocked ? ' disabled="disabled"' : ''; ?>>
							<?php echo $step_count === $step ? esc_html__( 'Finish Setup', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) : esc_html__( 'Next', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
						</button>
					</p>
				</form>
			</div>

			<a
				href="<?php echo esc_url( CARTBAY_DOCS_URL ); ?>"
				target="_blank"
				rel="noopener noreferrer"
				style="display: inline-block; margin-top: 12px;"
			>
				<?php esc_html_e( 'Documentation', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
			</a>

		</div>
		<?php
	}

	/**
	 * Render a specific wizard step.
	 *
	 * @since 1.0.0
	 *
	 * @param string $step_key Step key.
	 *
	 * @return void
	 */
	private function render_step( string $step_key ): void {
		switch ( $step_key ) {
			case 'welcome':
				$this->render_welcome();
				break;
			case 'consent':
				$this->render_consent();
				break;
			case 'email':
				$this->render_email();
				break;
			case 'launch':
				$this->render_launch();
				break;
			default:
				do_action( 'cartbay_wizard_render_step', $step_key, $this );
				break;
		}
	}

	/**
	 * Render Step 1: Welcome.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_welcome(): void {
		?>
		<h2><?php esc_html_e( 'Welcome to CartBay!', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'CartBay helps you recover abandoned carts with a 3-email recovery sequence. Let\'s set it up in under 5 minutes.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
		<p><?php esc_html_e( 'Click "Next" to get started.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render Step 3: Consent & Timing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_consent(): void {
		$settings = get_option( 'cartbay_settings', array() );
		$campaign = SequenceSettings::normalize( get_option( 'cartbay_campaign_settings', array() ) );
		?>
		<h2><?php esc_html_e( 'Consent & Timing', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="cartbay_consent_text"><?php esc_html_e( 'Consent Text', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></label>
					<?php $this->render_help_tip( __( 'This text appears beside the checkout consent checkbox. It tells shoppers that CartBay may save their email and cart so the store can send recovery reminders if they leave checkout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ); ?>
				</th>
				<td>
					<textarea name="cartbay_consent_text" id="cartbay_consent_text" rows="2" class="large-text"><?php echo esc_textarea( $settings['consent_text'] ?? '' ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cartbay_abandonment_timeout"><?php esc_html_e( 'Abandonment Timeout (minutes)', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></label>
					<?php $this->render_help_tip( __( 'CartBay waits this long after the shopper last interacts with checkout before marking the cart abandoned. Shorter values start recovery sooner; longer values reduce the chance of emailing someone who is still checking out.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ); ?>
				</th>
				<td>
					<input type="number" name="cartbay_abandonment_timeout" id="cartbay_abandonment_timeout" value="<?php echo esc_attr( $settings['abandonment_timeout'] ?? 30 ); ?>" min="5" max="1440" class="small-text" />
					<p class="description"><?php esc_html_e( 'How long before a captured cart is considered abandoned.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Recovery Email Schedule', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
				<td>
					<div class="cartbay-wizard__schedule" aria-label="<?php esc_attr_e( 'Recovery email schedule', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>">
						<?php foreach ( $campaign['steps'] as $index => $sequence_step ) : ?>
							<?php
							$delay_parts = SequenceSettings::get_delay_parts( absint( $sequence_step['delay_minutes'] ?? 0 ) );
							$tooltip     = $this->recovery_delay_tooltip( $index );
							?>
							<div class="cartbay-wizard__schedule-row">
								<span class="cartbay-wizard__schedule-label">
									<label for="cartbay_wizard_delay_value_<?php echo esc_attr( (string) $index ); ?>">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: recovery email step number. */
												__( 'Email %d sends after', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
												$index + 1
											)
										);
										?>
									</label>
									<?php $this->render_help_tip( $tooltip ); ?>
								</span>
								<input type="number" class="small-text" id="cartbay_wizard_delay_value_<?php echo esc_attr( (string) $index ); ?>" name="cartbay_campaign_settings[steps][<?php echo esc_attr( (string) $index ); ?>][delay_value]" min="1" max="999" value="<?php echo esc_attr( (string) $delay_parts['value'] ); ?>" />
								<select name="cartbay_campaign_settings[steps][<?php echo esc_attr( (string) $index ); ?>][delay_unit]">
									<option value="minutes" <?php selected( 'minutes', $delay_parts['unit'] ); ?>><?php esc_html_e( 'minutes', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></option>
									<option value="hours" <?php selected( 'hours', $delay_parts['unit'] ); ?>><?php esc_html_e( 'hours', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></option>
									<option value="days" <?php selected( 'days', $delay_parts['unit'] ); ?>><?php esc_html_e( 'days', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></option>
								</select>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'These starter intervals control when each recovery email is sent after the cart becomes abandoned. You can refine message focus and coupons later in CartBay settings.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Step 4: Email Delivery.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_email(): void {
		$environment = $this->container->make( AdminEnvironment::class );
		if ( ! $environment instanceof AdminEnvironment ) {
			return;
		}

		$status       = $environment->get_mail_environment_status();
		$current_user = wp_get_current_user();
		$target_email = isset( $current_user->user_email ) ? sanitize_email( (string) $current_user->user_email ) : '';
		if ( '' === $target_email ) {
			$target_email = sanitize_email( (string) get_option( 'admin_email' ) );
		}
		?>
		<h2><?php esc_html_e( 'Email Delivery', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h2>

		<p class="description"><?php esc_html_e( 'CartBay hands recovery emails to WordPress and WooCommerce for delivery — it does not send email itself. If sending is unreliable, the fix is your site\'s mail setup, not CartBay.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>

		<?php if ( ! empty( $status['has_delivery'] ) ) : ?>
			<div class="notice notice-success inline is-dismissible cartbay-notice-auto-dismiss">
				<p><?php esc_html_e( 'SMTP delivery detected. Your emails should deliver reliably.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
			</div>
		<?php elseif ( ! empty( $status['has_logger'] ) ) : ?>
			<div class="notice notice-warning inline is-dismissible cartbay-notice-auto-dismiss">
				<p>
					<strong><?php esc_html_e( 'An email logging plugin is active, but no SMTP delivery service was detected.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Emails to buyers may not be delivered reliably.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
					<a href="<?php echo esc_url( CARTBAY_DOCS_EMAIL_SETUP_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn how to set up reliable email delivery', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></a>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning inline is-dismissible cartbay-notice-auto-dismiss">
				<p>
					<strong><?php esc_html_e( 'No SMTP plugin detected.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'Without an SMTP service, recovery emails may land in spam. Consider installing an SMTP plugin.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
					<a href="<?php echo esc_url( CARTBAY_DOCS_EMAIL_SETUP_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn how to set up reliable email delivery', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<p class="cartbay-wizard__test-email">
			<label for="cartbay-test-email-address"><?php esc_html_e( 'Send to:', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></label>
			<input type="email" id="cartbay-test-email-address" class="regular-text" value="<?php echo esc_attr( $target_email ); ?>" placeholder="<?php esc_attr_e( 'email@example.com', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>" />
			<button type="button" id="cartbay-test-email" class="button">
				<?php esc_html_e( 'Send Test Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
			</button>
			<span id="cartbay-test-email-result"></span>
		</p>
		<p class="description"><?php esc_html_e( 'Enter an email address and click to send a test email and verify delivery.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Render Step 5: Launch.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_launch(): void {
		$campaign = get_option( 'cartbay_campaign_settings', array() );
		$enabled  = ! empty( $campaign['enabled'] );
		?>
		<h2><?php esc_html_e( 'Launch CartBay', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Recovery Emails', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="cartbay_campaign_enabled" value="1" <?php checked( $enabled ); ?> />
						<?php esc_html_e( 'Start sending recovery emails to abandoned carts', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'You can change this and other settings later from the CartBay Settings page.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Process a wizard step submission.
	 *
	 * @since 1.0.0
	 *
	 * @param string $step_key   Current step key.
	 * @param int    $step       Current numeric step.
	 * @param int    $step_count Total step count.
	 *
	 * @return int Next step.
	 */
	private function process_step( string $step_key, int $step, int $step_count ): int {
		/**
		 * Let extensions process custom wizard steps.
		 *
		 * Return an integer step number to override default progression.
		 *
		 * @since 1.0.0
		 *
		 * @param int|null         $next_step Extension-selected next step or null.
		 * @param string           $step_key  Current wizard step key.
		 * @param int              $step      Current numeric step.
		 * @param WizardController $wizard    Wizard controller instance.
		 */
		$extension_next_step = apply_filters( 'cartbay_wizard_process_step', null, $step_key, $step, $this );

		if ( is_int( $extension_next_step ) ) {
			return $extension_next_step;
		}

		switch ( $step_key ) {
			case 'consent':
				$settings = get_option( 'cartbay_settings', array() );
				// phpcs:ignore WordPress.Security.NonceVerification -- nonce verified in render().
				$settings['consent_text'] = sanitize_text_field( wp_unslash( $_POST['cartbay_consent_text'] ?? '' ) );
				// phpcs:ignore WordPress.Security.NonceVerification -- nonce verified in render().
				$settings['abandonment_timeout'] = absint( $_POST['cartbay_abandonment_timeout'] ?? 30 );
				update_option( 'cartbay_settings', $settings );

				$existing_campaign = get_option( 'cartbay_campaign_settings', array() );
				// phpcs:ignore WordPress.Security.NonceVerification -- nonce verified in render().
				$posted_campaign = isset( $_POST['cartbay_campaign_settings'] ) ? map_deep( wp_unslash( $_POST['cartbay_campaign_settings'] ), 'sanitize_text_field' ) : array();
				$campaign        = is_array( $existing_campaign ) ? $existing_campaign : array();
				if ( is_array( $posted_campaign ) && isset( $posted_campaign['steps'] ) ) {
					$campaign['steps'] = $posted_campaign['steps'];
				}
				update_option( 'cartbay_campaign_settings', SequenceSettings::normalize( $campaign ) );
				break;
			case 'launch':
				$campaign = get_option( 'cartbay_campaign_settings', array() );
				// phpcs:ignore WordPress.Security.NonceVerification -- nonce verified in render().
				$campaign['enabled'] = ! empty( $_POST['cartbay_campaign_enabled'] );
				update_option( 'cartbay_campaign_settings', $campaign );
				update_option( 'cartbay_wizard_complete', true );

				// Redirect to WC settings.
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=cartbay' ) );
				exit;
		}

		return min( $step + 1, $step_count );
	}

	/**
	 * Build a wizard step URL, including any queued action notice.
	 *
	 * @since 1.0.0
	 *
	 * @param int $step Wizard step number.
	 *
	 * @return string Wizard step admin URL.
	 */
	private function wizard_step_url( int $step ): string {
		$args = array(
			'page' => 'cartbay-wizard',
			'step' => $step,
		);

		if ( '' !== $this->redirect_notice_message ) {
			$query_arg          = 'error' === $this->redirect_notice_type ? 'wc_error' : 'wc_message';
			$args[ $query_arg ] = sanitize_text_field( $this->redirect_notice_message );
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Queue a wizard action notice for the next redirect.
	 *
	 * Part of the wizard's public extension API: the controller instance is
	 * passed to the `cartbay_wizard_process_step` filter so extensions that add
	 * their own wizard steps can surface a success or error notice on redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 *
	 * @return void
	 */
	public function set_redirect_notice( string $type, string $message ): void {
		$this->redirect_notice_type    = 'error' === $type ? 'error' : 'success';
		$this->redirect_notice_message = sanitize_text_field( $message );
	}

	/**
	 * Render wizard action notices from the redirect URL.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_wizard_context_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice display from a post/redirect/get URL.
		$error_message = isset( $_GET['wc_error'] ) ? sanitize_text_field( wp_unslash( $_GET['wc_error'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice display from a post/redirect/get URL.
		$success_message = isset( $_GET['wc_message'] ) ? sanitize_text_field( wp_unslash( $_GET['wc_message'] ) ) : '';

		if ( '' !== $error_message ) {
			$this->render_wizard_inline_notice( 'error', $error_message );
			return;
		}

		if ( '' !== $success_message ) {
			$this->render_wizard_inline_notice( 'success', $success_message );
		}
	}

	/**
	 * Render a WooCommerce-compatible inline wizard notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 *
	 * @return void
	 */
	private function render_wizard_inline_notice( string $type, string $message ): void {
		$type    = in_array( $type, array( 'success', 'error' ), true ) ? $type : 'success';
		$classes = 'notice notice-' . $type . ' inline';

		echo '<div class="' . esc_attr( $classes ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render a compact wizard help tooltip.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Tooltip help message.
	 *
	 * @return void
	 */
	private function render_help_tip( string $message ): void {
		echo '<span class="cartbay-wizard-help-tip" tabindex="0" role="img" aria-label="' . esc_attr( $message ) . '" data-tip="' . esc_attr( $message ) . '">?</span>';
	}

	/**
	 * Get the recovery delay tooltip for a sequence step.
	 *
	 * @since 1.0.0
	 *
	 * @param int $index Zero-based recovery email index.
	 *
	 * @return string Tooltip text.
	 */
	private function recovery_delay_tooltip( int $index ): string {
		$tooltips = array(
			__( 'The first recovery email should arrive while the shopper still remembers the cart. Use a shorter delay for a quick reminder without discounting too early.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			__( 'The second email gives shoppers time to compare options before following up. Use this interval to avoid making the sequence feel too aggressive.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			__( 'The final email is the later reminder and usually carries the strongest recovery message. A longer delay keeps it from feeling repetitive.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);

		return $tooltips[ $index ] ?? $tooltips[0];
	}

	/**
	 * Get the ordered wizard steps.
	 *
	 * Public and static so other admin surfaces (e.g. the global SMTP notice in
	 * SettingsPage) can resolve the current step key without depending on step
	 * numbers, which shift when Pro injects its own step via `cartbay_wizard_steps`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Wizard labels keyed by step ID.
	 */
	public static function get_steps(): array {
		$steps = array(
			'welcome' => __( 'Welcome', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'consent' => __( 'Consent & Timing', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'email'   => __( 'Email Delivery', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'launch'  => __( 'Launch', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);

		/**
		 * Filter the setup wizard steps.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $steps Wizard labels keyed by step ID.
		 */
		return apply_filters( 'cartbay_wizard_steps', $steps );
	}
}
