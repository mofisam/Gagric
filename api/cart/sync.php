<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$db = new Database();
$user_id = getCurrentUserId();
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['cart'])) {
    echo json_encode(['success' => false, 'error' => 'No cart data']);
    exit;
}

try {
    // Clear existing cart
    $db->query("DELETE FROM cart WHERE user_id = ?", [$user_id]);
    
    // Add new cart items
    foreach ($input['cart'] as $item) {
        $db->query(
            "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)",
            [$user_id, $item['productId'], $item['quantity']]
        );
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>