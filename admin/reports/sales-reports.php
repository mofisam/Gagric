<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';

requireAdmin();

$db = new Database();

// Date range defaults
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'daily';
$chart_type = $_GET['chart_type'] ?? 'line'; // line, bar, area

// Generate sales report
$sales_data = [];
$report_title = '';
$period_label = '';

if ($report_type === 'daily') {
    $report_title = 'Daily Sales Report';
    $period_label = 'Date';
    $sales_data = $db->fetchAll("
        SELECT DATE(created_at) as date, 
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE created_at BETWEEN ? AND ? 
          AND payment_status = 'paid'
        GROUP BY DATE(created_at) 
        ORDER BY date DESC
    ", [$date_from, $date_to]);
} elseif ($report_type === 'weekly') {
    $report_title = 'Weekly Sales Report';
    $period_label = 'Week';
    $sales_data = $db->fetchAll("
        SELECT YEAR(created_at) as year, 
               WEEK(created_at) as week,
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               AVG(total_amount) as avg_order_value,
               MIN(DATE(created_at)) as week_start,
               MAX(DATE(created_at)) as week_end
        FROM orders 
        WHERE created_at BETWEEN ? AND ? 
          AND payment_status = 'paid'
        GROUP BY YEAR(created_at), WEEK(created_at) 
        ORDER BY year ASC, week ASC
    ", [$date_from, $date_to]);
} elseif ($report_type === 'monthly') {
    $report_title = 'Monthly Sales Report';
    $period_label = 'Month';
    $sales_data = $db->fetchAll("
        SELECT YEAR(created_at) as year, 
               MONTH(created_at) as month,
               COUNT(*) as order_count,
               SUM(total_amount) as total_sales,
               AVG(total_amount) as avg_order_value
        FROM orders 
        WHERE created_at BETWEEN ? AND ? 
          AND payment_status = 'paid'
        GROUP BY YEAR(created_at), MONTH(created_at) 
        ORDER BY year ASC, month ASC
    ", [$date_from, $date_to]);
}

// Calculate totals
$total_orders = 0;
$total_sales = 0;
$avg_order_value = 0;
foreach ($sales_data as $data) {
    $total_orders += $data['order_count'];
    $total_sales += $data['total_sales'];
}
$avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;

// Get additional stats
$stats = [
    'total_orders' => $total_orders,
    'total_sales' => $total_sales,
    'avg_order_value' => $avg_order_value,
    'data_points' => count($sales_data),
    'best_period_sales' => !empty($sales_data) ? max(array_column($sales_data, 'total_sales')) : 0,
    'worst_period_sales' => !empty($sales_data) ? min(array_column($sales_data, 'total_sales')) : 0
];

// Prepare chart data
$chart_labels = [];
$chart_sales_data = [];
$chart_orders_data = [];
$chart_avg_data = [];

foreach ($sales_data as $data) {
    if ($report_type === 'daily') {
        $chart_labels[] = date('M j', strtotime($data['date']));
    } elseif ($report_type === 'weekly') {
        $chart_labels[] = 'W' . $data['week'];
    } elseif ($report_type === 'monthly') {
        $chart_labels[] = date('M', mktime(0, 0, 0, $data['month'], 1));
    }
    
    $chart_sales_data[] = $data['total_sales'];
    $chart_orders_data[] = $data['order_count'];
    $chart_avg_data[] = $data['avg_order_value'];
}

$page_title = "Sales Reports";
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
                        <h1 class="h5 mb-0 text-center">Sales Reports</h1>
                        <small class="text-muted d-block text-center">₦<?php echo number_format($total_sales, 2); ?> total</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshReport">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Sales Reports</h1>
                    <p class="text-muted mb-0">Analyze sales performance and trends</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportToExcel()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshReport">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="printReport('table')">
                                <i class="bi bi-table me-2"></i> Table Only
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="printReport('charts')">
                                <i class="bi bi-bar-chart me-2"></i> Charts Only
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="printReport('all')">
                                <i class="bi bi-file-earmark-text me-2"></i> Full Report
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Orders</small>
                                <h6 class="mb-0"><?php echo number_format($stats['total_orders']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Sales</small>
                                <h6 class="mb-0 text-success">₦<?php echo number_format($total_sales, 2); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Avg Order</small>
                                <h6 class="mb-0 text-info">₦<?php echo number_format($avg_order_value, 2); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Periods</small>
                                <h6 class="mb-0"><?php echo number_format($stats['data_points']); ?></h6>
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
                                        <?php echo $stats['data_points']; ?> periods
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-bag fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Sales -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Sales</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['total_sales'], 2); ?></h3>
                                    <small class="text-success">
                                        <?php echo $total_orders > 0 ? number_format($avg_order_value, 2) : '0.00'; ?> average
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-currency-exchange fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Average Order -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Average Order</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['avg_order_value'], 2); ?></h3>
                                    <small class="text-muted">
                                        Per order
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-graph-up fs-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Best Period -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Best Period</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($stats['best_period_sales'], 2); ?></h3>
                                    <small class="text-warning">
                                        Highest sales
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-trophy fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Report Parameters
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
                <div class="collapse d-md-block" id="filterCollapse">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-6 col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($date_from); ?>" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" 
                                       value="<?php echo htmlspecialchars($date_to); ?>" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Report Type</label>
                                <select name="report_type" class="form-select form-select-sm" required onchange="this.form.submit()">
                                    <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Chart Type</label>
                                <select name="chart_type" class="form-select form-select-sm" required onchange="changeChartType(this.value)">
                                    <option value="line" <?php echo $chart_type === 'line' ? 'selected' : ''; ?>>Line Chart</option>
                                    <option value="bar" <?php echo $chart_type === 'bar' ? 'selected' : ''; ?>>Bar Chart</option>
                                    <option value="area" <?php echo $chart_type === 'area' ? 'selected' : ''; ?>>Area Chart</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-filter me-1"></i> Generate
                                    </button>
                                    <a href="sales-reports.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-1"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <?php if (!empty($sales_data)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-graph-up me-2 text-primary"></i>
                                        Sales Trend Visualization
                                    </h5>
                                    <div class="btn-group btn-group-sm d-none d-md-block">
                                        <button class="btn btn-outline-primary active" onclick="showChart('sales')">
                                            <i class="bi bi-currency-exchange"></i> Sales
                                        </button>
                                        <button class="btn btn-outline-success" onclick="showChart('orders')">
                                            <i class="bi bi-bag"></i> Orders
                                        </button>
                                        <button class="btn btn-outline-info" onclick="showChart('avg')">
                                            <i class="bi bi-graph-up"></i> Avg Value
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Sales Chart -->
                                <div class="chart-container" id="salesChartContainer">
                                    <canvas id="salesChart" height="250"></canvas>
                                </div>
                                
                                <!-- Orders Chart (Hidden by default) -->
                                <div class="chart-container d-none" id="ordersChartContainer">
                                    <canvas id="ordersChart" height="250"></canvas>
                                </div>
                                
                                <!-- Average Value Chart (Hidden by default) -->
                                <div class="chart-container d-none" id="avgChartContainer">
                                    <canvas id="avgChart" height="250"></canvas>
                                </div>
                                
                                <!-- Mobile Chart Toggles -->
                                <div class="d-md-none mt-3">
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-outline-primary active" onclick="showChart('sales')">
                                            Sales
                                        </button>
                                        <button class="btn btn-outline-success" onclick="showChart('orders')">
                                            Orders
                                        </button>
                                        <button class="btn btn-outline-info" onclick="showChart('avg')">
                                            Avg Value
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Report Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block"><?php echo $report_title; ?></h5>
                    <small class="text-muted">
                        <?php echo date('M j, Y', strtotime($date_from)); ?> to <?php echo date('M j, Y', strtotime($date_to)); ?>
                        • <?php echo $stats['data_points']; ?> periods
                    </small>
                </div>
                <div class="text-muted d-none d-md-block">
                    <button class="btn btn-sm btn-outline-success" onclick="exportToExcel()">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>

            <!-- Sales Report Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($sales_data)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No sales data found</h4>
                            <p class="text-muted mb-4">Try adjusting the date range or report type.</p>
                            <a href="sales-reports.php?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?>&report_type=daily" 
                               class="btn btn-primary">
                                <i class="bi bi-calendar-month me-1"></i> This Month
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="120"><?php echo $period_label; ?></th>
                                        <th width="80">Orders</th>
                                        <th width="100">Sales</th>
                                        <th width="100">Avg Order</th>
                                        <th width="80">Share</th>
                                        <th width="80" class="text-center">Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $previous_sales = null;
                                    foreach ($sales_data as $index => $data): 
                                        $percentage = $total_sales > 0 ? ($data['total_sales'] / $total_sales) * 100 : 0;
                                        $is_best_period = $data['total_sales'] == $stats['best_period_sales'];
                                        
                                        // Calculate trend
                                        $trend = '';
                                        $trend_class = '';
                                        if ($previous_sales !== null) {
                                            if ($data['total_sales'] > $previous_sales) {
                                                $trend = 'up';
                                                $trend_class = 'text-success';
                                            } elseif ($data['total_sales'] < $previous_sales) {
                                                $trend = 'down';
                                                $trend_class = 'text-danger';
                                            } else {
                                                $trend = 'flat';
                                                $trend_class = 'text-muted';
                                            }
                                        }
                                        $previous_sales = $data['total_sales'];
                                    ?>
                                        
                                        <tr class="sales-row <?php echo $is_best_period ? 'table-warning' : ''; ?>" 
                                            data-sales-amount="<?php echo $data['total_sales']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-<?php echo $is_best_period ? 'warning' : 'primary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                        <?php if ($report_type === 'daily'): ?>
                                                            <i class="bi bi-calendar-date text-<?php echo $is_best_period ? 'warning' : 'primary'; ?>"></i>
                                                        <?php elseif ($report_type === 'weekly'): ?>
                                                            <i class="bi bi-calendar-week text-<?php echo $is_best_period ? 'warning' : 'primary'; ?>"></i>
                                                        <?php elseif ($report_type === 'monthly'): ?>
                                                            <i class="bi bi-calendar-month text-<?php echo $is_best_period ? 'warning' : 'primary'; ?>"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($report_type === 'daily'): ?>
                                                            <strong><?php echo date('M j, Y', strtotime($data['date'])); ?></strong>
                                                        <?php elseif ($report_type === 'weekly'): ?>
                                                            <strong>Week <?php echo $data['week']; ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo date('M j', strtotime($data['week_start'])); ?> - 
                                                                <?php echo date('M j', strtotime($data['week_end'])); ?>
                                                            </small>
                                                        <?php elseif ($report_type === 'monthly'): ?>
                                                            <strong><?php echo date('F Y', mktime(0, 0, 0, $data['month'], 1, $data['year'])); ?></strong>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong><?php echo $data['order_count']; ?></strong>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong class="text-success">₦<?php echo number_format($data['total_sales'], 2); ?></strong>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="text-info">₦<?php echo number_format($data['avg_order_value'], 2); ?></span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                        <div class="progress-bar bg-<?php echo $is_best_period ? 'warning' : 'primary'; ?>" 
                                                             style="width: <?php echo min($percentage, 100); ?>%"></div>
                                                    </div>
                                                    <small><?php echo number_format($percentage, 1); ?>%</small>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <?php if ($trend): ?>
                                                    <i class="bi bi-arrow-<?php echo $trend; ?> <?php echo $trend_class; ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Period & Sales -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $is_best_period ? 'warning' : 'primary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <?php if ($report_type === 'daily'): ?>
                                                                    <i class="bi bi-calendar-date text-<?php echo $is_best_period ? 'warning' : 'primary'; ?>"></i>
                                                                <?php elseif ($report_type === 'weekly'): ?>
                                                                    <i class="bi bi-calendar-week text-<?php echo $is_best_period ? 'warning' : 'primary'; ?>"></i>
                                                                <?php elseif ($report_type === 'monthly'): ?>
                                                                    <i class="bi bi-calendar-month text-<?php echo $is_best_period ? 'warning' : 'primary'; ?>"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <strong>
                                                                    <?php if ($report_type === 'daily'): ?>
                                                                        <?php echo date('M j', strtotime($data['date'])); ?>
                                                                    <?php elseif ($report_type === 'weekly'): ?>
                                                                        W<?php echo $data['week']; ?>
                                                                    <?php elseif ($report_type === 'monthly'): ?>
                                                                        <?php echo date('M', mktime(0, 0, 0, $data['month'], 1)); ?>
                                                                    <?php endif; ?>
                                                                </strong>
                                                                <?php if ($is_best_period): ?>
                                                                    <span class="badge bg-warning ms-2">
                                                                        <i class="bi bi-trophy"></i>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if ($trend): ?>
                                                                    <i class="bi bi-arrow-<?php echo $trend; ?> <?php echo $trend_class; ?> ms-1"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <strong class="text-success">₦<?php echo number_format($data['total_sales'], 2); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo $data['order_count']; ?> orders</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 2: Progress bar -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small class="text-muted">Share</small>
                                                            <small><?php echo number_format($percentage, 1); ?>%</small>
                                                        </div>
                                                        <div class="progress" style="height: 4px;">
                                                            <div class="progress-bar bg-<?php echo $is_best_period ? 'warning' : 'primary'; ?>" 
                                                                 style="width: <?php echo min($percentage, 100); ?>%"></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Details -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            Avg: ₦<?php echo number_format($data['avg_order_value'], 2); ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <?php if ($report_type === 'weekly'): ?>
                                                                <?php echo date('M j', strtotime($data['week_start'])); ?>-<?php echo date('j', strtotime($data['week_end'])); ?>
                                                            <?php elseif ($report_type === 'monthly'): ?>
                                                                <?php echo $data['year']; ?>
                                                            <?php else: ?>
                                                                <?php echo date('Y', strtotime($data['date'])); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="d-none d-md-table-footer-group">
                                    <tr class="table-primary">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo number_format($stats['total_orders']); ?></strong></td>
                                        <td><strong class="text-success">₦<?php echo number_format($stats['total_sales'], 2); ?></strong></td>
                                        <td><strong class="text-info">₦<?php echo number_format($stats['avg_order_value'], 2); ?></strong></td>
                                        <td><strong>100%</strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Data Insights -->
            <?php if (!empty($sales_data) && count($sales_data) >= 2): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0">
                                <h6 class="mb-0">
                                    <i class="bi bi-lightbulb me-2 text-warning"></i>
                                    Insights
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <?php 
                                        // Find best and worst periods
                                        $best_period = null;
                                        $worst_period = null;
                                        $max_sales = 0;
                                        $min_sales = PHP_INT_MAX;
                                        
                                        foreach ($sales_data as $data) {
                                            if ($data['total_sales'] > $max_sales) {
                                                $max_sales = $data['total_sales'];
                                                $best_period = $data;
                                            }
                                            if ($data['total_sales'] < $min_sales) {
                                                $min_sales = $data['total_sales'];
                                                $worst_period = $data;
                                            }
                                        }
                                        
                                        // Calculate growth
                                        $first_period = end($sales_data);
                                        $last_period = reset($sales_data);
                                        $growth = $first_period['total_sales'] > 0 ? 
                                                 (($last_period['total_sales'] - $first_period['total_sales']) / $first_period['total_sales']) * 100 : 0;
                                        ?>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Growth</small>
                                            <h5 class="mb-0 <?php echo $growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <i class="bi bi-arrow-<?php echo $growth >= 0 ? 'up' : 'down'; ?>"></i>
                                                <?php echo number_format(abs($growth), 1); ?>%
                                            </h5>
                                            <small>
                                                <?php if ($report_type === 'daily'): ?>
                                                    From <?php echo date('M j', strtotime($first_period['date'])); ?> to <?php echo date('M j', strtotime($last_period['date'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Best Period</small>
                                            <h6 class="mb-0">
                                                <?php if ($report_type === 'daily'): ?>
                                                    <?php echo date('M j, Y', strtotime($best_period['date'])); ?>
                                                <?php elseif ($report_type === 'weekly'): ?>
                                                    Week <?php echo $best_period['week']; ?>
                                                <?php elseif ($report_type === 'monthly'): ?>
                                                    <?php echo date('F Y', mktime(0, 0, 0, $best_period['month'], 1, $best_period['year'])); ?>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-success">₦<?php echo number_format($best_period['total_sales'], 2); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Average Per Period</small>
                                            <h5 class="mb-0 text-info">
                                                ₦<?php echo number_format($stats['total_sales'] / count($sales_data), 2); ?>
                                            </h5>
                                            <small>Across <?php echo count($sales_data); ?> periods</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Order Frequency</small>
                                            <h6 class="mb-0">
                                                <?php echo number_format($stats['total_orders'] / count($sales_data), 1); ?> orders/period
                                            </h6>
                                            <small><?php echo number_format($stats['avg_order_value'], 2); ?> average value</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Mobile Total Summary -->
            <?php if (!empty($sales_data)): ?>
                <div class="d-md-none mt-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <small class="text-muted d-block">Total Orders</small>
                                    <h6 class="mb-0"><?php echo number_format($stats['total_orders']); ?></h6>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Total Sales</small>
                                    <h6 class="mb-0 text-success">₦<?php echo number_format($stats['total_sales'], 2); ?></h6>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Avg Order</small>
                                    <h6 class="mb-0 text-info">₦<?php echo number_format($stats['avg_order_value'], 2); ?></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Global chart variables
let salesChartInstance = null;
let ordersChartInstance = null;
let avgChartInstance = null;
let distributionChartInstance = null;
let ordersMiniChartInstance = null;
let avgMiniChartInstance = null;

// Chart data from PHP
const chartLabels = <?php echo json_encode($chart_labels); ?>;
const chartSalesData = <?php echo json_encode($chart_sales_data); ?>;
const chartOrdersData = <?php echo json_encode($chart_orders_data); ?>;
const chartAvgData = <?php echo json_encode($chart_avg_data); ?>;
const chartType = '<?php echo $chart_type; ?>';
const reportType = '<?php echo $report_type; ?>';
const totalSales = <?php echo $total_sales; ?>;

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
    
    // Refresh report
    const refreshBtn = document.getElementById('refreshReport');
    const mobileRefreshBtn = document.getElementById('mobileRefreshReport');
    
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
    
    // Initialize charts if data exists
    if (chartLabels.length > 0) {
        initializeCharts();
    }
});

function initializeCharts() {
    // Destroy existing charts
    if (salesChartInstance) salesChartInstance.destroy();
    if (ordersChartInstance) ordersChartInstance.destroy();
    if (avgChartInstance) avgChartInstance.destroy();
    if (distributionChartInstance) distributionChartInstance.destroy();
    if (ordersMiniChartInstance) ordersMiniChartInstance.destroy();
    if (avgMiniChartInstance) avgMiniChartInstance.destroy();
    
    // Main Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    salesChartInstance = new Chart(salesCtx, {
        type: chartType,
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Sales (₦)',
                data: chartSalesData,
                borderColor: '#0d6efd',
                backgroundColor: chartType === 'line' ? 'rgba(13, 110, 253, 0.1)' : '#0d6efd',
                fill: chartType === 'area',
                tension: 0.4,
                borderWidth: 2
            }]
        },
        options: getChartOptions('Sales Trend', '₦')
    });
    
    // Orders Chart
    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    ordersChartInstance = new Chart(ordersCtx, {
        type: chartType,
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Orders',
                data: chartOrdersData,
                borderColor: '#198754',
                backgroundColor: chartType === 'line' ? 'rgba(25, 135, 84, 0.1)' : '#198754',
                fill: chartType === 'area',
                tension: 0.4,
                borderWidth: 2
            }]
        },
        options: getChartOptions('Orders Trend', '')
    });
    
    // Average Value Chart
    const avgCtx = document.getElementById('avgChart').getContext('2d');
    avgChartInstance = new Chart(avgCtx, {
        type: chartType,
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Avg Order Value (₦)',
                data: chartAvgData,
                borderColor: '#0dcaf0',
                backgroundColor: chartType === 'line' ? 'rgba(13, 202, 240, 0.1)' : '#0dcaf0',
                fill: chartType === 'area',
                tension: 0.4,
                borderWidth: 2
            }]
        },
        options: getChartOptions('Average Order Value', '₦')
    });
    
    // Mini Charts (Desktop only)
    if (window.innerWidth >= 768) {
        // Distribution Chart (Pie)
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const top5Data = chartSalesData.slice(-5).reverse();
        const top5Labels = chartLabels.slice(-5).reverse();
        
        distributionChartInstance = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: top5Labels,
                datasets: [{
                    data: top5Data,
                    backgroundColor: [
                        '#0d6efd',
                        '#198754',
                        '#ffc107',
                        '#dc3545',
                        '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const percentage = ((value / totalSales) * 100).toFixed(1);
                                return `₦${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Orders Mini Chart
        const ordersMiniCtx = document.getElementById('ordersMiniChart').getContext('2d');
        ordersMiniChartInstance = new Chart(ordersMiniCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Orders',
                    data: chartOrdersData,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Average Mini Chart
        const avgMiniCtx = document.getElementById('avgMiniChart').getContext('2d');
        avgMiniChartInstance = new Chart(avgMiniCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Avg Value',
                    data: chartAvgData,
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
}

function getChartOptions(title, currencyPrefix) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: title,
                font: {
                    size: 16,
                    weight: 'bold'
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (currencyPrefix) {
                            label += currencyPrefix + context.parsed.y.toLocaleString();
                        } else {
                            label += context.parsed.y.toLocaleString();
                        }
                        return label;
                    }
                }
            },
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                },
                ticks: {
                    callback: function(value) {
                        if (currencyPrefix) {
                            return currencyPrefix + value.toLocaleString();
                        }
                        return value.toLocaleString();
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        },
        elements: {
            point: {
                radius: 4,
                hoverRadius: 6
            }
        }
    };
}

function showChart(chartType) {
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Show selected chart, hide others
    document.querySelectorAll('.chart-container').forEach(container => {
        container.classList.add('d-none');
    });
    
    document.getElementById(chartType + 'ChartContainer').classList.remove('d-none');
}

function changeChartType(type) {
    // Update URL parameter
    const url = new URL(window.location.href);
    url.searchParams.set('chart_type', type);
    window.location.href = url.toString();
}

function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = 'export-sales-report.php?' + params.toString();
    link.download = 'sales-report-<?php echo date('Y-m-d'); ?>.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    agriApp.showToast('Export started', 'info');
}

function printReport(type = 'all') {
    const printWindow = window.open('', '_blank');
    const title = '<?php echo $report_title; ?>';
    const period = '<?php echo date("M j, Y", strtotime($date_from)); ?> to <?php echo date("M j, Y", strtotime($date_to)); ?>';
    
    let content = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .print-header { text-align: center; margin-bottom: 30px; }
                .print-header h1 { margin-bottom: 5px; }
                .print-header p { color: #666; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; }
                .total-row { background-color: #e9ecef; font-weight: bold; }
                .chart-container { margin: 20px 0; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>${title}</h1>
                <p>${period}</p>
                <p>Generated: ${new Date().toLocaleDateString()}</p>
            </div>
    `;
    
    if (type === 'table' || type === 'all') {
        content += `
            <h2>Sales Data</h2>
            <table>
                <thead>
                    <tr>
                        <th><?php echo $period_label; ?></th>
                        <th>Orders</th>
                        <th>Sales</th>
                        <th>Avg Order</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        <?php foreach ($sales_data as $data): ?>
            content += `
                <tr>
                    <td>
                        <?php 
                        if ($report_type === 'daily') {
                            echo date('M j, Y', strtotime($data['date']));
                        } elseif ($report_type === 'weekly') {
                            echo 'Week ' . $data['week'];
                        } elseif ($report_type === 'monthly') {
                            echo date('F Y', mktime(0, 0, 0, $data['month'], 1, $data['year']));
                        }
                        ?>
                    </td>
                    <td><?php echo $data['order_count']; ?></td>
                    <td>₦<?php echo number_format($data['total_sales'], 2); ?></td>
                    <td>₦<?php echo number_format($data['avg_order_value'], 2); ?></td>
                    <td>
                        <?php 
                        $percentage = $total_sales > 0 ? ($data['total_sales'] / $total_sales) * 100 : 0;
                        echo number_format($percentage, 1) . '%';
                        ?>
                    </td>
                </tr>
            `;
        <?php endforeach; ?>
        
        content += `
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>Total</td>
                        <td><?php echo number_format($stats['total_orders']); ?></td>
                        <td>₦<?php echo number_format($stats['total_sales'], 2); ?></td>
                        <td>₦<?php echo number_format($stats['avg_order_value'], 2); ?></td>
                        <td>100%</td>
                    </tr>
                </tfoot>
            </table>
        `;
    }
    
    if ((type === 'charts' || type === 'all') && chartLabels.length > 0) {
        content += `
            <div class="chart-container">
                <h2>Sales Trend Chart</h2>
                <p>Chart Type: <?php echo ucfirst($chart_type); ?> Chart</p>
                <p>Note: For interactive charts, please use the web interface.</p>
            </div>
        `;
    }
    
    content += `
            <div class="no-print" style="margin-top: 30px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Print Report
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                    Close
                </button>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(content);
    printWindow.document.close();
}

// Handle window resize for responsive charts
window.addEventListener('resize', function() {
    if (chartLabels.length > 0) {
        setTimeout(initializeCharts, 300);
    }
});

// Add CSS for charts
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Chart Styles */
    .chart-container {
        position: relative;
        height: 250px;
        width: 100%;
    }
    
    @media (max-width: 767.98px) {
        .chart-container {
            height: 200px;
        }
    }
    
    /* Mobile Table Styles */
    .mobile-table-row {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
    }
    
    .mobile-table-row:last-child {
        border-bottom: none;
    }
    
    @media (max-width: 767.98px) {
        .mobile-optimized-table {
            border: 0;
        }
        
        .mobile-optimized-table thead,
        .mobile-optimized-table tfoot {
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
        
        /* Progress bars */
        .progress {
            height: 4px !important;
        }
        
        /* Mobile header */
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Chart buttons */
        .btn-group .btn {
            flex: 1;
        }
        
        /* Sales amounts */
        .text-success {
            font-size: 1.1rem;
            font-weight: 600;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .sales-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        /* Mini charts hover */
        .card:hover {
            transform: translateY(-2px);
            transition: transform 0.2s;
        }
    }
    
    /* Print styles */
    @media print {
        .sidebar, 
        .mobile-page-header, 
        .btn-toolbar,
        .card-header .d-md-none,
        .d-md-none,
        .chart-container,
        .btn-group {
            display: none !important;
        }
        
        .container-fluid {
            padding: 0 !important;
        }
        
        .card {
            border: 1px solid #dee2e6 !important;
            box-shadow: none !important;
        }
        
        .table {
            font-size: 0.9rem;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>