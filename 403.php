<?php
http_response_code(403);
$page_title = "Access Denied - Green Agric LTD";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-70">
        <div class="col-md-6 text-center">
            <div class="error-icon mb-4">
                <i class="bi bi-shield-lock display-1 text-danger"></i>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3">403</h1>
            <h2 class="h3 mb-4">Access Denied</h2>
            
            <div class="alert alert-danger mb-4">
                <i class="bi bi-exclamation-octagon me-2"></i>
                You don't have permission to access this page.
            </div>
            
            <p class="text-muted mb-4">
                This page requires special permissions or authentication. 
                Please ensure you're logged in with the correct account.
            </p>
            
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-success btn-lg px-5">
                        <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                    </a>
                    <a href="auth/logout.php" class="btn btn-outline-danger btn-lg px-5">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-success btn-lg px-5">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Login
                    </a>
                    <a href="auth/register.php" class="btn btn-outline-success btn-lg px-5">
                        <i class="bi bi-person-plus me-2"></i> Register
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary btn-lg px-5">
                    <i class="bi bi-house-door me-2"></i> Homepage
                </a>
            </div>
            
            <div class="mt-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">Need Help?</h5>
                        <p class="text-muted small mb-0">
                            If you believe this is an error, please contact our support team.
                            <a href="mailto:support@greenagric.shop" class="text-success">support@greenagric.shop</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.min-vh-70 {
    min-height: 70vh;
}

.error-icon {
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}
</style>

<?php include 'includes/footer.php'; ?>