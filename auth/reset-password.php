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

$error = '';
$success = '';
$valid_token = false;
$email = '';
$token = '';

// Check if token is provided
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = '
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Invalid or missing reset link
    </div>
    ';
} else {
    $db = new Database();
    
    // Verify token against database
    $reset = $db->fetchOne("
        SELECT pr.*, u.id as user_id, u.email as user_email 
        FROM password_resets pr 
        JOIN users u ON pr.email = u.email 
        WHERE pr.token = ? 
        AND pr.expires_at > NOW() 
        AND pr.used_at IS NULL 
        AND u.is_active = TRUE
    ", [$token]);
    
    if (!$reset) {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Invalid or expired reset link</strong><br>
            This password reset link has expired or has already been used.
        </div>
        ';
    } else {
        $valid_token = true;
        $email = $reset['user_email'];
        $token_id = $reset['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Both password fields are required
        </div>
        ';
    } elseif ($password !== $confirm_password) {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Passwords do not match
        </div>
        ';
    } elseif (strlen($password) < 8) {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Password must be at least 8 characters long
        </div>
        ';
    } else {
        // Verify token again (prevent race condition)
        $reset = $db->fetchOne("SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW()", [$token]);
        
        if (!$reset) {
            $error = '
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                This reset link has already been used or has expired
            </div>
            ';
        } else {
            // Update user's password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $db->conn->begin_transaction();
            
            try {
                // Update user password
                $db->query("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?", 
                          [$password_hash, $reset['email']]);
                
                // Mark token as used
                $db->query("UPDATE password_resets SET used_at = NOW() WHERE token = ?", [$token]);
                
                // Delete expired tokens for this email
                $db->query("DELETE FROM password_resets WHERE email = ? AND expires_at <= NOW()", [$reset['email']]);
                
                // Log password change
                $db->query("
                    INSERT INTO user_logs (user_id, action, ip_address, user_agent) 
                    VALUES ((SELECT id FROM users WHERE email = ?), 'password_reset', ?, ?)
                ", [$reset['email'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
                $db->conn->commit();
                
                $success = '
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Password reset successful!</strong><br>
                    Your password has been updated. You can now login with your new password.
                </div>
                ';
                
            } catch (Exception $e) {
                $db->conn->rollback();
                $error = '
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to reset password. Please try again.
                </div>
                ';
                error_log("Password reset error: " . $e->getMessage());
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
    <title>Set New Password - Green Agric LTD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../assets/css/auth.css" rel="stylesheet">
    <style>
        .password-strength {
            height: 4px;
            background: #dee2e6;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #fd7e14; width: 50%; }
        .strength-good { background: #ffc107; width: 75%; }
        .strength-strong { background: #198754; width: 100%; }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .requirement-met {
            color: #198754;
        }
        .requirement-not-met {
            color: #dc3545;
        }
    </style>
</head>
<body class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="text-success mb-3" style="font-size: 3rem;">
                                <i class="bi bi-key"></i>
                            </div>
                            <h2 class="card-title fw-bold">Set New Password</h2>
                            <?php if ($valid_token): ?>
                                <p class="text-muted mb-0">Create a new password for your account</p>
                                <small class="text-muted">Logged in as: <?php echo htmlspecialchars($email); ?></small>
                            <?php endif; ?>
                        </div>

                        <?php if ($error): ?>
                            <?php echo $error; ?>
                            <div class="text-center mt-3">
                                <a href="forgot-password.php" class="btn btn-outline-success">
                                    <i class="bi bi-arrow-left me-1"></i> Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <?php echo $success; ?>
                            <div class="text-center mt-4">
                                <a href="login.php" class="btn btn-success">
                                    <i class="bi bi-box-arrow-in-right me-2"></i> Go to Login
                                </a>
                            </div>
                        <?php elseif ($valid_token): ?>

                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter new password" required minlength="8">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm new password" required minlength="8">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="text-danger small mt-1" id="passwordMatchError" style="display: none;">
                                    <i class="bi bi-x-circle me-1"></i> Passwords do not match
                                </div>
                            </div>

                            <!-- Password Requirements -->
                            <div class="password-requirements mb-4">
                                <p class="mb-2"><strong>Password must contain:</strong></p>
                                <div class="row">
                                    <div class="col-6">
                                        <div id="reqLength" class="requirement-not-met">
                                            <i class="bi bi-circle me-1"></i> At least 8 characters
                                        </div>
                                        <div id="reqLowercase" class="requirement-not-met">
                                            <i class="bi bi-circle me-1"></i> One lowercase letter
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div id="reqUppercase" class="requirement-not-met">
                                            <i class="bi bi-circle me-1"></i> One uppercase letter
                                        </div>
                                        <div id="reqNumber" class="requirement-not-met">
                                            <i class="bi bi-circle me-1"></i> One number
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg py-2" id="submitBtn" disabled>
                                    <i class="bi bi-check-circle me-2"></i> Reset Password
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i> Back to Login
                            </a>
                        </div>

                        <?php else: ?>
                            <div class="text-center">
                                <a href="forgot-password.php" class="btn btn-outline-success">
                                    <i class="bi bi-arrow-left me-1"></i> Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentNode.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        
        // Check password strength
        function checkPasswordStrength(password) {
            let strength = 0;
            
            // Check length
            if (password.length >= 8) strength++;
            
            // Check for lowercase
            if (/[a-z]/.test(password)) strength++;
            
            // Check for uppercase
            if (/[A-Z]/.test(password)) strength++;
            
            // Check for numbers
            if (/[0-9]/.test(password)) strength++;
            
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        // Update password requirements
        function updateRequirements(password) {
            const reqLength = document.getElementById('reqLength');
            const reqLowercase = document.getElementById('reqLowercase');
            const reqUppercase = document.getElementById('reqUppercase');
            const reqNumber = document.getElementById('reqNumber');
            
            // Update icons and classes
            reqLength.innerHTML = (password.length >= 8 ? 
                '<i class="bi bi-check-circle me-1"></i>' : 
                '<i class="bi bi-circle me-1"></i>') + ' At least 8 characters';
            reqLength.className = password.length >= 8 ? 'requirement-met' : 'requirement-not-met';
            
            reqLowercase.innerHTML = (/[a-z]/.test(password) ? 
                '<i class="bi bi-check-circle me-1"></i>' : 
                '<i class="bi bi-circle me-1"></i>') + ' One lowercase letter';
            reqLowercase.className = /[a-z]/.test(password) ? 'requirement-met' : 'requirement-not-met';
            
            reqUppercase.innerHTML = (/[A-Z]/.test(password) ? 
                '<i class="bi bi-check-circle me-1"></i>' : 
                '<i class="bi bi-circle me-1"></i>') + ' One uppercase letter';
            reqUppercase.className = /[A-Z]/.test(password) ? 'requirement-met' : 'requirement-not-met';
            
            reqNumber.innerHTML = (/[0-9]/.test(password) ? 
                '<i class="bi bi-check-circle me-1"></i>' : 
                '<i class="bi bi-circle me-1"></i>') + ' One number';
            reqNumber.className = /[0-9]/.test(password) ? 'requirement-met' : 'requirement-not-met';
            
            // Update strength bar
            const strength = checkPasswordStrength(password);
            const strengthBar = document.getElementById('passwordStrength');
            strengthBar.className = 'password-strength';
            
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-fair');
            } else if (strength === 4) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }
        
        // Check if passwords match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchError = document.getElementById('passwordMatchError');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword && password !== confirmPassword) {
                matchError.style.display = 'block';
                submitBtn.disabled = true;
                return false;
            } else {
                matchError.style.display = 'none';
                return true;
            }
        }
        
        // Enable submit button only if all requirements are met
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            const strength = checkPasswordStrength(password);
            const hasMatch = password === confirmPassword && confirmPassword.length > 0;
            const hasMinLength = password.length >= 8;
            const hasLowercase = /[a-z]/.test(password);
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            const isValid = hasMatch && hasMinLength && hasLowercase && hasUppercase && hasNumber;
            
            submitBtn.disabled = !isValid;
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    updateRequirements(this.value);
                    checkPasswordMatch();
                    validateForm();
                });
                
                passwordField.addEventListener('keyup', validateForm);
            }
            
            if (confirmField) {
                confirmField.addEventListener('input', function() {
                    checkPasswordMatch();
                    validateForm();
                });
                
                confirmField.addEventListener('keyup', validateForm);
            }
            
            // Focus on password field
            if (passwordField && document.getElementById('passwordForm')) {
                passwordField.focus();
            }
        });
        
        // Form submission
        document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Resetting...';
        });
    </script>
</body>
</html>