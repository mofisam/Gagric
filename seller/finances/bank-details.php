<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get existing bank details
$bank_details = $db->fetchOne("SELECT * FROM seller_financial_info WHERE seller_id = ?", [$seller_id]);

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
            if ($bank_details) {
                // Update existing
                $db->update('seller_financial_info', $bank_data, 'seller_id = ?', [$seller_id]);
            } else {
                // Insert new
                $db->insert('seller_financial_info', array_merge($bank_data, ['seller_id' => $seller_id]));
            }
            $success = 'Bank details updated successfully!';
            // Refresh bank details
            $bank_details = $db->fetchOne("SELECT * FROM seller_financial_info WHERE seller_id = ?", [$seller_id]);
        } catch (Exception $e) {
            $error = 'Error updating bank details: ' . $e->getMessage();
        }
    }
}

$page_title = "Bank Details";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Bank Details</h1>
                <a href="earnings.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Earnings
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Update Bank Account</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="bank_name" class="form-label">Bank Name *</label>
                                            <select class="form-select" id="bank_name" name="bank_name" required>
                                                <option value="">Select Bank</option>
                                                <option value="Access Bank" <?php echo ($bank_details['bank_name'] ?? '') == 'Access Bank' ? 'selected' : ''; ?>>Access Bank</option>
                                                <option value="First Bank" <?php echo ($bank_details['bank_name'] ?? '') == 'First Bank' ? 'selected' : ''; ?>>First Bank</option>
                                                <option value="Guaranty Trust Bank" <?php echo ($bank_details['bank_name'] ?? '') == 'Guaranty Trust Bank' ? 'selected' : ''; ?>>Guaranty Trust Bank</option>
                                                <option value="Zenith Bank" <?php echo ($bank_details['bank_name'] ?? '') == 'Zenith Bank' ? 'selected' : ''; ?>>Zenith Bank</option>
                                                <option value="United Bank for Africa" <?php echo ($bank_details['bank_name'] ?? '') == 'United Bank for Africa' ? 'selected' : ''; ?>>United Bank for Africa</option>
                                                <option value="Union Bank" <?php echo ($bank_details['bank_name'] ?? '') == 'Union Bank' ? 'selected' : ''; ?>>Union Bank</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="bank_code" class="form-label">Bank Code</label>
                                            <input type="text" class="form-control" id="bank_code" name="bank_code" 
                                                   value="<?php echo $bank_details['bank_code'] ?? ''; ?>" 
                                                   placeholder="Will be auto-filled">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="account_number" class="form-label">Account Number *</label>
                                            <input type="text" class="form-control" id="account_number" name="account_number" 
                                                   value="<?php echo $bank_details['account_number'] ?? ''; ?>" 
                                                   maxlength="10" pattern="[0-9]{10}" required>
                                            <div class="form-text">10-digit account number</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="account_name" class="form-label">Account Name *</label>
                                            <input type="text" class="form-control" id="account_name" name="account_name" 
                                                   value="<?php echo $bank_details['account_name'] ?? ''; ?>" readonly>
                                            <div class="form-text">This will be auto-verified</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="settlement_frequency" class="form-label">Payout Frequency</label>
                                    <select class="form-select" id="settlement_frequency" name="settlement_frequency">
                                        <option value="weekly" <?php echo ($bank_details['settlement_frequency'] ?? 'weekly') == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo ($bank_details['settlement_frequency'] ?? '') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    </select>
                                    <div class="form-text">How often you want to receive payouts</div>
                                </div>

                                <button class="btn btn-success">Save Bank Details</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Current Bank Details -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Details</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($bank_details): ?>
                                <div class="mb-3">
                                    <strong>Bank:</strong><br>
                                    <?php echo $bank_details['bank_name']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Account Number:</strong><br>
                                    <?php echo $bank_details['account_number']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Account Name:</strong><br>
                                    <?php echo $bank_details['account_name']; ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Status:</strong><br>
                                    <span class="badge bg-<?php echo $bank_details['is_bank_verified'] ? 'success' : 'warning'; ?>">
                                        <?php echo $bank_details['is_bank_verified'] ? 'Verified' : 'Pending Verification'; ?>
                                    </span>
                                </div>
                                <?php if ($bank_details['bank_verified_at']): ?>
                                    <div class="mb-3">
                                        <strong>Verified On:</strong><br>
                                        <?php echo formatDate($bank_details['bank_verified_at']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">No bank details added yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payout Information -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Payout Information</h5>
                        </div>
                        <div class="card-body">
                            <ul class="small">
                                <li>Payouts are processed automatically</li>
                                <li>Minimum payout: <?php echo formatCurrency(MIN_PAYOUT_AMOUNT); ?></li>
                                <li>Commission rate: <?php echo COMMISSION_RATE; ?>%</li>
                                <li>Processing time: 1-3 business days</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Auto-fill account name when account number is entered
document.getElementById('account_number').addEventListener('blur', function() {
    const accountNumber = this.value;
    if (accountNumber.length === 10) {
        // In a real application, you would call Paystack's resolve account API
        // For demo purposes, we'll simulate it
        document.getElementById('account_name').value = 'Verifying...';
        
        setTimeout(() => {
            // Simulate API response
            document.getElementById('account_name').value = 'Verified Account Holder';
        }, 1000);
    }
});

// Auto-fill bank code when bank is selected
document.getElementById('bank_name').addEventListener('change', function() {
    const bankCodes = {
        'Access Bank': '044',
        'First Bank': '011',
        'Guaranty Trust Bank': '058',
        'Zenith Bank': '057',
        'United Bank for Africa': '033',
        'Union Bank': '032'
    };
    document.getElementById('bank_code').value = bankCodes[this.value] || '';
});
</script>

<?php include '../../includes/footer.php'; ?>