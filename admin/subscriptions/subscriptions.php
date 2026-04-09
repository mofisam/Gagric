<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

// Handle Subscription Actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'cancel':
            $sub_id = (int)$_GET['id'];
            $reason = $_GET['reason'] ?? 'Cancelled by admin';
            $db->update('seller_subscriptions', [
                'is_active' => 0,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason
            ], 'id = ?', [$sub_id]);
            setFlashMessage('Subscription cancelled', 'warning');
            break;
            
        case 'extend':
            $sub_id = (int)$_GET['id'];
            $days = (int)$_GET['days'];
            $current = $db->fetchOne("SELECT end_date FROM seller_subscriptions WHERE id = ?", [$sub_id]);
            if ($current) {
                $new_end = date('Y-m-d H:i:s', strtotime($current['end_date'] . " + $days days"));
                $db->update('seller_subscriptions', ['end_date' => $new_end], 'id = ?', [$sub_id]);
                setFlashMessage("Subscription extended by $days days", 'success');
            }
            break;
            
        case 'delete':
            $sub_id = (int)$_GET['id'];
            $db->query("DELETE FROM seller_subscriptions WHERE id = ?", [$sub_id]);
            setFlashMessage('Subscription record deleted', 'danger');
            break;
    }
    header('Location: subscriptions.php');
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.business_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    if ($status === 'active') {
        $where .= " AND ss.is_active = 1 AND ss.end_date > NOW()";
    } elseif ($status === 'expired') {
        $where .= " AND ss.end_date < NOW()";
    } elseif ($status === 'cancelled') {
        $where .= " AND ss.is_active = 0 AND ss.cancelled_at IS NOT NULL";
    }
}

if (!empty($type)) {
    $where .= " AND ss.subscription_type = ?";
    $params[] = $type;
}

// Get subscriptions
$subscriptions = $db->fetchAll("
    SELECT ss.*,
           u.id as user_id,
           u.first_name,
           u.last_name,
           u.email,
           u.phone,
           sp.business_name,
           sp.business_logo,
           p.name as plan_name,
           p.price as plan_price,
           p.duration_days as plan_duration,
           c.code as coupon_code,
           c.discount_type as coupon_discount_type,
           c.discount_value as coupon_discount_value
    FROM seller_subscriptions ss
    JOIN users u ON ss.seller_id = u.id
    LEFT JOIN seller_profiles sp ON sp.user_id = u.id
    LEFT JOIN subscription_plans p ON ss.plan_id = p.id
    LEFT JOIN coupon_codes c ON ss.coupon_code_id = c.id
    $where
    ORDER BY ss.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Get total count
$total_subs = $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions ss JOIN users u ON ss.seller_id = u.id $where", $params)['count'];
$total_pages = ceil($total_subs / $limit);

// Get statistics
$stats = [
    'total_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions")['count'],
    'active_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE is_active = 1 AND end_date > NOW()")['count'],
    'expired_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE end_date < NOW() AND is_active = 1")['count'],
    'cancelled_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE is_active = 0 AND cancelled_at IS NOT NULL")['count'],
    'trial_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE subscription_type = 'free_trial'")['count'],
    'paid_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE subscription_type = 'paid'")['count'],
    'total_revenue' => $db->fetchOne("SELECT SUM(amount_paid) as total FROM seller_subscriptions WHERE payment_status = 'paid'")['total'] ?? 0,
    'monthly_revenue' => $db->fetchOne("SELECT SUM(amount_paid) as total FROM seller_subscriptions WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0,
    'expiring_soon' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE is_active = 1 AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)")['count']
];

$page_title = "Seller Subscriptions";
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
                    <h1 class="h2 mb-1">Seller Subscriptions</h1>
                    <p class="text-muted mb-0">Manage and monitor seller subscription plans</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-plans.php" class="btn btn-sm btn-outline-primary me-2">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Plans
                    </a>
                    <a href="coupons.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-ticket-perforated me-1"></i> Coupons
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Total</h6>
                            <h3 class="card-title mb-0"><?php echo number_format($stats['total_subscriptions']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0 bg-success bg-opacity-10">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Active</h6>
                            <h3 class="card-title mb-0 text-success"><?php echo number_format($stats['active_subscriptions']); ?></h3>
                            <small class="text-warning"><?php echo $stats['expiring_soon']; ?> expiring soon</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0 bg-danger bg-opacity-10">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Expired</h6>
                            <h3 class="card-title mb-0 text-danger"><?php echo number_format($stats['expired_subscriptions']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0 bg-secondary bg-opacity-10">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Cancelled</h6>
                            <h3 class="card-title mb-0"><?php echo number_format($stats['cancelled_subscriptions']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Trial / Paid</h6>
                            <h3 class="card-title mb-0">
                                <span class="text-info"><?php echo $stats['trial_subscriptions']; ?></span> / 
                                <span class="text-primary"><?php echo $stats['paid_subscriptions']; ?></span>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Revenue</h6>
                            <h3 class="card-title mb-0 text-success">₦<?php echo number_format($stats['total_revenue'], 0); ?></h3>
                            <small>This month: ₦<?php echo number_format($stats['monthly_revenue'], 0); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Filter Subscriptions
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-12 col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search seller, business, email..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <select name="type" class="form-select form-select-sm">
                                <option value="">All Types</option>
                                <option value="free_trial" <?php echo $type === 'free_trial' ? 'selected' : ''; ?>>Free Trial</option>
                                <option value="paid" <?php echo $type === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-filter me-1"></i> Filter
                                </button>
                                <a href="subscriptions.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x-circle me-1"></i> Clear
                                </a>
                                <?php if($stats['expiring_soon'] > 0): ?>
                                    <a href="?status=active" class="btn btn-warning btn-sm">
                                        <i class="bi bi-clock me-1"></i> Expiring Soon (<?php echo $stats['expiring_soon']; ?>)
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0">Subscriptions (<?php echo $total_subs; ?>)</h5>
                    <small class="text-muted">
                        Showing <?php echo min($offset + 1, $total_subs); ?>-<?php echo min($offset + $limit, $total_subs); ?> of <?php echo $total_subs; ?>
                    </small>
                </div>
            </div>

            <!-- Subscriptions Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($subscriptions)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-receipt text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No Subscriptions Found</h4>
                            <p class="text-muted">No seller subscriptions match your current filters.</p>
                            <a href="subscriptions.php" class="btn btn-primary">Reset Filters</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Seller</th>
                                        <th>Plan</th>
                                        <th>Type</th>
                                        <th>Period</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $sub): 
                                        $is_expired = strtotime($sub['end_date']) < time();
                                        $is_expiring_soon = !$is_expired && strtotime($sub['end_date']) < strtotime('+7 days');
                                        $days_left = ceil((strtotime($sub['end_date']) - time()) / 86400);
                                    ?>
                                        <tr class="<?php echo $is_expiring_soon && $sub['is_active'] ? 'table-warning' : ''; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($sub['business_logo']): ?>
                                                        <img src="<?php echo BASE_URL; ?>/assets/uploads/profiles/<?php echo $sub['business_logo']; ?>" 
                                                             class="rounded-circle me-2" width="40" height="40" style="object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary bg-opacity-10 rounded-circle me-2 d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person fs-5"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($sub['business_name'] ?? $sub['first_name'] . ' ' . $sub['last_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo $sub['email']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($sub['plan_name']): ?>
                                                    <strong><?php echo htmlspecialchars($sub['plan_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $sub['plan_duration']; ?> days</small>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Free Trial</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $sub['subscription_type'] == 'paid' ? 'primary' : 'info'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $sub['subscription_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="bi bi-calendar-check"></i> <?php echo date('M j, Y', strtotime($sub['start_date'])); ?><br>
                                                    <i class="bi bi-calendar-x"></i> <?php echo date('M j, Y', strtotime($sub['end_date'])); ?>
                                                </small>
                                                <?php if ($sub['is_active'] && !$is_expired): ?>
                                                    <br><small class="text-<?php echo $is_expiring_soon ? 'danger' : 'success'; ?>">
                                                        <?php echo $days_left; ?> days left
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($sub['amount_paid'] > 0): ?>
                                                    <strong class="text-success">₦<?php echo number_format($sub['amount_paid'], 2); ?></strong>
                                                    <?php if ($sub['coupon_code']): ?>
                                                        <br><small class="text-muted">
                                                            <i class="bi bi-ticket"></i> <?php echo $sub['coupon_code']; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Free</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($sub['cancelled_at']) {
                                                    $status_badge = 'secondary';
                                                    $status_text = 'Cancelled';
                                                } elseif ($is_expired) {
                                                    $status_badge = 'danger';
                                                    $status_text = 'Expired';
                                                } elseif ($sub['is_active']) {
                                                    $status_badge = 'success';
                                                    $status_text = 'Active';
                                                } else {
                                                    $status_badge = 'warning';
                                                    $status_text = 'Inactive';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                                                <?php if ($sub['cancelled_at']): ?>
                                                    <br><small class="text-muted" title="<?php echo htmlspecialchars($sub['cancellation_reason']); ?>">
                                                        <?php echo date('M j', strtotime($sub['cancelled_at'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewModal<?php echo $sub['id']; ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    
                                                    <?php if ($sub['is_active'] && !$is_expired && !$sub['cancelled_at']): ?>
                                                        <button class="btn btn-outline-warning" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#cancelModal<?php echo $sub['id']; ?>">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-outline-success dropdown-toggle" 
                                                                    data-bs-toggle="dropdown">
                                                                <i class="bi bi-plus"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li><a class="dropdown-item" href="?action=extend&id=<?php echo $sub['id']; ?>&days=7">+7 Days</a></li>
                                                                <li><a class="dropdown-item" href="?action=extend&id=<?php echo $sub['id']; ?>&days=14">+14 Days</a></li>
                                                                <li><a class="dropdown-item" href="?action=extend&id=<?php echo $sub['id']; ?>&days=30">+30 Days</a></li>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?action=delete&id=<?php echo $sub['id']; ?>" 
                                                       class="btn btn-outline-danger"
                                                       onclick="return confirm('Delete this subscription record?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- View Details Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $sub['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            Subscription Details - <?php echo htmlspecialchars($sub['business_name'] ?? $sub['first_name']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6 class="border-bottom pb-2">Seller Information</h6>
                                                                <dl class="row small">
                                                                    <dt class="col-4">Name:</dt>
                                                                    <dd class="col-8"><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></dd>
                                                                    
                                                                    <dt class="col-4">Email:</dt>
                                                                    <dd class="col-8"><?php echo $sub['email']; ?></dd>
                                                                    
                                                                    <dt class="col-4">Phone:</dt>
                                                                    <dd class="col-8"><?php echo $sub['phone']; ?></dd>
                                                                    
                                                                    <dt class="col-4">Business:</dt>
                                                                    <dd class="col-8"><?php echo htmlspecialchars($sub['business_name'] ?? 'N/A'); ?></dd>
                                                                </dl>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6 class="border-bottom pb-2">Subscription Information</h6>
                                                                <dl class="row small">
                                                                    <dt class="col-4">Type:</dt>
                                                                    <dd class="col-8"><?php echo ucfirst($sub['subscription_type']); ?></dd>
                                                                    
                                                                    <dt class="col-4">Plan:</dt>
                                                                    <dd class="col-8"><?php echo $sub['plan_name'] ?? 'Free Trial'; ?></dd>
                                                                    
                                                                    <dt class="col-4">Start Date:</dt>
                                                                    <dd class="col-8"><?php echo date('F j, Y', strtotime($sub['start_date'])); ?></dd>
                                                                    
                                                                    <dt class="col-4">End Date:</dt>
                                                                    <dd class="col-8"><?php echo date('F j, Y', strtotime($sub['end_date'])); ?></dd>
                                                                    
                                                                    <?php if ($sub['amount_paid'] > 0): ?>
                                                                        <dt class="col-4">Amount Paid:</dt>
                                                                        <dd class="col-8">₦<?php echo number_format($sub['amount_paid'], 2); ?></dd>
                                                                        
                                                                        <?php if ($sub['coupon_code']): ?>
                                                                            <dt class="col-4">Coupon:</dt>
                                                                            <dd class="col-8"><?php echo $sub['coupon_code']; ?></dd>
                                                                            
                                                                            <dt class="col-4">Discount:</dt>
                                                                            <dd class="col-8">
                                                                                <?php echo $sub['coupon_discount_type'] == 'percentage' ? $sub['coupon_discount_value'] . '%' : '₦' . number_format($sub['coupon_discount_value'], 2); ?>
                                                                            </dd>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($sub['cancelled_at']): ?>
                                                                        <dt class="col-4">Cancelled:</dt>
                                                                        <dd class="col-8"><?php echo date('F j, Y', strtotime($sub['cancelled_at'])); ?></dd>
                                                                        
                                                                        <dt class="col-4">Reason:</dt>
                                                                        <dd class="col-8"><?php echo htmlspecialchars($sub['cancellation_reason']); ?></dd>
                                                                    <?php endif; ?>
                                                                </dl>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($sub['features_snapshot']): ?>
                                                            <div class="row mt-3">
                                                                <div class="col-12">
                                                                    <h6 class="border-bottom pb-2">Features at Signup</h6>
                                                                    <ul class="small">
                                                                        <?php 
                                                                        $features = json_decode($sub['features_snapshot'], true);
                                                                        if ($features && is_array($features)):
                                                                            foreach ($features as $feature): ?>
                                                                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> <?php echo htmlspecialchars($feature); ?></li>
                                                                        <?php 
                                                                            endforeach;
                                                                        endif; 
                                                                        ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if ($sub['is_active'] && !$is_expired && !$sub['cancelled_at']): ?>
                                                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal<?php echo $sub['id']; ?>">
                                                                Cancel Subscription
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Cancel Modal -->
                                        <div class="modal fade" id="cancelModal<?php echo $sub['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Cancel Subscription</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="GET" action="subscriptions.php">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="id" value="<?php echo $sub['id']; ?>">
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to cancel <strong><?php echo htmlspecialchars($sub['business_name'] ?? $sub['first_name']); ?></strong>'s subscription?</p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for cancellation</label>
                                                                <textarea name="reason" class="form-control" rows="3" required placeholder="Please provide a reason..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Go Back</button>
                                                            <button type="submit" class="btn btn-danger">Yes, Cancel Subscription</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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

<?php include '../../includes/footer.php'; ?>