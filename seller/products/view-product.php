<?php
// seller/products/view-product.php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get product details with all related information
$product = $db->fetchOne("
    SELECT 
        p.*,
        c.name as category_name,
        c.id as category_id,
        sp.business_name as seller_business,
        u.email as seller_email,
        u.phone as seller_phone,
        ag.grade,
        ag.is_organic,
        ag.is_gmo,
        ag.organic_certification_number,
        ag.harvest_date,
        ag.expiry_date,
        ag.shelf_life_days,
        ag.farming_method,
        ag.irrigation_type,
        ag.storage_temperature,
        ag.storage_humidity,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id AND oi.status = 'delivered') as total_sales,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as total_orders,
        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi 
         WHERE oi.product_id = p.id AND oi.status IN ('pending', 'confirmed', 'shipped')) as reserved_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN seller_profiles sp ON p.seller_id = sp.user_id
    LEFT JOIN users u ON p.seller_id = u.id
    LEFT JOIN product_agricultural_details ag ON p.id = ag.product_id
    WHERE p.id = ? AND p.seller_id = ?
", [$product_id, $seller_id]);

if (!$product) {
    setFlashMessage('Product not found or access denied', 'danger');
    header('Location: manage-products.php');
    exit;
}

// Get product images
$product_images = $db->fetchAll("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, sort_order ASC
", [$product_id]);

// Get product specifications/attributes
$specifications = $db->fetchAll("
    SELECT ps.*, pa.attribute_name, pa.attribute_type
    FROM product_specifications ps
    JOIN product_attributes pa ON ps.attribute_id = pa.id
    WHERE ps.product_id = ?
    ORDER BY pa.sort_order
", [$product_id]);

// Get bulk pricing if available
$bulk_pricing = $db->fetchAll("
    SELECT * FROM product_bulk_pricing 
    WHERE product_id = ? 
    ORDER BY min_quantity ASC
", [$product_id]);

// Get product reviews from seller_ratings (linked via order_items)
$reviews = $db->fetchAll("
    SELECT 
        sr.*,
        u.first_name,
        u.last_name,
        u.profile_image as reviewer_avatar,
        o.order_number,
        o.created_at as order_date
    FROM seller_ratings sr
    JOIN orders o ON sr.order_id = o.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN users u ON sr.buyer_id = u.id
    WHERE oi.product_id = ? AND sr.seller_id = ?
    ORDER BY sr.created_at DESC
    LIMIT 10
", [$product_id, $seller_id]);

// Calculate average rating for this product specifically
$product_rating = $db->fetchOne("
    SELECT 
        AVG(rating) as avg_rating,
        COUNT(*) as total_reviews
    FROM seller_ratings sr
    JOIN orders o ON sr.order_id = o.id
    JOIN order_items oi ON o.id = oi.order_id
    WHERE oi.product_id = ? AND sr.seller_id = ?
", [$product_id, $seller_id]);

// Get similar products (same category, exclude current)
$similar_products = $db->fetchAll("
    SELECT 
        p.id,
        p.name,
        p.price_per_unit,
        p.unit,
        p.stock_quantity,
        p.status,
        (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
    FROM products p
    WHERE p.category_id = ? AND p.id != ? AND p.seller_id = ? AND p.status = 'approved'
    LIMIT 4
", [$product['category_id'], $product_id, $seller_id]);

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

// Determine stock status
if ($product['stock_quantity'] <= 0) {
    $stock_status = 'out_of_stock';
    $stock_class = 'danger';
    $stock_text = 'Out of Stock';
} elseif ($product['stock_quantity'] <= $product['low_stock_alert_level']) {
    $stock_status = 'low_stock';
    $stock_class = 'warning';
    $stock_text = 'Low Stock';
} else {
    $stock_status = 'in_stock';
    $stock_class = 'success';
    $stock_text = 'In Stock';
}

// Product status badge
$status_badge = [
    'draft' => 'secondary',
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'suspended' => 'danger'
];

$page_title = $product['name'] . " - View Product";
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
                    <div class="flex-grow-1">
                        <h1 class="h5 mb-0"><?php echo htmlspecialchars(substr($product['name'], 0, 30)) . '...'; ?></h1>
                        <small class="text-muted">View Product Details</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <div class="d-flex align-items-center">
                        <h1 class="h2 mb-1"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <span class="ms-3 badge bg-<?php echo $status_badge[$product['status']]; ?> fs-6">
                            <?php echo ucfirst($product['status']); ?>
                        </span>
                    </div>
                    <p class="text-muted mb-0">
                        <i class="bi bi-tag me-1"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                        • <i class="bi bi-calendar me-1"></i> Added <?php echo formatDate($product['created_at'], 'M j, Y'); ?>
                    </p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="manage-products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <a href="edit-product.php?id=<?php echo $product_id; ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil-square me-1"></i> Edit
                        </a>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Actions
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="#" onclick="duplicateProduct(<?php echo $product_id; ?>)">
                                    <i class="bi bi-files me-2"></i> Duplicate Product
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="toggleProductStatus(<?php echo $product_id; ?>, '<?php echo $product['status']; ?>')">
                                    <i class="bi bi-power me-2"></i> 
                                    <?php echo $product['status'] == 'suspended' ? 'Activate' : 'Suspend'; ?> Product
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="#" onclick="deleteProduct(<?php echo $product_id; ?>)">
                                    <i class="bi bi-trash me-2"></i> Delete Product
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Price</h6>
                                    <h3 class="card-title mb-0">₦<?php echo number_format($product['price_per_unit'], 2); ?></h3>
                                    <small class="text-primary">per <?php echo $product['unit']; ?></small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-currency-dollar fs-4 text-primary"></i>
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
                                    <h6 class="card-subtitle text-muted mb-1">Stock</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($product['stock_quantity']); ?></h3>
                                    <small class="text-<?php echo $stock_class; ?>">
                                        <?php echo $stock_text; ?> • 
                                        <?php 
                                        $available = $product['stock_quantity'] - ($product['reserved_stock'] ?? 0);
                                        echo number_format(max($available, 0)); ?> available
                                    </small>
                                </div>
                                <div class="bg-<?php echo $stock_class; ?> bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-box-seam fs-4 text-<?php echo $stock_class; ?>"></i>
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
                                    <h6 class="card-subtitle text-muted mb-1">Total Sales</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($product['total_sales'] ?? 0); ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-cart-check me-1"></i>
                                        <?php echo number_format($product['total_orders'] ?? 0); ?> orders
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-graph-up-arrow fs-4 text-success"></i>
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
                                    <h6 class="card-subtitle text-muted mb-1">Rating</h6>
                                    <div class="d-flex align-items-center">
                                        <h3 class="card-title mb-0 me-2"><?php echo number_format($product_rating['avg_rating'] ?? 0, 1); ?></h3>
                                        <div>
                                            <?php 
                                            $rating = round($product_rating['avg_rating'] ?? 0);
                                            for($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <i class="bi bi-star<?php echo $i <= $rating ? '-fill' : ''; ?> text-warning small"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        Based on <?php echo number_format($product_rating['total_reviews'] ?? 0); ?> reviews
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-star fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row g-4">
                <!-- Left Column - Product Images & Details -->
                <div class="col-lg-8">
                    <!-- Product Images Gallery -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-images me-2 text-primary"></i>
                                Product Images
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($product_images)): ?>
                                <div class="text-center py-4 bg-light rounded">
                                    <i class="bi bi-image display-1 text-muted"></i>
                                    <p class="text-muted mt-2">No images uploaded</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <!-- Main image -->
                                    <div class="col-12">
                                        <?php 
                                        $main_image = array_filter($product_images, fn($img) => $img['is_primary']);
                                        $main_image = reset($main_image) ?: $product_images[0];
                                        ?>
                                        <div class="main-image-container text-center border rounded p-3 bg-light">
                                            <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $main_image['image_path']; ?>" 
                                                 alt="<?php echo htmlspecialchars($main_image['alt_text'] ?? $product['name']); ?>"
                                                 class="img-fluid" style="max-height: 400px; object-fit: contain;">
                                        </div>
                                    </div>
                                    
                                    <!-- Thumbnail images -->
                                    <?php if (count($product_images) > 1): ?>
                                        <div class="col-12">
                                            <div class="row g-2">
                                                <?php foreach ($product_images as $image): ?>
                                                    <div class="col-3">
                                                        <div class="thumbnail-container border rounded p-1 <?php echo $image['is_primary'] ? 'border-primary' : ''; ?>">
                                                            <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $image['image_path']; ?>" 
                                                                 alt="<?php echo htmlspecialchars($image['alt_text'] ?? ''); ?>"
                                                                 class="img-fluid cursor-pointer" style="height: 80px; width: 100%; object-fit: cover;"
                                                                 onclick="showMainImage('<?php echo $image['image_path']; ?>')">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Product Description -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-file-text me-2 text-primary"></i>
                                Description
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($product['description']): ?>
                                <div class="description-content">
                                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No description provided.</p>
                            <?php endif; ?>
                            
                            <?php if ($product['short_description']): ?>
                                <hr>
                                <h6 class="text-muted mb-2">Short Description</h6>
                                <p class="mb-0"><?php echo htmlspecialchars($product['short_description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Agricultural Details -->
                    <?php if ($product['grade'] || $product['is_organic'] || $product['harvest_date']): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-tree me-2 text-success"></i>
                                Agricultural Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if ($product['grade']): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <span class="badge bg-primary">Grade</span>
                                            </div>
                                            <div>
                                                <strong><?php echo $product['grade']; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['is_organic']): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <span class="badge bg-success">Organic</span>
                                            </div>
                                            <div>
                                                <strong>
                                                    <?php echo $product['is_organic'] ? 'Yes' : 'No'; ?>
                                                    <?php if ($product['organic_certification_number']): ?>
                                                        <br><small class="text-muted">Cert: <?php echo $product['organic_certification_number']; ?></small>
                                                    <?php endif; ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['is_gmo'] !== null): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <span class="badge bg-info">GMO</span>
                                            </div>
                                            <div>
                                                <strong><?php echo $product['is_gmo'] ? 'Yes' : 'No'; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['harvest_date']): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <span class="badge bg-warning">Harvest</span>
                                            </div>
                                            <div>
                                                <strong><?php echo formatDate($product['harvest_date']); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['expiry_date']): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <span class="badge bg-danger">Expires</span>
                                            </div>
                                            <div>
                                                <strong><?php echo formatDate($product['expiry_date']); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['shelf_life_days']): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <span class="badge bg-secondary">Shelf Life</span>
                                            </div>
                                            <div>
                                                <strong><?php echo $product['shelf_life_days']; ?> days</strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['farming_method']): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex mb-3">
                                            <div class="me-3">
                                                <span class="badge bg-success">Farming</span>
                                            </div>
                                            <div>
                                                <strong><?php echo ucfirst(str_replace('_', ' ', $product['farming_method'])); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($product['storage_temperature'] || $product['storage_humidity']): ?>
                                <hr>
                                <h6 class="text-muted mb-3">Storage Requirements</h6>
                                <div class="row">
                                    <?php if ($product['storage_temperature']): ?>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Temperature</small>
                                            <strong><?php echo $product['storage_temperature']; ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($product['storage_humidity']): ?>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">Humidity</small>
                                            <strong><?php echo $product['storage_humidity']; ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Product Specifications -->
                    <?php if (!empty($specifications)): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-list-check me-2 text-primary"></i>
                                Specifications
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($specifications as $spec): ?>
                                            <tr>
                                                <th style="width: 40%" class="text-muted"><?php echo $spec['attribute_name']; ?></th>
                                                <td><?php echo htmlspecialchars($spec['attribute_value']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Bulk Pricing -->
                    <?php if (!empty($bulk_pricing)): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-tags me-2 text-primary"></i>
                                Bulk Pricing
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Min Quantity</th>
                                            <th>Max Quantity</th>
                                            <th>Price per <?php echo $product['unit']; ?></th>
                                            <th>Discount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bulk_pricing as $price): ?>
                                            <tr>
                                                <td><?php echo number_format($price['min_quantity']); ?></td>
                                                <td><?php echo $price['max_quantity'] ? number_format($price['max_quantity']) : 'Any'; ?></td>
                                                <td class="fw-bold text-success">₦<?php echo number_format($price['price_per_unit'], 2); ?></td>
                                                <td>
                                                    <?php if ($price['discount_percentage']): ?>
                                                        <span class="badge bg-success"><?php echo $price['discount_percentage']; ?>% off</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Customer Reviews -->
                    <?php if (!empty($reviews)): ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-star me-2 text-warning"></i>
                                Customer Reviews
                            </h5>
                            <span class="badge bg-warning"><?php echo count($reviews); ?> Reviews</span>
                        </div>
                        <div class="card-body">
                            <div class="reviews-list">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-item mb-4 pb-3 border-bottom">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <?php if ($review['reviewer_avatar']): ?>
                                                    <img src="<?php echo $review['reviewer_avatar']; ?>" 
                                                         class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 48px; height: 48px;">
                                                        <i class="bi bi-person text-muted fs-4"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                                        <span class="text-muted mx-2">•</span>
                                                        <small class="text-muted">Order #<?php echo $review['order_number']; ?></small>
                                                    </div>
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                <?php if ($review['review']): ?>
                                                    <p class="mt-2 mb-1"><?php echo htmlspecialchars($review['review']); ?></p>
                                                <?php endif; ?>
                                                <small class="text-muted"><?php echo formatDate($review['created_at'], 'M j, Y'); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column - Product Info & Actions -->
                <div class="col-lg-4">
                    <!-- Product Information Card -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2 text-primary"></i>
                                Product Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Product Type</span>
                                    <span class="fw-bold"><?php echo ucfirst($product['product_type']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Category</span>
                                    <span class="fw-bold"><?php echo $product['category_name']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Unit</span>
                                    <span class="fw-bold"><?php echo $product['unit']; ?></span>
                                </li>
                                <?php if ($product['variety']): ?>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Variety</span>
                                    <span class="fw-bold"><?php echo $product['variety']; ?></span>
                                </li>
                                <?php endif; ?>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Price per Unit</span>
                                    <span class="fw-bold text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Min Order</span>
                                    <span class="fw-bold"><?php echo number_format($product['min_order_quantity']); ?> <?php echo $product['unit']; ?></span>
                                </li>
                                <?php if ($product['max_order_quantity']): ?>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Max Order</span>
                                    <span class="fw-bold"><?php echo number_format($product['max_order_quantity']); ?> <?php echo $product['unit']; ?></span>
                                </li>
                                <?php endif; ?>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Stock Status</span>
                                    <span class="badge bg-<?php echo $stock_class; ?>"><?php echo $stock_text; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Featured</span>
                                    <span class="fw-bold">
                                        <?php if ($product['is_featured']): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i> Yes
                                        <?php else: ?>
                                            <i class="bi bi-x-circle-fill text-secondary"></i> No
                                        <?php endif; ?>
                                    </span>
                                </li>
                                <?php if ($product['weight_kg']): ?>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Weight (kg)</span>
                                    <span class="fw-bold"><?php echo $product['weight_kg']; ?> kg</span>
                                </li>
                                <?php endif; ?>
                                <?php if ($product['dimensions']): ?>
                                <li class="list-group-item d-flex justify-content-between px-0">
                                    <span class="text-muted">Dimensions</span>
                                    <span class="fw-bold"><?php echo $product['dimensions']; ?></span>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Admin Notes (if any) -->
                    <?php if ($product['admin_notes']): ?>
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-chat-text me-2 text-primary"></i>
                                Admin Notes
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                <?php echo nl2br(htmlspecialchars($product['admin_notes'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SEO Information -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-search me-2 text-primary"></i>
                                SEO Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted d-block">Slug</small>
                                <code>/product/<?php echo $product['slug']; ?></code>
                            </div>
                            <div>
                                <small class="text-muted d-block">Meta Description</small>
                                <p class="mb-0 small"><?php echo $product['short_description'] ?: 'No meta description'; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Similar Products -->
                    <?php if (!empty($similar_products)): ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-files me-2 text-primary"></i>
                                Similar Products
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <?php foreach ($similar_products as $similar): ?>
                                    <div class="col-12">
                                        <div class="d-flex align-items-center p-2 border rounded">
                                            <?php if ($similar['product_image']): ?>
                                                <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $similar['product_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($similar['name']); ?>"
                                                     class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="bi bi-box text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <a href="view-product.php?id=<?php echo $similar['id']; ?>" class="text-decoration-none">
                                                    <small class="fw-bold d-block"><?php echo htmlspecialchars($similar['name']); ?></small>
                                                    <small class="text-success">₦<?php echo number_format($similar['price_per_unit'], 2); ?>/<?php echo $similar['unit']; ?></small>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Show main image when thumbnail is clicked
function showMainImage(imagePath) {
    const mainImageContainer = document.querySelector('.main-image-container img');
    if (mainImageContainer) {
        mainImageContainer.src = '<?php echo BASE_URL; ?>/uploads/products/' + imagePath;
    }
}

// Duplicate product
function duplicateProduct(productId) {
    if (confirm('Are you sure you want to duplicate this product?')) {
        window.location.href = 'duplicate-product.php?id=' + productId;
    }
}

// Toggle product status (activate/suspend)
function toggleProductStatus(productId, currentStatus) {
    const action = currentStatus === 'suspended' ? 'activate' : 'suspend';
    if (confirm(`Are you sure you want to ${action} this product?`)) {
        window.location.href = `toggle-product-status.php?id=${productId}&action=${action}`;
    }
}

// Delete product
function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        window.location.href = 'delete-product.php?id=' + productId;
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Add custom styles
const style = document.createElement('style');
style.textContent = `
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .main-image-container {
        cursor: pointer;
    }
    
    .thumbnail-container {
        cursor: pointer;
        transition: transform 0.2s;
    }
    
    .thumbnail-container:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .thumbnail-container.border-primary {
        box-shadow: 0 0 0 2px #4e73df;
    }
    
    .review-item:hover {
        background-color: rgba(0,0,0,0.02);
    }
    
    .list-group-item {
        background: transparent;
        border-color: #e9ecef;
    }
    
    .cursor-pointer {
        cursor: pointer;
    }
    
    @media (max-width: 768px) {
        .main-image-container img {
            max-height: 250px;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>