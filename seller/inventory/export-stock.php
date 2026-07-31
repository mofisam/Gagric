<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

$products = $db->fetchAll("
    SELECT p.name, p.unit, p.unit_quantity, p.price_per_unit, p.stock_quantity, p.low_stock_alert_level,
           c.name as category_name,
           ag.grade, ag.is_organic, ag.harvest_date, ag.shelf_life_days,
           (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi
            WHERE oi.product_id = p.id AND oi.status IN ('confirmed', 'shipped', 'pending')) as reserved_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_agricultural_details ag ON p.id = ag.product_id
    WHERE p.seller_id = ? AND p.status = 'approved'
    ORDER BY
        CASE
            WHEN p.stock_quantity <= 0 THEN 1
            WHEN p.stock_quantity <= p.low_stock_alert_level THEN 2
            ELSE 3
        END,
        p.stock_quantity ASC
", [$seller_id]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=stock-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Product', 'Category', 'Grade', 'Organic', 'Stock Quantity', 'Reserved', 'Unit',
    'Low Stock Alert Level', 'Price Per Unit', 'Stock Value', 'Harvest Date', 'Shelf Life (Days)', 'Status'
]);

foreach ($products as $p) {
    $status = $p['stock_quantity'] <= 0
        ? 'Out of Stock'
        : ($p['stock_quantity'] <= $p['low_stock_alert_level'] ? 'Low Stock' : 'In Stock');

    fputcsv($out, [
        $p['name'],
        $p['category_name'] ?? '',
        $p['grade'] ?? '',
        $p['is_organic'] ? 'Yes' : 'No',
        $p['stock_quantity'],
        $p['reserved_stock'],
        $p['unit'],
        $p['low_stock_alert_level'],
        number_format((float)$p['price_per_unit'], 2, '.', ''),
        number_format((float)$p['stock_quantity'] * (float)$p['price_per_unit'], 2, '.', ''),
        $p['harvest_date'] ?? '',
        $p['shelf_life_days'] ?? '',
        $status,
    ]);
}

fclose($out);
exit;