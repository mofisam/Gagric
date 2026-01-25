<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = new Database();

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    setFlashMessage('User ID required', 'error');
    header('Location: manage-users.php');
    exit;
}

// Get user details
$user = $db->fetchOne("
    SELECT u.*, sp.* 
    FROM users u 
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id 
    WHERE u.id = ?
", [$user_id]);

if (!$user) {
    setFlashMessage('User not found', 'error');
    header('Location: manage-users.php');
    exit;
}

// Get user's recent orders
$recent_orders = $db->fetchAll("
    SELECT * FROM orders 
    WHERE buyer_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$user_id]);

// Get user's products if seller
$user_products = [];
if ($user['role'] === 'seller') {
    $user_products = $db->fetchAll("
        SELECT * FROM products 
        WHERE seller_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ", [$user_id]);
}

$page_title = "User Details - " . $user['first_name'] . ' ' . $user['last_name'];

?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">User Details</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-users.php" class="btn btn-secondary">Back to Users</a>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Name:</th>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                </tr>
                                <tr>
                                    <th>Role:</th>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['role'] === 'admin' ? 'danger' : 
                                                 ($user['role'] === 'seller' ? 'success' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Registered:</th>
                                    <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Login:</th>
                                    <td>
                                        <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($user['role'] === 'seller'): ?>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Business Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Business Name:</th>
                                    <td><?php echo htmlspecialchars($user['business_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Registration No:</th>
                                    <td><?php echo htmlspecialchars($user['business_reg_number'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Approval Status:</th>
                                    <td>
                                        <span class="badge bg-<?php echo $user['is_approved'] ? 'success' : 'warning'; ?>">
                                            <?php echo $user['is_approved'] ? 'Approved' : 'Pending Approval'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($user['is_approved']): ?>
                                <tr>
                                    <th>Approved On:</th>
                                    <td><?php echo date('M j, Y', strtotime($user['approved_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Total Sales:</th>
                                    <td>₦<?php echo number_format($user['total_sales'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Rating:</th>
                                    <td>
                                        <?php if ($user['avg_rating'] > 0): ?>
                                            <span class="text-warning">
                                                <?php echo str_repeat('★', round($user['avg_rating'])); ?>
                                                (<?php echo number_format($user['avg_rating'], 1); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No ratings yet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <?php if ($user['role'] === 'buyer' || $user['role'] === 'seller'): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Orders</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_orders)): ?>
                                <p class="text-muted">No orders found</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_orders as $order): ?>
                                    <a href="../orders/order-details.php?id=<?php echo $order['id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">#<?php echo $order['order_number']; ?></h6>
                                            <small>₦<?php echo number_format($order['total_amount'], 2); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge bg-<?php echo $order['status'] === 'delivered' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </p>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user['role'] === 'seller'): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Products</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($user_products)): ?>
                                <p class="text-muted">No products listed</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($user_products as $product): ?>
                                    <a href="../products/product-details.php?id=<?php echo $product['id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <small>₦<?php echo number_format($product['price_per_unit'], 2); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge bg-<?php 
                                                echo $product['status'] === 'approved' ? 'success' : 
                                                     ($product['status'] === 'pending' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </p>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($product['created_at'])); ?></small>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>