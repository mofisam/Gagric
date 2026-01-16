<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';
require_once '../../classes/Logistics.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$logistics = new Logistics($db);
$user_id = getCurrentUserId();

$order_id = $_GET['id'] ?? 0;

// FIX: First get the order by ID to get the order number
$order_by_id = $db->fetchOne("
    SELECT * FROM orders 
    WHERE id = ? AND buyer_id = ?
", [$order_id, $user_id]);

if (!$order_by_id) {
    setFlashMessage('Order not found', 'error');
    header('Location: order-history.php');
    exit;
}

// Now use the order number with the Order class
$order_number = $order_by_id['order_number'];
$order_data = $order->getOrder($order_number, $user_id);

if (!$order_data) {
    setFlashMessage('Order not found', 'error');
    header('Location: order-history.php');
    exit;
}

// Get shipping details
$shipping = $db->fetchOne("
    SELECT os.*, s.name as state_name, l.name as lga_name, c.name as city_name 
    FROM order_shipping_details os 
    JOIN states s ON os.state_id = s.id 
    JOIN lgas l ON os.lga_id = l.id 
    JOIN cities c ON os.city_id = c.id 
    WHERE os.order_id = ?
", [$order_id]);

// Get tracking information
$tracking_info = [];
if ($shipping && $shipping['tracking_number']) {
    $tracking_info = $logistics->trackShipment($shipping['tracking_number']);
}

// Get order items for reference
$order_items = $db->fetchAll("
    SELECT oi.product_name, oi.quantity, oi.unit, sp.business_name as seller_name 
    FROM order_items oi 
    JOIN seller_profiles sp ON oi.seller_id = sp.user_id 
    WHERE oi.order_id = ?
", [$order_id]);
?>
<?php 
$page_title = "Track Order #" . $order_data['order_number'];
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8">
            <!-- Order Tracking Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Track Order #<?php echo $order_data['order_number']; ?></h2>
                            <p class="text-muted mb-0">Estimated Delivery: 
                                <?php echo isset($shipping['estimated_delivery']) && $shipping['estimated_delivery'] ? formatDate($shipping['estimated_delivery']) : 'Processing'; ?>
                            </p>
                        </div>
                        <span class="badge bg-<?php 
                            echo $order_data['status'] === 'delivered' ? 'success' : 
                                 ($order_data['status'] === 'shipped' ? 'info' : 'warning'); 
                        ?> fs-6">
                            <?php echo ucfirst($order_data['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tracking Progress -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Delivery Status</h5>
                </div>
                <div class="card-body">
                    <div class="tracking-progress">
                        <?php
                        $steps = [
                            ['Order Placed', 'bi-bag-check', $order_data['created_at']],
                            ['Order Confirmed', 'bi-check-circle', $order_data['status'] !== 'pending' ? $order_data['created_at'] : null],
                            ['Processing', 'bi-gear', in_array($order_data['status'], ['processing', 'shipped', 'delivered']) ? $order_data['created_at'] : null],
                            ['Shipped', 'bi-truck', in_array($order_data['status'], ['shipped', 'delivered']) ? $order_data['created_at'] : null],
                            ['Delivered', 'bi-house-check', $order_data['status'] === 'delivered' ? ($order_data['delivered_at'] ?? null) : null]
                        ];
                        
                        foreach ($steps as $index => $step):
                            $is_completed = $step[2] !== null;
                            $is_current = !$is_completed && ($index === 0 || $steps[$index-1][2] !== null);
                        ?>
                            <div class="tracking-step <?php echo $is_completed ? 'completed' : ($is_current ? 'current' : ''); ?>">
                                <div class="step-icon">
                                    <i class="bi <?php echo $step[1]; ?>"></i>
                                </div>
                                <div class="step-content">
                                    <h6 class="mb-1"><?php echo $step[0]; ?></h6>
                                    <?php if ($step[2]): ?>
                                        <small class="text-muted">
                                            <?php echo formatDate($step[2], 'M d, h:i A'); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Shipment Tracking -->
            <?php if ($shipping && isset($shipping['tracking_number']) && $shipping['tracking_number']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Shipment Tracking</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Tracking Information</h6>
                                <p class="mb-1"><strong>Tracking Number:</strong> <?php echo htmlspecialchars($shipping['tracking_number']); ?></p>
                                <p class="mb-1"><strong>Logistics Partner:</strong> <?php echo isset($shipping['logistics_partner']) ? htmlspecialchars($shipping['logistics_partner']) : 'AgriMarket Logistics'; ?></p>
                                <?php if ($tracking_info && isset($tracking_info['status'])): ?>
                                    <p class="mb-0"><strong>Current Status:</strong> 
                                        <span class="badge bg-info"><?php echo ucfirst($tracking_info['status']); ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6>Delivery Information</h6>
                                <p class="mb-1"><strong>Delivery Address:</strong> <?php echo htmlspecialchars($shipping['address_line']); ?></p>
                                <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($shipping['city_name'] . ', ' . $shipping['state_name']); ?></p>
                                <p class="mb-0"><strong>Contact:</strong> <?php echo htmlspecialchars($shipping['shipping_phone']); ?></p>
                            </div>
                        </div>

                        <?php if ($tracking_info && isset($tracking_info['tracking_events'])): ?>
                            <h6>Tracking History</h6>
                            <div class="tracking-timeline">
                                <?php foreach ($tracking_info['tracking_events'] as $event): ?>
                                    <div class="tracking-event">
                                        <div class="event-time"><?php echo isset($event['timestamp']) ? formatDate($event['timestamp'], 'M d, h:i A') : 'N/A'; ?></div>
                                        <div class="event-content">
                                            <h6 class="mb-1"><?php echo isset($event['status']) ? ucfirst(str_replace('_', ' ', $event['status'])) : 'Unknown Status'; ?></h6>
                                            <p class="text-muted mb-0"><?php echo isset($event['location']) ? htmlspecialchars($event['location']) : 'Location not specified'; ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($shipping && !isset($shipping['tracking_number'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Shipment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Your order is being processed. Tracking information will be available once your order is shipped.
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Delivery Information</h6>
                                <p class="mb-1"><strong>Delivery Address:</strong> <?php echo htmlspecialchars($shipping['address_line']); ?></p>
                                <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($shipping['city_name'] . ', ' . $shipping['state_name']); ?></p>
                                <p class="mb-0"><strong>Contact:</strong> <?php echo htmlspecialchars($shipping['shipping_phone']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Next Steps</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Order confirmed</li>
                                    <li class="mb-2"><i class="bi bi-clock text-warning me-2"></i> Processing items</li>
                                    <li><i class="bi bi-truck text-secondary me-2"></i> Awaiting shipment</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Order Summary -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Order Details</h6>
                        <p class="mb-1"><strong>Order #:</strong> <?php echo $order_data['order_number']; ?></p>
                        <p class="mb-1"><strong>Order Date:</strong> <?php echo formatDate($order_data['created_at']); ?></p>
                        <p class="mb-0"><strong>Total Amount:</strong> <?php echo formatCurrency($order_data['total_amount']); ?></p>
                    </div>

                    <hr>

                    <h6 class="mb-3">Order Items</h6>
                    <?php foreach ($order_items as $item): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <small><?php echo htmlspecialchars($item['product_name']); ?></small>
                                <br><small class="text-muted">From: <?php echo htmlspecialchars($item['seller_name']); ?></small>
                            </div>
                            <small><?php echo htmlspecialchars($item['quantity']); ?> × <?php echo htmlspecialchars($item['unit']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Support Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Need Help?</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-headset text-success" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-center">Having issues with your delivery?</p>
                    
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-success">
                            <i class="bi bi-chat-dots me-2"></i> Chat with Support
                        </a>
                        <a href="#" class="btn btn-outline-success">
                            <i class="bi bi-telephone me-2"></i> Call Support
                        </a>
                        <a href="../orders/order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-success">
                            <i class="bi bi-info-circle me-2"></i> View Order Details
                        </a>
                    </div>

                    <hr class="my-3">

                    <div class="text-center">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i> Support Hours: 8AM - 6PM (Mon-Sat)
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tracking-progress {
    position: relative;
    padding-left: 40px;
}
.tracking-progress::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #dee2e6;
}
.tracking-step {
    position: relative;
    margin-bottom: 30px;
}
.tracking-step:last-child {
    margin-bottom: 0;
}
.tracking-step.completed::before {
    background-color: #198754;
}
.tracking-step.current::before {
    background-color: #198754;
}
.tracking-step::before {
    content: '';
    position: absolute;
    left: -30px;
    top: 0;
    width: 2px;
    height: 100%;
    background-color: #dee2e6;
}
.tracking-step.completed .step-icon {
    background-color: #198754;
    color: white;
    border-color: #198754;
}
.tracking-step.current .step-icon {
    background-color: white;
    color: #198754;
    border-color: #198754;
    box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.2);
}
.step-icon {
    position: absolute;
    left: -50px;
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
.step-content {
    padding-left: 10px;
}

.tracking-timeline {
    border-left: 2px solid #dee2e6;
    padding-left: 20px;
    margin-left: 10px;
}
.tracking-event {
    position: relative;
    margin-bottom: 20px;
}
.tracking-event:last-child {
    margin-bottom: 0;
}
.tracking-event::before {
    content: '';
    position: absolute;
    left: -27px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #198754;
}
.event-time {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 5px;
}
</style>

<?php include '../../includes/footer.php'; ?>