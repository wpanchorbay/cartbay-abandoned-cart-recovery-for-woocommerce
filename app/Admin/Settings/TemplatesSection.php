<?php
/**
 * Templates settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

use WPAnchorBay\CartBay\Core\Settings;
use WPAnchorBay\CartBay\Recovery\SequenceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Templates section for recovery email customization.
 *
 * Links each recovery email to its WooCommerce-native email settings page
 * where the merchant can edit subject, heading, preheader, body, button text,
 * logo, colors, footer, preview, and test-send.
 *
 * @since 1.0.0
 */
class TemplatesSection extends AbstractSettingsSection {

	/**
	 * Map step index to the WooCommerce email class key.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	private const EMAIL_CLASS_KEYS = array(
		0 => 'CartBay_Email_Recovery_1',
		1 => 'CartBay_Email_Recovery_2',
		2 => 'CartBay_Email_Recovery_3',
	);

	/**
	 * Settings URL helper.
	 *
	 * @since 1.0.0
	 *
	 * @var SettingsUrl
	 */
	private SettingsUrl $url;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SettingsUrl $url Settings URL helper.
	 */
	public function __construct( SettingsUrl $url ) {
		$this->url = $url;
	}

	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string {
		return 'templates';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Templates', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
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
	 * Render the Templates section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		$this->render_list();
	}

	/**
	 * Render the template list with links to WooCommerce email settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_list(): void {
		$step_labels = array(
			__( 'Recovery Email 1', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			__( 'Recovery Email 2', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			__( 'Recovery Email 3', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);

		$campaign = get_option( 'cartbay_campaign_settings', array() );
		$campaign = SequenceSettings::normalize( is_array( $campaign ) ? $campaign : array() );

		$emails = class_exists( 'WooCommerce', false ) ? WC()->mailer()->get_emails() : array();

		$sequence_url = $this->url->section( 'sequence' );
		$offers_url   = $this->url->section( 'offers' );
		?>
		<h2><?php esc_html_e( 'Templates', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Each recovery email is edited in WooCommerce Email Settings where you can customize subject, heading, preheader, body, button text, logo, colors, footer, preview, and test-send. Customer recovery emails do not need a fixed recipient because CartBay sends each one to the captured checkout email address.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Open WooCommerce Email Settings', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></a></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Step', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Timing', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Coupon', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php for ( $i = 0; $i < 3; $i++ ) : ?>
				<?php
				$delay_label    = SequenceSettings::format_delay( absint( $campaign['steps'][ $i ]['delay_minutes'] ?? 0 ) );
				$coupon_enabled = ! empty( $campaign['steps'][ $i ]['coupon_enabled'] );
				$subject        = $this->get_email_subject( $emails, $i );
				$wc_url         = $this->get_woocommerce_email_url( $i );
				?>
				<tr>
					<td><strong><?php echo esc_html( $step_labels[ $i ] ); ?></strong></td>
					<td><?php echo '' !== $subject ? esc_html( $subject ) : '<em>' . esc_html__( 'Default', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</em>'; ?></td>
					<td>
						<?php echo esc_html( $delay_label ); ?>
						<a href="<?php echo esc_url( $sequence_url ); ?>" class="dashicons dashicons-edit" style="text-decoration:none;margin-left:4px;vertical-align:middle;" title="<?php esc_attr_e( 'Edit in Recovery Sequence', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>"></a>
					</td>
					<td>
						<?php echo $coupon_enabled ? esc_html__( 'Yes', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) : esc_html__( 'No', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
						<a href="<?php echo esc_url( $offers_url ); ?>" class="dashicons dashicons-edit" style="text-decoration:none;margin-left:4px;vertical-align:middle;" title="<?php esc_attr_e( 'Edit in Offers', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>"></a>
					</td>
					<td>
						<a href="<?php echo esc_url( $wc_url ); ?>" class="button button-primary button-small">
							<?php esc_html_e( 'Edit in WooCommerce', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?>
						</a>
					</td>
				</tr>
			<?php endfor; ?>
			</tbody>
		</table>

		<hr />

		<h3><?php esc_html_e( 'Trigger Test Flow', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h3>
		<?php
		$test_mode = Settings::is_test_mode_enabled();
		if ( ! $test_mode ) :
			$enable_url = wp_nonce_url( admin_url( 'admin-post.php?action=cartbay_enable_test_mode' ), 'cartbay_enable_test_mode', 'cartbay_nonce' );
			?>
			<div class="notice notice-warning inline" style="margin:8px 0;">
				<p>
					<?php
					printf(
						/* translators: %s: enable URL */
						wp_kses_post( __( 'Test mode is not enabled. <a href="%s">Enable it now</a>.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
						esc_url( $enable_url )
					);
					?>
				</p>
			</div>
			<?php
		else :
			?>
			<p class="description"><?php esc_html_e( 'Creates a test abandoned session and schedules the first recovery email in about 30 seconds.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
			<?php
		endif;
		?>
		<p>
			<button type="button" id="cartbay-trigger-test" class="button"<?php echo ! $test_mode ? ' disabled' : ''; ?>><?php esc_html_e( 'Trigger Test Flow', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></button>
		</p>
		<?php
		$this->render_placeholder_reference();
	}

	/**
	 * Render the placeholder reference table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_placeholder_reference(): void {
		$placeholders = array(
			'{site_title}'      => __( 'Your store name', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'{site_name}'       => __( 'Your store name (alias)', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'{store_name}'      => __( 'Your store name (alias)', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'{customer_email}'  => __( 'Customer email address', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'{restore_url}'     => __( 'Link to restore the abandoned cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'{coupon_code}'     => __( 'Coupon code (when coupons enabled for this step)', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'{coupon_expiry}'   => __( 'Coupon expiry date (when coupons enabled for this step)', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'{unsubscribe_url}' => __( 'One-click unsubscribe link', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);
		?>
		<hr />
		<h3><?php esc_html_e( 'Available Placeholders', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Use these placeholders in subject, heading, preheader, body, and button label fields. Coupon placeholders only resolve when coupons are enabled for that step.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></p>
		<table class="widefat striped" style="max-width:600px;margin-top:8px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Placeholder', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Replaced with', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $placeholders as $code => $desc ) : ?>
				<tr>
					<td><code><?php echo esc_html( $code ); ?></code></td>
					<td><?php echo esc_html( $desc ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get the saved subject for a recovery email step from the WC email object.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $emails     WC email instances keyed by class name.
	 * @param int                  $step_index 0-based step index.
	 *
	 * @return string Saved subject or empty string.
	 */
	private function get_email_subject( array $emails, int $step_index ): string {
		$class_key = self::EMAIL_CLASS_KEYS[ $step_index ] ?? '';
		if ( '' === $class_key || ! isset( $emails[ $class_key ] ) ) {
			return '';
		}

		$subject = $emails[ $class_key ]->get_option( 'subject', '' );

		return is_string( $subject ) ? $subject : '';
	}

	/**
	 * Get the WooCommerce email settings URL for a recovery step.
	 *
	 * WooCommerce builds the section URL from strtolower($email_key) where
	 * $email_key is the array key in woocommerce_email_classes. CartBay
	 * registers under 'CartBay_Email_Recovery_1', so the section slug is
	 * 'cartbay_email_recovery_1'.
	 *
	 * @since 1.0.0
	 *
	 * @param int $step_index 0-based step index.
	 *
	 * @return string WooCommerce email settings URL.
	 */
	private function get_woocommerce_email_url( int $step_index ): string {
		$class_key = self::EMAIL_CLASS_KEYS[ $step_index ] ?? '';
		$section   = strtolower( $class_key );

		return admin_url( 'admin.php?page=wc-settings&tab=email&section=' . $section );
	}
}
