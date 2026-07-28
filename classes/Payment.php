<?php
class Payment {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function initialize($orderId, $email, $amount) {
        require_once '../config/paystack.php';
        
        $reference = 'AGRIPAY_' . time() . uniqid();
        
        // Record payment attempt (payments has no order_id column - the
        // relationship goes orders.payment_id -> payments.id, since one
        // payment can cover more than one order in a multi-seller checkout).
        $paymentId = $this->db->insert('payments', [
            'paystack_reference' => $reference,
            'amount' => $amount,
            'customer_email' => $email,
            'status' => 'pending'
        ]);

        $this->db->update(
            'orders',
            ['payment_id' => $paymentId],
            'id = ?',
            [$orderId]
        );

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
            $payment = $this->db->fetchOne(
                "SELECT id FROM payments WHERE paystack_reference = ?",
                [$reference]
            );

            if (!$payment) {
                return false;
            }

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

            // A single payment can cover more than one order (multi-seller
            // checkout splits the cart into one order per seller, all
            // sharing one payment), so update every order linked to it.
            $this->db->update(
                'orders',
                [
                    'payment_status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s')
                ],
                'payment_id = ?',
                [$payment['id']]
            );

            return true;
        }
        
        return false;
    }

    /**
     * Returns the payment linked to this order, wrapped in an array for
     * backward compatibility with earlier callers of this method
     * (an order has at most one linked payment; a payment may cover
     * more than one order, but this only looks up this order's own one).
     */
    public function getOrderPayments($orderId) {
        $order = $this->db->fetchOne("SELECT payment_id FROM orders WHERE id = ?", [$orderId]);

        if (empty($order['payment_id'])) {
            return [];
        }

        $payment = $this->db->fetchOne(
            "SELECT * FROM payments WHERE id = ?",
            [$order['payment_id']]
        );

        return $payment ? [$payment] : [];
    }
}