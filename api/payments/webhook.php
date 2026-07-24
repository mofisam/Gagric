<?php
require_once '../../config/database.php';
require_once '../../config/paystack.php';
require_once '../../classes/Mailer.php';

$db = new Database();
$input = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Verify webhook signature
$computed_signature = hash_hmac('sha512', $input, PaystackConfig::SECRET_KEY);

if ($signature !== $computed_signature) {
    http_response_code(401);
    exit;
}

$event = json_decode($input, true);

if ($event['event'] === 'charge.success') {
    $data = $event['data'];
    $reference = $data['reference'];

    // Find which orders this payment covers, and which of them still have
    // pending items right now - only those need a seller notification.
    // (If the buyer's browser already confirmed this payment via
    // buyer/cart/payment.php, items will already be 'confirmed' and this
    // webhook simply skips sending duplicate emails.)
    $orders_for_reference = $db->fetchAll(
        "SELECT o.id, o.order_number, o.created_at
         FROM orders o
         JOIN payments p ON o.payment_id = p.id
         WHERE p.paystack_reference = ?",
        [$reference]
    );

    $orders_needing_notification = [];
    foreach ($orders_for_reference as $ord) {
        $stillPending = $db->fetchOne(
            "SELECT COUNT(*) as count FROM order_items WHERE order_id = ? AND status = 'pending'",
            [$ord['id']]
        )['count'] ?? 0;

        if ($stillPending > 0) {
            $orders_needing_notification[] = $ord;
        }
    }

    // Update payment, linked orders, and item statuses.
    $stmt = $db->conn->prepare("
        UPDATE payments p 
        JOIN orders o ON o.payment_id = p.id
        SET p.status = 'success', p.paid_at = NOW(), 
            o.payment_status = 'paid',
            o.status = CASE WHEN o.status = 'pending' THEN 'confirmed' ELSE o.status END,
            o.paid_at = NOW()
        WHERE p.paystack_reference = ?
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    
    $stmt = $db->conn->prepare("
        UPDATE order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN payments pm ON o.payment_id = pm.id
        SET oi.status = 'confirmed'
        WHERE pm.paystack_reference = ?
        AND oi.status = 'pending'
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();

    // Notify sellers for whichever orders actually transitioned here.
    // A fresh Mailer/SMTP connection is created per send (see payment.php
    // for why reusing one connection across multiple emails is unreliable).
    if (!empty($orders_needing_notification)) {
        foreach ($orders_needing_notification as $ord) {
            $items_by_seller = $db->fetchAll(
                "SELECT oi.seller_id, oi.product_name, oi.quantity, oi.unit,
                        oi.unit_price as price, oi.item_total as total_price,
                        u.email as seller_email, sp.business_name
                 FROM order_items oi
                 JOIN seller_profiles sp ON oi.seller_id = sp.user_id
                 JOIN users u ON sp.user_id = u.id
                 WHERE oi.order_id = ?",
                [$ord['id']]
            );

            $grouped = [];
            foreach ($items_by_seller as $item) {
                $sid = $item['seller_id'];
                if (!isset($grouped[$sid])) {
                    $grouped[$sid] = [
                        'seller_email' => $item['seller_email'],
                        'business_name' => $item['business_name'],
                        'items' => [],
                    ];
                }
                $grouped[$sid]['items'][] = [
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'price' => $item['price'],
                    'total_price' => $item['total_price'],
                ];
            }

            foreach ($grouped as $sellerGroup) {
                try {
                    $mailer = new Mailer();
                    $mailer->sendSellerNewOrder(
                        [
                            'id' => $ord['id'],
                            'order_number' => $ord['order_number'],
                            'created_at' => $ord['created_at'],
                        ],
                        [
                            'email' => $sellerGroup['seller_email'],
                            'business_name' => $sellerGroup['business_name'],
                        ],
                        $sellerGroup['items']
                    );
                } catch (Exception $e) {
                    error_log("Seller new-order email failed for order {$ord['order_number']}: " . $e->getMessage());
                }
            }
        }
    }
}

if ($event['event'] === 'transfer.success') {
    $data = $event['data'];
    $transfer_code = $data['transfer_code'] ?? '';
    $transfer_reference = $data['reference'] ?? '';

    foreach (array_filter([$transfer_code, $transfer_reference]) as $reference) {
        $stmt = $db->conn->prepare("
            UPDATE seller_payouts
            SET status = 'paid', paid_at = NOW()
            WHERE paystack_transfer_reference = ?
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
    }
}

if (in_array($event['event'], ['transfer.failed', 'transfer.reversed'], true)) {
    $data = $event['data'];
    $transfer_code = $data['transfer_code'] ?? '';
    $transfer_reference = $data['reference'] ?? '';

    foreach (array_filter([$transfer_code, $transfer_reference]) as $reference) {
        $stmt = $db->conn->prepare("
            UPDATE seller_payouts
            SET status = 'failed'
            WHERE paystack_transfer_reference = ?
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
    }
}

http_response_code(200);
?>