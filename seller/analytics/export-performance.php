<?php
// seller/analytics/export-performance.php
$vendorPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    die('Vendor autoload not found. Run: composer require dompdf/dompdf');
}
require_once $vendorPath;

require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$db = new Database();
$seller_id = $_SESSION['user_id'];

$seller_profile = $db->fetchOne("
    SELECT business_name, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

$current_month = date('Y-m-01 00:00:00');
$last_month_start = date('Y-m-01', strtotime('-1 month')) . ' 00:00:00';
$last_month_end = date('Y-m-t', strtotime('-1 month')) . ' 23:59:59';

$metrics = $db->fetchOne("
    SELECT
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.item_total), 0) as total_revenue,
        COALESCE(AVG(oi.item_total), 0) as avg_order_value,
        COUNT(DISTINCT o.buyer_id) as unique_customers,
        (SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'approved') as active_products,
        (SELECT COUNT(*) FROM products WHERE seller_id = ?) as total_products,
        (SELECT AVG(rating) FROM seller_ratings WHERE seller_id = ?) as avg_rating,
        (SELECT COUNT(*) FROM seller_ratings WHERE seller_id = ?) as total_reviews,
        SUM(CASE WHEN o.created_at >= ? THEN oi.item_total ELSE 0 END) as monthly_revenue,
        COUNT(DISTINCT CASE WHEN o.created_at >= ? THEN o.id END) as monthly_orders,
        SUM(CASE WHEN o.created_at BETWEEN ? AND ? THEN oi.item_total ELSE 0 END) as last_month_revenue,
        COUNT(DISTINCT CASE WHEN o.created_at BETWEEN ? AND ? THEN o.id END) as last_month_orders
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? AND oi.status = 'delivered'
", [
    $seller_id, $seller_id, $seller_id, $seller_id,
    $current_month, $current_month,
    $last_month_start, $last_month_end,
    $last_month_start, $last_month_end,
    $seller_id
]);

// Average time from order placed to delivered, in hours (uses orders.created_at
// and orders.delivered_at - order_items has no created_at column of its own).
$fulfillment = $db->fetchOne("
    SELECT AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.delivered_at)) as avg_fulfillment_hours
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? AND oi.status = 'delivered' AND o.delivered_at IS NOT NULL
", [$seller_id]);

$top_products = $db->fetchAll("
    SELECT p.name, p.unit,
           SUM(oi.quantity) as units_sold,
           COUNT(DISTINCT oi.order_id) as order_count,
           SUM(oi.item_total) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE oi.seller_id = ? AND oi.status = 'delivered'
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 10
", [$seller_id]);

$revenue_growth = ($metrics['last_month_revenue'] ?? 0) > 0
    ? (($metrics['monthly_revenue'] - $metrics['last_month_revenue']) / $metrics['last_month_revenue']) * 100
    : 0;

function pdfMoney($amount) {
    return '&#8358;' . number_format((float)$amount, 2);
}

$rows = '';
foreach ($top_products as $p) {
    $rows .= '<tr>
        <td>' . htmlspecialchars($p['name']) . '</td>
        <td style="text-align:center;">' . number_format($p['units_sold'], 2) . ' ' . htmlspecialchars($p['unit']) . '</td>
        <td style="text-align:center;">' . $p['order_count'] . '</td>
        <td style="text-align:right;">' . pdfMoney($p['revenue']) . '</td>
    </tr>';
}
if (empty($top_products)) {
    $rows = '<tr><td colspan="4" style="text-align:center; color:#888;">No delivered sales yet.</td></tr>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: Helvetica, Arial, sans-serif; color: #333; font-size: 12px; }
    h1 { color: #198754; margin-bottom: 0; }
    .subtitle { color: #666; margin-top: 4px; margin-bottom: 20px; }
    .metrics-grid { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .metrics-grid td { padding: 10px; border: 1px solid #ddd; width: 25%; }
    .metrics-grid .label { color: #666; font-size: 10px; display: block; }
    .metrics-grid .value { font-size: 16px; font-weight: bold; color: #198754; }
    table.products { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.products th { background: #f8f9fa; text-align: left; padding: 8px; border-bottom: 2px solid #ddd; }
    table.products td { padding: 8px; border-bottom: 1px solid #eee; }
    .footer { margin-top: 30px; color: #999; font-size: 10px; text-align: center; }
</style>
</head>
<body>
    <h1>Performance Report</h1>
    <p class="subtitle">' . htmlspecialchars($seller_profile['business_name'] ?? 'Seller') . ' &middot; Generated ' . date('F j, Y') . '</p>

    <table class="metrics-grid">
        <tr>
            <td><span class="label">Total Orders (Delivered)</span><span class="value">' . number_format($metrics['total_orders']) . '</span></td>
            <td><span class="label">Total Revenue</span><span class="value">' . pdfMoney($metrics['total_revenue']) . '</span></td>
            <td><span class="label">Avg Order Value</span><span class="value">' . pdfMoney($metrics['avg_order_value']) . '</span></td>
            <td><span class="label">Unique Customers</span><span class="value">' . number_format($metrics['unique_customers']) . '</span></td>
        </tr>
        <tr>
            <td><span class="label">Active Products</span><span class="value">' . number_format($metrics['active_products']) . ' / ' . number_format($metrics['total_products']) . '</span></td>
            <td><span class="label">Avg Rating</span><span class="value">' . number_format($metrics['avg_rating'] ?? 0, 1) . ' / 5</span></td>
            <td><span class="label">Total Reviews</span><span class="value">' . number_format($metrics['total_reviews']) . '</span></td>
            <td><span class="label">Avg Fulfillment Time</span><span class="value">' . ($fulfillment['avg_fulfillment_hours'] ? number_format($fulfillment['avg_fulfillment_hours'], 1) . ' hrs' : 'N/A') . '</span></td>
        </tr>
        <tr>
            <td><span class="label">This Month Revenue</span><span class="value">' . pdfMoney($metrics['monthly_revenue']) . '</span></td>
            <td><span class="label">Last Month Revenue</span><span class="value">' . pdfMoney($metrics['last_month_revenue']) . '</span></td>
            <td><span class="label">Revenue Growth</span><span class="value">' . ($revenue_growth >= 0 ? '+' : '') . number_format($revenue_growth, 1) . '%</span></td>
            <td><span class="label">This Month Orders</span><span class="value">' . number_format($metrics['monthly_orders']) . '</span></td>
        </tr>
    </table>

    <h3>Top Products by Revenue</h3>
    <table class="products">
        <thead>
            <tr>
                <th>Product</th>
                <th style="text-align:center;">Units Sold</th>
                <th style="text-align:center;">Orders</th>
                <th style="text-align:right;">Revenue</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
    </table>

    <p class="footer">Green Agric &middot; Seller Performance Report</p>
</body>
</html>
';

try {
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    if (ob_get_length()) ob_clean();

    $dompdf->stream(
        'performance-report-' . date('Y-m-d') . '.pdf',
        [
            'Attachment' => true,
            'compress' => true
        ]
    );

} catch (Exception $e) {
    error_log('Performance PDF Generation Error: ' . $e->getMessage());
    die('Failed to generate PDF report. Please try again.');
}