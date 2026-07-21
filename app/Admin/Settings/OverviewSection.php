<?php
/**
 * Overview settings section.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Recovery\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Overview section with analytics cards and session table.
 *
 * @since 1.0.0
 */
class OverviewSection extends AbstractSettingsSection {
	/**
	 * Analytics service.
	 *
	 * @since 1.0.0
	 *
	 * @var AnalyticsService
	 */
	private AnalyticsService $analytics_service;

	/**
	 * Notification service.
	 *
	 * @since 1.0.0
	 *
	 * @var NotificationService
	 */
	private NotificationService $notification_service;

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
	 * @param AnalyticsService    $analytics_service    Analytics service.
	 * @param NotificationService $notification_service Notification service.
	 * @param SettingsUrl         $url                  Settings URL helper.
	 */
	public function __construct( AnalyticsService $analytics_service, NotificationService $notification_service, SettingsUrl $url ) {
		$this->analytics_service    = $analytics_service;
		$this->notification_service = $notification_service;
		$this->url                  = $url;
	}

	/**
	 * Get the section identifier used in the URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section identifier.
	 */
	public function id(): string {
		return 'overview';
	}

	/**
	 * Get the navigation label for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section label.
	 */
	public function label(): string {
		return __( 'Overview', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
	}

	/**
	 * Get WooCommerce settings API fields for the section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Section fields.
	 */
	public function fields(): array {
		return array();
	}

	/**
	 * Render the overview section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$period = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30;
		if ( ! in_array( $period, array( 7, 30, 90 ), true ) ) {
			$period = 30;
		}

		$data            = $this->analytics_service->get( $period );
		$abandoned_value = function_exists( 'wc_price' ) ? wc_price( (float) $data['abandoned_value'] ) : (string) $data['abandoned_value'];
		$revenue         = function_exists( 'wc_price' ) ? wc_price( (float) $data['revenue'] ) : (string) $data['revenue'];
		$cards           = array(
			array(
				'label'   => __( 'Tracked Carts', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => (string) $data['tracked'],
				'tooltip' => __( 'Captured checkout sessions created by CartBay during the selected period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
			array(
				'label'   => __( 'Abandoned Carts', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => (string) $data['abandoned'],
				'tooltip' => __( 'Tracked carts that passed the inactivity timeout during the selected period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
			array(
				'label'   => __( 'Recovered Carts', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => (string) $data['recovered'],
				'tooltip' => __( 'Abandoned CartBay sessions that matched a later WooCommerce order in the selected period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
			array(
				'label'      => __( 'Abandoned Cart Value', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'      => $abandoned_value,
				'tooltip'    => __( 'Total cart value for sessions that became abandoned during the selected period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'allow_html' => true,
			),
			array(
				'label'      => __( 'Recovered Revenue', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'      => $revenue,
				'tooltip'    => __( 'Revenue from WooCommerce orders matched to recovered CartBay sessions in the selected period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'allow_html' => true,
			),
			array(
				'label'   => __( 'Recovery Rate', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'value'   => (string) $data['recovery_rate'] . '%',
				'tooltip' => __( 'Recovered carts divided by abandoned carts for the selected period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			),
		);

		/**
		 * Filter the Overview metric cards.
		 *
		 * Extensions can append their own metric cards here (for example deeper
		 * reporting such as restore-link clicks, click-to-recovery rate, or
		 * shopper behaviour). The current period and the core analytics payload
		 * are passed so extensions can compute additional figures without
		 * re-querying. Each card is an array with `label`, `value`, optional
		 * `tooltip`, and optional `allow_html` keys.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $cards  Metric cards to render.
		 * @param int                              $period Selected reporting period in days.
		 * @param array<string, mixed>             $data   Core analytics payload for the period.
		 */
		$cards    = apply_filters( 'cartbay_overview_metric_cards', $cards, $period, $data );
		$periods  = array(
			7  => __( '7 Days', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			30 => __( '30 Days', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			90 => __( '90 Days', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);
		$base_url = $this->url->section( 'overview' );

		echo '<div class="cartbay-overview-header">';
		echo '<h2 class="cartbay-overview-title">' . esc_html__( 'CartBay Overview', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</h2>';
		echo '<div class="cartbay-button-group">';
		foreach ( $periods as $days => $label ) {
			$url   = $base_url . '&period=' . $days;
			$class = $days === $period ? 'button button-primary' : 'button';
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</div></div>';

		echo '<div class="cartbay-card-grid cartbay-card-grid--metrics">';

		foreach ( $cards as $card ) {
			$value      = (string) $card['value'];
			$label      = (string) $card['label'];
			$tooltip    = (string) ( $card['tooltip'] ?? '' );
			$allow_html = ! empty( $card['allow_html'] );

			$this->render_metric_card( $label, $value, $tooltip, $allow_html );
		}

		echo '</div>';

		$this->render_sessions_table( $base_url, $period );
		$this->render_help_section();
	}

	/**
	 * Render overview help actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_help_section(): void {
		$wizard_url    = admin_url( 'admin.php?page=cartbay-wizard' );
		$support_email = 'support@wpanchorbay.com';

		echo '<section class="cartbay-help-panel" aria-labelledby="cartbay-help-title">';
		echo '<div class="cartbay-help-panel__content">';
		echo '<h3 id="cartbay-help-title">' . esc_html__( 'Help', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</h3>';
		echo '<p>' . esc_html__( 'Read the documentation to learn how to get started with CartBay. If you have any questions, please email us.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</p>';
		echo '<a class="" href="' . esc_url( 'mailto:' . $support_email ) . '">' . esc_html( $support_email ) . '</a>';
		echo '</div>';
		echo '<div class="cartbay-help-panel__actions">';
		echo '<a class="button button-secondary" href="' . esc_url( CARTBAY_DOCS_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Documentation', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</a>';
		echo '<a class="button button-primary" href="' . esc_url( $wizard_url ) . '">' . esc_html__( 'Open Setup Wizard', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</a>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render the sessions table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $base_url Base overview URL.
	 * @param int    $period   Reporting period.
	 *
	 * @return void
	 */
	private function render_sessions_table( string $base_url, int $period ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$paged         = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$orderby       = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'last_activity';
		$order         = isset( $_GET['order'] ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'DESC';
		$search_query  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page       = 20;
		$valid_statuses = array( 'captured', 'abandoned', 'recovered', 'suppressed' );
		$valid_orderby  = array( 'session_id', 'cart_total', 'created', 'last_activity', 'emails_sent' );
		$query_status   = in_array( $status_filter, $valid_statuses, true ) ? 'wc-cartbay-' . $status_filter : '';

		$orderby = in_array( $orderby, $valid_orderby, true ) ? $orderby : 'last_activity';
		$order   = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
		$since   = gmdate( 'Y-m-d H:i:s', time() - ( $period * DAY_IN_SECONDS ) );

		$args         = array(
			'status'       => '' !== $query_status ? $query_status : array( 'wc-cartbay-captured', 'wc-cartbay-abandoned', 'wc-cartbay-recovered', 'wc-cartbay-suppressed' ),
			'date_created' => '>=' . $since,
			'limit'        => -1,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'return'       => 'objects',
		);
		$all_sessions = function_exists( 'wc_get_orders' ) ? wc_get_orders( $args ) : array();

		if ( '' !== $search_query ) {
			$all_sessions = array_filter(
				$all_sessions,
				function ( $session ) use ( $search_query ): bool {
					$email = $session->get_billing_email();
					$id    = (string) $session->get_id();
					return false !== stripos( $email, $search_query ) || false !== stripos( $id, $search_query );
				}
			);
		}

		usort(
			$all_sessions,
			function ( $a, $b ) use ( $orderby, $order ): int {
				switch ( $orderby ) {
					case 'session_id':
						$a_value = absint( $a->get_id() );
						$b_value = absint( $b->get_id() );
						break;
					case 'cart_total':
						$a_value = floatval( $a->get_meta( '_cartbay_cart_total', true ) );
						$b_value = floatval( $b->get_meta( '_cartbay_cart_total', true ) );
						break;
					case 'emails_sent':
						$a_value = $this->count_session_successful_notifications( $a->get_id() );
						$b_value = $this->count_session_successful_notifications( $b->get_id() );
						break;
					case 'last_activity':
						$a_value = absint( $a->get_meta( '_cartbay_last_activity_at', true ) );
						$b_value = absint( $b->get_meta( '_cartbay_last_activity_at', true ) );
						break;
					case 'created':
					default:
						$a_date  = $a->get_date_created();
						$b_date  = $b->get_date_created();
						$a_value = $a_date ? $a_date->getTimestamp() : 0;
						$b_value = $b_date ? $b_date->getTimestamp() : 0;
						break;
				}

				$result = $a_value <=> $b_value;

				if ( 0 === $result ) {
					$result = absint( $a->get_id() ) <=> absint( $b->get_id() );
				}

				return 'ASC' === $order ? $result : -$result;
			}
		);

		$total       = count( $all_sessions );
		$total_pages = (int) ceil( $total / $per_page );
		$sessions    = array_slice( $all_sessions, ( $paged - 1 ) * $per_page, $per_page );

		$status_labels       = $this->get_status_labels();
		$status_descriptions = $this->get_status_descriptions();
		$status_counts       = $this->get_status_counts( array_keys( $status_labels ) );
		$filter_base         = add_query_arg(
			array(
				'period'  => $period,
				'orderby' => $orderby,
				'order'   => strtolower( $order ),
			),
			$base_url
		);
		$pagination_base     = '' !== $status_filter ? add_query_arg( 'status', $status_filter, $filter_base ) : $filter_base;
		if ( '' !== $search_query ) {
			$pagination_base = add_query_arg( 's', $search_query, $pagination_base );
		}
		$sort_header = function ( string $key, string $label, string $column_class = '' ) use ( $base_url, $period, $status_filter, $search_query, $orderby, $order ): string {
			$is_current = $key === $orderby;
			$next_order = $is_current && 'ASC' === $order ? 'desc' : 'asc';
			$sort_class = $is_current ? 'sorted ' . strtolower( $order ) : 'sortable desc';
			$url        = add_query_arg(
				array_filter(
					array(
						'period'  => $period,
						'status'  => $status_filter,
						's'       => $search_query,
						'orderby' => $key,
						'order'   => $next_order,
					),
					static fn ( $value ): bool => '' !== $value
				),
				$base_url
			);

			return '<th scope="col" class="manage-column ' . esc_attr( trim( $column_class . ' ' . $sort_class ) ) . '"><a href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a></th>';
		};

		echo '<h3>' . esc_html__( 'Sessions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</h3>';

		echo '<div class="cartbay-session-filters">';
		echo '<div class="tablenav top"><div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="cartbay-session-status-filter">' . esc_html__( 'Filter by session status', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</label>';
		echo '<select name="status" id="cartbay-session-status-filter">';
		echo '<option value=""' . selected( '', $status_filter, false ) . '>' . esc_html__( 'All statuses', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</option>';
		foreach ( $status_labels as $slug => $label ) {
			$short = str_replace( 'wc-cartbay-', '', $slug );
			echo '<option value="' . esc_attr( $short ) . '"' . selected( $short, $status_filter, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<button type="button" id="cartbay-session-query-submit" class="button" data-base-url="' . esc_url( $filter_base ) . '">' . esc_html__( 'Filter', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</button>';
		echo '</div>';
		echo '<div class="alignleft actions">';
		/**
		 * Fires in the sessions table toolbar, alongside the filter controls.
		 *
		 * Extensions can add their own toolbar controls here (for example CSV
		 * export buttons) without modifying the free plugin. The current
		 * session-list view context is passed so extensions can honour the
		 * active filters.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $context Current session-list view context
		 *                                       (status, search, orderby, order, period).
		 */
		do_action(
			'cartbay_overview_after_table_actions',
			array(
				'status'  => $status_filter,
				'search'  => $search_query,
				'orderby' => $orderby,
				'order'   => $order,
				'period'  => $period,
			)
		);
		echo '</div>';
		echo '<div class="alignright">';
		echo '<label class="screen-reader-text" for="cartbay-session-search-input">' . esc_html__( 'Search sessions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</label>';
		echo '<input type="search" id="cartbay-session-search-input" name="s" value="' . esc_attr( $search_query ) . '" placeholder="' . esc_attr__( 'Email or ID', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '" />';
		echo '<input type="button" id="cartbay-session-search-submit" class="button" value="' . esc_attr__( 'Search', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '" data-base-url="' . esc_url( $filter_base ) . '" />';
		echo '</div>';
		echo '<br class="clear" /></div></div>';

		echo '<table class="wp-list-table widefat fixed striped table-view-list cartbay-sessions-table"><thead><tr>';
		echo $sort_header( 'session_id', __( 'Session', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-primary column-session-id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<th scope="col" class="manage-column column-email">' . esc_html__( 'Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th scope="col" class="manage-column column-status">' . esc_html__( 'Status', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo $sort_header( 'cart_total', __( 'Cart Total', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-cart-total' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sort_header( 'created', __( 'Created', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-created' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sort_header( 'last_activity', __( 'Last Activity', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-last-activity' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sort_header( 'emails_sent', __( 'Emails Sent', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-emails-sent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</tr></thead><tfoot><tr>';
		echo $sort_header( 'session_id', __( 'Session', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-primary column-session-id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<th scope="col" class="manage-column column-email">' . esc_html__( 'Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th scope="col" class="manage-column column-status">' . esc_html__( 'Status', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo $sort_header( 'cart_total', __( 'Cart Total', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-cart-total' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sort_header( 'created', __( 'Created', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-created' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sort_header( 'last_activity', __( 'Last Activity', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-last-activity' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sort_header( 'emails_sent', __( 'Emails Sent', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'column-emails-sent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</tr></tfoot><tbody>';

		if ( empty( $sessions ) ) {
			echo '<tr class="no-items"><td class="colspanchange" colspan="7">' . esc_html__( 'No sessions found.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</td></tr>';
		} else {
			foreach ( $sessions as $session ) {
				$detail_url       = $base_url . '&session_id=' . $session->get_id();
				$cart_total       = floatval( $session->get_meta( '_cartbay_cart_total', true ) );
				$sent_count       = $this->count_session_successful_notifications( $session->get_id() );
				$created_at       = $session->get_date_created() ? $session->get_date_created()->getTimestamp() : 0;
				$last_activity_at = absint( $session->get_meta( '_cartbay_last_activity_at', true ) );

				echo '<tr>';
				echo '<td class="column-primary column-session-id" data-colname="' . esc_attr__( 'Session', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '"><strong><a class="row-title" href="' . esc_url( $detail_url ) . '">' . esc_html( '#' . (string) $session->get_id() ) . '</a></strong><button type="button" class="toggle-row"><span class="screen-reader-text">' . esc_html__( 'Show more details', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</span></button></td>';
				echo '<td class="column-email" data-colname="' . esc_attr__( 'Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( $session->get_billing_email() ) . '</td>';
				echo '<td class="column-status" data-colname="' . esc_attr__( 'Status', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( $status_labels[ 'wc-' . $session->get_status() ] ?? $session->get_status() ) . '</td>';
				echo '<td class="column-cart-total" data-colname="' . esc_attr__( 'Cart Total', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $cart_total ) : $cart_total ) . '</td>';
				echo '<td class="column-created" data-colname="' . esc_attr__( 'Created', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( $this->format_local_datetime( $created_at ) ) . '</td>';
				echo '<td class="column-last-activity" data-colname="' . esc_attr__( 'Last Activity', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( $this->format_local_datetime( $last_activity_at ) ) . '</td>';
				echo '<td class="column-emails-sent" data-colname="' . esc_attr__( 'Emails Sent', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '">' . esc_html( (string) $sent_count ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';

		$this->render_pagination( $paged, $total, $total_pages, $pagination_base );
		$this->render_status_guide( $status_labels, $status_descriptions, $status_counts );
	}

	/**
	 * Count successful notifications for a session.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id CartBay session order ID.
	 *
	 * @return int Successful notification count.
	 */
	private function count_session_successful_notifications( int $session_id ): int {
		$count = 0;

		foreach ( $this->notification_service->get_session_notifications( $session_id ) as $notification ) {
			$status = sanitize_key( (string) ( $notification['status'] ?? '' ) );

			if ( in_array( $status, array( 'sent', 'delivered' ), true ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get session status labels.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Labels keyed by status.
	 */
	private function get_status_labels(): array {
		return array(
			'wc-cartbay-captured'   => __( 'Captured', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-abandoned'  => __( 'Abandoned', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-recovered'  => __( 'Recovered', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-suppressed' => __( 'Suppressed', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);
	}

	/**
	 * Get session status descriptions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Descriptions keyed by status.
	 */
	private function get_status_descriptions(): array {
		return array(
			'wc-cartbay-captured'   => __( 'Shopper email and cart data were captured and the cart is still inside the abandonment timeout.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-abandoned'  => __( 'The cart passed the inactivity timeout and is eligible for recovery emails.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-recovered'  => __( 'A later WooCommerce order matched this CartBay session.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-suppressed' => __( 'The shopper or email is excluded from recovery messaging.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);
	}

	/**
	 * Get current session counts by status.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $statuses Status slugs.
	 *
	 * @return array<string, int> Counts keyed by status.
	 */
	private function get_status_counts( array $statuses ): array {
		$counts = array();

		foreach ( $statuses as $status_slug ) {
			$counts[ $status_slug ] = function_exists( 'wc_get_orders' )
				? count(
					wc_get_orders(
						array(
							'status' => $status_slug,
							'limit'  => -1,
							'return' => 'ids',
						)
					)
				)
				: 0;
		}

		return $counts;
	}

	/**
	 * Render session table pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $paged           Current page.
	 * @param int    $total           Total rows.
	 * @param int    $total_pages     Total pages.
	 * @param string $pagination_base Pagination base URL.
	 *
	 * @return void
	 */
	private function render_pagination( int $paged, int $total, int $total_pages, string $pagination_base ): void {
		if ( $total_pages <= 1 ) {
			return;
		}

		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		/* translators: %s: number of items */
		echo '<span class="displaying-num">' . esc_html( sprintf( __( '%s items', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), number_format_i18n( $total ) ) ) . '</span>';
		echo '<span class="pagination-links">';

		if ( $paged > 1 ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', 1, $pagination_base ) ) . '">&laquo;</a> ';
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $paged - 1, $pagination_base ) ) . '">&lsaquo;</a> ';
		} else {
			echo '<span class="button disabled">&laquo;</span> ';
			echo '<span class="button disabled">&lsaquo;</span> ';
		}

		/* translators: 1: current page, 2: total pages */
		echo esc_html( sprintf( __( '%1$d of %2$d', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), $paged, $total_pages ) );

		if ( $paged < $total_pages ) {
			echo ' <a class="button" href="' . esc_url( add_query_arg( 'paged', $paged + 1, $pagination_base ) ) . '">&rsaquo;</a>';
			echo ' <a class="button" href="' . esc_url( add_query_arg( 'paged', $total_pages, $pagination_base ) ) . '">&raquo;</a>';
		} else {
			echo ' <span class="button disabled">&rsaquo;</span>';
			echo ' <span class="button disabled">&raquo;</span>';
		}

		echo '</span></div></div>';
	}

	/**
	 * Render the status guide table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $status_labels       Status labels.
	 * @param array<string, string> $status_descriptions Status descriptions.
	 * @param array<string, int>    $status_counts       Status counts.
	 *
	 * @return void
	 */
	private function render_status_guide( array $status_labels, array $status_descriptions, array $status_counts ): void {
		echo '<div class="cartbay-status-guide" style="margin-top:20px;max-width:1100px;">';
		echo '<h3>' . esc_html__( 'Status Guide', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Current session counts help explain the table filters. Overview cards above use the selected reporting period.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Status', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Current Sessions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Meaning', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $status_labels as $status_slug => $status_label ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $status_label ) . '</strong></td>';
			echo '<td>' . esc_html( number_format_i18n( $status_counts[ $status_slug ] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( $status_descriptions[ $status_slug ] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
