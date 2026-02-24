<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if using Composer or manual installation
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Manual installation path
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
}

require_once __DIR__ . '/../config/constants.php';

class Mailer {
    private $mail;
    private $debug_mode = false;
    private $last_error = '';
    
    // Changed default debug to false
    public function __construct($debug = false) {
        $this->debug_mode = $debug;
        $this->mail = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = SMTP_AUTH; 
            $this->mail->Username   = SMTP_USERNAME;
            $this->mail->Password   = SMTP_PASSWORD;
            $this->mail->SMTPSecure = SMTP_SECURE;
            $this->mail->Port       = SMTP_PORT;
            
            // Add timeout settings (helps with "try again later" errors)
            $this->mail->Timeout = 30; // seconds
            $this->mail->SMTPKeepAlive = true;
            
            // Enable debug if needed
            if ($this->debug_mode) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mail->Debugoutput = function($str, $level) {
                    error_log("SMTP Debug level $level: $str");
                };
            }
            
            // Sender settings
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->isHTML(true);
            
            // Set charset
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            
            // Add reply-to
            $this->mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Mailer Initialization Error: " . $e->getMessage());
            // Don't throw exception, just log it
        }
    }
    
    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    /**
     * Test SMTP connection
     */
    public function testConnection() {
        try {
            $this->mail->smtpConnect();
            $this->mail->smtpClose();
            return true;
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Send contact form email to admin
     */
    public function sendContactToAdmin($contactData) {
        try {
            $this->resetMailer();
            
            // Use constant for admin email
            $this->mail->addAddress(ADMIN_EMAIL, ADMIN_NAME);
            
            // Subject
            $this->mail->Subject = EMAIL_SUBJECT_CONTACT_ADMIN . ': ' . $contactData['subject'];
            
            // Content
            $content = $this->getContactAdminTemplate($contactData);
            $this->mail->Body = $content;
            
            // Plain text alternative
            $this->mail->AltBody = $this->getPlainTextContent($contactData['message']);
            
            // Send email
            $result = $this->mail->send();
            
            if ($this->debug_mode) {
                error_log("Admin email sent: " . ($result ? 'Success' : 'Failed'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send admin email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send confirmation email to user
     */
    public function sendContactToUser($contactData) {
        try {
            $this->resetMailer();
            
            // Validate email
            if (!filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: " . $contactData['email']);
            }
            
            $this->mail->addAddress($contactData['email'], $contactData['full_name']);
            
            // Subject
            $this->mail->Subject = EMAIL_SUBJECT_CONTACT_USER;
            
            // Content
            $content = $this->getContactUserTemplate($contactData);
            $this->mail->Body = $content;
            
            // Plain text alternative
            $this->mail->AltBody = "Thank you for contacting us. We'll respond within 24 hours.";
            
            // Send email
            $result = $this->mail->send();
            
            if ($this->debug_mode) {
                error_log("User email sent: " . ($result ? 'Success' : 'Failed'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send user email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation($orderData, $userData) {
        try {
            $this->resetMailer();
            
            $this->mail->addAddress($userData['email'], $userData['full_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_ORDER_CONFIRMATION . ' - #' . $orderData['order_number'];
            
            $content = $this->getOrderConfirmationTemplate($orderData, $userData);
            $this->mail->Body = $content;
            $this->mail->AltBody = "Your order #{$orderData['order_number']} has been confirmed.";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send order confirmation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($userEmail, $userName, $resetLink) {
        try {
            $this->resetMailer();
            
            $this->mail->addAddress($userEmail, $userName);
            
            $this->mail->Subject = EMAIL_SUBJECT_PASSWORD_RESET;
            
            $content = $this->getPasswordResetTemplate($userName, $resetLink);
            $this->mail->Body = $content;
            $this->mail->AltBody = "Click here to reset your password: $resetLink";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send password reset: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send payment success email
     */
    public function sendPaymentSuccess($orderData, $userData, $paymentData) {
        try {
            $this->resetMailer();
            
            $this->mail->addAddress($userData['email'], $userData['full_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_PAYMENT_SUCCESS . ' - Order #' . $orderData['order_number'];
            
            $content = $this->getPaymentSuccessTemplate($orderData, $userData, $paymentData);
            $this->mail->Body = $content;
            $this->mail->AltBody = "Payment of ₦" . number_format($paymentData['amount'], 2) . " for order #{$orderData['order_number']} was successful.";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send payment success email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send new order notification to seller
     */
    public function sendSellerNewOrder($orderData, $sellerData, $orderItems) {
        try {
            $this->resetMailer();
            
            $this->mail->addAddress($sellerData['email'], $sellerData['business_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_SELLER_NEW_ORDER . ' - #' . $orderData['order_number'];
            
            $content = $this->getSellerNewOrderTemplate($orderData, $sellerData, $orderItems);
            $this->mail->Body = $content;
            $this->mail->AltBody = "You have a new order #{$orderData['order_number']}. Please process it soon.";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send seller notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email verification link
     */
    public function sendEmailVerification($userEmail, $userName, $verificationLink) {
        try {
            $this->resetMailer();
            
            $this->mail->addAddress($userEmail, $userName);
            
            $this->mail->Subject = 'Verify Your Email - Green Agric LTD';
            
            $content = $this->getEmailVerificationTemplate($userName, $verificationLink);
            $this->mail->Body = $content;
            $this->mail->AltBody = "Verify your email: $verificationLink";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send email verification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset mailer for new email
     */
    private function resetMailer() {
        $this->mail->clearAddresses();
        $this->mail->clearCCs();
        $this->mail->clearBCCs();
        $this->mail->clearReplyTos();
        $this->mail->clearAttachments();
        $this->mail->clearCustomHeaders();
    }
    
    /**
     * Template for admin contact email
     */
    private function getContactAdminTemplate($contactData) {
        $contact_types = [
            'general' => 'General Inquiry',
            'order' => 'Order Related',
            'product' => 'Product Inquiry',
            'seller' => 'Seller Support',
            'payment' => 'Payment Issue',
            'delivery' => 'Delivery Issue',
            'technical' => 'Technical Support',
            'partnership' => 'Partnership/Business',
            'other' => 'Other'
        ];
        
        $type = $contact_types[$contactData['contact_type']] ?? 'General Inquiry';
        $userStatus = isset($contactData['user_id']) && $contactData['user_id'] ? 'Registered User (ID: ' . $contactData['user_id'] . ')' : 'Guest Visitor';
        
        $content = '
        <h2>📨 New Contact Form Submission</h2>
        <p>A new contact message has been received from the Green Agric LTD website.</p>
        
        <div class="message-box">
            <h3>👤 Contact Details</h3>
            <p><strong>Name:</strong> ' . htmlspecialchars($contactData['full_name'] ?? '') . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($contactData['email'] ?? '') . '</p>
            <p><strong>Phone:</strong> ' . ($contactData['phone'] ? htmlspecialchars($contactData['phone']) : 'Not provided') . '</p>
            <p><strong>Contact Type:</strong> ' . $type . '</p>
            <p><strong>User Status:</strong> ' . $userStatus . '</p>
        </div>
        
        <div class="message-box">
            <h3>💬 Message Details</h3>
            <p><strong>Subject:</strong> ' . htmlspecialchars($contactData['subject'] ?? '') . '</p>
            <p><strong>Message:</strong></p>
            <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                ' . nl2br(htmlspecialchars($contactData['message'] ?? '')) . '
            </div>
        </div>
        
        <div class="info-box">
            <h3>📊 Submission Details</h3>
            <p><strong>Submitted:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
            <p><strong>IP Address:</strong> ' . htmlspecialchars($contactData['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'N/A') . '</p>
        </div>
        
        <div class="highlight">
            <h3>🚀 Action Required</h3>
            <p>Please respond to this inquiry within <strong>24 hours</strong>.</p>
            <p>You can reply directly to: <strong>' . htmlspecialchars($contactData['email'] ?? '') . '</strong></p>
        </div>
        
        <p style="text-align: center; margin-top: 30px;">
            <a href="' . BASE_URL . '/admin/contacts/manage-contacts.php" class="btn-primary">View in Admin Panel</a>
        </p>
        ';
        
        return $this->renderTemplate($content, EMAIL_SUBJECT_CONTACT_ADMIN);
    }
    
    /**
     * Template for user contact confirmation
     */
    private function getContactUserTemplate($contactData) {
        $reference_id = 'CONTACT-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $content = '
        <h2>🙏 Thank You for Contacting Us!</h2>
        <p>Dear <strong>' . htmlspecialchars($contactData['full_name'] ?? '') . '</strong>,</p>
        
        <p>We have successfully received your message and our support team will review it shortly.</p>
        
        <div class="message-box">
            <h3>📋 Your Message Summary</h3>
            <p><strong>Reference ID:</strong> ' . $reference_id . '</p>
            <p><strong>Subject:</strong> ' . htmlspecialchars($contactData['subject'] ?? '') . '</p>
            <p><strong>Submitted:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
            <p><strong>Your Message:</strong></p>
            <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                ' . nl2br(htmlspecialchars($contactData['message'] ?? '')) . '
            </div>
        </div>
        
        <div class="info-box">
            <h3>⏱️ What Happens Next?</h3>
            <ol style="padding-left: 20px;">
                <li><strong>Immediately:</strong> Your message is logged in our system</li>
                <li><strong>Within 1 hour:</strong> Assigned to a support agent</li>
                <li><strong>Within 24 hours:</strong> You\'ll receive a detailed response</li>
                <li><strong>If urgent:</strong> Call +234 800 123 4567 (Mon-Sat, 8AM-6PM)</li>
            </ol>
        </div>
        
        <div class="highlight">
            <h3>💡 Need Immediate Help?</h3>
            <p>For urgent matters, you can:</p>
            <ul style="padding-left: 20px;">
                <li>Call our support line: <strong>+234 800 123 4567</strong></li>
                <li>Chat with us on WhatsApp: <strong>+234 800 123 4567</strong></li>
                <li>Visit our FAQ: <a href="' . BASE_URL . '/faq.php">' . BASE_URL . '/faq.php</a></li>
            </ul>
        </div>
        
        <p>If you need to add more information to your inquiry, simply reply to this email.</p>
        
        <p style="margin-top: 30px;">
            Best regards,<br>
            <strong>The Green Agric LTD Support Team</strong><br>
            <em>Connecting Nigerian Farmers with Buyers</em>
        </p>
        ';
        
        return $this->renderTemplate($content, EMAIL_SUBJECT_CONTACT_USER);
    }
    
    /**
     * Template for order confirmation
     */
    private function getOrderConfirmationTemplate($orderData, $userData) {
        $content = '
        <h2>✅ Order Confirmation</h2>
        <p>Dear ' . htmlspecialchars($userData['full_name'] ?? '') . ',</p>
        
        <p>Thank you for your order! We have received your order and it is being processed.</p>
        
        <div class="message-box">
            <h3>📦 Order Details</h3>
            <p><strong>Order Number:</strong> ' . ($orderData['order_number'] ?? 'N/A') . '</p>
            <p><strong>Order Date:</strong> ' . (isset($orderData['created_at']) ? date('F j, Y \a\t g:i A', strtotime($orderData['created_at'])) : date('F j, Y \a\t g:i A')) . '</p>
            <p><strong>Total Amount:</strong> ₦' . number_format($orderData['total_amount'] ?? 0, 2) . '</p>
            <p><strong>Status:</strong> <span style="color: #198754; font-weight: bold;">Confirmed</span></p>
        </div>
        
        <p>You can track your order from your account dashboard:</p>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="' . BASE_URL . '/buyer/orders/track-order.php?id=' . ($orderData['id'] ?? '') . '" class="btn-primary">Track Your Order</a>
        </p>
        
        <div class="info-box">
            <h3>ℹ️ Next Steps</h3>
            <ul style="padding-left: 20px;">
                <li>Seller will prepare your items (1-2 business days)</li>
                <li>You\'ll receive shipping confirmation</li>
                <li>Track delivery in real-time</li>
                <li>Review products after delivery</li>
            </ul>
        </div>
        
        <p>Best regards,<br>
        <strong>The Green Agric LTD Team</strong></p>
        ';
        
        return $this->renderTemplate($content, EMAIL_SUBJECT_ORDER_CONFIRMATION);
    }
    
    /**
     * Template for password reset
     */
    private function getPasswordResetTemplate($userName, $resetLink) {
        $expiry_time = date('g:i A', strtotime('+1 hour'));
        
        $content = '
        <h2>🔐 Password Reset Request</h2>
        <p>Dear ' . htmlspecialchars($userName) . ',</p>
        
        <p>We received a request to reset your password for your Green Agric LTD account.</p>
        
        <div class="message-box">
            <h3>Reset Your Password</h3>
            <p>Click the button below to reset your password. This link will expire at ' . $expiry_time . ':</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $resetLink . '" class="btn-primary" style="font-size: 16px; padding: 12px 30px;">Reset Password Now</a>
            </p>
            <p><strong>Or copy this link:</strong><br>
            <small>' . $resetLink . '</small></p>
        </div>
        
        <div class="highlight">
            <h3>⚠️ Important Security Information</h3>
            <ul style="padding-left: 20px;">
                <li>This link expires in <strong>1 hour</strong> (' . $expiry_time . ')</li>
                <li>If you didn\'t request this reset, please ignore this email</li>
                <li>Never share your password or this link with anyone</li>
                <li>Our team will never ask for your password</li>
            </ul>
        </div>
        
        <p>If you continue to have issues, contact our support team.</p>
        
        <p>Best regards,<br>
        <strong>Green Agric LTD Security Team</strong></p>
        ';
        
        return $this->renderTemplate($content, EMAIL_SUBJECT_PASSWORD_RESET);
    }
    
    /**
     * Template for payment success
     */
    private function getPaymentSuccessTemplate($orderData, $userData, $paymentData) {
        $content = '
        <h2>💰 Payment Successful!</h2>
        <p>Dear ' . htmlspecialchars($userData['full_name'] ?? '') . ',</p>
        
        <p>Your payment has been successfully processed. Thank you for your purchase!</p>
        
        <div class="message-box">
            <h3>🧾 Payment Details</h3>
            <p><strong>Order Number:</strong> ' . ($orderData['order_number'] ?? 'N/A') . '</p>
            <p><strong>Amount Paid:</strong> ₦' . number_format($paymentData['amount'] ?? 0, 2) . '</p>
            <p><strong>Payment Reference:</strong> ' . ($paymentData['reference'] ?? 'N/A') . '</p>
            <p><strong>Payment Date:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
            <p><strong>Payment Method:</strong> ' . ($paymentData['method'] ?? 'Card') . '</p>
        </div>
        
        <p>Your order is now being processed by the seller.</p>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="' . BASE_URL . '/buyer/orders/view-order.php?id=' . ($orderData['id'] ?? '') . '" class="btn-primary">View Order Details</a>
        </p>
        
        <p>Best regards,<br>
        <strong>The Green Agric LTD Team</strong></p>
        ';
        
        return $this->renderTemplate($content, EMAIL_SUBJECT_PAYMENT_SUCCESS);
    }
    
    /**
     * Template for seller new order
     */
    private function getSellerNewOrderTemplate($orderData, $sellerData, $orderItems) {
        $itemsList = '';
        if (!empty($orderItems) && is_array($orderItems)) {
            $itemsList = '<table><tr><th>Product</th><th>Qty</th><th>Price</th></tr>';
            foreach ($orderItems as $item) {
                $itemsList .= '<tr>';
                $itemsList .= '<td>' . htmlspecialchars($item['product_name'] ?? 'Product') . '</td>';
                $itemsList .= '<td>' . ($item['quantity'] ?? 0) . '</td>';
                $itemsList .= '<td>₦' . number_format($item['price'] ?? 0, 2) . '</td>';
                $itemsList .= '</tr>';
            }
            $itemsList .= '</table>';
        }
        
        $content = '
        <h2>🛍️ New Order Received!</h2>
        <p>Dear ' . htmlspecialchars($sellerData['business_name'] ?? 'Seller') . ',</p>
        
        <p>Congratulations! You have received a new order.</p>
        
        <div class="message-box">
            <h3>📦 Order Summary</h3>
            <p><strong>Order Number:</strong> ' . ($orderData['order_number'] ?? 'N/A') . '</p>
            <p><strong>Order Date:</strong> ' . (isset($orderData['created_at']) ? date('F j, Y \a\t g:i A', strtotime($orderData['created_at'])) : date('F j, Y \a\t g:i A')) . '</p>
            <p><strong>Total Amount:</strong> ₦' . number_format($orderData['total_amount'] ?? 0, 2) . '</p>
        </div>
        
        <h3>📋 Items Ordered:</h3>
        ' . $itemsList . '
        
        <div class="highlight">
            <h3>⚠️ Action Required</h3>
            <p>Please process this order within <strong>24 hours</strong>.</p>
            <p>Log in to your seller dashboard to confirm and prepare the items for shipping.</p>
        </div>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="' . BASE_URL . '/seller/orders/process-order.php?id=' . ($orderData['id'] ?? '') . '" class="btn-primary">Process Order Now</a>
        </p>
        
        <p>Best regards,<br>
        <strong>The Green Agric LTD Team</strong></p>
        ';
        
        return $this->renderTemplate($content, EMAIL_SUBJECT_SELLER_NEW_ORDER);
    }
    
    /**
     * Template for email verification
     */
    private function getEmailVerificationTemplate($userName, $verificationLink) {
        $content = '
        <h2>🔐 Verify Your Email Address</h2>
        <p>Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
        
        <p>Thank you for registering with Green Agric LTD! Please verify your email address to complete your registration.</p>
        
        <div class="message-box" style="text-align: center;">
            <h3>Verify Your Email</h3>
            <p>Click the button below to verify your email address:</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $verificationLink . '" class="btn-primary" style="font-size: 16px; padding: 12px 30px;">Verify Email Address</a>
            </p>
            <p><strong>Or copy this link:</strong><br>
            <small>' . $verificationLink . '</small></p>
        </div>
        
        <div class="highlight">
            <h3>⚠️ Important Information</h3>
            <ul style="padding-left: 20px;">
                <li>This verification link expires in <strong>24 hours</strong></li>
                <li>If you didn\'t create an account, please ignore this email</li>
                <li>Verify your email to access all features</li>
            </ul>
        </div>
        
        <p>Once verified, you can:</p>
        <ul style="padding-left: 20px;">
            <li>Start buying agricultural products</li>
            <li>List your products for sale (as a seller)</li>
            <li>Track your orders</li>
            <li>Receive order notifications</li>
        </ul>
        
        <p>Best regards,<br>
        <strong>The Green Agric LTD Team</strong></p>
        ';
        
        return $this->renderTemplate($content, 'Verify Your Email');
    }
    
    /**
     * Render email template with placeholders
     */
    private function renderTemplate($content, $subject) {
        return str_replace(
            ['{content}', '{subject}', '{unsubscribe_link}', '{year}'],
            [$content, $subject, BASE_URL . '/unsubscribe?email=placeholder', date('Y')],
            EMAIL_TEMPLATE_HTML
        );
    }
    
    /**
     * Convert HTML to plain text
     */
    private function getPlainTextContent($text) {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Replace common HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Decode any escaped quotes
        $text = stripslashes($text);
        
        return trim($text);
    }
}
?>