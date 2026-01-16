<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once '../../config/database.php';
require_once '../../config/paystack.php';

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    $db = new Database();
    $user_id = $_SESSION['user_id'];

    // Get input data
    $order_number = $_GET['order_number'] ?? '';
    $amount = $_GET['amount'] ?? 0;
    $email = $_GET['email'] ?? '';
    
    // If no order number in GET, check session
    if (empty($order_number) && isset($_SESSION['pending_order'])) {
        $order_number = $_SESSION['pending_order'];
    }
    
    if (empty($order_number)) {
        throw new Exception('Order number is required');
    }

    // Get order details (verify ownership)
    $order = $db->fetchOne("
        SELECT o.*, u.email, u.first_name, u.last_name 
        FROM orders o 
        JOIN users u ON o.buyer_id = u.id 
        WHERE o.order_number = ? AND o.buyer_id = ?
    ", [$order_number, $user_id]);

    if (!$order) {
        throw new Exception('Order not found or access denied');
    }

    // Use order data (more secure than trusting GET parameters)
    $email = $order['email'];
    $amount = $order['total_amount'];
    $order_id = $order['id'];

    // Check if already paid
    if ($order['payment_status'] === 'paid') {
        header('Location: ../../buyer/orders/order-details.php?id=' . $order_id . '&message=already_paid');
        exit;
    }

    // Generate reference
    $reference = 'AGRIPAY_' . time() . '_' . uniqid();

    // Save payment record
    $db->query("
        INSERT INTO payments (order_id, paystack_reference, amount, customer_email, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ", [$order_id, $reference, $amount, $email]);

    // Initialize Paystack
    $paystack_response = PaystackAPI::initializeTransaction($email, $amount, $reference, [
        'order_id' => $order_id,
        'order_number' => $order_number,
        'customer_name' => $order['first_name'] . ' ' . $order['last_name']
    ]);

    // Check response and redirect
    if ($paystack_response && $paystack_response['status'] && isset($paystack_response['data']['authorization_url'])) {
        // Direct redirect to Paystack
        header('Location: ' . $paystack_response['data']['authorization_url']);
        exit;
    } else {
        $error_msg = $paystack_response['message'] ?? 'Payment initialization failed';
        throw new Exception($error_msg);
    }

} catch (Exception $e) {
    // Redirect to error page
    header('Location: ../../buyer/cart/checkout.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>