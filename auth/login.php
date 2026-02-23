<?php
require_once '../classes/Database.php';
require_once '../config/constants.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (!empty($email) && !empty($password)) {
        require_once '../classes/User.php';
        $db = new Database();
        $user = new User($db);
        
        if ($user->login($email, $password)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['user_name'] = $user->first_name . ' ' . $user->last_name;
            
            // Handle "Remember me" - set cookie for 30 days if checked
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (86400 * 30); // 30 days
                setcookie('remember_token', $token, $expires, '/', '', false, true);
                
                // Store token in database (you'll need to add a remember_tokens table)
                // For now, we'll skip actual storage
            }
            
            // Redirect based on role
            if ($user->role === 'admin') {
                header('Location: ../admin/dashboard.php');
            } elseif ($user->role === 'seller') {
                header('Location: ../seller/dashboard.php');
            } else {
                header('Location: ../index.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Green Agric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%,rgb(81, 246, 81) 100%);
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
        .verification-badge {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #198754;
        }
        .email-status {
            transition: all 0.3s ease;
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
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .auth-container {
            width: 100%;
            padding: 15px;
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
        .floating-label {
            position: relative;
        }
        .floating-label .form-control:focus + label,
        .floating-label .form-control:not(:placeholder-shown) + label {
            transform: translateY(-1.5rem) scale(0.85);
            color: #28a745;
        }
    </style>
</head>
<body class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card card shadow">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <img src="../assets/images/logo.jpeg"
                                alt="Green Agric Logo"
                                class="mb-3"
                                style="height:80px; width:auto; border-radius: 10px;">
                            <h2 class="card-title fw-bold text-success">Welcome Back</h2>
                            <p class="text-muted">Sign in to continue your agricultural journey</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                Registration successful! Please login with your credentials.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['verified']) && $_GET['verified'] == 1): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                Email verified successfully! You can now login.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="loginForm">
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">
                                    <i class="bi bi-envelope text-success me-1"></i>Email Address
                                </label>
                                <div class="position-relative">
                                    <input type="email" 
                                           class="form-control form-control-lg email-status" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($email); ?>" 
                                           placeholder="you@example.com"
                                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                                           title="Please enter a valid email address"
                                           autofocus
                                           required>
                                    <span class="verification-badge" id="emailValidationIcon"></span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">
                                    <i class="bi bi-lock text-success me-1"></i>Password
                                </label>
                                <div class="password-input-group">
                                    <input type="password" 
                                           class="form-control form-control-lg" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Enter your password"
                                           required>
                                    <span class="password-toggle" onclick="togglePassword('password', this)">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-4 d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember" 
                                           <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="remember">
                                        <i class="bi bi-check-circle text-success me-1"></i>Remember me
                                    </label>
                                </div>
                                <a href="forgot-password.php" class="text-decoration-none text-success">
                                    <i class="bi bi-question-circle me-1"></i>Forgot password?
                                </a>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-3 mb-3 fw-semibold" id="submitBtn">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>

                            <div class="divider">
                                <span class="px-3 text-muted small">or</span>
                            </div>

                            <div class="text-center mt-4">
                                <p class="mb-0">
                                    Don't have an account? 
                                    <a href="register.php" class="text-success text-decoration-none fw-semibold">
                                        Create account <i class="bi bi-arrow-right"></i>
                                    </a>
                                </p>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="small text-muted">
                                <i class="bi bi-shield-check text-success me-1"></i>
                                Secure login powered by Green Agric
                            </p>
                        </div>
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
                emailHelp.innerHTML = '<i class="bi bi-info-circle me-1"></i>Enter your registered email address';
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

        function isValidEmail(email) {
            const re = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            return re.test(String(email).toLowerCase());
        }

        // Form validation before submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
        });
    </script>
</body>
</html>