<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'classes/Mailer.php';

echo "<h1>Email Configuration Test</h1>";

// Display current configuration
echo "<h2>Current SMTP Settings:</h2>";
echo "<pre>";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USERNAME: " . SMTP_USERNAME . "\n";
echo "SMTP_FROM_EMAIL: " . SMTP_FROM_EMAIL . "\n";
echo "SMTP_FROM_NAME: " . SMTP_FROM_NAME . "\n";
echo "SMTP_SECURE: " . SMTP_SECURE . "\n";
echo "ADMIN_EMAIL: " . ADMIN_EMAIL . "\n";
echo "</pre>";

// Test 1: Basic connection
echo "<h2>Test 1: SMTP Connection Test</h2>";
try {
    $mailer = new Mailer(true); // Enable debug mode
    if ($mailer->testConnection()) {
        echo "<p style='color: green'>✅ SMTP Connection successful!</p>";
    } else {
        echo "<p style='color: red'>❌ SMTP Connection failed: " . $mailer->getLastError() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test 2: Send test email to admin
echo "<h2>Test 2: Send Test Email to Admin</h2>";
try {
    $mailer = new Mailer(true);
    
    $contactData = [
        'full_name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '08012345678',
        'contact_type' => 'general',
        'subject' => 'Test Email from Green Agric',
        'message' => 'This is a test message to verify email functionality.',
        'user_id' => null,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ];
    
    if ($mailer->sendContactToAdmin($contactData)) {
        echo "<p style='color: green'>✅ Test email sent successfully to admin!</p>";
    } else {
        echo "<p style='color: red'>❌ Failed to send test email: " . $mailer->getLastError() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test 3: Send test email to user
echo "<h2>Test 3: Send Test Email to User</h2>";
try {
    $mailer = new Mailer(true);
    
    $contactData = [
        'full_name' => 'Test User',
        'email' => 'your-personal-email@gmail.com', // CHANGE THIS TO YOUR EMAIL
        'subject' => 'Thank you for contacting us',
        'message' => 'This is a confirmation that your message was received.'
    ];
    
    if ($mailer->sendContactToUser($contactData)) {
        echo "<p style='color: green'>✅ Test email sent successfully to user!</p>";
    } else {
        echo "<p style='color: red'>❌ Failed to send test email: " . $mailer->getLastError() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Common issues and solutions
echo "<h2>🔧 Troubleshooting Guide</h2>";
echo "<ul>";
echo "<li><strong>Check your SMTP credentials:</strong> Make sure SMTP_USERNAME and SMTP_PASSWORD are correct in constants.php</li>";
echo "<li><strong>Check SMTP host and port:</strong> Verify with your hosting provider what the correct SMTP settings are</li>";
echo "<li><strong>Firewall issues:</strong> Some hosting providers block outgoing SMTP ports. Contact your host</li>";
echo "<li><strong>SPF/DKIM records:</strong> Your domain needs proper DNS records for email authentication</li>";
echo "<li><strong>Email sending limits:</strong> Your hosting might have daily email sending limits</li>";
echo "<li><strong>Check spam folder:</strong> Test emails might end up in spam</li>";
echo "</ul>";
?>