<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();

if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$report_type = $_GET['type'] ?? 'sales';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

switch ($report_type) {
    case 'sales':
        $sql = "
            SELECT DATE(created_at) as date, SUM(total_amount) as daily_sales, COUNT(*) as order_count 
            FROM orders 
            WHERE created_at BETWEEN ? AND ? AND payment_status = 'paid' 
            GROUP BY DATE(created_at) 
            ORDER BY date
        ";
        break;
        
    case 'sellers':
        $sql = "
            SELECT sp.business_name, SUM(oi.item_total) as total_sales, COUNT(oi.id) as orders_count 
            FROM seller_profiles sp 
            JOIN order_items oi ON sp.user_id = oi.seller_id 
            JOIN orders o ON oi.order_id = o.id 
            WHERE o.created_at BETWEEN ? AND ? AND o.payment_status = 'paid' 
            GROUP BY sp.user_id 
            ORDER BY total_sales DESC
        ";
        break;
        
    case 'products':
        $sql = "
            SELECT p.name, SUM(oi.quantity) as units_sold, SUM(oi.item_total) as revenue 
            FROM products p 
            JOIN order_items oi ON p.id = oi.product_id 
            JOIN orders o ON oi.order_id = o.id 
            WHERE o.created_at BETWEEN ? AND ? AND o.payment_status = 'paid' 
            GROUP BY p.id 
            ORDER BY revenue DESC 
            LIMIT 20
        ";
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report type']);
        exit;
}

$stmt = $db->conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();

echo json_encode([
    'report_type' => $report_type,
    'period' => ['start' => $start_date, 'end' => $end_date],
    'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
]);
?>