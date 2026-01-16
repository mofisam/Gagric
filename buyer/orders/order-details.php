<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

// ACCEPT BOTH order_number AND id PARAMETERS
$order_number = $_GET['order_number'] ?? '';
$order_id = $_GET['id'] ?? 0;

// If we have order_number, get the order using it
if (!empty($order_number)) {
    $order_data = $order->getOrder($order_number, $user_id);
    if ($order_data && isset($order_data['id'])) {
        $order_id = $order_data['id']; // Get the ID from the order data
    }
} 
// If we have order_id, get the order using it
else if ($order_id) {
    // First get order details by ID
    $order_data_by_id = $db->fetchOne("
        SELECT * FROM orders 
        WHERE id = ? AND buyer_id = ?
    ", [$order_id, $user_id]);
    
    if ($order_data_by_id) {
        // Now get full order details using order number
        $order_data = $order->getOrder($order_data_by_id['order_number'], $user_id);
    } else {
        $order_data = null;
    }
}

if (!$order_data) {
    setFlashMessage('Order not found', 'error');
    header('Location: order-history.php');
    exit;
}

// Ensure we have the order ID for queries
if (!$order_id && isset($order_data['id'])) {
    $order_id = $order_data['id'];
}

// Get order items
$order_items = $db->fetchAll("
    SELECT oi.*, sp.business_name as seller_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN seller_profiles sp ON oi.seller_id = sp.user_id 
    WHERE oi.order_id = ?
", [$order_id]);

// Get shipping details
$shipping = $db->fetchOne("
    SELECT os.*, s.name as state_name, l.name as lga_name, c.name as city_name 
    FROM order_shipping_details os 
    JOIN states s ON os.state_id = s.id 
    JOIN lgas l ON os.lga_id = l.id 
    JOIN cities c ON os.city_id = c.id 
    WHERE os.order_id = ?
", [$order_id]);

// Get payment details
$payment = $db->fetchOne("SELECT * FROM payments WHERE order_id = ?", [$order_id]);
?>
<?php 
$page_title = "Order #" . $order_data['order_number'];
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <!-- Order Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Order #<?php echo $order_data['order_number']; ?></h2>
            <p class="text-muted mb-0">
                Placed on <?php echo formatDate($order_data['created_at']); ?>
                • <?php echo getOrderStatusBadge($order_data['status']); ?>
            </p>
        </div>
        <div>
            <a href="order-history.php" class="btn btn-outline-success">
                <i class="bi bi-arrow-left me-1"></i> Back to Orders
            </a>
            <?php if ($order_data['status'] === 'pending'): ?>
                <button class="btn btn-outline-danger" onclick="cancelOrder(<?php echo $order_id; ?>)">
                    Cancel Order
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Order Items -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Seller</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                // Get product image
                                                $product_image = $db->fetchOne("
                                                    SELECT image_path FROM product_images 
                                                    WHERE product_id = ? AND is_primary = 1 LIMIT 1
                                                ", [$item['product_id']]);
                                                
                                                $image_path = $product_image['image_path'] ?? '';
                                                ?>
                                                <img src="<?php echo !empty($image_path) ? '../../assets/uploads/products/' . htmlspecialchars($image_path) : '../../assets/images/placeholder-product.jpg'; ?>" 
                                                     class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;" 
                                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($item['unit']); ?>
                                                        <?php if (isset($item['is_organic']) && $item['is_organic']): ?>
                                                            • <span class="text-success">Organic</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['seller_name']); ?></td>
                                        <td><?php echo formatCurrency($item['unit_price']); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td class="text-success fw-bold">
                                            <?php echo formatCurrency($item['item_total']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Order Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="track-order.php?id=<?php echo $order_id; ?>" class="btn btn-outline-success w-100">
                                <i class="bi bi-truck me-2"></i> Track Order
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <?php if ($order_data['status'] === 'delivered'): ?>
                                <a href="../reviews/write-review.php?order=<?php echo $order_id; ?>" class="btn btn-outline-success w-100">
                                    <i class="bi bi-star me-2"></i> Write Review
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary w-100" disabled>
                                    Review after delivery
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="invoice.php?id=<?php echo $order_id; ?>" class="btn btn-outline-success w-100" target="_blank">
                                <i class="bi bi-printer me-2"></i> Print Invoice
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-4">
            <!-- Order Status Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Status</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php
                        $statuses = [
                            'pending' => ['Pending', 'bi-clock'],
                            'confirmed' => ['Confirmed', 'bi-check-circle'],
                            'processing' => ['Processing', 'bi-gear'],
                            'shipped' => ['Shipped', 'bi-truck'],
                            'delivered' => ['Delivered', 'bi-check2-circle']
                        ];
                        
                        $current_status = $order_data['status'];
                        $status_index = array_search($current_status, array_keys($statuses));
                        
                        foreach ($statuses as $status => $info):
                            $is_active = array_search($status, array_keys($statuses)) <= $status_index;
                            $is_current = $status === $current_status;
                        ?>
                            <div class="timeline-item <?php echo $is_active ? 'active' : ''; ?>">
                                <div class="timeline-marker">
                                    <i class="bi <?php echo $info[1]; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6 class="mb-0"><?php echo $info[0]; ?></h6>
                                    <?php if ($is_current && $status !== 'pending'): ?>
                                        <small class="text-muted">
                                            <?php 
                                            $date_field = $status . '_at';
                                            echo isset($order_data[$date_field]) && $order_data[$date_field] ? formatDate($order_data[$date_field], 'M d, h:i A') : 'Processing';
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Totals -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span><?php echo formatCurrency($order_data['subtotal_amount']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping</span>
                        <span><?php echo formatCurrency($order_data['shipping_amount']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax</span>
                        <span><?php echo isset($order_data['tax_amount']) ? formatCurrency($order_data['tax_amount']) : '₦0.00'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount</span>
                        <span class="text-danger">-<?php echo isset($order_data['discount_amount']) ? formatCurrency($order_data['discount_amount']) : '₦0.00'; ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <h5>Total</h5>
                        <h5 class="text-success"><?php echo formatCurrency($order_data['total_amount']); ?></h5>
                    </div>
                    
                    <!-- Payment Status -->
                    <hr>
                    <div class="mb-3">
                        <h6>Payment Status</h6>
                        <span class="badge <?php echo $order_data['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                            <?php echo ucfirst($order_data['payment_status']); ?>
                        </span>
                        <?php if (isset($order_data['paid_at']) && $order_data['paid_at']): ?>
                            <small class="d-block text-muted mt-1">
                                Paid on <?php echo formatDate($order_data['paid_at']); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Information -->
            <?php if ($shipping): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars($shipping['shipping_name']); ?></h6>
                        <p class="mb-1">
                            <?php echo htmlspecialchars($shipping['address_line']); ?><br>
                            <?php echo htmlspecialchars($shipping['city_name'] . ', ' . $shipping['lga_name'] . ', ' . $shipping['state_name']); ?>
                        </p>
                        <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($shipping['shipping_phone']); ?></p>
                        
                        <?php if (isset($shipping['tracking_number']) && $shipping['tracking_number']): ?>
                            <hr>
                            <div class="mb-3">
                                <h6>Tracking Information</h6>
                                <p class="mb-1"><strong>Tracking #:</strong> <?php echo htmlspecialchars($shipping['tracking_number']); ?></p>
                                <p class="mb-0"><strong>Logistics Partner:</strong> <?php echo isset($shipping['logistics_partner']) ? htmlspecialchars($shipping['logistics_partner']) : 'Not specified'; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($shipping['estimated_delivery']) && $shipping['estimated_delivery']): ?>
                            <p class="mb-0"><strong>Estimated Delivery:</strong> <?php echo formatDate($shipping['estimated_delivery']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #dee2e6;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-item:last-child {
    margin-bottom: 0;
}
.timeline-item.active .timeline-marker {
    background-color: #198754;
    color: white;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
}
.timeline-content {
    padding-left: 10px;
}
</style>

<script>
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        fetch('../../api/orders/cancel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                agriApp.showToast('Order cancelled successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                agriApp.showToast(data.error || 'Failed to cancel order', 'error');
            }
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>