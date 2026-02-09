<?php
http_response_code(404);
$page_title = "Page Not Found - Green Agric LTD";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-70">
        <div class="col-md-6 text-center">
            <div class="error-icon mb-4">
                <i class="bi bi-exclamation-triangle display-1 text-warning"></i>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3">404</h1>
            <h2 class="h3 mb-4">Page Not Found</h2>
            
            <p class="text-muted mb-5">
                The page you're looking for doesn't exist or has been moved. 
                Please check the URL or return to the homepage.
            </p>
            
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                <a href="index.php" class="btn btn-success btn-lg px-5">
                    <i class="bi bi-house-door me-2"></i> Go to Homepage
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-success btn-lg px-5">
                    <i class="bi bi-arrow-left me-2"></i> Go Back
                </a>
            </div>
            
            <div class="mt-5">
                <h5 class="mb-3">Looking for something specific?</h5>
                <div class="row g-3 justify-content-center">
                    <div class="col-md-4">
                        <a href="buyer/products/browse.php" class="card border-0 shadow-sm text-decoration-none h-100">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-basket text-success fs-4 mb-2"></i>
                                <h6 class="mb-0">Browse Products</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="auth/login.php" class="card border-0 shadow-sm text-decoration-none h-100">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-person text-success fs-4 mb-2"></i>
                                <h6 class="mb-0">Login</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="auth/register.php" class="card border-0 shadow-sm text-decoration-none h-100">
                            <div class="card-body text-center p-3">
                                <i class="bi bi-person-plus text-success fs-4 mb-2"></i>
                                <h6 class="mb-0">Register</h6>
                            </div>
                        </a>
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
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-20px);
    }
}

.card {
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
}
</style>

<?php include 'includes/footer.php'; ?>