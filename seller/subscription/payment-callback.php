<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$reference = $_GET['reference'] ?? '';
$status = $_GET['status'] ?? '';

// Paystack configuration
$paystack_secret_key = PAYSTACK_SECRET_KEY;

function verifyPaystackPayment($reference, $secret_key) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $secret_key",
            "Cache-Control: no-cache"
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return false;
    }
    
    return json_decode($response, true);
}

if ($reference && $status == 'success') {
    // Verify payment with Paystack
    $verification = verifyPaystackPayment($reference, $paystack_secret_key);
    
    if ($verification && $verification['data']['status'] == 'success') {
        $amount = $verification['data']['amount'] / 100;
        $metadata = $verification['data']['metadata']['custom_fields'] ?? [];
        
        // Extract metadata
        $plan_id = null;
        $coupon_id = null;
        foreach ($metadata as $field) {
            if ($field['variable_name'] == 'plan_id') {
                $plan_id = $field['value'];
            }
            if ($field['variable_name'] == 'coupon_id') {
                $coupon_id = $field['value'];
            }
        }
        
        // Get plan details
        $plan = $db->fetchOne("SELECT * FROM subscription_plans WHERE id = ?", [$plan_id]);
        
        if ($plan) {
            // Calculate subscription dates
            $start_date = date('Y-m-d H:i:s');
            $end_date = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
            
            // Check if seller already has an active subscription
            $existing = $db->fetchOne("SELECT id FROM seller_subscriptions WHERE seller_id = ? AND is_active = 1", [$seller_id]);
            
            if ($existing) {
                // Deactivate current subscription
                $db->update('seller_subscriptions', [
                    'is_active' => 0,
                    'cancelled_at' => date('Y-m-d H:i:s'),
                    'cancellation_reason' => 'Upgraded to new plan'
                ], 'seller_id = ? AND is_active = 1', [$seller_id]);
            }
            
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
                'discount_amount' => 0, // Calculate if needed
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
                'payment_date' => date('Y-m-d H:i:s'),
                'payment_details' => json_encode($verification['data'])
            ]);
            
            // Update coupon usage count if applicable
            if ($coupon_id) {
                $db->query("UPDATE coupon_codes SET used_count = used_count + 1 WHERE id = ?", [$coupon_id]);
            }
            
            // Set success message
            setFlashMessage('Payment successful! Your subscription is now active.', 'success');
            
            // Redirect to subscription management
            header('Location: manage.php');
            exit;
        }
    }
}

// If we get here, something went wrong
setFlashMessage('Payment verification failed. Please contact support.', 'danger');
header('Location: upgrade.php');
exit;
?>