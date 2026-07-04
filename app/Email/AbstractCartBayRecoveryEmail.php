<?php
/**
 * Base recovery email class.
 *
 * @package WPAnchorBay\CartBay\Email
 */

namespace WPAnchorBay\CartBay\Email;

use WC_Email;

defined( 'ABSPATH' ) || exit;

/**
 * Shared behavior for CartBay recovery emails.
 *
 * @since 1.0.0
 */
abstract class AbstractCartBayRecoveryEmail extends WC_Email {
	/**
	 * Notification identifier for tracking.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $notification_id = '';

	/**
	 * Step index (0-based).
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $step_index = 0;

	/**
	 * Default body copy.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $default_body_content = '';

	/**
	 * Default preheader text.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $default_preheader = '';

	/**
	 * Default CTA button label.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $default_cta_label = '';

	/**
	 * Default subject line.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $default_subject = '';

	/**
	 * Default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $default_heading = '';

	/**
	 * Send the recovery email for a real session.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $session_id      CartBay session order ID.
	 * @param string $restore_url     Full restore URL with token.
	 * @param string $coupon_code     Coupon code or empty string.
	 * @param string $coupon_expiry   Human-readable expiry date.
	 * @param string $unsubscribe_url Unsubscribe URL.
	 *
	 * @return bool Whether the mailer accepted the email.
	 */
	public function trigger( int $session_id, string $restore_url, string $coupon_code, string $coupon_expiry, string $unsubscribe_url ): bool {
		$session = wc_get_order( $session_id );
		if ( ! $session ) {
			return false;
		}

		$this->setup_locale();
		$this->object    = $session;
		$this->recipient = $session->get_billing_email();

		$context = array(
			'restore_url'     => $restore_url,
			'coupon_code'     => $coupon_code,
			'coupon_expiry'   => $coupon_expiry,
			'unsubscribe_url' => $unsubscribe_url,
			'recipient_email' => $this->get_recipient(),
		);

		$this->set_context_placeholders( $context );
		$template_settings = $this->get_template_settings();
		$this->heading     = $template_settings['heading'];

		$sent = $this->send(
			$this->get_recipient(),
			$this->render_tokens( $template_settings['subject'], $context ),
			$this->build_email_html( $template_settings, $context ),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();
		$this->notification_id = '';
		$this->object          = null;

		return (bool) $sent;
	}

	/**
	 * Send a preview email to a specific address using sample data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $recipient Preview recipient email address.
	 *
	 * @return bool Whether the mailer accepted the preview email.
	 */
	public function send_preview( string $recipient ): bool {
		$this->recipient = sanitize_email( $recipient );

		if ( '' === $this->recipient ) {
			return false;
		}

		$this->setup_locale();

		$context = $this->get_preview_context( $this->get_recipient() );
		$this->set_context_placeholders( $context );

		$template_settings = $this->get_template_settings();
		$this->heading     = $template_settings['heading'];

		$sent = $this->send(
			$this->get_recipient(),
			sprintf(
				/* translators: %s: original subject */
				__( '[Preview] %s', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				$this->render_tokens( $template_settings['subject'], $context )
			),
			$this->build_email_html( $template_settings, $context ),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();
		$this->notification_id = '';
		$this->object          = null;

		return (bool) $sent;
	}

	/**
	 * Append tracking headers to recovery emails.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_headers(): string {
		$headers = parent::get_headers();

		if ( '' === $this->notification_id ) {
			return $headers;
		}

		return $headers . 'X-CartBay-Notification: ' . sanitize_key( $this->notification_id ) . "\r\n";
	}

	/**
	 * Get the HTML content.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		$context = $this->get_preview_context();
		$this->set_context_placeholders( $context );

		$template_settings = $this->get_template_settings();
		$this->heading     = $template_settings['heading'];

		return $this->build_email_html( $template_settings, $context );
	}

	/**
	 * Get the plain text content.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		return wp_strip_all_tags( $this->get_content_html() );
	}

	/**
	 * Get the default email subject.
	 *
	 * @since 1.0.0
	 *
	 * @return string Default subject.
	 */
	public function get_default_subject(): string {
		return $this->default_subject;
	}

	/**
	 * Get the default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @return string Default heading.
	 */
	public function get_default_heading(): string {
		return $this->default_heading;
	}

	/**
	 * Initialize native WooCommerce email settings fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		parent::init_form_fields();

		$placeholder_codes = '<code>' . implode(
			'</code>, <code>',
			array_map( 'esc_html', array_keys( $this->get_default_placeholders() ) )
		) . '</code>';

		$placeholder_text = sprintf(
			/* translators: %s: list of placeholders */
			__( 'Available placeholders: %s', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			$placeholder_codes
		);

		$additional_content = isset( $this->form_fields['additional_content'] ) ? $this->form_fields['additional_content'] : array();
		unset( $this->form_fields['additional_content'] );

		if ( ! isset( $this->form_fields['preheader'] ) ) {
			$this->form_fields['preheader'] = array(
				'title'       => __( 'Preheader', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown as preview text in the inbox, next to the subject line.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . ' ' . $placeholder_text,
				'desc_tip'    => true,
				'default'     => $this->default_preheader,
			);
		}

		$this->form_fields['body_content'] = array(
			'title'       => __( 'Email body', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'type'        => 'textarea',
			'css'         => 'width:400px; min-height: 180px;',
			'description' => __( 'Main recovery message. Basic HTML is allowed and WooCommerce wraps this content with your store email logo, colors, and footer.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . ' ' . $placeholder_text,
			'default'     => $this->get_default_body_content(),
			'desc_tip'    => true,
		);

		$this->form_fields['cta_label'] = array(
			'title'       => __( 'Button label', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Text for the restore-cart button.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . ' ' . $placeholder_text,
			'default'     => $this->default_cta_label,
			'desc_tip'    => true,
		);

		$this->form_fields['show_unsubscribe'] = array(
			'title'       => __( 'Unsubscribe link', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'type'        => 'checkbox',
			'label'       => __( 'Show unsubscribe link', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'description' => __( 'Display a one-click unsubscribe link at the bottom of this email. Recommended for CAN-SPAM compliance.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'default'     => 'yes',
		);

		if ( ! empty( $additional_content ) ) {
			$this->form_fields['additional_content'] = $additional_content;
		}
	}

	/**
	 * Build the rendered email HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $template_settings Template settings.
	 * @param array<string, string> $context           Render context.
	 *
	 * @return string
	 */
	protected function build_email_html( array $template_settings, array $context ): string {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'email_heading'      => $this->get_heading(),
				'restore_url'        => $context['restore_url'],
				'coupon_code'        => $context['coupon_code'],
				'coupon_expiry'      => $context['coupon_expiry'],
				'unsubscribe_url'    => $context['unsubscribe_url'],
				'body_content'       => $this->render_tokens( $template_settings['body'], $context ),
				'preheader_text'     => $this->render_tokens( $template_settings['preheader'], $context ),
				'cta_label'          => $this->render_tokens( $template_settings['cta_label'], $context ),
				'additional_content' => $this->render_tokens( $this->get_additional_content(), $context ),
				'show_unsubscribe'   => $this->get_option( 'show_unsubscribe', 'yes' ) === 'yes',
				'email'              => $this,
			),
			'',
			$this->template_base
		);

		return $this->style_inline( (string) ob_get_clean() );
	}

	/**
	 * Get the saved template settings for the current step.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Template settings.
	 */
	protected function get_template_settings(): array {
		$subject     = $this->read_email_setting( 'subject', '' );
		$heading     = $this->read_email_setting( 'heading', '' );
		$body        = $this->read_email_setting( 'body_content', '' );
		$preheader   = $this->read_email_setting( 'preheader', '' );
		$cta_label   = $this->read_email_setting( 'cta_label', '' );
		$campaign    = get_option( 'cartbay_campaign_settings', array() );
		$step        = isset( $campaign['steps'][ $this->step_index ] ) && is_array( $campaign['steps'][ $this->step_index ] ) ? $campaign['steps'][ $this->step_index ] : array();
		$template_id = absint( $step['template_id'] ?? 0 );

		if ( $template_id > 0 ) {
			$post = get_post( $template_id );
			if ( '' === $body && $post && 'cartbay_template' === $post->post_type ) {
				$body = wp_kses_post( $post->post_content );
			}

			$saved_subject = sanitize_text_field( (string) get_post_meta( $template_id, '_cartbay_subject', true ) );
			$saved_heading = sanitize_text_field( (string) get_post_meta( $template_id, '_cartbay_heading', true ) );
			$saved_prehead = sanitize_text_field( (string) get_post_meta( $template_id, '_cartbay_preheader', true ) );
			$saved_cta     = sanitize_text_field( (string) get_post_meta( $template_id, '_cartbay_cta_label', true ) );

			if ( '' === $subject && '' !== $saved_subject ) {
				$subject = $saved_subject;
			}

			if ( '' === $heading && '' !== $saved_heading ) {
				$heading = $saved_heading;
			}

			if ( '' === $preheader && '' !== $saved_prehead ) {
				$preheader = $saved_prehead;
			}

			if ( '' === $cta_label && '' !== $saved_cta ) {
				$cta_label = $saved_cta;
			}
		}

		return array(
			'subject'   => '' === $subject ? $this->get_default_subject() : $subject,
			'heading'   => '' === $heading ? $this->get_default_heading() : $heading,
			'body'      => '' === $body ? $this->get_default_body_content() : $body,
			'preheader' => '' === $preheader ? $this->default_preheader : $preheader,
			'cta_label' => '' === $cta_label ? $this->default_cta_label : $cta_label,
		);
	}

	/**
	 * Replace template tokens with real values.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $content Template content.
	 * @param array<string, string> $context Render context.
	 *
	 * @return string
	 */
	protected function render_tokens( string $content, array $context ): string {
		$this->set_context_placeholders( $context );

		return $this->format_string( strtr( $content, $this->get_legacy_token_replacements( $context ) ) );
	}

	/**
	 * Get the default body content.
	 *
	 * @since 1.0.0
	 *
	 * @return string Default body content.
	 */
	protected function get_default_body_content(): string {
		return $this->default_body_content;
	}

	/**
	 * Read a WooCommerce email setting with preview transient support.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key      Setting key.
	 * @param string $fallback Fallback value.
	 *
	 * @return string Setting value.
	 */
	protected function read_email_setting( string $key, string $fallback ): string {
		$value = $this->get_option_or_transient( $key, '' );
		$value = is_string( $value ) ? $value : $fallback;

		return '' === $value ? $fallback : $value;
	}

	/**
	 * Set dynamic placeholders for the current render context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $context Render context.
	 *
	 * @return void
	 */
	protected function set_context_placeholders( array $context ): void {
		$this->placeholders = array_merge( $this->get_default_placeholders(), $this->get_context_placeholders( $context ) );
	}

	/**
	 * Get CartBay placeholders with sample-safe defaults.
	 *
	 * Coupon placeholders are only populated when the step has coupons enabled
	 * in the recovery sequence settings, so previews reflect what will actually
	 * be sent.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Placeholder values.
	 */
	protected function get_default_placeholders(): array {
		$coupon_code   = '';
		$coupon_expiry = '';
		if ( $this->step_has_coupon_enabled() ) {
			$settings    = get_option( 'cartbay_settings', array() );
			$static_code = is_array( $settings ) && isset( $settings['static_coupon_code'] ) ? sanitize_text_field( (string) $settings['static_coupon_code'] ) : '';
			$coupon_code = '' !== $static_code ? $static_code : __( 'SAVE10', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		}

		return array(
			'{site_title}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{site_name}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{store_name}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{restore_url}'     => wc_get_checkout_url(),
			'{coupon_code}'     => $coupon_code,
			'{coupon_expiry}'   => $coupon_expiry,
			'{unsubscribe_url}' => home_url( '/?cartbay_unsubscribe=preview' ),
			'{customer_email}'  => 'customer@example.com',
		);
	}

	/**
	 * Get context-specific placeholder values.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $context Render context.
	 *
	 * @return array<string, string> Placeholder values.
	 */
	protected function get_context_placeholders( array $context ): array {
		return array(
			'{restore_url}'     => esc_url_raw( $context['restore_url'] ?? '' ),
			'{coupon_code}'     => sanitize_text_field( $context['coupon_code'] ?? '' ),
			'{coupon_expiry}'   => sanitize_text_field( $context['coupon_expiry'] ?? '' ),
			'{unsubscribe_url}' => esc_url_raw( $context['unsubscribe_url'] ?? '' ),
			'{customer_email}'  => sanitize_email( $context['recipient_email'] ?? '' ),
		);
	}

	/**
	 * Get legacy double-brace token replacements.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $context Render context.
	 *
	 * @return array<string, string> Token replacements.
	 */
	protected function get_legacy_token_replacements( array $context ): array {
		return array(
			'{{store_name}}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{{restore_url}}'     => esc_url_raw( $context['restore_url'] ?? '' ),
			'{{coupon_code}}'     => sanitize_text_field( $context['coupon_code'] ?? '' ),
			'{{coupon_expiry}}'   => sanitize_text_field( $context['coupon_expiry'] ?? '' ),
			'{{unsubscribe_url}}' => esc_url_raw( $context['unsubscribe_url'] ?? '' ),
			'{{customer_email}}'  => sanitize_email( $context['recipient_email'] ?? '' ),
		);
	}

	/**
	 * Get sample context for native WooCommerce previews and test emails.
	 *
	 * Coupon values are only included when the step has coupons enabled,
	 * so previews match what the customer will actually receive.
	 *
	 * @since 1.0.0
	 *
	 * @param string $recipient Preview recipient.
	 *
	 * @return array<string, string> Preview context.
	 */
	protected function get_preview_context( string $recipient = 'customer@example.com' ): array {
		$coupon_code   = '';
		$coupon_expiry = '';
		if ( $this->step_has_coupon_enabled() ) {
			$settings    = get_option( 'cartbay_settings', array() );
			$static_code = is_array( $settings ) && isset( $settings['static_coupon_code'] ) ? sanitize_text_field( (string) $settings['static_coupon_code'] ) : '';
			$coupon_code = '' !== $static_code ? $static_code : __( 'SAVE10', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		}

		return array(
			'restore_url'     => wc_get_checkout_url(),
			'coupon_code'     => $coupon_code,
			'coupon_expiry'   => $coupon_expiry,
			'unsubscribe_url' => home_url( '/?cartbay_unsubscribe=preview' ),
			'recipient_email' => sanitize_email( $recipient ),
		);
	}

	/**
	 * Check whether the current step has coupons enabled in campaign settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the step has coupon_enabled set.
	 */
	protected function step_has_coupon_enabled(): bool {
		$campaign = get_option( 'cartbay_campaign_settings', array() );
		if ( ! is_array( $campaign ) || ! isset( $campaign['steps'][ $this->step_index ] ) ) {
			return false;
		}

		return ! empty( $campaign['steps'][ $this->step_index ]['coupon_enabled'] );
	}
}
