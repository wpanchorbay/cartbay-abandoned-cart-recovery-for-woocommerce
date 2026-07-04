<?php
/**
 * CartBay Recovery Email — Step 3.
 *
 * @package WPAnchorBay\CartBay\Email
 */

namespace WPAnchorBay\CartBay\Email;

defined( 'ABSPATH' ) || exit;

/**
 * CartBay Recovery Email — Step 3.
 *
 * @since 1.0.0
 */
class CartBay_Email_Recovery_3 extends AbstractCartBayRecoveryEmail {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id                   = 'cartbay_recovery_3';
		$this->title                = __( 'CartBay: Recovery Email 3', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->description          = __( 'Third abandoned cart recovery email.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->customer_email       = true;
		$this->template_base        = CARTBAY_DIR . 'templates/';
		$this->template_html        = 'emails/recovery-email-3.php';
		$this->template_plain       = '';
		$this->default_subject      = __( '[{site_title}] Last chance to complete your order', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_heading      = __( 'Your cart is still here', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_preheader    = __( 'A final reminder to finish checking out before your cart expires.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_cta_label    = __( 'Claim My Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_body_content = '<p>This is your last chance to complete your order.</p><p>Your cart is still saved and ready for you. If you still want these items, now is the best time to come back.</p>';
		$this->placeholders         = $this->get_default_placeholders();

		parent::__construct();

		$this->heading = $this->get_option( 'heading', $this->default_heading );
		$this->subject = $this->get_option( 'subject', $this->default_subject );
	}
}
