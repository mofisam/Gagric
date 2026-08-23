<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/includes/auth.php'; // for getCSRFToken()/validateCSRFToken()

// Already unlocked - send them on their way rather than showing this page again.
if (!empty($_SESSION['site_unlocked'])) {
    $redirect = $_GET['redirect'] ?? (BASE_URL . '/index.php');
    header('Location: ' . $redirect);
    exit;
}

$db = new Database();
$error = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? (BASE_URL . '/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $submitted_password = $_POST['password'] ?? '';

        // Checked against any super-admin's current login password, so
        // there's nothing extra to remember or keep in sync separately -
        // it just changes automatically if a super-admin changes theirs.
        $super_admins = $db->fetchAll("SELECT password_hash FROM users WHERE is_super_admin = 1");

        $matched = false;
        foreach ($super_admins as $admin) {
            if (password_verify($submitted_password, $admin['password_hash'])) {
                $matched = true;
                break;
            }
        }

        if ($matched) {
            $_SESSION['site_unlocked'] = true;
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Incorrect password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - Green Agric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #198754 0%, #0d4a2e 100%);
            font-family: Arial, sans-serif;
        }
        .coming-soon-card {
            max-width: 420px;
            width: 100%;
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        .coming-soon-icon {
            font-size: 3rem;
            color: #198754;
        }
    </style>
</head>
<body>
    <div class="coming-soon-card text-center">
        <div class="coming-soon-icon mb-3">
            <i class="bi bi-cone-striped"></i>
        </div>
        <h2 class="mb-0">Green Agric</h2>
        <h5 class="mb-2 text-warning">Coming soon</h5>
        <p class="text-muted mb-4">We're putting the finishing touches on things. The site isn't open to the public just yet - if you have access, enter the password below.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

            <div class="mb-3 text-start">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control form-control-lg" id="password" name="password" required autofocus>
            </div>

            <button type="submit" class="btn btn-success btn-lg w-100">
                <i class="bi bi-unlock me-1"></i> Enter Site
            </button>
        </form>

        <p class="text-muted small mt-4 mb-0">&copy; <?php echo date('Y'); ?> Green Agric</p>
    </div>
</body>
</html>