<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

function respond($success, $payload = []) {
    echo json_encode(array_merge(['success' => $success], $payload));
    exit;
}

if (!isLoggedIn() || !hasRole('seller')) {
    http_response_code(401);
    respond(false, ['error' => 'Your session has expired. Please log in again.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, ['error' => 'Invalid request method']);
}

$db = new Database();
$seller_id = $_SESSION['user_id'];

$order_item_id = (int)($_POST['order_item_id'] ?? 0);
$order_id = (int)($_POST['order_id'] ?? 0);
$logistics_partner = trim($_POST['logistics_partner'] ?? '');
$tracking_number = trim($_POST['tracking_number'] ?? '');
$estimated_delivery = trim($_POST['estimated_delivery'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$order_item_id || !$order_id) {
    respond(false, ['error' => 'Missing order item details']);
}

if ($logistics_partner === '' || $tracking_number === '' || $estimated_delivery === '') {
    respond(false, ['error' => 'Logistics partner, tracking number, and estimated delivery are required']);
}

$deliveryDate = DateTime::createFromFormat('Y-m-d', $estimated_delivery);
if (!$deliveryDate || $deliveryDate->format('Y-m-d') !== $estimated_delivery) {
    respond(false, ['error' => 'Invalid estimated delivery date']);
}

try {
    $item = $db->fetchOne("
        SELECT 
            oi.id,
            oi.order_id,
            oi.status as item_status,
            o.order_number,
            o.status as order_status,
            o.payment_status
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.id = ?
        AND oi.order_id = ?
        AND oi.seller_id = ?
        LIMIT 1
    ", [$order_item_id, $order_id, $seller_id]);

    if (!$item) {
        respond(false, ['error' => 'Order item not found or access denied']);
    }

    if ($item['payment_status'] !== 'paid') {
        respond(false, ['error' => 'This order has not been paid yet']);
    }

    if (!in_array($item['order_status'], ['confirmed', 'processing'], true)) {
        respond(false, ['error' => 'Only confirmed or processing orders can be shipped']);
    }

    if (!in_array($item['item_status'], ['confirmed', 'processing'], true)) {
        respond(false, ['error' => 'Only confirmed or processing order items can be shipped']);
    }

    $shipping = $db->fetchOne(
        "SELECT id FROM order_shipping_details WHERE order_id = ? LIMIT 1",
        [$order_id]
    );

    if (!$shipping) {
        respond(false, ['error' => 'Shipping address is missing for this order']);
    }

    $db->conn->begin_transaction();

    $db->update('order_shipping_details', [
        'logistics_partner' => $logistics_partner,
        'tracking_number' => $tracking_number,
        'estimated_delivery' => $estimated_delivery
    ], 'order_id = ?', [$order_id]);

    $items_to_ship = $db->fetchAll("
        SELECT id
        FROM order_items
        WHERE id = ?
        AND order_id = ?
        AND seller_id = ?
        AND status IN ('confirmed', 'processing')
    ", [$order_item_id, $order_id, $seller_id]);

    if (empty($items_to_ship)) {
        throw new Exception('No shippable items found for this order');
    }

    $db->query("
        UPDATE order_items
        SET status = 'shipped'
        WHERE id = ?
        AND order_id = ?
        AND seller_id = ?
        AND status IN ('confirmed', 'processing')
    ", [$order_item_id, $order_id, $seller_id]);

    foreach ($items_to_ship as $ship_item) {
        $history_id = $db->insert('order_item_status_history', [
            'order_item_id' => (int)$ship_item['id'],
            'status' => 'shipped',
            'changed_by' => $seller_id,
            'notes' => $notes !== '' ? $notes : 'Marked as shipped',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        if (!$history_id) {
            throw new Exception('Failed to save item status history');
        }
    }

    $remaining = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM order_items
        WHERE order_id = ?
        AND status NOT IN ('shipped', 'delivered', 'cancelled')
    ", [$order_id]);

    if ((int)($remaining['count'] ?? 0) === 0) {
        $db->update('orders', [
            'status' => 'shipped'
        ], 'id = ?', [$order_id]);

        $history_id = $db->insert('order_status_history', [
            'order_id' => $order_id,
            'status' => 'shipped',
            'changed_by' => $seller_id,
            'notes' => $notes !== '' ? $notes : 'Order marked as shipped'
        ]);

        if (!$history_id) {
            throw new Exception('Failed to save order status history');
        }
    }

    $db->conn->commit();

    respond(true, [
        'message' => 'Order marked as shipped',
        'order_number' => $item['order_number'],
        'tracking_number' => $tracking_number
    ]);

} catch (Exception $e) {
    if ($db->conn) {
        @$db->conn->rollback();
    }

    error_log('Process shipping error: ' . $e->getMessage());
    respond(false, ['error' => $e->getMessage()]);
}
?>
