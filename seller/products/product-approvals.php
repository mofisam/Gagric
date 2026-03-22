<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get pending approvals with agricultural details
$pending_products = $db->fetchAll("
    SELECT 
        p.*,
        c.name as category_name,
        pa.admin_notes,
        pa.reviewed_at,
        pa.created_at as approval_request_date,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
        ag.grade,
        ag.is_organic,
        ag.harvest_date,
        ag.farming_method,
        ag.organic_certification_number
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN product_approvals pa ON p.id = pa.product_id AND pa.status = 'pending_review'
    LEFT JOIN product_agricultural_details ag ON p.id = ag.product_id
    WHERE p.seller_id = ? AND p.status = 'pending'
    ORDER BY p.created_at DESC
", [$seller_id]);

// Get approval statistics
$stats = [
    'pending' => count($pending_products),
    'approved_this_month' => $db->fetchOne("
        SELECT COUNT(*) as count FROM products 
        WHERE seller_id = ? AND status = 'approved' AND MONTH(approved_at) = MONTH(CURRENT_DATE())
    ", [$seller_id])['count'],
    'rejected_this_month' => $db->fetchOne("
        SELECT COUNT(*) as count FROM products 
        WHERE seller_id = ? AND status = 'rejected' AND MONTH(updated_at) = MONTH(CURRENT_DATE())
    ", [$seller_id])['count'],
    'avg_approval_time' => $db->fetchOne("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) as avg_hours
        FROM products 
        WHERE seller_id = ? AND status = 'approved' AND approved_at IS NOT NULL
    ", [$seller_id])['avg_hours'] ?? 0
];

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $stats['pending'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Product Approvals";
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-3" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0">Product Approvals</h1>
                        <small class="text-muted">Track pending product reviews</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Product Approvals</h1>
                    <p class="text-muted mb-0">Track products pending admin review</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshData">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <a href="add-product.php" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Add New Product
                    </a>
                </div>
            </div>

            <!-- Approval Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending Approval</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['pending']); ?></h3>
                                    <small class="text-warning">Awaiting review</small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-clock-history fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Approved This Month</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['approved_this_month']); ?></h3>
                                    <small class="text-success">Successfully approved</small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-check-circle fs-4 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Rejected This Month</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['rejected_this_month']); ?></h3>
                                    <small class="text-danger">Needs attention</small>
                                </div>
                                <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-x-circle fs-4 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Avg. Approval Time</h6>
                                    <h3 class="card-title mb-0">
                                        <?php 
                                        $hours = $stats['avg_approval_time'];
                                        if ($hours < 24) {
                                            echo round($hours) . 'h';
                                        } else {
                                            echo round($hours / 24, 1) . 'd';
                                        }
                                        ?>
                                    </h3>
                                    <small class="text-info">From submission</small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-hourglass-split fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Products Grid -->
            <?php if ($pending_products): ?>
                <div class="row g-4">
                    <?php foreach ($pending_products as $product): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm border-0 h-100 hover-card">
                                <!-- Product Image -->
                                <div class="position-relative">
                                    <?php if ($product['primary_image']): ?>
                                        <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $product['primary_image']; ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" 
                                             style="height: 200px;">
                                            <i class="bi bi-image text-muted fs-1"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status Badge -->
                                    <div class="position-absolute top-0 end-0 m-3">
                                        <span class="badge bg-warning">
                                            <i class="bi bi-clock me-1"></i> Pending
                                        </span>
                                    </div>
                                    
                                    <!-- Organic Badge -->
                                    <?php if ($product['is_organic']): ?>
                                        <div class="position-absolute top-0 start-0 m-3">
                                            <span class="badge bg-success">
                                                <i class="bi bi-flower1 me-1"></i> Organic
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <!-- Product Title & Category -->
                                    <div class="mb-3">
                                        <h5 class="card-title fw-bold mb-1"><?php echo htmlspecialchars($product['name']); ?></h5>
                                        <small class="text-muted">
                                            <i class="bi bi-tag me-1"></i>
                                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Price & Stock -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="border rounded p-2 text-center">
                                                <small class="text-muted d-block">Price</small>
                                                <span class="fw-bold text-success">
                                                    ₦<?php echo number_format($product['price_per_unit'], 2); ?>
                                                </span>
                                                <small class="text-muted">/<?php echo $product['unit']; ?></small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-2 text-center">
                                                <small class="text-muted d-block">Stock</small>
                                                <span class="fw-bold"><?php echo number_format($product['stock_quantity']); ?></span>
                                                <small class="text-muted"><?php echo $product['unit']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Agricultural Details -->
                                    <?php if ($product['grade'] || $product['farming_method']): ?>
                                        <div class="mb-3">
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php if ($product['grade']): ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-star me-1"></i>
                                                        Grade <?php echo $product['grade']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($product['farming_method']): ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-tree me-1"></i>
                                                        <?php echo ucfirst(str_replace('_', ' ', $product['farming_method'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($product['harvest_date']): ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        Harvest: <?php echo formatDate($product['harvest_date']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Admin Notes -->
                                    <?php if ($product['admin_notes']): ?>
                                        <div class="alert alert-warning alert-sm mb-3">
                                            <i class="bi bi-chat-dots me-1"></i>
                                            <strong>Admin Note:</strong>
                                            <p class="small mb-0 mt-1"><?php echo htmlspecialchars($product['admin_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Submission Info -->
                                    <div class="text-muted small mb-3">
                                        <i class="bi bi-calendar-plus me-1"></i>
                                        Submitted: <?php echo formatDate($product['created_at'], 'M j, Y \a\t h:i A'); ?>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-white border-0 pt-0">
                                    <div class="d-grid gap-2 d-flex">
                                        <a href="edit-product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-primary flex-grow-1">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </a>
                                        <a href="view-product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-secondary flex-grow-1">
                                            <i class="bi bi-eye me-1"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle display-1 text-success"></i>
                        </div>
                        <h3 class="text-success mb-3">No Pending Approvals!</h3>
                        <p class="text-muted mb-4">All your products have been reviewed by the admin.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="manage-products.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-seam me-1"></i> View All Products
                            </a>
                            <a href="add-product.php" class="btn btn-success">
                                <i class="bi bi-plus-circle me-1"></i> Add New Product
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Approved Products -->
                <?php
                $recent_approved = $db->fetchAll("
                    SELECT name, price_per_unit, unit, approved_at 
                    FROM products 
                    WHERE seller_id = ? AND status = 'approved' 
                    ORDER BY approved_at DESC 
                    LIMIT 5
                ", [$seller_id]);
                ?>
                
                <?php if ($recent_approved): ?>
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white border-0 pb-2">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-check-circle me-2 text-success"></i>
                            Recently Approved Products
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Approved Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_approved as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td>₦<?php echo number_format($product['price_per_unit'], 2); ?>/<?php echo $product['unit']; ?></td>
                                        <td><?php echo formatDate($product['approved_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Approval Tips -->
            <?php if ($pending_products): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle text-primary fs-3 me-3"></i>
                                <div>
                                    <strong class="d-block mb-1">Approval Tips:</strong>
                                    <ul class="mb-0 small">
                                        <li>Products are reviewed within 24-48 hours</li>
                                        <li>Ensure all product details are accurate and complete</li>
                                        <li>High-quality images increase approval chances</li>
                                        <li>You'll receive a notification when your product is approved</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh button
    document.getElementById('refreshData')?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
        btn.disabled = true;
        setTimeout(() => window.location.reload(), 500);
    });
});

// Add custom styles
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .hover-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .hover-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .alert-sm {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    @media (max-width: 768px) {
        .card-img-top {
            height: 150px !important;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>