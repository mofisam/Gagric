<?php
$page_title = "Admin Dashboard";
$page_css = 'dashboard.css';
require_once '../includes/header.php';
require_once '../includes/auth.php';
require_once '../classes/Database.php';

requireAdmin();

$db = new Database();

// Get dashboard statistics
$stats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE")['count'],
    'total_sellers' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE is_approved = TRUE")['count'],
    'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'approved'")['count'],
    'pending_approvals' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'pending'")['count'],
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['count'],
    'total_revenue' => $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0,
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")['count'],
    'pending_sellers' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE is_approved = FALSE")['count'],
];

// Revenue comparison (vs last month)
$last_month_revenue = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0;
$revenue_change = $last_month_revenue > 0 ? (($stats['total_revenue'] - $last_month_revenue) / $last_month_revenue) * 100 : 100;

// Recent orders
$recent_orders = $db->fetchAll("
    SELECT o.*, u.first_name, u.last_name, u.phone 
    FROM orders o 
    JOIN users u ON o.buyer_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");

// Pending product approvals
$pending_products = $db->fetchAll("
    SELECT p.*, sp.business_name, u.phone as seller_phone
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN users u ON sp.user_id = u.id
    WHERE p.status = 'pending' 
    ORDER BY p.created_at DESC 
    LIMIT 5
");

// Top selling products
$top_products = $db->fetchAll("
    SELECT p.name, COUNT(oi.id) as sales_count, SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY p.id
    ORDER BY sales_count DESC
    LIMIT 5
");

?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile page header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-3" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0">Dashboard</h1>
                        <small class="text-muted"><?php echo date('F j, Y'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Dashboard Overview</h1>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="exportData">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshData">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-plus-circle me-1"></i> Quick Action
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/users/manage-users.php?action=add">
                                <i class="bi bi-person-plus me-2"></i> Add User
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/products/manage-products.php?action=add">
                                <i class="bi bi-plus-square me-2"></i> Add Product
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/products/product-approvals.php">
                                <i class="bi bi-shield-check me-2"></i> Review Approvals
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row g-3 mb-4">
                <!-- Total Users -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Users</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-arrow-up-short"></i> Active users
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-people fs-4 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Approved Sellers -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Approved Sellers</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_sellers']); ?></h3>
                                    <?php if($stats['pending_sellers'] > 0): ?>
                                        <small class="text-warning">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo $stats['pending_sellers']; ?> pending
                                        </small>
                                    <?php else: ?>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            All processed
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-shop fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Products -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Products</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_products']); ?></h3>
                                    <?php if($stats['pending_approvals'] > 0): ?>
                                        <small class="text-warning">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo $stats['pending_approvals']; ?> pending
                                        </small>
                                    <?php else: ?>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            All approved
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-box-seam fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Revenue -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Monthly Revenue</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                                    <small class="<?php echo $revenue_change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi bi-arrow-<?php echo $revenue_change >= 0 ? 'up' : 'down'; ?>-short"></i>
                                        <?php echo number_format(abs($revenue_change), 1); ?>% from last month
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cash-stack fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="row g-4">
                <!-- Recent Orders -->
                <div class="col-12 col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bag me-2 text-primary"></i>
                                Recent Orders
                            </h5>
                            <div class="btn-group btn-group-sm">
                                <a href="<?php echo BASE_URL; ?>/admin/orders/manage-orders.php" 
                                   class="btn btn-outline-primary">
                                    View All
                                </a>
                                <a href="<?php echo BASE_URL; ?>/admin/orders/manage-orders.php?filter=today" 
                                   class="btn btn-primary">
                                    <i class="bi bi-calendar-day me-1"></i> Today (<?php echo $stats['today_orders']; ?>)
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th class="d-none d-md-table-cell">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($recent_orders)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                    No recent orders
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr class="clickable-row" 
                                                onclick="window.location='<?php echo BASE_URL; ?>/admin/orders/order-details.php?id=<?php echo $order['id']; ?>'"
                                                style="cursor:pointer;">
                                                <td>
                                                    <strong><?php echo $order['order_number']; ?></strong>
                                                    <small class="d-block text-muted"><?php echo $order['phone']; ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                                </td>
                                                <td>
                                                    <strong class="text-success">₦<?php echo number_format($order['total_amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_badge = [
                                                        'pending' => 'warning',
                                                        'confirmed' => 'info',
                                                        'processing' => 'primary',
                                                        'shipped' => 'info',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger'
                                                    ][$order['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_badge; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="d-none d-md-table-cell">
                                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                                    <small class="d-block text-muted">
                                                        <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-12 col-lg-4">
                    <!-- Pending Approvals -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2 text-warning"></i>
                                Pending Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pending_products) && $stats['pending_orders'] == 0): ?>
                                <div class="text-center py-3 text-muted">
                                    <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                                    All caught up!
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php if($stats['pending_orders'] > 0): ?>
                                    <a href="<?php echo BASE_URL; ?>/admin/orders/manage-orders.php?filter=pending" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-bag me-2 text-info"></i>
                                            Pending Orders
                                        </div>
                                        <span class="badge bg-info rounded-pill">
                                            <?php echo $stats['pending_orders']; ?>
                                        </span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if($stats['pending_sellers'] > 0): ?>
                                    <a href="<?php echo BASE_URL; ?>/admin/users/seller-approvals.php" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-person-plus me-2 text-primary"></i>
                                            Seller Approvals
                                        </div>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $stats['pending_sellers']; ?>
                                        </span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($pending_products as $product): ?>
                                    <a href="<?php echo BASE_URL; ?>/admin/products/product-details.php?id=<?php echo $product['id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($product['business_name']); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo $product['seller_phone']; ?></small>
                                            </div>
                                            <small class="text-warning">Pending</small>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3 d-grid">
                                    <a href="<?php echo BASE_URL; ?>/admin/products/product-approvals.php" 
                                       class="btn btn-warning btn-sm">
                                        <i class="bi bi-shield-check me-1"></i>
                                        Review All Approvals
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-graph-up me-2 text-success"></i>
                                Quick Stats
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-primary fw-bold fs-4"><?php echo $stats['today_orders']; ?></div>
                                        <small class="text-muted">Today's Orders</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-success fw-bold fs-4"><?php echo $stats['total_orders']; ?></div>
                                        <small class="text-muted">30-day Orders</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-info fw-bold fs-4">₦<?php echo number_format($stats['total_revenue'] / 30, 2); ?></div>
                                        <small class="text-muted">Daily Average</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-warning fw-bold fs-4"><?php echo $stats['pending_approvals']; ?></div>
                                        <small class="text-muted">Pending Reviews</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Section (Top Products) -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-trophy me-2 text-warning"></i>
                                Top Selling Products (30 days)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if(empty($top_products)): ?>
                                <p class="text-muted mb-0">No sales data available</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th class="text-center">Sales Count</th>
                                                <th class="text-center">Total Quantity</th>
                                                <th class="text-center">Rank</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_products as $index => $product): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary"><?php echo $product['sales_count']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-success"><?php echo $product['total_quantity']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    $rank_icon = ['🥇', '🥈', '🥉', '4', '5'];
                                                    echo $rank_icon[$index];
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- JavaScript for Dashboard Interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    
    // Export button
    document.getElementById('exportData')?.addEventListener('click', function() {
        agriApp.showToast('Exporting data...', 'info');
        // Implement export functionality here
    });
    
    // Refresh button
    document.getElementById('refreshData')?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
        btn.disabled = true;
        
        setTimeout(() => {
            window.location.reload();
        }, 500);
    });
    
    // Make table rows clickable
    document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', function() {
            window.location = this.getAttribute('onclick').match(/window\.location='([^']+)'/)[1];
        });
    });
    
    // Auto-update dashboard every 5 minutes
    setInterval(() => {
        if (!document.hidden) {
            document.getElementById('refreshData')?.click();
        }
    }, 300000); // 5 minutes
});

// Add spinning animation for refresh icon
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    .sidebar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1029;
        display: none;
    }
    .sidebar-backdrop.show {
        display: block;
    }
    #sidebar.show {
        transform: translateX(0) !important;
    }
    @media (max-width: 767.98px) {
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1030;
        }
        .sidebar-toggle {
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>