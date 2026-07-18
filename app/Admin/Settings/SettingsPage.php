<?php
/**
 * WooCommerce settings page coordinator.
 *
 * @package WPAnchorBay\CartBay\Admin\Settings
 */

namespace WPAnchorBay\CartBay\Admin\Settings;

use WPAnchorBay\CartBay\Admin\Wizard\WizardController;
use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Core\Container;
use WPAnchorBay\CartBay\Core\Settings;
use WPAnchorBay\CartBay\Recovery\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates CartBay's WooCommerce settings tab and sections.
 *
 * @since 1.0.0
 */
class SettingsPage {
	/**
	 * Field renderer.
	 *
	 * @since 1.0.0
	 *
	 * @var FieldRenderer
	 */
	private FieldRenderer $field_renderer;

	/**
	 * Settings URL helper.
	 *
	 * @since 1.0.0
	 *
	 * @var SettingsUrl
	 */
	private SettingsUrl $url;

	/**
	 * Admin environment helper.
	 *
	 * @since 1.0.0
	 *
	 * @var AdminEnvironment
	 */
	private AdminEnvironment $environment;

	/**
	 * Settings section instances keyed by section ID.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, SettingsSectionInterface>
	 */
	private array $sections;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Container        $container      Service container.
	 * @param FieldRenderer    $field_renderer Field renderer.
	 * @param SettingsUrl      $url            Settings URL helper.
	 * @param AdminEnvironment $environment    Admin environment helper.
	 */
	public function __construct( Container $container, FieldRenderer $field_renderer, SettingsUrl $url, AdminEnvironment $environment ) {
		$this->field_renderer = $field_renderer;
		$this->url            = $url;
		$this->environment    = $environment;
		$this->sections       = $this->build_sections( $container );
	}

	/**
	 * Register WordPress and WooCommerce hooks for the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'register_wc_settings_tab' ), 25 );
		add_action( 'woocommerce_settings_tabs_cartbay', array( $this, 'render_wc_settings_tab' ) );
		add_action( 'woocommerce_update_options_cartbay', array( $this, 'save_wc_settings_tab' ) );
		add_filter( 'woocommerce_cartbay_settings', array( $this, 'get_wc_settings_fields' ) );
		add_action( 'woocommerce_admin_field_cartbay_static_row', array( $this->field_renderer, 'render_static_row' ) );
		add_action( 'woocommerce_admin_field_cartbay_action_row', array( $this->field_renderer, 'render_action_row' ) );
		add_action( 'woocommerce_admin_field_cartbay_sequence_designer', array( $this->sections['sequence'], 'render_sequence_designer_field' ) );
		do_action( 'cartbay_register_admin_fields', $this->field_renderer, $this );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wc_settings_admin_assets' ) );
		add_action( 'admin_post_cartbay_disable_test_mode', array( $this, 'handle_disable_test_mode' ) );
		add_action( 'admin_post_cartbay_enable_test_mode', array( $this, 'handle_enable_test_mode' ) );
		add_filter( 'admin_body_class', array( $this, 'add_wc_settings_body_class' ) );
		add_action( 'admin_footer', array( $this, 'render_wc_settings_modal_templates' ) );
		add_action( 'admin_notices', array( $this, 'show_test_mode_banner' ) );
		add_action( 'admin_notices', array( $this, 'show_smtp_warning' ) );
	}

	/**
	 * Register CartBay's WooCommerce settings tab after Products.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $tabs WooCommerce settings tabs.
	 *
	 * @return array<string, string>
	 */
	public function register_wc_settings_tab( array $tabs ): array {
		$new_tabs = array();

		foreach ( $tabs as $slug => $label ) {
			$new_tabs[ $slug ] = $label;

			if ( 'products' === $slug ) {
				$new_tabs['cartbay'] = __( 'Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
			}
		}

		if ( ! isset( $new_tabs['cartbay'] ) ) {
			$new_tabs['cartbay'] = __( 'Cart', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		}

		return $new_tabs;
	}

	/**
	 * Render CartBay settings inside WooCommerce > Settings > Cart.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_wc_settings_tab(): void {
		$current_section = $this->current_section_id();
		$is_read_only    = in_array( $current_section, array( 'overview', 'templates', 'notifications' ), true );

		echo '<ul class="subsubsub">';

		$visible_sections = array_filter(
			array_keys( $this->sections ),
			static fn ( string $slug ): bool => ! in_array( $slug, array( 'logs' ), true )
		);
		$last_key         = end( $visible_sections );
		foreach ( $this->sections as $slug => $section ) {
			if ( in_array( $slug, array( 'logs' ), true ) ) {
				continue;
			}

			echo '<li><a href="' . esc_url( $this->url->section( $slug ) ) . '" class="' . ( $slug === $current_section ? 'current' : '' ) . '">' . esc_html( $section->label() ) . '</a>';
			if ( $slug !== $last_key ) {
				echo ' | ';
			}
			echo '</li>';
		}

		echo '</ul><br class="clear" />';

		$this->render_wc_settings_context_notices();
		echo '<div class="cartbay-settings-section cartbay-settings-section--' . esc_attr( $current_section ) . ( $is_read_only ? ' cartbay-settings-section--read-only' : '' ) . '">';
		$this->sections[ $current_section ]->render();
		echo '</div>';
	}

	/**
	 * Save CartBay settings via WooCommerce settings API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save_wc_settings_tab(): void {
		$current_section = $this->current_section_id();

		if ( in_array( $current_section, array( 'overview', 'templates', 'notifications' ), true ) ) {
			return;
		}

		woocommerce_update_options( $this->sections[ $current_section ]->fields() );
		$this->sections[ $current_section ]->save();
	}

	/**
	 * Define WooCommerce settings fields for CartBay sections.
	 *
	 * @since 1.0.0
	 *
	 * @param string $section Optional section filter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_wc_settings_fields( string $section = '' ): array {
		if ( '' !== $section ) {
			return isset( $this->sections[ $section ] ) ? $this->sections[ $section ]->fields() : array();
		}

		$fields = array();
		foreach ( $this->sections as $section_instance ) {
			$fields = array_merge( $fields, $section_instance->fields() );
		}

		return $fields;
	}

	/**
	 * Enqueue WooCommerce admin assets for the CartBay settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_wc_settings_admin_assets(): void {
		if ( ! $this->environment->is_cartbay_admin_page() ) {
			return;
		}

		wp_enqueue_style(
			'cartbay-admin',
			CARTBAY_URL . 'assets/css/cartbay-admin.css',
			array(),
			CARTBAY_VERSION
		);

		wp_enqueue_script( 'jquery' );

		$script = <<<'JS'
jQuery( function( $ ) {
	// Test-email button — present on both the wizard Email step and the
	// Notifications section, so bind it before the wc-settings-only handlers.
	$( document.body ).on( 'click', '#cartbay-test-email, #cartbay-test-email-notifications', function( event ) {
		event.preventDefault();

		var btn     = $( this );
		var email   = $( '#cartbay-test-email-address' ).val();
		var $result = $( '#cartbay-test-email-result' );

		btn.prop( 'disabled', true ).text( 'TEST_EMAIL_SENDING_TEXT' );
		$.post( 'TEST_EMAIL_URL', {
			_wpnonce: 'REST_NONCE',
			email: email
		} ).done( function( resp ) {
			$result.text( resp.message || 'TEST_EMAIL_SUCCESS_TEXT' );
		} ).fail( function( jqXHR ) {
			var base = 'TEST_EMAIL_FAILURE_TEXT';
			var reason = jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.reason;
			$result.text( reason ? base + ' ' + reason : base );
		} ).always( function() {
			btn.prop( 'disabled', false ).text( 'TEST_EMAIL_BUTTON_TEXT' );
		} );
	} );

	if ( ! $( 'body' ).hasClass( 'woocommerce_page_wc-settings' ) ) {
		window.setTimeout( function() {
			$( '.cartbay-notice-auto-dismiss.is-dismissible:visible' ).each( function() {
				var $notice = $( this );

				$notice.fadeOut( 250, function() {
					$notice.remove();
				} );
			} );
		}, 10000 );

		return;
	}

	function handleSessionQuery( event ) {
		event.preventDefault();
		window.onbeforeunload = null;

		var $button = $( '#cartbay-session-query-submit' );
		var status = $( '#cartbay-session-status-filter' ).val();
		var search = $( '#cartbay-session-search-input' ).val();
		var url = $button.data( 'baseUrl' );

		if ( ! url ) {
			return;
		}

		if ( status ) {
			url += '&status=' + encodeURIComponent( status );
		}

		if ( search ) {
			url += '&s=' + encodeURIComponent( search );
		}

		window.location = url;
	}

	$( document.body ).on( 'click', '#cartbay-session-query-submit, #cartbay-session-search-submit', handleSessionQuery );

	$( document.body ).on( 'keydown', '#cartbay-session-search-input', function( event ) {
		if ( 13 === event.which ) { // Enter key
			handleSessionQuery( event );
		}
	} );

	$( document.body ).on( 'click', '.cartbay-copy-log-entry', function( event ) {
		event.preventDefault();

		var $button = $( this );
		var text = $button.closest( '.cartbay-log-entry' ).find( '.cartbay-log-entry-copy-source' ).val() || '';

		if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
			window.navigator.clipboard.writeText( text ).then( function() {
				$button.text( 'COPIED_TEXT' );
				window.setTimeout( function() {
					$button.text( 'COPY_ENTRY_TEXT' );
				}, 1500 );
			} );
			return;
		}

		$button.closest( '.cartbay-log-entry' ).find( '.cartbay-log-entry-copy-source' ).trigger( 'select' );
		document.execCommand( 'copy' );
		$button.text( 'COPIED_TEXT' );
		window.setTimeout( function() {
			$button.text( 'COPY_ENTRY_TEXT' );
		}, 1500 );
	} );

	$( document.body ).on( 'click', '.cartbay-log-details-trigger', function( event ) {
		event.preventDefault();

		var $trigger = $( this );
		var modalData = {
			title: $trigger.data( 'modalTitle' ),
			entry: $trigger.attr( 'data-entry' ) || ''
		};

		if ( $.fn.WCBackboneModal ) {
			$trigger.WCBackboneModal( {
				template: 'cartbay-log-entry-modal',
				variable: modalData
			} );
			return;
		}

		window.alert( modalData.entry );
	} );

	$( document.body ).on( 'click', '#cartbay-log-query-submit', function( event ) {
		event.preventDefault();
		window.onbeforeunload = null;

		var $button = $( this );
		var level = $( '#cartbay-log-level-filter' ).val();
		var url = $button.data( 'baseUrl' );

		if ( ! url ) {
			return;
		}

		if ( level ) {
			url += '&level=' + encodeURIComponent( level );
		}

		window.location = url;
	} );

	$( document.body ).on( 'click', '.cartbay-copy-log-entry-modal', function( event ) {
		event.preventDefault();

		var $button = $( this );
		var text = $button.closest( '.wc-backbone-modal' ).find( '.cartbay-log-entry-modal-copy-source' ).val() || '';

		if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
			window.navigator.clipboard.writeText( text ).then( function() {
				$button.text( 'COPIED_TEXT' );
				window.setTimeout( function() {
					$button.text( 'COPY_ENTRY_TEXT' );
				}, 1500 );
			} );
			return;
		}

		$button.closest( '.wc-backbone-modal' ).find( '.cartbay-log-entry-modal-copy-source' ).trigger( 'select' );
		document.execCommand( 'copy' );
		$button.text( 'COPIED_TEXT' );
		window.setTimeout( function() {
			$button.text( 'COPY_ENTRY_TEXT' );
		}, 1500 );
	} );

	$( document.body ).on( 'click', '#cartbay-trigger-test', function( event ) {
		event.preventDefault();

		var btn = $( this );
		btn.prop( 'disabled', true ).text( 'TRIGGERING_TEXT' );
		$.post( 'TRIGGER_URL', {
			_wpnonce: 'REST_NONCE'
		} ).done( function( resp ) {
			alert( resp.message || 'TRIGGER_SUCCESS_TEXT' );
		} ).fail( function( xhr ) {
			var message = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'TRIGGER_FAILURE_TEXT';
			alert( message );
		} ).always( function() {
			btn.prop( 'disabled', false ).text( 'TRIGGER_BUTTON_TEXT' );
		} );
	} );

	function formatDelayLabel( value, unit ) {
		var numericValue = parseInt( value, 10 );

		if ( ! numericValue || numericValue < 1 ) {
			numericValue = 1;
		}

		if ( unit === 'days' ) {
			return numericValue + ' ' + ( numericValue === 1 ? 'day' : 'days' );
		}

		if ( unit === 'hours' ) {
			return numericValue + ' ' + ( numericValue === 1 ? 'hour' : 'hours' );
		}

		return numericValue + ' ' + ( numericValue === 1 ? 'minute' : 'minutes' );
	}

	function updateSequenceSummaries() {
		$( '.cartbay-sequence-card' ).each( function() {
			var $card = $( this );
			var value = $card.find( '.cartbay-delay-value' ).val();
			var unit = $card.find( '.cartbay-delay-unit' ).val();

			$card.find( '.cartbay-sequence-summary__value' ).text( formatDelayLabel( value, unit ) );
		} );
	}

	$( document.body ).on( 'input change', '.cartbay-delay-value, .cartbay-delay-unit', updateSequenceSummaries );

	updateSequenceSummaries();
} );
JS;

		$script = str_replace(
			array(
				'TRIGGERING_TEXT',
				'TRIGGER_URL',
				'REST_NONCE',
				'TRIGGER_SUCCESS_TEXT',
				'TRIGGER_FAILURE_TEXT',
				'TRIGGER_BUTTON_TEXT',
				'COPIED_TEXT',
				'COPY_ENTRY_TEXT',
				'TEST_EMAIL_URL',
				'TEST_EMAIL_SENDING_TEXT',
				'TEST_EMAIL_SUCCESS_TEXT',
				'TEST_EMAIL_FAILURE_TEXT',
				'TEST_EMAIL_BUTTON_TEXT',
			),
			array(
				esc_js( __( 'Triggering...', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_url( rest_url( 'cartbay/v1/test/trigger' ) ),
				esc_js( wp_create_nonce( 'wp_rest' ) ),
				esc_js( __( 'Test flow triggered.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_js( __( 'Failed to trigger the test flow.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_js( __( 'Trigger Test Flow', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_js( __( 'Copied', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_js( __( 'Copy Entry', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_url( rest_url( 'cartbay/v1/test/email' ) ),
				esc_js( __( 'Sending...', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_js( __( 'Test email sent!', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_js( __( 'Failed to send test email.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
				esc_js( __( 'Send Test Email', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
			),
			$script
		);

		wp_add_inline_script( 'jquery', $script );
		do_action( 'cartbay_admin_assets_enqueued', $this->url );

		if ( ! $this->url->is_wc_settings_cartbay_page() ) {
			return;
		}

		wp_enqueue_script( 'wc-backbone-modal' );
		wp_enqueue_script( 'wp-util' );
	}

	/**
	 * Render WooCommerce modal templates used by CartBay settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_wc_settings_modal_templates(): void {
		if ( ! $this->url->is_wc_settings_cartbay_page() ) {
			return;
		}
		do_action( 'cartbay_admin_footer_modal_templates', $this->url );
		?>
		<script type="text/template" id="tmpl-cartbay-log-entry-modal">
			<div class="wc-backbone-modal cartbay-log-entry-modal">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
							<h1>{{ data.title }}</h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></span>
							</button>
						</header>
						<article>
							<textarea class="large-text code cartbay-log-entry-modal-copy-source" rows="16" readonly="readonly">{{ data.entry }}</textarea>
						</article>
						<footer>
							<div class="inner">
								<button type="button" class="button button-large cartbay-copy-log-entry-modal"><?php esc_html_e( 'Copy Entry', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></button>
								<button type="button" class="button button-primary button-large modal-close"><?php esc_html_e( 'Close', 'cartbay-abandoned-cart-recovery-for-woocommerce' ); ?></button>
							</div>
						</footer>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
		<?php
	}

	/**
	 * Disable test mode from the banner action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_disable_test_mode(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to update CartBay settings.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		check_admin_referer( 'cartbay_disable_test_mode', 'cartbay_nonce' );

		$settings              = get_option( 'cartbay_settings', array() );
		$settings              = is_array( $settings ) ? $settings : array();
		$settings['test_mode'] = 'no';
		update_option( 'cartbay_settings', $settings );

		$this->url->redirect_with_notice( 'success', __( 'Test mode disabled.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
	}

	/**
	 * Handle the enable-test-mode action from the Templates section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_enable_test_mode(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to update CartBay settings.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) );
		}

		check_admin_referer( 'cartbay_enable_test_mode', 'cartbay_nonce' );

		$settings              = get_option( 'cartbay_settings', array() );
		$settings              = is_array( $settings ) ? $settings : array();
		$settings['test_mode'] = 'yes';
		update_option( 'cartbay_settings', $settings );

		$redirect_url = add_query_arg(
			array(
				'page'       => 'wc-settings',
				'tab'        => 'cartbay',
				'section'    => 'templates',
				'wc_message' => rawurlencode( __( 'Test mode enabled.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render persistent notices inside the CartBay WooCommerce settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_wc_settings_context_notices(): void {
		if ( ! Settings::is_capture_enabled() ) {
			$capture_url = $this->url->section( 'capture' );
			$this->render_wc_settings_inline_notice(
				'warning',
				sprintf(
					/* translators: %s: URL to the Capture settings section. */
					__( '<strong>CartBay capture is disabled.</strong> CartBay will not capture new abandoned carts until capture is enabled. <a href="%s">Enable Capture</a>.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					esc_url( $capture_url )
				)
			);
		}

		if ( Settings::is_test_mode_enabled() ) {
			$disable_url = wp_nonce_url( admin_url( 'admin-post.php?action=cartbay_disable_test_mode' ), 'cartbay_disable_test_mode', 'cartbay_nonce' );
			$this->render_wc_settings_inline_notice(
				'warning',
				sprintf(
					/* translators: %s: URL to disable test mode. */
					__( '<strong>CartBay Test Mode is active.</strong> Recovery emails use shortened delays. <a href="%s">Disable Test Mode</a> before going live.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
					esc_url( $disable_url )
				)
			);
		}

		$mail_warning = $this->get_mail_warning_message();
		if ( '' !== $mail_warning ) {
			$this->render_wc_settings_inline_notice(
				'warning',
				$mail_warning
			);
		}

		do_action( 'cartbay_admin_context_notices', $this );
	}

	/**
	 * Render a WooCommerce-compatible inline settings notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message HTML.
	 *
	 * @return void
	 */
	public function render_wc_settings_inline_notice( string $type, string $message ): void {
		$allowed_types = array( 'success', 'error', 'warning', 'info' );
		$type          = in_array( $type, $allowed_types, true ) ? $type : 'info';
		$classes       = 'notice notice-' . $type . ' inline';

		echo '<div class="' . esc_attr( $classes ) . '"><p>' . wp_kses_post( $message ) . '</p></div>';
	}

	/**
	 * Add a body class flagging CartBay read-only screens for CSS targeting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $classes Space-separated admin body classes.
	 *
	 * @return string
	 */
	public function add_wc_settings_body_class( string $classes ): string {
		if ( ! $this->environment->is_cartbay_admin_page() ) {
			return $classes;
		}

		$current_section = $this->current_section_id();
		$is_read_only    = $this->url->is_wc_settings_cartbay_page() && in_array( $current_section, array( 'overview', 'templates', 'notifications' ), true );

		if ( $is_read_only ) {
			$classes .= ' cartbay-is-read-only';
		}

		return $classes;
	}

	/**
	 * Show a persistent admin notice when test mode is active.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function show_test_mode_banner(): void {
		if ( ! $this->environment->is_cartbay_admin_page() || $this->url->is_wc_settings_cartbay_page() ) {
			return;
		}

		if ( ! Settings::is_test_mode_enabled() ) {
			return;
		}

		$disable_url = wp_nonce_url( admin_url( 'admin-post.php?action=cartbay_disable_test_mode' ), 'cartbay_disable_test_mode', 'cartbay_nonce' );

		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>' . esc_html__( 'CartBay Test Mode is active.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</strong> ';
		echo esc_html__( 'Recovery emails use shortened delays. Disable before going live.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		echo ' <a href="' . esc_url( $disable_url ) . '">' . esc_html__( 'Disable Test Mode', 'cartbay-abandoned-cart-recovery-for-woocommerce' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Show a warning if no mail delivery service is detected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function show_smtp_warning(): void {
		if ( ! $this->environment->is_cartbay_admin_page() || $this->url->is_wc_settings_cartbay_page() ) {
			return;
		}

		if ( ! $this->is_wizard_email_step() ) {
			return;
		}

		$mail_warning = $this->get_mail_warning_message();

		if ( '' === $mail_warning ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p>' . wp_kses_post( $mail_warning ) . '</p></div>';
	}

	/**
	 * Determine whether the current request is the wizard's Email Delivery step.
	 *
	 * `is_cartbay_admin_page()` only checks `page=cartbay-wizard`, not the current
	 * step, so without this the global SMTP notice would duplicate on every wizard
	 * step instead of just the one that already shows it inline. Resolves the step
	 * key the same way WizardController::render() does, rather than a hardcoded
	 * step number, since Pro injects its own step via `cartbay_wizard_steps`.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the current wizard step is the Email Delivery step.
	 */
	private function is_wizard_email_step(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'cartbay-wizard' !== $page ) {
			return false;
		}

		$step_keys  = array_keys( WizardController::get_steps() );
		$step_count = count( $step_keys );
		$step       = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step       = max( 1, min( $step, $step_count ) );

		return 'email' === ( $step_keys[ $step - 1 ] ?? '' );
	}

	/**
	 * Build the mail environment warning message.
	 *
	 * @since 1.0.0
	 *
	 * @return string Warning HTML, or empty string when no warning is needed.
	 */
	private function get_mail_warning_message(): string {
		$status = $this->environment->get_mail_environment_status();

		if ( ! empty( $status['has_delivery'] ) ) {
			return '';
		}

		if ( ! empty( $status['has_logger'] ) ) {
			$message = __( '<strong>An email logging plugin is active, but no SMTP delivery service was detected.</strong> Emails to buyers may not be delivered reliably.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		} else {
			$message = __( '<strong>No SMTP plugin detected.</strong> Without an SMTP service, recovery emails may land in spam. Consider installing an SMTP plugin.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		}

		$message .= ' ' . __( 'CartBay does not send email itself — this depends on your site\'s mail setup.', 'cartbay-abandoned-cart-recovery-for-woocommerce' );
		$message .= sprintf(
			' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( CARTBAY_DOCS_EMAIL_SETUP_URL ),
			esc_html__( 'Learn how to set up reliable email delivery', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
		);

		return $message;
	}

	/**
	 * Build section instances in navigation order.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container Service container.
	 *
	 * @return array<string, SettingsSectionInterface> Section instances.
	 */
	private function build_sections( Container $container ): array {
		$sections = array(
			new OverviewSection(
				$container->make( AnalyticsService::class ),
				$container->make( NotificationService::class ),
				$this->url
			),
			new CaptureSection(),
			new RecoverySequenceSection(),
			new NotificationsSection(
				$this->url,
				$container->make( AnalyticsService::class ),
				$container->make( MailEnvironmentDetector::class )
			),
			new TemplatesSection( $this->url ),
			new OffersSection(),
			new SettingsSection(),
			new LogsSection( $this->url ),
		);
		$indexed  = array();

		foreach ( $sections as $section ) {
			$indexed[ $section->id() ] = $section;
		}

		/**
		 * Filter the CartBay settings sections after host sections are built.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, SettingsSectionInterface> $indexed Settings sections keyed by section ID.
		 * @param Container                              $container Service container.
		 */
		return apply_filters( 'cartbay_admin_settings_sections', $indexed, $container );
	}

	/**
	 * Get the current section ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string Section ID.
	 */
	private function current_section_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'overview';

		return isset( $this->sections[ $current_section ] ) ? $current_section : 'overview';
	}
}
