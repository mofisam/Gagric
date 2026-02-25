<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file for debugging
$debug_log = __DIR__ . '/../logs/password_reset_debug.log';
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

function debug_log($message) {
    global $debug_log;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debug_log, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("=== Password Reset Request Started ===");

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
    debug_log("POST request received");
    $email = trim($_POST['email']);
    debug_log("Email: " . $email);
    
    if (!empty($email)) {
        require_once '../classes/Validation.php';
        require_once '../classes/Mailer.php';
        
        if (Validation::email($email)) {
            debug_log("Email validation passed");
            
            $db = new Database();
            
            // Check if user exists
            $user = $db->fetchOne("SELECT id, first_name, last_name, email FROM users WHERE email = ? AND is_active = TRUE", [$email]);
            debug_log("User found: " . ($user ? 'Yes' : 'No'));
            
            if ($user) {
                debug_log("User details: ID=" . $user['id'] . ", Name=" . $user['first_name'] . " " . $user['last_name']);
                
                // Generate secure reset token
                $reset_token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                debug_log("Token generated: " . $reset_token);
                debug_log("Expires: " . $expires);
                
                // Delete any existing tokens for this email
                $db->query("DELETE FROM password_resets WHERE email = ? OR expires_at < NOW()", [$email]);
                debug_log("Old tokens deleted");
                
                // Store new token in database
                $db->query("INSERT INTO password_resets (email, token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)", 
                          [$email, $reset_token, $expires, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                debug_log("New token stored in database");
                
                // Create reset link
                $reset_link = BASE_URL . "/auth/reset-password.php?token=" . $reset_token;
                debug_log("Reset link: " . $reset_link);
                
                // Send email using Mailer
                try {
                    debug_log("Initializing Mailer...");
                    $mailer = new Mailer(true); // Enable debug mode
                    debug_log("Mailer initialized");
                    
                    // Prepare user data
                    $user_data = [
                        'email' => $user['email'],
                        'full_name' => $user['first_name'] . ' ' . $user['last_name']
                    ];
                    debug_log("User data prepared: " . print_r($user_data, true));
                    
                    // Send password reset email
                    debug_log("Calling sendPasswordReset...");
                    $email_sent = $mailer->sendPasswordReset($email, $user_data['full_name'], $reset_link);
                    debug_log("sendPasswordReset returned: " . ($email_sent ? 'true' : 'false'));
                    
                    if ($email_sent) {
                        debug_log("Email sent successfully");
                        $message = '
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Reset link sent successfully!</strong><br>
                            We have sent a password reset link to <strong>' . htmlspecialchars($email) . '</strong>.
                            Please check your inbox (and spam folder).
                        </div>
                        ';
                    } else {
                        $last_error = $mailer->getLastError();
                        debug_log("Email sending failed. Last error: " . $last_error);
                        $message = '
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Reset link generated!</strong><br>
                            However, we couldn\'t send the email. Error: ' . htmlspecialchars($last_error) . '
                        </div>
                        ';
                    }
                    
                } catch (Exception $e) {
                    debug_log("EXCEPTION: " . $e->getMessage());
                    debug_log("Stack trace: " . $e->getTraceAsString());
                    $message = '
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Error: ' . htmlspecialchars($e->getMessage()) . '
                    </div>
                    ';
                }
            } else {
                debug_log("User not found with email: " . $email);
                // Don't reveal if email exists (security)
                $message = '
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    If the email exists in our system, you will receive reset instructions shortly.
                </div>
                ';
            }
        } else {
            debug_log("Email validation failed for: " . $email);
            $error = '
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Please enter a valid email address
            </div>
            ';
        }
    } else {
        debug_log("Email field was empty");
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Please enter your email address
        </div>
        ';
    }
    debug_log("=== Password Reset Request Ended ===");
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
        .debug-info {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow: auto;
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
                        
                        <!-- Debug info (remove in production) -->
                        <div class="debug-info">
                            <strong>Debug Log:</strong><br>
                            <?php
                            if (file_exists($debug_log)) {
                                $logs = file($debug_log);
                                $last_logs = array_slice($logs, -10);
                                echo "<pre>";
                                foreach ($last_logs as $log) {
                                    echo htmlspecialchars($log) . "\n";
                                }
                                echo "</pre>";
                            } else {
                                echo "No debug log yet";
                            }
                            ?>
                        </div>
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