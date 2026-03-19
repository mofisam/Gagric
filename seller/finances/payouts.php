<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get payout summary
$payout_summary = $db->fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN net_amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END), 0) as total_pending,
        COALESCE(SUM(CASE WHEN status = 'processing' THEN net_amount ELSE 0 END), 0) as total_processing,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN net_amount ELSE 0 END), 0) as total_failed,
        COUNT(*) as total_payouts,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_count,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
    FROM seller_payouts 
    WHERE seller_id = ?
", [$seller_id]);

// Get bank details
$bank_details = $db->fetchOne("
    SELECT * FROM seller_financial_info 
    WHERE seller_id = ? AND is_bank_verified = 1
", [$seller_id]);

// Build query for payouts
$where = "sp.seller_id = ?";
$params = [$seller_id];

if ($status_filter) {
    $where .= " AND sp.status = ?";
    $params[] = $status_filter;
}

// Get total count for pagination
$total_payouts = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM seller_payouts sp
    WHERE $where
", $params)['count'];

$total_pages = ceil($total_payouts / $limit);

// Get payouts with details
$payouts = $db->fetchAll("
    SELECT 
        sp.*,
        oi.product_name,
        o.order_number,
        o.created_at as order_date,
        DATE(sp.created_at) as payout_date,
        TIME(sp.created_at) as payout_time
    FROM seller_payouts sp
    LEFT JOIN order_items oi ON sp.order_item_id = oi.id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE $where
    ORDER BY sp.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Get earnings available for payout
$available_earnings = $db->fetchOne("
    SELECT 
        COALESCE(SUM(oi.item_total - (oi.item_total * " . COMMISSION_RATE . " / 100)), 0) as available
    FROM order_items oi
    WHERE oi.seller_id = ? 
        AND oi.status = 'delivered'
        AND NOT EXISTS (
            SELECT 1 FROM seller_payouts sp 
            WHERE sp.order_item_id = oi.id AND sp.status IN ('paid', 'processing', 'pending')
        )
", [$seller_id])['available'];

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Payouts";
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
                        <h1 class="h5 mb-0">Payouts</h1>
                        <small class="text-muted">Manage your earnings and payouts</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Payouts</h1>
                    <p class="text-muted mb-0">Track and manage your earnings payouts</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="earnings.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-graph-up me-1"></i> Earnings
                        </a>
                        <a href="bank-details.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-bank me-1"></i> Bank Details
                        </a>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" onclick="requestPayout()" 
                            <?php echo $available_earnings < MIN_PAYOUT_AMOUNT ? 'disabled' : ''; ?>>
                        <i class="bi bi-cash-stack me-1"></i> Request Payout
                    </button>
                </div>
            </div>

            <!-- Available Balance Alert -->
            <?php if ($available_earnings >= MIN_PAYOUT_AMOUNT): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-cash-stack fs-3 me-3"></i>
                        <div>
                            <strong>₦<?php echo number_format($available_earnings, 2); ?></strong> available for payout
                            <?php if (!$bank_details): ?>
                                <br>
                                <span class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    Please add and verify your bank details first
                                </span>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-success ms-auto" onclick="requestPayout()" <?php echo !$bank_details ? 'disabled' : ''; ?>>
                            <i class="bi bi-cash-stack me-1"></i> Request Payout Now
                        </button>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Payout Summary Cards -->
            <div class="row g-3 mb-4">
                <!-- Total Paid -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Paid</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($payout_summary['total_paid'], 2); ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <?php echo $payout_summary['paid_count']; ?> payouts
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cash-stack fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Payouts -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card  shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($payout_summary['total_pending'], 2); ?></h3>
                                    <small class="text-warning">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo $payout_summary['pending_count']; ?> pending
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-clock-history fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Processing -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Processing</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($payout_summary['total_processing'], 2); ?></h3>
                                    <small class="text-info">
                                        <i class="bi bi-gear me-1"></i>
                                        <?php echo $payout_summary['processing_count']; ?> processing
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-arrow-repeat fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Balance -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Available</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($available_earnings, 2); ?></h3>
                                    <small class="text-primary">
                                        <i class="bi bi-wallet2 me-1"></i>
                                        Ready for payout
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-wallet2 fs-4 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Account Info -->
            <?php if ($bank_details): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light p-3 rounded-circle me-3">
                                        <i class="bi bi-bank text-primary fs-4"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">Bank Account</small>
                                        <strong><?php echo htmlspecialchars($bank_details['bank_name']); ?></strong>
                                        <br>
                                        <span class="font-monospace">
                                            <?php echo $bank_details['account_number']; ?> - <?php echo htmlspecialchars($bank_details['account_name']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i> Verified
                                </span>
                                <a href="bank-details.php" class="btn btn-sm btn-outline-primary ms-2">
                                    <i class="bi bi-pencil"></i> Update
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 mb-4 bg-warning bg-opacity-10">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle text-warning fs-3 me-3"></i>
                            <div>
                                <strong class="text-warning">No verified bank account found</strong>
                                <p class="mb-0 text-muted">Please add and verify your bank details to receive payouts.</p>
                            </div>
                            <a href="bank-details.php" class="btn btn-warning ms-auto">
                                <i class="bi bi-bank me-1"></i> Add Bank Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payouts Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-ul me-2 text-primary"></i>
                        Payout History
                    </h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary <?php echo !$status_filter ? 'active' : ''; ?>" onclick="filterStatus('')">All</button>
                        <button class="btn btn-outline-warning <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" onclick="filterStatus('pending')">Pending</button>
                        <button class="btn btn-outline-info <?php echo $status_filter == 'processing' ? 'active' : ''; ?>" onclick="filterStatus('processing')">Processing</button>
                        <button class="btn btn-outline-success <?php echo $status_filter == 'paid' ? 'active' : ''; ?>" onclick="filterStatus('paid')">Paid</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($payouts): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Order</th>
                                        <th>Product</th>
                                        <th class="text-end">Gross Amount</th>
                                        <th class="text-end">Commission</th>
                                        <th class="text-end">Net Amount</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payouts as $payout): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo formatDate($payout['payout_date'], 'M j, Y'); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $payout['payout_time']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="font-monospace small">
                                                    <?php echo $payout['order_number'] ?? 'N/A'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($payout['product_name']): ?>
                                                    <span class="fw-bold"><?php echo htmlspecialchars($payout['product_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">Bulk payout</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                ₦<?php echo number_format($payout['amount'], 2); ?>
                                            </td>
                                            <td class="text-end text-danger">
                                                -₦<?php echo number_format($payout['commission_amount'], 2); ?>
                                            </td>
                                            <td class="text-end text-success fw-bold">
                                                ₦<?php echo number_format($payout['net_amount'], 2); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'processing' => 'info',
                                                    'paid' => 'success',
                                                    'failed' => 'danger'
                                                ];
                                                $color = $status_colors[$payout['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($payout['status']); ?>
                                                </span>
                                                <?php if ($payout['status'] == 'paid' && $payout['paid_at']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo formatDate($payout['paid_at'], 'M j'); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($payout['paystack_transfer_reference']): ?>
                                                    <span class="badge bg-light text-dark" 
                                                          data-bs-toggle="tooltip" 
                                                          title="<?php echo $payout['paystack_transfer_reference']; ?>">
                                                        <i class="bi bi-hash"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-cash-stack display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No payouts yet</h4>
                            <p class="text-muted mb-4">Your payout history will appear here once you start receiving payments.</p>
                            <a href="earnings.php" class="btn btn-primary">
                                <i class="bi bi-graph-up me-1"></i> View Earnings
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Payout Schedule Info -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex">
                                        <i class="bi bi-calendar-check text-primary fs-3 me-3"></i>
                                        <div>
                                            <h6 class="mb-1">Payout Schedule</h6>
                                            <p class="small text-muted mb-0">
                                                Payouts are processed every Monday for the previous week's earnings.
                                                Processing takes 1-3 business days to reflect in your bank account.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex">
                                        <i class="bi bi-cash-stack text-success fs-3 me-3"></i>
                                        <div>
                                            <h6 class="mb-1">Minimum Payout</h6>
                                            <p class="small text-muted mb-0">
                                                The minimum payout amount is <strong>₦<?php echo number_format(MIN_PAYOUT_AMOUNT, 2); ?></strong>.
                                                Earnings below this amount will roll over to the next payout cycle.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Request Payout Modal -->
<div class="modal fade" id="requestPayoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-cash-stack me-2"></i>
                    Request Payout
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="payoutForm" onsubmit="submitPayoutRequest(event)">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="display-4 text-success mb-2">₦<?php echo number_format($available_earnings, 2); ?></div>
                        <p class="text-muted">Available balance</p>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Payouts are processed every Monday. You'll receive the funds within 1-3 business days.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Bank Account</label>
                        <div class="bg-light p-3 rounded">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($bank_details['bank_name'] ?? 'No bank account'); ?></strong>
                                    <br>
                                    <span class="font-monospace">
                                        <?php echo $bank_details['account_number'] ?? ''; ?> - <?php echo htmlspecialchars($bank_details['account_name'] ?? ''); ?>
                                    </span>
                                </div>
                                <span class="badge bg-success">Verified</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Amount to Withdraw</label>
                        <div class="input-group">
                            <span class="input-group-text">₦</span>
                            <input type="text" class="form-control" id="payout_amount" 
                                   value="<?php echo number_format($available_earnings, 2); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="confirm_payout" required>
                        <label class="form-check-label" for="confirm_payout">
                            I confirm that I want to request a payout of the available balance to my bank account.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="submitPayoutBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="payoutSpinner"></span>
                        Confirm Payout Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterStatus(status) {
    const url = new URL(window.location.href);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function requestPayout() {
    <?php if (!$bank_details): ?>
        showToast('Please add and verify your bank details first', 'warning');
        window.location.href = 'bank-details.php';
        return;
    <?php endif; ?>
    
    <?php if ($available_earnings < MIN_PAYOUT_AMOUNT): ?>
        showToast('Available balance is below minimum payout amount', 'warning');
        return;
    <?php endif; ?>
    
    const modal = new bootstrap.Modal(document.getElementById('requestPayoutModal'));
    modal.show();
}

function submitPayoutRequest(event) {
    event.preventDefault();
    
    const submitBtn = document.getElementById('submitPayoutBtn');
    const spinner = document.getElementById('payoutSpinner');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    
    // Simulate API call - replace with actual AJAX
    setTimeout(() => {
        showToast('Payout request submitted successfully!', 'success');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('requestPayoutModal'));
        modal.hide();
        
        // Reset button
        submitBtn.disabled = false;
        spinner.classList.add('d-none');
        
        // Reload after delay
        setTimeout(() => {
            location.reload();
        }, 1500);
    }, 2000);
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
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
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

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

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
    
    .modal-header.bg-success {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
    }
    
    .btn-group .btn.active {
        z-index: 3;
    }
    
    @media (max-width: 768px) {
        .table td:nth-child(1),
        .table td:nth-child(4),
        .table td:nth-child(5) {
            font-size: 0.9rem;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>