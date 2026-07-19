<?php
/**
 * Logs settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

use WPAnchorBay\CartBay\Utils\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden CartBay logs section with configuration and copyable entries.
 *
 * @since 1.0.0
 */
class LogsSection extends AbstractSettingsSection {
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
		return 'logs';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Logs', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
	}

	/**
	 * Get WooCommerce settings API fields for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Section fields.
	 */
	public function fields(): array {
		return array(
			array(
				'title' => __( 'CartBay Log Configuration', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Control CartBay-owned troubleshooting logs. WooCommerce native logs remain available separately.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'    => 'cartbay_log_settings',
			),
			array(
				'title'    => __( 'CartBay Logging', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc'     => __( 'Enable CartBay-owned troubleshooting log', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc_tip' => __( 'Keeps a bounded CartBay log in the database (in addition to WooCommerce native logs). Sensitive values such as emails, license keys, and tokens are redacted.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'       => 'cartbay_settings[log_enabled]',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'             => __( 'CartBay Log Retention (days)', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'desc'              => __( 'CartBay log entries older than this are removed automatically. The log also keeps at most the most recent few hundred entries.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'id'                => 'cartbay_settings[log_retention_days]',
				'default'           => 7,
				'type'              => 'number',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min' => 1,
					'max' => 30,
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cartbay_log_settings',
			),
		);
	}

	/**
	 * Render the logs section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<p><a class="button" href="' . esc_url( $this->url->section( 'settings' ) ) . '">' . esc_html__( 'Back to Settings', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</a></p>';
		woocommerce_admin_fields( $this->fields() );
		$this->render_logs_table();
	}

	/**
	 * Save section-specific data after WooCommerce updates settings API fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce handled by Woo settings save.
		$posted_log_enabled = isset( $_POST['cartbay_settings']['log_enabled'] ) ? 'yes' : 'no';
		$settings           = get_option( 'cartbay_settings', array() );
		$settings           = is_array( $settings ) ? $settings : array();

		$settings['log_enabled'] = $posted_log_enabled;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce handled by Woo settings save.
		$settings['log_retention_days'] = isset( $_POST['cartbay_settings']['log_retention_days'] ) ? max( 1, min( 30, absint( wp_unslash( $_POST['cartbay_settings']['log_retention_days'] ) ) ) ) : 7;

		update_option( 'cartbay_settings', $settings );

		Logger::info(
			'CartBay log settings saved.',
			array(
				'log_enabled'        => $settings['log_enabled'],
				'log_retention_days' => $settings['log_retention_days'],
			),
			'logs'
		);
	}

	/**
	 * Render all CartBay file log entries in a table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_logs_table(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'DESC';
		$level = isset( $_GET['level'] ) ? sanitize_key( $_GET['level'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page     = 20;
		$order        = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
		$valid_levels = array( 'info', 'warning', 'error' );
		$level        = in_array( $level, $valid_levels, true ) ? $level : '';
		$total        = Logger::count_entries( $level );
		$total_pages  = (int) ceil( $total / $per_page );
		$paged        = max( 1, min( $paged, max( 1, $total_pages ) ) );
		$offset       = ( $paged - 1 ) * $per_page;
		$entries      = Logger::get_entries( 0, $order, $offset, $per_page, $level );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filter_base = add_query_arg(
			array_filter(
				array(
					'level' => '' !== $level ? $level : null,
					'order' => strtolower( $order ),
				),
				static fn ( $v ): bool => null !== $v
			),
			$this->url->section( 'logs' )
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<h2>' . esc_html__( 'CartBay Log Entries', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</h2>';
		echo '<p class="description cartbay-section-description">' . esc_html__( 'These entries are stored in the WordPress database and sanitized for support. Context values for emails, tokens, and licenses are redacted.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</p>';

		echo '<div class="cartbay-log-filters">';
		echo '<div class="tablenav top"><div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="cartbay-log-level-filter">' . esc_html__( 'Filter by level', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</label>';
		echo '<select name="level" id="cartbay-log-level-filter">';
		echo '<option value=""' . selected( '', $level, false ) . '>' . esc_html__( 'All levels', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</option>';
		foreach ( $valid_levels as $l ) {
			echo '<option value="' . esc_attr( $l ) . '"' . selected( $l, $level, false ) . '>' . esc_html( $this->level_label( $l ) ) . '</option>';
		}
		echo '</select> ';
		echo '<button type="button" id="cartbay-log-query-submit" class="button" data-base-url="' . esc_url( $filter_base ) . '">' . esc_html__( 'Filter', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</button>';
		echo '</div>';
		if ( $total > 0 ) {
			echo '<div class="tablenav-pages one-page">';
			/* translators: %s: number of items */
			echo '<span class="displaying-num">' . esc_html( sprintf( __( '%s entries', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), number_format_i18n( $total ) ) ) . '</span>';
			echo '</div>';
		}
		echo '<br class="clear" /></div></div>';

		echo '<div class="cartbay-table-wrap"><table class="wp-list-table widefat fixed striped table-view-list cartbay-log-table"><thead><tr>';
		echo '<th class="manage-column column-primary column-timestamp' . ( 'DESC' === $order ? ' sorted asc' : ' sortable desc' ) . '">';
		echo '<a href="' . esc_url( add_query_arg( 'order', 'DESC' === $order ? 'asc' : 'desc', $filter_base ) ) . '">';
		echo '<span>' . esc_html__( 'Time', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</span>';
		echo '<span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span>';
		echo '</a></th>';
		echo '<th class="manage-column column-level">' . esc_html__( 'Level', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th class="manage-column column-system">' . esc_html__( 'System', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th class="manage-column column-message">' . esc_html__( 'Message', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th class="manage-column column-context-count">' . esc_html__( 'Context', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th class="manage-column column-actions">' . esc_html__( 'Actions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $entries ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No log entries found for the selected filter.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</td></tr>';
		} else {
			foreach ( $entries as $entry ) {
				$this->render_log_row( $entry );
			}
		}

		echo '</tbody></table></div>';
		$this->render_pagination( $paged, $total, $total_pages, $filter_base );
	}

	/**
	 * Render one log table row.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $entry Log entry.
	 *
	 * @return void
	 */
	private function render_log_row( array $entry ): void {
		$timestamp     = sanitize_text_field( (string) ( $entry['timestamp'] ?? '' ) );
		$level         = sanitize_key( (string) ( $entry['level'] ?? 'info' ) );
		$system        = sanitize_key( (string) ( $entry['system'] ?? 'core' ) );
		$message       = sanitize_text_field( (string) ( $entry['message'] ?? '' ) );
		$context       = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
		$context_count = count( $context );
		$encoded       = wp_json_encode( $entry, JSON_PRETTY_PRINT );
		$encoded       = is_string( $encoded ) ? $encoded : '';

		echo '<tr class="cartbay-log-entry">';
		echo '<td class="column-primary column-timestamp" data-colname="' . esc_attr__( 'Time', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '"><code>' . esc_html( $timestamp ) . '</code></td>';
		echo '<td class="column-level" data-colname="' . esc_attr__( 'Level', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '"><span class="cartbay-badge cartbay-badge--' . esc_attr( $level ) . '">' . esc_html( $this->level_label( $level ) ) . '</span></td>';
		echo '<td class="column-system" data-colname="' . esc_attr__( 'System', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( $system ) . '</td>';
		echo '<td class="column-message" data-colname="' . esc_attr__( 'Message', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( $message ) . '</td>';
		/* translators: %s: number of context fields recorded for the log entry. */
		echo '<td class="column-context-count" data-colname="' . esc_attr__( 'Context', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( sprintf( _n( '%s item', '%s items', $context_count, 'cartbay-abandoned-cart-recovery-for-woocommerce' ), number_format_i18n( $context_count ) ) ) . '</td>';
		echo '<td class="column-actions" data-colname="' . esc_attr__( 'Actions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">';
		echo '<textarea class="cartbay-log-entry-copy-source screen-reader-text" readonly="readonly">' . esc_textarea( $encoded ) . '</textarea>';
		echo '<button type="button" class="button cartbay-log-details-trigger" data-modal-title="' . esc_attr__( 'CartBay Log Entry', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '" data-entry="' . esc_attr( $encoded ) . '">' . esc_html__( 'Details', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</button> ';
		echo '<button type="button" class="button cartbay-copy-log-entry">' . esc_html__( 'Copy Entry', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</button>';
		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render pagination for the logs table.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $paged          Current page.
	 * @param int    $total          Total rows.
	 * @param int    $total_pages    Total pages.
	 * @param string $filter_base    Filter and sort base URL.
	 *
	 * @return void
	 */
	private function render_pagination( int $paged, int $total, int $total_pages, string $filter_base ): void {
		if ( $total_pages <= 1 ) {
			return;
		}

		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		/* translators: %s: number of items */
		echo '<span class="displaying-num">' . esc_html( sprintf( __( '%s entries', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), number_format_i18n( $total ) ) ) . '</span>';
		echo '<span class="pagination-links">';

		if ( $paged > 1 ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', 1, $filter_base ) ) . '">&laquo;</a> ';
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $paged - 1, $filter_base ) ) . '">&lsaquo;</a> ';
		} else {
			echo '<span class="button disabled">&laquo;</span> ';
			echo '<span class="button disabled">&lsaquo;</span> ';
		}

		/* translators: 1: current page, 2: total pages */
		echo esc_html( sprintf( __( '%1$d of %2$d', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), $paged, $total_pages ) );

		if ( $paged < $total_pages ) {
			echo ' <a class="button" href="' . esc_url( add_query_arg( 'paged', $paged + 1, $filter_base ) ) . '">&rsaquo;</a>';
			echo ' <a class="button" href="' . esc_url( add_query_arg( 'paged', $total_pages, $filter_base ) ) . '">&raquo;</a>';
		} else {
			echo ' <span class="button disabled">&rsaquo;</span>';
			echo ' <span class="button disabled">&raquo;</span>';
		}

		echo '</span></div></div>';
	}

	/**
	 * Translated display label for a log level slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Log level slug.
	 *
	 * @return string Translated label, or the capitalized slug for unknown levels.
	 */
	private function level_label( string $slug ): string {
		$labels = array(
			'info'    => __( 'Info', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'warning' => __( 'Warning', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'error'   => __( 'Error', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);

		return $labels[ $slug ] ?? ucfirst( $slug );
	}
}
