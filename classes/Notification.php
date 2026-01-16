<?php
class Notification {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function sendToUser($userId, $title, $message, $type = 'info') {
        // In production, integrate with email/SMS services
        // For now, log to database or file
        
        error_log("Notification to User $userId: $title - $message");
        return true;
    }

    public function sendToSeller($sellerId, $title, $message) {
        return $this->sendToUser($sellerId, $title, $message, 'seller');
    }

    public function sendOrderConfirmation($orderId) {
        $order = $this->db->fetchOne(
            "SELECT o.*, u.email, u.first_name FROM orders o 
             JOIN users u ON o.buyer_id = u.id 
             WHERE o.id = ?",
            [$orderId]
        );

        if ($order) {
            $subject = "Order Confirmation - {$order['order_number']}";
            $message = "Hello {$order['first_name']}, your order has been confirmed.";
            $this->sendToUser($order['buyer_id'], $subject, $message);
        }
    }

    public function sendProductApprovalNotification($productId, $approved = true) {
        $product = $this->db->fetchOne(
            "SELECT p.*, u.email FROM products p 
             JOIN users u ON p.seller_id = u.id 
             WHERE p.id = ?",
            [$productId]
        );

        if ($product) {
            $status = $approved ? 'approved' : 'rejected';
            $subject = "Product $status";
            $message = "Your product '{$product['name']}' has been $status.";
            $this->sendToUser($product['seller_id'], $subject, $message);
        }
    }
}
?>