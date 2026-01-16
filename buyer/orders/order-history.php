<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

// Get orders
$orders = $order->getUserOrders($user_id);

// Filter by status if requested
$status_filter = $_GET['status'] ?? '';
if ($status_filter) {
    $orders = array_filter($orders, function($order) use ($status_filter) {
        return $order['status'] === $status_filter;
    });
}

// Get order stats
$total_orders = count($orders);
$pending_orders = array_filter($orders, function($order) {
    return $order['status'] === 'pending';
});
$delivered_orders = array_filter($orders, function($order) {
    return $order['status'] === 'delivered';
});
$shipped_orders = array_filter($orders, function($order) {
    return $order['status'] === 'shipped';
});
?>
<?php 
$page_title = "Order History";
include '../../includes/header.php'; 
?>

<div class="container py-2">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-2">My Orders</h2>
            <p class="text-muted mb-0">Track and manage all your purchases</p>
        </div>
        <div>
            <a href="../products/browse.php" class="btn btn-outline-success">
                <i class="bi bi-plus-circle me-2"></i> Continue Shopping
            </a>
        </div>
    </div>
    
    
    <!-- Order Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-1">Filter Orders</h5>
            <div class="row g-2">
                <div class="col-auto">
                    <a href="?status=" class="btn btn-outline-secondary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                        <i class="bi bi-grid-3x3-gap me-2"></i> All Orders
                    </a>
                </div>
                <div class="col-auto">
                    <a href="?status=pending" class="btn btn-outline-secondary <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        <i class="bi bi-clock me-2"></i> Pending
                    </a>
                </div>
                <div class="col-auto">
                    <a href="?status=confirmed" class="btn btn-outline-secondary <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                        <i class="bi bi-check-circle me-2"></i> Confirmed
                    </a>
                </div>
                <div class="col-auto">
                    <a href="?status=shipped" class="btn btn-outline-secondary <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">
                        <i class="bi bi-truck me-2"></i> Shipped
                    </a>
                </div>
                <div class="col-auto">
                    <a href="?status=delivered" class="btn btn-outline-secondary <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">
                        <i class="bi bi-house-check me-2"></i> Delivered
                    </a>
                </div>
                <div class="col-auto">
                    <a href="?status=cancelled" class="btn btn-outline-secondary <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                        <i class="bi bi-x-circle me-2"></i> Cancelled
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if ($total_orders > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="border-0 ps-4">Order Details</th>
                                <th class="border-0 text-center">Items</th>
                                <th class="border-0 text-center">Payment</th>
                                <th class="border-0 text-center">Status</th>
                                <th class="border-0 pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                $item_count = $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?", [$order['id']])['count'];
                            ?>
                                <tr class="border-bottom">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-start">
                                            <div class="bg-light rounded p-2 me-3">
                                                <i class="bi bi-receipt text-success" style="font-size: 1.2rem;"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo $order['order_number']; ?></h6>
                                                <p class="text-muted mb-1">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    <?php echo formatDate($order['created_at'], 'F j, Y'); ?>
                                                </p>
                                                <p class="mb-0">
                                                    <span class="fw-semibold">Total:</span> 
                                                    <span class="text-success"><?php echo formatCurrency($order['total_amount']); ?></span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-light text-dark">
                                            <?php echo $item_count; ?> item<?php echo $item_count != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php 
                                        $payment_badge = $order['payment_status'] === 'paid' ? 
                                            '<span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle me-1"></i> Paid</span>' :
                                            '<span class="badge bg-warning bg-opacity-10 text-warning"><i class="bi bi-clock me-1"></i> Pending</span>';
                                        echo $payment_badge;
                                        ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php echo getOrderStatusBadge($order['status']); ?>
                                    </td>
                                    <td class="pe-4 text-end align-middle">
                                        <div class="btn-group" role="group">
                                            <a href="order-details.php?order_number=<?php echo $order['order_number']; ?>" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-eye me-1"></i> View
                                            </a>
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Order Summary -->
                <div class="card-footer bg-light border-0">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <p class="mb-0">
                                Showing <strong><?php echo $total_orders; ?></strong> order<?php echo $total_orders != 1 ? 's' : ''; ?>
                                <?php if ($status_filter): ?>
                                    filtered by <span class="badge bg-secondary"><?php echo $status_filter; ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Click "View" to see order details, tracking, and invoice
                            </small>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-bag text-muted" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="mb-3">No orders found</h4>
                    <p class="text-muted mb-4">
                        <?php if ($status_filter): ?>
                            You don't have any <?php echo $status_filter; ?> orders at the moment.
                        <?php else: ?>
                            You haven't placed any orders yet. Start exploring our agricultural products!
                        <?php endif; ?>
                    </p>
                    <a href="../products/browse.php" class="btn btn-success px-4">
                        <i class="bi bi-shop me-2"></i> Browse Products
                    </a>
                    <?php if ($status_filter): ?>
                        <a href="order-history.php" class="btn btn-outline-success px-4 ms-2">
                            <i class="bi bi-arrow-left me-2"></i> View All Orders
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Custom styles for a cleaner look */
.card {
    border-radius: 12px;
}

.table thead th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

.btn-outline-secondary.active {
    background-color: #198754;
    border-color: #198754;
    color: white;
}

.bg-opacity-10 {
    --bs-bg-opacity: 0.1;
}

/* Status badge improvements */
.badge.bg-success {
    background-color: rgba(25, 135, 84, 0.1) !important;
    color: #198754 !important;
}

.badge.bg-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
    color: #ffc107 !important;
}

.badge.bg-info {
    background-color: rgba(13, 110, 253, 0.1) !important;
    color: #0d6efd !important;
}

.badge.bg-danger {
    background-color: rgba(220, 53, 69, 0.1) !important;
    color: #dc3545 !important;
}

/* Table row styling */
.table tbody tr {
    transition: background-color 0.2s ease;
}

.table tbody tr:last-child {
    border-bottom: 0 !important;
}
</style>

<script>
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
        const btn = event.target;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cancelling...';
        btn.disabled = true;
        
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
                // Show success toast
                const toast = `
                    <div class="toast align-items-center text-white bg-success border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi bi-check-circle me-2"></i>
                                Order cancelled successfully
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `;
                
                // Create toast container if not exists
                let container = document.querySelector('.toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'toast-container position-fixed top-0 end-0 p-3';
                    document.body.appendChild(container);
                }
                
                container.innerHTML = toast;
                const bsToast = new bootstrap.Toast(container.querySelector('.toast'));
                bsToast.show();
                
                // Reload page after delay
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                alert('Error: ' + (data.error || 'Failed to cancel order'));
            }
        })
        .catch(error => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            console.error('Error:', error);
            alert('Network error. Please try again.');
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>