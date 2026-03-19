<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Get seller profile info
$seller_profile = $db->fetchOne("
    SELECT 
        sp.business_name,
        sp.business_logo as avatar,
        sp.created_at as join_date,
        sp.total_sales,
        sp.avg_rating,
        COUNT(DISTINCT oi.id) as total_orders_count,
        COUNT(DISTINCT sr.id) as total_reviews_count
    FROM seller_profiles sp
    LEFT JOIN order_items oi ON sp.user_id = oi.seller_id AND oi.status = 'delivered'
    LEFT JOIN seller_ratings sr ON sp.user_id = sr.seller_id
    WHERE sp.user_id = ?
    GROUP BY sp.id
", [$seller_id]);

// Current month period
$current_month = date('Y-m-01 00:00:00');
$last_month_start = date('Y-m-01', strtotime('-1 month')) . ' 00:00:00';
$last_month_end = date('Y-m-t', strtotime('-1 month')) . ' 23:59:59';

// Performance metrics
$metrics = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.item_total), 0) as total_revenue,
        COALESCE(AVG(oi.item_total), 0) as avg_order_value,
        COUNT(DISTINCT o.buyer_id) as unique_customers,
        
        -- Product metrics
        (SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'approved') as active_products,
        (SELECT COUNT(*) FROM products WHERE seller_id = ?) as total_products,
        (SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'pending') as pending_products,
        
        -- Rating metrics
        (SELECT AVG(rating) FROM seller_ratings WHERE seller_id = ?) as avg_rating,
        (SELECT COUNT(*) FROM seller_ratings WHERE seller_id = ?) as total_reviews,
        
        -- Monthly metrics
        SUM(CASE WHEN o.created_at >= ? THEN oi.item_total ELSE 0 END) as monthly_revenue,
        COUNT(CASE WHEN o.created_at >= ? THEN 1 END) as monthly_orders,
        
        -- Last month metrics
        SUM(CASE WHEN o.created_at BETWEEN ? AND ? THEN oi.item_total ELSE 0 END) as last_month_revenue,
        COUNT(CASE WHEN o.created_at BETWEEN ? AND ? THEN 1 END) as last_month_orders
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? AND oi.status = 'delivered'
", [
    $seller_id, $seller_id, $seller_id,  // For product counts
    $seller_id, $seller_id,               // For rating metrics
    $current_month, $current_month,       // Monthly
    $last_month_start, $last_month_end,   // Last month
    $last_month_start, $last_month_end,   // Last month orders
    $seller_id
]);

// Order fulfillment time - using order_items tracking
$fulfillment = $db->fetchOne("
    SELECT 
        AVG(TIMESTAMPDIFF(HOUR, oi.created_at, o.delivered_at)) as avg_fulfillment_hours,
        MIN(TIMESTAMPDIFF(HOUR, oi.created_at, o.delivered_at)) as min_fulfillment_hours,
        MAX(TIMESTAMPDIFF(HOUR, oi.created_at, o.delivered_at)) as max_fulfillment_hours
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? AND oi.status = 'delivered' AND o.delivered_at IS NOT NULL
", [$seller_id]);

// Recent seller ratings (reviews)
$recent_reviews = $db->fetchAll("
    SELECT 
        sr.*,
        p.name as product_name,
        u.first_name, 
        u.last_name,
        u.profile_image as customer_avatar
    FROM seller_ratings sr
    JOIN orders o ON sr.order_id = o.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON sr.buyer_id = u.id
    WHERE sr.seller_id = ?
    ORDER BY sr.created_at DESC
    LIMIT 10
", [$seller_id]);

// Order status distribution
$order_stats = $db->fetchAll("
    SELECT 
        status, 
        COUNT(*) as count,
        SUM(item_total) as total
    FROM order_items
    WHERE seller_id = ?
    GROUP BY status
    ORDER BY 
        CASE status 
            WHEN 'pending' THEN 1
            WHEN 'confirmed' THEN 2
            WHEN 'shipped' THEN 3
            WHEN 'delivered' THEN 4
            WHEN 'cancelled' THEN 5
            ELSE 6
        END
", [$seller_id]);

// Product performance with ranking
$product_performance = $db->fetchAll("
    SELECT 
        p.id,
        p.name,
        p.price_per_unit,
        p.unit,
        p.stock_quantity,
        p.low_stock_alert_level,
        p.status,
        COUNT(oi.id) as orders,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.item_total), 0) as revenue,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id AND oi.status = 'delivered'
    WHERE p.seller_id = ?
    GROUP BY p.id
    HAVING orders > 0 OR revenue > 0
    ORDER BY revenue DESC
", [$seller_id]);

// Low stock products
$low_stock_products = $db->fetchAll("
    SELECT 
        id, name, stock_quantity, low_stock_alert_level
    FROM products 
    WHERE seller_id = ? 
        AND status = 'approved'
        AND stock_quantity <= low_stock_alert_level
        AND stock_quantity > 0
    ORDER BY stock_quantity ASC
", [$seller_id]);

$out_of_stock_count = $db->fetchOne("
    SELECT COUNT(*) as count FROM products 
    WHERE seller_id = ? AND stock_quantity <= 0
", [$seller_id])['count'];

// Calculate growth percentages
$revenue_growth = 0;
$order_growth = 0;
if (($metrics['last_month_revenue'] ?? 0) > 0) {
    $revenue_growth = (($metrics['monthly_revenue'] - $metrics['last_month_revenue']) / $metrics['last_month_revenue']) * 100;
}
if (($metrics['last_month_orders'] ?? 0) > 0) {
    $order_growth = (($metrics['monthly_orders'] - $metrics['last_month_orders']) / $metrics['last_month_orders']) * 100;
}

// Performance tips based on actual data
$tips = [];

// Rating tips
if (($metrics['avg_rating'] ?? 0) < 4 && ($metrics['avg_rating'] ?? 0) > 0) {
    $tips[] = [
        'icon' => 'star',
        'title' => 'Improve Your Rating',
        'message' => 'Your average rating is ' . number_format($metrics['avg_rating'], 1) . '/5. Consider reaching out to customers for feedback.',
        'color' => 'warning'
    ];
}

// Fulfillment tips
if (($fulfillment['avg_fulfillment_hours'] ?? 0) > 48) {
    $tips[] = [
        'icon' => 'clock',
        'title' => 'Reduce Fulfillment Time',
        'message' => 'Orders are taking ' . round($fulfillment['avg_fulfillment_hours']) . ' hours to fulfill. Streamline your process to improve customer satisfaction.',
        'color' => 'danger'
    ];
}

// Low stock tips
if (count($low_stock_products) > 0) {
    $tips[] = [
        'icon' => 'exclamation-triangle',
        'title' => 'Low Stock Alert',
        'message' => 'You have ' . count($low_stock_products) . ' products running low on stock. Restock soon to avoid missed sales.',
        'color' => 'warning'
    ];
}

// Out of stock tips
if ($out_of_stock_count > 0) {
    $tips[] = [
        'icon' => 'x-circle',
        'title' => 'Out of Stock Items',
        'message' => $out_of_stock_count . ' products are out of stock. Update inventory to reactivate them.',
        'color' => 'danger'
    ];
}

// Pending products tips
if (($metrics['pending_products'] ?? 0) > 0) {
    $tips[] = [
        'icon' => 'clock-history',
        'title' => 'Pending Approvals',
        'message' => 'You have ' . $metrics['pending_products'] . ' products waiting for admin approval.',
        'color' => 'info'
    ];
}

// If no specific issues, show positive tip
if (empty($tips)) {
    $tips[] = [
        'icon' => 'check-circle',
        'title' => 'Great Performance!',
        'message' => 'You\'re doing excellently! Keep monitoring these metrics to maintain your performance.',
        'color' => 'success'
    ];
}

// Pass stats to sidebar
$seller_stats = [
    'pending_products' => $metrics['pending_products'] ?? 0,
    'low_stock_count' => count($low_stock_products),
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'out_of_stock_count' => $out_of_stock_count,
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND DATE(created_at) = CURDATE()", [$seller_id])['count'] ?? 0,
    'total_orders' => $metrics['total_orders'] ?? 0,
    'total_revenue' => $metrics['total_revenue'] ?? 0,
    'total_products' => $metrics['total_products'] ?? 0
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;
$_SESSION['join_date'] = $seller_profile['join_date'] ?? date('Y-m-d');
$_SESSION['avatar'] = $seller_profile['avatar'] ?? null;

$page_title = "Performance Metrics";
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
                        <h1 class="h5 mb-0">Performance Metrics</h1>
                        <small class="text-muted">Track your store's performance</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Performance Metrics</h1>
                    <p class="text-muted mb-0">Comprehensive insights into your store's performance</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportAsPDF()">
                            <i class="bi bi-download me-1"></i> Export Report
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshMetrics">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Performance Indicators -->
            <div class="row g-3 mb-4">
                <!-- Customer Rating -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Seller Rating</h6>
                                    <div class="d-flex align-items-baseline">
                                        <h3 class="card-title mb-0 me-2">
                                            <?php echo number_format($metrics['avg_rating'] ?? 0, 1); ?>
                                        </h3>
                                        <small class="text-muted">/5</small>
                                    </div>
                                    <div class="mt-1">
                                        <?php 
                                        $rating = round($metrics['avg_rating'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++): 
                                        ?>
                                            <i class="bi bi-star<?php echo $i <= $rating ? '-fill' : ''; ?> text-warning small"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted">
                                        Based on <?php echo number_format($metrics['total_reviews'] ?? 0); ?> reviews
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-star fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Customers -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Unique Customers</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($metrics['unique_customers'] ?? 0); ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-people me-1"></i>
                                        Lifetime customers
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-people fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Avg Order Value -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Avg Order Value</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($metrics['avg_order_value'] ?? 0, 2); ?></h3>
                                    <small class="text-info">
                                        <i class="bi bi-cart me-1"></i>
                                        Per delivered order
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cart-check fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fulfillment Time -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Fulfillment Time</h6>
                                    <div class="d-flex align-items-baseline">
                                        <h3 class="card-title mb-0 me-2">
                                            <?php
                                            $hours = $fulfillment['avg_fulfillment_hours'] ?? 0;
                                            if ($hours < 24) {
                                                echo round($hours) . 'h';
                                            } else {
                                                echo round($hours / 24, 1) . 'd';
                                            }
                                            ?>
                                        </h3>
                                    </div>
                                    <small class="text-muted">
                                        From order to delivery
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-truck fs-4 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Performance Summary -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-calendar-check me-2 text-primary"></i>
                                Monthly Performance
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded">
                                        <small class="text-muted d-block">This Month</small>
                                        <div class="h4 mb-1">₦<?php echo number_format($metrics['monthly_revenue'] ?? 0, 0); ?></div>
                                        <div class="small"><?php echo number_format($metrics['monthly_orders'] ?? 0); ?> orders</div>
                                        <?php if($revenue_growth != 0): ?>
                                            <small class="text-<?php echo $revenue_growth >= 0 ? 'success' : 'danger'; ?>">
                                                <i class="bi bi-arrow-<?php echo $revenue_growth >= 0 ? 'up' : 'down'; ?>"></i>
                                                <?php echo number_format(abs($revenue_growth), 1); ?>% vs last month
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-3 border rounded">
                                        <small class="text-muted d-block">Last Month</small>
                                        <div class="h4 mb-1">₦<?php echo number_format($metrics['last_month_revenue'] ?? 0, 0); ?></div>
                                        <div class="small"><?php echo number_format($metrics['last_month_orders'] ?? 0); ?> orders</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Active Products:</span>
                                        <span class="fw-bold"><?php echo number_format($metrics['active_products'] ?? 0); ?> / <?php echo number_format($metrics['total_products'] ?? 0); ?></span>
                                    </div>
                                    <div class="progress mt-1" style="height: 8px;">
                                        <?php $active_percent = ($metrics['total_products'] ?? 0) > 0 ? (($metrics['active_products'] ?? 0) / ($metrics['total_products'] ?? 1)) * 100 : 0; ?>
                                        <div class="progress-bar bg-success" style="width: <?php echo $active_percent; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Status Distribution -->
                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-pie-chart me-2 text-primary"></i>
                                Order Status Distribution
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($order_stats): ?>
                                <?php 
                                $total_order_items = array_sum(array_column($order_stats, 'count'));
                                $status_colors = [
                                    'pending' => 'warning', 
                                    'confirmed' => 'info', 
                                    'shipped' => 'primary', 
                                    'delivered' => 'success', 
                                    'cancelled' => 'danger'
                                ];
                                ?>
                                <?php foreach ($order_stats as $stat): 
                                    $percentage = $total_order_items > 0 ? ($stat['count'] / $total_order_items) * 100 : 0;
                                    $color = $status_colors[$stat['status']] ?? 'secondary';
                                ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($stat['status']); ?>
                                                </span>
                                                <small class="text-muted ms-2">
                                                    <?php echo $stat['count']; ?> items
                                                </small>
                                            </div>
                                            <small class="fw-bold"><?php echo number_format($percentage, 1); ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No order data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Performance Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-trophy me-2 text-warning"></i>
                        Top Selling Products
                    </h5>
                    <a href="../products/manage-products.php" class="btn btn-sm btn-outline-primary">Manage Products</a>
                </div>
                <div class="card-body">
                    <?php if ($product_performance): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Orders</th>
                                        <th class="text-center">Units Sold</th>
                                        <th class="text-center">Revenue</th>
                                        <th class="text-center">Stock Status</th>
                                        <th class="text-center">Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $max_revenue = $product_performance[0]['revenue'] ?? 1;
                                    foreach ($product_performance as $index => $product): 
                                        $performance_percent = ($product['revenue'] / $max_revenue) * 100;
                                        
                                        // Stock status
                                        if ($product['stock_quantity'] <= 0) {
                                            $stock_class = 'danger';
                                            $stock_text = 'Out of Stock';
                                        } elseif ($product['stock_quantity'] <= ($product['low_stock_alert_level'] ?? 10)) {
                                            $stock_class = 'warning';
                                            $stock_text = 'Low Stock';
                                        } else {
                                            $stock_class = 'success';
                                            $stock_text = 'In Stock';
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                    $primary_image = $product['primary_image'] ?? null;
                                                    ?>
                                                    <?php if ($primary_image): ?>
                                                        <img src="<?php echo BASE_URL . '/uploads/products/' . $primary_image; ?>" 
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                             class="rounded me-3" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-box text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <?php if ($index == 0): ?>
                                                            <span class="badge bg-warning ms-2">Best Seller</span>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted">₦<?php echo number_format($product['price_per_unit'], 2); ?>/<?php echo $product['unit']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center fw-bold"><?php echo number_format($product['orders'] ?? 0); ?></td>
                                            <td class="text-center"><?php echo number_format($product['units_sold'] ?? 0); ?></td>
                                            <td class="text-center text-success fw-bold">
                                                ₦<?php echo number_format($product['revenue'] ?? 0, 2); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $stock_class; ?>">
                                                    <?php echo $stock_text; ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo number_format($product['stock_quantity']); ?> <?php echo $product['unit']; ?> left</small>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-<?php 
                                                            echo $performance_percent >= 70 ? 'success' : 
                                                                ($performance_percent >= 40 ? 'warning' : 'danger'); 
                                                        ?>" style="width: <?php echo $performance_percent; ?>%">
                                                        </div>
                                                    </div>
                                                    <small class="ms-2"><?php echo round($performance_percent); ?>%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-box fs-1 text-muted d-block mb-3"></i>
                            <p class="text-muted">No sales data available yet</p>
                            <a href="../products/add-product.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> Add Your First Product
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews and Tips Row -->
            <div class="row g-4">
                <!-- Customer Reviews -->
                <div class="col-12 col-lg-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-chat-quote me-2 text-primary"></i>
                                Customer Reviews
                            </h5>
                            <span class="badge bg-primary"><?php echo count($recent_reviews); ?> Reviews</span>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_reviews): ?>
                                <div class="reviews-list">
                                    <?php foreach ($recent_reviews as $review): ?>
                                        <div class="review-item mb-4 pb-3 border-bottom">
                                            <div class="d-flex">
                                                <!-- Customer Avatar -->
                                                <div class="flex-shrink-0 me-3">
                                                    <?php if ($review['customer_avatar']): ?>
                                                        <img src="<?php echo htmlspecialchars($review['customer_avatar']); ?>" 
                                                             class="rounded-circle" 
                                                             style="width: 48px; height: 48px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                                             style="width: 48px; height: 48px;">
                                                            <i class="bi bi-person text-muted fs-4"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Review Content -->
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                                            <span class="text-muted mx-2">•</span>
                                                            <small class="text-muted">
                                                                <?php echo timeAgo($review['created_at']); ?>
                                                            </small>
                                                        </div>
                                                        <div class="text-warning">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($review['review']): ?>
                                                        <p class="mt-2 mb-2"><?php echo htmlspecialchars($review['review']); ?></p>
                                                    <?php else: ?>
                                                        <p class="mt-2 mb-2 text-muted fst-italic">No written review</p>
                                                    <?php endif; ?>
                                                    
                                                    <small class="text-muted">
                                                        <i class="bi bi-box me-1"></i>
                                                        Order #<?php echo $review['order_id']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-chat-quote display-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">No reviews yet</h5>
                                    <p class="text-muted">Customer reviews will appear here once you start making sales</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Performance Tips & Alerts -->
                <div class="col-12 col-lg-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightbulb me-2 text-warning"></i>
                                Insights & Alerts
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Low Stock Alerts -->
                            <?php if (count($low_stock_products) > 0): ?>
                                <div class="alert alert-warning mb-4">
                                    <h6 class="alert-heading">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Low Stock Alert (<?php echo count($low_stock_products); ?>)
                                    </h6>
                                    <ul class="mb-0 small">
                                        <?php foreach (array_slice($low_stock_products, 0, 3) as $product): ?>
                                            <li>
                                                <?php echo htmlspecialchars($product['name']); ?> - 
                                                <?php echo number_format($product['stock_quantity']); ?> units left
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($low_stock_products) > 3): ?>
                                            <li class="text-muted">And <?php echo count($low_stock_products) - 3; ?> more...</li>
                                        <?php endif; ?>
                                    </ul>
                                    <a href="../inventory/stock-management.php" class="btn btn-sm btn-warning mt-2">
                                        <i class="bi bi-boxes me-1"></i> Manage Stock
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Out of Stock Alert -->
                            <?php if ($out_of_stock_count > 0): ?>
                                <div class="alert alert-danger mb-4">
                                    <h6 class="alert-heading">
                                        <i class="bi bi-x-circle me-2"></i>
                                        Out of Stock (<?php echo $out_of_stock_count; ?>)
                                    </h6>
                                    <p class="small mb-0">Products are out of stock and not visible to customers.</p>
                                    <a href="../inventory/stock-management.php?filter=out_of_stock" class="btn btn-sm btn-danger mt-2">
                                        <i class="bi bi-arrow-repeat me-1"></i> Restock Now
                                    </a>
                                </div>
                            <?php endif; ?>

                            <!-- Performance Tips -->
                            <div class="tips-list">
                                <h6 class="text-muted mb-3">Performance Tips</h6>
                                <?php foreach ($tips as $tip): ?>
                                    <div class="tip-item mb-3 p-3 bg-<?php echo $tip['color']; ?> bg-opacity-10 rounded">
                                        <h6 class="text-<?php echo $tip['color']; ?> mb-2">
                                            <i class="bi bi-<?php echo $tip['icon']; ?> me-2"></i>
                                            <?php echo $tip['title']; ?>
                                        </h6>
                                        <p class="small text-muted mb-0"><?php echo $tip['message']; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Quick Stats Summary -->
                            <div class="mt-4">
                                <h6 class="text-muted mb-3">Quick Stats</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center">
                                            <small class="text-muted d-block">Total Products</small>
                                            <span class="fw-bold"><?php echo number_format($metrics['total_products'] ?? 0); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center">
                                            <small class="text-muted d-block">Active Products</small>
                                            <span class="fw-bold"><?php echo number_format($metrics['active_products'] ?? 0); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center">
                                            <small class="text-muted d-block">Pending Approval</small>
                                            <span class="fw-bold"><?php echo number_format($metrics['pending_products'] ?? 0); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center">
                                            <small class="text-muted d-block">Total Orders</small>
                                            <span class="fw-bold"><?php echo number_format($metrics['total_orders'] ?? 0); ?></span>
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

<!-- Helper function for time ago -->
<?php
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}
?>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh button
    document.getElementById('refreshMetrics')?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
        btn.disabled = true;
        setTimeout(() => window.location.reload(), 500);
    });
});

function exportAsPDF() {
    window.location.href = 'export-performance.php?format=pdf';
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
    
    .review-item:hover {
        background-color: rgba(0,0,0,0.02);
    }
    
    .tip-item {
        transition: transform 0.2s;
    }
    
    .tip-item:hover {
        transform: translateX(5px);
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>