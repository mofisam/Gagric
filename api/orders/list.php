<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

if ($role === 'buyer') {
    $sql = "SELECT * FROM orders WHERE buyer_id = ? ORDER BY created_at DESC";
    $stmt = $db->conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} elseif ($role === 'seller') {
    $sql = "SELECT o.* FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.seller_id = ? 
            GROUP BY o.id 
            ORDER BY o.created_at DESC";
    $stmt = $db->conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $db->conn->prepare("SELECT * FROM orders ORDER BY created_at DESC");
}

$stmt->execute();
echo json_encode(['orders' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
?>