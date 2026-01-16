<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get store settings
$settings = $db->fetchOne("SELECT * FROM seller_settings WHERE seller_id = ?", [$seller_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_data = [
        'notification_emails' => isset($_POST['notification_emails']) ? 1 : 0,
        'notification_sms' => isset($_POST['notification_sms']) ? 1 : 0,
        'low_stock_alerts' => isset($_POST['low_stock_alerts']) ? 1 : 0,
        'auto_confirm_orders' => isset($_POST['auto_confirm_orders']) ? 1 : 0,
        'vacation_mode' => isset($_POST['vacation_mode']) ? 1 : 0,
        'return_policy' => sanitizeInput($_POST['return_policy']),
        'shipping_policy' => sanitizeInput($_POST['shipping_policy'])
    ];

    try {
        if ($settings) {
            $db->update('seller_settings', $settings_data, 'seller_id = ?', [$seller_id]);
        } else {
            $db->insert('seller_settings', array_merge($settings_data, ['seller_id' => $seller_id]));
        }
        $success = 'Settings updated successfully!';
    } catch (Exception $e) {
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

$page_title = "Store Settings";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Store Settings</h1>
                <a href="profile.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-8">
                        <!-- Notifications -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Notification Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="notification_emails" 
                                           name="notification_emails" value="1" 
                                           <?php echo ($settings['notification_emails'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notification_emails">
                                        Email Notifications
                                    </label>
                                    <div class="form-text">Receive email notifications for new orders and important updates</div>
                                </div>

                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="notification_sms" 
                                           name="notification_sms" value="1" 
                                           <?php echo ($settings['notification_sms'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notification_sms">
                                        SMS Notifications
                                    </label>
                                    <div class="form-text">Receive SMS alerts for urgent matters</div>
                                </div>

                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="low_stock_alerts" 
                                           name="low_stock_alerts" value="1" 
                                           <?php echo ($settings['low_stock_alerts'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="low_stock_alerts">
                                        Low Stock Alerts
                                    </label>
                                    <div class="form-text">Get notified when products are running low</div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Settings -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Order Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="auto_confirm_orders" 
                                           name="auto_confirm_orders" value="1" 
                                           <?php echo ($settings['auto_confirm_orders'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_confirm_orders">
                                        Auto-Confirm Orders
                                    </label>
                                    <div class="form-text">Automatically confirm new orders (otherwise manual confirmation required)</div>
                                </div>

                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="vacation_mode" 
                                           name="vacation_mode" value="1" 
                                           <?php echo ($settings['vacation_mode'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="vacation_mode">
                                        Vacation Mode
                                    </label>
                                    <div class="form-text">Temporarily pause your store (customers can't place orders)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Store Policies -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Store Policies</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="return_policy" class="form-label">Return Policy</label>
                                    <textarea class="form-control" id="return_policy" name="return_policy" 
                                              rows="4"><?php echo $settings['return_policy'] ?? 'We accept returns within 7 days of delivery for damaged or incorrect items. Products must be in original condition.'; ?></textarea>
                                    <div class="form-text">Your return and refund policy for customers</div>
                                </div>

                                <div class="mb-3">
                                    <label for="shipping_policy" class="form-label">Shipping Policy</label>
                                    <textarea class="form-control" id="shipping_policy" name="shipping_policy" 
                                              rows="4"><?php echo $settings['shipping_policy'] ?? 'Orders are processed within 1-2 business days. Shipping takes 2-5 days within Nigeria. Free shipping on orders over ₦10,000.'; ?></textarea>
                                    <div class="form-text">Your shipping timeline and policy</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Save Settings
                                    </button>
                                    <a href="profile.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                </div>

                                <hr>

                                <div class="mt-3">
                                    <h6>Store Statistics</h6>
                                    <ul class="list-unstyled small">
                                        <?php
                                        $stats = $db->fetchOne("
                                            SELECT 
                                                COUNT(*) as total_products,
                                                (SELECT COUNT(*) FROM order_items WHERE seller_id = ?) as total_orders,
                                                (SELECT AVG(rating) FROM product_reviews WHERE seller_id = ?) as avg_rating
                                        ", [$seller_id, $seller_id]);
                                        ?>
                                        <li><i class="bi bi-box-seam text-primary"></i> Products: <?php echo $stats['total_products'] ?? 0; ?></li>
                                        <li><i class="bi bi-cart text-success"></i> Orders: <?php echo $stats['total_orders'] ?? 0; ?></li>
                                        <li><i class="bi bi-star text-warning"></i> Rating: <?php echo number_format($stats['avg_rating'] ?? 0, 1); ?>/5</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>