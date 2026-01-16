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
    
    public function __construct($debug = false) {
        $this->debug_mode = $debug;
        $this->mail = new PHPMailer(true);
        
        try {
            // Enable debug if needed
            if ($this->debug_mode) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = SMTP_USERNAME;
            $this->mail->Password   = SMTP_PASSWORD;
            $this->mail->SMTPSecure = SMTP_SECURE;
            $this->mail->Port       = SMTP_PORT;
            
            // Sender settings
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->isHTML(true);
            
            // Set charset
            $this->mail->CharSet = 'UTF-8';
            
            // Add reply-to
            $this->mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
        } catch (Exception $e) {
            error_log("Mailer Initialization Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send contact form email to admin
     */
    public function sendContactToAdmin($contactData) {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            
            // Recipient (admin)
            $this->mail->addAddress('support@greenagric.ng', 'Green Agric Support');
            
            // Optional: Add CC to other admins
            // $this->mail->addCC('admin@greenagric.ng', 'Administrator');
            
            // Subject
            $this->mail->Subject = EMAIL_SUBJECT_CONTACT_ADMIN . ': ' . $contactData['subject'];
            
            // Content
            $content = $this->getContactAdminTemplate($contactData);
            $this->mail->Body = $content;
            
            // Plain text alternative
            $this->mail->AltBody = $this->getPlainTextContent($content);
            
            // Send email
            $result = $this->mail->send();
            
            if ($this->debug_mode) {
                error_log("Admin email sent: " . ($result ? 'Success' : 'Failed'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to send admin email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send confirmation email to user
     */
    public function sendContactToUser($contactData) {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            
            // Recipient (user)
            $this->mail->addAddress($contactData['email'], $contactData['full_name']);
            
            // Subject
            $this->mail->Subject = EMAIL_SUBJECT_CONTACT_USER;
            
            // Content
            $content = $this->getContactUserTemplate($contactData);
            $this->mail->Body = $content;
            
            // Plain text alternative
            $this->mail->AltBody = $this->getPlainTextContent($content);
            
            // Send email
            $result = $this->mail->send();
            
            if ($this->debug_mode) {
                error_log("User email sent: " . ($result ? 'Success' : 'Failed'));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to send user email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation($orderData, $userData) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            
            $this->mail->addAddress($userData['email'], $userData['full_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_ORDER_CONFIRMATION . ' - #' . $orderData['order_number'];
            
            $content = $this->getOrderConfirmationTemplate($orderData, $userData);
            $this->mail->Body = $content;
            $this->mail->AltBody = $this->getPlainTextContent($content);
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Failed to send order confirmation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordReset($userEmail, $userName, $resetLink) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            
            $this->mail->addAddress($userEmail, $userName);
            
            $this->mail->Subject = EMAIL_SUBJECT_PASSWORD_RESET . ' - Green Agric LTD';
            
            $content = $this->getPasswordResetTemplate($userName, $resetLink);
            $this->mail->Body = $content;
            $this->mail->AltBody = $this->getPlainTextContent($content);
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Failed to send password reset: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send payment success email
     */
    public function sendPaymentSuccess($orderData, $userData, $paymentData) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            
            $this->mail->addAddress($userData['email'], $userData['full_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_PAYMENT_SUCCESS . ' - Order #' . $orderData['order_number'];
            
            $content = $this->getPaymentSuccessTemplate($orderData, $userData, $paymentData);
            $this->mail->Body = $content;
            $this->mail->AltBody = $this->getPlainTextContent($content);
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Failed to send payment success email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send new order notification to seller
     */
    public function sendSellerNewOrder($orderData, $sellerData, $orderItems) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            
            $this->mail->addAddress($sellerData['email'], $sellerData['business_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_SELLER_NEW_ORDER . ' - #' . $orderData['order_number'];
            
            $content = $this->getSellerNewOrderTemplate($orderData, $sellerData, $orderItems);
            $this->mail->Body = $content;
            $this->mail->AltBody = $this->getPlainTextContent($content);
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            error_log("Failed to send seller notification: " . $e->getMessage());
            return false;
        }
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
        $userStatus = $contactData['user_id'] ? 'Registered User (ID: ' . $contactData['user_id'] . ')' : 'Guest Visitor';
        
        $content = '
        <h2>📨 New Contact Form Submission</h2>
        <p>A new contact message has been received from the Green Agric LTD website.</p>
        
        <div class="message-box">
            <h3>👤 Contact Details</h3>
            <p><strong>Name:</strong> ' . htmlspecialchars($contactData['full_name']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($contactData['email']) . '</p>
            <p><strong>Phone:</strong> ' . ($contactData['phone'] ? htmlspecialchars($contactData['phone']) : 'Not provided') . '</p>
            <p><strong>Contact Type:</strong> ' . $type . '</p>
            <p><strong>User Status:</strong> ' . $userStatus . '</p>
        </div>
        
        <div class="message-box">
            <h3>💬 Message Details</h3>
            <p><strong>Subject:</strong> ' . htmlspecialchars($contactData['subject']) . '</p>
            <p><strong>Message:</strong></p>
            <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                ' . nl2br(htmlspecialchars($contactData['message'])) . '
            </div>
        </div>
        
        <div class="info-box">
            <h3>📊 Submission Details</h3>
            <p><strong>Submitted:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
            <p><strong>IP Address:</strong> ' . ($contactData['ip_address'] ?? 'N/A') . '</p>
            <p><strong>User Agent:</strong> ' . ($contactData['user_agent'] ?? 'N/A') . '</p>
        </div>
        
        <div class="highlight">
            <h3>🚀 Action Required</h3>
            <p>Please respond to this inquiry within <strong>24 hours</strong>.</p>
            <p>You can reply directly to: <strong>' . htmlspecialchars($contactData['email']) . '</strong></p>
        </div>
        
        <p style="text-align: center; margin-top: 30px;">
            <a href="' . BASE_URL . '/admin/contacts/manage-contacts.php" class="btn-primary">View in Admin Panel</a>
        </p>
        ';
        
        return str_replace(
            ['{content}', '{subject}', '{unsubscribe_link}'],
            [$content, EMAIL_SUBJECT_CONTACT_ADMIN, BASE_URL . '/unsubscribe'],
            EMAIL_TEMPLATE_HTML
        );
    }
    
    /**
     * Template for user contact confirmation
     */
    private function getContactUserTemplate($contactData) {
        $reference_id = 'CONTACT-' . date('Ymd') . '-' . rand(1000, 9999);
        
        $content = '
        <h2>🙏 Thank You for Contacting Us!</h2>
        <p>Dear <strong>' . htmlspecialchars($contactData['full_name']) . '</strong>,</p>
        
        <p>We have successfully received your message and our support team will review it shortly.</p>
        
        <div class="message-box">
            <h3>📋 Your Message Summary</h3>
            <p><strong>Reference ID:</strong> ' . $reference_id . '</p>
            <p><strong>Subject:</strong> ' . htmlspecialchars($contactData['subject']) . '</p>
            <p><strong>Submitted:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
            <p><strong>Your Message:</strong></p>
            <div style="background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                ' . nl2br(htmlspecialchars($contactData['message'])) . '
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
        
        return str_replace(
            ['{content}', '{subject}', '{unsubscribe_link}'],
            [$content, EMAIL_SUBJECT_CONTACT_USER, BASE_URL . '/unsubscribe'],
            EMAIL_TEMPLATE_HTML
        );
    }
    
    /**
     * Convert HTML to plain text
     */
    private function getPlainTextContent($html) {
        // Remove HTML tags
        $text = strip_tags($html);
        
        // Replace common HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Replace multiple spaces and newlines
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim and return
        return trim($text);
    }
    
    // ... (other template methods remain similar but updated with proper constants)
    
    /**
     * Template for order confirmation
     */
    private function getOrderConfirmationTemplate($orderData, $userData) {
        $content = '
        <h2>✅ Order Confirmation</h2>
        <p>Dear ' . htmlspecialchars($userData['full_name']) . ',</p>
        
        <p>Thank you for your order! We have received your order and it is being processed.</p>
        
        <div class="message-box">
            <h3>📦 Order Details</h3>
            <p><strong>Order Number:</strong> ' . $orderData['order_number'] . '</p>
            <p><strong>Order Date:</strong> ' . date('F j, Y \a\t g:i A', strtotime($orderData['created_at'])) . '</p>
            <p><strong>Total Amount:</strong> ₦' . number_format($orderData['total_amount'], 2) . '</p>
            <p><strong>Status:</strong> <span style="color: #198754; font-weight: bold;">Confirmed</span></p>
        </div>
        
        <p>You can track your order from your account dashboard:</p>
        
        <p style="text-align: center; margin: 30px 0;">
            <a href="' . BASE_URL . '/buyer/orders/track-order.php?id=' . $orderData['id'] . '" class="btn-primary">Track Your Order</a>
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
        
        return str_replace(
            ['{content}', '{subject}', '{unsubscribe_link}'],
            [$content, EMAIL_SUBJECT_ORDER_CONFIRMATION, BASE_URL . '/unsubscribe'],
            EMAIL_TEMPLATE_HTML
        );
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
        
        return str_replace(
            ['{content}', '{subject}', '{unsubscribe_link}'],
            [$content, EMAIL_SUBJECT_PASSWORD_RESET, BASE_URL . '/unsubscribe'],
            EMAIL_TEMPLATE_HTML
        );
    }
}
?>