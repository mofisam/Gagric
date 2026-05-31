<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../config/paystack.php';

$db = new Database();

function generateAdminPayoutReference($payout_id) {
    return 'ga_admin_payout_' . $payout_id . '_' . time() . '_' . bin2hex(random_bytes(4));
}

function mapAdminPaystackTransferStatus($status) {
    $status = strtolower((string)$status);

    if ($status === 'success') {
        return 'paid';
    }

    if (in_array($status, ['failed', 'reversed', 'abandoned', 'rejected', 'blocked'], true)) {
        return 'failed';
    }

    return 'processing';
}

function processPaystackPayout(Database $db, $payout_id) {
    $payout = $db->fetchOne("
        SELECT 
            spay.id,
            spay.seller_id,
            spay.net_amount,
            spay.status,
            spay.paystack_transfer_reference,
            sfi.bank_name,
            sfi.bank_code,
            sfi.account_number,
            sfi.account_name,
            sfi.is_bank_verified,
            sp.business_name
        FROM seller_payouts spay
        LEFT JOIN seller_financial_info sfi ON spay.seller_id = sfi.seller_id
        LEFT JOIN seller_profiles sp ON spay.seller_id = sp.user_id
        WHERE spay.id = ?
    ", [$payout_id]);

    if (!$payout) {
        return ['success' => false, 'message' => 'Payout not found.'];
    }

    if ($payout['status'] === 'paid') {
        return ['success' => false, 'message' => 'This payout has already been paid.'];
    }

    $has_transfer_code = !empty($payout['paystack_transfer_reference'])
        && strpos($payout['paystack_transfer_reference'], 'TRF_') === 0;

    if ($payout['status'] === 'processing' && $has_transfer_code) {
        return ['success' => false, 'message' => 'This payout is already processing.'];
    }

    if (empty($payout['is_bank_verified']) || empty($payout['bank_code']) || empty($payout['account_number']) || empty($payout['account_name'])) {
        return ['success' => false, 'message' => 'Seller does not have verified bank details.'];
    }

    $amount = (float)$payout['net_amount'];
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Payout amount is invalid.'];
    }

    $transfer_reference = generateAdminPayoutReference($payout_id);

    $lock_stmt = $db->query(
        "UPDATE seller_payouts
         SET status = 'processing', processed_at = ?, paystack_transfer_reference = ?
         WHERE id = ?
            AND (
                status IN ('pending', 'failed')
                OR (
                    status = 'processing'
                    AND (paystack_transfer_reference IS NULL OR paystack_transfer_reference NOT LIKE 'TRF_%')
                )
            )",
        [date('Y-m-d H:i:s'), $transfer_reference, $payout_id]
    );
    $updated = $lock_stmt->affected_rows;
    $lock_stmt->close();

    if ($updated <= 0) {
        return ['success' => false, 'message' => 'Payout could not be locked for processing. Please refresh and try again.'];
    }

    $recipient_response = PaystackAPI::createTransferRecipient(
        $payout['account_name'],
        $payout['account_number'],
        $payout['bank_code'],
        'Green Agric seller payout'
    );

    if (empty($recipient_response['status']) || empty($recipient_response['data']['recipient_code'])) {
        $db->update('seller_payouts', ['status' => 'failed'], 'id = ?', [$payout_id]);

        return [
            'success' => false,
            'message' => $recipient_response['message'] ?? 'Paystack could not create the transfer recipient.'
        ];
    }

    $transfer_response = PaystackAPI::initiateTransfer(
        $amount,
        $recipient_response['data']['recipient_code'],
        $transfer_reference,
        'Green Agric seller payout'
    );

    if (empty($transfer_response['status']) || empty($transfer_response['data'])) {
        $db->update('seller_payouts', ['status' => 'failed'], 'id = ?', [$payout_id]);

        return [
            'success' => false,
            'message' => $transfer_response['message'] ?? 'Paystack could not initiate the transfer.'
        ];
    }

    $paystack_status = $transfer_response['data']['status'] ?? 'pending';
    $payout_status = mapAdminPaystackTransferStatus($paystack_status);
    $transfer_code = $transfer_response['data']['transfer_code'] ?? '';

    if ($transfer_code) {
        $db->update(
            'seller_payouts',
            ['paystack_transfer_reference' => $transfer_code],
            'id = ?',
            [$payout_id]
        );
    }

    if ($payout_status === 'paid') {
        $db->query(
            "UPDATE seller_payouts SET status = 'paid', paid_at = NOW() WHERE id = ?",
            [$payout_id]
        );
    } else {
        $db->update('seller_payouts', ['status' => $payout_status], 'id = ?', [$payout_id]);
    }

    if (strtolower((string)$paystack_status) === 'otp') {
        $message = $transfer_response['message'] ?? 'Paystack requires OTP to complete this payout.';
    } elseif ($payout_status === 'paid') {
        $message = 'Paystack payout sent successfully.';
    } else {
        $message = 'Paystack payout initiated. Status will update after Paystack confirms the transfer.';
    }

    return [
        'success' => true,
        'message' => $message,
        'status' => $payout_status
    ];
}

function finalizePaystackPayoutOtp(Database $db, $payout_id, $otp) {
    $otp = preg_replace('/\D/', '', (string)$otp);

    if ($otp === '') {
        return ['success' => false, 'message' => 'Please enter the Paystack OTP.'];
    }

    $payout = $db->fetchOne("
        SELECT id, status, paystack_transfer_reference
        FROM seller_payouts
        WHERE id = ?
    ", [$payout_id]);

    if (!$payout) {
        return ['success' => false, 'message' => 'Payout not found.'];
    }

    if ($payout['status'] !== 'processing') {
        return ['success' => false, 'message' => 'Only processing payouts can accept an OTP.'];
    }

    $transfer_code = $payout['paystack_transfer_reference'] ?? '';
    if (!$transfer_code || strpos($transfer_code, 'TRF_') !== 0) {
        return ['success' => false, 'message' => 'Paystack transfer code is missing. Retry the payout.'];
    }

    $response = PaystackAPI::finalizeTransfer($transfer_code, $otp);

    if (empty($response['status']) || empty($response['data'])) {
        return [
            'success' => false,
            'message' => $response['message'] ?? 'Paystack could not verify the OTP.'
        ];
    }

    $paystack_status = $response['data']['status'] ?? 'pending';
    $payout_status = mapAdminPaystackTransferStatus($paystack_status);

    if ($payout_status === 'paid') {
        $db->query(
            "UPDATE seller_payouts SET status = 'paid', paid_at = NOW() WHERE id = ?",
            [$payout_id]
        );
    } else {
        $db->update('seller_payouts', ['status' => $payout_status], 'id = ?', [$payout_id]);
    }

    return [
        'success' => true,
        'message' => $payout_status === 'paid'
            ? 'Paystack OTP accepted. Payout sent successfully.'
            : 'Paystack OTP accepted. Transfer is still processing.',
        'status' => $payout_status
    ];
}

// Handle payout actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Invalid session token. Please refresh and try again.', 'error');
        header('Location: payouts.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'process_payout') {
        $result = processPaystackPayout($db, (int)($_POST['payout_id'] ?? 0));
        setFlashMessage($result['message'], $result['success'] ? 'success' : 'error');
    }

    if ($action === 'finalize_payout_otp') {
        $result = finalizePaystackPayoutOtp($db, (int)($_POST['payout_id'] ?? 0), $_POST['otp'] ?? '');
        setFlashMessage($result['message'], $result['success'] ? 'success' : 'error');
    }

    if ($action === 'process_all_pending') {
        $pending_ids = $db->fetchAll("SELECT id FROM seller_payouts WHERE status = 'pending' ORDER BY created_at ASC");
        $success_count = 0;
        $failed_count = 0;

        foreach ($pending_ids as $pending) {
            $result = processPaystackPayout($db, (int)$pending['id']);
            if ($result['success']) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }

        setFlashMessage(
            "Paystack processing complete: {$success_count} sent/processing, {$failed_count} failed.",
            $failed_count > 0 ? 'warning' : 'success'
        );
    }

    if ($action === 'mark_paid') {
        $payout_id = (int)($_POST['payout_id'] ?? 0);

        $db->query(
            "UPDATE seller_payouts SET status = 'paid', paid_at = NOW() WHERE id = ?",
            [$payout_id]
        );
        setFlashMessage('Payout marked as paid', 'success');
    }

    header('Location: payouts.php');
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (sp.business_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    $where .= " AND spay.status = ?";
    $params[] = $status;
}

if (!empty($date_from)) {
    $where .= " AND DATE(spay.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where .= " AND DATE(spay.created_at) <= ?";
    $params[] = $date_to;
}

// Get payouts
$payouts = $db->fetchAll("
    SELECT spay.*, 
           u.first_name, u.last_name, u.email,
           sp.business_name,
           oi.product_name, oi.quantity, oi.unit_price,
           oi.item_total,
           o.order_number, o.id as order_id,
           oi.id as order_item_id
    FROM seller_payouts spay
    JOIN users u ON spay.seller_id = u.id
    JOIN seller_profiles sp ON spay.seller_id = sp.user_id
    JOIN order_items oi ON spay.order_item_id = oi.id
    JOIN orders o ON oi.order_id = o.id
    $where 
    ORDER BY spay.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

// Total count for pagination
$total_payouts = $db->fetchOne("SELECT COUNT(*) as count FROM seller_payouts spay JOIN users u ON spay.seller_id = u.id $where", $params)['count'];
$total_pages = ceil($total_payouts / $limit);

// Stats
$stats = [
    'total_payouts' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_payouts")['count'],
    'pending_payouts' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_payouts WHERE status = 'pending'")['count'],
    'processing_payouts' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_payouts WHERE status = 'processing'")['count'],
    'paid_payouts' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_payouts WHERE status = 'paid'")['count'],
    'failed_payouts' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_payouts WHERE status = 'failed'")['count'],
    'total_paid' => $db->fetchOne("SELECT SUM(net_amount) as total FROM seller_payouts WHERE status = 'paid'")['total'] ?? 0,
    'pending_amount' => $db->fetchOne("SELECT SUM(net_amount) as total FROM seller_payouts WHERE status = 'pending'")['total'] ?? 0,
    'today_payouts' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_payouts WHERE DATE(created_at) = CURDATE()")['count']
];

$page_title = "Seller Payouts";
$page_css = 'dashboard.css';
$csrf_token = getCSRFToken();
require_once '../../includes/header.php';
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
                        <h1 class="h5 mb-0 text-center">Seller Payouts</h1>
                        <small class="text-muted d-block text-center">₦<?php echo number_format($stats['total_paid'], 2); ?> paid</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshPayouts">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Seller Payouts</h1>
                    <p class="text-muted mb-0">Manage seller commission payouts</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportPayouts()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPayouts">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <?php if($stats['pending_payouts'] > 0): ?>
                        <form method="POST" action="" class="d-inline" id="processAllPendingForm"
                              onsubmit="return confirm('This will send <?php echo $stats['pending_payouts']; ?> real Paystack payout transfers. Continue?')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="process_all_pending">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-send-check me-1"></i> Pay All via Paystack
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Total</small>
                                <h6 class="mb-0"><?php echo number_format($stats['total_payouts']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Pending</small>
                                <h6 class="mb-0 text-warning"><?php echo number_format($stats['pending_payouts']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Paid</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['paid_payouts']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Failed</small>
                                <h6 class="mb-0 text-danger"><?php echo number_format($stats['failed_payouts']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="d-none d-md-flex row g-3 mb-4">
                <!-- Total Payouts -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Payouts</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_payouts']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo $stats['today_payouts']; ?> today
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-cash-coin fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Payouts -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['pending_payouts']); ?></h3>
                                    <small class="text-warning">
                                        ₦<?php echo number_format($stats['pending_amount'], 2); ?> pending
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-clock fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Paid -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Paid</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['total_paid'], 2); ?></h3>
                                    <small class="text-success">
                                        <?php echo $stats['paid_payouts']; ?> payouts
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-check-circle fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Processing -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Processing</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['processing_payouts']); ?></h3>
                                    <small class="text-info">
                                        In progress
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-gear fs-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Filter Payouts
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
                <div class="collapse d-md-block" id="filterCollapse">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-12 col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search sellers..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <input type="date" name="date_from" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-6 col-md-2">
                                <input type="date" name="date_to" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-6 col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-filter me-1"></i> Filter
                                </button>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <a href="payouts.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-1"></i> Clear
                                    </a>
                                    <?php if($stats['pending_payouts'] > 0): ?>
                                        <button type="submit" form="processAllPendingForm" class="btn btn-warning btn-sm">
                                            <i class="bi bi-send-check me-1"></i> Pay All via Paystack
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">Payouts (<?php echo $total_payouts; ?>)</h5>
                    <small class="text-muted">
                        <?php echo $total_payouts; ?> total • 
                        Showing <?php echo min($offset + 1, $total_payouts); ?>-<?php echo min($offset + $limit, $total_payouts); ?>
                    </small>
                </div>
                <div class="text-muted d-none d-md-block">
                    <?php if($stats['pending_payouts'] > 0): ?>
                        <span class="badge bg-warning">
                            <i class="bi bi-clock me-1"></i>
                            <?php echo $stats['pending_payouts']; ?> pending
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payouts Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($payouts)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-cash-coin text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No payouts found</h4>
                            <p class="text-muted mb-4">No payouts match your current filters.</p>
                            <a href="payouts.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="150">Seller</th>
                                        <th width="100">Order</th>
                                        <th>Product</th>
                                        <th width="90">Amount</th>
                                        <th width="90">Net</th>
                                        <th width="90">Status</th>
                                        <th width="100">Created</th>
                                        <th width="80" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payouts as $payout): ?>
                                        <?php 
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'processing' => 'info',
                                            'paid' => 'success',
                                            'failed' => 'danger'
                                        ];
                                        
                                        $commission_percent = $payout['commission_rate'];
                                        $commission_amount = $payout['commission_amount'];
                                        $net_amount = $payout['net_amount'];
                                        $gross_amount = $payout['amount'];
                                        $has_paystack_transfer_code = !empty($payout['paystack_transfer_reference']) && strpos($payout['paystack_transfer_reference'], 'TRF_') === 0;
                                        $can_process_payout = in_array($payout['status'], ['pending', 'failed'], true) || ($payout['status'] === 'processing' && !$has_paystack_transfer_code);
                                        $needs_paystack_otp = $payout['status'] === 'processing' && $has_paystack_transfer_code;
                                        ?>
                                        
                                        <tr class="payout-row" data-payout-id="<?php echo $payout['id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-<?php echo $status_colors[$payout['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                        <i class="bi bi-shop text-<?php echo $status_colors[$payout['status']] ?? 'secondary'; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($payout['business_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($payout['first_name'] . ' ' . $payout['last_name']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <a href="../orders/order-details.php?id=<?php echo $payout['order_id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo $payout['order_number']; ?>
                                                </a>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <?php echo htmlspecialchars($payout['product_name']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $payout['quantity']; ?> × ₦<?php echo number_format($payout['unit_price'], 2); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong class="text-success">₦<?php echo number_format($gross_amount, 2); ?></strong>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong>₦<?php echo number_format($net_amount, 2); ?></strong>
                                                <br>
                                                <small class="text-muted">-<?php echo $commission_percent; ?>%</small>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $status_colors[$payout['status']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($payout['status']); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo date('M j, Y', strtotime($payout['created_at'])); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($can_process_payout): ?>
                                                        <form method="POST" action="" class="d-inline"
                                                              onsubmit="return confirm('<?php echo $payout['status'] === 'pending' ? 'Send' : 'Retry'; ?> this payout through Paystack now?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                            <input type="hidden" name="action" value="process_payout">
                                                            <input type="hidden" name="payout_id" value="<?php echo (int)$payout['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-<?php echo $payout['status'] === 'pending' ? 'info' : 'warning'; ?>"
                                                                    title="<?php echo $payout['status'] === 'pending' ? 'Pay via Paystack' : 'Retry via Paystack'; ?>">
                                                                <i class="bi <?php echo $payout['status'] === 'pending' ? 'bi-send-check' : 'bi-arrow-clockwise'; ?>"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($needs_paystack_otp): ?>
                                                        <button type="button" class="btn btn-outline-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#payoutModal<?php echo $payout['id']; ?>"
                                                                title="Enter Paystack OTP">
                                                            <i class="bi bi-key"></i>
                                                        </button>
                                                        <form method="POST" action="" class="d-inline"
                                                              onsubmit="return confirm('Mark this payout as paid manually?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                            <input type="hidden" name="action" value="mark_paid">
                                                            <input type="hidden" name="payout_id" value="<?php echo (int)$payout['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-success">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#payoutModal<?php echo $payout['id']; ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Seller & Status -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $status_colors[$payout['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <i class="bi bi-shop text-<?php echo $status_colors[$payout['status']] ?? 'secondary'; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars(substr($payout['business_name'], 0, 20)); ?></strong>
                                                                <br>
                                                                <span class="badge bg-<?php echo $status_colors[$payout['status']] ?? 'secondary'; ?>">
                                                                    <?php echo ucfirst($payout['status']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <strong class="text-success">₦<?php echo number_format($net_amount, 2); ?></strong>
                                                            <br>
                                                            <small class="text-muted">Net</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 2: Order & Product -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <small class="text-muted">Order:</small>
                                                                <br>
                                                                <small><?php echo $payout['order_number']; ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted">Product:</small>
                                                                <br>
                                                                <small><?php echo htmlspecialchars(substr($payout['product_name'], 0, 20)); ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Details & Actions -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <small class="text-muted d-block">
                                                                Gross: ₦<?php echo number_format($gross_amount, 2); ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <?php echo date('M j', strtotime($payout['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <div class="btn-group btn-group-sm">
                                                            <?php if ($can_process_payout): ?>
                                                                <form method="POST" action="" class="d-inline"
                                                                      onsubmit="return confirm('<?php echo $payout['status'] === 'pending' ? 'Send' : 'Retry'; ?> this payout through Paystack now?')">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                    <input type="hidden" name="action" value="process_payout">
                                                                    <input type="hidden" name="payout_id" value="<?php echo (int)$payout['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-<?php echo $payout['status'] === 'pending' ? 'info' : 'warning'; ?>">
                                                                        <i class="bi <?php echo $payout['status'] === 'pending' ? 'bi-send-check' : 'bi-arrow-clockwise'; ?>"></i>
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($needs_paystack_otp): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#payoutModal<?php echo $payout['id']; ?>">
                                                                    <i class="bi bi-key"></i>
                                                                </button>
                                                                <form method="POST" action="" class="d-inline"
                                                                      onsubmit="return confirm('Mark this payout as paid manually?')">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                    <input type="hidden" name="action" value="mark_paid">
                                                                    <input type="hidden" name="payout_id" value="<?php echo (int)$payout['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                                        <i class="bi bi-check"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#payoutModal<?php echo $payout['id']; ?>">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                                    <i class="bi bi-chevron-left"></i>
                                    <span class="d-none d-md-inline">Previous</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Mobile: Simple pagination -->
                        <div class="d-md-none">
                            <li class="page-item disabled">
                                <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            </li>
                        </div>
                        
                        <!-- Desktop: Full pagination -->
                        <div class="d-none d-md-flex">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <span class="d-none d-md-inline">Next</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modals for each payout -->
<?php foreach ($payouts as $payout): ?>
    <?php
    $has_paystack_transfer_code = !empty($payout['paystack_transfer_reference']) && strpos($payout['paystack_transfer_reference'], 'TRF_') === 0;
    $can_process_payout = in_array($payout['status'], ['pending', 'failed'], true) || ($payout['status'] === 'processing' && !$has_paystack_transfer_code);
    $needs_paystack_otp = $payout['status'] === 'processing' && $has_paystack_transfer_code;
    ?>
    <!-- Payout Details Modal -->
    <div class="modal fade" id="payoutModal<?php echo $payout['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-cash-coin me-2"></i>
                        Payout Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Seller Information</h6>
                                    <div class="mb-2">
                                        <small class="text-muted">Business Name</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($payout['business_name']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Seller Name</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($payout['first_name'] . ' ' . $payout['last_name']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Email</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($payout['email']); ?></p>
                                    </div>
                                    <?php if ($needs_paystack_otp): ?>
                                        <div class="alert alert-warning mt-3 mb-0">
                                            <i class="bi bi-key me-2"></i>
                                            If Paystack sent an OTP for this transfer, enter it below to complete the payout.
                                        </div>
                                    <?php elseif ($payout['status'] === 'failed' || ($payout['status'] === 'processing' && !$has_paystack_transfer_code)): ?>
                                        <div class="alert alert-danger mt-3 mb-0">
                                            <i class="bi bi-arrow-clockwise me-2"></i>
                                            This payout can be retried through Paystack below.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Payout Details</h6>
                                    <div class="mb-2">
                                        <small class="text-muted">Status</small>
                                        <p class="mb-0">
                                            <span class="badge bg-<?php echo $status_colors[$payout['status']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($payout['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Created</small>
                                        <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($payout['created_at'])); ?></p>
                                    </div>
                                    <?php if($payout['processed_at']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Processed At</small>
                                        <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($payout['processed_at'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <?php if($payout['paid_at']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Paid At</small>
                                        <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($payout['paid_at'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Financial Breakdown</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <small class="text-muted">Gross Amount</small>
                                                <h5 class="mb-0 text-success">₦<?php echo number_format($payout['amount'], 2); ?></h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <small class="text-muted">Commission (<?php echo $payout['commission_rate']; ?>%)</small>
                                                <h5 class="mb-0 text-danger">-₦<?php echo number_format($payout['commission_amount'], 2); ?></h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <small class="text-muted">Net Amount (To Seller)</small>
                                                <h5 class="mb-0 text-primary">₦<?php echo number_format($payout['net_amount'], 2); ?></h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">Order Information</small>
                                        <p class="mb-1">
                                            Order: <a href="../orders/order-details.php?id=<?php echo $payout['order_id']; ?>" 
                                                     class="text-decoration-none">
                                                <?php echo $payout['order_number']; ?>
                                            </a>
                                        </p>
                                        <p class="mb-1">
                                            Product: <?php echo htmlspecialchars($payout['product_name']); ?>
                                            (<?php echo $payout['quantity']; ?> × ₦<?php echo number_format($payout['unit_price'], 2); ?>)
                                        </p>
                                        <p class="mb-0">
                                            Item Total: ₦<?php echo number_format($payout['item_total'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="d-flex gap-2 w-100">
                        <?php if ($can_process_payout): ?>
                            <form method="POST" action="" class="flex-fill"
                                  onsubmit="return confirm('<?php echo $payout['status'] === 'pending' ? 'Send' : 'Retry'; ?> this payout through Paystack now?')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="process_payout">
                                <input type="hidden" name="payout_id" value="<?php echo (int)$payout['id']; ?>">
                                <button type="submit" class="btn btn-<?php echo $payout['status'] === 'pending' ? 'info' : 'warning'; ?> w-100">
                                    <i class="bi <?php echo $payout['status'] === 'pending' ? 'bi-send-check' : 'bi-arrow-clockwise'; ?> me-1"></i>
                                    <?php echo $payout['status'] === 'pending' ? 'Pay via Paystack' : 'Retry Paystack Payout'; ?>
                                </button>
                            </form>
                        <?php elseif ($needs_paystack_otp): ?>
                            <form method="POST" action="" class="flex-fill">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="finalize_payout_otp">
                                <input type="hidden" name="payout_id" value="<?php echo (int)$payout['id']; ?>">
                                <div class="input-group">
                                    <input type="text" name="otp" class="form-control"
                                           inputmode="numeric" pattern="[0-9]*"
                                           placeholder="Paystack OTP" required>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-key me-1"></i> Submit OTP
                                    </button>
                                </div>
                            </form>
                            <form method="POST" action="" class="flex-fill"
                                  onsubmit="return confirm('Mark this payout as paid manually?')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="mark_paid">
                                <input type="hidden" name="payout_id" value="<?php echo (int)$payout['id']; ?>">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check me-1"></i> Mark as Paid
                                </button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

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
    
    // Refresh payouts
    const refreshBtn = document.getElementById('refreshPayouts');
    const mobileRefreshBtn = document.getElementById('mobileRefreshPayouts');
    
    function refreshPage() {
        const btn = event?.target?.closest('button');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            btn.disabled = true;
        }
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
    
    if (refreshBtn) refreshBtn.addEventListener('click', refreshPage);
    if (mobileRefreshBtn) mobileRefreshBtn.addEventListener('click', refreshPage);
    
    // Make table rows clickable on mobile to show details
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('a')) {
                const payoutId = this.closest('.payout-row').dataset.payoutId;
                const modal = new bootstrap.Modal(document.getElementById('payoutModal' + payoutId));
                modal.show();
            }
        });
    });
});

function exportPayouts() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = 'export-payouts.php?' + params.toString();
    link.download = 'payouts-export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    agriApp.showToast('Export started', 'info');
}

// Add CSS for mobile table
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Mobile Table Styles */
    .mobile-table-row {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
        cursor: pointer;
    }
    
    .mobile-table-row:last-child {
        border-bottom: none;
    }
    
    @media (max-width: 767.98px) {
        .mobile-optimized-table {
            border: 0;
        }
        
        .mobile-optimized-table thead {
            display: none;
        }
        
        .mobile-optimized-table tr {
            display: block;
            margin-bottom: 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: white;
        }
        
        .mobile-optimized-table td {
            display: block;
            padding: 0 !important;
            border: none;
        }
        
        .mobile-optimized-table td.d-md-none {
            display: block !important;
        }
        
        .mobile-optimized-table td.d-none {
            display: none !important;
        }
        
        /* Touch-friendly buttons */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            min-height: 36px;
            min-width: 36px;
        }
        
        /* Modal optimization */
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .modal-content {
            border-radius: 0.5rem;
        }
        
        /* Better mobile header */
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Compact filters */
        .form-select-sm, .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Status badges compact */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Payment amounts */
        .text-success {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        /* Financial breakdown */
        .card-body h5 {
            font-size: 1.2rem;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .payout-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .payout-row:hover .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>
