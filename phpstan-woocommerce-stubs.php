<?php
/**
 * Minimal WooCommerce stubs for Phase 1 static analysis.
 *
 * @package CartBay
 */

if ( ! class_exists( 'WC_Order' ) ) {
	/**
	 * Minimal WC_Order stub.
	 */
	class WC_Order {
		/**
		 * Set the order status.
		 *
		 * @param string $status Status slug.
		 *
		 * @return void
		 */
		public function set_status( string $status ): void {}

		/**
		 * Set the billing email.
		 *
		 * @param string $email Email address.
		 *
		 * @return void
		 */
		public function set_billing_email( string $email ): void {}

		/**
		 * Get the billing email.
		 *
		 * @return string
		 */
		public function get_billing_email(): string {
			return '';
		}

		/**
		 * Set the created-via marker.
		 *
		 * @param string $source Source slug.
		 *
		 * @return void
		 */
		public function set_created_via( string $source ): void {}

		/**
		 * Return the order ID.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return 0;
		}

		/**
		 * Update order metadata.
		 *
		 * @param string $key   Meta key.
		 * @param mixed  $value Meta value.
		 *
		 * @return void
		 */
		public function update_meta_data( string $key, mixed $value ): void {}

		/**
		 * Save the order.
		 *
		 * @return int
		 */
		public function save(): int {
			return 0;
		}

		/**
		 * Return the normalized status slug.
		 *
		 * @return string
		 */
		public function get_status(): string {
			return '';
		}

		/**
		 * Add an order note.
		 *
		 * @param string $note Note content.
		 *
		 * @return int
		 */
		public function add_order_note( string $note ): int {
			return 0;
		}

		/**
		 * Read order metadata.
		 *
		 * @param string $key    Meta key.
		 * @param bool   $single Whether to return one value.
		 *
		 * @return mixed
		 */
		public function get_meta( string $key, bool $single = true ): mixed {
			return null;
		}

		/**
		 * Delete the order.
		 *
		 * @param bool $force_delete Whether to bypass trash.
		 *
		 * @return bool
		 */
		public function delete( bool $force_delete = false ): bool {
			return true;
		}

		/**
		 * Get order items.
		 *
		 * @return array
		 */
		public function get_items(): array {
			return array();
		}

		/**
		 * Get order total.
		 *
		 * @return float
		 */
		public function get_total(): float {
			return 0.0;
		}

		/**
		 * Get date created.
		 *
		 * @return WC_DateTime|null
		 */
		public function get_date_created(): ?WC_DateTime {
			return null;
		}

		/**
		 * Remove an order item.
		 *
		 * @param int $item_id Order item ID.
		 *
		 * @return void
		 */
		public function remove_item( int $item_id ): void {}

		/**
		 * Add a product line item.
		 *
		 * @param WC_Product $product Product instance.
		 * @param int        $qty     Quantity.
		 * @param array      $args    Line item args.
		 *
		 * @return int
		 */
		public function add_product( WC_Product $product, int $qty = 1, array $args = array() ): int {
			return 0;
		}

		/**
		 * Get an order item.
		 *
		 * @param int $item_id Item ID.
		 *
		 * @return WC_Order_Item_Product|null
		 */
		public function get_item( int $item_id ): ?WC_Order_Item_Product {
			return null;
		}

		/**
		 * Calculate order totals.
		 *
		 * @param bool $and_taxes Whether to calculate taxes.
		 *
		 * @return float
		 */
		public function calculate_totals( bool $and_taxes = true ): float {
			return 0.0;
		}

		/**
		 * Get applied coupon codes.
		 *
		 * @return array<int, string>
		 */
		public function get_coupon_codes(): array {
			return array();
		}
	}
}

if ( ! class_exists( 'WC_Order_Item_Product' ) ) {
	/**
	 * Minimal WC_Order_Item_Product stub.
	 */
	class WC_Order_Item_Product {
		/**
		 * Add meta data.
		 *
		 * @param string $key    Meta key.
		 * @param mixed  $value  Meta value.
		 * @param bool   $unique Whether value is unique.
		 *
		 * @return void
		 */
		public function add_meta_data( string $key, mixed $value, bool $unique = false ): void {}
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Minimal WC_Product stub.
	 */
	class WC_Product {
		/**
		 * Get product ID.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return 0;
		}

		/**
		 * Get product name.
		 *
		 * @return string
		 */
		public function get_name(): string {
			return '';
		}

		/**
		 * Get product SKU.
		 *
		 * @return string
		 */
		public function get_sku(): string {
			return '';
		}

		/**
		 * Get image ID.
		 *
		 * @return int
		 */
		public function get_image_id(): int {
			return 0;
		}

		/**
		 * Get permalink.
		 *
		 * @return string
		 */
		public function get_permalink(): string {
			return '';
		}

		/**
		 * Whether product is visible.
		 *
		 * @return bool
		 */
		public function is_visible(): bool {
			return true;
		}

		/**
		 * Whether product is purchasable.
		 *
		 * @return bool
		 */
		public function is_purchasable(): bool {
			return true;
		}

		/**
		 * Whether product is in stock.
		 *
		 * @return bool
		 */
		public function is_in_stock(): bool {
			return true;
		}

		/**
		 * Get the active product price.
		 *
		 * @param string $context View or edit context.
		 *
		 * @return string
		 */
		public function get_price( string $context = 'view' ): string {
			return '';
		}

		/**
		 * Whether the product manages stock at the product level.
		 *
		 * @return bool
		 */
		public function managing_stock(): bool {
			return false;
		}

		/**
		 * Get the managed stock quantity.
		 *
		 * @return int|null
		 */
		public function get_stock_quantity(): ?int {
			return null;
		}

		/**
		 * Whether backorders are allowed.
		 *
		 * @return bool
		 */
		public function backorders_allowed(): bool {
			return false;
		}
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * Fetch a WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return WC_Product|false
	 */
	function wc_get_product( int $product_id ): WC_Product|false {
		return new WC_Product();
	}
}

if ( ! function_exists( 'woocommerce_admin_fields' ) ) {
	/**
	 * Output admin fields for WooCommerce settings.
	 *
	 * @param array $fields Settings fields array.
	 *
	 * @return void
	 */
	function woocommerce_admin_fields( array $fields ): void {}
}

if ( ! function_exists( 'woocommerce_update_options' ) ) {
	/**
	 * Save WooCommerce settings from admin fields.
	 *
	 * @param array $fields Settings fields array.
	 *
	 * @return void
	 */
	function woocommerce_update_options( array $fields ): void {}
}

if ( ! class_exists( 'WC_Logger' ) ) {
	/**
	 * Minimal WC_Logger stub.
	 */
	class WC_Logger {
		/**
		 * Log a message.
		 *
		 * @param string $level   Log level.
		 * @param string $message Log message.
		 * @param array  $context Log context.
		 *
		 * @return void
		 */
		public function log( string $level, string $message, array $context = array() ): void {}
	}
}

if ( ! function_exists( 'wc_create_order' ) ) {
	/**
	 * Create a WooCommerce order.
	 *
	 * @return WC_Order|\WP_Error
	 */
	function wc_create_order(): WC_Order|\WP_Error {
		return new WC_Order();
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * Fetch a WooCommerce order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return WC_Order|false
	 */
	function wc_get_order( int $order_id ): WC_Order|false {
		return new WC_Order();
	}
}

if ( ! function_exists( 'wc_get_orders' ) ) {
	/**
	 * Fetch WooCommerce orders.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 *
	 * @return array<int, WC_Order>
	 */
	function wc_get_orders( array $args ): array {
		return array();
	}
}

if ( ! function_exists( 'wc_get_order_statuses' ) ) {
	/**
	 * Get registered WooCommerce order statuses.
	 *
	 * @return array<string, string>
	 */
	function wc_get_order_statuses(): array {
		return array();
	}
}

if ( ! function_exists( 'wc_get_logger' ) ) {
	/**
	 * Fetch the WooCommerce logger.
	 *
	 * @return WC_Logger
	 */
	function wc_get_logger(): WC_Logger {
		return new WC_Logger();
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	/**
	 * Check if on a checkout page.
	 *
	 * @return bool
	 */
	function is_checkout(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_order_received_page' ) ) {
	/**
	 * Check if on the order received page.
	 *
	 * @return bool
	 */
	function is_order_received_page(): bool {
		return false;
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	/**
	 * Get the store currency code.
	 *
	 * @return string
	 */
	function get_woocommerce_currency(): string {
		return 'USD';
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	/**
	 * Schedule a single Action Scheduler action.
	 *
	 * @param int    $timestamp Event timestamp.
	 * @param string $hook      Action hook.
	 * @param array  $args      Hook arguments.
	 * @param string $group     Action group.
	 *
	 * @return int
	 */
	function as_schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '' ): int {
		return 0;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	/**
	 * Unschedule all Action Scheduler actions by hook and args.
	 *
	 * @param string $hook Action hook.
	 * @param array  $args Hook arguments.
	 * @param string $group Action group.
	 *
	 * @return void
	 */
	function as_unschedule_all_actions( string $hook, array $args = array(), string $group = '' ): void {}
}

if ( ! class_exists( 'WC_Coupon' ) ) {
	/**
	 * Minimal WC_Coupon stub.
	 */
	class WC_Coupon {
		/**
		 * Constructor.
		 *
		 * @param string $code Coupon code.
		 */
		public function __construct( string $code = '' ) {}

		/**
		 * Get the coupon ID.
		 *
		 * @return int
		 */
		public function get_id(): int {
			return 0;
		}

		/**
		 * Set the coupon code.
		 *
		 * @param string $code Coupon code.
		 *
		 * @return void
		 */
		public function set_code( string $code ): void {}

		/**
		 * Set discount type.
		 *
		 * @param string $type Discount type.
		 *
		 * @return void
		 */
		public function set_discount_type( string $type ): void {}

		/**
		 * Set discount amount.
		 *
		 * @param float $amount Discount amount.
		 *
		 * @return void
		 */
		public function set_amount( float $amount ): void {}

		/**
		 * Set usage limit.
		 *
		 * @param int $limit Usage limit.
		 *
		 * @return void
		 */
		public function set_usage_limit( int $limit ): void {}

		/**
		 * Set per-user usage limit.
		 *
		 * @param int $limit Usage limit per user.
		 *
		 * @return void
		 */
		public function set_usage_limit_per_user( int $limit ): void {}

		/**
		 * Set individual use flag.
		 *
		 * @param bool $individual_use Whether coupon can be used with others.
		 *
		 * @return void
		 */
		public function set_individual_use( bool $individual_use ): void {}

		/**
		 * Set email restrictions.
		 *
		 * @param array<int, string> $emails Allowed email addresses.
		 *
		 * @return void
		 */
		public function set_email_restrictions( array $emails ): void {}

		/**
		 * Set expiry date.
		 *
		 * @param int $timestamp Expiry timestamp.
		 *
		 * @return void
		 */
		public function set_date_expires( int $timestamp ): void {}

		/**
		 * Update meta data.
		 *
		 * @param string $key   Meta key.
		 * @param mixed  $value Meta value.
		 *
		 * @return void
		 */
		public function update_meta_data( string $key, mixed $value ): void {}

		/**
		 * Save the coupon.
		 *
		 * @return int
		 */
		public function save(): int {
			return 0;
		}

		/**
		 * Read coupon metadata.
		 *
		 * @param string $key    Meta key.
		 * @param bool   $single Whether to return one value.
		 *
		 * @return mixed
		 */
		public function get_meta( string $key, bool $single = true ): mixed {
			return null;
		}

		/**
		 * Get expiry date.
		 *
		 * @return WC_DateTime|null
		 */
		public function get_date_expires(): ?WC_DateTime {
			return null;
		}

		/**
		 * Get usage count.
		 *
		 * @return int
		 */
		public function get_usage_count(): int {
			return 0;
		}

		/**
		 * Get usage limit.
		 *
		 * @return int
		 */
		public function get_usage_limit(): int {
			return 0;
		}

		/**
		 * Get email restrictions.
		 *
		 * @return array<int, string>
		 */
		public function get_email_restrictions(): array {
			return array();
		}

		/**
		 * Get minimum order amount.
		 *
		 * @return string
		 */
		public function get_minimum_amount(): string {
			return '';
		}

		/**
		 * Get product IDs the coupon applies to.
		 *
		 * @return array<int, int>
		 */
		public function get_product_ids(): array {
			return array();
		}

		/**
		 * Get excluded product IDs.
		 *
		 * @return array<int, int>
		 */
		public function get_excluded_product_ids(): array {
			return array();
		}

		/**
		 * Get product category IDs the coupon applies to.
		 *
		 * @return array<int, int>
		 */
		public function get_product_categories(): array {
			return array();
		}

		/**
		 * Get excluded product category IDs.
		 *
		 * @return array<int, int>
		 */
		public function get_excluded_product_categories(): array {
			return array();
		}

		/**
		 * Delete the coupon.
		 *
		 * @param bool $force_delete Whether to bypass trash.
		 *
		 * @return bool
		 */
		public function delete( bool $force_delete = false ): bool {
			return true;
		}
	}
}

if ( ! class_exists( 'WC_DateTime' ) ) {
	/**
	 * Minimal WC_DateTime stub.
	 */
	class WC_DateTime {
		/**
		 * Get timestamp.
		 *
		 * @return int
		 */
		public function getTimestamp(): int {
			return 0;
		}

		/**
		 * Format date using date_i18n.
		 *
		 * @param string $format Date format.
		 *
		 * @return string
		 */
		public function date_i18n( string $format = 'Y-m-d' ): string {
			return '';
		}
	}
}

if ( ! function_exists( 'wc_get_checkout_url' ) ) {
	/**
	 * Get the checkout URL.
	 *
	 * @return string
	 */
	function wc_get_checkout_url(): string {
		return '';
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	/**
	 * Format a price.
	 *
	 * @param float $price Price.
	 *
	 * @return string
	 */
	function wc_price( float $price ): string {
		return '';
	}
}

if ( ! class_exists( 'WC_Email' ) ) {
	/**
	 * Minimal WC_Email stub.
	 */
	class WC_Email {
		/**
		 * Email ID.
		 *
		 * @var string
		 */
		public string $id = '';

		/**
		 * Email title.
		 *
		 * @var string
		 */
		public string $title = '';

		/**
		 * Email description.
		 *
		 * @var string
		 */
		public string $description = '';

		/**
		 * Email heading.
		 *
		 * @var string
		 */
		public string $heading = '';

		/**
		 * Email subject.
		 *
		 * @var string
		 */
		public string $subject = '';

		/**
		 * Recipient email.
		 *
		 * @var string
		 */
		public string $recipient = '';

		/**
		 * Related email object.
		 *
		 * @var mixed
		 */
		public mixed $object = null;

		/**
		 * Whether this email is sent to a customer.
		 *
		 * @var bool
		 */
		public bool $customer_email = false;

		/**
		 * Settings form fields.
		 *
		 * @var array
		 */
		public array $form_fields = array();

		/**
		 * Template HTML path.
		 *
		 * @var string
		 */
		public string $template_html = '';

		/**
		 * Template plain path.
		 *
		 * @var string
		 */
		public string $template_plain = '';

		/**
		 * Template base directory.
		 *
		 * @var string
		 */
		public string $template_base = '';

		/**
		 * Placeholders.
		 *
		 * @var array
		 */
		public array $placeholders = array();

		/**
		 * Constructor.
		 */
		public function __construct() {}

		/**
		 * Get blog name.
		 *
		 * @return string
		 */
		public function get_blogname(): string {
			return '';
		}

		/**
		 * Get option value.
		 *
		 * @param string $key     Option key.
		 * @param mixed  $default Default value.
		 *
		 * @return mixed
		 */
		public function get_option( string $key, mixed $default = false ): mixed {
			return $default;
		}

		/**
		 * Initialize settings form fields.
		 *
		 * @return void
		 */
		public function init_form_fields(): void {}

		/**
		 * Setup locale.
		 *
		 * @return void
		 */
		public function setup_locale(): void {}

		/**
		 * Restore locale.
		 *
		 * @return void
		 */
		public function restore_locale(): void {}

		/**
		 * Get recipient.
		 *
		 * @return string
		 */
		public function get_recipient(): string {
			return '';
		}

		/**
		 * Get subject.
		 *
		 * @return string
		 */
		public function get_subject(): string {
			return '';
		}

		/**
		 * Get heading.
		 *
		 * @return string
		 */
		public function get_heading(): string {
			return '';
		}

		/**
		 * Get headers.
		 *
		 * @return string
		 */
		public function get_headers(): string {
			return '';
		}

		/**
		 * Get attachments.
		 *
		 * @return string
		 */
		public function get_attachments(): string {
			return '';
		}

		/**
		 * Send the email.
		 *
		 * @param string $to          Recipient.
		 * @param string $subject     Subject.
		 * @param string $message     Message.
		 * @param string $headers     Headers.
		 * @param string $attachments Attachments.
		 *
		 * @return bool
		 */
		public function send( string $to, string $subject, string $message, string $headers, string $attachments ): bool {
			return true;
		}

		/**
		 * Apply inline styles to dynamic content.
		 *
		 * @param string|null $content Content that will receive inline styles.
		 *
		 * @return string
		 */
		public function style_inline( ?string $content ): string {
			return (string) $content;
		}

		/**
		 * Format a string with WooCommerce placeholders.
		 *
		 * @param string $string String to format.
		 *
		 * @return string
		 */
		public function format_string( string $string ): string {
			return $string;
		}

		/**
		 * Get option or preview transient.
		 *
		 * @param string $key         Option key.
		 * @param mixed  $empty_value Empty fallback value.
		 *
		 * @return mixed
		 */
		protected function get_option_or_transient( string $key, mixed $empty_value = null ): mixed {
			return $empty_value;
		}

		/**
		 * Get additional email content.
		 *
		 * @return string
		 */
		public function get_additional_content(): string {
			return '';
		}

		/**
		 * Get content HTML.
		 *
		 * @return string
		 */
		public function get_content_html(): string {
			return '';
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain(): string {
			return '';
		}
	}
}

if ( ! class_exists( 'WC_Product_Subscription' ) ) {
	/**
	 * Minimal WC_Product_Subscription stub.
	 */
	class WC_Product_Subscription {}
}

if ( ! class_exists( 'WC_Product_Subscription_Variation' ) ) {
	/**
	 * Minimal WC_Product_Subscription_Variation stub.
	 */
	class WC_Product_Subscription_Variation {}
}

if ( ! function_exists( 'wc_add_notice' ) ) {
	/**
	 * Add a WooCommerce notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type (success, error, notice).
	 *
	 * @return void
	 */
	function wc_add_notice( string $message, string $type = 'success' ): void {}
}

if ( ! function_exists( 'wc_get_template' ) ) {
	/**
	 * Load a WooCommerce template.
	 *
	 * @param string $template_name Template name.
	 * @param array  $args          Template arguments.
	 * @param string $template_path Template path.
	 * @param string $default_path  Default path.
	 *
	 * @return void
	 */
	function wc_get_template( string $template_name, array $args = array(), string $template_path = '', string $default_path = '' ): void {}
}

if ( ! function_exists( 'WC' ) ) {
	/**
	 * Returns the main WooCommerce instance.
	 *
	 * @return WooCommerce
	 */
	function WC(): WooCommerce {
		return new WooCommerce();
	}
}

if ( ! class_exists( 'WooCommerce' ) ) {
	/**
	 * Minimal WooCommerce stub.
	 */
	class WooCommerce {
		/**
		 * Cart instance.
		 *
		 * @var WC_Cart|null
		 */
		public ?WC_Cart $cart = null;

		/**
		 * Session instance.
		 *
		 * @var WC_Session|null
		 */
		public ?WC_Session $session = null;

		/**
		 * Customer instance.
		 *
		 * @var WC_Customer|null
		 */
		public ?WC_Customer $customer = null;

		/**
		 * Get the mailer instance.
		 *
		 * @return WC_Mailer
		 */
		public function mailer(): WC_Mailer {
			return new WC_Mailer();
		}
	}
}

if ( ! class_exists( 'WC_Customer' ) ) {
	/**
	 * Minimal WC_Customer stub.
	 */
	class WC_Customer {
		/**
		 * Set billing email.
		 *
		 * @param string $email Email address.
		 *
		 * @return void
		 */
		public function set_billing_email( string $email ): void {}

		/**
		 * Get billing email.
		 *
		 * @return string
		 */
		public function get_billing_email(): string {
			return '';
		}
	}
}

if ( ! class_exists( 'WC_Cart' ) ) {
	/**
	 * Minimal WC_Cart stub.
	 */
	class WC_Cart {
		/**
		 * Empty the cart.
		 *
		 * @return void
		 */
		public function empty_cart(): void {}

		/**
		 * Whether the cart has no items.
		 *
		 * @return bool
		 */
		public function is_empty(): bool {
			return true;
		}

		/**
		 * Add to cart.
		 *
		 * @param int   $product_id   Product ID.
		 * @param int   $quantity     Quantity.
		 * @param int   $variation_id Variation ID.
		 * @param array $variation   Variation attributes.
		 *
		 * @return string|false
		 */
		public function add_to_cart( int $product_id, int $quantity = 1, int $variation_id = 0, array $variation = array(), array $cart_item_data = array() ): string|false {
			return '';
		}

		/**
		 * Apply a coupon.
		 *
		 * @param string $coupon_code Coupon code.
		 *
		 * @return bool
		 */
		public function add_discount( string $coupon_code ): bool {
			return true;
		}

		/**
		 * Get cart contents.
		 *
		 * @return array
		 */
		public function get_cart(): array {
			return array();
		}

		/**
		 * Get cart hash.
		 *
		 * @return string
		 */
		public function get_cart_hash(): string {
			return '';
		}

		/**
		 * Get cart total.
		 *
		 * @return string
		 */
		public function get_cart_total(): string {
			return '0';
		}

		/**
		 * Get cart total.
		 *
		 * @param string $context View or edit context.
		 *
		 * @return string|float
		 */
		public function get_total( string $context = 'view' ): string|float {
			return '0';
		}

		/**
		 * Get applied coupon codes.
		 *
		 * @return array<int, string>
		 */
		public function get_applied_coupons(): array {
			return array();
		}

		/**
		 * Get subtotal.
		 *
		 * @return float
		 */
		public function get_subtotal(): float {
			return 0.0;
		}

		/**
		 * Get discount total.
		 *
		 * @return float
		 */
		public function get_discount_total(): float {
			return 0.0;
		}

		/**
		 * Get tax total.
		 *
		 * @return float
		 */
		public function get_total_tax(): float {
			return 0.0;
		}

		/**
		 * Get shipping total.
		 *
		 * @return float
		 */
		public function get_shipping_total(): float {
			return 0.0;
		}

		/**
		 * Get cart contents count.
		 *
		 * @return int
		 */
		public function get_cart_contents_count(): int {
			return 0;
		}
	}
}

if ( ! class_exists( 'WC_Session' ) ) {
	/**
	 * Minimal WC_Session stub.
	 */
	class WC_Session {
		/**
		 * Get a session value.
		 *
		 * @param string $key Session key.
		 *
		 * @return mixed
		 */
		public function get( string $key ): mixed {
			return null;
		}

		/**
		 * Set a session value.
		 *
		 * @param string $key   Session key.
		 * @param mixed  $value Session value.
		 *
		 * @return void
		 */
		public function set( string $key, mixed $value ): void {}

		/**
		 * Unset a session value.
		 *
		 * @param string $key Session key.
		 *
		 * @return void
		 */
		public function __unset( string $key ): void {}
	}
}

if ( ! class_exists( 'WC_Mailer' ) ) {
	/**
	 * Minimal WC_Mailer stub.
	 */
	class WC_Mailer {
		/**
		 * Get registered email instances.
		 *
		 * @return array
		 */
		public function get_emails(): array {
			return array();
		}
	}
}
