<?php
/**
 * Generate CartBay Demo Data
 *
 * Execute with: wp eval-file wp-content/plugins/cartbay/scripts/generate-demo-data.php
 */

use WPAnchorBay\CartBay\Data\SessionRepository;
use WPAnchorBay\CartBay\Analytics\AnalyticsService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "Cleaning up existing demo data...\n";

// Ensure WooCommerce is active.
if ( ! function_exists( 'wc_get_products' ) ) {
    echo "WooCommerce is not active.\n";
    return;
}

$repo = new SessionRepository();

// Wipe existing CartBay sessions
$existing_sessions = $repo->get_all_sessions();
foreach ( $existing_sessions as $session ) {
    $session->delete( true );
}
echo "Cleaned up " . count($existing_sessions) . " existing sessions.\n";

// Get products
$products = wc_get_products( array(
    'status' => 'publish',
    'limit'  => 20,
) );

if ( empty( $products ) ) {
    echo "No products found. Creating demo products...\n";
    for ( $i = 1; $i <= 5; $i++ ) {
        $product = new WC_Product_Simple();
        $product->set_name( "Premium Product $i" );
        $product->set_regular_price( rand( 150, 800 ) . '.00' );
        $product->set_status( 'publish' );
        $product->save();
        $products[] = $product;
    }
} else {
    // Increase prices of existing demo products to boost values
    foreach ( $products as $product ) {
        if ( strpos( $product->get_name(), 'Demo Product' ) !== false || strpos( $product->get_name(), 'Premium Product' ) !== false ) {
            $product->set_regular_price( rand( 150, 800 ) . '.00' );
            $product->save();
        }
    }
}

// Define new higher distribution
$total_sessions = 3000;
$abandoned_count = 2000;
$recovered_count = 800; // 40% recovery rate
$suppressed_count = 20;
$captured_count = $total_sessions - $abandoned_count - $suppressed_count; // 180

// Array of statuses to assign
$statuses = array_merge(
    array_fill( 0, $captured_count, 'wc-cartbay-captured' ),
    array_fill( 0, $abandoned_count - $recovered_count, 'wc-cartbay-abandoned' ),
    array_fill( 0, $recovered_count, 'wc-cartbay-recovered' ),
    array_fill( 0, $suppressed_count, 'wc-cartbay-suppressed' )
);
shuffle( $statuses );

$first_names = ['John', 'Jane', 'Michael', 'Emily', 'Chris', 'Sarah', 'David', 'Laura', 'Robert', 'Emma', 'William', 'Olivia'];
$last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
// Use strictly safe dummy domains
$domains = ['example.com', 'example.org', 'example.net', 'demo.local', 'shop-test.com'];

$now = time();
$ninety_days_ago = $now - (90 * DAY_IN_SECONDS);

echo "Generating new CartBay Demo Data...\n";

foreach ( $statuses as $i => $status ) {
    $random_weight = pow( mt_rand( 0, 1000 ) / 1000, 2 );
    $captured_at = $ninety_days_ago + (int) ( ( $now - $ninety_days_ago ) * $random_weight );
    
    $email = strtolower( $first_names[ array_rand( $first_names ) ] . '.' . $last_names[ array_rand( $last_names ) ] . rand(1000, 9999) . '@' . $domains[ array_rand( $domains ) ] );
    
    $cart_products = [];
    $num_products = rand( 1, 4 );
    $cart_total = 0;
    
    $cart_snapshot_items = [];
    
    for ( $p = 0; $p < $num_products; $p++ ) {
        $product = $products[ array_rand( $products ) ];
        $qty = rand( 1, 3 );
        // If product price is somehow 0, simulate a high price
        $price = (float) $product->get_price();
        if ( $price < 50 ) {
            $price = rand(100, 500);
        }
        $line_total = $price * $qty;
        $cart_total += $line_total;
        
        $cart_snapshot_items[] = [
            'product_id'   => $product->get_id(),
            'variation_id' => 0,
            'quantity'     => $qty,
            'product_name' => $product->get_name(),
            'line_total'   => $line_total,
            'line_subtotal'=> $line_total,
            'currency'     => get_woocommerce_currency(),
        ];
    }
    
    $cart_snapshot = [
        'items'           => $cart_snapshot_items,
        'grand_total'     => $cart_total,
        'cart_item_count' => $num_products,
        'currency'        => get_woocommerce_currency(),
    ];
    
    $meta = [
        'email'            => $email,
        'consent'          => true,
        'captured_at'      => $captured_at,
        'last_activity_at' => $captured_at + rand( 10, 300 ),
        'cart_total'       => $cart_total,
        'currency'         => get_woocommerce_currency(),
        'cart_snapshot'    => $cart_snapshot,
        'source'           => rand(0,1) ? 'classic' : 'block',
        'sequence_step'    => 0,
    ];
    
    $session_id = $repo->create( $email, $meta );
    
    if ( is_wp_error( $session_id ) ) {
        continue;
    }
    
    $order = wc_get_order( $session_id );
    if ( ! $order ) {
        continue;
    }
    
    $order->set_status( $status );
    
    $events = [];
    $notifications = [];
    
    $events[] = [ 'timestamp' => $captured_at, 'event' => 'captured', 'data' => ['source' => $meta['source']] ];
    
    if ( $status !== 'wc-cartbay-captured' && $status !== 'wc-cartbay-suppressed' ) {
        $abandoned_at = $captured_at + ( 15 * MINUTE_IN_SECONDS );
        $order->update_meta_data( '_cartbay_abandoned_at', $abandoned_at );
        $events[] = [ 'timestamp' => $abandoned_at, 'event' => 'abandoned', 'data' => [] ];
        
        $step_index = rand( 0, 2 );
        $order->update_meta_data( '_cartbay_sequence_step', $step_index );
        
        for ( $step = 0; $step <= $step_index; $step++ ) {
            $sent_at = $abandoned_at + ( pow( 24, $step ) * HOUR_IN_SECONDS );
            if ( $sent_at > $now ) {
                break;
            }
            $notifications[] = [
                'id' => wp_generate_uuid4(),
                'step_index' => $step,
                'status' => 'sent',
                'attempts' => 1,
            ];
            $events[] = [ 'timestamp' => $sent_at, 'event' => 'email_sent', 'data' => ['step_index' => $step] ];
        }
        
        if ( rand( 1, 20 ) === 1 ) {
            $notifications[] = [
                'id' => wp_generate_uuid4(),
                'step_index' => $step_index + 1,
                'status' => 'failed',
                'attempts' => 3,
            ];
        }
        
        if ( $status === 'wc-cartbay-recovered' || rand( 1, 3 ) === 1 ) {
            $clicked_at = $abandoned_at + rand( 1 * HOUR_IN_SECONDS, 48 * HOUR_IN_SECONDS );
            if ( $clicked_at < $now ) {
                $order->update_meta_data( '_cartbay_restore_clicked_at', $clicked_at );
                $events[] = [ 'timestamp' => $clicked_at, 'event' => 'restore_clicked', 'data' => [] ];
                
                if ( $status !== 'wc-cartbay-recovered' && rand( 1, 5 ) === 1 ) {
                    $events[] = [ 'timestamp' => $clicked_at + 10, 'event' => 'cart_restore_failed', 'data' => [] ];
                }
            }
        }
    }
    
    if ( $status === 'wc-cartbay-captured' && rand( 1, 5 ) === 1 ) {
        $events[] = [ 'timestamp' => $captured_at + 300, 'event' => 'completed_before_abandonment', 'data' => [] ];
    }
    
    if ( $status === 'wc-cartbay-recovered' ) {
        $recovered_at = $captured_at + rand( 2 * HOUR_IN_SECONDS, 72 * HOUR_IN_SECONDS );
        if ( $recovered_at > $now ) {
            $recovered_at = $now;
        }
        $order->update_meta_data( '_cartbay_recovered_at', $recovered_at );
        $events[] = [ 'timestamp' => $recovered_at, 'event' => 'recovered', 'data' => [] ];
        
        $recovered_order = wc_create_order();
        $recovered_order->set_billing_email( $email );
        $recovered_order->set_status( 'completed' );
        foreach ( $cart_snapshot_items as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( $product ) {
                $recovered_order->add_product( $product, $item['quantity'] );
            }
        }
        $recovered_order->calculate_totals();
        $recovered_order->set_date_created( gmdate( 'Y-m-d H:i:s', $recovered_at ) );
        $recovered_order->save();
        
        $order->update_meta_data( '_cartbay_recovered_order_id', $recovered_order->get_id() );
        $order->update_meta_data( '_cartbay_recovered_revenue', $recovered_order->get_total() );
        
        if ( ! empty( $notifications ) ) {
            $last_notification = end( $notifications );
            $order->update_meta_data( '_cartbay_recovered_notification_id', $last_notification['id'] );
        }
    }
    
    if ( ! empty( $notifications ) ) {
        $order->update_meta_data( '_cartbay_notifications', $notifications );
    }
    if ( ! empty( $events ) ) {
        $order->update_meta_data( '_cartbay_events', $events );
    }
    
    foreach ( $cart_snapshot_items as $item ) {
        $product = wc_get_product( $item['product_id'] );
        if ( $product ) {
            $order->add_product( $product, $item['quantity'] );
        }
    }
    $order->calculate_totals( false );
    $order->set_date_created( gmdate( 'Y-m-d H:i:s', $captured_at ) );
    
    $order->save();
    
    if ( $i > 0 && $i % 250 === 0 ) {
        echo "Generated $i sessions...\n";
    }
}

echo "Done! Generated $total_sessions sessions.\n";

AnalyticsService::invalidate_cache();
echo "Analytics cache invalidated.\n";
