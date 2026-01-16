<?php
ob_start();
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';

requireAdmin();

$db = new Database();

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    setFlashMessage('Order ID required', 'error');
    header('Location: manage-orders.php');
    exit;
}

// Get order details
$order = $db->fetchOne("
    SELECT o.*, u.first_name, u.last_name, u.email, u.phone,
           os.shipping_name, os.shipping_phone, os.address_line, os.landmark,
           os.tracking_number, os.logistics_partner, os.estimated_delivery,
           s.name as state_name, l.name as lga_name, c.name as city_name
    FROM orders o 
    JOIN users u ON o.buyer_id = u.id 
    LEFT JOIN order_shipping_details os ON o.id = os.order_id 
    LEFT JOIN states s ON os.state_id = s.id 
    LEFT JOIN lgas l ON os.lga_id = l.id 
    LEFT JOIN cities c ON os.city_id = c.id 
    WHERE o.id = ?
", [$order_id]);

if (!$order) {
    setFlashMessage('Order not found', 'error');
    header('Location: manage-orders.php');
    exit;
}

// Get order items
$order_items = $db->fetchAll("
    SELECT oi.*, p.name as product_name, p.unit as product_unit,
           sp.business_name, sp.business_reg_number
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN seller_profiles sp ON oi.seller_id = sp.user_id 
    WHERE oi.order_id = ?
", [$order_id]);

// Get payment details
$payment = $db->fetchOne("SELECT * FROM payments WHERE order_id = ?", [$order_id]);

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    
    $update_data = ['status' => $new_status];
    
    if ($new_status === 'delivered') {
        $update_data['delivered_at'] = date('Y-m-d H:i:s');
    } elseif ($new_status === 'paid' || $new_status === 'confirmed') {
        $update_data['paid_at'] = date('Y-m-d H:i:s');
        $update_data['payment_status'] = 'paid';
    }
    
    $db->update('orders', $update_data, 'id = ?', [$order_id]);
    
    // Log the status change
    $admin_id = getCurrentUserId();
    $db->insert('order_status_history', [
        'order_id' => $order_id,
        'status' => $new_status,
        'changed_by' => $admin_id,
        'notes' => $_POST['notes'] ?? 'Status updated by admin'
    ]);
    
    setFlashMessage('Order status updated successfully', 'success');
    header("Location: order-details.php?id=$order_id");
    exit;
}

// Handle tracking update
if (isset($_POST['update_tracking'])) {
    $tracking_number = $_POST['tracking_number'] ?? '';
    $logistics_partner = $_POST['logistics_partner'] ?? '';
    $estimated_delivery = $_POST['estimated_delivery'] ?? '';
    
    $existing_shipping = $db->fetchOne("SELECT id FROM order_shipping_details WHERE order_id = ?", [$order_id]);
    
    if ($existing_shipping) {
        $db->update('order_shipping_details', [
            'tracking_number' => $tracking_number,
            'logistics_partner' => $logistics_partner,
            'estimated_delivery' => $estimated_delivery ? date('Y-m-d', strtotime($estimated_delivery)) : null
        ], 'order_id = ?', [$order_id]);
    }
    
    setFlashMessage('Tracking information updated', 'success');
    header("Location: order-details.php?id=$order_id");
    exit;
}

$page_title = "Order Details - " . $order['order_number'];
$page_css = 'dashboard.css';

// Now include header AFTER setting variables
require_once '../../includes/header.php';
ob_flush();
?>

<div class="container-fluid">
    <div class="row">
        <?php 
        $sidebar_path = '../includes/sidebar.php';
        if (file_exists($sidebar_path)) {
            include $sidebar_path;
        }
        ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile page header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-2" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="flex-grow-1">
                        <h1 class="h5 mb-0">Order Details</h1>
                        <small class="text-muted d-block">#<?php echo htmlspecialchars($order['order_number']); ?></small>
                    </div>
                    <a href="manage-orders.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                    <p class="text-muted mb-0">Order details and management</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="manage-orders.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Orders
                        </a>
                        <button class="btn btn-outline-primary" onclick="printOrder()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php 
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                ?>
            <?php endif; ?>

            <!-- Order Summary Cards -->
            <div class="row g-3 mb-4">
                <!-- Order Status Card -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Status</h6>
                                    <h4 class="card-title mb-0">
                                        <span class="badge bg-<?php 
                                            echo $order['status'] === 'delivered' ? 'success' : 
                                                 ($order['status'] === 'pending' ? 'warning' : 
                                                 ($order['status'] === 'cancelled' ? 'danger' : 'info')); 
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </h4>
                                </div>
                                <div class="text-<?php 
                                    echo $order['status'] === 'delivered' ? 'success' : 
                                         ($order['status'] === 'pending' ? 'warning' : 
                                         ($order['status'] === 'cancelled' ? 'danger' : 'info')); 
                                ?>">
                                    <i class="bi bi-bag fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Amount Card -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Amount</h6>
                                    <h4 class="card-title mb-0 text-success">
                                        ₦<?php echo number_format($order['total_amount'], 2); ?>
                                    </h4>
                                </div>
                                <div class="text-success">
                                    <i class="bi bi-cash-stack fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Items Count Card -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Items</h6>
                                    <h4 class="card-title mb-0"><?php echo count($order_items); ?></h4>
                                    <small class="text-muted">Products</small>
                                </div>
                                <div class="text-primary">
                                    <i class="bi bi-box-seam fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Status Card -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Payment</h6>
                                    <h4 class="card-title mb-0">
                                        <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'refunded' ? 'info' : 'warning'); ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </h4>
                                </div>
                                <div class="text-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                    <i class="bi bi-credit-card fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row">
                <!-- Left Column: Order Items -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-cart me-2 text-primary"></i>
                                Order Items (<?php echo count($order_items); ?>)
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <!-- Desktop Table View -->
                            <div class="d-none d-md-block">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="40">#</th>
                                                <th>Product</th>
                                                <th width="120">Seller</th>
                                                <th width="100">Price</th>
                                                <th width="100">Quantity</th>
                                                <th width="100">Total</th>
                                                <th width="100">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $item_counter = 1;
                                            foreach ($order_items as $item): 
                                                $item_status_colors = [
                                                    'delivered' => 'success',
                                                    'pending' => 'warning',
                                                    'cancelled' => 'danger',
                                                    'confirmed' => 'info',
                                                    'processing' => 'primary',
                                                    'shipped' => 'info'
                                                ];
                                            ?>
                                            <tr>
                                                <td><?php echo $item_counter++; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                    <?php if (isset($item['grade']) && $item['grade']): ?>
                                                        <br><small class="badge bg-secondary"><?php echo htmlspecialchars($item['grade']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (isset($item['is_organic']) && $item['is_organic']): ?>
                                                        <span class="badge bg-success ms-1">Organic</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-2">
                                                            <i class="bi bi-shop text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <small><?php echo htmlspecialchars($item['business_name']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong>₦<?php echo number_format($item['unit_price'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo number_format($item['quantity'], 2); ?> 
                                                    <?php echo htmlspecialchars($item['unit'] ?? ($item['product_unit'] ?? '')); ?>
                                                </td>
                                                <td>
                                                    <strong class="text-success">₦<?php echo number_format($item['item_total'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo isset($item['status']) && isset($item_status_colors[$item['status']]) ? $item_status_colors[$item['status']] : 'info'; 
                                                    ?>">
                                                        <?php echo isset($item['status']) ? ucfirst($item['status']) : 'Processing'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Mobile List View -->
                            <div class="d-md-none">
                                <div class="list-group list-group-flush">
                                    <?php 
                                    $item_counter = 1;
                                    foreach ($order_items as $item): 
                                    ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-2">
                                                    <i class="bi bi-bag text-primary"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">Item #<?php echo $item_counter++; ?></small>
                                                </div>
                                            </div>
                                            <strong class="text-success">₦<?php echo number_format($item['item_total'], 2); ?></strong>
                                        </div>
                                        
                                        <div class="row mb-2">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Price</small>
                                                <small>₦<?php echo number_format($item['unit_price'], 2); ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Quantity</small>
                                                <small>
                                                    <?php echo number_format($item['quantity'], 2); ?> 
                                                    <?php echo htmlspecialchars($item['unit'] ?? ($item['product_unit'] ?? '')); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted d-block">Seller</small>
                                                <small><?php echo htmlspecialchars($item['business_name']); ?></small>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo isset($item['status']) && isset($item_status_colors[$item['status']]) ? $item_status_colors[$item['status']] : 'info'; 
                                            ?>">
                                                <?php echo isset($item['status']) ? ucfirst($item['status']) : 'Processing'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Totals -->
                        <div class="card-footer bg-white border-top">
                            <div class="row">
                                <div class="col-md-6 offset-md-6">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Subtotal:</span>
                                        <span class="text-dark">₦<?php echo number_format($order['subtotal_amount'], 2); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Shipping:</span>
                                        <span class="text-dark">₦<?php echo number_format($order['shipping_amount'], 2); ?></span>
                                    </div>
                                    <?php if (isset($order['tax_amount']) && $order['tax_amount'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Tax:</span>
                                        <span class="text-dark">₦<?php echo number_format($order['tax_amount'], 2); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Discount:</span>
                                        <span class="text-danger">-₦<?php echo number_format($order['discount_amount'], 2); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total Amount:</strong>
                                        <strong class="text-success">₦<?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Order Actions & Info -->
                <div class="col-lg-4">
                    <!-- Status Update Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-pencil me-2 text-primary"></i>
                                Update Order Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Current Status</label>
                                    <div class="alert alert-<?php 
                                        echo $order['status'] === 'delivered' ? 'success' : 
                                             ($order['status'] === 'pending' ? 'warning' : 
                                             ($order['status'] === 'cancelled' ? 'danger' : 'info')); 
                                    ?> mb-0 py-2">
                                        <strong><?php echo ucfirst($order['status']); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">New Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Admin Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Add notes about status change..."></textarea>
                                </div>
                                
                                <button name="update_status" class="btn btn-primary w-100">
                                    <i class="bi bi-check-circle me-1"></i> Update Status
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tracking Information Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-truck me-2 text-info"></i>
                                Tracking Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="tracking_number" class="form-label">Tracking Number</label>
                                    <input type="text" class="form-control" id="tracking_number" name="tracking_number" 
                                           value="<?php echo isset($order['tracking_number']) ? htmlspecialchars($order['tracking_number']) : ''; ?>" 
                                           placeholder="Enter tracking number">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="logistics_partner" class="form-label">Logistics Partner</label>
                                    <select class="form-select" id="logistics_partner" name="logistics_partner">
                                        <option value="">Select Partner</option>
                                        <option value="DHL" <?php echo isset($order['logistics_partner']) && $order['logistics_partner'] === 'DHL' ? 'selected' : ''; ?>>DHL</option>
                                        <option value="FedEx" <?php echo isset($order['logistics_partner']) && $order['logistics_partner'] === 'FedEx' ? 'selected' : ''; ?>>FedEx</option>
                                        <option value="UPS" <?php echo isset($order['logistics_partner']) && $order['logistics_partner'] === 'UPS' ? 'selected' : ''; ?>>UPS</option>
                                        <option value="GIG Logistics" <?php echo isset($order['logistics_partner']) && $order['logistics_partner'] === 'GIG Logistics' ? 'selected' : ''; ?>>GIG Logistics</option>
                                        <option value="Red Star" <?php echo isset($order['logistics_partner']) && $order['logistics_partner'] === 'Red Star' ? 'selected' : ''; ?>>Red Star</option>
                                        <option value="ABC Transport" <?php echo isset($order['logistics_partner']) && $order['logistics_partner'] === 'ABC Transport' ? 'selected' : ''; ?>>ABC Transport</option>
                                        <option value="AgriMarket Logistics" <?php echo isset($order['logistics_partner']) && $order['logistics_partner'] === 'AgriMarket Logistics' ? 'selected' : ''; ?>>AgriMarket Logistics</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="estimated_delivery" class="form-label">Estimated Delivery</label>
                                    <input type="date" class="form-control" id="estimated_delivery" name="estimated_delivery" 
                                           value="<?php echo isset($order['estimated_delivery']) ? date('Y-m-d', strtotime($order['estimated_delivery'])) : ''; ?>">
                                </div>
                                
                                <button name="update_tracking" class="btn btn-info w-100">
                                    <i class="bi bi-truck me-1"></i> Update Tracking
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Customer Information Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person me-2 text-primary"></i>
                                Customer Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                    <i class="bi bi-person-circle text-primary fs-4"></i>
                                </div>
                                <div>
                                    <strong class="d-block"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                    <small class="text-muted">Order placed: <?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1">Email</small>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-envelope text-muted me-2"></i>
                                    <span><?php echo htmlspecialchars($order['email']); ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-0">
                                <small class="text-muted d-block mb-1">Phone</small>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-telephone text-muted me-2"></i>
                                    <span><?php echo htmlspecialchars($order['phone']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Information Card -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-geo-alt me-2 text-success"></i>
                                Shipping Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($order['shipping_name']): ?>
                                <div class="mb-3">
                                    <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong>
                                    <small class="text-muted d-block">Recipient</small>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted d-block mb-1">Phone</small>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-telephone text-muted me-2"></i>
                                        <span><?php echo htmlspecialchars($order['shipping_phone']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted d-block mb-1">Address</small>
                                    <div class="d-flex">
                                        <i class="bi bi-geo-alt text-muted me-2 mt-1"></i>
                                        <div>
                                            <div><?php echo htmlspecialchars($order['address_line']); ?></div>
                                            <?php if ($order['landmark']): ?>
                                                <small class="text-muted">Landmark: <?php echo htmlspecialchars($order['landmark']); ?></small>
                                            <?php endif; ?>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($order['city_name'] . ', ' . $order['lga_name'] . ', ' . $order['state_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (isset($order['tracking_number']) && $order['tracking_number']): ?>
                                    <hr>
                                    <div class="mb-2">
                                        <small class="text-muted d-block mb-1">Tracking Number</small>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-upc-scan text-info me-2"></i>
                                            <code><?php echo htmlspecialchars($order['tracking_number']); ?></code>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($order['logistics_partner']) && $order['logistics_partner']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted d-block mb-1">Logistics Partner</small>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-truck text-muted me-2"></i>
                                            <span><?php echo htmlspecialchars($order['logistics_partner']); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($order['estimated_delivery']) && $order['estimated_delivery']): ?>
                                    <div class="mb-0">
                                        <small class="text-muted d-block mb-1">Estimated Delivery</small>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar-check text-muted me-2"></i>
                                            <span><?php echo date('F j, Y', strtotime($order['estimated_delivery'])); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-3 text-muted">
                                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                                    No shipping information available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (window.dashboardSidebar) {
                window.dashboardSidebar.toggle();
            }
        });
    }
    
    // Auto-focus tracking number if empty
    const trackingInput = document.getElementById('tracking_number');
    if (trackingInput && !trackingInput.value) {
        trackingInput.focus();
    }
    
    // Status change confirmation
    const statusForm = document.querySelector('form[action*="update_status"]');
    if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
            const statusSelect = document.getElementById('status');
            if (statusSelect.value === 'cancelled') {
                if (!confirm('Are you sure you want to cancel this order?')) {
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        });
    }
});

function printOrder() {
    window.print();
}

// Add CSS for mobile table
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .dashboard-card {
        animation: fadeIn 0.3s ease;
    }
    
    /* Mobile List View */
    @media (max-width: 767.98px) {
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .list-group-item {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
        }
        
        /* Touch-friendly buttons */
        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Form optimization */
        .form-control, .form-select {
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        /* Card spacing */
        .card {
            margin-bottom: 1rem;
        }
        
        /* Badge sizing */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Amount highlighting */
        .text-success {
            font-size: 1.1rem;
            font-weight: 600;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .dashboard-card:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }
        
        .list-group-item:hover {
            background-color: rgba(0,0,0,0.02);
        }
    }
    
    /* Print styles */
    @media print {
        .mobile-page-header,
        .card-header,
        .btn,
        form {
            display: none !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        
        .card-body {
            padding: 0 !important;
        }
    }
    
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>