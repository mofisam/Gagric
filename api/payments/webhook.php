<?php
require_once '../../config/database.php';
require_once '../../config/paystack.php';

$db = new Database();
$input = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Verify webhook signature
$computed_signature = hash_hmac('sha512', $input, PaystackConfig::SECRET_KEY);

if ($signature !== $computed_signature) {
    http_response_code(401);
    exit;
}

$event = json_decode($input, true);

if ($event['event'] === 'charge.success') {
    $data = $event['data'];
    $reference = $data['reference'];
    
    // Update payment and order status
    $stmt = $db->conn->prepare("
        UPDATE payments p 
        JOIN orders o ON p.order_id = o.id 
        SET p.status = 'success', p.paid_at = NOW(), 
            o.payment_status = 'paid', o.paid_at = NOW() 
        WHERE p.paystack_reference = ?
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    
    // Update product stock
    $stmt = $db->conn->prepare("
        UPDATE products p 
        JOIN order_items oi ON p.id = oi.product_id 
        JOIN orders o ON oi.order_id = o.id 
        JOIN payments pm ON o.id = pm.order_id 
        SET p.stock_quantity = p.stock_quantity - oi.quantity 
        WHERE pm.paystack_reference = ?
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
}

http_response_code(200);
?>