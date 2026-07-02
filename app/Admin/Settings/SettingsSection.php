<?php
/**
 * General settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * General settings, retention, and debug section.
 *
 * @since 1.0.0
 */
class SettingsSection extends AbstractSettingsSection {
	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string {
		return 'settings';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Settings', 'cartbay' );
	}

	/**
	 * Get WooCommerce settings API fields for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Section fields.
	 */
	public function fields(): array {
		$logs_url = add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'cartbay',
				'section' => 'logs',
			),
			admin_url( 'admin.php' )
		);

		return array_merge(
			/**
			 * Filter Settings-section fields before CartBay core retention fields.
			 *
			 * Pro uses this extension point to prepend commercial settings such as
			 * license management without making the free plugin own that subsystem.
			 *
			 * @since 1.0.0
			 *
			 * @param array<int, array<string, mixed>> $fields Settings fields.
			 */
			apply_filters( 'cartbay_settings_section_pre_fields', array() ),
			array(
				array(
					'title' => __( 'Data Retention', 'cartbay' ),
					'type'  => 'title',
					'desc'  => __( 'Control how long CartBay keeps abandoned cart session data.', 'cartbay' ),
					'id'    => 'cartbay_retention_settings',
				),
				array(
					'title'             => __( 'Retention Period (days)', 'cartbay' ),
					'desc'              => __( 'Sessions older than this will be automatically deleted.', 'cartbay' ),
					'desc_tip'          => __( 'Choose how long abandoned cart records remain available for reporting, session review, and cleanup jobs.', 'cartbay' ),
					'id'                => 'cartbay_settings[data_retention_days]',
					'default'           => 30,
					'type'              => 'number',
					'css'               => 'width:80px;',
					'custom_attributes' => array(
						'min' => 7,
						'max' => 90,
					),
				),
				array(
					'title'    => __( 'Delete Data on Uninstall', 'cartbay' ),
					'desc'     => __( 'Permanently delete all CartBay data when the plugin is deleted.', 'cartbay' ),
					'desc_tip' => __( 'When enabled, deleting CartBay removes CartBay settings and recovery data. Leave unchecked to preserve data for reinstalling later.', 'cartbay' ),
					'id'       => 'cartbay_settings[remove_data_on_uninstall]',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'cartbay_retention_settings',
				),
				array(
					'title' => __( 'Admin Navigation', 'cartbay' ),
					'type'  => 'title',
					'desc'  => __( 'Control where CartBay appears in the WordPress admin menu.', 'cartbay' ),
					'id'    => 'cartbay_admin_navigation_settings',
				),
				array(
					'title'    => __( 'WooCommerce Menu Shortcut', 'cartbay' ),
					'desc'     => __( 'Show CartBay under the WooCommerce admin menu.', 'cartbay' ),
					'desc_tip' => __( 'When enabled, WooCommerce > CartBay opens this CartBay settings area directly.', 'cartbay' ),
					'id'       => 'cartbay_settings[wc_menu_enabled]',
					'default'  => 'yes',
					'type'     => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'cartbay_admin_navigation_settings',
				),
				array(
					'title' => __( 'Debug & Testing', 'cartbay' ),
					'type'  => 'title',
					'desc'  => __( 'Developer tools for QA, troubleshooting, and short-cycle testing.', 'cartbay' ),
					'id'    => 'cartbay_debug_settings',
				),
				array(
					'title'    => __( 'Test Mode', 'cartbay' ),
					'desc'     => __( 'Enable test mode (shortened email delays, dummy sessions)', 'cartbay' ),
					'desc_tip' => __( 'Use this in staging or QA when you want quick feedback loops without waiting for the normal recovery schedule.', 'cartbay' ),
					'id'       => 'cartbay_settings[test_mode]',
					'default'  => 'no',
					'type'     => 'checkbox',
				),
				array(
					'title'       => __( 'WooCommerce Logs', 'cartbay' ),
					'type'        => 'cartbay_action_row',
					'tooltip'     => __( 'Open the WooCommerce log viewer filtered to the CartBay source for debugging background jobs and checkout capture.', 'cartbay' ),
					'actions'     => array(
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=cartbay' ) ) . '" class="button" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Logs', 'cartbay' ) . '</a>',
					),
					'description' => __( 'Logs open in WooCommerce Status and remain filtered to the CartBay log source.', 'cartbay' ),
					'id'          => 'cartbay_logs_link',
				),
				array(
					'title'       => __( 'CartBay Logs', 'cartbay' ),
					'type'        => 'cartbay_action_row',
					'tooltip'     => __( 'Open CartBay-owned sanitized logs and log configuration.', 'cartbay' ),
					'actions'     => array(
						'<a href="' . esc_url( $logs_url ) . '" class="button">' . esc_html__( 'Open CartBay Logs', 'cartbay' ) . '</a>',
					),
					'description' => __( 'CartBay logs are shown in a separate section so settings remain focused.', 'cartbay' ),
					'id'          => 'cartbay_file_logs_link',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'cartbay_debug_settings',
				),
			)
		);
	}

	/**
	 * Save Settings-section custom data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce handled by Woo settings save; values are only checked for presence.
		$posted_settings = isset( $_POST['cartbay_settings'] ) && is_array( $_POST['cartbay_settings'] ) ? wp_unslash( $_POST['cartbay_settings'] ) : array();
		$settings        = get_option( 'cartbay_settings', array() );
		$settings        = is_array( $settings ) ? $settings : array();

		$settings['test_mode']                = isset( $posted_settings['test_mode'] );
		$settings['remove_data_on_uninstall'] = isset( $posted_settings['remove_data_on_uninstall'] );
		$settings['wc_menu_enabled']          = isset( $posted_settings['wc_menu_enabled'] ) ? 'yes' : 'no';
		update_option( 'cartbay_settings', $settings );

		/**
		 * Fires after the free Settings section has saved host-owned options.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $settings Sanitized settings saved by the host.
		 */
		do_action( 'cartbay_settings_section_saved', $settings );
	}
}
