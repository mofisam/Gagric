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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (!empty($email) && !empty($password)) {
        require_once '../classes/User.php';
        $db = new Database();
        $user = new User($db);
        
        if ($user->login($email, $password)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['user_name'] = $user->first_name;
            
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
    <link href="../assets/css/auth.css" rel="stylesheet">
</head>
<body class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="auth-card card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-1">
                            <img src="../assets/images/logo.jpeg"
                                alt="Green Agric Logo"
                                class="mb-1"
                                style="height:100px; width:auto;">

                            <h2 class="card-title fw-bold text-success">Welcome Back</h2>
                            <p class="text-muted">Sign in to your account</p>
                        </div>


                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 mb-3">Sign In</button>

                            <div class="text-center">
                                <a href="forgot-password.php" class="text-decoration-none">Forgot your password?</a>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="mb-0">Don't have an account? 
                                <a href="register.php" class="text-success text-decoration-none fw-semibold">Sign up</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/auth.js"></script>
</body>
</html>