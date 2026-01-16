<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';

requireAdmin();

$db = new Database();

// Handle order status update
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    
    $db->query("UPDATE orders SET status = ? WHERE id = ?", [$new_status, $order_id]);
    setFlashMessage('Order status updated', 'success');
    header('Location: manage-orders.php');
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
$payment_status = $_GET['payment_status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    $where .= " AND o.status = ?";
    $params[] = $status;
}

if (!empty($payment_status)) {
    $where .= " AND o.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($date_from)) {
    $where .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

// Get orders
$orders = $db->fetchAll("
    SELECT o.*, 
           u.first_name, u.last_name, u.email, u.phone,
           COUNT(oi.id) as items_count,
           SUM(oi.quantity) as total_quantity,
           COUNT(DISTINCT oi.seller_id) as sellers_count
    FROM orders o 
    JOIN users u ON o.buyer_id = u.id 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    $where 
    GROUP BY o.id 
    ORDER BY o.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

// Total count for pagination
$total_orders = $db->fetchOne("SELECT COUNT(*) as count FROM orders o JOIN users u ON o.buyer_id = u.id $where", $params)['count'];
$total_pages = ceil($total_orders / $limit);

// Stats
$stats = [
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders")['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'],
    'processing_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'processing'")['count'],
    'shipped_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'shipped'")['count'],
    'delivered_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'delivered'")['count'],
    'cancelled_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'cancelled'")['count'],
    'total_revenue' => $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'")['total'] ?? 0,
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")['count'],
    'pending_payment' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'")['count']
];

$page_title = "Manage Orders";
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
                        <h1 class="h5 mb-0 text-center">Orders</h1>
                        <small class="text-muted d-block text-center"><?php echo $stats['total_orders']; ?> total</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshOrders">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Orders</h1>
                    <p class="text-muted mb-0">Track and manage all customer orders</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportOrders()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshOrders">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <?php if($stats['pending_orders'] > 0): ?>
                        <a href="?status=pending" class="btn btn-sm btn-warning">
                            <i class="bi bi-clock me-1"></i> <?php echo $stats['pending_orders']; ?> Pending
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
                                <h6 class="mb-0"><?php echo number_format($stats['total_orders']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Pending</small>
                                <h6 class="mb-0 text-warning"><?php echo number_format($stats['pending_orders']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Delivered</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['delivered_orders']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Revenue</small>
                                <h6 class="mb-0 text-primary">₦<?php echo number_format($stats['total_revenue'], 0); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="d-none d-md-flex row g-3 mb-4">
                <!-- Total Orders -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Orders</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_orders']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo $stats['today_orders']; ?> today
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-bag fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Orders -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['pending_orders']); ?></h3>
                                    <?php if($stats['pending_orders'] > 0): ?>
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            Needs attention
                                        </small>
                                    <?php else: ?>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            All processed
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-clock fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Processing Orders -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Processing</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['processing_orders']); ?></h3>
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
                
                <!-- Total Revenue -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Revenue</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                                    <small class="text-success">
                                        <?php echo $stats['delivered_orders']; ?> delivered
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-cash-stack fs-5 text-success"></i>
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
                        Filter Orders
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
                                           placeholder="Search orders..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="payment_status" class="form-select form-select-sm">
                                    <option value="">All Payment</option>
                                    <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Payment Pending</option>
                                    <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="failed" <?php echo $payment_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="refunded" <?php echo $payment_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
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
                                    <a href="manage-orders.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-1"></i> Clear
                                    </a>
                                    <?php if($stats['pending_orders'] > 0): ?>
                                        <a href="?status=pending" class="btn btn-warning btn-sm">
                                            <i class="bi bi-clock me-1"></i> View Pending
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
                    <h5 class="mb-0 d-none d-md-block">Orders (<?php echo $total_orders; ?>)</h5>
                    <small class="text-muted">
                        <?php echo $total_orders; ?> total • 
                        Showing <?php echo min($offset + 1, $total_orders); ?>-<?php echo min($offset + $limit, $total_orders); ?>
                    </small>
                </div>
                <div class="text-muted d-none d-md-block">
                    <?php if($stats['pending_orders'] > 0): ?>
                        <span class="badge bg-warning">
                            <i class="bi bi-clock me-1"></i>
                            <?php echo $stats['pending_orders']; ?> pending
                        </span>
                    <?php endif; ?>
                    <?php if($stats['pending_payment'] > 0): ?>
                        <span class="badge bg-danger ms-2">
                            <i class="bi bi-credit-card me-1"></i>
                            <?php echo $stats['pending_payment']; ?> unpaid
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-bag text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No orders found</h4>
                            <p class="text-muted mb-4">No orders match your current filters.</p>
                            <a href="manage-orders.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="120">Order #</th>
                                        <th>Customer</th>
                                        <th width="100">Amount</th>
                                        <th width="100">Status</th>
                                        <th width="100">Payment</th>
                                        <th width="100">Date</th>
                                        <th width="120" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <?php 
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'confirmed' => 'info',
                                            'processing' => 'primary',
                                            'shipped' => 'info',
                                            'delivered' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        
                                        $payment_colors = [
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'secondary'
                                        ];
                                        ?>
                                        
                                        <tr class="order-row" data-order-id="<?php echo $order['id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                        <i class="bi bi-bag text-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo $order['order_number']; ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo $order['items_count']; ?> items
                                                            <?php if($order['sellers_count'] > 1): ?>
                                                                • <?php echo $order['sellers_count']; ?> sellers
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                                    <?php if($order['phone']): ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['phone']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong class="text-success">₦<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $order['total_quantity'] ?? 0; ?> units
                                                </small>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $payment_colors[$order['payment_status']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#statusModal<?php echo $order['id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if($order['status'] == 'shipped'): ?>
                                                        <button class="btn btn-outline-success"
                                                                onclick="markDelivered(<?php echo $order['id']; ?>)">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Order & Status -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <i class="bi bi-bag text-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo $order['order_number']; ?></strong>
                                                                <br>
                                                                <span class="badge bg-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>">
                                                                    <?php echo ucfirst($order['status']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <strong class="text-success">₦<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo $order['items_count']; ?> items
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 2: Customer & Payment -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <small class="text-muted">Customer:</small>
                                                                <br>
                                                                <small><?php echo htmlspecialchars(substr($order['first_name'] . ' ' . $order['last_name'], 0, 20)); ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted">Payment:</small>
                                                                <br>
                                                                <span class="badge bg-<?php echo $payment_colors[$order['payment_status']] ?? 'secondary'; ?>">
                                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Date & Actions -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <?php echo date('M j, g:i A', strtotime($order['created_at'])); ?>
                                                        </small>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#statusModal<?php echo $order['id']; ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <?php if($order['status'] == 'shipped'): ?>
                                                                <button class="btn btn-sm btn-outline-success"
                                                                        onclick="markDelivered(<?php echo $order['id']; ?>)">
                                                                    <i class="bi bi-check"></i>
                                                                </button>
                                                            <?php endif; ?>
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

<!-- Modals for each order -->
<?php foreach ($orders as $order): ?>
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>
                        Update Order Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Order #<?php echo $order['order_number']; ?></label>
                            <p class="text-muted mb-2">Customer: <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <p class="mb-0">
                                <span class="badge bg-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status<?php echo $order['id']; ?>" class="form-label">New Status</label>
                            <select class="form-select" id="status<?php echo $order['id']; ?>" name="status" required>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="notifyCustomer<?php echo $order['id']; ?>" name="notify_customer" checked>
                            <label class="form-check-label" for="notifyCustomer<?php echo $order['id']; ?>">
                                Notify customer about status change
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button name="update_status" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> Update Status
                        </button>
                    </div>
                </form>
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
    
    // Refresh orders
    const refreshBtn = document.getElementById('refreshOrders');
    const mobileRefreshBtn = document.getElementById('mobileRefreshOrders');
    
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
    
    // Make table rows clickable on mobile to view details
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('a')) {
                const orderId = this.closest('.order-row').dataset.orderId;
                window.location.href = 'order-details.php?id=' + orderId;
            }
        });
    });
    
    // Auto-refresh for pending orders
    if (window.location.search.includes('status=pending') || !window.location.search) {
        setInterval(() => {
            if (!document.hidden) {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const pendingCount = doc.querySelector('.badge.bg-warning')?.textContent;
                        const currentCount = document.querySelector('.badge.bg-warning')?.textContent;
                        
                        if (pendingCount && pendingCount !== currentCount) {
                            agriApp.showToast('New orders available. Refreshing...', 'info');
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    });
            }
        }, 30000);
    }
});

function markDelivered(orderId) {
    if (confirm('Mark this order as delivered?')) {
        fetch('update-order-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId,
                status: 'delivered'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                agriApp.showToast('Order marked as delivered', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                agriApp.showToast('Failed to update order', 'error');
            }
        })
        .catch(error => {
            agriApp.showToast('Network error. Please try again.', 'error');
        });
    }
}

function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = 'export-orders.php?' + params.toString();
    link.download = 'orders-export.csv';
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
        
        /* Order amounts */
        .text-success {
            font-size: 1.1rem;
            font-weight: 600;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .order-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .order-row:hover .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        }
    }

`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>