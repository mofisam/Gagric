<?php
http_response_code(503);
$page_title = "Service Unavailable - Green Agric LTD";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-70">
        <div class="col-md-6 text-center">
            <div class="error-icon mb-4">
                <i class="bi bi-wrench display-1 text-warning"></i>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3">503</h1>
            <h2 class="h3 mb-4">Service Unavailable</h2>
            
            <div class="alert alert-info mb-4">
                <i class="bi bi-tools me-2"></i>
                We're currently undergoing maintenance. We'll be back shortly.
            </div>
            
            <div class="maintenance-info mb-5">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    <i class="bi bi-calendar-check text-success me-2"></i> Estimated Time
                                </h5>
                                <p class="mb-0 fw-bold text-success">30-60 minutes</p>
                                <small class="text-muted">We're working to restore service as soon as possible</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-3">
                                    <i class="bi bi-lightning-charge text-success me-2"></i> Status Updates
                                </h5>
                                <p class="mb-0">Follow us for updates:</p>
                                <div class="mt-2">
                                    <a href="#" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="bi bi-twitter"></i>
                                    </a>
                                    <a href="#" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-facebook"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <p class="text-muted mb-5">
                We're performing scheduled maintenance to improve your experience. 
                Thank you for your patience.
            </p>
            
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                <button onclick="window.location.reload()" class="btn btn-success btn-lg px-5">
                    <i class="bi bi-arrow-clockwise me-2"></i> Check Status
                </button>
                <a href="javascript:history.back()" class="btn btn-outline-success btn-lg px-5">
                    <i class="bi bi-arrow-left me-2"></i> Go Back
                </a>
            </div>
            
            <div class="mt-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">What we're improving:</h5>
                        <ul class="list-unstyled text-start">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Server performance upgrades</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Security enhancements</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> New features implementation</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Bug fixes and optimizations</li>
                        </ul>
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
    animation: wrench 2s infinite;
}

@keyframes wrench {
    0% { transform: rotate(0deg); }
    25% { transform: rotate(15deg); }
    50% { transform: rotate(0deg); }
    75% { transform: rotate(-15deg); }
    100% { transform: rotate(0deg); }
}

.maintenance-info .card {
    transition: transform 0.3s ease;
}

.maintenance-info .card:hover {
    transform: translateY(-5px);
}
</style>

<?php include 'includes/footer.php'; ?>