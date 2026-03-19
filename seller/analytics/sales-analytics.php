<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$time_filter = $_GET['period'] ?? 'month';

// Calculate date ranges
$date_ranges = [
    'week' => date('Y-m-d', strtotime('-1 week')),
    'month' => date('Y-m-d', strtotime('-1 month')),
    'quarter' => date('Y-m-d', strtotime('-3 months')),
    'year' => date('Y-m-d', strtotime('-1 year'))
];
$start_date = $date_ranges[$time_filter] ?? $date_ranges['month'];

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Sales data for chart
$sales_data = $db->fetchAll("
    SELECT 
        DATE(o.created_at) as date, 
        SUM(oi.item_total) as daily_sales,
        COUNT(DISTINCT oi.order_id) as order_count,
        COUNT(oi.id) as items_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? 
        AND oi.status = 'delivered'
        AND DATE(o.created_at) >= ?
    GROUP BY DATE(o.created_at)
    ORDER BY date
", [$seller_id, $start_date]);

// Get previous period data for comparison
$prev_start_date = date('Y-m-d', strtotime($start_date . ' -' . ($time_filter == 'week' ? '1 week' : 
    ($time_filter == 'month' ? '1 month' : 
    ($time_filter == 'quarter' ? '3 months' : '1 year')))));

    $prev_sales_data = $db->fetchAll("
        SELECT 
            SUM(oi.item_total) as total_revenue,
            COUNT(DISTINCT oi.order_id) as total_orders
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.seller_id = ? 
            AND oi.status = 'delivered'
            AND DATE(o.created_at) >= ?
            AND DATE(o.created_at) < ?
    ", [$seller_id, $prev_start_date, $start_date]);

// Calculate totals
$total_revenue = array_sum(array_column($sales_data, 'daily_sales'));
$total_orders = array_sum(array_column($sales_data, 'order_count'));
$total_items = array_sum(array_column($sales_data, 'items_sold'));
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
$avg_daily_sales = count($sales_data) > 0 ? $total_revenue / count($sales_data) : 0;

// Calculate growth percentages
$prev_revenue = $prev_sales_data[0]['total_revenue'] ?? 0;
$prev_orders = $prev_sales_data[0]['total_orders'] ?? 0;
$revenue_growth = $prev_revenue > 0 ? (($total_revenue - $prev_revenue) / $prev_revenue) * 100 : 0;
$orders_growth = $prev_orders > 0 ? (($total_orders - $prev_orders) / $prev_orders) * 100 : 0;

// Top products
$top_products = $db->fetchAll("
    SELECT 
        p.id,
        p.name,
        p.unit,
        (SELECT image_path 
         FROM product_images 
         WHERE product_id = p.id 
         AND is_primary = 1 
         LIMIT 1) as primary_image,
        SUM(oi.quantity) as units_sold,
        COUNT(DISTINCT oi.order_id) as order_count,
        SUM(oi.item_total) as revenue,
        AVG(oi.unit_price) as avg_price
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE oi.seller_id = ? 
        AND oi.status = 'delivered'
        AND DATE(o.created_at) >= ?
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 10
", [$seller_id, $start_date]);

// Get sales by product category
$category_sales = $db->fetchAll("
    SELECT 
        c.name as category_name,
        COUNT(oi.id) as sales_count,
        SUM(oi.item_total) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE oi.seller_id = ? 
        AND oi.status = 'delivered'
        AND DATE(o.created_at) >= ?
    GROUP BY c.id
    ORDER BY revenue DESC
", [$seller_id, $start_date]);

// Get daily average for trend
$max_daily_sales = !empty($sales_data) ? max(array_column($sales_data, 'daily_sales')) : 1;

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND DATE(o.created_at) = CURDATE()", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Sales Analytics";
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
                        <h1 class="h5 mb-0">Sales Analytics</h1>
                        <small class="text-muted">Track your sales performance</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Sales Analytics</h1>
                    <p class="text-muted mb-0">Detailed analysis of your sales performance</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="performance.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-bar-chart me-1"></i> Performance Metrics
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exportAnalytics">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                    <a href="reports.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-file-text me-1"></i> Generate Report
                    </a>
                </div>
            </div>

            <!-- Period Filter Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label class="text-muted">
                                <i class="bi bi-calendar-range me-1"></i> Time Period:
                            </label>
                        </div>
                        <div class="col-auto">
                            <select class="form-select form-select-sm" id="period" name="period" onchange="this.form.submit()">
                                <option value="week" <?php echo $time_filter == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="month" <?php echo $time_filter == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="quarter" <?php echo $time_filter == 'quarter' ? 'selected' : ''; ?>>Last 3 Months</option>
                                <option value="year" <?php echo $time_filter == 'year' ? 'selected' : ''; ?>>Last Year</option>
                            </select>
                        </div>
                        <div class="col-auto ms-auto">
                            <span class="text-muted small">
                                <?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j, Y'); ?>
                            </span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="row g-3 mb-4">
                <!-- Total Revenue -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card  shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Revenue</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($total_revenue, 2); ?></h3>
                                    <small class="<?php echo $revenue_growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi bi-arrow-<?php echo $revenue_growth >= 0 ? 'up' : 'down'; ?>-short"></i>
                                        <?php echo number_format(abs($revenue_growth), 1); ?>% vs previous period
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-currency-dollar fs-4 text-primary"></i>
                                </div>
                            </div>
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
                                    <h3 class="card-title mb-0"><?php echo number_format($total_orders); ?></h3>
                                    <small class="<?php echo $orders_growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi bi-arrow-<?php echo $orders_growth >= 0 ? 'up' : 'down'; ?>-short"></i>
                                        <?php echo number_format(abs($orders_growth), 1); ?>% vs previous period
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cart-check fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Average Order Value -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Avg Order Value</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($avg_order_value, 2); ?></h3>
                                    <small class="text-info">
                                        <i class="bi bi-cart me-1"></i>
                                        <?php echo number_format($total_items); ?> items sold
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-graph-up fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Average -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Daily Average</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($avg_daily_sales, 2); ?></h3>
                                    <small class="text-warning">
                                        <i class="bi bi-calendar-day me-1"></i>
                                        <?php echo count($sales_data); ?> days
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-calendar-check fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- Sales Trend Chart -->
                <div class="col-12 col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-graph-up me-2 text-primary"></i>
                                Sales Trend
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($sales_data)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-bar-chart display-1 text-muted"></i>
                                    <p class="text-muted mt-3">No sales data available for this period</p>
                                </div>
                            <?php else: ?>
                                <div class="chart-container" style="height: 300px; position: relative;">
                                    <!-- Chart bars -->
                                    <div style="display: flex; align-items: flex-end; height: 250px; gap: 8px; margin-bottom: 20px;">
                                        <?php foreach ($sales_data as $data): ?>
                                            <?php 
                                            $bar_height = ($data['daily_sales'] / $max_daily_sales) * 200;
                                            $bar_height = max(30, $bar_height); // Minimum height for visibility
                                            ?>
                                            <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                                                <div class="position-relative w-100" style="height: 200px;">
                                                    <div class="bg-gradient-<?php echo $data['daily_sales'] == $max_daily_sales ? 'success' : 'primary'; ?> rounded-top" 
                                                         style="position: absolute; bottom: 0; width: 100%; height: <?php echo $bar_height; ?>px; 
                                                                transition: height 0.3s; opacity: <?php echo $data['daily_sales'] / $max_daily_sales; ?>;">
                                                    </div>
                                                </div>
                                                <div class="mt-2 text-center">
                                                    <small class="text-muted d-block"><?php echo date('M j', strtotime($data['date'])); ?></small>
                                                    <small class="fw-bold text-primary">₦<?php echo number_format($data['daily_sales']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Summary stats -->
                                    <div class="row mt-4 pt-3 border-top text-center">
                                        <div class="col-4">
                                            <small class="text-muted d-block">Best Day</small>
                                            <span class="fw-bold text-success">
                                                ₦<?php echo number_format($max_daily_sales, 2); ?>
                                            </span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Average</small>
                                            <span class="fw-bold text-info">
                                                ₦<?php echo number_format($avg_daily_sales, 2); ?>
                                            </span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted d-block">Total</small>
                                            <span class="fw-bold text-primary">
                                                ₦<?php echo number_format($total_revenue, 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Category Breakdown -->
                <div class="col-12 col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-pie-chart me-2 text-primary"></i>
                                Sales by Category
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($category_sales)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-pie-chart display-1 text-muted"></i>
                                    <p class="text-muted mt-3">No category data available</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                $total_cat_revenue = array_sum(array_column($category_sales, 'revenue'));
                                $colors = ['primary', 'success', 'info', 'warning', 'danger'];
                                ?>
                                <?php foreach ($category_sales as $index => $category): 
                                    $percentage = ($category['revenue'] / $total_cat_revenue) * 100;
                                    $color = $colors[$index % count($colors)];
                                ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="fw-bold"><?php echo htmlspecialchars($category['category_name']); ?></small>
                                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $color; ?>" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <small class="text-muted"><?php echo $category['sales_count']; ?> sales</small>
                                            <small class="fw-bold text-<?php echo $color; ?>">
                                                ₦<?php echo number_format($category['revenue'], 2); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-trophy me-2 text-warning"></i>
                        Top Selling Products
                    </h5>
                    <span class="badge bg-primary"><?php echo count($top_products); ?> Products</span>
                </div>
                <div class="card-body">
                    <?php if (empty($top_products)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-box fs-1 text-muted d-block mb-3"></i>
                            <p class="text-muted">No sales data available for this period</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Orders</th>
                                        <th class="text-center">Units Sold</th>
                                        <th class="text-center">Avg Price</th>
                                        <th class="text-center">Revenue</th>
                                        <th class="text-center">Share</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_top_revenue = array_sum(array_column($top_products, 'revenue'));
                                    foreach ($top_products as $index => $product): 
                                        $share_percentage = ($product['revenue'] / $total_top_revenue) * 100;
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($product['primary_image']): ?>
                                                        <img src="<?php echo BASE_URL . '/uploads/products/' . $product['primary_image']; ?>" 
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
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center"><?php echo number_format($product['order_count']); ?></td>
                                            <td class="text-center">
                                                <?php echo number_format($product['units_sold']); ?> 
                                                <small class="text-muted"><?php echo $product['unit']; ?></small>
                                            </td>
                                            <td class="text-center">₦<?php echo number_format($product['avg_price'], 2); ?></td>
                                            <td class="text-center text-success fw-bold">
                                                ₦<?php echo number_format($product['revenue'], 2); ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-success" 
                                                             style="width: <?php echo $share_percentage; ?>%"></div>
                                                    </div>
                                                    <small class="ms-2"><?php echo number_format($share_percentage, 1); ?>%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Export options -->
                        <div class="mt-3 d-flex justify-content-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportTopProducts()">
                                <i class="bi bi-download me-1"></i> Export Top Products
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Additional Insights Row -->
            <div class="row g-4">
                <!-- Performance Insights -->
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightbulb me-2 text-warning"></i>
                                Performance Insights
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php if ($total_orders > 0): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="bi bi-check-circle-fill text-success me-3"></i>
                                        <div>
                                            <strong><?php echo number_format($total_orders); ?></strong> total orders processed
                                        </div>
                                    </li>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="bi bi-cart-fill text-info me-3"></i>
                                        <div>
                                            Average of <strong><?php echo number_format($total_orders / count($sales_data), 1); ?></strong> orders per day
                                        </div>
                                    </li>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="bi bi-currency-exchange text-success me-3"></i>
                                        <div>
                                            <strong>₦<?php echo number_format($avg_order_value, 2); ?></strong> average order value
                                        </div>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($top_products)): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="bi bi-trophy-fill text-warning me-3"></i>
                                        <div>
                                            Top product: <strong><?php echo htmlspecialchars($top_products[0]['name']); ?></strong>
                                            (₦<?php echo number_format($top_products[0]['revenue'], 2); ?>)
                                        </div>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Recommendations -->
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-graph-up-arrow me-2 text-success"></i>
                                Growth Recommendations
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $recommendations = [];
                            
                            if (empty($top_products)) {
                                $recommendations[] = "Start by adding products and promoting your store to generate sales.";
                            } else {
                                if (count($top_products) < 5) {
                                    $recommendations[] = "Add more products to increase your catalog size and attract more customers.";
                                }
                                
                                if ($avg_order_value < 5000) {
                                    $recommendations[] = "Consider bundling products or offering bulk discounts to increase average order value.";
                                }
                                
                                if ($share_percentage > 50 && count($top_products) > 1) {
                                    $recommendations[] = "Your top product dominates sales. Consider promoting other products to diversify revenue.";
                                }
                                
                                if (empty($recommendations)) {
                                    $recommendations[] = "Great job! Keep monitoring your analytics and maintaining product quality.";
                                    $recommendations[] = "Consider running promotions to attract new customers and increase sales.";
                                }
                            }
                            ?>
                            
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recommendations as $rec): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="bi bi-arrow-right-circle-fill text-primary me-3"></i>
                                        <div><?php echo $rec; ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Export button
    document.getElementById('exportAnalytics')?.addEventListener('click', function() {
        exportAnalytics();
    });
});

function exportAnalytics() {
    const period = document.getElementById('period').value;
    window.location.href = 'export-analytics.php?period=' + period + '&format=csv';
}

function exportTopProducts() {
    const period = document.getElementById('period').value;
    window.location.href = 'export-products.php?period=' + period + '&format=csv';
}

// Add CSS for gradients
const style = document.createElement('style');
style.textContent = `
    .bg-gradient-primary {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    }
    .bg-gradient-success {
        background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
    }
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    .chart-bar {
        cursor: pointer;
        transition: opacity 0.3s;
    }
    .chart-bar:hover {
        opacity: 0.8;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>