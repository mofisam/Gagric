<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];

// Get orders ready for shipping
$orders_to_ship = $db->fetchAll("
    SELECT oi.*, o.order_number, o.created_at, p.name as product_name,
           u.first_name, u.last_name, os.*
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN order_shipping_details os ON o.id = os.order_id
    WHERE oi.seller_id = ? AND oi.status = 'confirmed'
    ORDER BY o.created_at ASC
", [$seller_id]);

$page_title = "Shipping Management";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Shipping Management</h1>
            </div>

            <!-- Orders Ready for Shipping -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Orders Ready for Shipping</h5>
                </div>
                <div class="card-body">
                    <?php if ($orders_to_ship): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Product</th>
                                        <th>Customer</th>
                                        <th>Shipping Address</th>
                                        <th>Order Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders_to_ship as $order): ?>
                                        <tr>
                                            <td><?php echo $order['order_number']; ?></td>
                                            <td><?php echo $order['product_name']; ?></td>
                                            <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                                            <td>
                                                <?php echo $order['address_line']; ?><br>
                                                <small class="text-muted">
                                                    <?php echo $order['landmark'] ? 'Landmark: ' . $order['landmark'] : ''; ?>
                                                </small>
                                            </td>
                                            <td><?php echo formatDate($order['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#shipModal"
                                                            data-order-id="<?php echo $order['order_id']; ?>"
                                                            data-order-item-id="<?php echo $order['id']; ?>"
                                                            data-order-number="<?php echo $order['order_number']; ?>">
                                                        <i class="bi bi-truck"></i> Ship
                                                    </button>
                                                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle display-1 text-success"></i>
                            <h4 class="text-success mt-3">All orders are processed!</h4>
                            <p class="text-muted">No orders pending shipment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Ship Modal -->
<div class="modal fade" id="shipModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ship Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="shipForm">
                    <input type="hidden" id="order_item_id" name="order_item_id">
                    <input type="hidden" id="order_id" name="order_id">
                    
                    <div class="mb-3">
                        <label for="tracking_number" class="form-label">Tracking Number</label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="logistics_partner" class="form-label">Logistics Partner</label>
                        <select class="form-select" id="logistics_partner" name="logistics_partner" required>
                            <option value="">Select Partner</option>
                            <option value="sendy">Sendy</option>
                            <option value="max_ng">MAX.NG</option>
                            <option value="gig_logistics">GIG Logistics</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="estimated_delivery" class="form-label">Estimated Delivery</label>
                        <input type="date" class="form-control" id="estimated_delivery" name="estimated_delivery" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitShipment()">Mark as Shipped</button>
            </div>
        </div>
    </div>
</div>

<script>
var shipModal = document.getElementById('shipModal');
shipModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('order_item_id').value = button.getAttribute('data-order-item-id');
    document.getElementById('order_id').value = button.getAttribute('data-order-id');
    
    // Set minimum date for delivery (tomorrow)
    var tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('estimated_delivery').min = tomorrow.toISOString().split('T')[0];
});

function submitShipment() {
    const formData = new FormData(document.getElementById('shipForm'));
    
    fetch('process-shipping.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>