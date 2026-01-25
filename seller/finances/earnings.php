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

// Get earnings summary
$earnings = $db->fetchOne("
    SELECT 
        COUNT(*) as total_orders,
        SUM(oi.item_total) as total_sales,
        SUM(oi.item_total * " . COMMISSION_RATE . " / 100) as total_commission,
        SUM(oi.item_total - (oi.item_total * " . COMMISSION_RATE . " / 100)) as net_earnings
    FROM order_items oi
    WHERE oi.seller_id = ? 
    AND oi.status = 'delivered'
    AND oi.created_at >= ?
", [$seller_id, $start_date]);

// Recent payouts
$recent_payouts = $db->fetchAll("
    SELECT sp.*, oi.product_name, o.order_number
    FROM seller_payouts sp
    JOIN order_items oi ON sp.order_item_id = oi.id
    JOIN orders o ON oi.order_id = o.id
    WHERE sp.seller_id = ?
    ORDER BY sp.created_at DESC
    LIMIT 5
", [$seller_id]);

$page_title = "Earnings & Revenue";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Earnings & Revenue</h1>
                <div class="btn-group">
                    <a href="payouts.php" class="btn btn-outline-primary">Payouts</a>
                    <a href="bank-details.php" class="btn btn-outline-secondary">Bank Details</a>
                </div>
            </div>

            <!-- Period Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label for="period" class="form-label">View earnings for:</label>
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

            <!-- Earnings Summary -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Sales</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatCurrency($earnings['total_sales'] ?? 0); ?>
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
                                        Net Earnings</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatCurrency($earnings['net_earnings'] ?? 0); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-wallet2 fa-2x text-gray-300"></i>
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
                                        Platform Commission</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatCurrency($earnings['total_commission'] ?? 0); ?>
                                    </div>
                                    <div class="text-xs text-muted">(<?php echo COMMISSION_RATE; ?>%)</div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-percent fa-2x text-gray-300"></i>
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
                                        Orders Delivered</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $earnings['total_orders'] ?? 0; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-cart-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Recent Transactions -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Sales</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $recent_sales = $db->fetchAll("
                                SELECT oi.*, o.order_number, o.created_at, p.name as product_name
                                FROM order_items oi
                                JOIN orders o ON oi.order_id = o.id
                                JOIN products p ON oi.product_id = p.id
                                WHERE oi.seller_id = ? AND oi.status = 'delivered'
                                ORDER BY o.created_at DESC
                                LIMIT 10
                            ", [$seller_id]);
                            ?>
                            
                            <?php if ($recent_sales): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Product</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Commission</th>
                                                <th>Net</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale): ?>
                                                <tr>
                                                    <td><?php echo $sale['order_number']; ?></td>
                                                    <td><?php echo $sale['product_name']; ?></td>
                                                    <td><?php echo formatDate($sale['created_at']); ?></td>
                                                    <td><?php echo formatCurrency($sale['item_total']); ?></td>
                                                    <td class="text-danger">-<?php echo formatCurrency(calculateCommission($sale['item_total'])); ?></td>
                                                    <td class="text-success"><?php echo formatCurrency($sale['item_total'] - calculateCommission($sale['item_total'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No recent sales</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Payout Summary -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Payout Summary</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $payout_summary = $db->fetchOne("
                                SELECT 
                                    SUM(CASE WHEN status = 'paid' THEN net_amount ELSE 0 END) as total_paid,
                                    SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END) as pending_payout,
                                    COUNT(*) as total_payouts
                                FROM seller_payouts 
                                WHERE seller_id = ?
                            ", [$seller_id]);
                            ?>
                            
                            <div class="mb-3">
                                <strong>Total Paid:</strong>
                                <span class="float-end text-success">
                                    <?php echo formatCurrency($payout_summary['total_paid'] ?? 0); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Pending Payout:</strong>
                                <span class="float-end text-warning">
                                    <?php echo formatCurrency($payout_summary['pending_payout'] ?? 0); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Next Payout:</strong>
                                <span class="float-end text-info">
                                    <?php
                                    $next_payout = $db->fetchOne("
                                        SELECT MIN(processed_at) as next_date 
                                        FROM seller_payouts 
                                        WHERE seller_id = ? AND status = 'pending'
                                    ", [$seller_id]);
                                    echo $next_payout && $next_payout['next_date'] ? formatDate($next_payout['next_date']) : 'Not scheduled';
                                    ?>
                                </span>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <a href="payouts.php" class="btn btn-primary btn-sm">View All Payouts</a>
                            </div>
                        </div>
                    </div>

                    <!-- Commission Info -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Commission Info</h5>
                        </div>
                        <div class="card-body">
                            <p class="small">
                                <strong>Platform Commission:</strong> <?php echo COMMISSION_RATE; ?>%<br>
                                <strong>Minimum Payout:</strong> <?php echo formatCurrency(MIN_PAYOUT_AMOUNT); ?><br>
                                <strong>Payout Frequency:</strong> Weekly
                            </p>
                            <div class="alert alert-info small">
                                <i class="bi bi-info-circle"></i>
                                Commission is deducted from your sales revenue. Payouts are processed automatically when your balance reaches the minimum amount.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>