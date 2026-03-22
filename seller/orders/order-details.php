<?php
// seller/orders/order-details.php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? 0;

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get order details
$order = $db->fetchOne("
    SELECT o.*, 
           u.first_name, u.last_name, u.email, u.phone,
           u.profile_image as customer_avatar,
           os.shipping_name, os.shipping_phone, os.address_line, os.landmark,
           os.tracking_number, os.logistics_partner, os.estimated_delivery, os.shipping_instructions,
           s.name as state_name, l.name as lga_name, c.name as city_name
    FROM orders o
    LEFT JOIN order_shipping_details os ON o.id = os.order_id
    LEFT JOIN states s ON os.state_id = s.id
    LEFT JOIN lgas l ON os.lga_id = l.id
    LEFT JOIN cities c ON os.city_id = c.id
    JOIN users u ON o.buyer_id = u.id
    WHERE o.id = ? AND EXISTS (
        SELECT 1 FROM order_items oi 
        WHERE oi.order_id = o.id AND oi.seller_id = ?
    )
", [$order_id, $seller_id]);

if (!$order) {
    setFlashMessage('Order not found or access denied', 'error');
    header('Location: manage-orders.php');
    exit;
}

// Get order items for this seller with agricultural details
$order_items = $db->fetchAll("
    SELECT oi.*, 
           p.name as product_name, 
           p.unit,
           p.product_type,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image,
           ag.grade,
           ag.is_organic,
           ag.harvest_date,
           ag.farming_method,
           ag.organic_certification_number
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_agricultural_details ag ON p.id = ag.product_id
    WHERE oi.order_id = ? AND oi.seller_id = ?
    ORDER BY oi.id
", [$order_id, $seller_id]);

// Calculate seller's totals
$seller_subtotal = 0;
$seller_commission_total = 0;
$commission_rate = 5;

foreach ($order_items as $item) {
    $seller_subtotal += $item['item_total'];
    $seller_commission_total += ($item['item_total'] * $commission_rate / 100);
}
$seller_net_amount = $seller_subtotal - $seller_commission_total;

// Get order status history
$status_history = $db->fetchAll("
    SELECT oish.*, u.first_name, u.last_name
    FROM order_item_status_history oish
    JOIN users u ON oish.changed_by = u.id
    WHERE oish.order_item_id IN (
        SELECT id FROM order_items 
        WHERE order_id = ? AND seller_id = ?
    )
    ORDER BY oish.created_at DESC
", [$order_id, $seller_id]);

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND DATE(o.created_at) = CURDATE() ", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

// Define available statuses with their properties
$available_statuses = [
    'pending' => ['label' => 'Order Placed', 'icon' => 'bi-bag-check', 'color' => 'secondary', 'can_move_to' => ['confirmed', 'cancelled']],
    'confirmed' => ['label' => 'Confirmed', 'icon' => 'bi-check-circle', 'color' => 'info', 'can_move_to' => ['processing', 'cancelled']],
    'processing' => ['label' => 'Processing', 'icon' => 'bi-gear', 'color' => 'primary', 'can_move_to' => ['shipped', 'cancelled']],
    'shipped' => ['label' => 'Shipped', 'icon' => 'bi-truck', 'color' => 'info', 'can_move_to' => ['delivered']],
    'delivered' => ['label' => 'Delivered', 'icon' => 'bi-house-check', 'color' => 'success', 'can_move_to' => []],
    'cancelled' => ['label' => 'Cancelled', 'icon' => 'bi-x-circle', 'color' => 'danger', 'can_move_to' => []]
];

// Get the current status order (you can store this in database for custom workflows)
$status_flow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
$current_status_index = array_search($order['status'], $status_flow);
if ($current_status_index === false) $current_status_index = 0;

$page_title = "Order #" . $order['order_number'];
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-3" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0">Order Details</h1>
                        <small class="text-muted">#<?php echo htmlspecialchars($order['order_number']); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <div class="d-flex align-items-center">
                        <h1 class="h2 mb-1">Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                        <span class="ms-3"><?php echo getOrderStatusBadge($order['status']); ?></span>
                    </div>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar me-1"></i> Placed on <?php echo formatDate($order['created_at'], 'F j, Y \a\t g:i A'); ?>
                    </p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="manage-orders.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i>
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#customizeFlowModal">
                        <i class="bi bi-pencil-square me-1"></i> Customize Flow
                    </button>
                </div>
            </div>

            <!-- Interactive Order Progress Timeline -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted mb-0">Order Progress</h6>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" id="resetFlowBtn" title="Reset to default flow">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Draggable Progress Tracker -->
                            <div class="progress-tracker draggable-tracker" id="progressTracker">
                                <div class="d-flex justify-content-between" id="trackerSteps">
                                    <?php foreach ($status_flow as $index => $status): 
                                        $is_completed = $index <= $current_status_index;
                                        $is_current = $status === $order['status'];
                                        $status_info = $available_statuses[$status];
                                    ?>
                                        <div class="progress-step draggable-step text-center <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>" 
                                             data-status="<?php echo $status; ?>"
                                             data-index="<?php echo $index; ?>"
                                             style="flex: 1; cursor: move;">
                                            <div class="step-icon <?php echo $is_completed ? 'bg-success text-white' : 'bg-light'; ?>"
                                                 data-bs-toggle="tooltip" 
                                                 title="Click to update status">
                                                <i class="bi <?php echo $status_info['icon']; ?>"></i>
                                            </div>
                                            <div class="step-label mt-2">
                                                <strong><?php echo $status_info['label']; ?></strong>
                                                <?php if ($status === 'pending'): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo formatDate($order['created_at'], 'M d, h:i A'); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!$is_completed && !$is_current && $status !== 'cancelled'): ?>
                                                <button class="btn btn-sm btn-outline-primary mt-2 update-status-btn" 
                                                        data-status="<?php echo $status; ?>"
                                                        style="font-size: 0.7rem;">
                                                    <i class="bi bi-arrow-right"></i> Move Here
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($index < count($status_flow) - 1): ?>
                                            <div class="progress-connector flex-grow-1">
                                                <div class="connector-line <?php echo $index < $current_status_index ? 'bg-success' : 'bg-light'; ?>" style="height: 2px; margin-top: 25px;"></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Status Quick Actions -->
                            <div class="mt-4">
                                <label class="text-muted small mb-2">Quick Status Update:</label>
                                <div class="btn-group flex-wrap">
                                    <?php 
                                    $current_status = $order['status'];
                                    $allowed_moves = $available_statuses[$current_status]['can_move_to'] ?? [];
                                    foreach ($allowed_moves as $next_status):
                                        $next_info = $available_statuses[$next_status];
                                    ?>
                                        <button type="button" 
                                                class="btn btn-<?php echo $next_info['color']; ?> btn-sm mb-1"
                                                onclick="updateOrderStatus('<?php echo $next_status; ?>')">
                                            <i class="bi <?php echo $next_info['icon']; ?> me-1"></i>
                                            Mark as <?php echo $next_info['label']; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-md-end">
                                <h6 class="text-muted mb-2">Payment Status</h6>
                                <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?> fs-6 p-2">
                                    <i class="bi bi-<?php echo $order['payment_status'] === 'paid' ? 'check-circle' : 'clock'; ?> me-1"></i>
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                                <?php if ($order['paid_at']): ?>
                                    <br>
                                    <small class="text-muted"><?php echo formatDate($order['paid_at'], 'M d, Y'); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rest of the page content remains the same... -->
            <div class="row g-4">
                <!-- Left Column - Order Items -->
                <div class="col-lg-8">
                    <!-- Order Items Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-box-seam me-2 text-primary"></i>
                                Your Items (<?php echo count($order_items); ?>)
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-center">Price</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Action</th>
                                        </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($item['product_image']): ?>
                                                            <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $item['product_image']; ?>" 
                                                                 class="rounded me-3" 
                                                                 style="width: 60px; height: 60px; object-fit: cover;"
                                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                                 style="width: 60px; height: 60px;">
                                                                <i class="bi bi-box text-muted fs-2"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">
                                                                Type: <?php echo ucfirst($item['product_type'] ?? 'N/A'); ?>
                                                                <?php if ($item['grade']): ?>
                                                                    • Grade: <?php echo htmlspecialchars($item['grade']); ?>
                                                                <?php endif; ?>
                                                                <?php if ($item['is_organic']): ?>
                                                                    • <span class="text-success">Organic</span>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <strong>₦<?php echo number_format($item['unit_price'], 2); ?></strong>
                                                    <br>
                                                    <small class="text-muted">per <?php echo $item['unit']; ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold"><?php echo number_format($item['quantity']); ?></span>
                                                    <small class="text-muted"> <?php echo $item['unit']; ?></small>
                                                </td>
                                                <td class="text-end">
                                                    <strong class="text-success">₦<?php echo number_format($item['item_total'], 2); ?></strong>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'confirmed' => 'info',
                                                        'processing' => 'primary',
                                                        'shipped' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $color = $status_colors[$item['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="updateItemStatus(<?php echo $item['id']; ?>, '<?php echo $item['status']; ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Status History -->
                    <?php if (!empty($status_history)): ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2 text-info"></i>
                                Status History
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline-vertical">
                                <?php foreach ($status_history as $history): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-<?php 
                                            echo $history['status'] == 'delivered' ? 'success' : 
                                                ($history['status'] == 'shipped' ? 'primary' : 
                                                ($history['status'] == 'processing' ? 'info' : 
                                                ($history['status'] == 'confirmed' ? 'info' : 
                                                ($history['status'] == 'cancelled' ? 'danger' : 'warning')))); 
                                        ?>"></div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between">
                                                <strong><?php echo ucfirst($history['status']); ?></strong>
                                                <small class="text-muted"><?php echo formatDate($history['created_at'], 'M d, Y h:i A'); ?></small>
                                            </div>
                                            <?php if ($history['notes']): ?>
                                                <p class="mb-0 small text-muted"><?php echo htmlspecialchars($history['notes']); ?></p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                By: <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column - Summary & Customer Info (same as before) -->
                <div class="col-lg-4">
                    <!-- Order Summary Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-calculator me-2 text-primary"></i>
                                Order Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="summary-item d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-bold">₦<?php echo number_format($seller_subtotal, 2); ?></span>
                            </div>
                            <div class="summary-item d-flex justify-content-between mb-2">
                                <span class="text-muted">Commission (<?php echo $commission_rate; ?>%):</span>
                                <span class="text-danger">-₦<?php echo number_format($seller_commission_total, 2); ?></span>
                            </div>
                            <hr>
                            <div class="summary-item d-flex justify-content-between mb-3">
                                <span class="h6 mb-0">Your Earnings:</span>
                                <span class="h5 mb-0 text-success">₦<?php echo number_format($seller_net_amount, 2); ?></span>
                            </div>
                            
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                <small>Payouts are processed weekly after order delivery.</small>
                            </div>
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
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($order['customer_avatar']): ?>
                                    <img src="<?php echo htmlspecialchars($order['customer_avatar']); ?>" 
                                         class="rounded-circle me-3" 
                                         style="width: 50px; height: 50px; object-fit: cover;"
                                         alt="<?php echo htmlspecialchars($order['first_name']); ?>">
                                <?php else: ?>
                                    <div class="bg-light rounded-circle me-3 d-flex align-items-center justify-content-center" 
                                         style="width: 50px; height: 50px;">
                                        <i class="bi bi-person text-muted fs-3"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">Customer</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="text-muted small mb-1">Email Address</label>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-envelope me-2"></i>
                                    <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>">
                                        <?php echo htmlspecialchars($order['email']); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="text-muted small mb-1">Phone Number</label>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-telephone me-2"></i>
                                    <a href="tel:<?php echo htmlspecialchars($order['phone']); ?>">
                                        <?php echo htmlspecialchars($order['phone']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Information Card -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-truck me-2 text-primary"></i>
                                Shipping Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($order['shipping_name']): ?>
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Recipient</label>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person me-2"></i>
                                        <span><?php echo htmlspecialchars($order['shipping_name']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Contact Phone</label>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-telephone me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($order['shipping_phone']); ?>">
                                            <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-muted small mb-1">Delivery Address</label>
                                    <div class="d-flex">
                                        <i class="bi bi-geo-alt me-2"></i>
                                        <div>
                                            <?php echo nl2br(htmlspecialchars($order['address_line'])); ?>
                                            <?php if ($order['landmark']): ?>
                                                <br><small class="text-muted">Landmark: <?php echo htmlspecialchars($order['landmark']); ?></small>
                                            <?php endif; ?>
                                            <br><small><?php echo htmlspecialchars($order['city_name'] . ', ' . $order['state_name']); ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($order['tracking_number']): ?>
                                    <div class="mb-3">
                                        <label class="text-muted small mb-1">Tracking Information</label>
                                        <div class="bg-light p-2 rounded">
                                            <code class="d-block"><?php echo htmlspecialchars($order['tracking_number']); ?></code>
                                            <?php if ($order['logistics_partner']): ?>
                                                <small class="text-muted">Carrier: <?php echo htmlspecialchars($order['logistics_partner']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Shipping information not available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Customize Flow Modal -->
<div class="modal fade" id="customizeFlowModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customize Order Workflow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Drag and drop to reorder the status flow. This will affect how you update orders.</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Tip:</strong> You can create a custom workflow for different order types.
                </div>
                
                <div class="workflow-editor" id="workflowEditor">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Available Statuses</h6>
                            <div class="available-statuses list-group" id="availableStatuses">
                                <?php foreach ($available_statuses as $key => $info): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center" data-status="<?php echo $key; ?>">
                                        <div>
                                            <i class="bi <?php echo $info['icon']; ?> me-2 text-<?php echo $info['color']; ?>"></i>
                                            <?php echo $info['label']; ?>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary add-to-flow" data-status="<?php echo $key; ?>">
                                            <i class="bi bi-plus"></i> Add
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Current Flow <span class="badge bg-primary" id="flowCount"><?php echo count($status_flow); ?></span></h6>
                            <div class="current-flow list-group" id="currentFlow">
                                <?php foreach ($status_flow as $index => $status): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center" data-status="<?php echo $status; ?>">
                                        <div>
                                            <i class="bi bi-<?php echo $available_statuses[$status]['icon']; ?> me-2 text-<?php echo $available_statuses[$status]['color']; ?>"></i>
                                            <?php echo $available_statuses[$status]['label']; ?>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary move-up" data-index="<?php echo $index; ?>" <?php echo $index == 0 ? 'disabled' : ''; ?>>
                                                <i class="bi bi-arrow-up"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary move-down" data-index="<?php echo $index; ?>" <?php echo $index == count($status_flow) - 1 ? 'disabled' : ''; ?>>
                                                <i class="bi bi-arrow-down"></i>
                                            </button>
                                            <button class="btn btn-outline-danger remove-from-flow" data-status="<?php echo $status; ?>">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFlowBtn">
                    <i class="bi bi-save me-1"></i> Save Workflow
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Add a note (optional)</label>
                    <textarea class="form-control" id="statusNote" rows="3" placeholder="Add any notes about this status change..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusUpdate">
                    <i class="bi bi-check-circle me-1"></i> Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let pendingStatus = null;
let pendingItemId = null;
let statusModal = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modals
    statusModal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Make steps draggable
    initializeDraggable();
    
    // Reset flow button
    document.getElementById('resetFlowBtn')?.addEventListener('click', function() {
        if (confirm('Reset to default workflow?')) {
            resetWorkflow();
        }
    });
    
    // Update status buttons
    document.querySelectorAll('.update-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const status = this.dataset.status;
            updateOrderStatus(status);
        });
    });
    
    // Save flow button
    document.getElementById('saveFlowBtn')?.addEventListener('click', function() {
        saveWorkflow();
    });
    
    // Confirm status update
    document.getElementById('confirmStatusUpdate')?.addEventListener('click', function() {
        if (pendingStatus) {
            performStatusUpdate(pendingStatus, pendingItemId);
        }
    });
    
    // Workflow editor move up/down
    document.querySelectorAll('.move-up').forEach(btn => {
        btn.addEventListener('click', function() {
            const index = parseInt(this.dataset.index);
            moveFlowItem(index, 'up');
        });
    });
    
    document.querySelectorAll('.move-down').forEach(btn => {
        btn.addEventListener('click', function() {
            const index = parseInt(this.dataset.index);
            moveFlowItem(index, 'down');
        });
    });
    
    // Add to flow
    document.querySelectorAll('.add-to-flow').forEach(btn => {
        btn.addEventListener('click', function() {
            const status = this.dataset.status;
            addToFlow(status);
        });
    });
    
    // Remove from flow
    document.querySelectorAll('.remove-from-flow').forEach(btn => {
        btn.addEventListener('click', function() {
            const status = this.dataset.status;
            removeFromFlow(status);
        });
    });
});

function initializeDraggable() {
    // Simple drag and drop functionality
    let dragSrcEl = null;
    
    const steps = document.querySelectorAll('.draggable-step');
    
    steps.forEach(step => {
        step.addEventListener('dragstart', function(e) {
            dragSrcEl = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
            this.style.opacity = '0.4';
        });
        
        step.addEventListener('dragend', function(e) {
            this.style.opacity = '';
            steps.forEach(s => {
                s.classList.remove('drag-over');
            });
        });
        
        step.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });
        
        step.addEventListener('dragleave', function(e) {
            this.classList.remove('drag-over');
        });
        
        step.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            if (dragSrcEl !== this) {
                // Swap the steps
                const srcIndex = parseInt(dragSrcEl.dataset.index);
                const destIndex = parseInt(this.dataset.index);
                
                if (srcIndex !== destIndex) {
                    reorderFlow(srcIndex, destIndex);
                }
            }
        });
    });
}

function reorderFlow(srcIndex, destIndex) {
    const tracker = document.getElementById('trackerSteps');
    const steps = Array.from(tracker.children);
    
    // Reorder the steps array
    const [removed] = steps.splice(srcIndex, 1);
    steps.splice(destIndex, 0, removed);
    
    // Rebuild the tracker
    tracker.innerHTML = '';
    steps.forEach((step, index) => {
        step.dataset.index = index;
        tracker.appendChild(step);
        
        // Update connector lines
        if (index < steps.length - 1) {
            const connector = document.createElement('div');
            connector.className = 'progress-connector flex-grow-1';
            connector.innerHTML = '<div class="connector-line bg-light" style="height: 2px; margin-top: 25px;"></div>';
            tracker.appendChild(connector);
        }
    });
    
    // Reinitialize draggable
    initializeDraggable();
}

function updateOrderStatus(status) {
    pendingStatus = status;
    pendingItemId = null;
    statusModal.show();
}

function updateItemStatus(itemId, currentStatus) {
    pendingItemId = itemId;
    pendingStatus = null;
    statusModal.show();
}

function performStatusUpdate(status, itemId = null) {
    const note = document.getElementById('statusNote').value;
    const data = {
        order_id: <?php echo $order_id; ?>,
        status: status,
        note: note
    };
    
    if (itemId) {
        data.order_item_id = itemId;
        data.action = 'update_item_status';
    } else {
        data.action = 'update_order_status';
    }
    
    fetch('update-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            statusModal.hide();
            showToast('Status updated successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error: ' + (result.error || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to update status. Please try again.', 'danger');
    });
}

function saveWorkflow() {
    const steps = document.querySelectorAll('#currentFlow .list-group-item');
    const flow = Array.from(steps).map(step => step.dataset.status);
    
    fetch('save-workflow.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ flow: flow })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Workflow saved successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error saving workflow', 'danger');
        }
    });
}

function resetWorkflow() {
    const defaultFlow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    
    fetch('save-workflow.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ flow: defaultFlow, reset: true })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Workflow reset to default!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    });
}

function moveFlowItem(index, direction) {
    const flowList = document.getElementById('currentFlow');
    const items = Array.from(flowList.children);
    
    if (direction === 'up' && index > 0) {
        const [item] = items.splice(index, 1);
        items.splice(index - 1, 0, item);
    } else if (direction === 'down' && index < items.length - 1) {
        const [item] = items.splice(index, 1);
        items.splice(index + 1, 0, item);
    }
    
    // Rebuild the flow list
    flowList.innerHTML = '';
    items.forEach((item, newIndex) => {
        const status = item.dataset.status;
        const info = <?php echo json_encode($available_statuses); ?>[status];
        
        const newItem = document.createElement('div');
        newItem.className = 'list-group-item d-flex justify-content-between align-items-center';
        newItem.dataset.status = status;
        newItem.innerHTML = `
            <div>
                <i class="bi ${info.icon} me-2 text-${info.color}"></i>
                ${info.label}
            </div>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary move-up" data-index="${newIndex}" ${newIndex == 0 ? 'disabled' : ''}>
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button class="btn btn-outline-secondary move-down" data-index="${newIndex}" ${newIndex == items.length - 1 ? 'disabled' : ''}>
                    <i class="bi bi-arrow-down"></i>
                </button>
                <button class="btn btn-outline-danger remove-from-flow" data-status="${status}">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
        
        flowList.appendChild(newItem);
    });
    
    // Reattach event listeners
    document.querySelectorAll('.move-up').forEach(btn => {
        btn.addEventListener('click', function() {
            moveFlowItem(parseInt(this.dataset.index), 'up');
        });
    });
    
    document.querySelectorAll('.move-down').forEach(btn => {
        btn.addEventListener('click', function() {
            moveFlowItem(parseInt(this.dataset.index), 'down');
        });
    });
    
    document.querySelectorAll('.remove-from-flow').forEach(btn => {
        btn.addEventListener('click', function() {
            removeFromFlow(this.dataset.status);
        });
    });
    
    document.getElementById('flowCount').textContent = items.length;
}

function addToFlow(status) {
    const flowList = document.getElementById('currentFlow');
    const existingItems = Array.from(flowList.children);
    
    if (existingItems.some(item => item.dataset.status === status)) {
        showToast('Status already in flow', 'warning');
        return;
    }
    
    const info = <?php echo json_encode($available_statuses); ?>[status];
    const newIndex = existingItems.length;
    
    const newItem = document.createElement('div');
    newItem.className = 'list-group-item d-flex justify-content-between align-items-center';
    newItem.dataset.status = status;
    newItem.innerHTML = `
        <div>
            <i class="bi ${info.icon} me-2 text-${info.color}"></i>
            ${info.label}
        </div>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary move-up" data-index="${newIndex}" ${newIndex == 0 ? 'disabled' : ''}>
                <i class="bi bi-arrow-up"></i>
            </button>
            <button class="btn btn-outline-secondary move-down" data-index="${newIndex}" disabled>
                <i class="bi bi-arrow-down"></i>
            </button>
            <button class="btn btn-outline-danger remove-from-flow" data-status="${status}">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    flowList.appendChild(newItem);
    document.getElementById('flowCount').textContent = existingItems.length + 1;
    
    // Reattach event listeners
    newItem.querySelector('.move-up').addEventListener('click', function() {
        moveFlowItem(parseInt(this.dataset.index), 'up');
    });
    
    newItem.querySelector('.remove-from-flow').addEventListener('click', function() {
        removeFromFlow(status);
    });
}

function removeFromFlow(status) {
    const flowList = document.getElementById('currentFlow');
    const items = Array.from(flowList.children);
    const index = items.findIndex(item => item.dataset.status === status);
    
    if (index !== -1) {
        items[index].remove();
        document.getElementById('flowCount').textContent = items.length - 1;
        
        // Update indices
        const remainingItems = Array.from(flowList.children);
        remainingItems.forEach((item, newIndex) => {
            const moveUpBtn = item.querySelector('.move-up');
            const moveDownBtn = item.querySelector('.move-down');
            
            if (moveUpBtn) {
                moveUpBtn.dataset.index = newIndex;
                moveUpBtn.disabled = newIndex === 0;
            }
            if (moveDownBtn) {
                moveDownBtn.dataset.index = newIndex;
                moveDownBtn.disabled = newIndex === remainingItems.length - 1;
            }
        });
    }
}

function showToast(message, type) {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}
</script>

<style>
    .draggable-step {
        cursor: grab;
        transition: all 0.2s;
    }
    
    .draggable-step:active {
        cursor: grabbing;
    }
    
    .draggable-step.drag-over {
        transform: scale(1.05);
        filter: drop-shadow(0 0 5px rgba(0,0,0,0.2));
    }
    
    .step-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .step-icon:hover {
        transform: scale(1.1);
    }
    
    .progress-step.completed .step-icon {
        background-color: #198754;
        color: white;
    }
    
    .progress-step.current .step-icon {
        background-color: #0d6efd;
        color: white;
        transform: scale(1.1);
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2);
    }
    
    .connector-line {
        height: 2px;
        width: 100%;
        transition: background-color 0.3s;
    }
    
    .timeline-vertical {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline-vertical .timeline-item {
        position: relative;
        padding-bottom: 20px;
        margin-bottom: 10px;
        border-left: 2px solid #dee2e6;
        padding-left: 20px;
    }
    
    .timeline-vertical .timeline-item:last-child {
        border-left-color: transparent;
        padding-bottom: 0;
    }
    
    .timeline-vertical .timeline-marker {
        position: absolute;
        left: -9px;
        top: 0;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        z-index: 1;
    }
    
    .workflow-editor .list-group-item {
        cursor: default;
    }
    
    .workflow-editor .list-group-item:hover {
        background-color: #f8f9fa;
    }
    
    @media print {
        #sidebar, .btn-toolbar, .btn, .modal {
            display: none !important;
        }
        
        .col-lg-10 {
            width: 100% !important;
            flex: 0 0 100% !important;
        }
    }
</style>

<?php include '../../includes/footer.php'; ?>