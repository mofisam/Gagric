<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';

$db = new Database();

// Mirrors the exact filters used on payments.php.
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$method = $_GET['method'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.paystack_reference LIKE ? OR o.order_number LIKE ? OR p.customer_email LIKE ? OR p.customer_name LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

if (!empty($method)) {
    $where .= " AND p.payment_method = ?";
    $params[] = $method;
}

if (!empty($date_from)) {
    $where .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

// Note: orders.payment_id -> payments.id (a payment can cover more than one
// order in a multi-seller checkout), not the other way around.
$payments = $db->fetchAll("
    SELECT p.paystack_reference, p.amount, p.currency, p.payment_method, p.status,
           p.customer_email, p.customer_name, p.created_at, p.paid_at,
           o.order_number, o.total_amount, o.status as order_status,
           u.first_name, u.last_name, u.phone
    FROM payments p
    JOIN orders o ON o.payment_id = p.id
    JOIN users u ON o.buyer_id = u.id
    $where
    ORDER BY p.created_at DESC
", $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=payments-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Paystack Reference', 'Order Number', 'Customer Name', 'Customer Email', 'Customer Phone',
    'Amount', 'Currency', 'Payment Method', 'Status', 'Order Status', 'Created At', 'Paid At'
]);

foreach ($payments as $p) {
    fputcsv($out, [
        $p['paystack_reference'],
        $p['order_number'],
        $p['customer_name'] ?: ($p['first_name'] . ' ' . $p['last_name']),
        $p['customer_email'],
        $p['phone'],
        number_format((float)$p['amount'], 2, '.', ''),
        $p['currency'],
        ucfirst(str_replace('_', ' ', $p['payment_method'])),
        ucfirst($p['status']),
        ucfirst($p['order_status']),
        $p['created_at'],
        $p['paid_at'] ?? '',
    ]);
}

fclose($out);
exit;