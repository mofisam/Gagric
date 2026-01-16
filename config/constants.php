<?php
// Application Configuration
define('APP_NAME', 'Green Agric');
define('APP_VERSION', '1.0');
define('BASE_URL', 'http://localhost/web/greenagric');


// File Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
define('PRODUCT_IMAGE_PATH', UPLOAD_PATH . 'products/');
define('PROFILE_IMAGE_PATH', UPLOAD_PATH . 'profiles/');
define('DOCUMENT_PATH', UPLOAD_PATH . 'documents/');

// File Upload Limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx']);

// Business Rules
define('COMMISSION_RATE', 5.0); // 5%
define('MIN_PAYOUT_AMOUNT', 5000.00);
define('LOW_STOCK_ALERT', 10);
define('MAX_PRODUCT_IMAGES', 5);

// Product Status
define('PRODUCT_DRAFT', 'draft');
define('PRODUCT_PENDING', 'pending');
define('PRODUCT_APPROVED', 'approved');
define('PRODUCT_REJECTED', 'rejected');

// Order Status
define('ORDER_PENDING', 'pending');
define('ORDER_CONFIRMED', 'confirmed');
define('ORDER_SHIPPED', 'shipped');
define('ORDER_DELIVERED', 'delivered');
define('ORDER_CANCELLED', 'cancelled');

// Payment Status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_PAID', 'paid');
define('PAYMENT_FAILED', 'failed');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_SELLER', 'seller');
define('ROLE_BUYER', 'buyer');

// Nigerian Specific
define('DEFAULT_CURRENCY', 'NGN');
define('DEFAULT_COUNTRY', 'Nigeria');
define('SUPPORTED_STATES', ['Lagos', 'Abuja', 'Rivers', 'Kano', 'Oyo', 'Ogun']);

// Session & Security
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_ALGO', PASSWORD_DEFAULT);

// Platform Settings
define('ITEMS_PER_PAGE', 12);
define('FEATURED_PRODUCTS_LIMIT', 8);
define('RECENT_PRODUCTS_DAYS', 30);

// SMTP Configuration
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io'); // Change to your SMTP server
define('SMTP_PORT', 2525); // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', '61afe5fbfca488'); // Your email
define('SMTP_PASSWORD', '98b8d56957eaa6'); // Use app password for Gmail
define('SMTP_FROM_EMAIL', 'noreply@greenagric.ng');
define('SMTP_FROM_NAME', 'Green Agric LTD');
define('SMTP_SECURE', 'tls'); // tls or ssl

// Email subjects
define('EMAIL_SUBJECT_CONTACT_ADMIN', 'New Contact Form Submission');
define('EMAIL_SUBJECT_CONTACT_USER', 'Thank You for Contacting Green Agric LTD');
define('EMAIL_SUBJECT_ORDER_CONFIRMATION', 'Order Confirmation');
define('EMAIL_SUBJECT_PASSWORD_RESET', 'Password Reset Request');
define('EMAIL_SUBJECT_PAYMENT_SUCCESS', 'Payment Successful');
define('EMAIL_SUBJECT_SELLER_NEW_ORDER', 'New Order Received');

// Email template
define('EMAIL_TEMPLATE_HTML', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .email-header { 
            background: linear-gradient(135deg, #198754 0%, #25a569 100%); 
            color: white; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .email-header h1 { 
            margin: 0; 
            font-size: 28px; 
            font-weight: bold;
        }
        .email-header p { 
            margin: 10px 0 0; 
            opacity: 0.9;
        }
        .email-logo {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .email-content { 
            padding: 30px; 
        }
        .email-footer { 
            text-align: center; 
            padding: 25px 20px; 
            color: #666; 
            font-size: 12px; 
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        .btn-primary { 
            display: inline-block; 
            padding: 12px 30px; 
            background-color: #198754; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            font-weight: bold;
            margin: 15px 0;
        }
        .btn-primary:hover {
            background-color: #157347;
        }
        .message-box { 
            background-color: #f8f9fa; 
            border-left: 4px solid #198754; 
            padding: 20px; 
            margin: 20px 0; 
            border-radius: 0 5px 5px 0;
        }
        .info-box {
            background-color: #e7f5ee;
            border: 1px solid #c3e6cb;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
            border-radius: 0 5px 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .social-links {
            margin: 20px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #198754;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            .email-content { padding: 20px; }
            table { font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Green Agric LTD</h1>
            <p>Nigeria\'s Agricultural E-commerce Platform</p>
        </div>
        <div class="email-content">
            {content}
        </div>
        <div class="email-footer">
            <p><strong>Green Agric LTD Nigeria</strong><br>
            123 Agric Street, Ikeja, Lagos, Nigeria</p>
            <p>Phone: +234 800 123 4567 | Email: support@Green Agric LTD.ng</p>
            
            <div class="social-links">
                <a href="https://facebook.com/greenagric" target="_blank">Facebook</a> | 
                <a href="https://twitter.com/greenagric" target="_blank">Twitter</a> | 
                <a href="https://instagram.com/greenagric" target="_blank">Instagram</a> | 
                <a href="https://wa.me/2348001234567" target="_blank">WhatsApp</a>
            </div>
            
            <p>&copy; <?php echo date("Y"); ?> Green Agric LTD. All rights reserved.<br>
            This is an automated message, please do not reply to this email.</p>
            <p style="font-size: 10px; color: #999; margin-top: 15px;">
                If you no longer wish to receive these emails, <a href="{unsubscribe_link}" style="color: #999;">unsubscribe here</a>.
            </p>
        </div>
    </div>
</body>
</html>
');

// API Keys (Replace with actual keys in production)
define('PAYSTACK_PUBLIC_KEY', 'pk_test_3d8772ab51c1407f1302d2fffc114220b0b1d9ee');
define('PAYSTACK_SECRET_KEY', 'sk_test_41008269e1c6f30a68e89226ebe8bf9628c9e3ae');
define('SENDY_API_KEY', 'your_sendy_key_here');


// Error Reporting
define('DISPLAY_ERRORS', true);
?>