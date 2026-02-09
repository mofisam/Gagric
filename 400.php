<?php
http_response_code(400);
$page_title = "Bad Request - Green Agric LTD";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-70">
        <div class="col-md-6 text-center">
            <div class="error-icon mb-4">
                <i class="bi bi-x-circle display-1 text-danger"></i>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3">400</h1>
            <h2 class="h3 mb-4">Bad Request</h2>
            
            <div class="alert alert-danger mb-4">
                <i class="bi bi-bug me-2"></i>
                The server cannot process your request due to invalid syntax.
            </div>
            
            <p class="text-muted mb-5">
                There might be an issue with your request. 
                Please check the information you provided and try again.
            </p>
            
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                <a href="javascript:history.back()" class="btn btn-success btn-lg px-5">
                    <i class="bi bi-arrow-left me-2"></i> Go Back
                </a>
                <a href="index.php" class="btn btn-outline-success btn-lg px-5">
                    <i class="bi bi-house-door me-2"></i> Homepage
                </a>
            </div>
            
            <div class="mt-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">Common Issues</h5>
                        <div class="row text-start">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i> Invalid form data</li>
                                    <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i> Missing required fields</li>
                                    <li><i class="bi bi-exclamation-triangle text-warning me-2"></i> File size too large</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i> Invalid file type</li>
                                    <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i> Malformed URL</li>
                                    <li><i class="bi bi-exclamation-triangle text-warning me-2"></i> Corrupted cookies</li>
                                </ul>
                            </div>
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
    animation: spin 3s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<?php include 'includes/footer.php'; ?>