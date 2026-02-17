<?php
require_once '../classes/Database.php';
require_once '../config/constants.php';

session_start();

$error = '';
$success = '';
$already_verified = false;

// Get token and email from URL
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    $error = 'Invalid verification link';
} else {
    $db = new Database();
    
    // Find user with this token and email
    $user = $db->fetchOne(
        "SELECT id, email, is_email_verified, email_verification_expires 
         FROM users 
         WHERE email = ? AND email_verification_token = ?",
        [$email, $token]
    );
    
    if (!$user) {
        $error = 'Invalid verification link';
    } elseif ($user['is_email_verified']) {
        $already_verified = true;
        $success = 'Your email is already verified. You can now login.';
    } else {
        // Check if token has expired
        if (strtotime($user['email_verification_expires']) < time()) {
            $error = 'Verification link has expired. Please request a new one.';
        } else {
            // Verify the email
            $updated = $db->query(
                "UPDATE users 
                 SET is_email_verified = 1, 
                     email_verification_token = NULL, 
                     email_verification_expires = NULL,
                     email_verified_at = NOW() 
                 WHERE id = ?",
                [$user['id']]
            );
            
            if ($updated) {
                $success = 'Email verified successfully! You can now login to your account.';
                
                // Log the verification
                $db->query(
                    "INSERT INTO user_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)",
                    [$user['id'], 'email_verified', 'Email verified via link', $_SERVER['REMOTE_ADDR']]
                );
            } else {
                $error = 'Failed to verify email. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Green Agric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verification-card {
            max-width: 500px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .verification-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card verification-card">
                    <div class="card-body p-5 text-center">
                        <?php if ($error): ?>
                            <div class="verification-icon text-danger">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                            <h3 class="mb-3 text-danger">Verification Failed</h3>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            <a href="resend-verification.php?email=<?php echo urlencode($email); ?>" class="btn btn-success mt-3">
                                <i class="bi bi-envelope me-2"></i>Resend Verification Email
                            </a>
                        <?php elseif ($already_verified): ?>
                            <div class="verification-icon text-info">
                                <i class="bi bi-info-circle-fill"></i>
                            </div>
                            <h3 class="mb-3 text-info">Already Verified</h3>
                            <div class="alert alert-info">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                            <a href="login.php" class="btn btn-success mt-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                            </a>
                        <?php else: ?>
                            <div class="verification-icon text-success">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <h3 class="mb-3 text-success">Email Verified!</h3>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                            <a href="login.php" class="btn btn-success mt-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login to Your Account
                            </a>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="../index.php" class="text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>