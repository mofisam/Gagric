<?php
/**
 * API endpoint for retrieving cart totals
 * Used by AJAX calls from view-cart.php
 */

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Please login to view cart totals'
    ]);
    exit;
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

try {
    $db = new Database();
    $user_id = getCurrentUserId();
    
    // Get cart items with prices
    $cart_items = $db->fetchAll("
        SELECT 
            c.quantity, 
            p.price_per_unit,
            p.stock_quantity,
            p.id as product_id,
            p.name as product_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        JOIN seller_profiles sp ON p.seller_id = sp.user_id
        WHERE c.user_id = ? 
          AND p.status = 'approved' 
          AND sp.is_approved = TRUE
    ", [$user_id]);
    
    // Calculate totals
    $subtotal = 0;
    $item_count = 0;
    $total_items = 0;
    $stock_warnings = [];
    
    foreach ($cart_items as $item) {
        $item_total = $item['price_per_unit'] * $item['quantity'];
        $subtotal += $item_total;
        $total_items += $item['quantity'];
        $item_count++;
        
        // Check stock availability
        if ($item['quantity'] > $item['stock_quantity']) {
            $stock_warnings[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'requested' => $item['quantity'],
                'available' => $item['stock_quantity']
            ];
        }
    }
    
    // Calculate shipping (you can make this dynamic based on location/weight)
    $shipping = $subtotal > 0 ? 500 : 0; // Free shipping if cart is empty
    
    // Calculate tax (optional)
    $tax_rate = 0; // 0% tax for agricultural products
    $tax = $subtotal * $tax_rate;
    
    $total = $subtotal + $shipping + $tax;
    
    // Get available shipping methods
    $shipping_methods = [
        [
            'id' => 'standard',
            'name' => 'Standard Delivery',
            'price' => $shipping,
            'estimated_days' => '3-5 business days'
        ],
        [
            'id' => 'express',
            'name' => 'Express Delivery',
            'price' => $shipping + 1000,
            'estimated_days' => '1-2 business days'
        ]
    ];
    
    // Check if any coupons are applied (if you have coupon system)
    $discount = 0;
    $coupon_code = $_SESSION['applied_coupon'] ?? null;
    
    if ($coupon_code) {
        // Validate coupon (implement your coupon logic here)
        $coupon = $db->fetchOne("
            SELECT * FROM coupons 
            WHERE code = ? AND status = 'active' 
              AND valid_from <= NOW() AND valid_until >= NOW()
        ", [$coupon_code]);
        
        if ($coupon) {
            if ($coupon['discount_type'] === 'percentage') {
                $discount = $subtotal * ($coupon['discount_value'] / 100);
            } else {
                $discount = min($coupon['discount_value'], $subtotal);
            }
        }
    }
    
    $final_total = $total - $discount;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'subtotal' => round($subtotal, 2),
            'subtotal_formatted' => formatCurrency($subtotal),
            'shipping' => round($shipping, 2),
            'shipping_formatted' => formatCurrency($shipping),
            'tax' => round($tax, 2),
            'tax_formatted' => formatCurrency($tax),
            'discount' => round($discount, 2),
            'discount_formatted' => formatCurrency($discount),
            'total' => round($final_total, 2),
            'total_formatted' => formatCurrency($final_total),
            'item_count' => $item_count,
            'total_items' => $total_items,
            'stock_warnings' => $stock_warnings,
            'has_stock_warnings' => !empty($stock_warnings),
            'shipping_methods' => $shipping_methods,
            'coupon_applied' => $coupon_code,
            'can_checkout' => empty($stock_warnings) && $subtotal > 0
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve cart totals',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
    error_log("Cart totals error: " . $e->getMessage());
}