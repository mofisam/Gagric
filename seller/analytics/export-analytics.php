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

$sales_data = $db->fetchAll("
    SELECT
        DATE(o.created_at) as date,
        SUM(oi.item_total) as daily_sales,
        COUNT(DISTINCT oi.order_id) as order_count,
        COUNT(oi.id) as items_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ?
        AND oi.status = 'delivered'
        AND DATE(o.created_at) >= ?
    GROUP BY DATE(o.created_at)
    ORDER BY date
", [$seller_id, $start_date]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sales-analytics-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Date', 'Orders', 'Items Sold', 'Revenue']);

foreach ($sales_data as $d) {
    fputcsv($out, [
        $d['date'],
        $d['order_count'],
        $d['items_sold'],
        number_format((float)$d['daily_sales'], 2, '.', ''),
    ]);
}

fclose($out);
exit;