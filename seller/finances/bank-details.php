<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Paystack configuration
//define('PAYSTACK_SECRET_KEY', 'YOUR_PAYSTACK_SECRET_KEY'); // Move to config file

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get existing bank details
$bank_details = $db->fetchOne("SELECT * FROM seller_financial_info WHERE seller_id = ?", [$seller_id]);

// Fetch banks from Paystack
$banks = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/bank?country=nigeria");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $result = json_decode($response, true);
    if ($result['status']) {
        $banks = $result['data'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_data = [
        'bank_name' => sanitizeInput($_POST['bank_name']),
        'bank_code' => sanitizeInput($_POST['bank_code']),
        'account_number' => sanitizeInput($_POST['account_number']),
        'account_name' => sanitizeInput($_POST['account_name']),
        'settlement_frequency' => $_POST['settlement_frequency']
    ];

    // Basic validation
    if (empty($bank_data['bank_name']) || empty($bank_data['account_number']) || empty($bank_data['account_name'])) {
        $error = 'Please fill in all required fields';
    } elseif (!preg_match('/^[0-9]{10}$/', $bank_data['account_number'])) {
        $error = 'Please enter a valid 10-digit account number';
    } else {
        try {
            // Verify account with Paystack before saving
            $verify_url = "https://api.paystack.co/bank/resolve?account_number={$bank_data['account_number']}&bank_code={$bank_data['bank_code']}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $verify_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
            ]);
            $verify_response = curl_exec($ch);
            $verify_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($verify_http_code == 200) {
                $verify_result = json_decode($verify_response, true);
                if ($verify_result['status']) {
                    // Account verified successfully
                    $bank_data['account_name'] = $verify_result['data']['account_name'];
                    $bank_data['is_bank_verified'] = 1;
                    $bank_data['bank_verified_at'] = date('Y-m-d H:i:s');
                } else {
                    $error = 'Could not verify account: ' . ($verify_result['message'] ?? 'Unknown error');
                }
            } else {
                $error = 'Failed to verify account with Paystack. Please try again.';
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
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND DATE(o.created_at) = CURDATE()", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Bank Details";
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
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
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo $success; ?>
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
                                                            data-code="<?php echo $bank['code']; ?>"
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
                                                   value="<?php echo $bank_details['bank_code'] ?? ''; ?>" 
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
                                                       value="<?php echo $bank_details['account_number'] ?? ''; ?>" 
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
                                                       value="<?php echo $bank_details['account_name'] ?? ''; ?>" 
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
                                        <span class="font-monospace"><?php echo $bank_details['account_number']; ?></span>
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
                                            <?php echo ucfirst($bank_details['settlement_frequency'] ?? 'weekly'); ?>
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
const bankData = <?php echo json_encode($banks); ?>;

// Auto-fill bank code when bank is selected
document.getElementById('bank_name').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const bankCode = selectedOption.getAttribute('data-code');
    document.getElementById('bank_code').value = bankCode || '';
});

// Account number validation
document.getElementById('account_number').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    
    // Enable/disable verify button based on length
    const verifyBtn = document.getElementById('verifyAccountBtn');
    if (this.value.length === 10) {
        verifyBtn.disabled = false;
        verifyBtn.classList.remove('btn-outline-primary');
        verifyBtn.classList.add('btn-primary');
    } else {
        verifyBtn.disabled = true;
        verifyBtn.classList.add('btn-outline-primary');
        verifyBtn.classList.remove('btn-primary');
    }
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
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            account_number: accountNumber,
            bank_code: bankCode
        })
    })
    .then(response => response.json())
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
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = '<i class="bi bi-check-circle"></i> Verify';
    });
}

// Form validation before submit
document.getElementById('bankForm').addEventListener('submit', function(e) {
    const accountName = document.getElementById('account_name').value;
    const bankName = document.getElementById('bank_name').value;
    const accountNumber = document.getElementById('account_number').value;
    
    if (!bankName || !accountNumber || !accountName) {
        e.preventDefault();
        showToast('Please fill in all required fields and verify your account', 'warning');
        return false;
    }
    
    if (accountName === 'Verifying with Paystack...' || accountName.includes('Verifying')) {
        e.preventDefault();
        showToast('Please wait for account verification to complete', 'warning');
        return false;
    }
});

function resetForm() {
    document.getElementById('bankForm').reset();
    document.getElementById('bank_code').value = '';
    document.getElementById('account_name').value = '';
    document.getElementById('verificationStatus i').className = 'bi bi-question-circle text-muted';
    showToast('Form has been reset', 'info');
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
                ${message}
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

// Add custom styles
const style = document.createElement('style');
style.textContent = `
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
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>