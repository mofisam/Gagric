<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';

$db = new Database();

$seller_id = $_SESSION['user_id'];
$time_filter = $_GET['period'] ?? 'month';

// Calculate date range
$date_ranges = [
    'week' => date('Y-m-d', strtotime('-1 week')),
    'month' => date('Y-m-d', strtotime('-1 month')),
    'quarter' => date('Y-m-d', strtotime('-3 months')),
    'year' => date('Y-m-d', strtotime('-1 year'))
];
$start_date = $date_ranges[$time_filter] ?? $date_ranges['month'];

// Sales data for chart
$sales_data = $db->fetchAll("
    SELECT DATE(oi.created_at) as date, 
           SUM(oi.item_total) as daily_sales,
           COUNT(*) as order_count
    FROM order_items oi
    WHERE oi.seller_id = ? 
    AND oi.status = 'delivered'
    AND oi.created_at >= ?
    GROUP BY DATE(oi.created_at)
    ORDER BY date
", [$seller_id, $start_date]);

// Top products
$top_products = $db->fetchAll("
    SELECT p.name, SUM(oi.quantity) as units_sold, SUM(oi.item_total) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.seller_id = ? 
    AND oi.status = 'delivered'
    AND oi.created_at >= ?
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 10
", [$seller_id, $start_date]);

$page_title = "Sales Analytics";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Sales Analytics</h1>
                <a href="performance.php" class="btn btn-outline-primary">Performance Metrics</a>
            </div>

            <!-- Period Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label for="period" class="form-label">View analytics for:</label>
                        </div>
                        <div class="col-auto">
                            <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                                <option value="week" <?php echo $time_filter == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="month" <?php echo $time_filter == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="quarter" <?php echo $time_filter == 'quarter' ? 'selected' : ''; ?>>Last 3 Months</option>
                                <option value="year" <?php echo $time_filter == 'year' ? 'selected' : ''; ?>>Last Year</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $total_revenue = array_sum(array_column($sales_data, 'daily_sales'));
                                        echo formatCurrency($total_revenue);
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Orders</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $total_orders = array_sum(array_column($sales_data, 'order_count'));
                                        echo $total_orders;
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-cart-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Average Order Value</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
                                        echo formatCurrency($avg_order_value);
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-graph-up fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Top Product</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php
                                        $top_product = $top_products[0]['name'] ?? 'N/A';
                                        echo strlen($top_product) > 15 ? substr($top_product, 0, 15) . '...' : $top_product;
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-trophy fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Sales Chart -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Sales Trend</h5>
                        </div>
                        <div class="card-body">
                            <div id="salesChart" style="height: 300px;">
                                <!-- Simple bar chart using CSS -->
                                <div class="chart-container" style="height: 100%; display: flex; align-items: end; gap: 10px; padding: 20px 0;">
                                    <?php foreach ($sales_data as $data): ?>
                                        <div class="chart-bar" style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                                            <div class="chart-value bg-primary rounded" 
                                                 style="width: 30px; height: <?php echo min(($data['daily_sales'] / max(array_column($sales_data, 'daily_sales'))) * 200, 200); ?>px;">
                                            </div>
                                            <div class="chart-label mt-2 small text-muted text-center">
                                                <?php echo date('M j', strtotime($data['date'])); ?><br>
                                                <?php echo formatCurrency($data['daily_sales']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Top Products -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Top Products</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($top_products): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($top_products as $index => $product): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                                <?php echo $product['name']; ?>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold"><?php echo formatCurrency($product['revenue']); ?></div>
                                                <small class="text-muted"><?php echo $product['units_sold']; ?> sold</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No sales data available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="text-muted small">Conversion Rate</div>
                                    <div class="h5 text-primary">
                                        <?php
                                        // This would require product view data in a real application
                                        echo 'N/A';
                                        ?>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="text-muted small">Repeat Customers</div>
                                    <div class="h5 text-success">
                                        <?php
                                        $repeat_customers = $db->fetchOne("
                                            SELECT COUNT(DISTINCT o.buyer_id) as count
                                            FROM order_items oi
                                            JOIN orders o ON oi.order_id = o.id
                                            WHERE oi.seller_id = ? 
                                            AND oi.status = 'delivered'
                                            GROUP BY o.buyer_id
                                            HAVING COUNT(*) > 1
                                        ", [$seller_id]);
                                        echo $repeat_customers ? $repeat_customers['count'] : 0;
                                        ?>
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

<?php include '../../includes/footer.php'; ?>