<?php
/**
 * Runs when the plugin is deleted from WP admin.
 *
 * @package CartBay
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove CartBay custom role capabilities.
 *
 * Capabilities are plugin artifacts (not shopper data), so they are always
 * cleaned up on delete regardless of the data-retention preference. This
 * covers capabilities granted by pre-1.0 builds of the removed AI agent layer.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_uninstall_remove_capabilities(): void {
	$caps = array(
		'cartbay_agent_read',
		'cartbay_agent_write',
		'cartbay_agent_contact',
		'cartbay_agent_sensitive',
		'cartbay_agent_destructive',
		'cartbay_agent_manage_tokens',
		'cartbay_agent_manage_access',
	);

	foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}

		foreach ( $caps as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}

cartbay_uninstall_remove_capabilities();

$cartbay_settings = get_option( 'cartbay_settings', array() );
$cartbay_settings = is_array( $cartbay_settings ) ? $cartbay_settings : array();

$cartbay_remove_data_on_uninstall = $cartbay_settings['remove_data_on_uninstall'] ?? false;
if ( is_string( $cartbay_remove_data_on_uninstall ) ) {
	$cartbay_remove_data_on_uninstall = in_array( strtolower( trim( $cartbay_remove_data_on_uninstall ) ), array( '1', 'true', 'yes', 'on' ), true );
}

if ( ! $cartbay_remove_data_on_uninstall ) {
	return;
}

/**
 * Unschedule CartBay-owned Action Scheduler jobs.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_uninstall_unschedule_actions(): void {
	if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
		return;
	}

	$actions = array(
		'cartbay_detect_abandonment',
		'cartbay_detect_session_abandonment',
		'cartbay_send_recovery_email',
		'cartbay_mark_notification_delivered',
		'cartbay_refresh_analytics',
		'cartbay_prune_sessions',
	);

	foreach ( $actions as $action ) {
		as_unschedule_all_actions( $action );
	}
}

/**
 * Delete CartBay order-backed recovery sessions.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_uninstall_delete_sessions(): void {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return;
	}

	$statuses = array(
		'wc-cartbay-captured',
		'wc-cartbay-abandoned',
		'wc-cartbay-recovered',
		'wc-cartbay-expired',
		'wc-cartbay-suppressed',
	);

	do {
		$sessions = wc_get_orders(
			array(
				'status'  => $statuses,
				'limit'   => 100,
				'return'  => 'objects',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);
		$count    = count( $sessions );

		$deleted = 0;
		foreach ( $sessions as $session ) {
			if ( ! is_object( $session ) || ! method_exists( $session, 'delete' ) ) {
				continue;
			}

			$session->delete( true );
			++$deleted;
		}
	} while ( 100 === $count && $deleted > 0 );
}

/**
 * Delete CartBay-generated recovery coupons.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_uninstall_delete_coupons(): void {
	if ( ! function_exists( 'wc_get_coupons' ) ) {
		return;
	}

	do {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WC CRUD lookup for CartBay-generated coupons during uninstall cleanup.
		$coupons = wc_get_coupons(
			array(
				'limit'      => 100,
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'meta_key'   => '_cartbay_generated',
				'meta_value' => 'yes',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$count = count( $coupons );

		$deleted = 0;
		foreach ( $coupons as $coupon ) {
			if ( ! is_object( $coupon ) || ! method_exists( $coupon, 'delete' ) ) {
				continue;
			}

			$coupon->delete( true );
			++$deleted;
		}
	} while ( 100 === $count && $deleted > 0 );
}

/**
 * Delete CartBay private content records.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_uninstall_delete_private_content(): void {
	$post_types = array( 'cartbay_template', 'cartbay_suppressed' );

	foreach ( $post_types as $post_type ) {
		do {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'fields'         => 'ids',
				)
			);
			$count = count( $posts );

			foreach ( $posts as $post_id ) {
				wp_delete_post( absint( $post_id ), true );
			}
		} while ( 100 === $count );
	}
}

/**
 * Delete CartBay-owned file log artifacts.
 *
 * @since 1.0.0
 *
 * @return void
 */
function cartbay_uninstall_delete_file_logs(): void {
	$upload_dir = wp_upload_dir();
	$base_dir   = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';

	if ( '' === $base_dir ) {
		return;
	}

	$files = array(
		trailingslashit( $base_dir ) . 'cartbay/cartbay.log.php',
		trailingslashit( $base_dir ) . 'cartbay/cartbay.log',
		trailingslashit( $base_dir ) . 'cartbay/index.php',
		trailingslashit( $base_dir ) . 'cartbay/.htaccess',
	);

	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}

	$directory = trailingslashit( $base_dir ) . 'cartbay';
	if ( is_dir( $directory ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $directory );
	}
}

cartbay_uninstall_unschedule_actions();
cartbay_uninstall_delete_sessions();
cartbay_uninstall_delete_coupons();
cartbay_uninstall_delete_private_content();
cartbay_uninstall_delete_file_logs();

$cartbay_options = array(
	'cartbay_campaign_settings',
	'cartbay_settings',
	'cartbay_wizard_complete',
	'cartbay_sequence_defaults_version',
	'cartbay_db_version',
	'cartbay_log_entries',
	'woocommerce_cartbay_recovery_1_settings',
	'woocommerce_cartbay_recovery_2_settings',
	'woocommerce_cartbay_recovery_3_settings',
);

foreach ( $cartbay_options as $cartbay_option ) {
	delete_option( $cartbay_option );
}

delete_transient( 'cartbay_analytics_cache' );
delete_transient( 'cartbay_wizard_redirect' );

// Sweep the per-notification context transients (cartbay_notification_ctx_*),
// which are created dynamically with a TTL. They self-expire, but on an
// opt-in full removal we clear any that remain.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_cartbay_notification_ctx_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cartbay_notification_ctx_' ) . '%'
	)
);
