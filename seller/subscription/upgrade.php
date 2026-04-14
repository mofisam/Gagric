<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

// Initialize database connection
$db = new Database();
$seller_id = $_SESSION['user_id'];

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get seller information
$seller = $db->fetchOne("
    SELECT u.id, u.email, u.first_name, u.last_name, u.phone,
           sp.business_name
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    WHERE u.id = ?
", [$seller_id]);

if (!$seller) {
    die("Seller not found. Please contact support.");
}

// Get current subscription
$current_subscription = $db->fetchOne("
    SELECT ss.*, p.name as plan_name, p.price as plan_price
    FROM seller_subscriptions ss
    LEFT JOIN subscription_plans p ON ss.plan_id = p.id
    WHERE ss.seller_id = ? AND ss.is_active = 1 AND ss.end_date > NOW()
    ORDER BY ss.id DESC LIMIT 1
", [$seller_id]);

// Get all active subscription plans
$plans = $db->fetchAll("
    SELECT * FROM subscription_plans 
    WHERE is_active = 1 
    ORDER BY price ASC
");

if (empty($plans)) {
    die("No subscription plans available. Please contact support.");
}

// Paystack Configuration
$paystack_public_key = 'pk_test_3d8772ab51c1407f1302d2fffc114220b0b1d9ee'; // REPLACE WITH YOUR ACTUAL TEST KEY

$page_title = "Upgrade Subscription";
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Choose Your Plan</h1>
                    <p class="text-muted mb-0">Select the perfect plan for your agricultural business</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="../dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Current Subscription Info -->
            <?php if ($current_subscription): ?>
                <div class="alert alert-info mb-4 d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Current Plan:</strong> <?php echo htmlspecialchars($current_subscription['plan_name'] ?? 'Free Trial'); ?>
                        <span class="mx-2">•</span>
                        Valid until: <?php echo date('F j, Y', strtotime($current_subscription['end_date'])); ?>
                    </div>
                    <a href="manage.php" class="btn btn-sm btn-outline-primary mt-2 mt-sm-0">
                        <i class="bi bi-gear me-1"></i> Manage
                    </a>
                </div>
            <?php endif; ?>

            <!-- Subscription Plans Row -->
            <div class="row g-4 mb-5">
                <?php foreach ($plans as $index => $plan): 
                    $features = json_decode($plan['features'], true);
                    $is_popular = ($plan['display_order'] == 2);
                    $plan_price = (float)$plan['price'];
                ?>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm border-0 plan-card position-relative" 
                             data-plan-id="<?php echo $plan['id']; ?>"
                             data-plan-price="<?php echo $plan_price; ?>"
                             data-plan-name="<?php echo htmlspecialchars($plan['name']); ?>"
                             data-plan-duration="<?php echo $plan['duration_days']; ?>">
                            
                            <?php if ($is_popular): ?>
                                <div class="popular-badge">
                                    <span>Most Popular</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-header bg-white border-0 text-center pt-4 pb-3">
                                <h3 class="h4 mb-0"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                <div class="mt-3">
                                    <span class="display-5 fw-bold text-success plan-price-display" id="price-display-<?php echo $plan['id']; ?>">
                                        ₦<?php echo number_format($plan_price, 2); ?>
                                    </span>
                                    <span class="text-muted">/<?php echo $plan['duration_days']; ?> days</span>
                                    <div class="plan-original-price small text-muted mt-1" id="original-price-<?php echo $plan['id']; ?>" style="display: none;">
                                        <span class="text-decoration-line-through">₦<?php echo number_format($plan_price, 2); ?></span>
                                        <span class="text-success ms-2" id="savings-<?php echo $plan['id']; ?>"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <?php if (is_array($features)): ?>
                                        <?php foreach ($features as $feature): ?>
                                            <li class="mb-2">
                                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                                <?php echo htmlspecialchars($feature); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            
                            <div class="card-footer bg-white border-0 pb-4 text-center">
                                <button type="button" 
                                        class="btn btn-lg w-100 subscribe-btn <?php echo $is_popular ? 'btn-success' : 'btn-outline-success'; ?>" 
                                        data-plan-id="<?php echo $plan['id']; ?>"
                                        data-plan-name="<?php echo htmlspecialchars($plan['name']); ?>"
                                        data-plan-price="<?php echo $plan_price; ?>"
                                        data-plan-original="<?php echo $plan_price; ?>">
                                    <?php if ($current_subscription && $current_subscription['plan_id'] == $plan['id']): ?>
                                        Current Plan
                                    <?php else: ?>
                                        Get Started
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Coupon Section - Clean & Minimal -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-ticket-perforated fs-4 text-primary me-2"></i>
                                <div>
                                    <h6 class="mb-0">Have a coupon?</h6>
                                    <small class="text-muted">Enter code for discount</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 mt-3 mt-md-0">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       id="coupon_code_input" 
                                       placeholder="Enter coupon code"
                                       autocomplete="off">
                                <button class="btn btn-primary" type="button" id="apply_coupon_btn">
                                    Apply
                                </button>
                            </div>
                            <div id="coupon_message" class="mt-2"></div>
                        </div>
                        <div class="col-md-3 mt-3 mt-md-0 text-md-end">
                            <div id="applied_coupon_display" style="display: none;">
                                <span class="badge bg-success p-2">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    <span id="applied_coupon_code"></span> applied
                                    <button type="button" id="remove_coupon_btn" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;"></button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Modal - Clean & Professional -->
            <div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title">Complete Payment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body pt-0">
                            <!-- Order Summary -->
                            <div class="border rounded-3 p-4 mb-4">
                                <h6 class="mb-3">Order Summary</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Plan:</span>
                                    <strong id="modal_plan_name" class="text-dark"></strong>
                                </div>
                                <div id="modal_original_price_row" style="display: none;">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Original Price:</span>
                                        <span class="text-decoration-line-through text-muted" id="modal_original_price"></span>
                                    </div>
                                </div>
                                <div id="modal_discount_row" style="display: none;">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Discount:</span>
                                        <span class="text-success" id="modal_discount"></span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between pt-2 border-top mt-2">
                                    <span class="fw-bold">Total:</span>
                                    <strong class="text-success fs-4" id="modal_amount"></strong>
                                </div>
                            </div>
                            
                            <!-- Payment Info -->
                            <div class="text-center">
                                <img src="https://paystack.com/assets/img/paystack-logo-og.png" alt="Paystack" height="30" class="mb-3">
                                <p class="small text-muted mb-0">
                                    <i class="bi bi-shield-check me-1"></i> 
                                    Secure payment powered by Paystack
                                </p>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success px-4" id="confirmPaymentBtn">
                                <i class="bi bi-lock me-1"></i> Pay Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Paystack Script -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<script>
// Global variables
let selectedPlan = {
    id: 0,
    name: '',
    amount: 0,
    original_amount: 0
};

let activeCoupon = null;

// Format number as currency
function formatNumber(amount) {
    return amount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Show toast notification
function showToast(message, type = 'success') {
    // Check if toast container exists, create if not
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-info');
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
}

// Validate coupon via AJAX
async function validateCoupon(code, planId) {
    try {
        const response = await fetch('ajax-validate-coupon.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                coupon_code: code,
                plan_id: planId
            })
        });
        
        if (!response.ok) throw new Error('Network error');
        return await response.json();
    } catch (error) {
        return { success: false, message: 'Error validating coupon. Please try again.' };
    }
}

// Update plan prices based on active coupon
function updatePlanPrices(couponData) {
    const planCards = document.querySelectorAll('.plan-card');
    
    planCards.forEach(card => {
        const planId = parseInt(card.getAttribute('data-plan-id'));
        const originalPrice = parseFloat(card.getAttribute('data-plan-price'));
        const priceDisplay = document.getElementById(`price-display-${planId}`);
        const originalPriceDiv = document.getElementById(`original-price-${planId}`);
        const savingsSpan = document.getElementById(`savings-${planId}`);
        const subscribeBtn = card.querySelector('.subscribe-btn');
        
        if (couponData && couponData.plan_id === planId) {
            // Apply discount to this plan
            const discountedPrice = originalPrice - couponData.discount;
            priceDisplay.innerHTML = `₦${formatNumber(discountedPrice)}`;
            originalPriceDiv.style.display = 'block';
            savingsSpan.innerHTML = `Save ₦${formatNumber(couponData.discount)}`;
            
            // Update button data attributes
            subscribeBtn.setAttribute('data-plan-price', discountedPrice);
            subscribeBtn.setAttribute('data-plan-original', originalPrice);
        } else if (couponData && couponData.plan_id !== planId) {
            // Show original price for other plans
            priceDisplay.innerHTML = `₦${formatNumber(originalPrice)}`;
            originalPriceDiv.style.display = 'none';
            
            // Reset button
            subscribeBtn.setAttribute('data-plan-price', originalPrice);
            subscribeBtn.setAttribute('data-plan-original', originalPrice);
        } else {
            // No coupon - reset all
            priceDisplay.innerHTML = `₦${formatNumber(originalPrice)}`;
            originalPriceDiv.style.display = 'none';
            subscribeBtn.setAttribute('data-plan-price', originalPrice);
            subscribeBtn.setAttribute('data-plan-original', originalPrice);
        }
    });
}

// Apply coupon handler
document.getElementById('apply_coupon_btn').addEventListener('click', async function() {
    const couponCode = document.getElementById('coupon_code_input').value.trim().toUpperCase();
    const messageDiv = document.getElementById('coupon_message');
    const applyBtn = this;
    
    if (!couponCode) {
        showToast('Please enter a coupon code', 'error');
        return;
    }
    
    // Get the first plan's ID (or currently active plan)
    const firstPlan = document.querySelector('.plan-card');
    const planId = firstPlan ? parseInt(firstPlan.getAttribute('data-plan-id')) : null;
    
    if (!planId) return;
    
    // Show loading state
    applyBtn.disabled = true;
    applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
    messageDiv.innerHTML = '<div class="text-info small"><i class="bi bi-hourglass-split me-1"></i>Validating...</div>';
    
    // Validate coupon
    const result = await validateCoupon(couponCode, planId);
    
    if (result.success) {
        activeCoupon = result.coupon;
        updatePlanPrices(activeCoupon);
        
        // Show applied coupon display
        document.getElementById('applied_coupon_code').textContent = activeCoupon.code;
        document.getElementById('applied_coupon_display').style.display = 'block';
        
        // Clear message and input
        messageDiv.innerHTML = '';
        document.getElementById('coupon_code_input').value = '';
        
        showToast(result.message, 'success');
        
    } else {
        messageDiv.innerHTML = `<div class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i>${result.message}</div>`;
        setTimeout(() => {
            messageDiv.innerHTML = '';
        }, 3000);
        showToast(result.message, 'error');
    }
    
    // Reset button
    applyBtn.disabled = false;
    applyBtn.innerHTML = 'Apply';
});

// Remove coupon handler
document.getElementById('remove_coupon_btn').addEventListener('click', async function() {
    activeCoupon = null;
    updatePlanPrices(null);
    document.getElementById('applied_coupon_display').style.display = 'none';
    showToast('Coupon removed', 'info');
});

// Handle subscribe button clicks
document.querySelectorAll('.subscribe-btn').forEach(button => {
    button.addEventListener('click', function() {
        const planId = parseInt(this.getAttribute('data-plan-id'));
        const planName = this.getAttribute('data-plan-name');
        const planPrice = parseFloat(this.getAttribute('data-plan-price'));
        const originalPrice = parseFloat(this.getAttribute('data-plan-original'));
        
        if (this.textContent.trim() === 'Current Plan') {
            showToast('This is your current plan', 'info');
            return;
        }
        
        selectedPlan.id = planId;
        selectedPlan.name = planName;
        selectedPlan.amount = planPrice;
        selectedPlan.original_amount = originalPrice;
        
        const discount = originalPrice - planPrice;
        
        // Update modal content
        document.getElementById('modal_plan_name').textContent = planName;
        document.getElementById('modal_amount').textContent = `₦${formatNumber(planPrice)}`;
        
        if (discount > 0) {
            document.getElementById('modal_original_price').textContent = `₦${formatNumber(originalPrice)}`;
            document.getElementById('modal_original_price_row').style.display = 'block';
            document.getElementById('modal_discount').textContent = `- ₦${formatNumber(discount)}`;
            document.getElementById('modal_discount_row').style.display = 'block';
        } else {
            document.getElementById('modal_original_price_row').style.display = 'none';
            document.getElementById('modal_discount_row').style.display = 'none';
        }
        
        // Show modal with animation
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
    });
});

// Process payment when confirm button is clicked
document.getElementById('confirmPaymentBtn').addEventListener('click', function() {
    if (selectedPlan.id === 0) {
        showToast('Please select a plan first', 'error');
        return;
    }
    
    // Disable button
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
    
    // Calculate amount in kobo
    const amountInKobo = Math.round(selectedPlan.amount * 100);
    
    // Generate unique reference
    const reference = 'SUB_' + Date.now() + '_' + Math.floor(Math.random() * 1000000);
    
    // Get seller info
    const sellerEmail = '<?php echo $seller['email']; ?>';
    const sellerFirstName = '<?php echo addslashes($seller['first_name']); ?>';
    const sellerLastName = '<?php echo addslashes($seller['last_name']); ?>';
    const sellerPhone = '<?php echo $seller['phone']; ?>';
    
    // Initialize Paystack
    const handler = PaystackPop.setup({
        key: '<?php echo $paystack_public_key; ?>',
        email: sellerEmail,
        amount: amountInKobo,
        currency: 'NGN',
        ref: reference,
        firstname: sellerFirstName,
        lastname: sellerLastName,
        phone: sellerPhone,
        metadata: {
            custom_fields: [
                { display_name: "Plan ID", variable_name: "plan_id", value: selectedPlan.id },
                { display_name: "Seller ID", variable_name: "seller_id", value: <?php echo $seller_id; ?> },
                { display_name: "Coupon ID", variable_name: "coupon_id", value: activeCoupon ? activeCoupon.id : '' },
                { display_name: "Subscription Type", variable_name: "subscription_type", value: "paid" }
            ]
        },
        callback: function(response) {
            window.location.href = 'payment-callback.php?reference=' + response.reference + '&status=success';
        },
        onClose: function() {
            document.getElementById('confirmPaymentBtn').disabled = false;
            document.getElementById('confirmPaymentBtn').innerHTML = '<i class="bi bi-lock me-1"></i> Pay Now';
        }
    });
    
    handler.openIframe();
});

// Allow Enter key to apply coupon
document.getElementById('coupon_code_input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('apply_coupon_btn').click();
    }
});
</script>

<style>
/* Card Styles */
.plan-card {
    transition: all 0.3s ease;
    overflow: hidden;
}

.plan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.1) !important;
}

/* Popular Badge */
.popular-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 1;
}

/* Button Styles */
.btn-success {
    background: linear-gradient(135deg, #28a745, #20c997);
    border: none;
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

.btn-outline-success {
    transition: all 0.3s ease;
}

.btn-outline-success:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, #28a745, #20c997);
    border-color: transparent;
}

/* Price Animation */
.plan-price-display {
    transition: all 0.3s ease;
}

/* Coupon Section */
#apply_coupon_btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

#applied_coupon_display .badge {
    font-size: 0.85rem;
    padding: 8px 12px;
}

/* Modal Styles */
.modal-content {
    border-radius: 16px;
    border: none;
}

.modal-header {
    padding: 1.5rem 1.5rem 0.5rem 1.5rem;
}

.modal-body {
    padding: 0.5rem 1.5rem 1rem 1.5rem;
}

.modal-footer {
    padding: 0.5rem 1.5rem 1.5rem 1.5rem;
}

/* Toast Container */
.toast-container {
    z-index: 1100;
}

.toast {
    border-radius: 12px;
    font-size: 0.875rem;
}

/* Loading Spinner */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
    border-width: 0.15em;
}

/* List Styles */
.list-unstyled li {
    font-size: 0.9rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .display-5 {
        font-size: 1.75rem;
    }
    
    .plan-card {
        margin-bottom: 1rem;
    }
    
    .btn-lg {
        padding: 0.5rem 1rem;
        font-size: 1rem;
    }
}

/* Feature Check Icon */
.bi-check-circle-fill {
    font-size: 0.85rem;
}

/* Input Focus */
.form-control:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
}

.btn-primary:focus {
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
}
</style>

<?php include '../../includes/footer.php'; ?>