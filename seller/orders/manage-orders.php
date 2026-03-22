<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get order statistics
$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ?", [$seller_id])['count'],
    'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'confirmed' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'confirmed'", [$seller_id])['count'],
    'processing' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'processing'", [$seller_id])['count'],
    'shipped' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'shipped'", [$seller_id])['count'],
    'delivered' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'delivered'", [$seller_id])['count'],
    'cancelled' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'cancelled'", [$seller_id])['count']
];

// Build query with search
$where = "oi.seller_id = ?";
$params = [$seller_id];

if ($status_filter && $status_filter !== 'all') {
    $where .= " AND oi.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where .= " AND (o.order_number LIKE ? OR p.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total for pagination
$total_orders = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE $where
", $params)['count'];

$total_pages = ceil($total_orders / $limit);

// Get orders with pagination
$orders = $db->fetchAll("
    SELECT 
        oi.id as order_item_id,
        oi.order_id,
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.item_total,
        oi.status as item_status,
        o.order_number,
        o.created_at,
        o.payment_status,
        p.name as product_name,
        p.unit,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image,
        u.first_name,
        u.last_name,
        u.email,
        u.phone
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE $where
    ORDER BY 
        CASE oi.status 
            WHEN 'pending' THEN 1
            WHEN 'confirmed' THEN 2
            WHEN 'processing' THEN 3
            WHEN 'shipped' THEN 4
            WHEN 'delivered' THEN 5
            WHEN 'cancelled' THEN 6
            ELSE 7
        END,
        o.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $stats['pending'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Manage Orders";
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
                        <h1 class="h5 mb-0">Manage Orders</h1>
                        <small class="text-muted">Track and process customer orders</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Orders</h1>
                    <p class="text-muted mb-0">View and process all customer orders</p>
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
                    <a href="bulk-processing.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-box-seam me-1"></i> Bulk Process
                    </a>
                </div>
            </div>

            <!-- Order Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=all" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo !$status_filter || $status_filter == 'all' ? 'bg-light' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Total</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['total']); ?></h3>
                                <small class="text-secondary">All orders</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=pending" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'pending' ? 'bg-warning bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Pending</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['pending']); ?></h3>
                                <small class="text-warning">Awaiting action</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=confirmed" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'confirmed' ? 'bg-info bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Confirmed</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['confirmed']); ?></h3>
                                <small class="text-info">Ready to process</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=processing" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'processing' ? 'bg-primary bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Processing</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['processing']); ?></h3>
                                <small class="text-primary">In progress</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=shipped" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'shipped' ? 'bg-info bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Shipped</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['shipped']); ?></h3>
                                <small class="text-info">In transit</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=delivered" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'delivered' ? 'bg-success bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Delivered</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['delivered']); ?></h3>
                                <small class="text-success">Completed</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2">
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Search order #, product, customer..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <select name="status" class="form-select">
                                <option value="all">All Statuses</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-3 col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter me-1"></i> Filter
                            </button>
                        </div>
                        <div class="col-3 col-md-2">
                            <a href="manage-orders.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle me-1"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Summary -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">
                        <?php echo $status_filter && $status_filter != 'all' ? ucfirst($status_filter) . ' Orders' : 'All Orders'; ?>
                        <span class="text-muted fs-6 ms-2">(<?php echo number_format($total_orders); ?>)</span>
                    </h5>
                    <small class="text-muted">
                        Showing <?php echo min($offset + 1, $total_orders); ?>-<?php echo min($offset + $limit, $total_orders); ?> of <?php echo $total_orders; ?> orders
                    </small>
                </div>
                <div>
                    <span class="badge bg-info">
                        <i class="bi bi-clock me-1"></i>
                        Last updated: <?php echo date('h:i A'); ?>
                    </span>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No orders found</h4>
                            <p class="text-muted mb-4">No orders match your current filters.</p>
                            <a href="manage-orders.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order & Product</th>
                                        <th>Customer</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Payment</th>
                                        <th class="text-center">Date</th>
                                        <th class="text-center">Actions</th>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): 
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'confirmed' => 'info',
                                                'processing' => 'primary',
                                                'shipped' => 'info',
                                                'delivered' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $status_color = $status_colors[$order['item_status']] ?? 'secondary';
                                            
                                            $payment_colors = [
                                                'pending' => 'warning',
                                                'paid' => 'success',
                                                'failed' => 'danger',
                                                'refunded' => 'info'
                                            ];
                                            $payment_color = $payment_colors[$order['payment_status']] ?? 'secondary';
                                        ?>
                                            <tr class="order-row" data-order-id="<?php echo $order['order_id']; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($order['product_image']): ?>
                                                            <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $order['product_image']; ?>" 
                                                                 alt="<?php echo htmlspecialchars($order['product_name']); ?>"
                                                                 class="rounded me-3" 
                                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                                 style="width: 50px; height: 50px;">
                                                                <i class="bi bi-box text-muted fs-4"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                               class="fw-bold text-decoration-none">
                                                                #<?php echo htmlspecialchars($order['order_number']); ?>
                                                            </a>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($order['product_name']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($order['phone']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold"><?php echo number_format($order['quantity']); ?></span>
                                                    <small class="text-muted"> <?php echo $order['unit']; ?></small>
                                                </td>
                                                <td class="text-end">
                                                    <span class="fw-bold text-success">₦<?php echo number_format($order['item_total'], 2); ?></span>
                                                    <br>
                                                    <small class="text-muted">₦<?php echo number_format($order['unit_price'], 2); ?>/<?php echo $order['unit']; ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo ucfirst($order['item_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?php echo $payment_color; ?>">
                                                        <i class="bi bi-<?php echo $order['payment_status'] == 'paid' ? 'check-circle' : 'clock'; ?> me-1"></i>
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div>
                                                        <?php echo formatDate($order['created_at'], 'M j, Y'); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo formatDate($order['created_at'], 'h:i A'); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                           class="btn btn-outline-primary" 
                                                           data-bs-toggle="tooltip" 
                                                           title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($order['item_status'] == 'pending'): ?>
                                                            <button type="button" 
                                                                    class="btn btn-outline-success" 
                                                                    onclick="updateStatus(<?php echo $order['order_item_id']; ?>, 'confirmed')"
                                                                    data-bs-toggle="tooltip" 
                                                                    title="Confirm Order">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($order['item_status'] == 'confirmed'): ?>
                                                            <button type="button" 
                                                                    class="btn btn-outline-primary" 
                                                                    onclick="updateStatus(<?php echo $order['order_item_id']; ?>, 'processing')"
                                                                    data-bs-toggle="tooltip" 
                                                                    title="Start Processing">
                                                                <i class="bi bi-gear"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($order['item_status'] == 'processing'): ?>
                                                            <button type="button" 
                                                                    class="btn btn-outline-info" 
                                                                    onclick="updateStatus(<?php echo $order['order_item_id']; ?>, 'shipped')"
                                                                    data-bs-toggle="tooltip" 
                                                                    title="Mark as Shipped">
                                                                <i class="bi bi-truck"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($order['item_status'] == 'shipped'): ?>
                                                            <button type="button" 
                                                                    class="btn btn-outline-success" 
                                                                    onclick="updateStatus(<?php echo $order['order_item_id']; ?>, 'delivered')"
                                                                    data-bs-toggle="tooltip" 
                                                                    title="Mark as Delivered">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($order['item_status'], ['pending', 'confirmed', 'processing'])): ?>
                                                            <button type="button" 
                                                                    class="btn btn-outline-danger" 
                                                                    onclick="updateStatus(<?php echo $order['order_item_id']; ?>, 'cancelled')"
                                                                    data-bs-toggle="tooltip" 
                                                                    title="Cancel Order">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Add a note (optional)</label>
                    <textarea class="form-control" id="statusNote" rows="3" placeholder="Add any notes about this status change..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusUpdate">
                    <i class="bi bi-check-circle me-1"></i> Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentOrderItemId = null;
let newStatus = null;
let statusModal = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('statusModal');
    if (modalElement) {
        statusModal = new bootstrap.Modal(modalElement);
    }
    
    // Refresh button
    document.getElementById('refreshOrders')?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
        btn.disabled = true;
        setTimeout(() => window.location.reload(), 500);
    });
    
    // Confirm status update
    document.getElementById('confirmStatusUpdate')?.addEventListener('click', function() {
        if (currentOrderItemId && newStatus) {
            performStatusUpdate(currentOrderItemId, newStatus);
        }
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});

function updateStatus(orderItemId, status) {
    currentOrderItemId = orderItemId;
    newStatus = status;
    
    // Show modal for notes
    if (statusModal) {
        statusModal.show();
    } else {
        // Fallback to confirm dialog if modal not available
        if (confirm('Are you sure you want to update this order status?')) {
            performStatusUpdate(orderItemId, status);
        }
    }
}

function performStatusUpdate(orderItemId, status) {
    const note = document.getElementById('statusNote')?.value || '';
    
    // Show loading on the button that was clicked
    const buttons = document.querySelectorAll(`button[onclick*="updateStatus(${orderItemId}"]`);
    let btn = null;
    if (buttons.length > 0) {
        btn = buttons[buttons.length - 1];
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
    }
    
    fetch('update-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_item_id: orderItemId,
            status: status,
            note: note
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide modal
            if (statusModal) {
                statusModal.hide();
            }
            
            // Show success message
            showToast('Order status updated successfully', 'success');
            
            // Reload after short delay
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'danger');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'danger');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        }
    });
}

function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = 'export-orders.php?' + params.toString();
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

// Add spinning animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .dashboard-card.bg-light {
        background-color: #f8f9fc !important;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
    }
    
    @media (max-width: 768px) {
        .table-responsive {
            border: 0;
        }
        
        .table td:nth-child(2),
        .table td:nth-child(4),
        .table td:nth-child(6) {
            display: none;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>