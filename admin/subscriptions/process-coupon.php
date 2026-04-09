<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicable_plans = isset($_POST['applicable_plans']) ? array_filter($_POST['applicable_plans']) : null;
    
    $data = [
        'code' => strtoupper(sanitizeInput($_POST['code'])),
        'description' => sanitizeInput($_POST['description']),
        'discount_type' => $_POST['discount_type'],
        'discount_value' => (float)$_POST['discount_value'],
        'applicable_plans' => $applicable_plans ? json_encode($applicable_plans) : null,
        'min_subscription_amount' => !empty($_POST['min_subscription_amount']) ? (float)$_POST['min_subscription_amount'] : null,
        'max_discount_amount' => !empty($_POST['max_discount_amount']) ? (float)$_POST['max_discount_amount'] : null,
        'usage_limit' => !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null,
        'per_user_limit' => (int)$_POST['per_user_limit'],
        'valid_from' => $_POST['valid_from'],
        'valid_until' => $_POST['valid_until'],
        'created_by' => $_SESSION['user_id']
    ];
    
    $result = $db->insert('coupon_codes', $data);
    
    if ($result) {
        setFlashMessage('Coupon code created successfully', 'success');
    } else {
        setFlashMessage('Failed to create coupon code', 'danger');
    }
    
    header('Location: manage-plans.php#coupons');
    exit;
}
?>