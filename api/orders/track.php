<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$order_number = $_GET['order_number'] ?? '';

$sql = "SELECT o.*, os.tracking_number, os.logistics_partner, os.estimated_delivery 
        FROM orders o 
        LEFT JOIN order_shipping_details os ON o.id = os.order_id 
        WHERE o.order_number = ?";

$stmt = $db->conn->prepare($sql);
$stmt->bind_param("s", $order_number);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if ($order) {
    echo json_encode($order);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
}
?>