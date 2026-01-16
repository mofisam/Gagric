<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$product_id = $_GET['id'] ?? 0;

$sql = "SELECT p.*, sp.business_name, c.name as category_name, 
               pad.grade, pad.is_organic, pad.harvest_date
        FROM products p 
        JOIN seller_profiles sp ON p.seller_id = sp.user_id 
        JOIN categories c ON p.category_id = c.id 
        LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id 
        WHERE p.id = ? AND p.status = 'approved'";

$stmt = $db->conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if ($product) {
    echo json_encode($product);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
}
?>