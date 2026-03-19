<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get orders ready for shipping (status = 'confirmed')
$orders_to_ship = $db->fetchAll("
    SELECT 
        oi.id as order_item_id,
        oi.order_id,
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.item_total,
        oi.status as item_status,
        o.order_number,
        o.created_at as order_date,
        o.payment_status,
        p.name as product_name,
        p.unit,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image,
        u.id as customer_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        os.shipping_name,
        os.shipping_phone,
        os.address_line,
        os.landmark,
        os.shipping_instructions,
        s.name as state_name,
        l.name as lga_name,
        c.name as city_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN order_shipping_details os ON o.id = os.order_id
    LEFT JOIN states s ON os.state_id = s.id
    LEFT JOIN lgas l ON os.lga_id = l.id
    LEFT JOIN cities c ON os.city_id = c.id
    WHERE oi.seller_id = ? AND oi.status = 'confirmed'
    ORDER BY o.created_at ASC
", [$seller_id]);

// Get shipping stats
$stats = [
    'ready_to_ship' => count($orders_to_ship),

    'shipped_today' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.seller_id = ? 
        AND oi.status = 'shipped' 
        AND o.updated_at >= CURDATE()
        AND o.updated_at < CURDATE() + INTERVAL 1 DAY
    ", [$seller_id])['count'],

    'in_transit' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM order_items oi
        WHERE oi.seller_id = ? 
        AND oi.status = 'shipped'
    ", [$seller_id])['count'],

    'delivered' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.seller_id = ? 
        AND oi.status = 'delivered' 
        AND o.updated_at >= CURDATE() - INTERVAL 7 DAY
    ", [$seller_id])['count']
];

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND DATE(o.created_at) = CURDATE()
", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Shipping Management";
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
                        <h1 class="h5 mb-0">Shipping Management</h1>
                        <small class="text-muted">Process and track shipments</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Shipping Management</h1>
                    <p class="text-muted mb-0">Process orders ready for shipment</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshData">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <a href="bulk-shipping.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-truck me-1"></i> Bulk Shipping
                    </a>
                </div>
            </div>

            <!-- Shipping Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Ready to Ship</h6>
                                    <h3 class="card-title mb-0"><?php echo $stats['ready_to_ship']; ?></h3>
                                    <small class="text-warning">Awaiting processing</small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-box-seam fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Shipped Today</h6>
                                    <h3 class="card-title mb-0"><?php echo $stats['shipped_today']; ?></h3>
                                    <small class="text-info">Processed today</small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-truck fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">In Transit</h6>
                                    <h3 class="card-title mb-0"><?php echo $stats['in_transit']; ?></h3>
                                    <small class="text-primary">On the way</small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-airplane fs-4 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Delivered (7d)</h6>
                                    <h3 class="card-title mb-0"><?php echo $stats['delivered']; ?></h3>
                                    <small class="text-success">Last 7 days</small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-check-circle fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Ready for Shipping -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-truck me-2 text-primary"></i>
                        Orders Ready for Shipping (<?php echo $stats['ready_to_ship']; ?>)
                    </h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="filterOrders('all')">All</button>
                        <button class="btn btn-outline-secondary" onclick="filterOrders('today')">Today</button>
                        <button class="btn btn-outline-secondary" onclick="filterOrders('week')">This Week</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($orders_to_ship): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order & Product</th>
                                        <th>Customer</th>
                                        <th>Shipping Address</th>
                                        <th class="text-center">Order Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders_to_ship as $order): ?>
                                        <tr class="order-row" data-order-date="<?php echo $order['order_date']; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($order['product_image']): ?>
                                                        <img src="<?php echo BASE_URL . '/uploads/products/' . $order['product_image']; ?>" 
                                                             alt="<?php echo htmlspecialchars($order['product_name']); ?>"
                                                             class="rounded me-3" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="bi bi-box text-muted fs-4"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                           class="fw-bold text-decoration-none">
                                                            #<?php echo htmlspecialchars($order['order_number']); ?>
                                                        </a>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($order['product_name']); ?>
                                                            (<?php echo number_format($order['quantity']); ?> <?php echo $order['unit']; ?>)
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($order['phone']); ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($order['email']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($order['shipping_name']): ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($order['shipping_phone']); ?>
                                                        </small>
                                                        <br>
                                                        <small>
                                                            <?php echo nl2br(htmlspecialchars($order['address_line'])); ?>
                                                            <?php if ($order['landmark']): ?>
                                                                <br><span class="text-muted">Landmark: <?php echo htmlspecialchars($order['landmark']); ?></span>
                                                            <?php endif; ?>
                                                            <br>
                                                            <span class="text-muted">
                                                                <?php echo htmlspecialchars($order['city_name'] . ', ' . $order['state_name']); ?>
                                                            </span>
                                                        </small>
                                                        <?php if ($order['shipping_instructions']): ?>
                                                            <br>
                                                            <span class="badge bg-info" 
                                                                  data-bs-toggle="tooltip" 
                                                                  title="<?php echo htmlspecialchars($order['shipping_instructions']); ?>">
                                                                <i class="bi bi-info-circle"></i> Instructions
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-warning">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                        Shipping details not available
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo formatDate($order['order_date'], 'M j, Y'); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo formatDate($order['order_date'], 'h:i A'); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" 
                                                            class="btn btn-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#shipModal"
                                                            data-order-id="<?php echo $order['order_id']; ?>"
                                                            data-order-item-id="<?php echo $order['order_item_id']; ?>"
                                                            data-order-number="<?php echo $order['order_number']; ?>"
                                                            data-customer-name="<?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>">
                                                        <i class="bi bi-truck me-1"></i> Ship
                                                    </button>
                                                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                       class="btn btn-outline-primary"
                                                       data-bs-toggle="tooltip" 
                                                       title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                                
                                                <!-- Quick actions -->
                                                <div class="mt-1">
                                                    <small>
                                                        <a href="#" class="text-decoration-none" onclick="printLabel(<?php echo $order['order_id']; ?>)">
                                                            <i class="bi bi-printer"></i> Label
                                                        </a>
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="bi bi-check-circle display-1 text-success"></i>
                            </div>
                            <h4 class="text-success mb-3">All orders are processed!</h4>
                            <p class="text-muted mb-4">No orders pending shipment at this time.</p>
                            <a href="manage-orders.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Orders
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Shipping Tips -->
            <?php if ($orders_to_ship): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-lightbulb text-warning fs-3 me-3"></i>
                                <div>
                                    <strong>Shipping Tips:</strong>
                                    <ul class="mb-0 mt-1">
                                        <li>Always double-check the shipping address before sending</li>
                                        <li>Use sturdy packaging for agricultural products</li>
                                        <li>Include handling instructions for fragile items</li>
                                        <li>Update tracking numbers immediately after shipping</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Ship Modal -->
<div class="modal fade" id="shipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-truck me-2"></i>
                    Ship Order
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="shipForm">
                    <input type="hidden" id="order_item_id" name="order_item_id">
                    <input type="hidden" id="order_id" name="order_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Order Number</label>
                                <div class="form-control bg-light" id="display_order_number" readonly></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Customer</label>
                                <div class="form-control bg-light" id="display_customer_name" readonly></div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="logistics_partner" class="form-label fw-bold">Logistics Partner</label>
                                <select class="form-select" id="logistics_partner" name="logistics_partner" required>
                                    <option value="">Select Partner</option>
                                    <option value="GIG Logistics">GIG Logistics</option>
                                    <option value="DHL">DHL</option>
                                    <option value="FedEx">FedEx</option>
                                    <option value="UPS">UPS</option>
                                    <option value="Aramex">Aramex</option>
                                    <option value="Sendy">Sendy</option>
                                    <option value="MAX.NG">MAX.NG</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tracking_number" class="form-label fw-bold">Tracking Number</label>
                                <input type="text" class="form-control" id="tracking_number" name="tracking_number" 
                                       placeholder="Enter tracking number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="estimated_delivery" class="form-label fw-bold">Estimated Delivery</label>
                                <input type="date" class="form-control" id="estimated_delivery" name="estimated_delivery" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="shipping_cost" class="form-label fw-bold">Shipping Cost (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" id="shipping_cost" name="shipping_cost" 
                                           placeholder="0.00" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="shipping_notes" class="form-label fw-bold">Shipping Notes</label>
                        <textarea class="form-control" id="shipping_notes" name="notes" rows="2" 
                                  placeholder="Any special instructions for the courier?"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Marking as shipped will update the order status and notify the customer.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitShipment()">
                    <i class="bi bi-check-circle me-1"></i> Mark as Shipped
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var shipModal = document.getElementById('shipModal');

shipModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    
    // Get data from button
    var orderItemId = button.getAttribute('data-order-item-id');
    var orderId = button.getAttribute('data-order-id');
    var orderNumber = button.getAttribute('data-order-number');
    var customerName = button.getAttribute('data-customer-name');
    
    // Set form values
    document.getElementById('order_item_id').value = orderItemId;
    document.getElementById('order_id').value = orderId;
    document.getElementById('display_order_number').value = orderNumber;
    document.getElementById('display_customer_name').value = customerName;
    
    // Set minimum date for delivery (tomorrow)
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    var tomorrowStr = tomorrow.toISOString().split('T')[0];
    document.getElementById('estimated_delivery').min = tomorrowStr;
    document.getElementById('estimated_delivery').value = tomorrowStr;
    
    // Clear previous values
    document.getElementById('tracking_number').value = '';
    document.getElementById('logistics_partner').value = '';
    document.getElementById('shipping_cost').value = '';
    document.getElementById('shipping_notes').value = '';
});

function submitShipment() {
    // Validate form
    var trackingNumber = document.getElementById('tracking_number').value;
    var logisticsPartner = document.getElementById('logistics_partner').value;
    var estimatedDelivery = document.getElementById('estimated_delivery').value;
    
    if (!trackingNumber || !logisticsPartner || !estimatedDelivery) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Show loading state
    var submitBtn = event.target;
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    submitBtn.disabled = true;
    
    const formData = new FormData(document.getElementById('shipForm'));
    
    fetch('process-shipping.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showToast('Order marked as shipped successfully!', 'success');
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('shipModal'));
            modal.hide();
            
            // Reload after delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'danger');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to process shipment. Please try again.', 'danger');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function filterOrders(period) {
    var rows = document.querySelectorAll('.order-row');
    var today = new Date();
    var oneDay = 24 * 60 * 60 * 1000;
    
    rows.forEach(row => {
        var orderDate = new Date(row.getAttribute('data-order-date'));
        var show = true;
        
        if (period === 'today') {
            show = orderDate.toDateString() === today.toDateString();
        } else if (period === 'week') {
            var weekAgo = new Date(today.getTime() - (7 * oneDay));
            show = orderDate >= weekAgo;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function printLabel(orderId) {
    window.open('shipping-label.php?id=' + orderId, '_blank');
}

function showToast(message, type) {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
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
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 3000
    });
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Refresh button
    document.getElementById('refreshData')?.addEventListener('click', function() {
        location.reload();
    });
});

// Add custom styles
const style = document.createElement('style');
style.textContent = `
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .modal-header.bg-success {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
    }
    
    @media (max-width: 768px) {
        .table-responsive {
            border: 0;
        }
        
        .table td:nth-child(3) {
            max-width: 200px;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>