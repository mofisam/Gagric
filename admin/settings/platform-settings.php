<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = new Database();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real application, you'd save these to a settings table
    $settings = [
        'site_name' => $_POST['site_name'],
        'site_email' => $_POST['site_email'],
        'site_phone' => $_POST['site_phone'],
        'commission_rate' => $_POST['commission_rate'],
        'min_payout_amount' => $_POST['min_payout_amount'],
        'currency' => $_POST['currency'],
        'order_auto_cancel_days' => $_POST['order_auto_cancel_days'],
        'low_stock_threshold' => $_POST['low_stock_threshold'],
        'max_product_images' => $_POST['max_product_images'],
        'product_approval_required' => isset($_POST['product_approval_required']) ? 1 : 0,
        'seller_approval_required' => isset($_POST['seller_approval_required']) ? 1 : 0,
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0
    ];
    
    setFlashMessage('Platform settings updated successfully', 'success');
    header('Location: platform-settings.php');
    exit;
}

// Get current settings (in real app, fetch from database)
$current_settings = [
    'site_name' => 'Green Agric',
    'site_email' => 'support@greenagric.ng',
    'site_phone' => '+2347030419150',
    'commission_rate' => '5.0',
    'min_payout_amount' => '5000.00',
    'currency' => 'NGN',
    'order_auto_cancel_days' => '7',
    'low_stock_threshold' => '10',
    'max_product_images' => '5',
    'product_approval_required' => 1,
    'seller_approval_required' => 1,
    'email_notifications' => 1,
    'sms_notifications' => 0
];

$page_title = "Platform Settings";
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile page header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-2" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="flex-grow-1">
                        <h1 class="h5 mb-0 text-center">Platform Settings</h1>
                        <small class="text-muted d-block text-center">Configure your marketplace</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileResetForm">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Platform Settings</h1>
                    <p class="text-muted mb-0">Configure your marketplace settings</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetForm()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Reset Form
                    </button>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <form method="POST" id="settingsForm" class="settings-form">
                <!-- General Settings Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2 text-primary"></i>
                            General Settings
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#generalCollapse">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse d-md-block show" id="generalCollapse">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="site_name" name="site_name" 
                                               value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" 
                                               placeholder="Site Name" required>
                                        <label for="site_name" class="form-label">Site Name</label>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="site_email" name="site_email" 
                                               value="<?php echo htmlspecialchars($current_settings['site_email']); ?>" 
                                               placeholder="support@example.com" required>
                                        <label for="site_email" class="form-label">Support Email</label>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="tel" class="form-control" id="site_phone" name="site_phone" 
                                               value="<?php echo htmlspecialchars($current_settings['site_phone']); ?>" 
                                               placeholder="+2347030419150" required>
                                        <label for="site_phone" class="form-label">Support Phone</label>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="currency" name="currency" required>
                                            <option value="NGN" <?php echo $current_settings['currency'] === 'NGN' ? 'selected' : ''; ?>>Nigerian Naira (₦)</option>
                                            <option value="USD" <?php echo $current_settings['currency'] === 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                            <option value="EUR" <?php echo $current_settings['currency'] === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                            <option value="GBP" <?php echo $current_settings['currency'] === 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                                        </select>
                                        <label for="currency" class="form-label">Default Currency</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Business Settings Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-graph-up me-2 text-success"></i>
                            Business Settings
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#businessCollapse">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse d-md-block show" id="businessCollapse">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="commission_rate" name="commission_rate" 
                                               value="<?php echo htmlspecialchars($current_settings['commission_rate']); ?>" 
                                               min="0" max="50" step="0.1" placeholder="5.0" required>
                                        <label for="commission_rate" class="form-label">Commission Rate (%)</label>
                                        <div class="form-text mt-1">Percentage commission charged on each sale</div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="min_payout_amount" name="min_payout_amount" 
                                               value="<?php echo htmlspecialchars($current_settings['min_payout_amount']); ?>" 
                                               min="0" step="0.01" placeholder="5000.00" required>
                                        <label for="min_payout_amount" class="form-label">Minimum Payout Amount</label>
                                        <div class="form-text mt-1">Minimum amount required for seller payout</div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="order_auto_cancel_days" name="order_auto_cancel_days" 
                                               value="<?php echo htmlspecialchars($current_settings['order_auto_cancel_days']); ?>" 
                                               min="1" max="30" placeholder="7" required>
                                        <label for="order_auto_cancel_days" class="form-label">Order Auto-Cancel (Days)</label>
                                        <div class="form-text mt-1">Automatically cancel unpaid orders after X days</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Settings Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-box-seam me-2 text-warning"></i>
                            Product Settings
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#productCollapse">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse d-md-block show" id="productCollapse">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" 
                                               value="<?php echo htmlspecialchars($current_settings['low_stock_threshold']); ?>" 
                                               min="1" placeholder="10" required>
                                        <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                        <div class="form-text mt-1">Show low stock warning when quantity falls below this number</div>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="max_product_images" name="max_product_images" 
                                               value="<?php echo htmlspecialchars($current_settings['max_product_images']); ?>" 
                                               min="1" max="10" placeholder="5" required>
                                        <label for="max_product_images" class="form-label">Maximum Product Images</label>
                                        <div class="form-text mt-1">Maximum number of images allowed per product</div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="row g-3">
                                        <div class="col-6 col-md-3">
                                            <div class="form-check card-check">
                                                <input class="form-check-input" type="checkbox" id="product_approval_required" 
                                                       name="product_approval_required" value="1" 
                                                       <?php echo $current_settings['product_approval_required'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="product_approval_required">
                                                    <i class="bi bi-shield-check text-primary me-2"></i>
                                                    <div>
                                                        <strong>Product Approval</strong>
                                                        <small class="d-block text-muted">Require admin approval for new products</small>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-6 col-md-3">
                                            <div class="form-check card-check">
                                                <input class="form-check-input" type="checkbox" id="seller_approval_required" 
                                                       name="seller_approval_required" value="1"
                                                       <?php echo $current_settings['seller_approval_required'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="seller_approval_required">
                                                    <i class="bi bi-person-check text-success me-2"></i>
                                                    <div>
                                                        <strong>Seller Approval</strong>
                                                        <small class="d-block text-muted">Require admin approval for new sellers</small>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bell me-2 text-info"></i>
                            Notification Settings
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#notificationCollapse">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse d-md-block show" id="notificationCollapse">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <div class="form-check card-check">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" 
                                               name="email_notifications" value="1"
                                               <?php echo $current_settings['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            <i class="bi bi-envelope text-primary me-2"></i>
                                            <div>
                                                <strong>Email</strong>
                                                <small class="d-block text-muted">Enable email notifications</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-6 col-md-3">
                                    <div class="form-check card-check">
                                        <input class="form-check-input" type="checkbox" id="sms_notifications" 
                                               name="sms_notifications" value="1"
                                               <?php echo $current_settings['sms_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sms_notifications">
                                            <i class="bi bi-chat-text text-success me-2"></i>
                                            <div>
                                                <strong>SMS</strong>
                                                <small class="d-block text-muted">Enable SMS notifications</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="border-top pt-3 mt-3">
                                        <h6 class="mb-3">Notification Types</h6>
                                        <div class="row g-2">
                                            <div class="col-6 col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="new_order_notify" checked>
                                                    <label class="form-check-label" for="new_order_notify">
                                                        <i class="bi bi-bag me-1"></i> New Orders
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="payment_notify" checked>
                                                    <label class="form-check-label" for="payment_notify">
                                                        <i class="bi bi-credit-card me-1"></i> Payments
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="product_approval_notify" checked>
                                                    <label class="form-check-label" for="product_approval_notify">
                                                        <i class="bi bi-check-circle me-1"></i> Approvals
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="low_stock_notify" checked>
                                                    <label class="form-check-label" for="low_stock_notify">
                                                        <i class="bi bi-exclamation-triangle me-1"></i> Low Stock
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Integration Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-credit-card me-2 text-danger"></i>
                            Payment Integration
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#paymentCollapse">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="collapse d-md-block show" id="paymentCollapse">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="paystack_public_key" 
                                               value="pk_test_xxxxxxxxxxxx" placeholder="Paystack Public Key">
                                        <label for="paystack_public_key" class="form-label">Paystack Public Key</label>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="paystack_secret_key" 
                                               value="sk_test_xxxxxxxxxxxx" placeholder="Paystack Secret Key">
                                        <label for="paystack_secret_key" class="form-label">Paystack Secret Key</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Test Mode Active</strong>
                                        <p class="mb-0">Your payment gateway is currently in test mode. Transactions will not process real payments.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Actions -->
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                                    <button type="submit" class="btn btn-primary btn-lg px-5 py-3">
                                        <i class="bi bi-save me-2"></i>
                                        <span>Save All Settings</span>
                                    </button>
                                    
                                    <button type="reset" class="btn btn-outline-secondary btn-lg px-5 py-3">
                                        <i class="bi bi-arrow-clockwise me-2"></i>
                                        <span>Reset to Defaults</span>
                                    </button>
                                    
                                    <button type="button" class="btn btn-outline-danger btn-lg px-5 py-3" 
                                            onclick="confirmReset()">
                                        <i class="bi bi-trash me-2"></i>
                                        <span>Clear All</span>
                                    </button>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Settings are automatically saved when you click "Save All Settings"
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (window.dashboardSidebar) {
                window.dashboardSidebar.toggle();
            }
        });
    }
    
    // Mobile reset form button
    const mobileResetBtn = document.getElementById('mobileResetForm');
    if (mobileResetBtn) {
        mobileResetBtn.addEventListener('click', resetForm);
    }
    
    // Form validation
    const form = document.getElementById('settingsForm');
    form.addEventListener('submit', function(e) {
        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                // Add error message
                if (!field.nextElementSibling?.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'This field is required';
                    field.parentNode.appendChild(errorDiv);
                }
            } else {
                field.classList.remove('is-invalid');
                const errorDiv = field.parentNode.querySelector('.invalid-feedback');
                if (errorDiv) errorDiv.remove();
                
                // Validate email
                if (field.type === 'email' && !isValidEmail(field.value)) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'Please enter a valid email address';
                    field.parentNode.appendChild(errorDiv);
                }
                
                // Validate numbers
                if (field.type === 'number') {
                    const min = parseFloat(field.getAttribute('min'));
                    const max = parseFloat(field.getAttribute('max'));
                    const value = parseFloat(field.value);
                    
                    if (!isNaN(min) && value < min) {
                        isValid = false;
                        field.classList.add('is-invalid');
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = `Minimum value is ${min}`;
                        field.parentNode.appendChild(errorDiv);
                    }
                    
                    if (!isNaN(max) && value > max) {
                        isValid = false;
                        field.classList.add('is-invalid');
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = `Maximum value is ${max}`;
                        field.parentNode.appendChild(errorDiv);
                    }
                }
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            agriApp.showToast('Please fix the errors in the form', 'error');
            // Scroll to first error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        } else {
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split spin me-2"></i>Saving...';
            submitBtn.disabled = true;
            
            // In real app, you would have AJAX submission here
            // For now, just reset after a delay
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 1500);
        }
    });
    
    // Real-time validation
    form.querySelectorAll('input, select').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const errorDiv = this.parentNode.querySelector('.invalid-feedback');
            if (errorDiv) errorDiv.remove();
        });
        
        field.addEventListener('change', function() {
            this.classList.remove('is-invalid');
            const errorDiv = this.parentNode.querySelector('.invalid-feedback');
            if (errorDiv) errorDiv.remove();
        });
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function resetForm() {
    if (confirm('Reset all settings to their current values?')) {
        document.getElementById('settingsForm').reset();
        // Remove any validation errors
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        document.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });
        agriApp.showToast('Form reset to current values', 'info');
    }
}

function confirmReset() {
    if (confirm('Are you sure you want to clear all settings? This will reset all fields to empty.')) {
        document.getElementById('settingsForm').reset();
        // Set all checkboxes to unchecked
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
        });
        agriApp.showToast('All settings cleared', 'warning');
    }
}

// Add CSS for settings form
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Settings Form Styles */
    .settings-form .card {
        transition: transform 0.2s;
    }
    
    .settings-form .card:hover {
        transform: translateY(-2px);
    }
    
    .settings-form .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    
    /* Form floating labels */
    .form-floating > label {
        padding-left: 0.75rem;
    }
    
    .form-floating > .form-control:focus ~ label,
    .form-floating > .form-control:not(:placeholder-shown) ~ label {
        padding-left: 0.75rem;
    }
    
    /* Card checkbox style */
    .card-check {
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        transition: all 0.2s;
        cursor: pointer;
        height: 100%;
    }
    
    .card-check:hover {
        border-color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.05);
    }
    
    .card-check .form-check-input {
        margin-top: 0.25rem;
    }
    
    .card-check .form-check-label {
        display: flex;
        align-items: flex-start;
        width: 100%;
    }
    
    .card-check .form-check-label i {
        font-size: 1.5rem;
        margin-right: 0.75rem;
        flex-shrink: 0;
    }
    
    .card-check .form-check-label div {
        flex-grow: 1;
    }
    
    /* Mobile optimizations */
    @media (max-width: 767.98px) {
        .settings-form .card {
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .settings-form .card-header {
            padding: 0.75rem 1rem;
        }
        
        .settings-form .card-body {
            padding: 1rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating > .form-control,
        .form-floating > .form-select {
            padding: 1rem 0.75rem;
            height: calc(3.5rem + 2px);
            min-height: 56px;
        }
        
        .form-floating > label {
            padding: 1rem 0.75rem;
        }
        
        .card-check {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .card-check .form-check-label i {
            font-size: 1.25rem;
            margin-right: 0.5rem;
        }
        
        /* Button adjustments */
        .btn-lg {
            padding: 0.75rem 1rem;
            font-size: 1rem;
            width: 100%;
            margin-bottom: 0.5rem;
        }
        
        /* Better mobile header */
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Improved form sections */
        .collapse:not(.show) {
            display: none;
        }
        
        .collapse.show {
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    }
    
    /* Desktop optimizations */
    @media (min-width: 768px) {
        .settings-form .card-check {
            min-height: 120px;
        }
        
        .settings-form .card-check .form-check-label {
            align-items: center;
        }
    }
    
    /* Focus states */
    .form-control:focus, .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    /* Invalid states */
    .form-control.is-invalid, .form-select.is-invalid {
        border-color: #dc3545;
        padding-right: calc(1.5em + 0.75rem);
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    
    .invalid-feedback {
        display: block;
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>