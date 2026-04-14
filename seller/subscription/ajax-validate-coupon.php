<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';

header('Content-Type: application/json');

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$coupon_code = strtoupper(trim($input['coupon_code'] ?? ''));
$plan_id = (int)($input['plan_id'] ?? 0);

if (empty($coupon_code) || $plan_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Please provide coupon code and plan.'
    ]);
    exit;
}

// Get plan price
$plan = $db->fetchOne("SELECT id, price FROM subscription_plans WHERE id = ?", [$plan_id]);

if (!$plan) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid plan selected.'
    ]);
    exit;
}

// Validate coupon
$coupon = $db->fetchOne("
    SELECT * FROM coupon_codes 
    WHERE code = ? 
    AND is_active = 1 
    AND valid_from <= NOW() 
    AND valid_until >= NOW()
", [$coupon_code]);

if (!$coupon) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired coupon code.'
    ]);
    exit;
}

// Check usage limit
if ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
    echo json_encode([
        'success' => false,
        'message' => 'This coupon has reached its usage limit.'
    ]);
    exit;
}

// Check if user has already used this coupon
$user_usage = $db->fetchOne("
    SELECT COUNT(*) as count FROM seller_subscriptions 
    WHERE seller_id = ? AND coupon_code_id = ?
", [$seller_id, $coupon['id']]);

if ($user_usage && $user_usage['count'] >= $coupon['per_user_limit']) {
    echo json_encode([
        'success' => false,
        'message' => 'You have already used this coupon the maximum number of times.'
    ]);
    exit;
}

// Check applicable plans
$applicable_plans = json_decode($coupon['applicable_plans'], true);
if (!empty($applicable_plans) && !in_array($plan_id, $applicable_plans)) {
    echo json_encode([
        'success' => false,
        'message' => 'This coupon is not applicable to the selected plan.'
    ]);
    exit;
}

// Calculate discount
$plan_price = (float)$plan['price'];
$discount_amount = 0;

if ($coupon['discount_type'] == 'percentage') {
    $discount_amount = ($plan_price * $coupon['discount_value'] / 100);
    if ($coupon['max_discount_amount'] && $discount_amount > $coupon['max_discount_amount']) {
        $discount_amount = $coupon['max_discount_amount'];
    }
} else {
    $discount_amount = (float)$coupon['discount_value'];
    if ($discount_amount > $plan_price) {
        $discount_amount = $plan_price;
    }
}

$final_price = $plan_price - $discount_amount;

echo json_encode([
    'success' => true,
    'message' => sprintf(
        'Coupon applied! You saved ₦%s',
        number_format($discount_amount, 2)
    ),
    'coupon' => [
        'id' => $coupon['id'],
        'code' => $coupon['code'],
        'discount' => $discount_amount,
        'discount_type' => $coupon['discount_type'],
        'discount_value' => $coupon['discount_value'],
        'plan_id' => $plan_id,
        'final_price' => $final_price
    ]
]);
exit;
?>