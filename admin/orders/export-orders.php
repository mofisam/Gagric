<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';

$db = new Database();

// Mirrors the exact filters used on manage-orders.php.
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    $where .= " AND o.status = ?";
    $params[] = $status;
}

if (!empty($payment_status)) {
    $where .= " AND o.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($date_from)) {
    $where .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$orders = $db->fetchAll("
    SELECT o.order_number, o.subtotal_amount, o.shipping_amount, o.total_amount,
           o.status, o.payment_status, o.created_at, o.paid_at, o.delivered_at,
           u.first_name, u.last_name, u.email, u.phone,
           COUNT(oi.id) as items_count,
           SUM(oi.quantity) as total_quantity,
           COUNT(DISTINCT oi.seller_id) as sellers_count
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $where
    GROUP BY o.id
    ORDER BY o.created_at DESC
", $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=orders-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Order Number', 'Buyer Name', 'Buyer Email', 'Buyer Phone',
    'Items', 'Total Quantity', 'Sellers Involved',
    'Subtotal', 'Shipping', 'Total', 'Order Status', 'Payment Status',
    'Created At', 'Paid At', 'Delivered At'
]);

foreach ($orders as $o) {
    fputcsv($out, [
        $o['order_number'],
        $o['first_name'] . ' ' . $o['last_name'],
        $o['email'],
        $o['phone'],
        $o['items_count'],
        $o['total_quantity'],
        $o['sellers_count'],
        number_format((float)$o['subtotal_amount'], 2, '.', ''),
        number_format((float)$o['shipping_amount'], 2, '.', ''),
        number_format((float)$o['total_amount'], 2, '.', ''),
        ucfirst($o['status']),
        ucfirst($o['payment_status']),
        $o['created_at'],
        $o['paid_at'] ?? '',
        $o['delivered_at'] ?? '',
    ]);
}

fclose($out);
exit;