<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Handle subscription actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_subscription'])) {
        $reason = sanitizeInput($_POST['cancellation_reason']);
        $db->update('seller_subscriptions', [
            'is_active' => 0,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => $reason,
            'auto_renew' => 0
        ], 'seller_id = ? AND is_active = 1', [$seller_id]);
        setFlashMessage('Your subscription has been cancelled.', 'warning');
        header('Location: manage.php');
        exit;
    }
    
    if (isset($_POST['toggle_autorenew'])) {
        $current = $db->fetchOne("SELECT auto_renew FROM seller_subscriptions WHERE seller_id = ? AND is_active = 1", [$seller_id]);
        $new_status = $current['auto_renew'] ? 0 : 1;
        $db->update('seller_subscriptions', ['auto_renew' => $new_status], 'seller_id = ? AND is_active = 1', [$seller_id]);
        setFlashMessage('Auto-renew ' . ($new_status ? 'enabled' : 'disabled'), 'success');
        header('Location: manage.php');
        exit;
    }
}

// Get current subscription
$subscription = $db->fetchOne("
    SELECT ss.*, p.name as plan_name, p.price as plan_price, 
           p.duration_days, p.features as plan_features
    FROM seller_subscriptions ss
    LEFT JOIN subscription_plans p ON ss.plan_id = p.id
    WHERE ss.seller_id = ? AND ss.is_active = 1
    ORDER BY ss.id DESC LIMIT 1
", [$seller_id]);

// Get payment history
$payments = $db->fetchAll("
    SELECT * FROM subscription_payments 
    WHERE seller_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
", [$seller_id]);

// Get subscription history
$subscription_history = $db->fetchAll("
    SELECT ss.*, p.name as plan_name
    FROM seller_subscriptions ss
    LEFT JOIN subscription_plans p ON ss.plan_id = p.id
    WHERE ss.seller_id = ? 
    ORDER BY ss.created_at DESC
    LIMIT 5
", [$seller_id]);

$days_remaining = 0;
$is_expired = true;

if ($subscription) {
    $current_date = new DateTime();
    $end_date = new DateTime($subscription['end_date']);
    $days_remaining = $current_date->diff($end_date)->days;
    $is_expired = $subscription['end_date'] < date('Y-m-d H:i:s');
}

$page_title = "Manage Subscription";
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Subscription</h1>
                    <p class="text-muted mb-0">View and manage your subscription details</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="upgrade.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-repeat me-1"></i> Change Plan
                    </a>
                    <a href="../dashboard.php" class="btn btn-sm btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if (!$subscription || $is_expired): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    You don't have an active subscription. 
                    <a href="upgrade.php" class="alert-link">Click here to subscribe</a> to continue selling.
                </div>
            <?php endif; ?>

            <?php if ($subscription && !$is_expired): ?>
                <!-- Current Subscription Card -->
                <div class="row g-4 mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-patch-check-fill text-success me-2"></i>
                                    Current Subscription
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <dl class="row mb-0">
                                            <dt class="col-5">Plan:</dt>
                                            <dd class="col-7">
                                                <strong><?php echo htmlspecialchars($subscription['plan_name'] ?? 'Free Trial'); ?></strong>
                                            </dd>
                                            
                                            <dt class="col-5">Status:</dt>
                                            <dd class="col-7">
                                                <span class="badge bg-success">Active</span>
                                            </dd>
                                            
                                            <dt class="col-5">Start Date:</dt>
                                            <dd class="col-7"><?php echo date('F j, Y', strtotime($subscription['start_date'])); ?></dd>
                                            
                                            <dt class="col-5">End Date:</dt>
                                            <dd class="col-7">
                                                <?php echo date('F j, Y', strtotime($subscription['end_date'])); ?>
                                                <?php if ($days_remaining <= 7): ?>
                                                    <span class="badge bg-warning ms-2">Expiring Soon</span>
                                                <?php endif; ?>
                                            </dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-6">
                                        <dl class="row mb-0">
                                            <dt class="col-5">Days Remaining:</dt>
                                            <dd class="col-7">
                                                <strong class="text-<?php echo $days_remaining <= 7 ? 'danger' : 'success'; ?>">
                                                    <?php echo $days_remaining; ?> days
                                                </strong>
                                            </dd>
                                            
                                            <dt class="col-5">Auto-Renew:</dt>
                                            <dd class="col-7">
                                                <?php if ($subscription['auto_renew']): ?>
                                                    <span class="badge bg-success">Enabled</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Disabled</span>
                                                <?php endif; ?>
                                            </dd>
                                            
                                            <dt class="col-5">Payment Status:</dt>
                                            <dd class="col-7">
                                                <span class="badge bg-<?php echo $subscription['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($subscription['payment_status']); ?>
                                                </span>
                                            </dd>
                                            
                                            <?php if ($subscription['amount_paid'] > 0): ?>
                                                <dt class="col-5">Amount Paid:</dt>
                                                <dd class="col-7">₦<?php echo number_format($subscription['amount_paid'], 2); ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-gear me-2 text-primary"></i>
                                    Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <form method="POST" class="d-grid">
                                        <button type="submit" name="toggle_autorenew" 
                                                class="btn btn-<?php echo $subscription['auto_renew'] ? 'secondary' : 'success'; ?>">
                                            <i class="bi bi-<?php echo $subscription['auto_renew'] ? 'toggle-off' : 'toggle-on'; ?> me-1"></i>
                                            <?php echo $subscription['auto_renew'] ? 'Disable' : 'Enable'; ?> Auto-Renew
                                        </button>
                                    </form>
                                    
                                    <a href="upgrade.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-repeat me-1"></i> Change / Upgrade Plan
                                    </a>
                                    
                                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                        <i class="bi bi-x-circle me-1"></i> Cancel Subscription
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plan Features -->
                <?php if ($subscription['plan_features']): 
                    $features = json_decode($subscription['plan_features'], true);
                ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-star me-2 text-warning"></i>
                            Plan Features
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($features as $feature): ?>
                                <div class="col-md-6 mb-2">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <?php echo htmlspecialchars($feature); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment History -->
                <?php if (!empty($payments)): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2 text-info"></i>
                            Payment History
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Transaction ID</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'] ?? $payment['created_at'])); ?></td>
                                        <td>₦<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo ucfirst($payment['payment_method'] ?? 'Card'); ?></td>
                                        <td><code><?php echo $payment['transaction_id']; ?></code></td>
                                        <td>
                                            <span class="badge bg-<?php echo $payment['payment_status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($payment['payment_status']); ?>
                                            </span>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Subscription History -->
                <?php if (!empty($subscription_history) && count($subscription_history) > 1): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-archive me-2 text-secondary"></i>
                            Subscription History
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Period</th>
                                        <th>Plan</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscription_history as $history): 
                                        if ($history['id'] == $subscription['id']) continue;
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($history['start_date'])); ?> - 
                                            <?php echo date('M j, Y', strtotime($history['end_date'])); ?>
                                         </td>
                                        <td><?php echo htmlspecialchars($history['plan_name'] ?? 'Free Trial'); ?></td>
                                        <td>₦<?php echo number_format($history['amount_paid'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php if ($history['cancelled_at']): ?>
                                                <span class="badge bg-secondary">Cancelled</span>
                                            <?php elseif ($history['end_date'] < date('Y-m-d H:i:s')): ?>
                                                <span class="badge bg-danger">Expired</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Cancel Subscription Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning!</strong> Cancelling your subscription will:
                        <ul class="mt-2 mb-0">
                            <li>Deactivate all your product listings</li>
                            <li>Prevent new orders from being placed</li>
                            <li>Remove your store from search results</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for cancellation</label>
                        <select name="cancellation_reason" class="form-select" required>
                            <option value="">Select a reason...</option>
                            <option value="Too expensive">Too expensive</option>
                            <option value="Not using the platform">Not using the platform</option>
                            <option value="Found better alternative">Found better alternative</option>
                            <option value="Technical issues">Technical issues</option>
                            <option value="Poor customer support">Poor customer support</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional comments (optional)</label>
                        <textarea name="cancellation_comments" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Subscription</button>
                    <button type="submit" name="cancel_subscription" class="btn btn-danger">Yes, Cancel Subscription</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>