<?php
require_once '../classes/Database.php';
require_once '../config/paystack.php';

// Verify webhook signature (for production)
$secret = PaystackConfig::SECRET_KEY;
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Get webhook payload
$input = @file_get_contents("php://input");
$payload = json_decode($input, true);

if (!$payload) {
    http_response_code(400);
    exit('Invalid payload');
}

$db = new Database();

// Verify the event is from Paystack (in production, verify signature)
$event = $payload['event'] ?? '';
$data = $payload['data'] ?? [];

switch ($event) {
    case 'charge.success':
        // Handle successful payment
        $reference = $data['reference'] ?? '';
        
        // Verify transaction (extra security)
        $verification = PaystackAPI::verifyTransaction($reference);
        
        if ($verification['status'] && $verification['data']['status'] === 'success') {
            $payment_data = $verification['data'];
            
            // Update payment
            $db->update('payments', [
                'status' => 'success',
                'paystack_authorization_code' => $payment_data['authorization']['authorization_code'] ?? null,
                'payment_method' => $payment_data['channel'] ?? 'card',
                'paystack_response' => json_encode($payment_data),
                'paid_at' => date('Y-m-d H:i:s')
            ], 'paystack_reference = ?', [$reference]);
            
            // Get order ID from payment
            $payment = $db->fetchOne(
                "SELECT order_id FROM payments WHERE paystack_reference = ?",
                [$reference]
            );
            
            if ($payment) {
                // Update order
                $db->update('orders', [
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'paid_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$payment['order_id']]);
                
                // Log success
                error_log("Webhook: Payment {$reference} processed successfully");
            }
        }
        break;
        
    case 'transfer.success':
        // Handle successful seller payout
        $transfer_reference = $data['transfer_code'] ?? '';
        
        $db->update('seller_payouts', [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s')
        ], 'paystack_transfer_reference = ?', [$transfer_reference]);
        
        error_log("Webhook: Transfer {$transfer_reference} completed");
        break;
        
    default:
        // Log other events
        error_log("Webhook: Unhandled event {$event}");
}

http_response_code(200);
echo 'Webhook processed';
?>