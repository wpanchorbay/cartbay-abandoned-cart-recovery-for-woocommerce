<?php
/**
 * WordPress privacy tools integration.
 *
 * @package WPAnchorBay\CartBay\Core
 */

namespace WPAnchorBay\CartBay\Core;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates CartBay-stored personal data with WordPress core privacy tools.
 *
 * CartBay stores a shopper's email, cart snapshot, and recovery metadata (on
 * consent) as WooCommerce-native session records. This class registers a
 * personal-data exporter and eraser (Tools > Export/Erase Personal Data) keyed
 * by email, plus suggested privacy-policy content, so site owners can honour
 * data-subject access and erasure requests.
 *
 * @since 1.0.0
 */
class Privacy {

	/**
	 * CartBay session statuses that may hold a shopper's personal data.
	 *
	 * @since 1.0.0
	 */
	private const SESSION_STATUSES = array(
		'wc-cartbay-captured',
		'wc-cartbay-abandoned',
		'wc-cartbay-recovered',
		'wc-cartbay-expired',
	);

	/**
	 * Number of sessions processed per export/erase batch.
	 *
	 * @since 1.0.0
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Register the privacy hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
	}

	/**
	 * Register the CartBay personal-data exporter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 *
	 * @return array<string, array<string, mixed>> Exporters with CartBay added.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['cartbay'] = array(
			'exporter_friendly_name' => __( 'CartBay Abandoned Cart Sessions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the CartBay personal-data eraser.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 *
	 * @return array<string, array<string, mixed>> Erasers with CartBay added.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['cartbay'] = array(
			'eraser_friendly_name' => __( 'CartBay Abandoned Cart Sessions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export CartBay session data for an email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address Email address being exported.
	 * @param int    $page          Batch page (1-based).
	 *
	 * @return array{data: array<int, array<string, mixed>>, done: bool} Export payload.
	 */
	public function export( string $email_address, int $page = 1 ): array {
		$sessions = $this->get_sessions( $email_address, $page );
		$export   = array();

		foreach ( $sessions as $session ) {
			$export[] = array(
				'group_id'    => 'cartbay_sessions',
				'group_label' => __( 'CartBay Abandoned Cart Sessions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'item_id'     => 'cartbay-session-' . $session->get_id(),
				'data'        => $this->session_export_fields( $session ),
			);
		}

		return array(
			'data' => $export,
			'done' => count( $sessions ) < self::BATCH_SIZE,
		);
	}

	/**
	 * Erase CartBay session data for an email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address Email address being erased.
	 * @param int    $page          Batch page (1-based).
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool} Erase result.
	 */
	public function erase( string $email_address, int $page = 1 ): array {
		unset( $page );

		// Always operate on the first page: each session is deleted, so the
		// remaining set shrinks until none match.
		$sessions = $this->get_sessions( $email_address, 1 );
		$removed  = 0;

		foreach ( $sessions as $session ) {
			$session->delete( true );
			++$removed;
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $sessions ) < self::BATCH_SIZE,
		);
	}

	/**
	 * Suggest privacy-policy content describing CartBay's data handling.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = wp_kses_post(
			__( 'When a shopper checks the CartBay consent box at checkout, this site stores their email address, a snapshot of their cart, the checkout source, and timestamps so it can send recovery reminders if the cart is left behind. This data is stored on this site using WooCommerce-native records and is used only to send those recovery emails. Shoppers can withdraw consent by unchecking the box, and every recovery email includes an unsubscribe link. Stored data is removed automatically after the configured retention period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
		);

		wp_add_privacy_policy_content(
			'CartBay - Abandoned Cart Recovery for WooCommerce',
			'<p>' . $content . '</p>'
		);
	}

	/**
	 * Get CartBay session orders for an email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address Email address.
	 * @param int    $page          Batch page (1-based).
	 *
	 * @return array<int, WC_Order> Matching session orders.
	 */
	private function get_sessions( string $email_address, int $page ): array {
		$email = sanitize_email( $email_address );

		if ( '' === $email || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'status'        => self::SESSION_STATUSES,
				'billing_email' => $email,
				'limit'         => self::BATCH_SIZE,
				'paged'         => max( 1, $page ),
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'return'        => 'objects',
			)
		);

		return array_values( array_filter( $orders, static fn ( $order ): bool => $order instanceof WC_Order ) );
	}

	/**
	 * Build the exportable field list for a session.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $session Session order.
	 *
	 * @return array<int, array{name: string, value: string}> Export fields.
	 */
	private function session_export_fields( WC_Order $session ): array {
		$created  = $session->get_date_created();
		$snapshot = $session->get_meta( '_cartbay_cart_snapshot', true );
		$items    = is_array( $snapshot ) && isset( $snapshot['items'] ) && is_array( $snapshot['items'] ) ? count( $snapshot['items'] ) : 0;

		return array(
			array(
				'name'  => __( 'Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value' => $session->get_billing_email(),
			),
			array(
				'name'  => __( 'Status', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value' => $session->get_status(),
			),
			array(
				'name'  => __( 'Captured', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value' => $created ? $created->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '',
			),
			array(
				'name'  => __( 'Checkout source', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value' => sanitize_text_field( (string) $session->get_meta( '_cartbay_source', true ) ),
			),
			array(
				'name'  => __( 'Cart items stored', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value' => (string) $items,
			),
			array(
				'name'  => __( 'Cart total', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value' => (string) floatval( $session->get_meta( '_cartbay_cart_total', true ) ),
			),
		);
	}
}
