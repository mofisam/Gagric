<?php
/**
 * API endpoint for updating cart item quantity
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
        'error' => 'Please login to update your cart'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid CSRF token'
    ]);
    exit;
}

// Validate required fields
if (!isset($input['product_id']) || !isset($input['quantity'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields: product_id and quantity'
    ]);
    exit;
}

$product_id = (int)$input['product_id'];
$quantity = (int)$input['quantity'];
$user_id = getCurrentUserId();

// Validate quantity
if ($quantity <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Quantity must be greater than 0'
    ]);
    exit;
}

try {
    $db = new Database();
    
    // Check if product exists and get stock
    $product = $db->fetchOne("
        SELECT p.id, p.stock_quantity, p.status, sp.is_approved 
        FROM products p
        JOIN seller_profiles sp ON p.seller_id = sp.user_id
        WHERE p.id = ?
    ", [$product_id]);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Product not found'
        ]);
        exit;
    }
    
    // Check if product is available
    if ($product['status'] !== 'approved' || !$product['is_approved']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Product is not available'
        ]);
        exit;
    }
    
    // Validate stock
    if ($quantity > $product['stock_quantity']) {
        echo json_encode([
            'success' => false,
            'error' => "Only {$product['stock_quantity']} items available in stock",
            'max_quantity' => $product['stock_quantity']
        ]);
        exit;
    }
    
    // Check if item exists in cart
    $existing = $db->fetchOne("
        SELECT id FROM cart WHERE user_id = ? AND product_id = ?
    ", [$user_id, $product_id]);
    
    if ($existing) {
        // Update existing cart item
        $db->update(
            'cart',
            ['quantity' => $quantity],
            'user_id = ? AND product_id = ?',
            [$user_id, $product_id]
        );
    } else {
        // Add new cart item
        $db->insert('cart', [
            'user_id' => $user_id,
            'product_id' => $product_id,
            'quantity' => $quantity,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Get updated cart totals
    $cart_items = $db->fetchAll("
        SELECT c.quantity, p.price_per_unit
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ? AND p.status = 'approved'
    ", [$user_id]);
    
    $subtotal = 0;
    $item_count = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price_per_unit'] * $item['quantity'];
        $item_count += $item['quantity'];
    }
    
    $shipping = 500; // Default shipping
    $total = $subtotal + $shipping;
    
    // Log activity (optional)
    logActivity($user_id, 'cart_update', json_encode([
        'product_id' => $product_id,
        'quantity' => $quantity
    ]));
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated successfully',
        'data' => [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'item_count' => $item_count
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
    error_log("Cart update error: " . $e->getMessage());
}