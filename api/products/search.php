<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$query = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "SELECT p.*, sp.business_name, c.name as category_name 
        FROM products p 
        JOIN seller_profiles sp ON p.seller_id = sp.user_id 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'approved' AND (p.name LIKE ? OR p.description LIKE ?)";

$params = ["%$query%", "%$query%"];

if (!empty($category)) {
    $sql .= " AND c.name = ?";
    $params[] = $category;
}

$sql .= " LIMIT 20";

$stmt = $db->conn->prepare($sql);
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();

echo json_encode(['results' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
?>