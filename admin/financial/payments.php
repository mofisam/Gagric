<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';

requireAdmin();

$db = new Database();

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$method = $_GET['method'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.paystack_reference LIKE ? OR o.order_number LIKE ? OR p.customer_email LIKE ? OR p.customer_name LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

if (!empty($method)) {
    $where .= " AND p.payment_method = ?";
    $params[] = $method;
}

if (!empty($date_from)) {
    $where .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

// Get payments
$payments = $db->fetchAll("
    SELECT p.*, 
           o.order_number, 
           o.total_amount, 
           o.status as order_status,
           u.first_name, 
           u.last_name,
           u.phone as customer_phone,
           CONCAT(u.first_name, ' ', u.last_name) as customer_name
    FROM payments p 
    JOIN orders o ON p.order_id = o.id 
    JOIN users u ON o.buyer_id = u.id 
    $where 
    ORDER BY p.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

// Total count for pagination
$total_payments = $db->fetchOne("SELECT COUNT(*) as count FROM payments p JOIN orders o ON p.order_id = o.id $where", $params)['count'];
$total_pages = ceil($total_payments / $limit);

// Stats
$stats = [
    'total_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments")['count'],
    'successful_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE status = 'success'")['count'],
    'failed_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE status = 'failed'")['count'],
    'pending_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")['count'],
    'abandoned_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE status = 'abandoned'")['count'],
    'total_revenue' => $db->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'success'")['total'] ?? 0,
    'today_revenue' => $db->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'success' AND DATE(created_at) = CURDATE()")['total'] ?? 0,
];

$page_title = "Payment Management";
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
                        <h1 class="h5 mb-0 text-center">Payments</h1>
                        <small class="text-muted d-block text-center">₦<?php echo number_format($stats['total_revenue'], 2); ?> revenue</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshPayments">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Payment Management</h1>
                    <p class="text-muted mb-0">Monitor all payment transactions</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportPayments()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPayments">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Total</small>
                                <h6 class="mb-0"><?php echo number_format($stats['total_payments']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Successful</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['successful_payments']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Pending</small>
                                <h6 class="mb-0 text-warning"><?php echo number_format($stats['pending_payments']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Failed</small>
                                <h6 class="mb-0 text-danger"><?php echo number_format($stats['failed_payments']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="d-none d-md-flex row g-3 mb-4">
                <!-- Total Payments -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Payments</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_payments']); ?></h3>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-credit-card fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Successful Payments -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Successful</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['successful_payments']); ?></h3>
                                    <small class="text-success">
                                        <?php echo $stats['total_payments'] > 0 ? number_format(($stats['successful_payments'] / $stats['total_payments']) * 100, 1) : 0; ?>% success rate
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-check-circle fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Revenue -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Revenue</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-calendar-day me-1"></i>
                                        ₦<?php echo number_format($stats['today_revenue'], 2); ?> today
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-cash-stack fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending & Failed -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending & Failed</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['pending_payments'] + $stats['failed_payments']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo $stats['pending_payments']; ?> pending, <?php echo $stats['failed_payments']; ?> failed
                                    </small>
                                </div>
                                <div class="bg-danger bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-clock fs-5 text-danger"></i>
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
                        Filter Payments
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
                                           placeholder="Search..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="abandoned" <?php echo $status === 'abandoned' ? 'selected' : ''; ?>>Abandoned</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="method" class="form-select form-select-sm">
                                    <option value="">All Methods</option>
                                    <option value="card" <?php echo $method === 'card' ? 'selected' : ''; ?>>Card</option>
                                    <option value="bank_transfer" <?php echo $method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="ussd" <?php echo $method === 'ussd' ? 'selected' : ''; ?>>USSD</option>
                                    <option value="mobile_money" <?php echo $method === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
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
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-filter me-1"></i> Filter
                                    </button>
                                    <a href="payments.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-1"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">Payments (<?php echo $total_payments; ?>)</h5>
                    <small class="text-muted">
                        <?php echo $total_payments; ?> total • 
                        Showing <?php echo min($offset + 1, $total_payments); ?>-<?php echo min($offset + $limit, $total_payments); ?>
                    </small>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($payments)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-credit-card text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No payments found</h4>
                            <p class="text-muted mb-4">No payments match your current filters.</p>
                            <a href="payments.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="140">Reference</th>
                                        <th width="100">Order</th>
                                        <th>Customer</th>
                                        <th width="100">Amount</th>
                                        <th width="90">Status</th>
                                        <th width="90">Method</th>
                                        <th width="100">Date</th>
                                        <th width="60" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <?php 
                                        $status_colors = [
                                            'success' => 'success',
                                            'pending' => 'warning',
                                            'failed' => 'danger',
                                            'abandoned' => 'secondary'
                                        ];
                                        
                                        $method_badges = [
                                            'card' => 'primary',
                                            'bank_transfer' => 'info',
                                            'ussd' => 'dark',
                                            'mobile_money' => 'success'
                                        ];
                                        
                                        $order_status_colors = [
                                            'pending' => 'warning',
                                            'confirmed' => 'info',
                                            'processing' => 'primary',
                                            'shipped' => 'info',
                                            'delivered' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        ?>
                                        
                                        <tr class="payment-row" data-payment-id="<?php echo $payment['id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-<?php echo $status_colors[$payment['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                        <i class="bi bi-credit-card text-<?php echo $status_colors[$payment['status']] ?? 'secondary'; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <code class="text-muted"><?php echo htmlspecialchars(substr($payment['paystack_reference'], 0, 8) . '...'); ?></code>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('M j', strtotime($payment['created_at'])); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <a href="../orders/order-details.php?id=<?php echo $payment['order_id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo $payment['order_number']; ?>
                                                </a>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($payment['customer_email']); ?></small>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong class="text-success">₦<?php echo number_format($payment['amount'], 2); ?></strong>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $status_colors[$payment['status']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $method_badges[$payment['payment_method']] ?? 'secondary'; ?>">
                                                    <?php echo str_replace('_', ' ', $payment['payment_method']); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#paymentModal<?php echo $payment['id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Reference & Status -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $status_colors[$payment['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <i class="bi bi-credit-card text-<?php echo $status_colors[$payment['status']] ?? 'secondary'; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <code class="text-muted"><?php echo htmlspecialchars(substr($payment['paystack_reference'], 0, 12) . '...'); ?></code>
                                                                <br>
                                                                <span class="badge bg-<?php echo $status_colors[$payment['status']] ?? 'secondary'; ?>">
                                                                    <?php echo ucfirst($payment['status']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <strong class="text-success">₦<?php echo number_format($payment['amount'], 2); ?></strong>
                                                    </div>
                                                    
                                                    <!-- Row 2: Customer & Order -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars(substr($payment['customer_name'], 0, 20)); ?></strong>
                                                                <br>
                                                                <small class="text-muted"><?php echo $payment['order_number']; ?></small>
                                                            </div>
                                                            <span class="badge bg-<?php echo $method_badges[$payment['payment_method']] ?? 'secondary'; ?>">
                                                                <?php echo substr(str_replace('_', ' ', $payment['payment_method']), 0, 4); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Date & Actions -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <?php echo date('M j, g:i A', strtotime($payment['created_at'])); ?>
                                                        </small>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#paymentModal<?php echo $payment['id']; ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
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

<!-- Modals for each payment -->
<?php foreach ($payments as $payment): ?>
    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentModal<?php echo $payment['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-credit-card me-2"></i>
                        Payment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Transaction Details</h6>
                                    <div class="mb-2">
                                        <small class="text-muted">Reference</small>
                                        <p class="mb-0">
                                            <code><?php echo htmlspecialchars($payment['paystack_reference']); ?></code>
                                        </p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Amount</small>
                                        <h4 class="mb-0 text-success">₦<?php echo number_format($payment['amount'], 2); ?></h4>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Status</small>
                                        <p class="mb-0">
                                            <span class="badge bg-<?php echo $status_colors[$payment['status']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Method</small>
                                        <p class="mb-0">
                                            <span class="badge bg-<?php echo $method_badges[$payment['payment_method']] ?? 'secondary'; ?>">
                                                <?php echo str_replace('_', ' ', $payment['payment_method']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Date</small>
                                        <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></p>
                                    </div>
                                    <?php if($payment['paid_at']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Paid At</small>
                                        <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($payment['paid_at'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Customer & Order</h6>
                                    <div class="mb-2">
                                        <small class="text-muted">Customer</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($payment['customer_name']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Email</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($payment['customer_email']); ?></p>
                                    </div>
                                    <?php if($payment['customer_phone']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Phone</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($payment['customer_phone']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Order</small>
                                        <p class="mb-0">
                                            <a href="../orders/order-details.php?id=<?php echo $payment['order_id']; ?>" 
                                               class="text-decoration-none">
                                                <?php echo $payment['order_number']; ?>
                                            </a>
                                            <span class="badge bg-<?php echo $order_status_colors[$payment['order_status']] ?? 'secondary'; ?> ms-2">
                                                <?php echo $payment['order_status']; ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Order Amount</small>
                                        <p class="mb-0">₦<?php echo number_format($payment['total_amount'], 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if($payment['paystack_response']): ?>
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Payment Response</h6>
                                <pre class="mb-0" style="max-height: 200px; overflow-y: auto; font-size: 12px;">
                                    <?php 
                                    $response = json_decode($payment['paystack_response'], true);
                                    echo json_encode($response, JSON_PRETTY_PRINT); 
                                    ?>
                                </pre>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    
    // Refresh payments
    const refreshBtn = document.getElementById('refreshPayments');
    const mobileRefreshBtn = document.getElementById('mobileRefreshPayments');
    
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
                const paymentId = this.closest('.payment-row').dataset.paymentId;
                const modal = new bootstrap.Modal(document.getElementById('paymentModal' + paymentId));
                modal.show();
            }
        });
    });
});

function exportPayments() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = 'export-payments.php?' + params.toString();
    link.download = 'payments-export.csv';
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
        
        /* Payment amount */
        .text-success {
            font-size: 1.1rem;
            font-weight: 600;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .payment-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .payment-row:hover .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        }
    }
    

`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>