<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

// --- Filters ---
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['to'] ?? date('Y-m-d');

if (!strtotime($date_from)) { $date_from = date('Y-m-d', strtotime('-30 days')); }
if (!strtotime($date_to)) { $date_to = date('Y-m-d'); }
$range_start = $date_from . ' 00:00:00';
$range_end = $date_to . ' 23:59:59';

// --- Summary cards (platform totals, not limited to the date range) ---
$total_users = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
$total_buyers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'buyer'")['count'];
$total_sellers = $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE is_approved = 1")['count'];
$total_products = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'approved'")['count'];

// --- Range-based figures ---
$orders_in_range = $db->fetchOne(
    "SELECT COUNT(*) as count FROM orders WHERE created_at BETWEEN ? AND ?",
    [$range_start, $range_end]
)['count'];

$revenue_in_range = $db->fetchOne(
    "SELECT COALESCE(SUM(total_amount),0) as total FROM orders WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?",
    [$range_start, $range_end]
)['total'];

// --- Chart 1: New registrations per day, buyers vs sellers ---
$registrations = $db->fetchAll(
    "SELECT DATE(created_at) as day, role, COUNT(*) as count
     FROM users
     WHERE created_at BETWEEN ? AND ? AND role IN ('buyer','seller')
     GROUP BY DATE(created_at), role
     ORDER BY day ASC",
    [$range_start, $range_end]
);

$reg_days = [];
foreach ($registrations as $r) {
    $reg_days[$r['day']]['buyer'] = $reg_days[$r['day']]['buyer'] ?? 0;
    $reg_days[$r['day']]['seller'] = $reg_days[$r['day']]['seller'] ?? 0;
    $reg_days[$r['day']][$r['role']] = (int)$r['count'];
}
ksort($reg_days);
$reg_labels = array_map(fn($d) => date('M j', strtotime($d)), array_keys($reg_days));
$reg_buyers = array_map(fn($d) => $d['buyer'], array_values($reg_days));
$reg_sellers = array_map(fn($d) => $d['seller'], array_values($reg_days));

// --- Chart 2: Orders by status ---
$orders_by_status = $db->fetchAll(
    "SELECT status, COUNT(*) as count FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY status",
    [$range_start, $range_end]
);
$status_labels = array_map(fn($s) => ucfirst($s['status']), $orders_by_status);
$status_values = array_map(fn($s) => (int)$s['count'], $orders_by_status);

// --- Chart 3: Revenue by category (paid orders only) ---
$revenue_by_category = $db->fetchAll(
    "SELECT c.name as category, SUM(oi.item_total) as revenue
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     JOIN products p ON oi.product_id = p.id
     JOIN categories c ON p.category_id = c.id
     WHERE o.payment_status = 'paid' AND o.created_at BETWEEN ? AND ?
     GROUP BY c.id, c.name
     ORDER BY revenue DESC
     LIMIT 8",
    [$range_start, $range_end]
);
$category_labels = array_map(fn($c) => $c['category'], $revenue_by_category);
$category_values = array_map(fn($c) => (float)$c['revenue'], $revenue_by_category);

// --- Top viewed products in range ---
$top_viewed = $db->fetchAll(
    "SELECT p.id, p.name, sp.business_name, COUNT(pv.id) as views
     FROM product_views pv
     JOIN products p ON pv.product_id = p.id
     JOIN seller_profiles sp ON p.seller_id = sp.user_id
     WHERE pv.viewed_at BETWEEN ? AND ?
     GROUP BY p.id, p.name, sp.business_name
     ORDER BY views DESC
     LIMIT 10",
    [$range_start, $range_end]
);

$page_title = "Analytics";
$page_css = 'dashboard.css';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Analytics</h1>
                    <p class="text-muted mb-0">Platform-wide growth, orders, and product performance</p>
                </div>
            </div>

            <!-- Summary Cards (all-time) -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">Total Users</p>
                            <h4 class="mb-0"><?php echo number_format($total_users); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">Buyers</p>
                            <h4 class="mb-0"><?php echo number_format($total_buyers); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">Approved Sellers</p>
                            <h4 class="mb-0"><?php echo number_format($total_sellers); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-1">Live Products</p>
                            <h4 class="mb-0"><?php echo number_format($total_products); ?></h4>
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
                            <a href="analytics.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                        <div class="col-auto ms-auto text-end">
                            <p class="text-muted small mb-1">Orders in period</p>
                            <h5 class="mb-0"><?php echo number_format($orders_in_range); ?></h5>
                        </div>
                        <div class="col-auto text-end">
                            <p class="text-muted small mb-1">Revenue in period</p>
                            <h5 class="mb-0 text-success"><?php echo formatCurrency($revenue_in_range); ?></h5>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title">New Registrations (Buyers vs Sellers)</h6>
                            <?php if (!empty($reg_labels)): ?>
                                <canvas id="registrationsChart" height="110"></canvas>
                            <?php else: ?>
                                <p class="text-muted text-center py-5 mb-0">No new registrations in this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title">Orders by Status</h6>
                            <?php if (!empty($status_labels)): ?>
                                <canvas id="statusChart" height="140"></canvas>
                            <?php else: ?>
                                <p class="text-muted text-center py-5 mb-0">No orders in this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title">Revenue by Category</h6>
                            <?php if (!empty($category_labels)): ?>
                                <canvas id="categoryChart" height="180"></canvas>
                            <?php else: ?>
                                <p class="text-muted text-center py-5 mb-0">No paid orders in this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Most Viewed Products</h6>
                            <?php if (!empty($top_viewed)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Seller</th>
                                                <th class="text-end">Views</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_viewed as $tv): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../../buyer/products/product-details.php?id=<?php echo $tv['id']; ?>" class="text-dark text-decoration-none">
                                                            <?php echo htmlspecialchars($tv['name']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="text-muted"><?php echo htmlspecialchars($tv['business_name']); ?></td>
                                                    <td class="text-end"><?php echo number_format($tv['views']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-5 mb-0">No product views recorded in this period.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($reg_labels)): ?>
new Chart(document.getElementById('registrationsChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($reg_labels); ?>,
        datasets: [
            {
                label: 'Buyers',
                data: <?php echo json_encode($reg_buyers); ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,0.1)',
                tension: 0.3
            },
            {
                label: 'Sellers',
                data: <?php echo json_encode($reg_sellers); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                tension: 0.3
            }
        ]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
<?php endif; ?>

<?php if (!empty($status_labels)): ?>
new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($status_values); ?>,
            backgroundColor: ['#ffc107', '#198754', '#0dcaf0', '#0d6efd', '#20c997', '#dc3545', '#6c757d']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
<?php endif; ?>

<?php if (!empty($category_labels)): ?>
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($category_labels); ?>,
        datasets: [{
            label: 'Revenue (₦)',
            data: <?php echo json_encode($category_values); ?>,
            backgroundColor: '#198754'
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true } }
    }
});
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>