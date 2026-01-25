<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../includes/functions.php';

$db = new Database();

// Get seller stats
$seller_id = $_SESSION['user_id'];
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active_products,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_products,
        (SELECT COUNT(*) FROM order_items WHERE seller_id = ? AND status = 'pending') as pending_orders,
        (SELECT COUNT(*) FROM order_items WHERE seller_id = ? AND status = 'delivered') as completed_orders,
        (SELECT COALESCE(SUM(item_total), 0) FROM order_items WHERE seller_id = ? AND status = 'delivered') as total_sales
    FROM products 
    WHERE seller_id = ?
", [$seller_id, $seller_id, $seller_id, $seller_id]);

// Recent orders
$recent_orders = $db->fetchAll("
    SELECT oi.*, o.order_number, o.created_at, p.name as product_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE oi.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
", [$seller_id]);

$page_title = "Seller Dashboard";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Seller Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="products/add-product.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add New Product
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-xl-3 col-6 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Products</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['total_products']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-box-seam fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-6 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Products</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['active_products']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-6 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pending Orders</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['pending_orders']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-6 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Total Sales</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatCurrency($stats['total_sales']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                            <a href="orders/manage-orders.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_orders): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <a href="orders/order-details.php?id=<?php echo $order['order_id']; ?>">
                                                            <?php echo $order['order_number']; ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo $order['product_name']; ?></td>
                                                    <td><?php echo $order['quantity']; ?></td>
                                                    <td><?php echo formatCurrency($order['item_total']); ?></td>
                                                    <td><?php echo getOrderStatusBadge($order['status']); ?></td>
                                                    <td><?php echo formatDate($order['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No recent orders</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3 mb-3">
                                    <a href="products/add-product.php" class="btn btn-outline-primary btn-block">
                                        <i class="bi bi-plus-circle fs-1"></i><br>
                                        Add Product
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="products/manage-products.php" class="btn btn-outline-success btn-block">
                                        <i class="bi bi-box-seam fs-1"></i><br>
                                        Manage Products
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="orders/manage-orders.php" class="btn btn-outline-warning btn-block">
                                        <i class="bi bi-cart fs-1"></i><br>
                                        View Orders
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="analytics/sales-analytics.php" class="btn btn-outline-info btn-block">
                                        <i class="bi bi-graph-up fs-1"></i><br>
                                        View Analytics
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>