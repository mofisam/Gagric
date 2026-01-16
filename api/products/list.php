<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$page = max(1, $_GET['page'] ?? 1);
$limit = max(1, $_GET['limit'] ?? ITEMS_PER_PAGE);
$offset = ($page - 1) * $limit;

$sql = "SELECT p.*, sp.business_name, c.name as category_name 
        FROM products p 
        JOIN seller_profiles sp ON p.seller_id = sp.user_id 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'approved' AND sp.is_approved = TRUE 
        LIMIT ? OFFSET ?";

$stmt = $db->conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['products' => $products, 'page' => $page]);
?>