<?php
/**
 * CartBay Recovery Email — Step 2.
 *
 * @package WPAnchorBay\CartBay\Email
 */

namespace WPAnchorBay\CartBay\Email;

defined( 'ABSPATH' ) || exit;

/**
 * CartBay Recovery Email — Step 2.
 *
 * @since 1.0.0
 */
class CartBay_Email_Recovery_2 extends AbstractCartBayRecoveryEmail {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id                   = 'cartbay_recovery_2';
		$this->title                = __( 'CartBay: Recovery Email 2', 'cartbay' );
		$this->description          = __( 'Second abandoned cart recovery email.', 'cartbay' );
		$this->customer_email       = true;
		$this->template_base        = CARTBAY_DIR . 'templates/';
		$this->template_html        = 'emails/recovery-email-2.php';
		$this->template_plain       = '';
		$this->default_subject      = __( '[{site_title}] Your cart is waiting for you', 'cartbay' );
		$this->default_heading      = __( 'Still thinking about it?', 'cartbay' );
		$this->default_preheader    = __( 'Your saved cart is ready whenever you are.', 'cartbay' );
		$this->default_cta_label    = __( 'Complete My Order', 'cartbay' );
		$this->default_body_content = '<p>Still thinking it over?</p><p>Your cart is still waiting for you, and checkout only takes a moment.</p><p>If anything was getting in the way, this is a great time to jump back in and complete your order.</p>';
		$this->placeholders         = $this->get_default_placeholders();

		parent::__construct();

		$this->heading = $this->get_option( 'heading', $this->default_heading );
		$this->subject = $this->get_option( 'subject', $this->default_subject );
	}
}
