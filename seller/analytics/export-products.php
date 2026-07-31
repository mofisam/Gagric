<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Mirrors the exact period filter used on sales-analytics.php.
$time_filter = $_GET['period'] ?? 'month';

$date_ranges = [
    'week' => date('Y-m-d', strtotime('-1 week')),
    'month' => date('Y-m-d', strtotime('-1 month')),
    'quarter' => date('Y-m-d', strtotime('-3 months')),
    'year' => date('Y-m-d', strtotime('-1 year'))
];
$start_date = $date_ranges[$time_filter] ?? $date_ranges['month'];

$top_products = $db->fetchAll("
    SELECT
        p.name,
        p.unit,
        SUM(oi.quantity) as units_sold,
        COUNT(DISTINCT oi.order_id) as order_count,
        SUM(oi.item_total) as revenue,
        AVG(oi.unit_price) as avg_price
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE oi.seller_id = ?
        AND oi.status = 'delivered'
        AND DATE(o.created_at) >= ?
    GROUP BY p.id
    ORDER BY revenue DESC
", [$seller_id, $start_date]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=top-products-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Product', 'Unit', 'Units Sold', 'Orders', 'Avg Price', 'Total Revenue']);

foreach ($top_products as $p) {
    fputcsv($out, [
        $p['name'],
        $p['unit'],
        $p['units_sold'],
        $p['order_count'],
        number_format((float)$p['avg_price'], 2, '.', ''),
        number_format((float)$p['revenue'], 2, '.', ''),
    ]);
}

fclose($out);
exit;