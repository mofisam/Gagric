<?php
require_once '../../includes/auth.php';
requireAdmin();

if (!adminHasPermission('users')) {
    setFlashMessage("You do not have the permission to view this page.", 'error');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

require_once '../../classes/Database.php';

$db = new Database();

// Mirrors the exact filters used on seller-approvals.php.
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'pending';
$has_docs = isset($_GET['has_docs']) ? (int)$_GET['has_docs'] : 0;

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (sp.business_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    if ($status === 'pending') {
        $where .= " AND sp.is_approved = 0";
    } elseif ($status === 'approved') {
        $where .= " AND sp.is_approved = 1";
    }
}

if ($has_docs) {
    $where .= " AND sp.verification_documents IS NOT NULL AND sp.verification_documents != '[]' AND sp.verification_documents != 'null'";
}

$sellers = $db->fetchAll("
    SELECT
        sp.business_name, sp.is_approved, sp.total_sales, sp.avg_rating, sp.created_at as seller_since,
        u.id as user_id, u.email, u.phone, u.first_name, u.last_name, u.created_at as user_created_at, u.last_login,
        (SELECT COUNT(*) FROM products WHERE seller_id = u.id AND status = 'approved') as approved_products,
        (SELECT COUNT(*) FROM products WHERE seller_id = u.id AND status = 'pending') as pending_products,
        (SELECT SUM(oi.unit_price * oi.quantity) FROM order_items oi WHERE oi.seller_id = u.id) as total_order_value,
        (SELECT AVG(CAST(sr.rating AS DECIMAL)) FROM seller_ratings sr WHERE sr.seller_id = u.id) as avg_rating_calc,
        (SELECT COUNT(*) FROM seller_ratings WHERE seller_id = u.id) as total_reviews
    FROM seller_profiles sp
    JOIN users u ON sp.user_id = u.id
    $where
    ORDER BY
        CASE WHEN sp.is_approved = 0 THEN 0 ELSE 1 END,
        sp.created_at DESC
", $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sellers-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'Seller ID', 'Business Name', 'First Name', 'Last Name', 'Email', 'Phone',
    'Approved', 'Approved Products', 'Pending Products', 'Total Order Value',
    'Avg Rating', 'Total Reviews', 'Seller Since', 'Last Login'
]);

foreach ($sellers as $s) {
    fputcsv($out, [
        $s['user_id'],
        $s['business_name'],
        $s['first_name'],
        $s['last_name'],
        $s['email'],
        $s['phone'],
        $s['is_approved'] ? 'Yes' : 'No',
        $s['approved_products'],
        $s['pending_products'],
        number_format((float)($s['total_order_value'] ?? 0), 2, '.', ''),
        number_format((float)($s['avg_rating_calc'] ?? 0), 2),
        $s['total_reviews'],
        $s['seller_since'],
        $s['last_login'] ?? '',
    ]);
}

fclose($out);
exit;