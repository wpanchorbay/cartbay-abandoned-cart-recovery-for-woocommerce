<?php
/**
 * CartBay Recovery Email — Step 1.
 *
 * @package WPAnchorBay\CartBay\Email
 */

namespace WPAnchorBay\CartBay\Email;

defined( 'ABSPATH' ) || exit;

/**
 * CartBay Recovery Email — Step 1.
 *
 * @since 1.0.0
 */
class CartBay_Email_Recovery_1 extends AbstractCartBayRecoveryEmail {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id                   = 'cartbay_recovery_1';
		$this->title                = __( 'CartBay: Recovery Email 1', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->description          = __( 'First abandoned cart recovery email.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->customer_email       = true;
		$this->template_base        = CARTBAY_DIR . 'templates/';
		$this->template_html        = 'emails/recovery-email-1.php';
		$this->template_plain       = '';
		$this->default_subject      = __( '[{site_title}] You left something in your cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_heading      = __( 'You left something behind', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_preheader    = __( 'Pick up where you left off before your cart goes cold.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_cta_label    = __( 'Return to My Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$this->default_body_content = '<p>Hi there,</p><p>You were close to checking out, so we saved your cart for you.</p><p>Come back while everything is still fresh and finish your order in just a few clicks.</p>';
		$this->placeholders         = $this->get_default_placeholders();

		parent::__construct();

		$this->heading = $this->get_option( 'heading', $this->default_heading );
		$this->subject = $this->get_option( 'subject', $this->default_subject );
	}
}
