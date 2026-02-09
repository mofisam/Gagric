<?php
http_response_code(429);
$page_title = "Too Many Requests - Green Agric LTD";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center min-vh-70">
        <div class="col-md-6 text-center">
            <div class="error-icon mb-4">
                <i class="bi bi-hourglass-split display-1 text-warning"></i>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3">429</h1>
            <h2 class="h3 mb-4">Too Many Requests</h2>
            
            <div class="alert alert-warning mb-4">
                <i class="bi bi-speedometer2 me-2"></i>
                You've sent too many requests. Please slow down.
            </div>
            
            <p class="text-muted mb-4">
                To protect our platform, we limit the number of requests from a single user. 
                Please wait a moment before trying again.
            </p>
            
            <div class="countdown-container mb-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-4">
                        <h5 class="mb-3">Try again in:</h5>
                        <div class="countdown display-4 text-success fw-bold" id="countdown">30</div>
                        <small class="text-muted">seconds</small>
                    </div>
                </div>
            </div>
            
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-center mb-5">
                <button onclick="window.location.reload()" class="btn btn-success btn-lg px-5" id="retryBtn" disabled>
                    <i class="bi bi-arrow-clockwise me-2"></i> Try Again
                </button>
                <a href="index.php" class="btn btn-outline-success btn-lg px-5">
                    <i class="bi bi-house-door me-2"></i> Homepage
                </a>
            </div>
            
            <div class="mt-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-2">Rate Limiting Information</h6>
                        <p class="text-muted small mb-0">
                            This is an automated security measure. Limits reset automatically. 
                            If you're using automated tools, please reduce your request frequency.
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
    animation: hourglass 2s infinite;
}

@keyframes hourglass {
    0%, 100% { transform: rotate(0deg); }
    50% { transform: rotate(180deg); }
}

.countdown-container {
    max-width: 200px;
    margin: 0 auto;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let countdown = 30;
    const countdownElement = document.getElementById('countdown');
    const retryBtn = document.getElementById('retryBtn');
    
    const timer = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(timer);
            countdownElement.textContent = '0';
            retryBtn.disabled = false;
            retryBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i> Try Again';
        }
    }, 1000);
});
</script>

<?php include 'includes/footer.php'; ?>