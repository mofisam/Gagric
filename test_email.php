<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'classes/Mailer.php';

echo "<h1>Testing Mailer Class</h1>";

// Test 1: Send to admin
echo "<h2>Test 1: Send Contact to Admin</h2>";
try {
    $mailer = new Mailer(true); // Enable debug
    
    $contactData = [
        'full_name' => 'John Doe',
        'email' => 'test@example.com',
        'phone' => '08012345678',
        'contact_type' => 'general',
        'subject' => 'Test Contact Form',
        'message' => 'This is a test message from the Mailer class.',
        'user_id' => null
    ];
    
    if ($mailer->sendContactToAdmin($contactData)) {
        echo "<p style='color:green'>✅ Email to admin sent successfully!</p>";
    } else {
        echo "<p style='color:red'>❌ Failed: " . $mailer->getLastError() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test 2: Send to user
echo "<h2>Test 2: Send Contact to User</h2>";
try {
    $mailer = new Mailer(true);
    
    $contactData = [
        'full_name' => 'John Doe',
        'email' => 'your-personal-email@gmail.com', // CHANGE THIS
        'subject' => 'Thank you',
        'message' => 'We received your message.'
    ];
    
    if ($mailer->sendContactToUser($contactData)) {
        echo "<p style='color:green'>✅ Email to user sent successfully!</p>";
    } else {
        echo "<p style='color:red'>❌ Failed: " . $mailer->getLastError() . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
?>