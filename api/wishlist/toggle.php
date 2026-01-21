<?php
header('Content-Type: application/json');
require_once '../../classes/Database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login to use wishlist']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$product_id = $input['product_id'] ?? 0;

if (!$product_id) {
    echo json_encode(['success' => false, 'error' => 'Product ID required']);
    exit;
}

$db = new Database();
$user_id = $_SESSION['user_id'];

// Check if already in wishlist
$exists = $db->fetchOne("
    SELECT id FROM wishlists 
    WHERE user_id = ? AND product_id = ?
", [$user_id, $product_id]);

try {
    if ($exists) {
        // Remove from wishlist
        $db->query("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
        $action = 'removed';
    } else {
        // Add to wishlist
        $db->query("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)", [$user_id, $product_id]);
        $action = 'added';
    }
    
    echo json_encode([
        'success' => true,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>