<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';

$db = new Database();

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get store settings
$settings = $db->fetchOne("SELECT * FROM seller_settings WHERE seller_id = ?", [$seller_id]);

// Get current business address (used for distance-based shipping calculations)
$seller_profile = $db->fetchOne("SELECT business_address_id FROM seller_profiles WHERE user_id = ?", [$seller_id]);
$business_address = null;
if (!empty($seller_profile['business_address_id'])) {
    $business_address = $db->fetchOne("SELECT * FROM user_addresses WHERE id = ?", [$seller_profile['business_address_id']]);
}

// Get current shipping rates (or defaults if not yet configured)
$shipping_rates = $db->fetchOne("SELECT * FROM seller_shipping_rates WHERE seller_id = ?", [$seller_id]);

// States list for the business address dropdown
$states = $db->fetchAll("SELECT id, name FROM states ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_data = [
        // Notification Settings and Order Settings are commented out in the UI
        // below for now - not writing these keys so existing values in the
        // database (if any) are left untouched rather than reset to 0/off
        // every time this form is saved.
        'return_policy' => sanitizeInput($_POST['return_policy']),
        'shipping_policy' => sanitizeInput($_POST['shipping_policy'])
    ];

    $business_state_id = $_POST['business_state_id'] ?? '';
    $business_lga_id = $_POST['business_lga_id'] ?? '';
    $business_city = sanitizeInput($_POST['business_city'] ?? '');
    $business_address_line = sanitizeInput($_POST['business_address_line'] ?? '');

    $same_lga_fee = $_POST['same_lga_fee'] ?? '';
    $same_state_fee = $_POST['same_state_fee'] ?? '';
    $interstate_fee = $_POST['interstate_fee'] ?? '';

    try {
        if ($settings) {
            $db->update('seller_settings', $settings_data, 'seller_id = ?', [$seller_id]);
        } else {
            $db->insert('seller_settings', array_merge($settings_data, ['seller_id' => $seller_id]));
        }

        // Save business address (needed to work out same-LGA / same-state /
        // interstate delivery for shipping fee calculation at checkout).
        if ($business_state_id && $business_lga_id && $business_city && $business_address_line) {
            $address_data = [
                'user_id' => $seller_id,
                'address_type' => 'business',
                'state_id' => $business_state_id,
                'lga_id' => $business_lga_id,
                'city' => $business_city,
                'address_line' => $business_address_line,
            ];

            if ($business_address) {
                $db->update('user_addresses', $address_data, 'id = ?', [$business_address['id']]);
                $new_address_id = $business_address['id'];
            } else {
                $new_address_id = $db->insert('user_addresses', $address_data);
                $db->update('seller_profiles', ['business_address_id' => $new_address_id], 'user_id = ?', [$seller_id]);
            }
        }

        // Save shipping rates
        if ($same_lga_fee !== '' && $same_state_fee !== '' && $interstate_fee !== '') {
            $rates_data = [
                'same_lga_fee' => (float)$same_lga_fee,
                'same_state_fee' => (float)$same_state_fee,
                'interstate_fee' => (float)$interstate_fee,
            ];

            if ($shipping_rates) {
                $db->update('seller_shipping_rates', $rates_data, 'seller_id = ?', [$seller_id]);
            } else {
                $db->insert('seller_shipping_rates', array_merge($rates_data, ['seller_id' => $seller_id]));
            }
        }

        $success = 'Settings updated successfully!';

        // Refresh the data shown on the page after saving
        $seller_profile = $db->fetchOne("SELECT business_address_id FROM seller_profiles WHERE user_id = ?", [$seller_id]);
        if (!empty($seller_profile['business_address_id'])) {
            $business_address = $db->fetchOne("SELECT * FROM user_addresses WHERE id = ?", [$seller_profile['business_address_id']]);
        }
        $shipping_rates = $db->fetchOne("SELECT * FROM seller_shipping_rates WHERE seller_id = ?", [$seller_id]);

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
                        <!-- Business Location -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Business Location</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">
                                    Used to work out delivery fees for buyers based on distance - see Shipping Rates below.
                                </p>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="business_state_id" class="form-label">State</label>
                                        <select class="form-select" id="business_state_id" name="business_state_id">
                                            <option value="">Select State</option>
                                            <?php foreach ($states as $state): ?>
                                                <option value="<?php echo $state['id']; ?>"
                                                    <?php echo (!empty($business_address['state_id']) && $business_address['state_id'] == $state['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($state['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="business_lga_id" class="form-label">LGA</label>
                                        <select class="form-select" id="business_lga_id" name="business_lga_id"
                                                <?php echo empty($business_address['state_id']) ? 'disabled' : ''; ?>>
                                            <option value="">Select LGA</option>
                                            <?php if (!empty($business_address['lga_id'])):
                                                $current_lga = $db->fetchOne("SELECT id, name FROM lgas WHERE id = ?", [$business_address['lga_id']]); ?>
                                                <option value="<?php echo $current_lga['id']; ?>" selected>
                                                    <?php echo htmlspecialchars($current_lga['name']); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="business_city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="business_city" name="business_city"
                                               value="<?php echo htmlspecialchars($business_address['city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="business_address_line" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="business_address_line" name="business_address_line"
                                               value="<?php echo htmlspecialchars($business_address['address_line'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Rates -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Shipping Rates</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">
                                    Set your own delivery fee for each distance tier - buyers are charged the fee that
                                    matches where they're having their order delivered to, compared to your business location above.
                                </p>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="same_lga_fee" class="form-label">Same LGA</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" step="0.01" min="0" class="form-control" id="same_lga_fee" name="same_lga_fee"
                                                   value="<?php echo htmlspecialchars($shipping_rates['same_lga_fee'] ?? '500.00'); ?>" required>
                                        </div>
                                        <div class="form-text">Delivery within your own LGA</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="same_state_fee" class="form-label">Same State</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" step="0.01" min="0" class="form-control" id="same_state_fee" name="same_state_fee"
                                                   value="<?php echo htmlspecialchars($shipping_rates['same_state_fee'] ?? '1000.00'); ?>" required>
                                        </div>
                                        <div class="form-text">Elsewhere in your state</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="interstate_fee" class="form-label">Interstate</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₦</span>
                                            <input type="number" step="0.01" min="0" class="form-control" id="interstate_fee" name="interstate_fee"
                                                   value="<?php echo htmlspecialchars($shipping_rates['interstate_fee'] ?? '2000.00'); ?>" required>
                                        </div>
                                        <div class="form-text">Delivery to a different state</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Settings and Order Settings - temporarily disabled

                        Notifications:
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

                        Order Settings:
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

                        end of disabled section -->

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
                                                (SELECT AVG(r.rating) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.seller_id = ?) as avg_rating
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

<script>
document.getElementById('business_state_id').addEventListener('change', function () {
    const stateId = this.value;
    const lgaSelect = document.getElementById('business_lga_id');

    if (!stateId) {
        lgaSelect.disabled = true;
        lgaSelect.innerHTML = '<option value="">Select LGA</option>';
        return;
    }

    fetch(`../../api/locations/lgas.php?state_id=${stateId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success && result.lgas) {
                lgaSelect.innerHTML = '<option value="">Select LGA</option>';
                result.lgas.forEach(lga => {
                    lgaSelect.innerHTML += `<option value="${lga.id}">${lga.name}</option>`;
                });
                lgaSelect.disabled = false;
            } else {
                lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
            }
        })
        .catch(() => {
            lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
        });
});
</script>

<?php include '../../includes/footer.php'; ?>