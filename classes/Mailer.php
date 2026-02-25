<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

class Mailer {
    private $mail;
    private $debug_mode = false;
    private $last_error = '';
    
    public function __construct($debug = false) {
        $this->debug_mode = $debug;
        $this->mail = new PHPMailer(true);
        
        try {
            // Server settings - EXACTLY like your working test
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = SMTP_USERNAME;
            $this->mail->Password   = SMTP_PASSWORD;
            $this->mail->SMTPSecure = SMTP_SECURE;
            $this->mail->Port       = SMTP_PORT;
            
            // Debug mode
            if ($this->debug_mode) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
            // Sender settings
            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Content settings
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            $this->mail->Encoding = 'base64';
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Mailer Init Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send contact form email to admin
     */
    public function sendContactToAdmin($contactData) {
        try {
            // Reset recipients
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            $this->mail->clearReplyTos();
            
            // Recipients
            $this->mail->addAddress(ADMIN_EMAIL, ADMIN_NAME);
            
            // Subject
            $this->mail->Subject = EMAIL_SUBJECT_CONTACT_ADMIN . ': ' . $contactData['subject'];
            
            // HTML Body
            $htmlContent = $this->getContactAdminTemplate($contactData);
            $this->mail->Body = $htmlContent;
            
            // Plain text alternative (simplified)
            $this->mail->AltBody = "New contact from: " . $contactData['full_name'] . 
                                   "\nEmail: " . $contactData['email'] .
                                   "\nMessage: " . $contactData['message'];
            
            // Send
            $result = $this->mail->send();
            
            if ($this->debug_mode) {
                error_log("Admin email sent successfully");
            }
            
            return true;
            
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
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            $this->mail->clearReplyTos();
            
            // Validate email
            if (!filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address");
            }
            
            $this->mail->addAddress($contactData['email'], $contactData['full_name']);
            $this->mail->Subject = EMAIL_SUBJECT_CONTACT_USER;
            
            $htmlContent = $this->getContactUserTemplate($contactData);
            $this->mail->Body = $htmlContent;
            $this->mail->AltBody = "Thank you for contacting us. We'll respond within 24 hours.";
            
            return $this->mail->send();
            
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
            $this->mail->clearAddresses();
            $this->mail->addAddress($userData['email'], $userData['full_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_ORDER_CONFIRMATION . ' - #' . $orderData['order_number'];
            
            $htmlContent = $this->getOrderConfirmationTemplate($orderData, $userData);
            $this->mail->Body = $htmlContent;
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
            $this->mail->clearAddresses();
            $this->mail->addAddress($userEmail, $userName);
            
            $this->mail->Subject = EMAIL_SUBJECT_PASSWORD_RESET;
            
            $htmlContent = $this->getPasswordResetTemplate($userName, $resetLink);
            $this->mail->Body = $htmlContent;
            $this->mail->AltBody = "Reset your password here: $resetLink";
            
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
            $this->mail->clearAddresses();
            $this->mail->addAddress($userData['email'], $userData['full_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_PAYMENT_SUCCESS . ' - Order #' . $orderData['order_number'];
            
            $htmlContent = $this->getPaymentSuccessTemplate($orderData, $userData, $paymentData);
            $this->mail->Body = $htmlContent;
            $this->mail->AltBody = "Payment successful for order #{$orderData['order_number']}";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send payment success: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send new order notification to seller
     */
    public function sendSellerNewOrder($orderData, $sellerData, $orderItems) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($sellerData['email'], $sellerData['business_name']);
            
            $this->mail->Subject = EMAIL_SUBJECT_SELLER_NEW_ORDER . ' - #' . $orderData['order_number'];
            
            $htmlContent = $this->getSellerNewOrderTemplate($orderData, $sellerData, $orderItems);
            $this->mail->Body = $htmlContent;
            $this->mail->AltBody = "New order #{$orderData['order_number']} received";
            
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
            $this->mail->clearAddresses();
            $this->mail->addAddress($userEmail, $userName);
            
            $this->mail->Subject = 'Verify Your Email - Green Agric LTD';
            
            $htmlContent = $this->getEmailVerificationTemplate($userName, $verificationLink);
            $this->mail->Body = $htmlContent;
            $this->mail->AltBody = "Verify your email: $verificationLink";
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Failed to send email verification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->last_error;
    }
    
    // ==================== TEMPLATE METHODS ====================
    
    private function getContactAdminTemplate($contactData) {
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">📨 New Contact Form Submission</h2>
            
            <div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #198754;">
                <h3>👤 Contact Details</h3>
                <p><strong>Name:</strong> ' . htmlspecialchars($contactData['full_name'] ?? '') . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($contactData['email'] ?? '') . '</p>
                <p><strong>Phone:</strong> ' . htmlspecialchars($contactData['phone'] ?? 'Not provided') . '</p>
                <p><strong>Subject:</strong> ' . htmlspecialchars($contactData['subject'] ?? '') . '</p>
            </div>
            
            <div style="background: white; padding: 20px; border: 1px solid #ddd;">
                <h3>💬 Message</h3>
                <p>' . nl2br(htmlspecialchars($contactData['message'] ?? '')) . '</p>
            </div>
            
            <p style="margin-top: 30px;">Reply to: <a href="mailto:' . htmlspecialchars($contactData['email'] ?? '') . '">' . htmlspecialchars($contactData['email'] ?? '') . '</a></p>
        </body>
        </html>
        ';
    }
    
    private function getContactUserTemplate($contactData) {
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">🙏 Thank You for Contacting Us!</h2>
            
            <p>Dear <strong>' . htmlspecialchars($contactData['full_name'] ?? '') . '</strong>,</p>
            
            <p>We have received your message and will respond within 24 hours.</p>
            
            <div style="background: #f8f9fa; padding: 20px; margin: 20px 0;">
                <h3>📋 Your Message</h3>
                <p><strong>Subject:</strong> ' . htmlspecialchars($contactData['subject'] ?? '') . '</p>
                <p><strong>Message:</strong></p>
                <p>' . nl2br(htmlspecialchars($contactData['message'] ?? '')) . '</p>
            </div>
            
            <p>Best regards,<br>Green Agric LTD Support Team</p>
        </body>
        </html>
        ';
    }
    
    private function getOrderConfirmationTemplate($orderData, $userData) {
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">✅ Order Confirmation</h2>
            
            <p>Dear ' . htmlspecialchars($userData['full_name'] ?? '') . ',</p>
            
            <p>Thank you for your order!</p>
            
            <div style="background: #f8f9fa; padding: 20px; margin: 20px 0;">
                <h3>📦 Order Details</h3>
                <p><strong>Order Number:</strong> ' . ($orderData['order_number'] ?? 'N/A') . '</p>
                <p><strong>Total Amount:</strong> ₦' . number_format($orderData['total_amount'] ?? 0, 2) . '</p>
                <p><strong>Status:</strong> Confirmed</p>
            </div>
            
            <p>Best regards,<br>Green Agric LTD Team</p>
        </body>
        </html>
        ';
    }
    
    private function getPasswordResetTemplate($userName, $resetLink) {
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">🔐 Password Reset Request</h2>
            
            <p>Dear ' . htmlspecialchars($userName) . ',</p>
            
            <p>Click the button below to reset your password (expires in 1 hour):</p>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $resetLink . '" style="background: #198754; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">Reset Password</a>
            </p>
            
            <p>If you didn\'t request this, ignore this email.</p>
            
            <p>Best regards,<br>Green Agric LTD Security Team</p>
        </body>
        </html>
        ';
    }
    
    private function getPaymentSuccessTemplate($orderData, $userData, $paymentData) {
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">💰 Payment Successful!</h2>
            
            <p>Dear ' . htmlspecialchars($userData['full_name'] ?? '') . ',</p>
            
            <p>Your payment has been processed successfully.</p>
            
            <div style="background: #f8f9fa; padding: 20px; margin: 20px 0;">
                <h3>🧾 Payment Details</h3>
                <p><strong>Order:</strong> #' . ($orderData['order_number'] ?? 'N/A') . '</p>
                <p><strong>Amount:</strong> ₦' . number_format($paymentData['amount'] ?? 0, 2) . '</p>
                <p><strong>Reference:</strong> ' . ($paymentData['reference'] ?? 'N/A') . '</p>
            </div>
            
            <p>Best regards,<br>Green Agric LTD Team</p>
        </body>
        </html>
        ';
    }
    
    private function getSellerNewOrderTemplate($orderData, $sellerData, $orderItems) {
        $itemsHtml = '';
        if (!empty($orderItems)) {
            $itemsHtml = '<table style="width:100%; border-collapse:collapse; margin:15px 0;">';
            $itemsHtml .= '<tr><th style="text-align:left; padding:8px; background:#f8f9fa;">Product</th><th style="text-align:left; padding:8px; background:#f8f9fa;">Qty</th><th style="text-align:left; padding:8px; background:#f8f9fa;">Price</th></tr>';
            foreach ($orderItems as $item) {
                $itemsHtml .= '<tr>';
                $itemsHtml .= '<td style="padding:8px; border-bottom:1px solid #ddd;">' . htmlspecialchars($item['product_name'] ?? '') . '</td>';
                $itemsHtml .= '<td style="padding:8px; border-bottom:1px solid #ddd;">' . ($item['quantity'] ?? 0) . '</td>';
                $itemsHtml .= '<td style="padding:8px; border-bottom:1px solid #ddd;">₦' . number_format($item['price'] ?? 0, 2) . '</td>';
                $itemsHtml .= '</tr>';
            }
            $itemsHtml .= '</table>';
        }
        
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">🛍️ New Order Received!</h2>
            
            <p>Dear ' . htmlspecialchars($sellerData['business_name'] ?? 'Seller') . ',</p>
            
            <p>You have a new order #' . ($orderData['order_number'] ?? 'N/A') . '</p>
            
            <div style="background: #f8f9fa; padding: 20px; margin: 20px 0;">
                <h3>📦 Order Summary</h3>
                <p><strong>Total:</strong> ₦' . number_format($orderData['total_amount'] ?? 0, 2) . '</p>
            </div>
            
            <h3>Items Ordered:</h3>
            ' . $itemsHtml . '
            
            <p style="margin-top: 30px;">Please process this order within 24 hours.</p>
            
            <p>Best regards,<br>Green Agric LTD Team</p>
        </body>
        </html>
        ';
    }
    
    private function getEmailVerificationTemplate($userName, $verificationLink) {
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <h2 style="color: #198754;">🔐 Verify Your Email</h2>
            
            <p>Dear <strong>' . htmlspecialchars($userName) . '</strong>,</p>
            
            <p>Please verify your email address to complete registration:</p>
            
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $verificationLink . '" style="background: #198754; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">Verify Email</a>
            </p>
            
            <p>This link expires in 24 hours.</p>
            
            <p>Best regards,<br>Green Agric LTD Team</p>
        </body>
        </html>
        ';
    }
}
?>