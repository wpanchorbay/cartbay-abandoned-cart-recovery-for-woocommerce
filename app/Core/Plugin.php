<?php
/**
 * Plugin bootstrap.
 *
 * @package WPAnchorBay\CartBay\Core
 */

namespace WPAnchorBay\CartBay\Core;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WPAnchorBay\CartBay\Admin\Settings\AdminEnvironment;
use WPAnchorBay\CartBay\Admin\Settings\FieldRenderer;
use WPAnchorBay\CartBay\Admin\Settings\MailEnvironmentDetector;
use WPAnchorBay\CartBay\Admin\Settings\SettingsPage;
use WPAnchorBay\CartBay\Admin\Settings\SettingsUrl;
use WPAnchorBay\CartBay\Analytics\AnalyticsService;
use WPAnchorBay\CartBay\Api\Routes\AnalyticsRoute;
use WPAnchorBay\CartBay\Api\Routes\CaptureRoute;
use WPAnchorBay\CartBay\Api\Routes\TestEmailRoute;
use WPAnchorBay\CartBay\Api\Routes\TestRoute;
use WPAnchorBay\CartBay\Data\SessionRepository;
use WPAnchorBay\CartBay\Email\AbstractCartBayRecoveryEmail;
use WPAnchorBay\CartBay\Email\CartBay_Email_Recovery_1;
use WPAnchorBay\CartBay\Email\CartBay_Email_Recovery_2;
use WPAnchorBay\CartBay\Email\CartBay_Email_Recovery_3;
use WPAnchorBay\CartBay\Recovery\AbandonmentScheduler;
use WPAnchorBay\CartBay\Recovery\CaptureService;
use WPAnchorBay\CartBay\Recovery\EmailSequenceService;
use WPAnchorBay\CartBay\Recovery\NotificationService;
use WPAnchorBay\CartBay\Recovery\RecoveryMatcher;
use WPAnchorBay\CartBay\Recovery\RestoreService;
use WPAnchorBay\CartBay\Utils\Logger;
use WPAnchorBay\CartBay\Utils\TokenHelper;
use WPAnchorBay\CartBay\Admin\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Core plugin bootstrap.
 *
 * @since 1.0.0
 */
class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether the plugin has been initialized already.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Create the plugin bootstrap.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Get the shared plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self Plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->declare_wc_feature_compatibility();
		$this->register_services();
		$this->register_hooks();

		$this->initialized = true;

		do_action( 'cartbay_loaded' );
	}

	/**
	 * Get the service container.
	 *
	 * @since 1.0.0
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Declare WooCommerce feature compatibility.
	 *
	 * Declares compatibility with HPOS, Cart/Checkout Blocks, and other
	 * WooCommerce features that CartBay does not conflict with.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function declare_wc_feature_compatibility(): void {
		add_action(
			'before_woocommerce_init',
			static function (): void {
				if ( ! class_exists( FeaturesUtil::class ) ) {
					return;
				}

				$compatible_features = array(
					'custom_order_tables',
					'cart_checkout_blocks',
					'analytics',
					'product_block_editor',
					'order_attribution',
				);

				foreach ( $compatible_features as $feature ) {
					FeaturesUtil::declare_compatibility( $feature, CARTBAY_BASENAME, true );
				}
			},
			1
		);
	}

	/**
	 * Register service singletons.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_services(): void {
		$this->container->singleton(
			CheckoutFields::class,
			static fn (): CheckoutFields => new CheckoutFields()
		);
		$this->container->singleton(
			SessionRepository::class,
			static fn (): SessionRepository => new SessionRepository()
		);
		$this->container->singleton(
			AbandonmentScheduler::class,
			static fn (): AbandonmentScheduler => new AbandonmentScheduler(
				self::instance()->container()->make( SessionRepository::class ),
				self::instance()->container()->make( NotificationService::class )
			)
		);
		$this->container->singleton(
			CaptureService::class,
			static fn (): CaptureService => new CaptureService(
				self::instance()->container()->make( SessionRepository::class )
			)
		);
		$this->container->singleton(
			NotificationService::class,
			static fn (): NotificationService => new NotificationService(
				self::instance()->session_repository()
			)
		);
		$this->container->singleton(
			EmailSequenceService::class,
			static fn (): EmailSequenceService => new EmailSequenceService(
				self::instance()->session_repository(),
				self::instance()->container()->make( NotificationService::class )
			)
		);
		$this->container->singleton(
			RestoreService::class,
			static fn (): RestoreService => new RestoreService(
				self::instance()->container()->make( SessionRepository::class ),
				self::instance()->container()->make( NotificationService::class )
			)
		);
		$this->container->singleton(
			RecoveryMatcher::class,
			static fn (): RecoveryMatcher => new RecoveryMatcher(
				self::instance()->container()->make( SessionRepository::class ),
				self::instance()->container()->make( NotificationService::class )
			)
		);
		$this->container->singleton(
			AnalyticsService::class,
			static fn (): AnalyticsService => new AnalyticsService()
		);
		$this->container->singleton(
			SettingsUrl::class,
			static fn (): SettingsUrl => new SettingsUrl()
		);
		$this->container->singleton(
			MailEnvironmentDetector::class,
			static fn (): MailEnvironmentDetector => new MailEnvironmentDetector()
		);
		$this->container->singleton(
			AdminEnvironment::class,
			static fn (): AdminEnvironment => new AdminEnvironment(
				self::instance()->container()->make( SettingsUrl::class ),
				self::instance()->container()->make( MailEnvironmentDetector::class )
			)
		);
		$this->container->singleton(
			FieldRenderer::class,
			static fn (): FieldRenderer => new FieldRenderer()
		);
		$this->container->singleton(
			SettingsPage::class,
			static fn (): SettingsPage => new SettingsPage(
				self::instance()->container(),
				self::instance()->container()->make( FieldRenderer::class ),
				self::instance()->container()->make( SettingsUrl::class ),
				self::instance()->container()->make( AdminEnvironment::class )
			)
		);
	}

	/**
	 * Register WordPress and WooCommerce hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'init', array( $this, 'register_order_statuses' ) );
		add_filter( 'woocommerce_register_shop_order_statuses', array( $this, 'add_wc_order_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_wc_order_statuses' ) );
		add_action( 'init', array( $this, 'register_cpts' ) );
		add_action( 'init', array( Installer::class, 'maybe_schedule_recurring_jobs' ), 20 );
		add_action( 'action_scheduler_init', array( Installer::class, 'maybe_schedule_recurring_jobs' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_filter( 'plugin_action_links_' . CARTBAY_BASENAME, array( $this, 'add_plugin_action_links' ) );
		$this->settings_page()->register_hooks();
		add_action( 'wp_mail_failed', array( $this, 'handle_wp_mail_failed' ) );
		add_action( 'wp_mail_succeeded', array( $this, 'handle_wp_mail_succeeded' ) );
		add_action( 'cartbay_detect_abandonment', array( $this->abandonment_scheduler(), 'run' ) );
		add_action( 'cartbay_detect_session_abandonment', array( $this->abandonment_scheduler(), 'run_for_session' ) );
		add_action( 'cartbay_send_recovery_email', array( $this->email_sequence_service(), 'send_step' ), 10, 2 );
		add_action( 'cartbay_refresh_analytics', array( $this->analytics_service(), 'refresh' ) );
		add_action( 'cartbay_prune_sessions', array( $this->session_repository(), 'prune_expired' ) );

		// Recovery engine.
		add_action( 'template_redirect', array( $this, 'handle_restore_request' ) );
		add_action( 'init', array( $this, 'handle_unsubscribe_request' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );
		add_filter( 'woocommerce_email_preview_placeholders', array( $this, 'add_email_preview_placeholders' ) );
		add_filter( 'woocommerce_prepare_email_for_preview', array( $this, 'prepare_cartbay_email_preview' ) );
		add_filter( 'woocommerce_email_preview_email_content_setting_ids', array( $this, 'add_email_preview_content_setting_ids' ), 10, 2 );
		$this->container->make( RecoveryMatcher::class )->register_hooks();

		// Frontend notices.
		add_action( 'wp', array( $this, 'display_frontend_notices' ) );

		// Pre-fill billing email on checkout for restored sessions.
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_restored_email' ), 10, 2 );

		// Wizard redirect on first activation.
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );

		$this->checkout_fields()->init();

		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Frontend capture script (classic checkout only).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_capture_assets' ) );

		// Block checkout capture script.
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( $this, 'enqueue_block_capture_assets' ) );
	}

	/**
	 * Register CartBay custom order statuses.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_order_statuses(): void {
		$cartbay_statuses = $this->get_order_status_labels();
		$label_counts     = $this->get_order_status_count_labels();

		foreach ( $cartbay_statuses as $slug => $label ) {
			register_post_status(
				$slug,
				array(
					'label'                     => $label,
					'public'                    => false,
					'show_in_admin_all_list'    => false,
					'show_in_admin_status_list' => false,
					'exclude_from_search'       => true,
					'label_count'               => $label_counts[ $slug ],
				)
			);
		}
	}

	/**
	 * Add CartBay statuses to the WooCommerce order status list.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $statuses Existing statuses.
	 *
	 * @return array<string, string>
	 */
	public function add_wc_order_statuses( array $statuses ): array {
		return array_merge( $statuses, $this->get_order_status_labels() );
	}

	/**
	 * Register CartBay private post types.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_cpts(): void {
		register_post_type(
			'cartbay_template',
			array(
				'label'           => __( 'CartBay Templates', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'public'          => false,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'editor', 'revisions', 'custom-fields' ),
				'capability_type' => 'post',
				'has_archive'     => false,
			)
		);

		register_post_type(
			'cartbay_suppressed',
			array(
				'label'           => __( 'CartBay Suppressions', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'public'          => false,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'has_archive'     => false,
			)
		);
	}

	/**
	 * Register the CartBay admin menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		$container = $this->container;

		if ( Settings::is_wc_menu_enabled() ) {
			add_submenu_page(
				'woocommerce',
				__( 'CartBay', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				__( 'CartBay', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'manage_woocommerce',
				'admin.php?page=wc-settings&tab=cartbay'
			);
		}

		// Wizard page (not shown in menu).
		add_submenu_page(
			'cartbay-wizard',
			__( 'CartBay Setup Wizard', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			__( 'Setup Wizard', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'manage_woocommerce',
			'cartbay-wizard',
			array( new Wizard\WizardController( $container ), 'render' )
		);
	}

	/**
	 * Add action links to the plugin row on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 *
	 * @return array<int, string>
	 */
	public function add_plugin_action_links( array $links ): array {
		$overview_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=cartbay' ) ),
			esc_html__( 'Overview', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
		);
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( CARTBAY_SETTINGS_URL ) ),
			esc_html__( 'Settings', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
		);
		$docs_link     = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( CARTBAY_DOCS_URL ),
			esc_html__( 'Docs', 'cartbay-abandoned-cart-recovery-for-woocommerce' )
		);

		array_unshift( $links, $overview_link, $settings_link, $docs_link );

		return $links;
	}

	/**
	 * Get CartBay status labels keyed by slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private function get_order_status_labels(): array {
		return array(
			'wc-cartbay-captured'   => __( 'CartBay: Captured', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-abandoned'  => __( 'CartBay: Abandoned', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-recovered'  => __( 'CartBay: Recovered', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-expired'    => __( 'CartBay: Expired', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			'wc-cartbay-suppressed' => __( 'CartBay: Suppressed', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);
	}

	/**
	 * Get the translatable status count label map.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private function get_order_status_count_labels(): array {
		return array(
			/* translators: %s: number of orders currently in the captured CartBay status. */
			'wc-cartbay-captured'   => _n_noop( 'CartBay: Captured <span class="count">(%s)</span>', 'CartBay: Captured <span class="count">(%s)</span>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			/* translators: %s: number of orders currently in the abandoned CartBay status. */
			'wc-cartbay-abandoned'  => _n_noop( 'CartBay: Abandoned <span class="count">(%s)</span>', 'CartBay: Abandoned <span class="count">(%s)</span>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			/* translators: %s: number of orders currently in the recovered CartBay status. */
			'wc-cartbay-recovered'  => _n_noop( 'CartBay: Recovered <span class="count">(%s)</span>', 'CartBay: Recovered <span class="count">(%s)</span>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			/* translators: %s: number of orders currently in the expired CartBay status. */
			'wc-cartbay-expired'    => _n_noop( 'CartBay: Expired <span class="count">(%s)</span>', 'CartBay: Expired <span class="count">(%s)</span>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
			/* translators: %s: number of orders currently in the suppressed CartBay status. */
			'wc-cartbay-suppressed' => _n_noop( 'CartBay: Suppressed <span class="count">(%s)</span>', 'CartBay: Suppressed <span class="count">(%s)</span>', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
		);
	}

	/**
	 * Register CartBay REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$route = new CaptureRoute(
			$this->capture_service()
		);
		$route->register();

		// Analytics route.
		$analytics_route = new AnalyticsRoute(
			$this->container->make( AnalyticsService::class )
		);
		$analytics_route->register();

		// Test mode route.
		$test_route = new TestRoute( $this->notification_service() );
		$test_route->register();

		// Test email route.
		$test_email_route = new TestEmailRoute();
		$test_email_route->register();
	}

	/**
	 * Enqueue capture script on classic checkout pages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_capture_assets(): void {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$settings = get_option( 'cartbay_settings', array() );

		if ( ! Settings::is_capture_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'cartbay-capture',
			CARTBAY_URL . 'assets/js/cartbay-capture.js',
			array(),
			CARTBAY_VERSION,
			true
		);

		wp_localize_script(
			'cartbay-capture',
			'cartbayCapture',
			array(
				'endpoint'         => rest_url( 'cartbay/v1/capture' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'cart'             => $this->get_localized_cart_data(),
				'restored_session' => $this->has_restored_session_identity(),
				'settings'         => array(
					'consent_text'          => isset( $settings['consent_text'] ) ? esc_html( $settings['consent_text'] ) : '',
					'consent_default_state' => isset( $settings['consent_default_state'] ) ? sanitize_key( $settings['consent_default_state'] ) : 'checked',
				),
			)
		);
	}

	/**
	 * Enqueue capture script on block checkout pages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_block_capture_assets(): void {
		$settings = get_option( 'cartbay_settings', array() );

		if ( ! Settings::is_capture_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'cartbay-block',
			CARTBAY_URL . 'assets/js/cartbay-block.js',
			array( 'wp-api-fetch' ),
			CARTBAY_VERSION,
			true
		);

		wp_localize_script(
			'cartbay-block',
			'cartbayCapture',
			array(
				'endpoint'         => rest_url( 'cartbay/v1/capture' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'cart'             => $this->get_localized_cart_data(),
				'restored_session' => $this->has_restored_session_identity(),
				'settings'         => array(
					'consent_text'          => isset( $settings['consent_text'] ) ? esc_html( $settings['consent_text'] ) : '',
					'consent_default_state' => isset( $settings['consent_default_state'] ) ? sanitize_key( $settings['consent_default_state'] ) : 'checked',
				),
			)
		);
	}

	/**
	 * Build restore-safe cart data for frontend capture payloads.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Cart data payload.
	 */
	private function get_localized_cart_data(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array(
				'hash'     => '',
				'total'    => 0,
				'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
				'items'    => array(),
			);
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id   = absint( $cart_item['product_id'] ?? 0 );
			$variation_id = absint( $cart_item['variation_id'] ?? 0 );

			if ( $product_id <= 0 ) {
				continue;
			}

			$items[] = array(
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'quantity'       => max( 1, absint( $cart_item['quantity'] ?? 1 ) ),
				'variation'      => isset( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ? $cart_item['variation'] : array(),
				'cart_item_data' => array(),
			);
		}

		return array(
			'hash'            => WC()->cart->get_cart_hash(),
			'total'           => (float) WC()->cart->get_total( 'edit' ),
			'currency'        => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'items'           => $items,
			'cart_item_count' => absint( WC()->cart->get_cart_contents_count() ),
		);
	}

	/**
	 * Determine whether checkout is tied to an existing restored CartBay session.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether restore identity exists in the WooCommerce session.
	 */
	private function has_restored_session_identity(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return false;
		}

		return absint( WC()->session->get( 'cartbay_restored_session_id' ) ) > 0;
	}

	/**
	 * Resolve the checkout fields service.
	 *
	 * @since 1.0.0
	 *
	 * @return CheckoutFields
	 */
	private function checkout_fields(): CheckoutFields {
		return $this->container->make( CheckoutFields::class );
	}

	/**
	 * Resolve the session repository service.
	 *
	 * @since 1.0.0
	 *
	 * @return SessionRepository
	 */
	private function session_repository(): SessionRepository {
		return $this->container->make( SessionRepository::class );
	}

	/**
	 * Resolve the abandonment scheduler service.
	 *
	 * @since 1.0.0
	 *
	 * @return AbandonmentScheduler
	 */
	private function abandonment_scheduler(): AbandonmentScheduler {
		return $this->container->make( AbandonmentScheduler::class );
	}

	/**
	 * Resolve the email sequence service.
	 *
	 * @since 1.0.0
	 *
	 * @return EmailSequenceService
	 */
	private function email_sequence_service(): EmailSequenceService {
		return $this->container->make( EmailSequenceService::class );
	}

	/**
	 * Resolve the analytics service.
	 *
	 * @since 1.0.0
	 *
	 * @return AnalyticsService
	 */
	private function analytics_service(): AnalyticsService {
		return $this->container->make( AnalyticsService::class );
	}

	/**
	 * Resolve the notification tracking service.
	 *
	 * @since 1.0.0
	 *
	 * @return NotificationService
	 */
	private function notification_service(): NotificationService {
		return $this->container->make( NotificationService::class );
	}

	/**
	 * Resolve the capture service.
	 *
	 * @since 1.0.0
	 *
	 * @return CaptureService
	 */
	private function capture_service(): CaptureService {
		return $this->container->make( CaptureService::class );
	}

	/**
	 * Resolve the settings page coordinator.
	 *
	 * @since 1.0.0
	 *
	 * @return SettingsPage
	 */
	private function settings_page(): SettingsPage {
		return $this->container->make( SettingsPage::class );
	}

	/**
	 * Record a failed WordPress mail event for a CartBay notification.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Error $error Mail error object.
	 *
	 * @return void
	 */
	public function handle_wp_mail_failed( \WP_Error $error ): void {
		$mail_data        = $error->get_error_data();
		$notification_id  = $this->extract_cartbay_notification_id( $mail_data['headers'] ?? array() );
		$notification_ctx = '' !== $notification_id ? $this->notification_service()->get_context( $notification_id ) : null;

		if ( ! is_array( $notification_ctx ) ) {
			return;
		}

		$this->notification_service()->mark_failed(
			absint( $notification_ctx['session_id'] ?? 0 ),
			$notification_id,
			$error->get_error_message()
		);
	}

	/**
	 * Record a successful WordPress mail event for a CartBay notification.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $mail_data WordPress mail data.
	 *
	 * @return void
	 */
	public function handle_wp_mail_succeeded( array $mail_data ): void {
		$notification_id  = $this->extract_cartbay_notification_id( $mail_data['headers'] ?? array() );
		$notification_ctx = '' !== $notification_id ? $this->notification_service()->get_context( $notification_id ) : null;

		if ( ! is_array( $notification_ctx ) ) {
			return;
		}

		$this->notification_service()->mark_sent( absint( $notification_ctx['session_id'] ?? 0 ), $notification_id );
	}

	/**
	 * Extract the CartBay notification header value from mail headers.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $headers Mail headers.
	 *
	 * @return string Notification identifier when present.
	 */
	private function extract_cartbay_notification_id( mixed $headers ): string {
		$header_lines = array();

		if ( is_string( $headers ) ) {
			$header_lines = preg_split( "/\r\n|\n|\r/", $headers );
			$header_lines = is_array( $header_lines ) ? $header_lines : array();
		} elseif ( is_array( $headers ) ) {
			$header_lines = array_map( 'strval', $headers );
		}

		foreach ( $header_lines as $header_line ) {
			if ( ! is_string( $header_line ) || ! str_starts_with( strtolower( $header_line ), 'x-cartbay-notification:' ) ) {
				continue;
			}

			$parts = explode( ':', $header_line, 2 );

			return isset( $parts[1] ) ? sanitize_key( trim( $parts[1] ) ) : '';
		}

		return '';
	}

	/**
	 * Register CartBay email classes with WooCommerce.
	 *
	 * @since 1.0.0
	 *
	 * @param array $emails Existing WC email classes.
	 *
	 * @return array
	 */
	public function register_email_classes( array $emails ): array {
		$emails['CartBay_Email_Recovery_1'] = new CartBay_Email_Recovery_1();
		$emails['CartBay_Email_Recovery_2'] = new CartBay_Email_Recovery_2();
		$emails['CartBay_Email_Recovery_3'] = new CartBay_Email_Recovery_3();
		return $emails;
	}

	/**
	 * Add CartBay sample placeholders to WooCommerce native email preview.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $placeholders Existing preview placeholders.
	 *
	 * @return array<string, string> Preview placeholders.
	 */
	public function add_email_preview_placeholders( array $placeholders ): array {
		return array_merge(
			$placeholders,
			array(
				'{store_name}'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'{restore_url}'     => wc_get_checkout_url(),
				'{coupon_code}'     => __( 'SAVE10', 'cartbay-abandoned-cart-recovery-for-woocommerce' ),
				'{coupon_expiry}'   => date_i18n( get_option( 'date_format' ), time() + DAY_IN_SECONDS * 3 ),
				'{unsubscribe_url}' => home_url( '/?cartbay_unsubscribe=preview' ),
				'{customer_email}'  => 'customer@example.com',
			)
		);
	}

	/**
	 * Prepare CartBay recovery emails for WooCommerce native preview rendering.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $email WooCommerce email instance.
	 *
	 * @return mixed Prepared email instance.
	 */
	public function prepare_cartbay_email_preview( mixed $email ): mixed {
		if ( ! $email instanceof AbstractCartBayRecoveryEmail ) {
			return $email;
		}

		$email->recipient = get_option( 'admin_email' );

		return $email;
	}

	/**
	 * Register CartBay custom email fields with the WooCommerce live preview allow-list.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $setting_ids Email content setting IDs.
	 * @param string             $email_id    WooCommerce email ID.
	 *
	 * @return array<int, string> Email content setting IDs.
	 */
	public function add_email_preview_content_setting_ids( array $setting_ids, string $email_id ): array {
		if ( ! in_array( $email_id, array( 'cartbay_recovery_1', 'cartbay_recovery_2', 'cartbay_recovery_3' ), true ) ) {
			return $setting_ids;
		}

		$setting_ids[] = "woocommerce_{$email_id}_preheader";
		$setting_ids[] = "woocommerce_{$email_id}_body_content";
		$setting_ids[] = "woocommerce_{$email_id}_cta_label";

		return array_values( array_unique( $setting_ids ) );
	}

	/**
	 * Handle restore link requests from URL token.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_restore_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['cartbay_restore'] ) ? sanitize_text_field( wp_unslash( $_GET['cartbay_restore'] ) ) : '';
		if ( ! empty( $token ) ) {
			$this->container->make( RestoreService::class )->handle( $token );
		}
	}

	/**
	 * Handle unsubscribe link requests from URL token.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_unsubscribe_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['cartbay_unsubscribe'] ) ? sanitize_text_field( wp_unslash( $_GET['cartbay_unsubscribe'] ) ) : '';
		if ( empty( $token ) ) {
			return;
		}

		$token_hash = TokenHelper::hash( $token );
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WC CRUD lookup by CartBay-owned unsubscribe token hash.
		$sessions = wc_get_orders(
			array(
				'meta_key'   => '_cartbay_unsub_token_hash',
				'meta_value' => $token_hash,
				'limit'      => 1,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

		if ( empty( $sessions ) ) {
			wp_safe_redirect( home_url( '/?cartbay_unsub=invalid' ) );
			exit;
		}

		$session = $sessions[0];
		$email   = $session->get_billing_email();

		// Create suppression post.
		$hash = TokenHelper::hash_email( $email );
		if ( ! get_page_by_path( $hash, OBJECT, 'cartbay_suppressed' ) ) {
			wp_insert_post(
				array(
					'post_type'   => 'cartbay_suppressed',
					'post_status' => 'publish',
					'post_name'   => $hash,
					'post_title'  => $hash,
				)
			);
		}

		// Update session status to suppressed, cancel pending emails.
		$session->set_status( 'wc-cartbay-suppressed' );
		$session->save();

		// Cancel pending email jobs for this session.
		$this->cancel_pending_email_jobs( $session->get_id() );
		$this->notification_service()->cancel_pending_for_session( $session->get_id(), 'unsubscribed' );

		Logger::info(
			'Shopper unsubscribed via email link.',
			array( 'session_id' => $session->get_id() ),
			'unsubscribe'
		);

		$this->session_repository()->add_event( $session->get_id(), 'unsubscribed' );

		wp_safe_redirect( home_url( '/?cartbay_unsub=success' ) );
		exit;
	}

	/**
	 * Display frontend notices for CartBay actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function display_frontend_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$unsub = isset( $_GET['cartbay_unsub'] ) ? sanitize_text_field( wp_unslash( $_GET['cartbay_unsub'] ) ) : '';
		if ( 'success' === $unsub ) {
			wc_add_notice( __( 'You have been unsubscribed from cart recovery emails.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'success' );
			return;
		}
		if ( 'invalid' === $unsub ) {
			wc_add_notice( __( 'Invalid unsubscribe link.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'error' );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$restore_error = isset( $_GET['cartbay_restore_error'] ) ? sanitize_text_field( wp_unslash( $_GET['cartbay_restore_error'] ) ) : '';
		if ( 'expired' === $restore_error ) {
			wc_add_notice( __( 'This cart recovery link has expired. Please add items to your cart again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'error' );
			return;
		}
		if ( 'invalid' === $restore_error ) {
			wc_add_notice( __( 'Invalid cart recovery link.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'error' );
			return;
		}
		if ( 'rate_limited' === $restore_error ) {
			wc_add_notice( __( 'Too many cart recovery attempts. Please wait a moment and try again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'error' );
			return;
		}
		if ( 'empty' === $restore_error ) {
			wc_add_notice( __( 'We could not restore the items from this recovery link. Please add them to your cart again.', 'cartbay-abandoned-cart-recovery-for-woocommerce' ), 'error' );
		}
	}

	/**
	 * Pre-fill the billing email on checkout when the customer arrives via a restore link.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value Current field value.
	 * @param string $input Field name.
	 *
	 * @return mixed Pre-filled value when applicable.
	 */
	public function prefill_restored_email( mixed $value, string $input ): mixed {
		if ( 'billing_email' !== $input || ! empty( $value ) ) {
			return $value;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return $value;
		}

		$restored_email = WC()->session->get( 'cartbay_restored_email' );

		return ! empty( $restored_email ) ? sanitize_email( (string) $restored_email ) : $value;
	}

	/**
	 * Cancel all pending recovery email actions for a session.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id CartBay session order ID.
	 *
	 * @return void
	 */
	private function cancel_pending_email_jobs( int $session_id ): void {
		global $wpdb;

		$pattern = '%' . $wpdb->esc_like( '[' . $session_id . ',' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}actionscheduler_actions SET status = %s WHERE hook = %s AND status = %s AND args LIKE %s",
				'canceled',
				'cartbay_send_recovery_email',
				'pending',
				$pattern
			)
		);
	}

	/**
	 * Redirect to the setup wizard on first activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_redirect_to_wizard(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Only redirect from CartBay entry points, not every admin page.
		if ( ! $this->is_cartbay_wizard_entry_request() ) {
			return;
		}

		$wizard_complete = get_option( 'cartbay_wizard_complete', false );
		if ( $wizard_complete ) {
			return;
		}

		// Only redirect once per session.
		if ( get_transient( 'cartbay_wizard_redirect' ) ) {
			return;
		}

		set_transient( 'cartbay_wizard_redirect', true, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=cartbay-wizard' ) );
		exit;
	}

	/**
	 * Determine whether the current admin request should enter the setup wizard.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the request is a CartBay admin entry point.
	 */
	private function is_cartbay_wizard_entry_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'cartbay' === $page ) {
			return true;
		}

		if ( 'wc-settings' !== $page ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return 'cartbay' === $tab;
	}
}
