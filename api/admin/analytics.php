<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();

if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Platform analytics
$stats = [];

// Total sales
$result = $db->conn->query("SELECT SUM(total_amount) as total_sales FROM orders WHERE payment_status = 'paid'");
$stats['total_sales'] = $result->fetch_assoc()['total_sales'] ?? 0;

// Active users
$result = $db->conn->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = TRUE");
$stats['total_users'] = $result->fetch_assoc()['total_users'] ?? 0;

// Pending approvals
$result = $db->conn->query("SELECT COUNT(*) as pending_products FROM products WHERE status = 'pending'");
$stats['pending_products'] = $result->fetch_assoc()['pending_products'] ?? 0;

// Recent orders (last 30 days)
$result = $db->conn->query("
    SELECT COUNT(*) as recent_orders 
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats['recent_orders'] = $result->fetch_assoc()['recent_orders'] ?? 0;

// Top categories
$result = $db->conn->query("
    SELECT c.name, COUNT(oi.id) as order_count 
    FROM categories c 
    JOIN products p ON c.id = p.category_id 
    JOIN order_items oi ON p.id = oi.product_id 
    GROUP BY c.id 
    ORDER BY order_count DESC 
    LIMIT 5
");
$stats['top_categories'] = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($stats);
?>