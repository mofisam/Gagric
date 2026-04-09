<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

// Handle Plan Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_plan':
                $features = isset($_POST['features']) ? array_filter($_POST['features']) : [];
                $db->insert('subscription_plans', [
                    'name' => sanitizeInput($_POST['name']),
                    'description' => sanitizeInput($_POST['description']),
                    'duration_days' => (int)$_POST['duration_days'],
                    'price' => (float)$_POST['price'],
                    'features' => json_encode($features),
                    'display_order' => (int)$_POST['display_order'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ]);
                setFlashMessage('Subscription plan added successfully', 'success');
                break;
                
            case 'edit_plan':
                $features = isset($_POST['features']) ? array_filter($_POST['features']) : [];
                $db->update('subscription_plans', [
                    'name' => sanitizeInput($_POST['name']),
                    'description' => sanitizeInput($_POST['description']),
                    'duration_days' => (int)$_POST['duration_days'],
                    'price' => (float)$_POST['price'],
                    'features' => json_encode($features),
                    'display_order' => (int)$_POST['display_order'],
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ], 'id = ?', [$_POST['plan_id']]);
                setFlashMessage('Subscription plan updated successfully', 'success');
                break;
                
            case 'delete_plan':
                $db->query("DELETE FROM subscription_plans WHERE id = ?", [$_POST['plan_id']]);
                setFlashMessage('Subscription plan deleted', 'warning');
                break;
                
            case 'update_trial_settings':
                $db->update('free_trial_settings', [
                    'duration_days' => (int)$_POST['duration_days'],
                    'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
                    'max_products' => (int)$_POST['max_products'],
                    'max_orders' => (int)$_POST['max_orders'],
                    'features' => json_encode(array_filter($_POST['trial_features'] ?? [])),
                    'updated_by' => $_SESSION['user_id']
                ], 'id = ?', [1]);
                setFlashMessage('Free trial settings updated successfully', 'success');
                break;
        }
        header('Location: manage-plans.php');
        exit;
    }
}

// Get all subscription plans
$plans = $db->fetchAll("SELECT * FROM subscription_plans ORDER BY display_order ASC, price ASC");

// Get free trial settings
$trial_settings = $db->fetchOne("SELECT * FROM free_trial_settings WHERE id = 1");
if (!$trial_settings) {
    $db->insert('free_trial_settings', [
        'duration_days' => 14,
        'is_enabled' => 1,
        'max_products' => 50,
        'max_orders' => 100,
        'features' => json_encode(['Up to 50 products', 'Basic analytics', 'Email support'])
    ]);
    $trial_settings = $db->fetchOne("SELECT * FROM free_trial_settings WHERE id = 1");
}

// Get statistics
$stats = [
    'total_plans' => count($plans),
    'active_plans' => $db->fetchOne("SELECT COUNT(*) as count FROM subscription_plans WHERE is_active = 1")['count'],
    'total_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE subscription_type = 'paid'")['count'],
    'active_subscriptions' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_subscriptions WHERE is_active = 1 AND end_date > NOW()")['count'],
    'total_revenue' => $db->fetchOne("SELECT SUM(amount_paid) as total FROM seller_subscriptions WHERE payment_status = 'paid'")['total'] ?? 0,
    'monthly_revenue' => $db->fetchOne("SELECT SUM(amount_paid) as total FROM seller_subscriptions WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['total'] ?? 0
];

$page_title = "Manage Subscription Plans";
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
                    <h1 class="h2 mb-1">Subscription Management</h1>
                    <p class="text-muted mb-0">Manage subscription plans, free trial settings, and seller subscriptions</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="coupons.php" class="btn btn-sm btn-outline-primary me-2">
                        <i class="bi bi-ticket me-1"></i> Manage Coupons
                    </a>
                    <a href="subscriptions.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-people me-1"></i> View Subscriptions
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Total Plans</h6>
                            <h3 class="card-title mb-0"><?php echo $stats['total_plans']; ?></h3>
                            <small class="text-success"><?php echo $stats['active_plans']; ?> active</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Active Subs</h6>
                            <h3 class="card-title mb-0"><?php echo number_format($stats['active_subscriptions']); ?></h3>
                            <small class="text-info">Total: <?php echo number_format($stats['total_subscriptions']); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Total Revenue</h6>
                            <h3 class="card-title mb-0">₦<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                            <small class="text-success">This month: ₦<?php echo number_format($stats['monthly_revenue'], 2); ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Free Trial</h6>
                            <h3 class="card-title mb-0"><?php echo $trial_settings['duration_days']; ?> days</h3>
                            <small class="text-<?php echo $trial_settings['is_enabled'] ? 'success' : 'danger'; ?>">
                                <?php echo $trial_settings['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="subscriptionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="plans-tab" data-bs-toggle="tab" data-bs-target="#plans" type="button" role="tab">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Subscription Plans
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="trial-tab" data-bs-toggle="tab" data-bs-target="#trial" type="button" role="tab">
                        <i class="bi bi-clock-history me-1"></i> Free Trial Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="coupons-tab" data-bs-toggle="tab" data-bs-target="#coupons" type="button" role="tab">
                        <i class="bi bi-ticket-perforated me-1"></i> Coupon Codes
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Subscription Plans Tab -->
                <div class="tab-pane fade show active" id="plans" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Subscription Plans</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                            <i class="bi bi-plus-circle me-1"></i> Add Plan
                        </button>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($plans as $plan): ?>
                            <?php $features = json_decode($plan['features'], true); ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 shadow-sm border-0 <?php echo $plan['is_active'] ? 'border-start border-success border-3' : 'opacity-75'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($plan['name']); ?></h5>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editPlanModal<?php echo $plan['id']; ?>">
                                                            <i class="bi bi-pencil me-2"></i> Edit
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deletePlanModal<?php echo $plan['id']; ?>">
                                                            <i class="bi bi-trash me-2"></i> Delete
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="h2 text-success">₦<?php echo number_format($plan['price'], 2); ?></span>
                                            <span class="text-muted">/<?php echo $plan['duration_days']; ?> days</span>
                                        </div>
                                        
                                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($plan['description']); ?></p>
                                        
                                        <ul class="list-unstyled small">
                                            <?php if ($features): ?>
                                                <?php foreach ($features as $feature): ?>
                                                    <li class="mb-1">
                                                        <i class="bi bi-check-circle-fill text-success me-2" style="font-size: 0.75rem;"></i>
                                                        <?php echo htmlspecialchars($feature); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                        
                                        <div class="mt-3">
                                            <span class="badge bg-<?php echo $plan['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                            <span class="badge bg-secondary ms-1">Order: <?php echo $plan['display_order']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Plan Modal -->
                            <div class="modal fade" id="editPlanModal<?php echo $plan['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="edit_plan">
                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Plan</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Plan Name</label>
                                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($plan['name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($plan['description']); ?></textarea>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Duration (days)</label>
                                                        <input type="number" name="duration_days" class="form-control" value="<?php echo $plan['duration_days']; ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Price (₦)</label>
                                                        <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $plan['price']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Features (one per line)</label>
                                                    <textarea name="features_text" class="form-control" rows="4" placeholder="Enter each feature on a new line"><?php 
                                                        $features = json_decode($plan['features'], true);
                                                        echo implode("\n", $features ?? []);
                                                    ?></textarea>
                                                    <small class="text-muted">Enter each feature on a new line</small>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label">Display Order</label>
                                                        <input type="number" name="display_order" class="form-control" value="<?php echo $plan['display_order']; ?>">
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="form-check mt-4">
                                                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active_<?php echo $plan['id']; ?>" <?php echo $plan['is_active'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="is_active_<?php echo $plan['id']; ?>">Active</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Plan Modal -->
                            <div class="modal fade" id="deletePlanModal<?php echo $plan['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="delete_plan">
                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Plan</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($plan['name']); ?></strong>?</p>
                                                <p class="text-danger small">This action cannot be undone. Existing subscriptions using this plan will not be affected.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Free Trial Settings Tab -->
                <div class="tab-pane fade" id="trial" role="tabpanel">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0">
                            <h5 class="card-title mb-0">Free Trial Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_trial_settings">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="is_enabled" class="form-check-input" id="trialEnabled" <?php echo $trial_settings['is_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="trialEnabled">Enable Free Trial for New Sellers</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Trial Duration (days)</label>
                                        <input type="number" name="duration_days" class="form-control" value="<?php echo $trial_settings['duration_days']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Max Products During Trial</label>
                                        <input type="number" name="max_products" class="form-control" value="<?php echo $trial_settings['max_products']; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Max Orders During Trial</label>
                                        <input type="number" name="max_orders" class="form-control" value="<?php echo $trial_settings['max_orders']; ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Trial Features (one per line)</label>
                                    <textarea name="trial_features_text" class="form-control" rows="5" placeholder="Enter each feature on a new line"><?php 
                                        $features = json_decode($trial_settings['features'], true);
                                        echo implode("\n", $features ?? []);
                                    ?></textarea>
                                    <small class="text-muted">These features will be available during the free trial period</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Trial Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Coupons Tab -->
                <div class="tab-pane fade" id="coupons" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Coupon Codes</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCouponModal">
                            <i class="bi bi-plus-circle me-1"></i> Add Coupon
                        </button>
                    </div>
                    
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <?php
                            $coupons = $db->fetchAll("
                                SELECT c.*, u.first_name, u.last_name 
                                FROM coupon_codes c
                                LEFT JOIN users u ON c.created_by = u.id
                                ORDER BY c.created_at DESC
                            ");
                            ?>
                            
                            <?php if (empty($coupons)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-ticket text-muted" style="font-size: 3rem;"></i>
                                    <h4 class="mt-3">No Coupons Created</h4>
                                    <p class="text-muted">Create your first coupon code to offer discounts.</p>
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
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($coupons as $coupon): ?>
                                                <?php
                                                $is_valid = $coupon['valid_from'] <= date('Y-m-d H:i:s') && $coupon['valid_until'] >= date('Y-m-d H:i:s');
                                                $is_expired = $coupon['valid_until'] < date('Y-m-d H:i:s');
                                                ?>
                                                <tr>
                                                    <td>
                                                        <code class="bg-light p-1"><?php echo strtoupper($coupon['code']); ?></code>
                                                        <?php if ($coupon['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($coupon['description']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($coupon['discount_type'] == 'percentage'): ?>
                                                            <span class="badge bg-info"><?php echo $coupon['discount_value']; ?>% OFF</span>
                                                            <?php if ($coupon['max_discount_amount']): ?>
                                                                <br><small>Max: ₦<?php echo number_format($coupon['max_discount_amount'], 2); ?></small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">₦<?php echo number_format($coupon['discount_value'], 2); ?> OFF</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            From: <?php echo date('M j, Y', strtotime($coupon['valid_from'])); ?><br>
                                                            Until: <?php echo date('M j, Y', strtotime($coupon['valid_until'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php echo $coupon['used_count']; ?> / <?php echo $coupon['usage_limit'] ?? '∞'; ?>
                                                        <br><small><?php echo $coupon['per_user_limit']; ?> per user</small>
                                                    </td>
                                                    <td>
                                                        <?php if (!$coupon['is_active']): ?>
                                                            <span class="badge bg-secondary">Disabled</span>
                                                        <?php elseif ($is_expired): ?>
                                                            <span class="badge bg-danger">Expired</span>
                                                        <?php elseif ($is_valid): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Scheduled</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCouponModal<?php echo $coupon['id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCouponModal<?php echo $coupon['id']; ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_plan">
                <div class="modal-header">
                    <h5 class="modal-title">Add Subscription Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (days)</label>
                            <input type="number" name="duration_days" class="form-control" value="30" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price (₦)</label>
                            <input type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Features (one per line)</label>
                        <textarea name="features_text" class="form-control" rows="4" placeholder="Enter each feature on a new line"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" class="form-control" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="is_active" class="form-check-input" id="is_active_new" checked>
                                <label class="form-check-label" for="is_active_new">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Coupon Modal -->
<div class="modal fade" id="addCouponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="process-coupon.php">
                <div class="modal-header">
                    <h5 class="modal-title">Create Coupon Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Coupon Code</label>
                            <div class="input-group">
                                <input type="text" name="code" class="form-control" placeholder="e.g., WELCOME20" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateCouponCode()">
                                    <i class="bi bi-shuffle"></i> Generate
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Welcome discount for new sellers">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" class="form-select" id="discountType" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₦)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Value</label>
                            <input type="number" step="0.01" name="discount_value" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row" id="maxDiscountRow" style="display: none;">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Maximum Discount Amount (₦)</label>
                            <input type="number" step="0.01" name="max_discount_amount" class="form-control" placeholder="Leave empty for no limit">
                            <small class="text-muted">For percentage discounts, this caps the maximum discount amount</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valid From</label>
                            <input type="datetime-local" name="valid_from" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Valid Until</label>
                            <input type="datetime-local" name="valid_until" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usage Limit (Total)</label>
                            <input type="number" name="usage_limit" class="form-control" placeholder="Leave empty for unlimited">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Per User Limit</label>
                            <input type="number" name="per_user_limit" class="form-control" value="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Applicable Plans</label>
                        <select name="applicable_plans[]" class="form-select" multiple size="3">
                            <option value="">All Plans</option>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>"><?php echo htmlspecialchars($plan['name']); ?> (₦<?php echo number_format($plan['price'], 2); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple plans. Leave empty for all plans.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Minimum Subscription Amount</label>
                        <input type="number" step="0.01" name="min_subscription_amount" class="form-control" placeholder="Leave empty for no minimum">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle features textarea conversion for forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Convert features textarea to JSON array
            const featuresTextarea = this.querySelector('textarea[name="features_text"]');
            if (featuresTextarea) {
                const features = featuresTextarea.value.split('\n').filter(line => line.trim());
                const featuresInput = document.createElement('input');
                featuresInput.type = 'hidden';
                features.forEach((feature) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'features[]'; 
                    input.value = feature;
                    this.appendChild(input);
                });
                this.appendChild(featuresInput);
                featuresTextarea.removeAttribute('name');
            }
            
            const trialFeaturesTextarea = this.querySelector('textarea[name="trial_features_text"]');
            if (trialFeaturesTextarea) {
                const features = trialFeaturesTextarea.value.split('\n').filter(line => line.trim());
                const featuresInput = document.createElement('input');
                featuresInput.type = 'hidden';
                features.forEach((feature) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'trial_features[]'; 
                    input.value = feature;
                    this.appendChild(input);
                });
                this.appendChild(featuresInput);
                trialFeaturesTextarea.removeAttribute('name');
            }
        });
    });
    
    // Show/hide max discount field based on discount type
    const discountTypeSelect = document.getElementById('discountType');
    const maxDiscountRow = document.getElementById('maxDiscountRow');
    
    if (discountTypeSelect) {
        discountTypeSelect.addEventListener('change', function() {
            maxDiscountRow.style.display = this.value === 'percentage' ? 'block' : 'none';
        });
    }
    
    // Set default datetime values
    const now = new Date();
    const validFrom = new Date(now);
    const validUntil = new Date(now);
    validUntil.setMonth(validUntil.getMonth() + 3);
    
    const validFromInput = document.querySelector('input[name="valid_from"]');
    const validUntilInput = document.querySelector('input[name="valid_until"]');
    
    if (validFromInput) {
        validFromInput.value = validFrom.toISOString().slice(0, 16);
    }
    if (validUntilInput) {
        validUntilInput.value = validUntil.toISOString().slice(0, 16);
    }
});

function generateCouponCode() {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let result = '';
    for (let i = 0; i < 8; i++) {
        result += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    document.querySelector('input[name="code"]').value = result;
}
</script>

<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
}

.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 2px solid transparent;
}

.nav-tabs .nav-link:hover {
    border-color: transparent;
    color: #198754;
}

.nav-tabs .nav-link.active {
    color: #198754;
    border-bottom: 2px solid #198754;
    background: transparent;
}

code {
    background: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-weight: 600;
}

.modal-lg {
    max-width: 800px;
}
</style>

<?php include '../../includes/footer.php'; ?>