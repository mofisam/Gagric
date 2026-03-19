<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$time_filter = $_GET['period'] ?? 'month';

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Calculate date ranges
$date_ranges = [
    'week' => date('Y-m-d', strtotime('-1 week')),
    'month' => date('Y-m-d', strtotime('-1 month')),
    'quarter' => date('Y-m-d', strtotime('-3 months')),
    'year' => date('Y-m-d', strtotime('-1 year'))
];
$start_date = $date_ranges[$time_filter] ?? $date_ranges['month'];

// Get earnings summary for selected period
$earnings = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT oi.order_id) as total_orders,
        COUNT(oi.id) as total_items,
        COALESCE(SUM(oi.item_total), 0) as total_sales,
        COALESCE(SUM(oi.item_total * " . COMMISSION_RATE . " / 100), 0) as total_commission,
        COALESCE(SUM(oi.item_total - (oi.item_total * " . COMMISSION_RATE . " / 100)), 0) as net_earnings,
        COALESCE(AVG(oi.item_total), 0) as avg_order_value
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? 
        AND oi.status = 'delivered'
        AND o.created_at >= ?
", [$seller_id, $start_date]);

// Get previous period for comparison
$prev_start_date = date('Y-m-d', strtotime($start_date . ' -' . 
    ($time_filter == 'week' ? '1 week' : 
     ($time_filter == 'month' ? '1 month' : 
      ($time_filter == 'quarter' ? '3 months' : '1 year')))));

      $prev_earnings = $db->fetchOne("
      SELECT 
          COALESCE(SUM(oi.item_total), 0) as total_sales
      FROM order_items oi
      JOIN orders o ON oi.order_id = o.id
      WHERE oi.seller_id = ? 
          AND oi.status = 'delivered'
          AND o.created_at >= ? 
          AND o.created_at < ?
  ", [$seller_id, $prev_start_date, $start_date]);

// Calculate growth
$growth = 0;
if ($prev_earnings['total_sales'] > 0) {
    $growth = (($earnings['total_sales'] - $prev_earnings['total_sales']) / $prev_earnings['total_sales']) * 100;
}

// Get recent payouts
$recent_payouts = $db->fetchAll("
    SELECT sp.*, oi.product_name, o.order_number
    FROM seller_payouts sp
    JOIN order_items oi ON sp.order_item_id = oi.id
    JOIN orders o ON oi.order_id = o.id
    WHERE sp.seller_id = ?
    ORDER BY sp.created_at DESC
    LIMIT 5
", [$seller_id]);

// Get payout summary
$payout_summary = $db->fetchOne("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN net_amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END), 0) as pending_payout,
        COALESCE(SUM(CASE WHEN status = 'processing' THEN net_amount ELSE 0 END), 0) as processing_payout,
        COUNT(*) as total_payouts
    FROM seller_payouts 
    WHERE seller_id = ?
", [$seller_id]);

// Get recent sales
$recent_sales = $db->fetchAll("
    SELECT 
        oi.*, 
        o.order_number, 
        o.created_at, 
        p.name as product_name,
        p.unit,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE oi.seller_id = ? AND oi.status = 'delivered'
    ORDER BY o.created_at DESC
    LIMIT 10
", [$seller_id]);

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Earnings & Revenue";
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
                        <h1 class="h5 mb-0">Earnings & Revenue</h1>
                        <small class="text-muted">Track your earnings and payouts</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Earnings & Revenue</h1>
                    <p class="text-muted mb-0">Track your sales, earnings, and payouts</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="payouts.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-cash-stack me-1"></i> Payouts
                        </a>
                        <a href="bank-details.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-bank me-1"></i> Bank Details
                        </a>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="exportEarnings()">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
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
                                <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y'); ?>
                            </span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Earnings Summary Cards -->
            <div class="row g-3 mb-4">
                <!-- Total Sales -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Sales</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($earnings['total_sales'], 2); ?></h3>
                                    <small class="<?php echo $growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi bi-arrow-<?php echo $growth >= 0 ? 'up' : 'down'; ?>-short"></i>
                                        <?php echo number_format(abs($growth), 1); ?>% vs previous period
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-currency-dollar fs-4 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Net Earnings -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Net Earnings</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($earnings['net_earnings'], 2); ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-wallet2 me-1"></i>
                                        After commission
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-wallet2 fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Platform Commission -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Platform Commission</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($earnings['total_commission'], 2); ?></h3>
                                    <small class="text-info">
                                        <i class="bi bi-percent me-1"></i>
                                        <?php echo COMMISSION_RATE; ?>% of sales
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-percent fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders Delivered -->
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Orders Delivered</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($earnings['total_orders']); ?></h3>
                                    <small class="text-warning">
                                        <i class="bi bi-cart-check me-1"></i>
                                        <?php echo number_format($earnings['total_items']); ?> items sold
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-cart-check fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row g-4">
                <!-- Recent Transactions Column -->
                <div class="col-12 col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2 text-primary"></i>
                                Recent Sales
                            </h5>
                            <span class="badge bg-primary">Last 10 transactions</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($recent_sales): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th class="text-center">Order #</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">Commission</th>
                                                <th class="text-end">Net</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale): 
                                                $commission = calculateCommission($sale['item_total']);
                                                $net = $sale['item_total'] - $commission;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($sale['product_image']): ?>
                                                                <img src="<?php echo BASE_URL . '/uploads/products/' . $sale['product_image']; ?>" 
                                                                     alt="<?php echo htmlspecialchars($sale['product_name']); ?>"
                                                                     class="rounded me-2" 
                                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                            <?php endif; ?>
                                                            <div>
                                                                <span class="fw-bold"><?php echo htmlspecialchars($sale['product_name']); ?></span>
                                                                <br>
                                                                <small class="text-muted">Qty: <?php echo $sale['quantity']; ?> <?php echo $sale['unit']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="font-monospace small"><?php echo $sale['order_number']; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div>
                                                            <?php echo formatDate($sale['created_at'], 'M j'); ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo formatDate($sale['created_at'], 'h:i A'); ?></small>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="fw-bold">₦<?php echo number_format($sale['item_total'], 2); ?></span>
                                                    </td>
                                                    <td class="text-end text-danger">
                                                        -₦<?php echo number_format($commission, 2); ?>
                                                    </td>
                                                    <td class="text-end text-success fw-bold">
                                                        ₦<?php echo number_format($net, 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-cart-x display-1 text-muted"></i>
                                    <p class="text-muted mt-3 mb-0">No sales in this period</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Payout Summary -->
                <div class="col-12 col-lg-4">
                    <!-- Payout Summary Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-cash-stack me-2 text-success"></i>
                                Payout Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Available Balance -->
                            <div class="text-center mb-4 p-3 bg-light rounded">
                                <small class="text-muted d-block">Available Balance</small>
                                <h2 class="text-success mb-0">₦<?php echo number_format($payout_summary['pending_payout'], 2); ?></h2>
                                <small class="text-muted">Pending payout</small>
                            </div>
                            
                            <!-- Stats -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Total Paid</span>
                                    <span class="fw-bold text-success">₦<?php echo number_format($payout_summary['total_paid'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Processing</span>
                                    <span class="fw-bold text-info">₦<?php echo number_format($payout_summary['processing_payout'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Total Payouts</span>
                                    <span class="fw-bold"><?php echo number_format($payout_summary['total_payouts']); ?></span>
                                </div>
                            </div>
                            
                            <!-- Next Payout -->
                            <div class="alert alert-info mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar-check fs-4 me-3"></i>
                                    <div>
                                        <small class="d-block">Next Payout</small>
                                        <strong>
                                            <?php
                                            $next_payout_date = $db->fetchOne("
                                                SELECT MIN(created_at) as next_date 
                                                FROM seller_payouts 
                                                WHERE seller_id = ? AND status = 'pending'
                                            ", [$seller_id]);
                                            
                                            if ($next_payout_date && $next_payout_date['next_date']) {
                                                echo formatDate($next_payout_date['next_date'], 'M j, Y');
                                            } else {
                                                echo 'Not scheduled';
                                            }
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="payouts.php" class="btn btn-primary">
                                    <i class="bi bi-list-ul me-1"></i> View All Payouts
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Payouts Card -->
                    <?php if ($recent_payouts): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2 text-info"></i>
                                Recent Payouts
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_payouts as $payout): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">
                                                <?php echo formatDate($payout['created_at'], 'M j, Y'); ?>
                                            </small>
                                            <span class="fw-bold">₦<?php echo number_format($payout['net_amount'], 2); ?></span>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo $payout['status'] == 'paid' ? 'success' : 
                                                ($payout['status'] == 'pending' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($payout['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Commission Info Card -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2 text-primary"></i>
                                Commission Info
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Platform Commission</span>
                                    <span class="badge bg-info"><?php echo COMMISSION_RATE; ?>%</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Minimum Payout</span>
                                    <span class="fw-bold">₦<?php echo number_format(MIN_PAYOUT_AMOUNT, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Payout Frequency</span>
                                    <span class="badge bg-secondary">Weekly</span>
                                </div>
                            </div>
                            
                            <div class="alert alert-light border small mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                Commission is deducted from your sales revenue. Payouts are processed automatically when your balance reaches the minimum amount.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Helper function for commission calculation -->
<?php
if (!function_exists('calculateCommission')) {
    function calculateCommission($amount) {
        return $amount * COMMISSION_RATE / 100;
    }
}
?>

<script>
function exportEarnings() {
    const period = document.getElementById('period').value;
    window.location.href = 'export-earnings.php?period=' + period + '&format=csv';
}

// Add custom styles
const style = document.createElement('style');
style.textContent = `
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .list-group-item {
        border-left: none;
        border-right: none;
    }
    
    .list-group-item:first-child {
        border-top: none;
    }
    
    .list-group-item:last-child {
        border-bottom: none;
    }
    
    @media (max-width: 768px) {
        .table td:nth-child(2),
        .table td:nth-child(3) {
            font-size: 0.9rem;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>