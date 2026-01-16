<?php
// seller/orders/order-details.php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

requireSeller();

$db = new Database();
$seller_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? 0;

// Get order details
$order = $db->fetchOne("
    SELECT o.*, 
           u.first_name, u.last_name, u.email, u.phone,
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

// Get order items for this seller
$order_items = $db->fetchAll("
    SELECT oi.*, 
           p.name as product_name, p.unit as product_unit,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
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

$page_title = "Order #" . $order['order_number'];
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2">Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                    <p class="text-muted mb-0">
                        Placed on <?php echo formatDate($order['created_at']); ?>
                        • <?php echo getOrderStatusBadge($order['status']); ?>
                    </p>
                </div>
                <div>
                    <a href="manage-orders.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>

            <!-- Status Update Modals -->
            <div class="modal fade" id="itemStatusModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Item Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="itemStatusForm">
                            <div class="modal-body">
                                <input type="hidden" id="item_order_item_id" name="order_item_id">
                                <input type="hidden" name="action" value="update_item_status">
                                
                                <div class="mb-3">
                                    <label class="form-label">Product:</label>
                                    <div id="item_product_name" class="alert alert-light border"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Status:</label>
                                    <div id="item_current_status"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="item_new_status" class="form-label">New Status:</label>
                                    <select class="form-select" id="item_new_status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="processing">Processing</option>
                                        <option value="ready_to_ship">Ready to Ship</option>
                                        <option value="shipped">Shipped</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="item_status_notes" class="form-label">Notes (Optional):</label>
                                    <textarea class="form-control" id="item_status_notes" name="notes" rows="3" placeholder="Add any notes about this status change..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <span class="spinner-border spinner-border-sm d-none" id="itemSpinner"></span>
                                    Update Item Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="orderStatusModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Update Order Status</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="orderStatusForm">
                            <div class="modal-body">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <input type="hidden" name="action" value="update_order_status">
                                
                                <div class="mb-3">
                                    <label class="form-label">Order:</label>
                                    <div class="alert alert-light border">
                                        <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Status:</label>
                                    <div>
                                        <?php echo getOrderStatusBadge($order['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="order_new_status" class="form-label">New Status:</label>
                                    <select class="form-select" id="order_new_status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'disabled' : ''; ?>>Confirmed</option>
                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'disabled' : ''; ?>>Processing</option>
                                        <option value="ready_to_ship" <?php echo $order['status'] === 'ready_to_ship' ? 'disabled' : ''; ?>>Ready to Ship</option>
                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'disabled' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'disabled' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'disabled' : ''; ?>>Cancelled</option>
                                    </select>
                                    <small class="text-muted">Note: This will update all your items in this order.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="order_status_notes" class="form-label">Notes (Optional):</label>
                                    <textarea class="form-control" id="order_status_notes" name="notes" rows="3" placeholder="Add any notes about this status change..."></textarea>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    <strong>Note:</strong> Changing order status will update all your items in this order.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <span class="spinner-border spinner-border-sm d-none" id="orderSpinner"></span>
                                    Update Order Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">

        
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Order Items -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Your Order Items (<?php echo count($order_items); ?>)</h5>
                            <button class="btn btn-primary btn-sm" onclick="openOrderStatusModal()">
                                <i class="bi bi-pencil"></i> Update Order Status
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="40%">Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($item['product_image'])): ?>
                                                            <img src="../../assets/uploads/products/<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                                 class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;"
                                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                            <?php if (isset($item['grade']) && $item['grade']): ?>
                                                                <br><small class="text-muted">Grade: <?php echo htmlspecialchars($item['grade']); ?></small>
                                                            <?php endif; ?>
                                                            <?php if (isset($item['is_organic']) && $item['is_organic']): ?>
                                                                <span class="badge bg-success ms-1">Organic</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo formatCurrency($item['unit_price']); ?></td>
                                                <td>
                                                    <?php echo $item['quantity']; ?>
                                                    <?php echo htmlspecialchars($item['unit'] ?? $item['product_unit']); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatCurrency($item['item_total']); ?></strong>
                                                    <br><small class="text-muted">Comm: <?php echo formatCurrency($item['item_total'] * $commission_rate / 100); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo getOrderStatusBadge($item['status']); ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="openItemStatusModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['product_name'])); ?>', '<?php echo $item['status']; ?>')">
                                                        <i class="bi bi-pencil"></i> Update
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Order Timeline -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Order Progress</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php
                                $steps = [
                                    ['pending' => ['Order Placed', 'bi-bag-check', $order['created_at']]],
                                    ['confirmed' => ['Order Confirmed', 'bi-check-circle', null]],
                                    ['processing' => ['Processing', 'bi-gear', null]],
                                    ['shipped' => ['Shipped', 'bi-truck', null]],
                                    ['delivered' => ['Delivered', 'bi-house-check', null]]
                                ];
                                
                                $status_order = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
                                $current_index = array_search($order['status'], $status_order);
                                $current_index = $current_index !== false ? $current_index : 0;
                                
                                foreach ($status_order as $index => $status):
                                    $is_completed = $index <= $current_index;
                                    $step = [
                                        'Order Placed', 
                                        $status === 'pending' ? 'bi-bag-check' : 
                                        ($status === 'confirmed' ? 'bi-check-circle' : 
                                        ($status === 'processing' ? 'bi-gear' : 
                                        ($status === 'shipped' ? 'bi-truck' : 'bi-house-check')))
                                    ];
                                ?>
                                    <div class="timeline-item <?php echo $is_completed ? 'active' : ''; ?>">
                                        <div class="timeline-marker">
                                            <i class="bi <?php echo $step[1]; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-0"><?php echo $step[0]; ?></h6>
                                            <?php if ($status === $order['status'] && $status !== 'pending'): ?>
                                                <small class="text-muted">
                                                    <?php 
                                                    $date_field = $status . '_at';
                                                    echo isset($order[$date_field]) && $order[$date_field] ? formatDate($order[$date_field], 'M d, h:i A') : 'In progress';
                                                    ?>
                                                </small>
                                            <?php elseif ($status === 'pending'): ?>
                                                <small class="text-muted">
                                                    <?php echo formatDate($order['created_at'], 'M d, h:i A'); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Order Summary -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Order Status:</strong>
                                <div class="mt-1">
                                    <?php echo getOrderStatusBadge($order['status']); ?>
                                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="openOrderStatusModal()">
                                        <i class="bi bi-pencil"></i> Change
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Payment Status:</strong>
                                <div class="mt-1">
                                    <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Order Date:</strong>
                                <div class="mt-1"><?php echo formatDate($order['created_at'], 'F j, Y g:i A'); ?></div>
                            </div>
                            
                            <hr>
                            
                            <h6>Financial Summary</h6>
                            <div class="mb-2 d-flex justify-content-between">
                                <span>Items Value:</span>
                                <span><?php echo formatCurrency($seller_subtotal); ?></span>
                            </div>
                            <div class="mb-2 d-flex justify-content-between">
                                <span>Commission (<?php echo $commission_rate; ?>%):</span>
                                <span class="text-danger">-<?php echo formatCurrency($seller_commission_total); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Your Earnings:</span>
                                <span class="text-success"><?php echo formatCurrency($seller_net_amount); ?></span>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i> 
                                Payouts are processed weekly after order delivery.
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Name:</strong><br>
                                <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Email:</strong><br>
                                <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>">
                                    <?php echo htmlspecialchars($order['email']); ?>
                                </a>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Phone:</strong><br>
                                <?php echo htmlspecialchars($order['phone']); ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-envelope"></i> Send Email
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Information -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Shipping Information</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($order['shipping_name']): ?>
                                <div class="mb-3">
                                    <strong>Recipient:</strong><br>
                                    <?php echo htmlspecialchars($order['shipping_name']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Phone:</strong><br>
                                    <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Address:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['address_line'])); ?>
                                    <?php if ($order['landmark']): ?>
                                        <br>Landmark: <?php echo htmlspecialchars($order['landmark']); ?>
                                    <?php endif; ?>
                                    <br><?php echo htmlspecialchars($order['city_name'] . ', ' . $order['state_name']); ?>
                                </div>
                                
                                <?php if ($order['tracking_number']): ?>
                                    <div class="mb-3">
                                        <strong>Tracking Number:</strong><br>
                                        <code class="bg-light p-2 rounded d-block"><?php echo htmlspecialchars($order['tracking_number']); ?></code>
                                        <?php if ($order['logistics_partner']): ?>
                                            <small class="text-muted">Via: <?php echo htmlspecialchars($order['logistics_partner']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['estimated_delivery']): ?>
                                    <div class="mb-3">
                                        <strong>Estimated Delivery:</strong><br>
                                        <?php echo formatDate($order['estimated_delivery']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['shipping_instructions']): ?>
                                    <div>
                                        <strong>Special Instructions:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($order['shipping_instructions'])); ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
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

<script>
function openItemStatusModal(itemId, productName, currentStatus) {
    document.getElementById('item_order_item_id').value = itemId;
    document.getElementById('item_product_name').innerHTML = '<strong>' + productName + '</strong>';
    document.getElementById('item_current_status').innerHTML = getStatusBadge(currentStatus);
    document.getElementById('item_new_status').value = '';
    document.getElementById('item_status_notes').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('itemStatusModal'));
    modal.show();
}

function openOrderStatusModal() {
    const modal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
    modal.show();
}

function getStatusBadge(status) {
    const colors = {
        'pending': 'warning',
        'confirmed': 'info', 
        'processing': 'primary',
        'ready_to_ship': 'primary',
        'shipped': 'info',
        'delivered': 'success',
        'cancelled': 'danger'
    };
    const color = colors[status] || 'secondary';
    return `<span class="badge bg-${color}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

// Handle item status form submission
document.getElementById('itemStatusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const jsonData = {};
    formData.forEach((value, key) => jsonData[key] = value);
    
    updateStatus(jsonData, 'item');
});

// Handle order status form submission
document.getElementById('orderStatusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const jsonData = {};
    formData.forEach((value, key) => jsonData[key] = value);
    
    updateStatus(jsonData, 'order');
});

function updateStatus(data, type) {
    // Show loading spinner
    const spinnerId = type === 'item' ? 'itemSpinner' : 'orderSpinner';
    const submitBtn = document.querySelector(`#${type}StatusForm button[type="submit"]`);
    const spinner = document.getElementById(spinnerId);
    
    spinner.classList.remove('d-none');
    submitBtn.disabled = true;
    
    console.log('Updating status:', data);
    
    fetch('update-order-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        console.log('Update result:', result);
        
        if (result.success) {
            // Show success message
            showToast('Status updated successfully!', 'success');
            
            // Close modal after delay
            setTimeout(() => {
                const modalId = type === 'item' ? 'itemStatusModal' : 'orderStatusModal';
                const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
                if (modal) modal.hide();
                
                // Reload page
                location.reload();
            }, 1000);
        } else {
            showToast('Error: ' + (result.error || 'Unknown error'), 'danger');
            spinner.classList.add('d-none');
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Update error:', error);
        showToast('Failed to update status. Please try again.', 'danger');
        spinner.classList.add('d-none');
        submitBtn.disabled = false;
    });
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

</script>

<style>
.timeline {
    position: relative;
    padding-left: 40px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #dee2e6;
}
.timeline-item {
    position: relative;
    margin-bottom: 25px;
}
.timeline-item:last-child {
    margin-bottom: 0;
}
.timeline-item.active .timeline-marker {
    background-color: #198754;
    color: white;
    border-color: #198754;
}
.timeline-marker {
    position: absolute;
    left: -40px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: white;
    border: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}
.timeline-content {
    padding-left: 10px;
}

.table th {
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    color: #6c757d;
    border-bottom: 2px solid #dee2e6;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,.125);
}

.toast-container {
    z-index: 9999;
}
</style>

<?php include '../../includes/footer.php'; ?>