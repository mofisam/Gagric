<?php
session_start();

// Prevent direct access without registration
if (!isset($_SESSION['registration_email'])) {
    header('Location: register.php');
    exit;
}

$email = $_SESSION['registration_email'];
$email_sent = $_SESSION['email_sent'] ?? false;

// Clear session vars so page can't be revisited
unset($_SESSION['registration_email'], $_SESSION['email_sent']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Green Agric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, rgb(81, 246, 81) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            background: rgba(255,255,255,0.95);
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="success-card card p-5 text-center">
                    <i class="bi bi-check-circle-fill success-icon mb-4"></i>
                    <h2 class="fw-bold text-success mb-3">You're registered!</h2>

                    <?php if ($email_sent): ?>
                        <p class="text-muted mb-2">A verification link has been sent to:</p>
                        <p class="fw-semibold mb-4"><?php echo htmlspecialchars($email); ?></p>
                        <p class="text-muted small mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            Please check your inbox and spam folder, then click the link to activate your account.
                        </p>
                    <?php else: ?>
                        <p class="text-muted mb-4">
                            Your account was created but we couldn't send a verification email to
                            <strong><?php echo htmlspecialchars($email); ?></strong>.
                            Please contact support or request a new verification email from your profile.
                        </p>
                    <?php endif; ?>

                    <a href="../" class="btn btn-outline-secondary w-100 py-2 mt-2">
                        <i class="bi bi-house me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>