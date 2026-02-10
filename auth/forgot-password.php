<?php
require_once '../config/constants.php';
require_once '../config/smtp.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';


// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (!empty($email)) {
        require_once '../classes/Validation.php';
        require_once '../classes/Mailer.php';
        
        if (Validation::email($email)) {
            $db = new Database();
            
            // Check if user exists
            $user = $db->fetchOne("SELECT id, first_name, last_name FROM users WHERE email = ? AND is_active = TRUE", [$email]);
            
            if ($user) {
                // Generate secure reset token
                $reset_token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Delete any existing tokens for this email
                $db->query("DELETE FROM password_resets WHERE email = ? OR expires_at < NOW()", [$email]);
                
                // Store new token in database
                $db->query("INSERT INTO password_resets (email, token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)", 
                          [$email, $reset_token, $expires, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
                // Create reset link
                $reset_link = BASE_URL . "/auth/reset-password.php?token=" . $reset_token;
                
                // Send email using Mailer
                try {
                    $mailer = new Mailer();
                    
                    // Prepare user data
                    $user_data = [
                        'email' => $email,
                        'full_name' => $user['first_name'] . ' ' . $user['last_name']
                    ];
                    
                    // Send password reset email
                    $email_sent = $mailer->sendPasswordReset($email, $user_data['full_name'], $reset_link);
                    
                    if ($email_sent) {
                        $message = '
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Reset link sent successfully!</strong><br>
                            We have sent a password reset link to <strong>' . htmlspecialchars($email) . '</strong>.
                            Please check your inbox (and spam folder).
                        </div>
                        ';
                    } else {
                        $message = '
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Reset link generated!</strong><br>
                            However, we couldn\'t send the email. Please try again or contact support.
                        </div>
                        ';
                    }
                    
                } catch (Exception $e) {
                    error_log("Password reset email error: " . $e->getMessage());
                    $message = '
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        If the email exists, reset instructions will be sent.
                    </div>
                    ';
                }
            } else {
                // Don't reveal if email exists (security)
                $message = '
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    If the email exists in our system, you will receive reset instructions shortly.
                </div>
                ';
            }
        } else {
            $error = '
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Please enter a valid email address
            </div>
            ';
        }
    } else {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Please enter your email address
        </div>
        ';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Green Agric LTD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/auth.css" rel="stylesheet">
    <style>
        .password-reset-card {
            max-width: 450px;
            margin: 0 auto;
        }
        .reset-icon {
            font-size: 3rem;
            color: #198754;
            margin-bottom: 1rem;
        }
        .security-tips {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.875rem;
        }
        .security-tips h6 {
            color: #198754;
            margin-bottom: 10px;
        }
        .resend-link {
            font-size: 0.9rem;
            margin-top: 15px;
        }
    </style>
</head>
<body class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="password-reset-card card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="reset-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <h2 class="card-title fw-bold text-success">Reset Password</h2>
                            <p class="text-muted">Enter your email to receive reset instructions</p>
                        </div>

                        <?php if ($error): ?>
                            <?php echo $error; ?>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <?php echo $message; ?>
                            
                            <?php if (strpos($message, 'successfully') !== false): ?>
                                <div class="text-center mt-4">
                                    <a href="login.php" class="btn btn-outline-success me-2">
                                        <i class="bi bi-arrow-left me-1"></i> Back to Login
                                    </a>
                                    <button type="button" class="btn btn-success" onclick="location.reload()">
                                        <i class="bi bi-send me-1"></i> Send Another
                                    </button>
                                </div>
                                
                                <div class="security-tips">
                                    <h6><i class="bi bi-lightbulb me-1"></i> Didn't receive the email?</h6>
                                    <ul class="mb-0">
                                        <li>Check your spam or junk folder</li>
                                        <li>Make sure you entered the correct email</li>
                                        <li>Wait a few minutes and try again</li>
                                        <li>Contact support if you continue to have issues</li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="text-center mt-3">
                                    <a href="login.php" class="btn btn-outline-success">Back to Login</a>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>

                        <form method="POST" action="" id="resetForm">
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="Enter your registered email" required>
                                </div>
                                <div class="form-text">
                                    We'll send a secure password reset link to this email
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg py-2" id="submitBtn">
                                    <i class="bi bi-send me-2"></i> Send Reset Link
                                </button>
                            </div>

                            <div class="text-center mt-3">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                        </form>
                        
                        <div class="security-tips">
                            <h6><i class="bi bi-shield-check me-1"></i> Security Information</h6>
                            <ul class="mb-0">
                                <li>Reset links expire in 1 hour</li>
                                <li>Only one active reset link per email</li>
                                <li>Links can only be used once</li>
                                <li>We will never ask for your password</li>
                            </ul>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Support Information -->
                <div class="text-center mt-4">
                    <p class="text-muted small">
                        Need help? Contact our support team:
                        <a href="mailto:support@greenagric.shop" class="text-success">support@greenagric.shop</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and loading state
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const email = document.getElementById('email').value;
            
            // Basic email validation
            if (!email.includes('@') || !email.includes('.')) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Sending...';
        });
        
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.focus();
            }
        });
    </script>
</body>
</html>