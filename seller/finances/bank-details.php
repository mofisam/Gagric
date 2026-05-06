<?php
require_once __DIR__ . '/../../includes/auth.php';
requireSeller();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../config/paystack.php';

$page_title = "Bank Details";
$page_css = 'dashboard.css';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';
$bank_list_error = '';

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get existing bank details
$bank_details = $db->fetchOne("SELECT * FROM seller_financial_info WHERE seller_id = ?", [$seller_id]);

// Fetch banks from Paystack
$banks = [];
$bank_response = PaystackAPI::getBanks('nigeria');
if (!empty($bank_response['status']) && !empty($bank_response['data']) && is_array($bank_response['data'])) {
    $banks = $bank_response['data'];
} else {
    $bank_list_error = $bank_response['message'] ?? 'Unable to load the bank list from Paystack.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settlement_frequency = $_POST['settlement_frequency'] ?? 'weekly';
    $allowed_frequencies = ['daily', 'weekly', 'monthly'];

    $bank_data = [
        'bank_name' => sanitizeInput($_POST['bank_name'] ?? ''),
        'bank_code' => sanitizeInput($_POST['bank_code'] ?? ''),
        'account_number' => sanitizeInput($_POST['account_number'] ?? ''),
        'account_name' => sanitizeInput($_POST['account_name'] ?? ''),
        'settlement_frequency' => in_array($settlement_frequency, $allowed_frequencies, true) ? $settlement_frequency : 'weekly'
    ];

    // Basic validation
    if (empty($bank_data['bank_name']) || empty($bank_data['bank_code']) || empty($bank_data['account_number'])) {
        $error = 'Please fill in all required fields';
    } elseif (!preg_match('/^[0-9]{10}$/', $bank_data['account_number'])) {
        $error = 'Please enter a valid 10-digit account number';
    } else {
        try {
            // Verify account with Paystack before saving
            $verify_result = PaystackAPI::resolveBankAccount($bank_data['account_number'], $bank_data['bank_code']);

            if (!empty($verify_result['status']) && !empty($verify_result['data']['account_name'])) {
                // Account verified successfully
                $bank_data['account_name'] = $verify_result['data']['account_name'];
                $bank_data['is_bank_verified'] = 1;
                $bank_data['bank_verified_at'] = date('Y-m-d H:i:s');
            } else {
                $error = 'Could not verify account: ' . ($verify_result['message'] ?? 'Unknown error');
            }
            
            if (empty($error)) {
                if ($bank_details) {
                    // Update existing
                    $db->update('seller_financial_info', $bank_data, 'seller_id = ?', [$seller_id]);
                } else {
                    // Insert new
                    $db->insert('seller_financial_info', array_merge($bank_data, ['seller_id' => $seller_id]));
                }
                $success = 'Bank details verified and updated successfully!';
                // Refresh bank details
                $bank_details = $db->fetchOne("SELECT * FROM seller_financial_info WHERE seller_id = ?", [$seller_id]);
            }
        } catch (Exception $e) {
            $error = 'Error updating bank details: ' . $e->getMessage();
        }
    }
}

// Get seller stats for sidebar
$pending_products = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id]);
$low_stock = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id]);
$pending_orders = $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id]);
$today_orders = $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND DATE(o.created_at) = CURDATE()", [$seller_id]);

$seller_stats = [
    'pending_products' => (int)($pending_products['count'] ?? 0),
    'low_stock_count' => (int)($low_stock['count'] ?? 0),
    'pending_orders' => (int)($pending_orders['count'] ?? 0),
    'today_orders' => (int)($today_orders['count'] ?? 0),
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-3" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0">Bank Details</h1>
                        <small class="text-muted">Manage your payout account</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Bank Details</h1>
                    <p class="text-muted mb-0">Manage your bank account for payouts</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="earnings.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Earnings
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($bank_list_error): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($bank_list_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Main Form Column -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bank me-2 text-primary"></i>
                                Update Bank Account
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="bankForm">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="bank_name" class="form-label fw-bold">Select Bank *</label>
                                            <select class="form-select" id="bank_name" name="bank_name" required>
                                                <option value="">-- Choose your bank --</option>
                                                <?php foreach ($banks as $bank): ?>
                                                    <option value="<?php echo htmlspecialchars($bank['name']); ?>" 
                                                            data-code="<?php echo htmlspecialchars($bank['code']); ?>"
                                                            <?php echo ($bank_details['bank_name'] ?? '') == $bank['name'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($bank['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="bank_code" class="form-label fw-bold">Bank Code</label>
                                            <input type="text" class="form-control bg-light" id="bank_code" name="bank_code" 
                                                   value="<?php echo htmlspecialchars($bank_details['bank_code'] ?? ''); ?>" 
                                                   placeholder="Auto-filled" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="account_number" class="form-label fw-bold">Account Number *</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="account_number" name="account_number" 
                                                       value="<?php echo htmlspecialchars($bank_details['account_number'] ?? ''); ?>" 
                                                       maxlength="10" pattern="[0-9]{10}" 
                                                       placeholder="Enter 10-digit account number" required>
                                                <button class="btn btn-outline-primary" type="button" id="verifyAccountBtn" onclick="verifyAccount()">
                                                    <i class="bi bi-check-circle"></i> Verify
                                                </button>
                                            </div>
                                            <div class="form-text">
                                                <i class="bi bi-info-circle"></i> Enter your 10-digit account number
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="account_name" class="form-label fw-bold">Account Name *</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="account_name" name="account_name" 
                                                       value="<?php echo htmlspecialchars($bank_details['account_name'] ?? ''); ?>" 
                                                       placeholder="Will be auto-verified" readonly>
                                                <span class="input-group-text" id="verificationStatus">
                                                    <i class="bi bi-question-circle text-muted"></i>
                                                </span>
                                            </div>
                                            <div class="form-text">
                                                Account name will be verified with Paystack
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="settlement_frequency" class="form-label fw-bold">Payout Frequency</label>
                                    <select class="form-select" id="settlement_frequency" name="settlement_frequency">
                                        <option value="weekly" <?php echo ($bank_details['settlement_frequency'] ?? 'weekly') == 'weekly' ? 'selected' : ''; ?>>Weekly (Every Monday)</option>
                                        <option value="monthly" <?php echo ($bank_details['settlement_frequency'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly (1st of every month)</option>
                                    </select>
                                    <div class="form-text">
                                        <i class="bi bi-calendar"></i> Choose how often you want to receive payouts
                                    </div>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="submit" class="btn btn-success" id="submitBtn">
                                            <i class="bi bi-check-circle me-1"></i> Save Bank Details
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetForm()">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                                        </button>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-shield-check me-1"></i>
                                        Secured by Paystack
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Column -->
                <div class="col-lg-4">
                    <!-- Current Bank Details Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2 text-primary"></i>
                                Current Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($bank_details): ?>
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Bank Name</label>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-bank me-2 text-primary"></i>
                                        <span class="fw-bold"><?php echo htmlspecialchars($bank_details['bank_name']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Account Number</label>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-credit-card me-2 text-primary"></i>
                                        <span class="font-monospace"><?php echo htmlspecialchars($bank_details['account_number']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Account Name</label>
                                    <div><?php echo htmlspecialchars($bank_details['account_name']); ?></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Verification Status</label>
                                    <div>
                                        <?php if ($bank_details['is_bank_verified']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i> Verified
                                            </span>
                                            <?php if ($bank_details['bank_verified_at']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    Verified on <?php echo formatDate($bank_details['bank_verified_at']); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-exclamation-triangle me-1"></i> Pending Verification
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Payout Frequency</label>
                                    <div>
                                        <span class="badge bg-info">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?php echo htmlspecialchars(ucfirst($bank_details['settlement_frequency'] ?? 'weekly')); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-bank2 display-1 text-muted"></i>
                                    <p class="text-muted mt-3 mb-0">No bank details added yet.</p>
                                    <p class="text-muted small">Add your bank account to receive payouts</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payout Information Card -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-cash-stack me-2 text-success"></i>
                                Payout Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <i class="bi bi-currency-dollar text-success me-2"></i>
                                        Minimum Payout
                                    </div>
                                    <span class="fw-bold">₦5,000</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <i class="bi bi-percent text-info me-2"></i>
                                        Commission Rate
                                    </div>
                                    <span class="fw-bold">5%</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <i class="bi bi-clock text-warning me-2"></i>
                                        Processing Time
                                    </div>
                                    <span class="fw-bold">1-3 days</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <i class="bi bi-calendar-check text-primary me-2"></i>
                                        Cut-off Time
                                    </div>
                                    <span class="fw-bold">Friday 12PM</span>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                <small>
                                    Payouts are automatically processed based on your selected frequency.
                                    First payout may take up to 7 days for verification.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Store bank data for quick access
const bankData = <?php echo json_encode($banks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function clearVerificationState() {
    document.getElementById('account_name').value = '';
    
    const icon = document.querySelector('#verificationStatus i');
    if (icon) {
        icon.className = 'bi bi-question-circle text-muted';
    }
}

function updateVerifyButtonState() {
    const verifyBtn = document.getElementById('verifyAccountBtn');
    const accountNumber = document.getElementById('account_number').value;
    const bankCode = document.getElementById('bank_code').value;
    const canVerify = accountNumber.length === 10 && bankCode;

    verifyBtn.disabled = !canVerify;
    verifyBtn.classList.toggle('btn-primary', canVerify);
    verifyBtn.classList.toggle('btn-outline-primary', !canVerify);
}

// Auto-fill bank code when bank is selected
document.getElementById('bank_name').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const bankCode = selectedOption.getAttribute('data-code');
    document.getElementById('bank_code').value = bankCode || '';
    clearVerificationState();
    updateVerifyButtonState();
});

// Account number validation
document.getElementById('account_number').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    clearVerificationState();
    updateVerifyButtonState();
});

// Function to verify account with Paystack
function verifyAccount() {
    const accountNumber = document.getElementById('account_number').value;
    const bankCode = document.getElementById('bank_code').value;
    const bankName = document.getElementById('bank_name').value;
    
    if (!bankName) {
        showToast('Please select a bank first', 'warning');
        return;
    }

    if (!bankCode) {
        showToast('Please select a valid bank first', 'warning');
        return;
    }
    
    if (accountNumber.length !== 10) {
        showToast('Please enter a valid 10-digit account number', 'warning');
        return;
    }
    
    // Show loading state
    const verifyBtn = document.getElementById('verifyAccountBtn');
    const accountNameField = document.getElementById('account_name');
    const statusIcon = document.querySelector('#verificationStatus i');
    
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verifying...';
    accountNameField.value = 'Verifying with Paystack...';
    statusIcon.className = 'bi bi-arrow-repeat text-primary';
    
    // Make AJAX call to verify account
    fetch('verify-account.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            account_number: accountNumber,
            bank_code: bankCode
        })
    })
    .then(async response => {
        const data = await response.json().catch(() => ({
            success: false,
            message: 'Invalid response from verification server'
        }));

        if (!response.ok && !data.message) {
            data.message = 'Verification request failed';
        }

        return data;
    })
    .then(data => {
        if (data.success) {
            accountNameField.value = data.account_name;
            statusIcon.className = 'bi bi-check-circle-fill text-success';
            showToast('Account verified successfully!', 'success');
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
        } else {
            accountNameField.value = '';
            statusIcon.className = 'bi bi-exclamation-circle-fill text-danger';
            showToast('Verification failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        accountNameField.value = '';
        statusIcon.className = 'bi bi-x-circle-fill text-danger';
        showToast('Error verifying account. Please try again.', 'danger');
    })
    .finally(() => {
        verifyBtn.innerHTML = '<i class="bi bi-check-circle"></i> Verify';
        updateVerifyButtonState();
    });
}

// Form validation before submit
document.getElementById('bankForm').addEventListener('submit', function(e) {
    const accountName = document.getElementById('account_name').value;
    const bankName = document.getElementById('bank_name').value;
    const bankCode = document.getElementById('bank_code').value;
    const accountNumber = document.getElementById('account_number').value;
    
    if (!bankName || !bankCode || accountNumber.length !== 10) {
        e.preventDefault();
        showToast('Please select a bank and enter a valid 10-digit account number', 'warning');
        return false;
    }
    
    if (accountName.includes('Verifying')) {
        e.preventDefault();
        showToast('Please wait for account verification to complete', 'warning');
        return false;
    }
});

function resetForm() {
    document.getElementById('bankForm').reset();
    document.getElementById('bank_code').value = '';
    document.getElementById('account_name').value = '';
    const icon = document.querySelector('#verificationStatus i');
    if (icon) {
        icon.className = 'bi bi-question-circle text-muted';
    }
    showToast('Form has been reset', 'info');
}

function escapeHtml(value) {
    const wrapper = document.createElement('div');
    wrapper.textContent = value;
    return wrapper.innerHTML;
}

function showToast(message, type) {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'danger' ? 'bi-exclamation-triangle' : 'bi-info-circle'} me-2"></i>
                ${escapeHtml(message)}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 3000
    });
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

document.addEventListener('DOMContentLoaded', updateVerifyButtonState);

// Add custom styles
const customStyle = document.createElement('style');
customStyle.textContent = `
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .list-group-item {
        border: none;
        background: transparent;
    }
    
    #account_name:valid + #verificationStatus i {
        color: #198754;
    }
`;
document.head.appendChild(customStyle);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>