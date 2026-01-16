<?php
header('Content-Type: application/json');
require_once '../../classes/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get user's wishlist
$wishlist = $db->fetchAll("
    SELECT product_id 
    FROM wishlists 
    WHERE user_id = ?
", [$user_id]);

$product_ids = array_column($wishlist, 'product_id');

echo json_encode([
    'success' => true,
    'wishlist' => $product_ids
]);
?>