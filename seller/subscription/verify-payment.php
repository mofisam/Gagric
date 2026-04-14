<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';

header('Content-Type: application/json');

$db = new Database();
$seller_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$reference = $data['reference'] ?? '';
$plan_id = $data['plan_id'] ?? '';
$coupon_id = $data['coupon_id'] ?? '';

$paystack_secret_key = PAYSTACK_SECRET_KEY;

// Verify with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $paystack_secret_key",
        "Cache-Control: no-cache"
    ]
]);

$response = curl_exec($curl);
curl_close($curl);

$verification = json_decode($response, true);

if ($verification && $verification['data']['status'] == 'success') {
    $amount = $verification['data']['amount'] / 100;
    
    // Get plan details
    $plan = $db->fetchOne("SELECT * FROM subscription_plans WHERE id = ?", [$plan_id]);
    
    if ($plan) {
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        
        // Deactivate existing subscription
        $db->update('seller_subscriptions', [
            'is_active' => 0,
            'cancelled_at' => date('Y-m-d H:i:s')
        ], 'seller_id = ? AND is_active = 1', [$seller_id]);
        
        // Create new subscription
        $subscription_id = $db->insert('seller_subscriptions', [
            'seller_id' => $seller_id,
            'plan_id' => $plan_id,
            'subscription_type' => 'paid',
            'start_date' => $start_date,
            'end_date' => $end_date,
            'is_active' => 1,
            'auto_renew' => 0,
            'payment_status' => 'paid',
            'payment_id' => $reference,
            'coupon_code_id' => $coupon_id ?: null,
            'amount_paid' => $amount,
            'features_snapshot' => $plan['features']
        ]);
        
        // Record payment
        $db->insert('subscription_payments', [
            'subscription_id' => $subscription_id,
            'seller_id' => $seller_id,
            'amount' => $amount,
            'payment_method' => 'card',
            'transaction_id' => $reference,
            'payment_status' => 'completed',
            'payment_date' => date('Y-m-d H:i:s')
        ]);
        
        // Update coupon usage
        if ($coupon_id) {
            $db->query("UPDATE coupon_codes SET used_count = used_count + 1 WHERE id = ?", [$coupon_id]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false]);
?>