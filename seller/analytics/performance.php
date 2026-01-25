<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';

$db = new Database();

$seller_id = $_SESSION['user_id'];

// Performance metrics
$metrics = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT oi.order_id) as total_orders,
        SUM(oi.item_total) as total_revenue,
        AVG(oi.item_total) as avg_order_value,
        COUNT(DISTINCT o.buyer_id) as unique_customers,
        (SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'approved') as active_products,
        (SELECT AVG(rating) FROM product_reviews WHERE seller_id = ?) as avg_rating
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.seller_id = ? AND oi.status = 'delivered'
", [$seller_id, $seller_id, $seller_id]);

// Order fulfillment time
$fulfillment = $db->fetchOne("
    SELECT AVG(TIMESTAMPDIFF(HOUR, oi.created_at, oi.updated_at)) as avg_fulfillment_hours
    FROM order_items oi
    WHERE oi.seller_id = ? AND oi.status = 'delivered'
", [$seller_id]);

// Recent reviews
$recent_reviews = $db->fetchAll("
    SELECT pr.*, p.name as product_name, u.first_name, u.last_name
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    JOIN users u ON pr.user_id = u.id
    WHERE pr.seller_id = ?
    ORDER BY pr.created_at DESC
    LIMIT 5
", [$seller_id]);

$page_title = "Performance Metrics";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Performance Metrics</h1>
                <a href="sales-analytics.php" class="btn btn-outline-primary">Sales Analytics</a>
            </div>

            <!-- Performance Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Customer Rating</h5>
                            <h2 class="card-text">
                                <?php echo number_format($metrics['avg_rating'] ?? 0, 1); ?>/5
                            </h2>
                            <div class="small">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= ($metrics['avg_rating'] ?? 0) ? '-fill' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Total Customers</h5>
                            <h2 class="card-text"><?php echo $metrics['unique_customers'] ?? 0; ?></h2>
                            <div class="small">Unique buyers</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Avg Order Value</h5>
                            <h2 class="card-text"><?php echo formatCurrency($metrics['avg_order_value'] ?? 0); ?></h2>
                            <div class="small">Per order</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Fulfillment Time</h5>
                            <h2 class="card-text">
                                <?php
                                $hours = $fulfillment['avg_fulfillment_hours'] ?? 0;
                                if ($hours < 24) {
                                    echo round($hours) . 'h';
                                } else {
                                    echo round($hours / 24) . 'd';
                                }
                                ?>
                            </h2>
                            <div class="small">Order to delivery</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <!-- Order Performance -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Performance</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $order_stats = $db->fetchAll("
                                SELECT status, COUNT(*) as count
                                FROM order_items
                                WHERE seller_id = ?
                                GROUP BY status
                            ", [$seller_id]);
                            ?>
                            
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $total_orders = array_sum(array_column($order_stats, 'count'));
                                        foreach ($order_stats as $stat):
                                            $percentage = $total_orders > 0 ? ($stat['count'] / $total_orders) * 100 : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo getOrderStatusBadge($stat['status']); ?></td>
                                                <td><?php echo $stat['count']; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%">
                                                            <?php echo round($percentage, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Product Performance -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Product Performance</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $product_performance = $db->fetchAll("
                                SELECT p.name, 
                                       COUNT(oi.id) as orders,
                                       SUM(oi.quantity) as units_sold,
                                       SUM(oi.item_total) as revenue
                                FROM products p
                                LEFT JOIN order_items oi ON p.id = oi.product_id AND oi.status = 'delivered'
                                WHERE p.seller_id = ?
                                GROUP BY p.id
                                ORDER BY revenue DESC
                                LIMIT 5
                            ", [$seller_id]);
                            ?>
                            
                            <?php if ($product_performance): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Orders</th>
                                                <th>Units</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($product_performance as $product): ?>
                                                <tr>
                                                    <td><?php echo $product['name']; ?></td>
                                                    <td><?php echo $product['orders']; ?></td>
                                                    <td><?php echo $product['units_sold']; ?></td>
                                                    <td><?php echo formatCurrency($product['revenue']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No product performance data</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Customer Reviews -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Customer Reviews</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_reviews): ?>
                                <div class="reviews-container">
                                    <?php foreach ($recent_reviews as $review): ?>
                                        <div class="review-item mb-3 pb-3 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo $review['first_name'] . ' ' . $review['last_name']; ?></strong>
                                                    <br>
                                                    <small class="text-muted">on <?php echo $review['product_name']; ?></small>
                                                </div>
                                                <div class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <p class="mt-2 mb-1"><?php echo $review['comment']; ?></p>
                                            <small class="text-muted"><?php echo formatDate($review['created_at']); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-chat-quote display-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">No reviews yet</h5>
                                    <p class="text-muted">Customer reviews will appear here</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Performance Tips -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Performance Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="tips-list">
                                <div class="tip-item mb-3">
                                    <h6 class="text-success">
                                        <i class="bi bi-lightbulb"></i> Improve Response Time
                                    </h6>
                                    <p class="small mb-0">Respond to customer inquiries within 24 hours to improve satisfaction.</p>
                                </div>
                                
                                <div class="tip-item mb-3">
                                    <h6 class="text-success">
                                        <i class="bi bi-lightbulb"></i> Optimize Product Listings
                                    </h6>
                                    <p class="small mb-0">Use high-quality images and detailed descriptions to increase conversions.</p>
                                </div>
                                
                                <div class="tip-item">
                                    <h6 class="text-success">
                                        <i class="bi bi-lightbulb"></i> Monitor Stock Levels
                                    </h6>
                                    <p class="small mb-0">Keep products in stock to avoid missing sales opportunities.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>