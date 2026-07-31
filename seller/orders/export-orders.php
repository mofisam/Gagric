<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Mirrors the exact filters used on seller/orders/manage-orders.php.
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = "oi.seller_id = ?";
$params = [$seller_id];

if ($status_filter && $status_filter !== 'all') {
    $where .= " AND oi.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where .= " AND (o.order_number LIKE ? OR p.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$orders = $db->fetchAll("
    SELECT
        oi.id as order_item_id, oi.quantity, oi.unit_price, oi.item_total, oi.status as item_status,
        o.order_number, o.created_at, o.payment_status,
        p.name as product_name, p.unit,
        u.first_name, u.last_name, u.email, u.phone
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE $where
    ORDER BY o.created_at DESC
", $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=my-orders-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Order Number', 'Product', 'Quantity', 'Unit', 'Unit Price', 'Item Total',
    'Item Status', 'Payment Status', 'Buyer Name', 'Buyer Email', 'Buyer Phone', 'Order Date'
]);

foreach ($orders as $o) {
    fputcsv($out, [
        $o['order_number'],
        $o['product_name'],
        $o['quantity'],
        $o['unit'],
        number_format((float)$o['unit_price'], 2, '.', ''),
        number_format((float)$o['item_total'], 2, '.', ''),
        ucfirst($o['item_status']),
        ucfirst($o['payment_status']),
        $o['first_name'] . ' ' . $o['last_name'],
        $o['email'],
        $o['phone'],
        $o['created_at'],
    ]);
}

fclose($out);
exit;