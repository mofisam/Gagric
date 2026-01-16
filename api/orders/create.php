<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$input = json_decode(file_get_contents('php://input'), true);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Validate cart items and calculate total
$order_total = 0;
$order_items = [];

foreach ($input['items'] as $item) {
    $stmt = $db->conn->prepare("SELECT price_per_unit, stock_quantity FROM products WHERE id = ? AND status = 'approved'");
    $stmt->bind_param("i", $item['product_id']);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if (!$product || $product['stock_quantity'] < $item['quantity']) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid product or insufficient stock']);
        exit;
    }
    
    $item_total = $product['price_per_unit'] * $item['quantity'];
    $order_total += $item_total;
    $order_items[] = array_merge($item, ['unit_price' => $product['price_per_unit']]);
}

// Create order
$order_number = 'ORD' . time() . rand(100, 999);
$stmt = $db->conn->prepare("INSERT INTO orders (order_number, buyer_id, total_amount) VALUES (?, ?, ?)");
$stmt->bind_param("sii", $order_number, $_SESSION['user_id'], $order_total);

if ($stmt->execute()) {
    $order_id = $db->conn->insert_id;
    echo json_encode(['success' => true, 'order_id' => $order_id, 'order_number' => $order_number]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Order creation failed']);
}
?>