<?php
require_once '../config/constants.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
                
                // Send email using Mailer with debug mode ON to see errors
                try {
                    // Create mailer instance with debug mode ON
                    $mailer = new Mailer(true); // Set to true for debugging
                    
                    // Prepare user data
                    $full_name = $user['first_name'] . ' ' . $user['last_name'];
                    
                    // Send password reset email
                    $email_sent = $mailer->sendPasswordReset($email, $full_name, $reset_link);
                    
                    if ($email_sent) {
                        $message = '
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Reset link sent successfully!</strong><br>
                            We have sent a password reset link to <strong>' . htmlspecialchars($email) . '</strong>.
                            Please check your inbox (and spam folder).
                        </div>
                        ';
                        
                        // Log success
                        error_log("Password reset email sent successfully to: " . $email);
                    } else {
                        // Get the error from mailer
                        $mailer_error = $mailer->getLastError();
                        error_log("Mailer error: " . $mailer_error);
                        
                        $message = '
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Reset link generated!</strong><br>
                            However, we couldn\'t send the email. Technical details: ' . htmlspecialchars($mailer_error) . '<br>
                            Please try again or contact support.
                        </div>
                        ';
                    }
                    
                } catch (Exception $e) {
                    error_log("Password reset email exception: " . $e->getMessage());
                    $message = '
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        If the email exists, reset instructions will be sent.
                    </div>
                    ';
                }
            } else {
                // Don't reveal if email exists (security)
                $message = '
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    If the email exists in our system, you will receive reset instructions shortly.
                </div>
                ';
            }
        } else {
            $error = '
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Please enter a valid email address
            </div>
            ';
        }
    } else {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
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
    <title>Forgot Password - Green Agric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, rgb(81, 246, 81) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .auth-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        .btn-success {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        .btn-outline-success {
            transition: transform 0.3s ease;
        }
        .btn-outline-success:hover {
            transform: translateY(-2px);
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .auth-container {
            width: 100%;
            padding: 15px;
        }
        .reset-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .security-tips {
            background-color:rgb(248, 250, 248);
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.875rem;
        }
        .security-tips h6 {
            color: #28a745;
            margin-bottom: 10px;
        }
        .security-tips ul {
            padding-left: 1.2rem;
        }
        .security-tips li {
            margin-bottom: 5px;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .input-group .form-control {
            border-left: none;
        }
        .input-group .form-control:focus {
            border-left: none;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider::before {
            margin-right: .5rem;
        }
        .divider::after {
            margin-left: .5rem;
        }
    </style>
</head>
<body class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card card shadow">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-2">
                            <a href="../">
                                <img src="../assets/images/logo.jpeg"
                                    alt="Green Agric Logo"
                                    class="mb-2"
                                    style="height:80px; width:auto; border-radius: 10px;">
                            </a>
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
                            <?php else: ?>
                                <div class="text-center mt-3">
                                    <a href="login.php" class="btn btn-outline-success">Back to Login</a>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>

                        <form method="POST" action="" id="resetForm">
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">
                                    <i class="bi bi-envelope text-success me-1"></i>Email Address
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent">
                                        <i class="bi bi-envelope text-success"></i>
                                    </span>
                                    <input type="email" 
                                           class="form-control form-control-lg" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="Enter your registered email"
                                           required>
                                </div>
                                <div class="form-text mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    We'll send a secure password reset link to this email
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-3 mb-3 fw-semibold" id="submitBtn">
                                <i class="bi bi-send me-2"></i> Send Reset Link
                            </button>

                            <div class="divider my-2">
                                <span class="px-3 text-muted small">or</span>
                            </div>

                            <div class="text-center">
                                <a href="login.php" class="text-success text-decoration-none fw-semibold">
                                    <i class="bi bi-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                        </form>
                        
                        <div class="security-tips mt-2">
                            <h6><i class="bi bi-shield-check text-success me-1"></i> Security Information</h6>
                            <ul class="mb-0 small">
                                <li><i class="bi bi-clock text-success me-1"></i> Reset links expire in 1 hour</li>
                                <li><i class="bi bi-link me-1 text-success"></i> Only one active reset link per email</li>
                                <li><i class="bi bi-check2-circle me-1 text-success"></i> Links can only be used once</li>
                                <li><i class="bi bi-shield-exclamation me-1 text-success"></i> We will never ask for your password</li>
                            </ul>
                        </div>
                        
                        <div class="text-center mt-2">
                            <p class="small text-muted">
                                <i class="bi bi-shield-check text-success me-1"></i>
                                Secure password reset powered by Green Agric
                            </p>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Support Information -->
                <div class="text-center mt-4">
                    <p class="text-muted small">
                        Need help? Contact our support team:
                        <a href="mailto:support@greenagric.shop" class="text-success text-decoration-none">support@greenagric.shop</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and loading state
        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                const email = document.getElementById('email').value;
                
                // Basic email validation
                const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!emailPattern.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address');
                    return;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
            });
        }
        
        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.focus();
            }
        });
        
        // Real-time email validation
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('input', function(e) {
                const email = this.value;
                const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                
                if (email.length === 0) {
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (emailPattern.test(email)) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            });
        }
    </script>
</body>
</html>