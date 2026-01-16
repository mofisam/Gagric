<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';
require_once '../classes/Order.php';
require_once '../config/constants.php';

requireBuyer();

$db = new Database();
$order = new Order($db);

$user_id = getCurrentUserId();

// Get user stats
$recent_orders = $order->getUserOrders($user_id);
$recent_orders_count = count($recent_orders);
$total_orders = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE buyer_id = ?", [$user_id])['count'];
$total_spent = $db->fetchOne("SELECT SUM(total_amount) as total FROM orders WHERE buyer_id = ? AND payment_status = 'paid'", [$user_id])['total'] ?? 0;
$wishlist_count = $db->fetchOne("SELECT COUNT(*) as count FROM wishlists WHERE user_id = ?", [$user_id])['count'];

// Get order status breakdown
$order_stats = $db->fetchAll("
    SELECT status, COUNT(*) as count 
    FROM orders 
    WHERE buyer_id = ? 
    GROUP BY status
", [$user_id]);

// Get recent reviews
$recent_reviews = $db->fetchAll("
    SELECT r.*, p.name as product_name, p.slug as product_slug
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 3
", [$user_id]);

// Get monthly spending
$monthly_spending = $db->fetchAll("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(total_amount) as total
    FROM orders 
    WHERE buyer_id = ? 
        AND payment_status = 'paid'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
", [$user_id]);

// Get top categories
$top_categories = $db->fetchAll("
    SELECT c.name, COUNT(oi.id) as order_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.buyer_id = ?
    GROUP BY c.id
    ORDER BY order_count DESC
    LIMIT 4
", [$user_id]);

// Get delivery performance
$delivery_stats = $db->fetchOne("
    SELECT 
        AVG(DATEDIFF(delivered_at, created_at)) as avg_delivery_days,
        COUNT(CASE WHEN delivered_at IS NOT NULL THEN 1 END) as delivered_count
    FROM orders 
    WHERE buyer_id = ? 
        AND status = 'delivered'
", [$user_id]);
?>
<?php 
$page_title = "Dashboard";
include '../includes/header.php'; 
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #198754 0%, #25a76b 100%);
    --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    --hover-shadow: 0 15px 40px rgba(25, 135, 84, 0.15);
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background-color: #f8fafc;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Dashboard Components */
.dashboard-header {
    background: white;
    border-radius: var(--border-radius);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--card-shadow);
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 300px;
    height: 300px;
    background: var(--primary-gradient);
    opacity: 0.05;
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    height: 100%;
    border: 1px solid #f1f5f9;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--hover-shadow);
}

.stat-card-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    background: var(--primary-gradient);
    color: white;
}

.stat-card-icon.secondary {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    color: #0ea5e9;
}

.stat-card-icon.warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #f59e0b;
}

.stat-card-icon.danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #ef4444;
}

.progress-ring {
    width: 120px;
    height: 120px;
    position: relative;
}

.progress-ring-circle {
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

.progress-ring-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 8;
}

.progress-ring-fg {
    fill: none;
    stroke: url(#gradient);
    stroke-width: 8;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.5s ease;
}

.chart-container {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    height: 100%;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 0.5rem;
    transition: var(--transition);
    border: 1px solid transparent;
}

.activity-item:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
    border-radius: 12px;
    background: white;
    border: 2px solid #f1f5f9;
    transition: var(--transition);
    text-decoration: none;
    color: #334155;
    text-align: center;
    min-height: 120px;
}

.quick-action-btn:hover {
    border-color: #198754;
    transform: translateY(-2px);
    box-shadow: var(--hover-shadow);
    color: #198754;
}

.category-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: #f0f9ff;
    border-radius: 20px;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    color: #0c4a6e;
}

.delivery-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    background: #dcfce7;
    color: #166534;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

/* Animation Classes */
.fade-in {
    animation: fadeIn 0.5s ease-out;
}

.slide-up {
    animation: slideUp 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .stat-card {
        padding: 1.25rem;
    }
    
    .chart-container {
        padding: 1.25rem;
    }
}
</style>

<div class="container-fluid px-3 px-lg-4 py-4">
    <!-- Dashboard Header -->
    <div class="dashboard-header fade-in">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="h2 fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h1>
                <p class="text-muted mb-4">
                    Here's what's happening with your agricultural purchases today
                    <span class="text-success">•</span> 
                    <span id="currentDateTime"><?php echo date('l, F j, Y • g:i A'); ?></span>
                </p>
                
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach($top_categories as $category): ?>
                        <span class="category-chip">
                            <i class="bi bi-tag me-1"></i>
                            <?php echo htmlspecialchars($category['name']); ?>
                            <span class="ms-1 text-success"><?php echo $category['order_count']; ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="products/browse.php" class="btn btn-lg btn-success px-4 py-3 fw-semibold" style="background: var(--primary-gradient); border: none;">
                    <i class="bi bi-cart-plus me-2"></i> New Purchase
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-6 col-xl-3 mb-4">
            <div class="stat-card slide-up" style="animation-delay: 0.1s;">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-icon">
                            <i class="bi bi-bag-check" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="text-muted mb-2">Total Orders</h6>
                        <h2 class="fw-bold mb-0"><?php echo $total_orders; ?></h2>
                        <p class="text-success small mb-0 mt-2">
                            <i class="bi bi-arrow-up me-1"></i>
                            Lifetime purchases
                        </p>
                    </div>
                    <div class="progress-ring">
                        <svg viewBox="0 0 120 120">
                            <defs>
                                <linearGradient id="gradient1" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#198754" />
                                    <stop offset="100%" stop-color="#25a76b" />
                                </linearGradient>
                            </defs>
                            <circle class="progress-ring-bg" cx="60" cy="60" r="52" />
                            <circle class="progress-ring-fg" cx="60" cy="60" r="52" 
                                    stroke-dasharray="327" 
                                    stroke-dashoffset="<?php echo 327 - (($total_orders > 50 ? 50 : $total_orders) / 50 * 327); ?>" 
                                    stroke="url(#gradient1)" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-xl-3 mb-4">
            <div class="stat-card slide-up" style="animation-delay: 0.2s;">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-icon secondary">
                            <i class="bi bi-currency-exchange" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="text-muted mb-2">Total Value</h6>
                        <h2 class="fw-bold mb-0"><?php echo formatCurrency($total_spent); ?></h2>
                        <p class="text-primary small mb-0 mt-2">
                            <i class="bi bi-graph-up me-1"></i>
                            Successful orders
                        </p>
                    </div>
                    <div class="mt-3">
                        <?php if ($delivery_stats && $delivery_stats['avg_delivery_days']): ?>
                            <span class="delivery-badge">
                                <i class="bi bi-truck me-1"></i>
                                Avg. <?php echo round($delivery_stats['avg_delivery_days']); ?> days delivery
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-xl-3 mb-4">
            <div class="stat-card slide-up" style="animation-delay: 0.3s;">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-icon warning">
                            <i class="bi bi-heart" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="text-muted mb-2">Wishlist Items</h6>
                        <h2 class="fw-bold mb-0"><?php echo $wishlist_count; ?></h2>
                        <p class="text-warning small mb-0 mt-2">
                            <i class="bi bi-bookmark me-1"></i>
                            Saved for later
                        </p>
                    </div>
                    <div class="text-end">
                        <a href="wishlist/view-wishlist.php" class="btn btn-sm btn-outline-warning">
                            View All
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-xl-3 mb-4">
            <div class="stat-card slide-up" style="animation-delay: 0.4s;">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-icon danger">
                            <i class="bi bi-clock-history" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="text-muted mb-2">Active Orders</h6>
                        <h2 class="fw-bold mb-0"><?php echo count(array_filter($recent_orders, fn($o) => !in_array($o['status'], ['delivered', 'cancelled']))); ?></h2>
                        <p class="text-danger small mb-0 mt-2">
                            <i class="bi bi-hourglass-split me-1"></i>
                            In progress
                        </p>
                    </div>
                    <div class="text-end">
                        <a href="orders/order-history.php?status=pending" class="btn btn-sm btn-outline-danger">
                            Track
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts & Recent Orders -->
    <div class="row g-4 mb-4">
        <!-- Order Status Chart -->
        <div class="col-xl-8">
            <div class="chart-container slide-up" style="animation-delay: 0.5s;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-1">Order Status Overview</h5>
                        <p class="text-muted mb-0">Distribution of your orders by status</p>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            This Month
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">This Week</a></li>
                            <li><a class="dropdown-item" href="#">This Month</a></li>
                            <li><a class="dropdown-item" href="#">This Year</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="row g-4">
                    <?php 
                    $status_data = [
                        'pending' => ['Pending', 'bi-clock', 'warning'],
                        'confirmed' => ['Confirmed', 'bi-check-circle', 'info'],
                        'processing' => ['Processing', 'bi-gear', 'primary'],
                        'shipped' => ['Shipped', 'bi-truck', 'info'],
                        'delivered' => ['Delivered', 'bi-check2-circle', 'success'],
                        'cancelled' => ['Cancelled', 'bi-x-circle', 'danger']
                    ];
                    
                    foreach($status_data as $status => $info):
                        $count = 0;
                        foreach($order_stats as $stat) {
                            if ($stat['status'] === $status) {
                                $count = $stat['count'];
                                break;
                            }
                        }
                        $percentage = $total_orders > 0 ? ($count / $total_orders * 100) : 0;
                    ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="activity-item">
                                <div class="activity-icon bg-<?php echo $info[2]; ?> bg-opacity-10 text-<?php echo $info[2]; ?>">
                                    <i class="bi <?php echo $info[1]; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-semibold mb-1"><?php echo $info[0]; ?></h6>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold"><?php echo $count; ?></span>
                                        <span class="text-muted"><?php echo round($percentage); ?>%</span>
                                    </div>
                                    <div class="progress mt-2" style="height: 4px;">
                                        <div class="progress-bar bg-<?php echo $info[2]; ?>" 
                                             style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Monthly Spending -->
        <div class="col-xl-4">
            <div class="chart-container slide-up" style="animation-delay: 0.6s;">
                <h5 class="fw-bold mb-4">Monthly Spending</h5>
                
                <?php if (!empty($monthly_spending)): ?>
                    <div style="height: 200px;">
                        <canvas id="spendingChart"></canvas>
                    </div>
                    <div class="row mt-4">
                        <?php 
                        $current_month = date('Y-m');
                        $last_month = date('Y-m', strtotime('-1 month'));
                        $current_total = 0;
                        $last_total = 0;
                        
                        foreach($monthly_spending as $month) {
                            if ($month['month'] === $current_month) $current_total = $month['total'];
                            if ($month['month'] === $last_month) $last_total = $month['total'];
                        }
                        
                        $change = $last_total > 0 ? (($current_total - $last_total) / $last_total * 100) : 0;
                        ?>
                        <div class="col-6">
                            <div class="text-center">
                                <h3 class="fw-bold text-success"><?php echo formatCurrency($current_total); ?></h3>
                                <small class="text-muted">Current Month</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h6 class="fw-semibold <?php echo $change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <i class="bi bi-arrow-<?php echo $change >= 0 ? 'up' : 'down'; ?> me-1"></i>
                                    <?php echo abs(round($change)); ?>%
                                </h6>
                                <small class="text-muted">vs Last Month</small>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">No spending data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders & Quick Actions -->
    <div class="row g-4">
        <!-- Recent Orders -->
        <div class="col-lg-8">
            <div class="chart-container slide-up" style="animation-delay: 0.7s;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold mb-1">Recent Orders</h5>
                        <p class="text-muted mb-0">Latest purchases and their status</p>
                    </div>
                    <a href="orders/order-history.php" class="btn btn-sm btn-outline-success">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                
                <?php if ($recent_orders_count > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="border-0">Order Details</th>
                                    <th class="border-0 text-center">Date</th>
                                    <th class="border-0 text-center">Amount</th>
                                    <th class="border-0 text-center">Status</th>
                                    <th class="border-0 text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_orders, 0, 5) as $index => $order): ?>
                                    <tr class="border-bottom">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded p-2 me-3">
                                                    <span class="text-success fw-bold">#<?php echo $index + 1; ?></span>
                                                </div>
                                                <div>
                                                    <h6 class="fw-semibold mb-1"><?php echo $order['order_number']; ?></h6>
                                                    <?php 
                                                    $item_count = $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?", [$order['id']])['count'];
                                                    ?>
                                                    <small class="text-muted"><?php echo $item_count; ?> item<?php echo $item_count != 1 ? 's' : ''; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <small><?php echo formatDate($order['created_at'], 'M d'); ?></small>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="fw-bold text-success"><?php echo formatCurrency($order['total_amount']); ?></span>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php echo getOrderStatusBadge($order['status']); ?>
                                        </td>
                                        <td class="text-end align-middle">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="orders/order-details.php?order_number=<?php echo $order['order_number']; ?>" 
                                                   class="btn btn-outline-success">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="orders/track-order.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-outline-info">
                                                    <i class="bi bi-truck"></i>
                                                </a>
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                                        <i class="bi bi-x"></i>
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
                        <div class="mb-3">
                            <i class="bi bi-bag text-muted" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-3">No orders yet</h5>
                        <p class="text-muted mb-4">Start your journey with fresh agricultural products</p>
                        <a href="products/browse.php" class="btn btn-success px-4">
                            <i class="bi bi-cart-plus me-2"></i> Browse Products
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions & Reviews -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="chart-container mb-4 slide-up" style="animation-delay: 0.8s;">
                <h5 class="fw-bold mb-4">Quick Actions</h5>
                <div class="row g-3">
                    <div class="col-6">
                        <a href="products/browse.php" class="quick-action-btn">
                            <i class="bi bi-cart-plus text-success mb-3" style="font-size: 1.75rem;"></i>
                            <span class="fw-semibold">Shop Now</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="wishlist/view-wishlist.php" class="quick-action-btn">
                            <i class="bi bi-heart text-success mb-3" style="font-size: 1.75rem;"></i>
                            <span class="fw-semibold">Wishlist</span>
                            <?php if ($wishlist_count > 0): ?>
                                <span class="badge bg-success mt-2"><?php echo $wishlist_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="profile/personal-info.php" class="quick-action-btn">
                            <i class="bi bi-person text-success mb-3" style="font-size: 1.75rem;"></i>
                            <span class="fw-semibold">Profile</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="orders/track-order.php" class="quick-action-btn">
                            <i class="bi bi-truck text-success mb-3" style="font-size: 1.75rem;"></i>
                            <span class="fw-semibold">Track Order</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Reviews -->
            <?php if (!empty($recent_reviews)): ?>
            <div class="chart-container slide-up" style="animation-delay: 0.9s;">
                <h5 class="fw-bold mb-4">Recent Reviews</h5>
                <div class="vstack gap-3">
                    <?php foreach ($recent_reviews as $review): ?>
                        <div class="activity-item">
                            <div class="activity-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($review['product_name']); ?></h6>
                                <div class="d-flex align-items-center mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star-fill <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted small mb-0">
                                    "<?php echo htmlspecialchars(substr($review['comment'], 0, 60)); ?>..."
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Update current date and time
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    document.getElementById('currentDateTime').textContent = 
        now.toLocaleDateString('en-US', options) + ' • ' + 
        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

setInterval(updateDateTime, 60000);

// Initialize Spending Chart
<?php if (!empty($monthly_spending)): ?>
const ctx = document.getElementById('spendingChart').getContext('2d');
const labels = <?php echo json_encode(array_column($monthly_spending, 'month')); ?>;
const data = <?php echo json_encode(array_column($monthly_spending, 'total')); ?>;

const spendingChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels.reverse(),
        datasets: [{
            label: 'Spending',
            data: data.reverse(),
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#198754',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '₦' + context.parsed.y.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [5, 5]
                },
                ticks: {
                    callback: function(value) {
                        return '₦' + value.toLocaleString();
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});
<?php endif; ?>

// Cancel Order Function
function cancelOrder(orderId) {
    Swal.fire({
        title: 'Cancel Order?',
        text: "Are you sure you want to cancel this order? This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, cancel it!',
        cancelButtonText: 'Keep it',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
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
                    Swal.fire({
                        title: 'Cancelled!',
                        text: 'Your order has been cancelled.',
                        icon: 'success',
                        confirmButtonColor: '#198754',
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.error || 'Failed to cancel order',
                        icon: 'error',
                        confirmButtonColor: '#198754'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'Network error. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#198754'
                });
            });
        }
    });
}

// Initialize animations
document.addEventListener('DOMContentLoaded', function() {
    const animatedElements = document.querySelectorAll('.slide-up, .fade-in');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, { threshold: 0.1 });
    
    animatedElements.forEach(element => {
        observer.observe(element);
    });
});
</script>

<!-- Include SweetAlert2 for beautiful alerts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include '../includes/footer.php'; ?>