<?php
http_response_code(500);
$page_title = "Server Error - Green Agric LTD";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-70">
        <div class="col-md-6 text-center">
            <div class="error-icon mb-4">
                <i class="bi bi-exclamation-octagon display-1 text-danger"></i>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3">500</h1>
            <h2 class="h3 mb-4">Internal Server Error</h2>
            
            <div class="alert alert-warning mb-4">
                <i class="bi bi-tools me-2"></i>
                We're experiencing technical difficulties. Our team has been notified.
            </div>
            
            <p class="text-muted mb-5">
                Something went wrong on our end. Please try again in a few moments. 
                If the problem persists, contact our support team.
            </p>
            
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-center mb-5">
                <a href="index.php" class="btn btn-success btn-lg px-5">
                    <i class="bi bi-house-door me-2"></i> Go to Homepage
                </a>
                <button onclick="window.location.reload()" class="btn btn-outline-success btn-lg px-5">
                    <i class="bi bi-arrow-clockwise me-2"></i> Try Again
                </button>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-clock-history text-success me-2"></i> What to do?
                            </h5>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Refresh the page</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Clear your browser cache</li>
                                <li><i class="bi bi-check-circle text-success me-2"></i> Try again later</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-headset text-success me-2"></i> Contact Support
                            </h5>
                            <p class="mb-0 text-muted">
                                <i class="bi bi-envelope me-2"></i> support@greenagric.shop<br>
                                <i class="bi bi-telephone me-2"></i> +234 800 000 0000
                            </p>
                        </div>
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
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}
</style>

<?php include 'includes/footer.php'; ?>