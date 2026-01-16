<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();

if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Get pending approvals
$result = $db->conn->query("
    SELECT p.*, sp.business_name, u.first_name, u.last_name 
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN users u ON p.seller_id = u.id 
    WHERE p.status = 'pending'
");

echo json_encode(['pending_approvals' => $result->fetch_all(MYSQLI_ASSOC)]);
?>