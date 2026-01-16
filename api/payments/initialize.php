<?php
// Set headers FIRST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For debugging, remove in production
header('Access-Control-Allow-Credentials: true');

// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once '../../config/database.php';
require_once '../../config/paystack.php';

// Create response array
$response = ['success' => false];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }

    $db = new Database();
    $user_id = $_SESSION['user_id'];

    // Get input data
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($content_type, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
        } else {
            $input = $_POST;
        }
    }

    // Get order number
    $order_number = $input['order_number'] ?? $_GET['order_number'] ?? '';
    
    if (empty($order_number)) {
        throw new Exception('Order number is required');
    }

    // Get order details
    $order = $db->fetchOne("
        SELECT o.*, u.email, u.first_name, u.last_name 
        FROM orders o 
        JOIN users u ON o.buyer_id = u.id 
        WHERE o.order_number = ? AND o.buyer_id = ?
    ", [$order_number, $user_id]);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Check if order is already paid
    if ($order['payment_status'] === 'paid') {
        throw new Exception('Order already paid');
    }

    $email = $order['email'];
    $amount = $order['total_amount'];
    $order_id = $order['id'];

    // Validate amount (convert to kobo)
    if ($amount <= 0) {
        throw new Exception('Invalid amount');
    }

    // Generate reference
    $reference = 'AGRIPAY_' . time() . '_' . uniqid();

    // Save payment record in database
    $db->query("
        INSERT INTO payments (order_id, paystack_reference, amount, customer_email, status) 
        VALUES (?, ?, ?, ?, 'pending')
    ", [$order_id, $reference, $amount, $email]);

    // Initialize Paystack transaction
    $paystack_response = PaystackAPI::initializeTransaction($email, $amount, $reference, [
        'order_id' => $order_id,
        'order_number' => $order_number,
        'customer_name' => $order['first_name'] . ' ' . $order['last_name']
    ]);

    // Check Paystack response
    if (!$paystack_response || !isset($paystack_response['status'])) {
        throw new Exception('Paystack API error: No response');
    }

    if (!$paystack_response['status']) {
        $error_msg = $paystack_response['message'] ?? 'Payment initialization failed';
        throw new Exception('Paystack error: ' . $error_msg);
    }

    if (!isset($paystack_response['data']['authorization_url'])) {
        throw new Exception('No authorization URL received from Paystack');
    }

    // Success response
    $response = [
        'success' => true,
        'authorization_url' => $paystack_response['data']['authorization_url'],
        'reference' => $reference,
        'order_number' => $order_number,
        'message' => 'Payment initialized successfully'
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response['error'] = $e->getMessage();
    
    // Log error
    error_log("Payment initialization error: " . $e->getMessage());
}

// Output JSON response
echo json_encode($response);
exit;
?>