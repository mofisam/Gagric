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
$commission_rate = 5; // Default 5%

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
                </div>
            </div>

            <!-- Order Progress Timeline -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-muted mb-4">Order Progress</h6>
                            <div class="progress-tracker">
                                <?php
                                // Order status flow based on your schema
                                $status_flow = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
                                $current_status_index = array_search($order['status'], $status_flow);
                                if ($current_status_index === false) $current_status_index = 0;
                                
                                $status_labels = [
                                    'pending' => 'Order Placed',
                                    'confirmed' => 'Confirmed',
                                    'processing' => 'Processing',
                                    'shipped' => 'Shipped',
                                    'delivered' => 'Delivered'
                                ];
                                
                                $status_icons = [
                                    'pending' => 'bi-bag-check',
                                    'confirmed' => 'bi-check-circle',
                                    'processing' => 'bi-gear',
                                    'shipped' => 'bi-truck',
                                    'delivered' => 'bi-house-check'
                                ];
                                
                                // Note: You don't have these date fields in your orders table
                                // Only created_at exists
                                ?>
                                
                                <div class="d-flex justify-content-between">
                                    <?php foreach ($status_flow as $index => $status): 
                                        $is_completed = $index <= $current_status_index;
                                        $is_current = $status === $order['status'];
                                    ?>
                                        <div class="progress-step text-center <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>" style="flex: 1;">
                                            <div class="step-icon <?php echo $is_completed ? 'bg-success text-white' : 'bg-light'; ?>">
                                                <i class="bi <?php echo $status_icons[$status]; ?>"></i>
                                            </div>
                                            <div class="step-label mt-2">
                                                <strong><?php echo $status_labels[$status]; ?></strong>
                                                <?php if ($status === 'pending'): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo formatDate($order['created_at'], 'M d, h:i A'); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($index < count($status_flow) - 1): ?>
                                            <div class="progress-connector flex-grow-1">
                                                <div class="connector-line <?php echo $index < $current_status_index ? 'bg-success' : 'bg-light'; ?>" style="height: 2px; margin-top: 25px;"></div>
                                            </div>
                                        <?php endif; ?>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($item['product_image']): ?>
                                                            <img src="<?php echo BASE_URL . '/uploads/products/' . $item['product_image']; ?>" 
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
                                                            <?php if ($item['harvest_date']): ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    Harvest: <?php echo formatDate($item['harvest_date']); ?>
                                                                </small>
                                                            <?php endif; ?>
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

                <!-- Right Column - Summary & Customer Info -->
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
                            
                            <div class="d-grid gap-2">
                                <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-envelope me-1"></i> Send Email
                                </a>
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
                                
                                <?php if ($order['estimated_delivery']): ?>
                                    <div class="mb-3">
                                        <label class="text-muted small mb-1">Estimated Delivery</label>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar-check me-2"></i>
                                            <span><?php echo formatDate($order['estimated_delivery']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['shipping_instructions']): ?>
                                    <div class="mb-3">
                                        <label class="text-muted small mb-1">Special Instructions</label>
                                        <div class="alert alert-light border small">
                                            <?php echo nl2br(htmlspecialchars($order['shipping_instructions'])); ?>
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

<!-- Helper function for date formatting if not already in functions.php -->
<?php
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'M j, Y') {
        if (!$date) return 'N/A';
        return date($format, strtotime($date));
    }
}

if (!function_exists('getOrderStatusBadge')) {
    function getOrderStatusBadge($status) {
        $colors = [
            'pending' => 'warning',
            'confirmed' => 'info',
            'processing' => 'primary',
            'shipped' => 'primary',
            'delivered' => 'success',
            'cancelled' => 'danger'
        ];
        $color = $colors[$status] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
    }
}
?>

<style>
    .progress-tracker {
        margin-bottom: 20px;
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
    
    .summary-item {
        font-size: 0.95rem;
    }
    
    @media print {
        #sidebar, .btn-toolbar, .btn, .modal {
            display: none !important;
        }
        
        .col-lg-10 {
            width: 100% !important;
            flex: 0 0 100% !important;
        }
        
        .card {
            break-inside: avoid;
        }
    }
</style>

<?php include '../../includes/footer.php'; ?>