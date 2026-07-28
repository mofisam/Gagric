<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

// --- Filters ---
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Validate dates fall back to safe defaults
if (!strtotime($date_from)) { $date_from = date('Y-m-01'); }
if (!strtotime($date_to)) { $date_to = date('Y-m-d'); }
$range_end = $date_to . ' 23:59:59';

// --- Summary cards ---
$all_time = $db->fetchOne("SELECT COALESCE(SUM(commission_amount),0) as total FROM seller_payouts")['total'];

$this_month = $db->fetchOne(
    "SELECT COALESCE(SUM(commission_amount),0) as total FROM seller_payouts 
     WHERE created_at >= ?",
    [date('Y-m-01 00:00:00')]
)['total'];

$today_total = $db->fetchOne(
    "SELECT COALESCE(SUM(commission_amount),0) as total FROM seller_payouts 
     WHERE created_at >= ?",
    [date('Y-m-d 00:00:00')]
)['total'];

$period_summary = $db->fetchOne(
    "SELECT COALESCE(SUM(commission_amount),0) as total_commission,
            COALESCE(SUM(amount),0) as total_sales,
            COALESCE(AVG(commission_rate),0) as avg_rate,
            COUNT(*) as tx_count
     FROM seller_payouts
     WHERE created_at BETWEEN ? AND ?",
    [$date_from . ' 00:00:00', $range_end]
);

// --- Chart: commission earned per day, within the selected range ---
$daily = $db->fetchAll(
    "SELECT DATE(created_at) as day, SUM(commission_amount) as total
     FROM seller_payouts
     WHERE created_at BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY day ASC",
    [$date_from . ' 00:00:00', $range_end]
);
$chart_labels = array_map(fn($d) => date('M j', strtotime($d['day'])), $daily);
$chart_values = array_map(fn($d) => (float)$d['total'], $daily);

// --- Seller breakdown (paginated) ---
$seller_breakdown = $db->fetchAll(
    "SELECT sp.user_id as seller_id, sp.business_name,
            COUNT(spay.id) as tx_count,
            COALESCE(SUM(spay.amount),0) as total_sales,
            COALESCE(SUM(spay.commission_amount),0) as total_commission,
            COALESCE(SUM(spay.net_amount),0) as total_net,
            COALESCE(AVG(spay.commission_rate),0) as avg_rate
     FROM seller_payouts spay
     JOIN seller_profiles sp ON spay.seller_id = sp.user_id
     WHERE spay.created_at BETWEEN ? AND ?
     GROUP BY spay.seller_id, sp.business_name
     ORDER BY total_commission DESC
     LIMIT $per_page OFFSET $offset",
    [$date_from . ' 00:00:00', $range_end]
);

$total_sellers_in_range = $db->fetchOne(
    "SELECT COUNT(DISTINCT spay.seller_id) as count
     FROM seller_payouts spay
     WHERE spay.created_at BETWEEN ? AND ?",
    [$date_from . ' 00:00:00', $range_end]
)['count'];
$total_pages = max(1, ceil($total_sellers_in_range / $per_page));

$page_title = "Commissions";
$page_css = 'dashboard.css';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Commissions</h1>
                    <p class="text-muted mb-0">Platform commission revenue earned from seller sales</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">All-Time Commission</p>
                            <h4 class="mb-0 text-success"><?php echo formatCurrency($all_time); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">This Month</p>
                            <h4 class="mb-0"><?php echo formatCurrency($this_month); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">Today</p>
                            <h4 class="mb-0"><?php echo formatCurrency($today_total); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">Platform Rate</p>
                            <h4 class="mb-0"><?php echo number_format(COMMISSION_RATE, 1); ?>%</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label small mb-1">From</label>
                            <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-1">To</label>
                            <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-success">Apply</button>
                            <a href="commissions.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Period Summary + Chart -->
            <div class="row g-3 mb-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title">Selected Period</h6>
                            <p class="text-muted small mb-3"><?php echo formatDate($date_from); ?> &ndash; <?php echo formatDate($date_to); ?></p>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Total Sales Volume</span>
                                <strong><?php echo formatCurrency($period_summary['total_sales']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Commission Earned</span>
                                <strong class="text-success"><?php echo formatCurrency($period_summary['total_commission']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Avg. Commission Rate</span>
                                <strong><?php echo number_format($period_summary['avg_rate'], 2); ?>%</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Transactions</span>
                                <strong><?php echo number_format($period_summary['tx_count']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title">Commission Earned Per Day</h6>
                            <?php if (!empty($chart_values)): ?>
                                <canvas id="commissionChart" height="90"></canvas>
                            <?php else: ?>
                                <p class="text-muted text-center py-5 mb-0">No commission activity in this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seller Breakdown -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Commission by Seller</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($seller_breakdown)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Seller</th>
                                        <th class="text-center">Transactions</th>
                                        <th class="text-end">Total Sales</th>
                                        <th class="text-end">Commission</th>
                                        <th class="text-end">Avg. Rate</th>
                                        <th class="text-end pe-3">Net Paid to Seller</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seller_breakdown as $row): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <a href="../users/user-details.php?id=<?php echo $row['seller_id']; ?>" class="text-dark text-decoration-none">
                                                    <?php echo htmlspecialchars($row['business_name']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?php echo number_format($row['tx_count']); ?></td>
                                            <td class="text-end"><?php echo formatCurrency($row['total_sales']); ?></td>
                                            <td class="text-end text-success fw-semibold"><?php echo formatCurrency($row['total_commission']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['avg_rate'], 2); ?>%</td>
                                            <td class="text-end pe-3"><?php echo formatCurrency($row['total_net']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center py-3">
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?from=<?php echo urlencode($date_from); ?>&to=<?php echo urlencode($date_to); ?>&page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-percent text-muted" style="font-size: 2.5rem;"></i>
                            <p class="text-muted mt-3 mb-0">No commission activity for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php if (!empty($chart_values)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('commissionChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Commission Earned (₦)',
            data: <?php echo json_encode($chart_values); ?>,
            backgroundColor: '#198754'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>