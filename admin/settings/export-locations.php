<?php
require_once '../../includes/auth.php';
requireAdmin();

if (!adminHasPermission('settings')) {
    setFlashMessage("You do not have the permission to view this page.", 'error');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

require_once '../../classes/Database.php';

$db = new Database();

$rows = $db->fetchAll("
    SELECT s.name as state_name, s.code as state_code, s.region,
           l.name as lga_name,
           c.name as city_name
    FROM states s
    LEFT JOIN lgas l ON l.state_id = s.id
    LEFT JOIN cities c ON c.lga_id = l.id AND c.state_id = s.id
    ORDER BY s.name, l.name, c.name
");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=locations-export-' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['State', 'State Code', 'Region', 'LGA', 'City']);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['state_name'],
        $r['state_code'],
        $r['region'],
        $r['lga_name'] ?? '',
        $r['city_name'] ?? '',
    ]);
}

fclose($out);
exit;