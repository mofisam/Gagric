<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';

$db = new Database();

// Mirrors the exact filters used on manage-products.php.
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR sp.business_name LIKE ? OR p.variety LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

if (!empty($category)) {
    $where .= " AND p.category_id = ?";
    $params[] = $category;
}

$products = $db->fetchAll("
    SELECT p.id, p.name, p.variety, p.product_type, p.price_per_unit, p.unit, p.unit_quantity,
           p.stock_quantity, p.status, p.is_featured, p.created_at,
           sp.business_name, c.name as category_name,
           u.first_name as seller_first_name, u.last_name as seller_last_name
    FROM products p
    JOIN seller_profiles sp ON p.seller_id = sp.user_id
    JOIN users u ON p.seller_id = u.id
    JOIN categories c ON p.category_id = c.id
    $where
    ORDER BY p.created_at DESC
", $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'ID', 'Name', 'Variety', 'Type', 'Category', 'Seller', 'Price Per Unit', 'Unit Quantity', 'Unit',
    'Stock', 'Status', 'Featured', 'Created At'
]);

foreach ($products as $p) {
    fputcsv($out, [
        $p['id'],
        $p['name'],
        $p['variety'] ?? '',
        ucfirst($p['product_type']),
        $p['category_name'],
        $p['business_name'],
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