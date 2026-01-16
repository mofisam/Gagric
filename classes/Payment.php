<?php
class Payment {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function initialize($orderId, $email, $amount) {
        require_once '../config/paystack.php';
        
        $reference = 'AGRIPAY_' . time() . uniqid();
        
        // Record payment attempt
        $paymentId = $this->db->insert('payments', [
            'order_id' => $orderId,
            'paystack_reference' => $reference,
            'amount' => $amount,
            'customer_email' => $email,
            'status' => 'pending'
        ]);

        // Initialize with Paystack
        $response = PaystackAPI::initializeTransaction($email, $amount, $reference, [
            'order_id' => $orderId,
            'payment_id' => $paymentId
        ]);

        if ($response['status']) {
            return [
                'authorization_url' => $response['data']['authorization_url'],
                'reference' => $reference
            ];
        }
        
        throw new Exception("Payment initialization failed");
    }

    public function verify($reference) {
        require_once '../config/paystack.php';
        
        $response = PaystackAPI::verifyTransaction($reference);
        
        if ($response['status'] && $response['data']['status'] === 'success') {
            $this->db->update(
                'payments',
                [
                    'status' => 'success',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'paystack_response' => json_encode($response)
                ],
                'paystack_reference = ?',
                [$reference]
            );

            // Update order status
            $payment = $this->db->fetchOne(
                "SELECT order_id FROM payments WHERE paystack_reference = ?",
                [$reference]
            );
            
            $this->db->update(
                'orders',
                [
                    'payment_status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$payment['order_id']]
            );

            return true;
        }
        
        return false;
    }

    public function getOrderPayments($orderId) {
        return $this->db->fetchAll(
            "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC",
            [$orderId]
        );
    }
}
?>