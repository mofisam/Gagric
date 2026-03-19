<?php
require_once '../includes/auth.php';
requireSeller();
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Get seller profile info for sidebar
$seller_profile = $db->fetchOne("
    SELECT 
        sp.business_name,
        sp.business_logo as avatar,
        sp.created_at as join_date,
        sp.total_sales,
        sp.avg_rating,
        COUNT(DISTINCT sr.id) as total_reviews
    FROM seller_profiles sp
    LEFT JOIN seller_ratings sr ON sp.user_id = sr.seller_id
    WHERE sp.user_id = ?
    GROUP BY sp.id
", [$seller_id]);

// Get current month period
$current_month = date('Y-m-01 00:00:00');
$last_month_start = date('Y-m-01', strtotime('-1 month')) . ' 00:00:00';
$last_month_end = date('Y-m-t', strtotime('-1 month')) . ' 23:59:59';

// Get comprehensive seller stats
$stats = $db->fetchOne("
    SELECT 
        -- Product stats
        COUNT(DISTINCT p.id) as total_products,
        SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as active_products,
        SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as pending_products,
        SUM(CASE WHEN p.status = 'rejected' THEN 1 ELSE 0 END) as rejected_products,
        
        -- Stock stats
        SUM(CASE WHEN p.stock_quantity <= p.low_stock_alert_level AND p.stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN p.stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
        
        -- Order stats
        COUNT(DISTINCT oi.order_id) as total_orders,
        SUM(CASE WHEN oi.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN oi.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN oi.status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
        SUM(CASE WHEN oi.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN oi.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        
        -- Revenue stats
        COALESCE(SUM(CASE WHEN oi.status = 'delivered' THEN oi.item_total ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN o.created_at >= ? AND oi.status = 'delivered' THEN oi.item_total ELSE 0 END), 0) as monthly_revenue,
        COALESCE(SUM(CASE WHEN o.created_at BETWEEN ? AND ? AND oi.status = 'delivered' THEN oi.item_total ELSE 0 END), 0) as last_month_revenue,
        
        -- Customer stats
        COUNT(DISTINCT o.buyer_id) as total_customers
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id AND oi.seller_id = p.seller_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE p.seller_id = ?
", [$current_month, $last_month_start, $last_month_end, $seller_id]);

// Calculate revenue change percentage
$revenue_change = 0;
if ($stats['last_month_revenue'] > 0) {
    $revenue_change = (($stats['monthly_revenue'] - $stats['last_month_revenue']) / $stats['last_month_revenue']) * 100;
}

// Get recent orders with customer details
$recent_orders = $db->fetchAll("
    SELECT 
        oi.*, 
        o.order_number, 
        o.created_at,
        o.buyer_id,
        o.paid_at,
        o.delivered_at,
        p.name as product_name,
        p.unit,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image,
        u.first_name,
        u.last_name,
        u.phone
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE oi.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
", [$seller_id]);

// Get top selling products
$top_products = $db->fetchAll("
    SELECT 
        p.id,
        p.name,
        p.price_per_unit,
        p.unit,
        p.stock_quantity,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
        COUNT(oi.id) as sales_count,
        COALESCE(SUM(oi.quantity), 0) as total_quantity,
        COALESCE(SUM(oi.item_total), 0) as revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id AND oi.status = 'delivered'
    WHERE p.seller_id = ?
    GROUP BY p.id
    HAVING sales_count > 0
    ORDER BY revenue DESC
    LIMIT 5
", [$seller_id]);

// Get recent seller ratings
$recent_ratings = $db->fetchAll("
    SELECT 
        sr.*,
        p.name as product_name,
        u.first_name, 
        u.last_name,
        u.profile_image as customer_avatar,
        o.order_number
    FROM seller_ratings sr
    JOIN orders o ON sr.order_id = o.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON sr.buyer_id = u.id
    WHERE sr.seller_id = ?
    ORDER BY sr.created_at DESC
    LIMIT 5
", [$seller_id]);

// Get low stock products
$low_stock_products = $db->fetchAll("
    SELECT 
        id, name, stock_quantity, low_stock_alert_level, unit
    FROM products 
    WHERE seller_id = ? 
        AND status = 'approved'
        AND stock_quantity <= low_stock_alert_level
        AND stock_quantity > 0
    ORDER BY stock_quantity ASC
    LIMIT 5
", [$seller_id]);

$out_of_stock_count = $db->fetchOne("
    SELECT COUNT(*) as count FROM products 
    WHERE seller_id = ? AND stock_quantity <= 0
", [$seller_id])['count'];

// Calculate today's orders
$today_orders = $db->fetchOne("
    SELECT COUNT(*) as count FROM order_items 
    WHERE seller_id = ? AND DATE(created_at) = CURDATE()
", [$seller_id])['count'] ?? 0;

// Pass stats to sidebar
$seller_stats = [
    'pending_products' => $stats['pending_products'] ?? 0,
    'low_stock_count' => $stats['low_stock_count'] ?? 0,
    'pending_orders' => $stats['pending_orders'] ?? 0,
    'out_of_stock_count' => $out_of_stock_count,
    'today_orders' => $today_orders,
    'total_orders' => $stats['total_orders'] ?? 0,
    'total_revenue' => $stats['total_revenue'] ?? 0,
    'total_products' => $stats['total_products'] ?? 0
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;
$_SESSION['join_date'] = $seller_profile['join_date'] ?? date('Y-m-d');
$_SESSION['avatar'] = $seller_profile['avatar'] ?? null;

$page_title = "Seller Dashboard";
$page_css = 'dashboard.css';
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
                        <h1 class="h5 mb-0">Seller Dashboard</h1>
                        <small class="text-muted"><?php echo date('F j, Y'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Seller'); ?>!</h1>
                    <p class="text-muted mb-0">Here's what's happening with your store today.</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshData">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <a href="products/add-product.php" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Add Product
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <!-- Total Products -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Products</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_products'] ?? 0); ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <?php echo number_format($stats['active_products'] ?? 0); ?> active
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-box-seam fs-4 text-primary"></i>
                                </div>
                            </div>
                            <?php if(($stats['pending_products'] ?? 0) > 0): ?>
                                <div class="mt-2">
                                    <span class="badge bg-warning">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo $stats['pending_products']; ?> pending approval
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Total Orders -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Orders</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_orders'] ?? 0); ?></h3>
                                    <small class="text-info">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <?php echo number_format($stats['completed_orders'] ?? 0); ?> completed
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cart-check fs-4 text-success"></i>
                                </div>
                            </div>
                            <?php if(($stats['pending_orders'] ?? 0) > 0): ?>
                                <div class="mt-2">
                                    <span class="badge bg-warning">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo $stats['pending_orders']; ?> pending
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Revenue -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Revenue</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                                    <small class="<?php echo $revenue_change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi bi-arrow-<?php echo $revenue_change >= 0 ? 'up' : 'down'; ?>-short"></i>
                                        <?php echo number_format(abs($revenue_change), 1); ?>% vs last month
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cash-stack fs-4 text-warning"></i>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    Monthly: ₦<?php echo number_format($stats['monthly_revenue'] ?? 0, 2); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Rating -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Seller Rating</h6>
                                    <div class="d-flex align-items-center">
                                        <h3 class="card-title mb-0 me-2"><?php echo number_format($seller_profile['avg_rating'] ?? 0, 1); ?></h3>
                                        <div>
                                            <?php 
                                            $rating = round($seller_profile['avg_rating'] ?? 0);
                                            for($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="bi bi-star<?php echo $i <= $rating ? '-fill' : ''; ?> text-warning small"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        Based on <?php echo number_format($seller_profile['total_reviews'] ?? 0); ?> reviews
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-star fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row g-4">
                <!-- Recent Orders Column -->
                <div class="col-12 col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2 text-primary"></i>
                                Recent Orders
                            </h5>
                            <div class="btn-group btn-group-sm">
                                <a href="orders/manage-orders.php" class="btn btn-outline-primary">
                                    View All
                                </a>
                                <a href="orders/manage-orders.php?filter=pending" class="btn btn-primary">
                                    <i class="bi bi-clock me-1"></i> Pending (<?php echo $stats['pending_orders'] ?? 0; ?>)
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_orders)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                                    <h6 class="text-muted">No recent orders</h6>
                                    <p class="text-muted small">Your orders will appear here once customers start purchasing.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Order & Product</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($order['product_image']): ?>
                                                            <img src="<?php echo BASE_URL . '/uploads/products/' . $order['product_image']; ?>" 
                                                                 alt="<?php echo htmlspecialchars($order['product_name']); ?>"
                                                                 class="rounded me-3" 
                                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px;">
                                                                <i class="bi bi-box text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($order['product_name']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['phone']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong class="text-success">₦<?php echo number_format($order['item_total'], 2); ?></strong>
                                                    <br>
                                                    <small class="text-muted">Qty: <?php echo number_format($order['quantity']); ?> <?php echo $order['unit']; ?></small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'confirmed' => 'info',
                                                        'shipped' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $color = $status_colors[$order['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="orders/order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
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

                <!-- Right Column - Insights & Actions -->
                <div class="col-12 col-lg-4">
                    <!-- Inventory Alerts -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>
                                Inventory Alerts
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($low_stock_products) && $out_of_stock_count == 0): ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                    <p class="text-muted mb-0">All inventory levels are healthy!</p>
                                </div>
                            <?php else: ?>
                                <!-- Low Stock Items -->
                                <?php if (!empty($low_stock_products)): ?>
                                    <h6 class="text-warning mb-2">Low Stock Items</h6>
                                    <?php foreach ($low_stock_products as $product): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                            <div>
                                                <small class="d-block fw-bold"><?php echo htmlspecialchars($product['name']); ?></small>
                                                <small class="text-muted">Stock: <?php echo number_format($product['stock_quantity']); ?> <?php echo $product['unit']; ?></small>
                                            </div>
                                            <span class="badge bg-warning">Alert</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <!-- Out of Stock -->
                                <?php if ($out_of_stock_count > 0): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <div>
                                            <small class="d-block fw-bold">Out of Stock Items</small>
                                            <small class="text-muted"><?php echo $out_of_stock_count; ?> products need restock</small>
                                        </div>
                                        <span class="badge bg-danger"><?php echo $out_of_stock_count; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3 d-grid">
                                    <a href="inventory/stock-management.php" class="btn btn-warning btn-sm">
                                        <i class="bi bi-boxes me-1"></i> Manage Inventory
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Selling Products -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-trophy me-2 text-warning"></i>
                                Top Selling Products
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($top_products)): ?>
                                <p class="text-muted text-center py-3">No sales data available yet</p>
                            <?php else: ?>
                                <?php foreach ($top_products as $index => $product): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <?php if ($index == 0): ?>
                                                <span class="badge bg-warning text-white rounded-circle p-2">🥇</span>
                                            <?php elseif ($index == 1): ?>
                                                <span class="badge bg-secondary text-white rounded-circle p-2">🥈</span>
                                            <?php elseif ($index == 2): ?>
                                                <span class="badge bg-bronze text-white rounded-circle p-2">🥉</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark rounded-circle p-2">#<?php echo $index + 1; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="d-flex align-items-center">
                                                <?php if ($product['primary_image']): ?>
                                                    <img src="<?php echo BASE_URL . '/uploads/products/' . $product['primary_image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         class="rounded me-2" 
                                                         style="width: 30px; height: 30px; object-fit: cover;">
                                                <?php endif; ?>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $product['sales_count']; ?> sales • 
                                                ₦<?php echo number_format($product['revenue'] ?? 0, 0); ?>
                                            </small>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <?php $max_revenue = $top_products[0]['revenue'] ?? 1; ?>
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?php echo ($product['revenue'] / $max_revenue) * 100; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="mt-3 d-grid">
                                    <a href="analytics/sales-analytics.php" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-bar-chart me-1"></i> View Detailed Analytics
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Reviews -->
                    <?php if (!empty($recent_ratings)): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-star me-2 text-warning"></i>
                                Recent Reviews
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($recent_ratings as $rating): ?>
                                <div class="mb-3 pb-2 border-bottom">
                                    <div class="d-flex justify-content-between">
                                        <small class="fw-bold"><?php echo htmlspecialchars($rating['first_name'] . ' ' . $rating['last_name']); ?></small>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $rating['rating'] ? '-fill' : ''; ?> small"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="small mb-1"><?php echo htmlspecialchars($rating['review'] ?? 'No comment'); ?></p>
                                    <small class="text-muted">on order #<?php echo $rating['order_number']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightning-charge me-2 text-primary"></i>
                                Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="products/add-product.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle me-2"></i> Add New Product
                                </a>
                                <a href="orders/manage-orders.php?filter=pending" class="btn btn-primary">
                                    <i class="bi bi-box-seam me-2"></i> Process Orders
                                </a>
                                <a href="inventory/stock-management.php" class="btn btn-info text-white">
                                    <i class="bi bi-boxes me-2"></i> Update Stock
                                </a>
                                <a href="store/profile.php" class="btn btn-warning">
                                    <i class="bi bi-shop me-2"></i> Update Store Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- JavaScript for interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh button
    document.getElementById('refreshData')?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
        btn.disabled = true;
        
        setTimeout(() => {
            window.location.reload();
        }, 500);
    });
});

// Add bronze color for CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    .bg-bronze {
        background-color: #cd7f32 !important;
    }
    
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>