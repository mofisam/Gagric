<?php
$error_code = $_GET['code'] ?? 'Unknown';
$error_message = $_GET['message'] ?? 'An error occurred';
$page_title = "Error $error_code - Green Agric LTD";

// Set appropriate HTTP status code
switch($error_code) {
    case '404': http_response_code(404); break;
    case '403': http_response_code(403); break;
    case '500': http_response_code(500); break;
    case '400': http_response_code(400); break;
    case '429': http_response_code(429); break;
    case '503': http_response_code(503); break;
    default: http_response_code(500);
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-70">
        <div class="col-md-6 text-center">
            <div class="error-icon mb-4">
                <?php
                $icon = 'bi-exclamation-triangle';
                $color = 'warning';
                
                switch($error_code) {
                    case '404': $icon = 'bi-search'; break;
                    case '403': $icon = 'bi-shield-lock'; $color = 'danger'; break;
                    case '500': $icon = 'bi-exclamation-octagon'; $color = 'danger'; break;
                    case '400': $icon = 'bi-x-circle'; $color = 'danger'; break;
                    case '429': $icon = 'bi-hourglass-split'; break;
                    case '503': $icon = 'bi-wrench'; break;
                }
                ?>
                <i class="bi <?php echo $icon; ?> display-1 text-<?php echo $color; ?>"></i>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3"><?php echo htmlspecialchars($error_code); ?></h1>
            <h2 class="h3 mb-4"><?php echo htmlspecialchars($error_message); ?></h2>
            
            <div class="alert alert-<?php echo $color; ?> mb-4">
                <i class="bi bi-info-circle me-2"></i>
                <?php
                $descriptions = [
                    '404' => 'The requested page could not be found.',
                    '403' => 'You don\'t have permission to access this page.',
                    '500' => 'Internal server error. Our team has been notified.',
                    '400' => 'Bad request. Please check your input.',
                    '429' => 'Too many requests. Please try again later.',
                    '503' => 'Service temporarily unavailable for maintenance.',
                    'Unknown' => 'An unexpected error occurred.'
                ];
                echo htmlspecialchars($descriptions[$error_code] ?? $descriptions['Unknown']);
                ?>
            </div>
            
            <p class="text-muted mb-5">
                <?php if($error_code === '404'): ?>
                    The page you're looking for doesn't exist or has been moved.
                <?php elseif($error_code === '403'): ?>
                    Please ensure you have the proper permissions or try logging in.
                <?php elseif($error_code === '500'): ?>
                    We're working to fix this issue. Please try again shortly.
                <?php elseif($error_code === '400'): ?>
                    There might be an issue with your request. Please try again.
                <?php elseif($error_code === '429'): ?>
                    Please wait a moment before trying again.
                <?php elseif($error_code === '503'): ?>
                    We're performing maintenance to improve our service.
                <?php else: ?>
                    Please try again or contact support if the problem persists.
                <?php endif; ?>
            </p>
            
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-center mb-5">
                <?php if($error_code === '429'): ?>
                    <button onclick="window.location.reload()" class="btn btn-success btn-lg px-5">
                        <i class="bi bi-arrow-clockwise me-2"></i> Try Again
                    </button>
                <?php else: ?>
                    <a href="index.php" class="btn btn-success btn-lg px-5">
                        <i class="bi bi-house-door me-2"></i> Go to Homepage
                    </a>
                <?php endif; ?>
                
                <a href="javascript:history.back()" class="btn btn-outline-success btn-lg px-5">
                    <i class="bi bi-arrow-left me-2"></i> Go Back
                </a>
                
                <?php if(in_array($error_code, ['403', '500', '400'])): ?>
                    <a href="contact.php" class="btn btn-outline-primary btn-lg px-5">
                        <i class="bi bi-headset me-2"></i> Contact Support
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="mt-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">Need Help?</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <p class="mb-2">
                                    <i class="bi bi-envelope text-success me-2"></i>
                                    <strong>Email:</strong>
                                </p>
                                <a href="mailto:support@agrimarketplace.ng" class="text-decoration-none">
                                    support@agrimarketplace.ng
                                </a>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <i class="bi bi-telephone text-success me-2"></i>
                                    <strong>Phone:</strong>
                                </p>
                                <a href="tel:+2348000000000" class="text-decoration-none">
                                    +234 800 000 0000
                                </a>
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
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}
</style>

<?php include 'includes/footer.php'; ?>