<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';

$db = new Database();

// Mirrors the exact filters used on manage-users.php, so exporting the
// current filtered view gives the same rows the admin is looking at.
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR sp.business_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($role)) {
    $where .= " AND u.role = ?";
    $params[] = $role;
}

if (!empty($status)) {
    if ($status === 'active') {
        $where .= " AND u.is_active = TRUE";
    } elseif ($status === 'inactive') {
        $where .= " AND u.is_active = FALSE";
    }
}

$users = $db->fetchAll("
    SELECT u.id, u.uuid, u.first_name, u.last_name, u.email, u.phone, u.role,
           sp.business_name, sp.is_approved as seller_approved,
           u.is_active, u.is_email_verified, u.created_at, u.last_login
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    $where
    ORDER BY u.created_at DESC
", $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');

// BOM so Excel opens UTF-8 (Naira sign etc.) correctly
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, [
    'ID', 'UUID', 'First Name', 'Last Name', 'Email', 'Phone', 'Role',
    'Business Name', 'Seller Approved', 'Active', 'Email Verified', 'Joined', 'Last Login'
]);

foreach ($users as $u) {
    fputcsv($out, [
        $u['id'],
        $u['uuid'],
        $u['first_name'],
        $u['last_name'],
        $u['email'],
        $u['phone'],
        ucfirst($u['role']),
        $u['business_name'] ?? '',
        $u['role'] === 'seller' ? ($u['seller_approved'] ? 'Yes' : 'No') : '',
        $u['is_active'] ? 'Yes' : 'No',
        $u['is_email_verified'] ? 'Yes' : 'No',
        $u['created_at'],
        $u['last_login'] ?? '',
    ]);
}

fclose($out);
exit;