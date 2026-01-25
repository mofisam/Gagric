<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';

$db = new Database();

$seller_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? '';

// Build query
$where = "oi.seller_id = ?";
$params = [$seller_id];

if ($status_filter) {
    $where .= " AND oi.status = ?";
    $params[] = $status_filter;
}

$orders = $db->fetchAll("
    SELECT oi.*, o.order_number, o.created_at, o.total_amount, 
           p.name as product_name, u.first_name, u.last_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE $where
    ORDER BY o.created_at DESC
", $params);

$page_title = "Manage Orders";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Orders</h1>
            </div>

            <!-- Order Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Orders</h5>
                            <h2 class="card-text">
                                <?php echo $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ?", [$seller_id])['count']; ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Pending</h5>
                            <h2 class="card-text">
                                <?php echo $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count']; ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Shipped</h5>
                            <h2 class="card-text">
                                <?php echo $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'shipped'", [$seller_id])['count']; ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Delivered</h5>
                            <h2 class="card-text">
                                <?php echo $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'delivered'", [$seller_id])['count']; ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-body">
                    <?php if ($orders): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Product</th>
                                        <th>Customer</th>
                                        <th>Quantity</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $order['order_id']; ?>">
                                                    <?php echo $order['order_number']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo $order['product_name']; ?></td>
                                            <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td><?php echo formatCurrency($order['item_total']); ?></td>
                                            <td><?php echo getOrderStatusBadge($order['status']); ?></td>
                                            <td><?php echo formatDate($order['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="order-details.php?id=<?php echo $order['order_id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($order['status'] == 'pending'): ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="updateStatus(<?php echo $order['id']; ?>, 'confirmed')">
                                                            <i class="bi bi-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-cart display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No orders found</h4>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function updateStatus(orderItemId, status) {
    if (confirm('Are you sure you want to update this order status?')) {
        // AJAX call to update status
        fetch('update-order-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_item_id: orderItemId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating status: ' + data.error);
            }
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>