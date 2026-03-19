<?php
// export-earnings.php

require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

$period = $_GET['period'] ?? 'month';
$format = $_GET['format'] ?? 'csv';

// Date ranges
$date_ranges = [
    'week' => date('Y-m-d', strtotime('-1 week')),
    'month' => date('Y-m-d', strtotime('-1 month')),
    'quarter' => date('Y-m-d', strtotime('-3 months')),
    'year' => date('Y-m-d', strtotime('-1 year'))
];

$start_date = $date_ranges[$period] ?? $date_ranges['month'];
$end_date = date('Y-m-d');


// =====================
// FETCH DETAILED DATA
// =====================
$earnings_data = $db->fetchAll("
    SELECT 
        o.order_number,
        o.created_at as order_date,
        p.name as product_name,
        p.unit,
        oi.quantity,
        oi.unit_price,
        oi.item_total as gross_amount,
        (oi.item_total * " . COMMISSION_RATE . " / 100) as commission,
        (oi.item_total - (oi.item_total * " . COMMISSION_RATE . " / 100)) as net_amount,
        oi.status,
        o.payment_status
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE oi.seller_id = ? 
        AND oi.status = 'delivered'
        AND o.created_at >= ?
        AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY o.created_at DESC
", [$seller_id, $start_date, $end_date]);


// =====================
// FETCH SUMMARY
// =====================
$summary = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT oi.order_id) as total_orders,
        COUNT(*) as total_items,
        COALESCE(SUM(oi.item_total), 0) as total_gross,
        COALESCE(SUM(oi.item_total * " . COMMISSION_RATE . " / 100), 0) as total_commission,
        COALESCE(SUM(oi.item_total - (oi.item_total * " . COMMISSION_RATE . " / 100)), 0) as total_net,
        COUNT(DISTINCT DATE(o.created_at)) as active_days
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? 
        AND oi.status = 'delivered'
        AND o.created_at >= ?
        AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
", [$seller_id, $start_date, $end_date]);


// =====================
// EXPORT CSV
// =====================
if ($format === 'csv') {

    $filename = "earnings_report_{$period}_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM (fix Excel issues)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // ===== HEADER =====
    fputcsv($output, ['EARNINGS REPORT']);
    fputcsv($output, [
        'Period:',
        ucfirst($period),
        date('M j, Y', strtotime($start_date)),
        'to',
        date('M j, Y')
    ]);
    fputcsv($output, []);

    // ===== SUMMARY =====
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Total Orders:', $summary['total_orders']]);
    fputcsv($output, ['Total Items:', $summary['total_items']]);
    fputcsv($output, ['Gross Revenue:', '₦' . number_format($summary['total_gross'], 2)]);
    fputcsv($output, ['Total Commission:', '₦' . number_format($summary['total_commission'], 2)]);
    fputcsv($output, ['Net Earnings:', '₦' . number_format($summary['total_net'], 2)]);
    fputcsv($output, ['Active Days:', $summary['active_days']]);

    $daily_avg = ($summary['active_days'] > 0)
        ? $summary['total_net'] / $summary['active_days']
        : 0;

    fputcsv($output, ['Daily Average:', '₦' . number_format($daily_avg, 2)]);
    fputcsv($output, []);

    // ===== TABLE HEADERS =====
    fputcsv($output, [
        'Order Number',
        'Order Date',
        'Product Name',
        'Quantity',
        'Unit',
        'Unit Price (₦)',
        'Gross Amount (₦)',
        'Commission (₦)',
        'Net Amount (₦)',
        'Order Status',
        'Payment Status'
    ]);

    // ===== DATA =====
    if (!empty($earnings_data)) {
        foreach ($earnings_data as $row) {
            fputcsv($output, [
                $row['order_number'],
                date('Y-m-d H:i:s', strtotime($row['order_date'])),
                $row['product_name'],
                $row['quantity'],
                $row['unit'],
                number_format($row['unit_price'], 2),
                number_format($row['gross_amount'], 2),
                number_format($row['commission'], 2),
                number_format($row['net_amount'], 2),
                ucfirst($row['status']),
                ucfirst($row['payment_status'])
            ]);
        }
    } else {
        fputcsv($output, ['No data available for the selected period']);
    }

    // ===== FOOTER =====
    fputcsv($output, []);
    fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Commission Rate:', COMMISSION_RATE . '%']);

    fclose($output);
    exit;
}


// =====================
// PDF (PLACEHOLDER)
// =====================
elseif ($format === 'pdf') {
    $_SESSION['flash_message'] = 'PDF export coming soon!';
    $_SESSION['flash_type'] = 'info';
    header('Location: earnings.php?period=' . $period);
    exit;
}


// =====================
// INVALID FORMAT
// =====================
else {
    $_SESSION['flash_message'] = 'Invalid export format';
    $_SESSION['flash_type'] = 'error';
    header('Location: earnings.php?period=' . $period);
    exit;
}
?>