<?php
/**
 * Notification and messaging functions
 */

/**
 * Send email notification
 */
function sendEmail($to, $subject, $message, $headers = null) {
    if ($headers === null) {
        $headers = "From: no-reply@greenagric.ng\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    // In production, use a proper email service like PHPMailer
    // For now, we'll log the email
    error_log("Email to {$to}: {$subject}");
    return true; // Simulate success
}

/**
 * Send SMS notification (placeholder for SMS integration)
 */
function sendSMS($phone, $message) {
    // Integrate with Nigerian SMS service like Termii, Africa's Talking, etc.
    error_log("SMS to {$phone}: {$message}");
    return true; // Simulate success
}

/**
 * Send notification to user
 */
function notifyUser($user_id, $title, $message, $type = 'info') {
    $db = new Database();
    
    // Store notification in database
    $db->query(
        "INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, ?, FALSE)",
        [$user_id, $title, $message, $type]
    );
    
    // Get user details for email/SMS
    $user = $db->fetchOne("SELECT email, phone FROM users WHERE id = ?", [$user_id]);
    
    if ($user) {
        // Send email for important notifications
        if ($type === 'important') {
            sendEmail($user['email'], $title, $message);
        }
        
        // Send SMS for critical notifications
        if ($type === 'critical') {
            sendSMS($user['phone'], $title . ': ' . strip_tags($message));
        }
    }
    
    return true;
}

/**
 * Send product approval notification
 */
function notifyProductApproval($product_id, $approved = true) {
    $db = new Database();
    
    $product = $db->fetchOne(
        "SELECT p.name, u.id as user_id, u.email, u.first_name 
         FROM products p 
         JOIN users u ON p.seller_id = u.id 
         WHERE p.id = ?",
        [$product_id]
    );
    
    if ($product) {
        $status = $approved ? 'approved' : 'rejected';
        $title = "Product {$status}";
        $message = "Your product '{$product['name']}' has been {$status}.";
        
        notifyUser($product['user_id'], $title, $message, $approved ? 'success' : 'warning');
        
        // Send email
        $email_message = "
            <h3>Product {$status}</h3>
            <p>Hello {$product['first_name']},</p>
            <p>Your product <strong>{$product['name']}</strong> has been {$status}.</p>
            <p>Thank you for using Green Agric.</p>
        ";
        
        sendEmail($product['email'], $title, $email_message);
    }
}

/**
 * Send order confirmation
 */
function notifyOrderConfirmation($order_id) {
    $db = new Database();
    
    $order = $db->fetchOne(
        "SELECT o.order_number, o.total_amount, u.id as user_id, u.email, u.first_name 
         FROM orders o 
         JOIN users u ON o.buyer_id = u.id 
         WHERE o.id = ?",
        [$order_id]
    );
    
    if ($order) {
        $title = "Order Confirmation - {$order['order_number']}";
        $message = "Your order #{$order['order_number']} has been confirmed. Total: " . formatCurrency($order['total_amount']);
        
        notifyUser($order['user_id'], $title, $message, 'success');
        
        // Send email
        $email_message = "
            <h3>Order Confirmed</h3>
            <p>Hello {$order['first_name']},</p>
            <p>Your order <strong>#{$order['order_number']}</strong> has been confirmed.</p>
            <p><strong>Total Amount:</strong> " . formatCurrency($order['total_amount']) . "</p>
            <p>You can track your order from your dashboard.</p>
            <p>Thank you for shopping with Green Agric!</p>
        ";
        
        sendEmail($order['email'], $title, $email_message);
    }
}

/**
 * Send seller order notification
 */
function notifySellerNewOrder($order_item_id) {
    $db = new Database();
    
    $order_item = $db->fetchOne(
        "SELECT oi.*, o.order_number, u.id as seller_id, u.email, u.first_name, p.name as product_name 
         FROM order_items oi 
         JOIN orders o ON oi.order_id = o.id 
         JOIN users u ON oi.seller_id = u.id 
         JOIN products p ON oi.product_id = p.id 
         WHERE oi.id = ?",
        [$order_item_id]
    );
    
    if ($order_item) {
        $title = "New Order - {$order_item['product_name']}";
        $message = "You have a new order for {$order_item['product_name']} from order #{$order_item['order_number']}";
        
        notifyUser($order_item['seller_id'], $title, $message, 'info');
        
        // Send email to seller
        $email_message = "
            <h3>New Order Received</h3>
            <p>Hello {$order_item['first_name']},</p>
            <p>You have received a new order for your product:</p>
            <ul>
                <li><strong>Product:</strong> {$order_item['product_name']}</li>
                <li><strong>Order Number:</strong> #{$order_item['order_number']}</li>
                <li><strong>Quantity:</strong> {$order_item['quantity']}</li>
                <li><strong>Amount:</strong> " . formatCurrency($order_item['item_total']) . "</li>
            </ul>
            <p>Please process the order promptly.</p>
        ";
        
        sendEmail($order_item['email'], $title, $email_message);
    }
}

/**
 * Get unread notifications for user
 */
function getUnreadNotifications($user_id) {
    $db = new Database();
    return $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE user_id = ? AND is_read = FALSE 
         ORDER BY created_at DESC 
         LIMIT 10",
        [$user_id]
    );
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notification_id, $user_id) {
    $db = new Database();
    return $db->query(
        "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?",
        [$notification_id, $user_id]
    );
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead($user_id) {
    $db = new Database();
    return $db->query(
        "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE",
        [$user_id]
    );
}