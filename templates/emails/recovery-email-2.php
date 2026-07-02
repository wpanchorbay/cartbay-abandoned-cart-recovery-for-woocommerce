<?php
/**
 * CartBay Recovery Email - Step 2 template.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/.
 *
 * @since   1.0.0
 * @package CartBay
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Required WooCommerce email template hook.
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php if ( ! empty( $preheader_text ) ) : ?>
<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">
	<?php echo esc_html( $preheader_text ); ?>
</div>
<?php endif; ?>

<div style="background:#f6f7f7;border:1px solid #e0e0e0;border-radius:12px;padding:28px 24px;">
	<div style="font-size:15px;line-height:1.7;color:#1d2327;">
		<?php echo wp_kses_post( wpautop( $body_content ) ); ?>
	</div>

<?php if ( ! empty( $restore_url ) ) : ?>
<p style="text-align:center;margin:24px 0;">
	<a href="<?php echo esc_url( $restore_url ); ?>"
		style="background:#0f766e;color:#fff;padding:14px 24px;text-decoration:none;border-radius:999px;display:inline-block;font-weight:600;">
		<?php echo esc_html( ! empty( $cta_label ) ? $cta_label : __( 'Return to My Cart', 'cartbay' ) ); ?>
	</a>
</p>
<?php endif; ?>

<?php if ( ! empty( $coupon_code ) ) : ?>
<p>
	<?php
	printf(
		/* translators: %1$s: coupon code, %2$s: expiry date */
		esc_html__( 'Use coupon code %1$s at checkout. Expires %2$s.', 'cartbay' ),
		'<strong>' . esc_html( $coupon_code ) . '</strong>',
		esc_html( $coupon_expiry )
	);
	?>
</p>
<?php endif; ?>

<?php if ( ! empty( $additional_content ) ) : ?>
<div style="font-size:14px;line-height:1.6;color:#3c434a;margin-top:20px;">
	<?php echo wp_kses_post( wpautop( $additional_content ) ); ?>
</div>
<?php endif; ?>
</div>

<?php if ( ! empty( $restore_url ) ) : ?>
<p style="font-size:12px;line-height:1.6;color:#6b7280;margin-top:16px;word-break:break-all;">
	<?php esc_html_e( "If the button doesn't work, copy and paste this link into your browser:", 'cartbay' ); ?><br>
	<a href="<?php echo esc_url( $restore_url ); ?>" style="color:#0f766e;">
		<?php echo esc_html( $restore_url ); ?>
	</a>
</p>
<?php endif; ?>

<?php if ( ! empty( $show_unsubscribe ) ) : ?>
<p style="font-size:12px;color:#6b7280;margin-top:16px;">
	<a href="<?php echo esc_url( $unsubscribe_url ); ?>"><?php esc_html_e( 'Unsubscribe', 'cartbay' ); ?></a>
</p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Required WooCommerce email template hook.
do_action( 'woocommerce_email_footer', $email );
?>
