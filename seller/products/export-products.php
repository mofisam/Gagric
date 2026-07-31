<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Mirrors the exact filters used on seller/products/manage-products.php.
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = "p.seller_id = ?";
$params = [$seller_id];

if (!empty($status_filter)) {
    $where .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.variety LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

$products = $db->fetchAll("
    SELECT p.*, c.name as category_name, ag.grade, ag.is_organic, ag.harvest_date
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_agricultural_details ag ON p.id = ag.product_id
    WHERE $where
    ORDER BY p.created_at DESC
", $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=my-products-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Name', 'Category', 'Type', 'Variety', 'Grade', 'Organic',
    'Price Per Unit', 'Unit Quantity', 'Unit', 'Stock', 'Status', 'Featured', 'Created At'
]);

foreach ($products as $p) {
    fputcsv($out, [
        $p['name'],
        $p['category_name'] ?? '',
        ucfirst($p['product_type']),
        $p['variety'] ?? '',
        $p['grade'] ?? '',
        $p['is_organic'] ? 'Yes' : 'No',
        number_format((float)$p['price_per_unit'], 2, '.', ''),
        $p['unit_quantity'],
        $p['unit'],
        $p['stock_quantity'],
        ucfirst($p['status']),
        $p['is_featured'] ? 'Yes' : 'No',
        $p['created_at'],
    ]);
}

fclose($out);
exit; 