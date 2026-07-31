<?php
require_once '../../includes/auth.php';
requireAdmin();

if (!adminHasPermission('reports')) {
    setFlashMessage("You do not have the permission to view this page.", 'error');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

require_once '../../classes/Database.php';

$db = new Database();

// Mirrors the exact report generation logic on sales-reports.php.
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'daily';

$sales_data = [];

if ($report_type === 'weekly') {
    $sales_data = $db->fetchAll("
        SELECT YEAR(created_at) as year,
               WEEK(created_at) as week,
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               AVG(total_amount) as avg_order_value,
               MIN(DATE(created_at)) as week_start,
               MAX(DATE(created_at)) as week_end
        FROM orders
        WHERE created_at BETWEEN ? AND ?
          AND payment_status = 'paid'
        GROUP BY YEAR(created_at), WEEK(created_at)
        ORDER BY year ASC, week ASC
    ", [$date_from, $date_to]);
} elseif ($report_type === 'monthly') {
    $sales_data = $db->fetchAll("
        SELECT YEAR(created_at) as year,
               MONTH(created_at) as month,
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               AVG(total_amount) as avg_order_value
        FROM orders
        WHERE created_at BETWEEN ? AND ?
          AND payment_status = 'paid'
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY year ASC, month ASC
    ", [$date_from, $date_to]);
} else {
    $report_type = 'daily';
    $sales_data = $db->fetchAll("
        SELECT DATE(created_at) as date,
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               AVG(total_amount) as avg_order_value
        FROM orders
        WHERE created_at BETWEEN ? AND ?
          AND payment_status = 'paid'
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ", [$date_from, $date_to]);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sales-report-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

if ($report_type === 'weekly') {
    fputcsv($out, ['Year', 'Week', 'Week Start', 'Week End', 'Orders', 'Total Sales', 'Avg Order Value']);
    foreach ($sales_data as $d) {
        fputcsv($out, [
            $d['year'], $d['week'], $d['week_start'], $d['week_end'],
            $d['order_count'],
            number_format((float)$d['total_sales'], 2, '.', ''),
            number_format((float)$d['avg_order_value'], 2, '.', ''),
        ]);
    }
} elseif ($report_type === 'monthly') {
    fputcsv($out, ['Year', 'Month', 'Orders', 'Total Sales', 'Avg Order Value']);
    foreach ($sales_data as $d) {
        fputcsv($out, [
            $d['year'], date('F', mktime(0, 0, 0, $d['month'], 1)),
            $d['order_count'],
            number_format((float)$d['total_sales'], 2, '.', ''),
            number_format((float)$d['avg_order_value'], 2, '.', ''),
        ]);
    }
} else {
    fputcsv($out, ['Date', 'Orders', 'Total Sales', 'Avg Order Value']);
    foreach ($sales_data as $d) {
        fputcsv($out, [
            $d['date'],
            $d['order_count'],
            number_format((float)$d['total_sales'], 2, '.', ''),
            number_format((float)$d['avg_order_value'], 2, '.', ''),
        ]);
    }
}

fclose($out);
exit;