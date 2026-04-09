<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

// Handle Coupon Actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'toggle':
            $coupon_id = (int)$_GET['id'];
            $current = $db->fetchOne("SELECT is_active FROM coupon_codes WHERE id = ?", [$coupon_id]);
            if ($current) {
                $db->update('coupon_codes', ['is_active' => !$current['is_active']], 'id = ?', [$coupon_id]);
                setFlashMessage('Coupon status updated', 'success');
            }
            break;
            
        case 'delete':
            $coupon_id = (int)$_GET['id'];
            $db->query("DELETE FROM coupon_codes WHERE id = ?", [$coupon_id]);
            setFlashMessage('Coupon deleted successfully', 'success');
            break;
            
        case 'duplicate':
            $coupon_id = (int)$_GET['id'];
            $original = $db->fetchOne("SELECT * FROM coupon_codes WHERE id = ?", [$coupon_id]);
            if ($original) {
                unset($original['id']);
                $original['code'] = $original['code'] . '_COPY_' . rand(100, 999);
                $original['used_count'] = 0;
                $original['created_at'] = date('Y-m-d H:i:s');
                $db->insert('coupon_codes', $original);
                setFlashMessage('Coupon duplicated successfully', 'success');
            }
            break;
    }
    header('Location: coupons.php');
    exit;
}

// Handle Create/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicable_plans = isset($_POST['applicable_plans']) ? array_filter($_POST['applicable_plans']) : null;
    
    $data = [
        'code' => strtoupper(sanitizeInput($_POST['code'])),
        'description' => sanitizeInput($_POST['description']),
        'discount_type' => $_POST['discount_type'],
        'discount_value' => (float)$_POST['discount_value'],
        'applicable_plans' => $applicable_plans ? json_encode(array_values($applicable_plans)) : null,
        'min_subscription_amount' => !empty($_POST['min_subscription_amount']) ? (float)$_POST['min_subscription_amount'] : null,
        'max_discount_amount' => !empty($_POST['max_discount_amount']) ? (float)$_POST['max_discount_amount'] : null,
        'usage_limit' => !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null,
        'per_user_limit' => (int)$_POST['per_user_limit'],
        'valid_from' => $_POST['valid_from'],
        'valid_until' => $_POST['valid_until'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'created_by' => $_SESSION['user_id']
    ];
    
    if (isset($_POST['coupon_id']) && !empty($_POST['coupon_id'])) {
        // Update existing
        $db->update('coupon_codes', $data, 'id = ?', [$_POST['coupon_id']]);
        setFlashMessage('Coupon updated successfully', 'success');
    } else {
        // Create new
        $db->insert('coupon_codes', $data);
        setFlashMessage('Coupon created successfully', 'success');
    }
    
    header('Location: coupons.php');
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$discount_type = $_GET['discount_type'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (code LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
}

if (!empty($status)) {
    $now = date('Y-m-d H:i:s');
    if ($status === 'active') {
        $where .= " AND is_active = 1 AND valid_from <= ? AND valid_until >= ?";
        $params[] = $now;
        $params[] = $now;
    } elseif ($status === 'expired') {
        $where .= " AND valid_until < ?";
        $params[] = $now;
    } elseif ($status === 'upcoming') {
        $where .= " AND valid_from > ?";
        $params[] = $now;
    } elseif ($status === 'disabled') {
        $where .= " AND is_active = 0";
    }
}

if (!empty($discount_type)) {
    $where .= " AND discount_type = ?";
    $params[] = $discount_type;
}

// Get coupons
$coupons = $db->fetchAll("
    SELECT c.*, 
           u.first_name, 
           u.last_name,
           CASE 
               WHEN c.valid_until < NOW() THEN 'expired'
               WHEN c.valid_from > NOW() THEN 'upcoming'
               WHEN c.is_active = 0 THEN 'disabled'
               ELSE 'active'
           END as status
    FROM coupon_codes c
    LEFT JOIN users u ON c.created_by = u.id
    $where
    ORDER BY c.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Get total count
$total_coupons = $db->fetchOne("SELECT COUNT(*) as count FROM coupon_codes c $where", $params)['count'];
$total_pages = ceil($total_coupons / $limit);

// Get statistics
$stats = [
    'total_coupons' => $db->fetchOne("SELECT COUNT(*) as count FROM coupon_codes")['count'],
    'active_coupons' => $db->fetchOne("SELECT COUNT(*) as count FROM coupon_codes WHERE is_active = 1 AND valid_from <= NOW() AND valid_until >= NOW()")['count'],
    'expired_coupons' => $db->fetchOne("SELECT COUNT(*) as count FROM coupon_codes WHERE valid_until < NOW()")['count'],
    'total_uses' => $db->fetchOne("SELECT SUM(used_count) as total FROM coupon_codes")['total'] ?? 0,
    'avg_discount' => $db->fetchOne("SELECT AVG(discount_value) as avg FROM coupon_codes WHERE discount_type = 'percentage'")['avg'] ?? 0,
    'total_saved' => $db->fetchOne("SELECT SUM(discount_amount) as total FROM seller_subscriptions WHERE coupon_code_id IS NOT NULL")['total'] ?? 0
];

// Get all subscription plans for dropdown
$plans = $db->fetchAll("SELECT id, name, price FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC");

$page_title = "Coupon Codes";
$page_css = 'dashboard.css';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Coupon Codes</h1>
                    <p class="text-muted mb-0">Create and manage discount coupons for seller subscriptions</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-plans.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Plans
                    </a>
                    <a href="subscriptions.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-people me-1"></i> Subscriptions
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Total Coupons</h6>
                            <h3 class="card-title mb-0"><?php echo number_format($stats['total_coupons']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0 bg-success bg-opacity-10">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Active</h6>
                            <h3 class="card-title mb-0 text-success"><?php echo number_format($stats['active_coupons']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0 bg-danger bg-opacity-10">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Expired</h6>
                            <h3 class="card-title mb-0 text-danger"><?php echo number_format($stats['expired_coupons']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Total Uses</h6>
                            <h3 class="card-title mb-0"><?php echo number_format($stats['total_uses']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Total Saved</h6>
                            <h3 class="card-title mb-0 text-success">₦<?php echo number_format($stats['total_saved'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Coupon Button & Filter -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#couponModal" onclick="resetCouponForm()">
                    <i class="bi bi-plus-circle me-1"></i> Create New Coupon
                </button>
                
                <form method="GET" class="d-flex gap-2">
                    <div class="input-group" style="max-width: 250px;">
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="Search coupons..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary btn-sm" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="upcoming" <?php echo $status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="disabled" <?php echo $status === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                    <select name="discount_type" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="percentage" <?php echo $discount_type === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                        <option value="fixed" <?php echo $discount_type === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                    </select>
                    <?php if ($search || $status || $discount_type): ?>
                        <a href="coupons.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Coupons Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($coupons)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-ticket-perforated text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No Coupons Found</h4>
                            <p class="text-muted">Create your first coupon code to offer discounts to sellers.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#couponModal">
                                <i class="bi bi-plus-circle me-1"></i> Create Coupon
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Discount</th>
                                        <th>Valid Period</th>
                                        <th>Usage</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coupons as $coupon): ?>
                                        <?php
                                        $status_colors = [
                                            'active' => 'success',
                                            'expired' => 'danger',
                                            'upcoming' => 'info',
                                            'disabled' => 'secondary'
                                        ];
                                        $usage_percentage = $coupon['usage_limit'] ? ($coupon['used_count'] / $coupon['usage_limit']) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <code class="bg-light p-1 fs-6"><?php echo strtoupper($coupon['code']); ?></code>
                                                <?php if ($coupon['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($coupon['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($coupon['discount_type'] == 'percentage'): ?>
                                                    <span class="badge bg-info fs-6"><?php echo $coupon['discount_value']; ?>% OFF</span>
                                                    <?php if ($coupon['max_discount_amount']): ?>
                                                        <br><small class="text-muted">Max: ₦<?php echo number_format($coupon['max_discount_amount'], 2); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-success fs-6">₦<?php echo number_format($coupon['discount_value'], 2); ?> OFF</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="bi bi-calendar-check"></i> <?php echo date('M j, Y', strtotime($coupon['valid_from'])); ?><br>
                                                    <i class="bi bi-calendar-x"></i> <?php echo date('M j, Y', strtotime($coupon['valid_until'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span><?php echo $coupon['used_count']; ?> / <?php echo $coupon['usage_limit'] ?? '∞'; ?> uses</span>
                                                    <?php if ($coupon['usage_limit']): ?>
                                                        <div class="progress" style="height: 4px; width: 100px;">
                                                            <div class="progress-bar bg-<?php echo $usage_percentage > 90 ? 'danger' : ($usage_percentage > 70 ? 'warning' : 'success'); ?>" 
                                                                 style="width: <?php echo $usage_percentage; ?>%"></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <small class="text-muted"><?php echo $coupon['per_user_limit']; ?> per user</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $status_colors[$coupon['status']]; ?>">
                                                    <?php echo ucfirst($coupon['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($coupon['created_at'])); ?></small>
                                                <br>
                                                <small class="text-muted">by <?php echo $coupon['first_name'] ?? 'System'; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editCoupon(<?php echo htmlspecialchars(json_encode($coupon)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <a href="?action=duplicate&id=<?php echo $coupon['id']; ?>" 
                                                       class="btn btn-outline-secondary"
                                                       onclick="return confirm('Duplicate this coupon?')">
                                                        <i class="bi bi-files"></i>
                                                    </a>
                                                    <a href="?action=toggle&id=<?php echo $coupon['id']; ?>" 
                                                       class="btn btn-outline-<?php echo $coupon['is_active'] ? 'warning' : 'success'; ?>">
                                                        <i class="bi bi-<?php echo $coupon['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $coupon['id']; ?>" 
                                                       class="btn btn-outline-danger"
                                                       onclick="return confirm('Delete this coupon permanently?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <li class="page-item active"><span class="page-link"><?php echo $i; ?></span></li>
                                            <?php elseif ($i <= 2 || $i >= $total_pages - 1 || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php elseif ($i == 3 || $i == $total_pages - 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Coupon Modal (Create/Edit) -->
<div class="modal fade" id="couponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="couponForm">
                <input type="hidden" name="coupon_id" id="coupon_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="couponModalTitle">Create New Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Coupon Code *</label>
                            <div class="input-group">
                                <input type="text" name="code" id="code" class="form-control" placeholder="e.g., WELCOME20" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateCouponCode()">
                                    <i class="bi bi-shuffle"></i> Generate
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" id="description" class="form-control" placeholder="Welcome discount for new sellers">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Type *</label>
                            <select name="discount_type" id="discount_type" class="form-select" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₦)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Value *</label>
                            <input type="number" step="0.01" name="discount_value" id="discount_value" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row" id="max_discount_row" style="display: none;">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Maximum Discount Amount (₦)</label>
                            <input type="number" step="0.01" name="max_discount_amount" id="max_discount_amount" class="form-control" placeholder="Leave empty for no limit">
                            <small class="text-muted">For percentage discounts, this caps the maximum discount amount</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valid From *</label>
                            <input type="datetime-local" name="valid_from" id="valid_from" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valid Until *</label>
                            <input type="datetime-local" name="valid_until" id="valid_until" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usage Limit (Total)</label>
                            <input type="number" name="usage_limit" id="usage_limit" class="form-control" placeholder="Leave empty for unlimited">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Per User Limit</label>
                            <input type="number" name="per_user_limit" id="per_user_limit" class="form-control" value="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Applicable Plans</label>
                        <select name="applicable_plans[]" id="applicable_plans" class="form-select" multiple size="4">
                            <option value="">All Plans</option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>">
                                    <?php echo htmlspecialchars($plan['name']); ?> (₦<?php echo number_format($plan['price'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple plans. Leave empty for all plans.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Minimum Subscription Amount</label>
                        <input type="number" step="0.01" name="min_subscription_amount" id="min_subscription_amount" class="form-control" placeholder="Leave empty for no minimum">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Set default datetime values
const now = new Date();
const validFrom = new Date(now);
const validUntil = new Date(now);
validUntil.setMonth(validUntil.getMonth() + 3);

function setDefaultDates() {
    const validFromInput = document.getElementById('valid_from');
    const validUntilInput = document.getElementById('valid_until');
    
    if (validFromInput && !validFromInput.value) {
        validFromInput.value = validFrom.toISOString().slice(0, 16);
    }
    if (validUntilInput && !validUntilInput.value) {
        validUntilInput.value = validUntil.toISOString().slice(0, 16);
    }
}

function generateCouponCode() {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 8; i++) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    document.getElementById('code').value = result;
}

function resetCouponForm() {
    document.getElementById('couponForm').reset();
    document.getElementById('coupon_id').value = '';
    document.getElementById('couponModalTitle').textContent = 'Create New Coupon';
    setDefaultDates();
    document.getElementById('discount_type').dispatchEvent(new Event('change'));
}

function editCoupon(coupon) {
    document.getElementById('coupon_id').value = coupon.id;
    document.getElementById('code').value = coupon.code;
    document.getElementById('description').value = coupon.description || '';
    document.getElementById('discount_type').value = coupon.discount_type;
    document.getElementById('discount_value').value = coupon.discount_value;
    document.getElementById('max_discount_amount').value = coupon.max_discount_amount || '';
    document.getElementById('valid_from').value = coupon.valid_from.slice(0, 16);
    document.getElementById('valid_until').value = coupon.valid_until.slice(0, 16);
    document.getElementById('usage_limit').value = coupon.usage_limit || '';
    document.getElementById('per_user_limit').value = coupon.per_user_limit;
    document.getElementById('min_subscription_amount').value = coupon.min_subscription_amount || '';
    document.getElementById('is_active').checked = coupon.is_active == 1;
    
    // Set applicable plans
    let applicablePlans = [];
    if (coupon.applicable_plans && coupon.applicable_plans !== 'null') {
        try {
            applicablePlans = JSON.parse(coupon.applicable_plans);
        } catch(e) { applicablePlans = []; }
    }
    
    const select = document.getElementById('applicable_plans');
    for (let i = 0; i < select.options.length; i++) {
        select.options[i].selected = applicablePlans.includes(select.options[i].value);
    }
    
    document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
    document.getElementById('discount_type').dispatchEvent(new Event('change'));
    
    new bootstrap.Modal(document.getElementById('couponModal')).show();
}

// Show/hide max discount field based on discount type
document.getElementById('discount_type').addEventListener('change', function() {
    const maxDiscountRow = document.getElementById('max_discount_row');
    maxDiscountRow.style.display = this.value === 'percentage' ? 'block' : 'none';
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setDefaultDates();
    document.getElementById('discount_type').dispatchEvent(new Event('change'));
});
</script>

<style>
code {
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 600;
    font-size: 0.9rem;
}

.badge.fs-6 {
    font-size: 0.85rem;
    padding: 0.35rem 0.65rem;
}

.progress {
    background-color: #e9ecef;
    border-radius: 2px;
}

.table td {
    vertical-align: middle;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}
</style>

<?php include '../../includes/footer.php'; ?>