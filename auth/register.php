<?php
require_once '../classes/Database.php';
require_once '../config/constants.php';
require_once '../includes/validation.php';
require_once '../classes/Mailer.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';
$email_verification_sent = false;

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'buyer';
    
    // Use validation functions
    $validation_errors = [];
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
        $validation_errors[] = 'All fields are required';
    }
    
    if ($password !== $confirm_password) {
        $validation_errors[] = 'Passwords do not match';
    }
    
    if (!validateEmail($email)) {
        $validation_errors[] = 'Please enter a valid email address';
    }
    
    if (!validatePhone($phone)) {
        $validation_errors[] = 'Please enter a valid Nigerian phone number (e.g., 08012345678 or +2348012345678)';
    }
    
    if (!validatePassword($password)) {
        $validation_errors[] = 'Password must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number';
    }
    
    if (!isset($_POST['terms'])) {
        $validation_errors[] = 'You must agree to the Terms of Service and Privacy Policy';
    }
    
    if (!empty($validation_errors)) {
        $error = implode('<br>', $validation_errors);
    } else {
        require_once '../classes/User.php';
        
        $db = new Database();
        $user = new User($db);
        
        // Check if user already exists
        $existing = $db->fetchOne("SELECT id, is_email_verified FROM users WHERE email = ? OR phone = ?", [$email, $phone]);
        if ($existing) {
            $error = 'User with this email or phone already exists';
        } else {
            // Generate email verification token
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $uuid = generateUUID();

            // Prepare user data with verification token
            $userData = [
                'uuid' => $uuid,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => $role,
                'email_verification_token' => $verification_token,
                'email_verification_expires' => $verification_expires,
                'is_email_verified' => 0
            ];
            
            // First, create the user
            $user_id = $user->register($userData);
            
            if ($user_id) {
                // Send verification email
                try {
                    $mailer = new Mailer();
                    $verification_link = BASE_URL . "/auth/verify-email.php?token=" . $verification_token . "&email=" . urlencode($email);
                    
                    // Get user's full name
                    $user_name = $first_name . ' ' . $last_name;
                    
                    // Send verification email
                    $email_sent = $mailer->sendEmailVerification($email, $user_name, $verification_link);
                    
                    if ($email_sent) {
                        $email_verification_sent = true;
                        $success = 'Registration successful! Please check your email to verify your account.';
                    } else {
                        // Log the error but don't stop registration
                        error_log("Failed to send verification email to: " . $email);
                        $success = 'Registration successful! However, we couldn\'t send the verification email. Please contact support.';
                    }
                    
                    // Clear form
                    $_POST = [];
                } catch (Exception $e) {
                    error_log("Mailer Error: " . $e->getMessage());
                    $success = 'Registration successful! However, we couldn\'t send the verification email. You can request a new verification email from your profile.';
                }
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Register - Green Agric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="../assets/css/auth.css" rel="stylesheet">
    <style>
        .password-input-group {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
        .password-toggle:hover {
            color: #198754;
        }
        .password-strength {
            margin-top: 8px;
        }
        .strength-bar {
            height: 4px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        .strength-text {
            font-size: 12px;
            margin-top: 4px;
            text-align: right;
        }
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
            padding-left: 0;
            list-style: none;
        }
        .password-requirements li {
            margin-bottom: 2px;
        }
        .password-requirements li.valid {
            color: #198754;
        }
        .password-requirements li.invalid {
            color: #dc3545;
        }
        .password-requirements i {
            margin-right: 5px;
            font-size: 10px;
        }
        .verification-badge {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #198754;
        }
        .email-status {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="auth-card card shadow">
                    <div class="card-body p-4 p-lg-2">
                        <div class="text-center mb-4">
                            <img src="../assets/images/logo.jpeg"
                                alt="Green Agric Logo"
                                class="mb-3"
                                style="height:70px; width:auto;">
                            <h2 class="card-title fw-bold text-success">Join Green Agric</h2>
                            <p class="text-muted">Create your account to start buying and selling agricultural products</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                                <?php if ($email_verification_sent): ?>
                                    <hr>
                                    <p class="mb-0 small">
                                        <i class="bi bi-envelope-fill me-1"></i>
                                        We've sent a verification link to <strong><?php echo htmlspecialchars($email); ?></strong>. 
                                        Please check your inbox and spam folder.
                                    </p>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="registrationForm" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label fw-semibold">
                                            <i class=" text-success me-1"></i>First Name
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-lg" 
                                               id="first_name" 
                                               name="first_name" 
                                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label fw-semibold">
                                            <i class="text-success me-1"></i>Last Name
                                        </label>
                                        <input type="text" 
                                               class="form-control form-control-lg" 
                                               id="last_name" 
                                               name="last_name" 
                                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">
                                    <i class="text-success me-1"></i>Email Address
                                </label>
                                <div class="position-relative">
                                    <input type="email" 
                                           class="form-control form-control-lg email-status" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="you@example.com"
                                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                           title="Please enter a valid email address"
                                           required>
                                    <span class="verification-badge" id="emailValidationIcon"></span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label fw-semibold">
                                    <i class="text-success me-1"></i>Phone Number
                                </label>
                                <input type="tel" 
                                       class="form-control form-control-lg" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                       placeholder="eg.,08012345678"
                                       required>
                                <div class="form-text">
                                    <i class="me-1"></i>
                                    Enter your Nigerian phone number
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    I want to join as:
                                </label>
                                <div class="role-selector d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="role" id="role_buyer" 
                                               value="buyer" <?php echo ($_POST['role'] ?? 'buyer') === 'buyer' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="role_buyer">
                                            <i class="bi bi-cart text-success me-1"></i>Buyer
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="role" id="role_seller" 
                                               value="seller" <?php echo ($_POST['role'] ?? '') === 'seller' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="role_seller">
                                            <i class="bi bi-shop text-success me-1"></i>Seller
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">
                                    <i class="text-success me-1"></i>Password
                                </label>
                                <div class="password-input-group">
                                    <input type="password" 
                                           class="form-control form-control-lg" 
                                           id="password" 
                                           name="password" 
                                           required>
                                    <span class="password-toggle" onclick="togglePassword('password', this)">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                                <div class="password-strength">
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar strength-bar" id="passwordStrengthBar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="strength-text" id="passwordStrengthText">Enter password</small>
                                </div>
                                <ul class="password-requirements" id="passwordRequirements">
                                    <li class="invalid" id="req-length">
                                        <i class="bi bi-x-circle"></i> At least 8 characters
                                    </li>
                                    <li class="invalid" id="req-uppercase">
                                        <i class="bi bi-x-circle"></i> At least one uppercase letter
                                    </li>
                                    <li class="invalid" id="req-lowercase">
                                        <i class="bi bi-x-circle"></i> At least one lowercase letter
                                    </li>
                                    <li class="invalid" id="req-number">
                                        <i class="bi bi-x-circle"></i> At least one number
                                    </li>
                                </ul>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">
                                    <i class="text-success me-1"></i>Confirm Password
                                </label>
                                <div class="password-input-group">
                                    <input type="password" 
                                           class="form-control form-control-lg" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required>
                                    <span class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback" id="passwordMatchFeedback" style="display: none;">
                                    Passwords do not match
                                </div>
                            </div>

                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="../terms-and-conditions" class="text-success text-decoration-none">Terms of Service</a> 
                                    and <a href="../privacy-policy" class="text-success text-decoration-none">Privacy Policy</a>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-3 mb-3 fw-semibold" id="submitBtn">
                                <i class="me-2"></i>Create Account
                            </button>
<hr>
                            <div class="text-center">
                                <p class="mb-0">
                                    Already have an account? 
                                    <a href="login.php" class="text-success text-decoration-none fw-semibold">
                                        Sign in 
                                    </a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        function togglePassword(inputId, element) {
            const input = document.getElementById(inputId);
            const icon = element.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Real-time email validation
        document.getElementById('email').addEventListener('input', function(e) {
            const email = this.value;
            const icon = document.getElementById('emailValidationIcon');
            const emailHelp = document.getElementById('emailHelp');
            
            if (email.length === 0) {
                icon.innerHTML = '';
                this.classList.remove('is-valid', 'is-invalid');
                emailHelp.innerHTML = '<i class="bi bi-info-circle me-1"></i>We\'ll send a verification link to this email';
                emailHelp.classList.remove('text-success', 'text-danger');
            } else if (isValidEmail(email)) {
                icon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
                emailHelp.innerHTML = '<i class="bi bi-check-circle me-1 text-success"></i>Valid email address';
                emailHelp.classList.add('text-success');
                emailHelp.classList.remove('text-danger');
            } else {
                icon.innerHTML = '<i class="bi bi-exclamation-circle-fill text-danger"></i>';
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
                emailHelp.innerHTML = '<i class="bi bi-exclamation-circle me-1 text-danger"></i>Please enter a valid email address';
                emailHelp.classList.add('text-danger');
                emailHelp.classList.remove('text-success');
            }
        });

        // Password strength checker
        document.getElementById('password').addEventListener('input', function(e) {
            const password = this.value;
            checkPasswordStrength(password);
            checkPasswordMatch();
        });

        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        function isValidEmail(email) {
            const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return re.test(String(email).toLowerCase());
        }

        function checkPasswordStrength(password) {
            const bar = document.getElementById('passwordStrengthBar');
            const text = document.getElementById('passwordStrengthText');
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            // Update requirement indicators
            updateRequirement('req-length', hasLength);
            updateRequirement('req-uppercase', hasUppercase);
            updateRequirement('req-lowercase', hasLowercase);
            updateRequirement('req-number', hasNumber);
            
            // Calculate strength
            const requirements = [hasLength, hasUppercase, hasLowercase, hasNumber];
            const metCount = requirements.filter(Boolean).length;
            
            let strengthPercent = 0;
            let strengthText = '';
            let barColor = '';
            
            if (password.length === 0) {
                strengthPercent = 0;
                strengthText = 'Enter password';
                barColor = '';
            } else if (metCount <= 2) {
                strengthPercent = 25;
                strengthText = 'Weak';
                barColor = 'bg-danger';
            } else if (metCount === 3) {
                strengthPercent = 50;
                strengthText = 'Fair';
                barColor = 'bg-warning';
            } else if (metCount === 4) {
                strengthPercent = 100;
                strengthText = 'Strong';
                barColor = 'bg-success';
            }
            
            bar.style.width = strengthPercent + '%';
            bar.className = 'progress-bar strength-bar ' + barColor;
            text.textContent = strengthText;
            text.className = 'strength-text text-' + (barColor.replace('bg-', '') || 'muted');
        }

        function updateRequirement(elementId, isValid) {
            const element = document.getElementById(elementId);
            const icon = element.querySelector('i');
            
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                icon.classList.remove('bi-x-circle');
                icon.classList.add('bi-check-circle');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                icon.classList.remove('bi-check-circle');
                icon.classList.add('bi-x-circle');
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const feedback = document.getElementById('passwordMatchFeedback');
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    document.getElementById('confirm_password').classList.add('is-valid');
                    document.getElementById('confirm_password').classList.remove('is-invalid');
                    feedback.style.display = 'none';
                } else {
                    document.getElementById('confirm_password').classList.add('is-invalid');
                    document.getElementById('confirm_password').classList.remove('is-valid');
                    feedback.style.display = 'block';
                }
            } else {
                document.getElementById('confirm_password').classList.remove('is-valid', 'is-invalid');
                feedback.style.display = 'none';
            }
        }

        // Form validation before submit
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const email = document.getElementById('email').value;
            const terms = document.getElementById('terms').checked;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address!');
                return;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('You must agree to the Terms of Service and Privacy Policy!');
                return;
            }
            
            const requirements = [
                password.length >= 8,
                /[A-Z]/.test(password),
                /[a-z]/.test(password),
                /[0-9]/.test(password)
            ];
            
            if (!requirements.every(Boolean)) {
                e.preventDefault();
                alert('Please meet all password requirements!');
                return;
            }
        });
    </script>
</body>
</html>