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
    
    // Update payment, linked orders, and item statuses.
    $stmt = $db->conn->prepare("
        UPDATE payments p 
        JOIN orders o ON o.payment_id = p.id
        SET p.status = 'success', p.paid_at = NOW(), 
            o.payment_status = 'paid',
            o.status = CASE WHEN o.status = 'pending' THEN 'confirmed' ELSE o.status END,
            o.paid_at = NOW()
        WHERE p.paystack_reference = ?
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    
    $stmt = $db->conn->prepare("
        UPDATE order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN payments pm ON o.payment_id = pm.id
        SET oi.status = 'confirmed'
        WHERE pm.paystack_reference = ?
        AND oi.status = 'pending'
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
}

if ($event['event'] === 'transfer.success') {
    $data = $event['data'];
    $transfer_code = $data['transfer_code'] ?? '';
    $transfer_reference = $data['reference'] ?? '';

    foreach (array_filter([$transfer_code, $transfer_reference]) as $reference) {
        $stmt = $db->conn->prepare("
            UPDATE seller_payouts
            SET status = 'paid', paid_at = NOW()
            WHERE paystack_transfer_reference = ?
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
    }
}

if (in_array($event['event'], ['transfer.failed', 'transfer.reversed'], true)) {
    $data = $event['data'];
    $transfer_code = $data['transfer_code'] ?? '';
    $transfer_reference = $data['reference'] ?? '';

    foreach (array_filter([$transfer_code, $transfer_reference]) as $reference) {
        $stmt = $db->conn->prepare("
            UPDATE seller_payouts
            SET status = 'failed'
            WHERE paystack_transfer_reference = ?
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
    }
}

http_response_code(200);
?>
