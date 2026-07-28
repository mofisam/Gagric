<?php
// Set JSON header first
header('Content-Type: application/json');

// Include files that don't output HTML
require_once '../../config/database.php';
require_once '../../config/constants.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Get input from POST or JSON
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's JSON input
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($content_type, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        // Regular POST data
        $input = $_POST;
    }
}

$order_id = $input['order_id'] ?? 0;

if (empty($order_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID required']);
    exit;
}

$db = new Database();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'buyer';

// Check if order exists and belongs to user
$order = $db->fetchOne("
    SELECT o.*, os.tracking_number 
    FROM orders o 
    LEFT JOIN order_shipping_details os ON o.id = os.order_id 
    WHERE o.id = ? 
    AND (o.buyer_id = ? OR ? = 'admin')
", [$order_id, $user_id, $user_role]);

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// Check if order can be cancelled
$cancellable_statuses = ['pending', 'confirmed'];
if (!in_array($order['status'], $cancellable_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order cannot be cancelled. Current status: ' . $order['status']]);
    exit;
}

// Check if shipment already dispatched
if ($order['tracking_number']) {
    http_response_code(400);
    echo json_encode(['error' => 'Order has already been dispatched for shipping']);
    exit;
}

// Start transaction
$db->conn->begin_transaction();

try {
    // Update order status
    $db->query(
        "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
        [$order_id]
    );

    // Update order items status
    $db->query(
        "UPDATE order_items SET status = 'cancelled' WHERE order_id = ?",
        [$order_id]
    );

    // Restore product stock quantities, and keep seller_id/quantity/item id
    // around for the refund_requests inserts below.
    $order_items = $db->fetchAll(
        "SELECT id, product_id, seller_id, quantity FROM order_items WHERE order_id = ?",
        [$order_id]
    );

    foreach ($order_items as $item) {
        $db->query(
            "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
            [$item['quantity'], $item['product_id']]
        );
    }

    // If payment was made, initiate refund
    if ($order['payment_status'] === 'paid') {
        // Mark the linked payment for refund (orders.payment_id -> payments.id,
        // not the other way around - a payment can cover more than one order).
        if (!empty($order['payment_id'])) {
            $db->query(
                "UPDATE payments SET status = 'refund_pending' WHERE id = ?",
                [$order['payment_id']]
            );
        }

        // Log a refund request per order item (refund_requests is keyed by
        // order_item_id + seller_id, there is no order-level refunds table).
        $item_amount = count($order_items) > 0 ? $order['total_amount'] / count($order_items) : $order['total_amount'];
        foreach ($order_items as $item) {
            $db->query(
                "INSERT INTO refund_requests (order_item_id, seller_id, amount, reason, status) 
                 VALUES (?, ?, ?, 'Order cancellation', 'pending')",
                [$item['id'], $item['seller_id'], $item_amount]
            );
        }
    }

    // Log the cancellation
    $db->query(
        "INSERT INTO order_status_history (order_id, status, changed_by, notes) 
         VALUES (?, 'cancelled', ?, 'Order cancelled by user')",
        [$order_id, $user_id]
    );

    // Notify seller(s) and buyer via the app's Notification class
    // (there is no separate `notifications` table to insert into directly).
    require_once '../../classes/Notification.php';
    $notification = new Notification($db);

    $sellers = $db->fetchAll(
        "SELECT DISTINCT seller_id FROM order_items WHERE order_id = ?",
        [$order_id]
    );

    foreach ($sellers as $seller) {
        $notification->sendToSeller(
            $seller['seller_id'],
            'Order Cancelled',
            'Order #' . $order['order_number'] . ' has been cancelled'
        );
    }

    $notification->sendToUser(
        $user_id,
        'Order Cancelled',
        'Your order #' . $order['order_number'] . ' has been cancelled successfully'
    );

    // Commit transaction
    $db->conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully',
        'order_id' => $order_id,
        'order_number' => $order['order_number'],
        'refund_status' => $order['payment_status'] === 'paid' ? 'pending' : 'not_required'
    ]);

} catch (Exception $e) {
    // Rollback on error
    $db->conn->rollback();
    
    error_log("Order cancellation failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['error' => 'Order cancellation failed. Please try again or contact support.']);
}

// Ensure nothing else is output
exit;
?>