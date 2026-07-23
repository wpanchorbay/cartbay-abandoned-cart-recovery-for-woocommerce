<?php
/**
 * Plugin installer.
 *
 * @package WPAnchorBay\CartBay\Core
 */

namespace WPAnchorBay\CartBay\Core;

use WPAnchorBay\CartBay\Recovery\SequenceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin activation, deactivation, and upgrade handler.
 *
 * @since 1.0.0
 */
class Installer {
	/**
	 * Ensure recurring jobs are scheduled.
	 *
	 * Action Scheduler functions may not be available during the activation hook
	 * in some environments. This method can be called during normal runtime
	 * initialization to schedule the jobs once Action Scheduler is loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function maybe_schedule_recurring_jobs(): void {
		self::maybe_run_migrations();
		self::schedule_recurring_jobs();
	}

	/**
	 * Run one-time data migrations, gated by the stored schema version.
	 *
	 * This runs on `init` on every request, so the migration routines — some of
	 * which query the database (e.g. get_posts for template backfill) — are
	 * gated behind a stored version so they execute at most once per plugin
	 * version instead of on every page load.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_run_migrations(): void {
		$code_version = defined( 'CARTBAY_VERSION' ) ? (string) CARTBAY_VERSION : '';
		$db_version   = (string) get_option( 'cartbay_db_version', '' );

		if ( '' !== $code_version && '' !== $db_version && version_compare( $db_version, $code_version, '>=' ) ) {
			return;
		}

		self::maybe_upgrade_campaign_settings();
		self::maybe_upgrade_template_content();
		self::maybe_upgrade_capture_setting();
		self::maybe_upgrade_wc_menu_setting();
		self::maybe_upgrade_test_mode_setting();
		self::maybe_upgrade_remove_data_on_uninstall_setting();
		self::maybe_upgrade_log_enabled_setting();

		if ( '' !== $code_version ) {
			update_option( 'cartbay_db_version', $code_version, false );
		}
	}

	/**
	 * Run on plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_default_options();
		self::seed_default_templates();
		self::register_install_time_content();
		self::schedule_recurring_jobs();
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'cartbay_detect_abandonment' );
			as_unschedule_all_actions( 'cartbay_detect_session_abandonment' );
			as_unschedule_all_actions( 'cartbay_refresh_analytics' );
			as_unschedule_all_actions( 'cartbay_prune_sessions' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Create default plugin options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function create_default_options(): void {
		add_option(
			'cartbay_settings',
			array(
				'abandonment_timeout'      => 30,
				'data_retention_days'      => 30,
				'capture_enabled'          => 'yes',
				'consent_text'             => __( 'Save my email to recover my cart if I leave.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'consent_default_state'    => 'unchecked',
				'static_coupon_code'       => '',
				'remove_data_on_uninstall' => 'no',
				'wc_menu_enabled'          => 'yes',
				'log_enabled'              => 'yes',
				'log_retention_days'       => 7,
			)
		);

		add_option(
			'cartbay_campaign_settings',
			SequenceSettings::get_defaults(),
			'',
			false
		);

		add_option( 'cartbay_wizard_complete', false );
		add_option( 'cartbay_sequence_defaults_version', 3, '', false );
	}

	/**
	 * Register statuses and post types so rewrites and labels are ready after activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function register_install_time_content(): void {
		$plugin = Plugin::instance();
		$plugin->register_order_statuses();
		$plugin->register_cpts();
	}

	/**
	 * Seed default email template CPT posts on activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function seed_default_templates(): void {
		$default_templates = array(
			array(
				'title'      => 'Recovery Email 1',
				'content'    => __( '<p>Hi there,</p><p>You were close to checking out, so we saved your cart for you.</p><p>Come back while everything is still fresh and finish your order in just a few clicks.</p>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'subject'    => __( '[{site_title}] You left something in your cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'heading'    => __( 'You left something behind', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preheader'  => __( 'Pick up where you left off before your cart goes cold.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'cta_label'  => __( 'Return to My Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preset_key' => 'friendly_reminder',
			),
			array(
				'title'      => 'Recovery Email 2',
				'content'    => __( '<p>Still thinking it over?</p><p>Your cart is still waiting for you, and checkout only takes a moment.</p><p>If anything was getting in the way, this is a great time to jump back in and complete your order.</p>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'subject'    => __( '[{site_title}] Your cart is waiting for you', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'heading'    => __( 'Still thinking about it?', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preheader'  => __( 'Your saved cart is ready whenever you are.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'cta_label'  => __( 'Complete My Order', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preset_key' => 'value_follow_up',
			),
			array(
				'title'      => 'Recovery Email 3',
				'content'    => __( '<p>This is your last chance to complete your order.</p><p>Your cart is still saved and ready for you. If you still want these items, now is the best time to come back.</p>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'subject'    => __( '[{site_title}] Last chance to complete your order', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'heading'    => __( 'Your cart is still here', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preheader'  => __( 'A final reminder to finish checking out before your cart expires.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'cta_label'  => __( 'Claim My Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preset_key' => 'final_offer',
			),
		);

		$step_templates = array();

		foreach ( $default_templates as $index => $tpl ) {
			$existing = get_posts(
				array(
					'post_type'   => 'cartbay_template',
					'title'       => $tpl['title'],
					'numberposts' => 1,
				)
			);

			if ( empty( $existing ) ) {
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'cartbay_template',
						'post_status'  => 'private',
						'post_title'   => $tpl['title'],
						'post_content' => $tpl['content'],
					)
				);
				update_post_meta( $post_id, '_cartbay_subject', $tpl['subject'] );
				update_post_meta( $post_id, '_cartbay_heading', $tpl['heading'] );
				update_post_meta( $post_id, '_cartbay_preheader', $tpl['preheader'] );
				update_post_meta( $post_id, '_cartbay_cta_label', $tpl['cta_label'] );
				update_post_meta( $post_id, '_cartbay_preset_key', $tpl['preset_key'] );
				$step_templates[] = $post_id;
			}

			self::sync_woocommerce_email_options( $index, $tpl );
		}

		// Update campaign settings with template IDs if just created.
		if ( ! empty( $step_templates ) ) {
			$campaign = get_option( 'cartbay_campaign_settings', array() );
			foreach ( $step_templates as $i => $template_id ) {
				if ( isset( $campaign['steps'][ $i ] ) ) {
					$campaign['steps'][ $i ]['template_id'] = $template_id;
				}
			}
			update_option( 'cartbay_campaign_settings', $campaign );
		}
	}

	/**
	 * Upgrade campaign settings to the recommended structure and defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_upgrade_campaign_settings(): void {
		$campaign        = get_option( 'cartbay_campaign_settings', array() );
		$current_version = absint( get_option( 'cartbay_sequence_defaults_version', 0 ) );

		if ( $current_version < 2 && is_array( $campaign ) && SequenceSettings::is_legacy_default_campaign( $campaign ) ) {
			$campaign = SequenceSettings::get_defaults();
		} elseif ( $current_version < 3 && is_array( $campaign ) && SequenceSettings::is_v2_default_campaign( $campaign ) ) {
			// Untouched v2 install: Email 3 shipped with its coupon enabled. Flip
			// only that flag to match the new default, preserving assigned template
			// IDs and any other normalized values.
			$campaign                               = SequenceSettings::normalize( $campaign );
			$campaign['steps'][2]['coupon_enabled'] = false;
		} else {
			$campaign = SequenceSettings::normalize( is_array( $campaign ) ? $campaign : array() );
		}

		update_option( 'cartbay_campaign_settings', $campaign );
		update_option( 'cartbay_sequence_defaults_version', 3, false );
	}

	/**
	 * Backfill richer email template metadata on existing installs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_upgrade_template_content(): void {
		$templates = get_posts(
			array(
				'post_type'   => 'cartbay_template',
				'post_status' => 'private',
				'numberposts' => 3,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		if ( empty( $templates ) ) {
			return;
		}

		$defaults = array(
			0 => array(
				'subject'    => __( '[{site_title}] You left something in your cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'heading'    => __( 'You left something behind', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preheader'  => __( 'Pick up where you left off before your cart goes cold.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'cta_label'  => __( 'Return to My Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preset_key' => 'friendly_reminder',
			),
			1 => array(
				'subject'    => __( '[{site_title}] Your cart is waiting for you', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'heading'    => __( 'Still thinking about it?', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preheader'  => __( 'Your saved cart is ready whenever you are.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'cta_label'  => __( 'Complete My Order', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preset_key' => 'value_follow_up',
			),
			2 => array(
				'subject'    => __( '[{site_title}] Last chance to complete your order', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'heading'    => __( 'Your cart is still here', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preheader'  => __( 'A final reminder to finish checking out before your cart expires.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'cta_label'  => __( 'Claim My Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'preset_key' => 'final_offer',
			),
		);

		foreach ( array_values( $templates ) as $index => $template ) {
			if ( ! isset( $defaults[ $index ] ) ) {
				continue;
			}

			if ( '' === (string) get_post_meta( $template->ID, '_cartbay_preheader', true ) ) {
				update_post_meta( $template->ID, '_cartbay_preheader', $defaults[ $index ]['preheader'] );
			}

			if ( '' === (string) get_post_meta( $template->ID, '_cartbay_heading', true ) ) {
				update_post_meta( $template->ID, '_cartbay_heading', $defaults[ $index ]['heading'] );
			}

			if ( '' === (string) get_post_meta( $template->ID, '_cartbay_cta_label', true ) ) {
				update_post_meta( $template->ID, '_cartbay_cta_label', $defaults[ $index ]['cta_label'] );
			}

			if ( '' === (string) get_post_meta( $template->ID, '_cartbay_preset_key', true ) ) {
				update_post_meta( $template->ID, '_cartbay_preset_key', $defaults[ $index ]['preset_key'] );
			}

			$subject   = (string) get_post_meta( $template->ID, '_cartbay_subject', true );
			$heading   = (string) get_post_meta( $template->ID, '_cartbay_heading', true );
			$preheader = (string) get_post_meta( $template->ID, '_cartbay_preheader', true );
			$cta_label = (string) get_post_meta( $template->ID, '_cartbay_cta_label', true );

			self::sync_woocommerce_email_options(
				$index,
				array(
					'content'   => $template->post_content,
					'subject'   => '' === $subject ? $defaults[ $index ]['subject'] : $subject,
					'heading'   => '' === $heading ? $defaults[ $index ]['heading'] : $heading,
					'preheader' => '' === $preheader ? $defaults[ $index ]['preheader'] : $preheader,
					'cta_label' => '' === $cta_label ? $defaults[ $index ]['cta_label'] : $cta_label,
				)
			);
		}
	}

	/**
	 * Migrate boolean capture_enabled values to WC-compatible strings.
	 *
	 * The plugin installer previously stored capture_enabled as a PHP
	 * boolean (true/false). WooCommerce's settings API renders checkbox
	 * fields by comparing the stored value against the string 'yes' with
	 * checked(). The stripslashes() call in WC_Admin_Settings::get_option()
	 * coerces boolean true to string '1', which fails the comparison.
	 * This migration converts existing boolean values to 'yes'/'no'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_upgrade_capture_setting(): void {
		$settings = get_option( 'cartbay_settings', array() );
		if ( ! is_array( $settings ) || ! array_key_exists( 'capture_enabled', $settings ) ) {
			return;
		}

		if ( is_bool( $settings['capture_enabled'] ) ) {
			$settings['capture_enabled'] = $settings['capture_enabled'] ? 'yes' : 'no';
			update_option( 'cartbay_settings', $settings );
		}
	}

	/**
	 * Migrate boolean wc_menu_enabled values to WC-compatible strings.
	 *
	 * The same stripslashes() coercion that affects capture_enabled also
	 * affects wc_menu_enabled when stored as a PHP boolean. This migration
	 * converts existing boolean values to 'yes'/'no' strings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_upgrade_wc_menu_setting(): void {
		$settings = get_option( 'cartbay_settings', array() );
		if ( ! is_array( $settings ) || ! array_key_exists( 'wc_menu_enabled', $settings ) ) {
			return;
		}

		if ( is_bool( $settings['wc_menu_enabled'] ) ) {
			$settings['wc_menu_enabled'] = $settings['wc_menu_enabled'] ? 'yes' : 'no';
			update_option( 'cartbay_settings', $settings );
		}
	}

	/**
	 * Migrate boolean test_mode values to WC-compatible strings.
	 *
	 * The same stripslashes() coercion that affects capture_enabled also
	 * affects test_mode when stored as a PHP boolean. This migration
	 * converts existing boolean values to 'yes'/'no' strings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_upgrade_test_mode_setting(): void {
		$settings = get_option( 'cartbay_settings', array() );
		if ( ! is_array( $settings ) || ! array_key_exists( 'test_mode', $settings ) ) {
			return;
		}

		if ( is_bool( $settings['test_mode'] ) ) {
			$settings['test_mode'] = $settings['test_mode'] ? 'yes' : 'no';
			update_option( 'cartbay_settings', $settings );
		}
	}

	/**
	 * Migrate boolean remove_data_on_uninstall values to WC-compatible strings.
	 *
	 * The same stripslashes() coercion that affects capture_enabled also
	 * affects remove_data_on_uninstall when stored as a PHP boolean. This
	 * migration converts existing boolean values to 'yes'/'no' strings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_upgrade_remove_data_on_uninstall_setting(): void {
		$settings = get_option( 'cartbay_settings', array() );
		if ( ! is_array( $settings ) || ! array_key_exists( 'remove_data_on_uninstall', $settings ) ) {
			return;
		}

		if ( is_bool( $settings['remove_data_on_uninstall'] ) ) {
			$settings['remove_data_on_uninstall'] = $settings['remove_data_on_uninstall'] ? 'yes' : 'no';
			update_option( 'cartbay_settings', $settings );
		}
	}

	/**
	 * Migrate boolean log_enabled values to WC-compatible strings.
	 *
	 * The same stripslashes() coercion that affects capture_enabled also
	 * affects log_enabled when stored as a PHP boolean. This migration
	 * converts existing boolean values to 'yes'/'no' strings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function maybe_upgrade_log_enabled_setting(): void {
		$settings = get_option( 'cartbay_settings', array() );
		if ( ! is_array( $settings ) || ! array_key_exists( 'log_enabled', $settings ) ) {
			return;
		}

		if ( is_bool( $settings['log_enabled'] ) ) {
			$settings['log_enabled'] = $settings['log_enabled'] ? 'yes' : 'no';
			update_option( 'cartbay_settings', $settings );
		}
	}

	/**
	 * Seed WooCommerce native email settings without overwriting merchant edits.
	 *
	 * WooCommerce stores all email settings in a single serialized option
	 * per email class: woocommerce_{id}_settings. This method seeds that
	 * array with CartBay defaults for any keys that have no saved value.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $step_index Recovery step index.
	 * @param array<string,string> $template   Template defaults.
	 *
	 * @return void
	 */
	private static function sync_woocommerce_email_options( int $step_index, array $template ): void {
		$email_id   = 'cartbay_recovery_' . ( absint( $step_index ) + 1 );
		$option_key = 'woocommerce_' . $email_id . '_settings';
		$existing   = get_option( $option_key, null );

		// If no settings exist yet, build from defaults.
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$defaults = array(
			'subject'      => $template['subject'] ?? '',
			'heading'      => $template['heading'] ?? '',
			'preheader'    => $template['preheader'] ?? '',
			'body_content' => $template['content'] ?? '',
			'cta_label'    => $template['cta_label'] ?? '',
			'enabled'      => 'yes',
			'email_type'   => 'html',
		);

		foreach ( $defaults as $key => $value ) {
			// Only seed keys that have no saved value.
			if ( ! isset( $existing[ $key ] ) || '' === $existing[ $key ] ) {
				$existing[ $key ] = $value;
			}
		}

		update_option( $option_key, $existing, true );

		// Clean up phantom individual options from earlier versions.
		$phantom_keys = array( 'subject', 'heading', 'preheader', 'body_content', 'cta_label' );
		foreach ( $phantom_keys as $key ) {
			$phantom_option = "woocommerce_{$email_id}_{$key}";
			if ( '' !== (string) get_option( $phantom_option, '' ) ) {
				delete_option( $phantom_option );
			}
		}
	}

	/**
	 * Schedule recurring Action Scheduler jobs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function schedule_recurring_jobs(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( function_exists( 'did_action' ) && 0 === did_action( 'action_scheduler_init' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( 'cartbay_detect_abandonment' ) ) {
			as_schedule_recurring_action( time(), 5 * MINUTE_IN_SECONDS, 'cartbay_detect_abandonment', array(), 'cartbay' );
		}

		if ( ! as_has_scheduled_action( 'cartbay_refresh_analytics' ) ) {
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, 'cartbay_refresh_analytics', array(), 'cartbay' );
		}

		if ( ! as_has_scheduled_action( 'cartbay_prune_sessions' ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'cartbay_prune_sessions', array(), 'cartbay' );
		}
	}
}
