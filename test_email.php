<?php
// test_mail.php
require_once 'classes/Mailer.php';
require_once 'config/constants.php';

try {
    $mailer = new Mailer(true);
    $result = $mailer->sendEmailVerification(
        'your-test-email@gmail.com', 
        'Test User', 
        'http://localhost/test'
    );
    echo "Mail sent: " . ($result ? 'Yes' : 'No');
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>