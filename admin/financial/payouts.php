<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';

requireAdmin();

$db = new Database();

// Handle payout actions
if (isset($_GET['process_payout'])) {
    $payout_id = (int)$_GET['process_payout'];
    
    $db->query(
        "UPDATE seller_payouts SET status = 'processing', processed_at = NOW() WHERE id = ?",
        [$payout_id]
    );
    setFlashMessage('Payout marked as processing', 'success');
    header('Location: payouts.php');
    exit;
}

if (isset($_GET['mark_paid'])) {
    $payout_id = (int)$_GET['mark_paid'];
    
    $db->query(
        "UPDATE seller_payouts SET status = 'paid', paid_at = NOW() WHERE id = ?",
        [$payout_id]
    );
    setFlashMessage('Payout marked as paid', 'success');
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
                        <a href="?process_all_pending=true" class="btn btn-sm btn-primary" 
                           onclick="return confirm('Process all <?php echo $stats['pending_payouts']; ?> pending payouts?')">
                            <i class="bi bi-play-circle me-1"></i> Process All
                        </a>
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
                                        <a href="?process_all_pending=true" class="btn btn-warning btn-sm" 
                                           onclick="return confirm('Process all <?php echo $stats['pending_payouts']; ?> pending payouts?')">
                                            <i class="bi bi-play-circle me-1"></i> Process All
                                        </a>
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
                                                    <?php if ($payout['status'] === 'pending'): ?>
                                                        <a href="?process_payout=<?php echo $payout['id']; ?>" 
                                                           class="btn btn-outline-info"
                                                           onclick="return confirm('Mark this payout as processing?')">
                                                            <i class="bi bi-play"></i>
                                                        </a>
                                                    <?php elseif ($payout['status'] === 'processing'): ?>
                                                        <a href="?mark_paid=<?php echo $payout['id']; ?>" 
                                                           class="btn btn-outline-success"
                                                           onclick="return confirm('Mark this payout as paid?')">
                                                            <i class="bi bi-check"></i>
                                                        </a>
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
                                                            <?php if ($payout['status'] === 'pending'): ?>
                                                                <a href="?process_payout=<?php echo $payout['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-info"
                                                                   onclick="return confirm('Mark as processing?')">
                                                                    <i class="bi bi-play"></i>
                                                                </a>
                                                            <?php elseif ($payout['status'] === 'processing'): ?>
                                                                <a href="?mark_paid=<?php echo $payout['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-success"
                                                                   onclick="return confirm('Mark as paid?')">
                                                                    <i class="bi bi-check"></i>
                                                                </a>
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
                        <?php if ($payout['status'] === 'pending'): ?>
                            <a href="?process_payout=<?php echo $payout['id']; ?>" 
                               class="btn btn-info flex-fill"
                               onclick="return confirm('Mark this payout as processing?')">
                                <i class="bi bi-play me-1"></i> Mark as Processing
                            </a>
                        <?php elseif ($payout['status'] === 'processing'): ?>
                            <a href="?mark_paid=<?php echo $payout['id']; ?>" 
                               class="btn btn-success flex-fill"
                               onclick="return confirm('Mark this payout as paid?')">
                                <i class="bi bi-check me-1"></i> Mark as Paid
                            </a>
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