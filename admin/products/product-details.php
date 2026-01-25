<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = new Database();

$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) {
    setFlashMessage('Product ID required', 'error');
    header('Location: manage-products.php');
    exit;
}

// Get product details
$product = $db->fetchOne("
    SELECT p.*, 
           sp.business_name, sp.business_description,
           u.first_name, u.last_name, u.email, u.phone,
           c.name as category_name,
           pad.grade, pad.is_organic, pad.is_gmo, pad.organic_certification_number,
           pad.harvest_date, pad.expiry_date, pad.shelf_life_days,
           pad.farming_method, pad.irrigation_type,
           pad.storage_temperature, pad.storage_humidity
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN users u ON p.seller_id = u.id 
    JOIN categories c ON p.category_id = c.id 
    LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id 
    WHERE p.id = ?
", [$product_id]);

if (!$product) {
    setFlashMessage('Product not found', 'error');
    header('Location: manage-products.php');
    exit;
}

// Get product images
$product_images = $db->fetchAll("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, sort_order ASC
", [$product_id]);

// Get product specifications
$specifications = $db->fetchAll("
    SELECT pa.attribute_name, ps.attribute_value 
    FROM product_specifications ps 
    JOIN product_attributes pa ON ps.attribute_id = pa.id 
    WHERE ps.product_id = ?
", [$product_id]);

// Get bulk pricing
$bulk_pricing = $db->fetchAll("
    SELECT * FROM product_bulk_pricing 
    WHERE product_id = ? 
    ORDER BY min_quantity ASC
", [$product_id]);

// Handle product actions
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    $db->query(
        "UPDATE products SET status = ?, admin_notes = ? WHERE id = ?",
        [$new_status, $admin_notes, $product_id]
    );
    
    setFlashMessage('Product status updated successfully', 'success');
    header("Location: product-details.php?id=$product_id");
    exit;
}

if (isset($_POST['toggle_featured'])) {
    $is_featured = $product['is_featured'] ? 0 : 1;
    $db->query("UPDATE products SET is_featured = ? WHERE id = ?", [$is_featured, $product_id]);
    
    setFlashMessage('Product featured status updated', 'success');
    header("Location: product-details.php?id=$product_id");
    exit;
}

$page_title = "Product Details - " . $product['name'];
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile page header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-2" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="flex-grow-1">
                        <h1 class="h5 mb-0 text-center">Product Details</h1>
                        <small class="text-muted d-block text-center">ID: #<?php echo $product_id; ?></small>
                    </div>
                    <a href="manage-products.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Product Details</h1>
                    <p class="text-muted mb-0">ID: #<?php echo $product_id; ?> • <?php echo htmlspecialchars($product['name']); ?></p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-products.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Products
                    </a>
                </div>
            </div>

            <!-- Status Banner -->
            <div class="d-md-none mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-<?php 
                            echo $product['status'] === 'approved' ? 'success' : 
                                 ($product['status'] === 'pending' ? 'warning' : 
                                 ($product['status'] === 'rejected' ? 'danger' : 'secondary')); 
                        ?>">
                            <?php echo ucfirst($product['status']); ?>
                        </span>
                        <?php if ($product['is_featured']): ?>
                            <span class="badge bg-warning ms-1">
                                <i class="bi bi-star-fill me-1"></i> Featured
                            </span>
                        <?php endif; ?>
                    </div>
                    <strong class="text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?>/<?php echo $product['unit']; ?></strong>
                </div>
            </div>

            <!-- Product Header Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="row">
                        <!-- Product Images - Mobile Carousel -->
                        <div class="col-md-4 mb-3 mb-md-0">
                            <?php if (empty($product_images)): ?>
                                <div class="text-center text-muted py-4 border rounded">
                                    <i class="bi bi-image display-1"></i>
                                    <p class="mt-2">No images available</p>
                                </div>
                            <?php else: ?>
                                <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-inner rounded">
                                        <?php foreach ($product_images as $index => $image): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo BASE_URL . '/assets/uploads/products/' . htmlspecialchars($image['image_path']); ?>" 
                                                 class="d-block w-100" 
                                                 alt="<?php echo htmlspecialchars($image['alt_text'] ?? $product['name']); ?>"
                                                 style="height: 250px; object-fit: cover;">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($product_images) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                                        <span class="carousel-control-next-icon"></span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-2 text-center">
                                    <small class="text-muted">
                                        <?php echo count($product_images); ?> image(s)
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Info -->
                        <div class="col-md-8">
                            <div class="d-md-none mb-3">
                                <h4 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            </div>
                            
                            <div class="d-none d-md-block">
                                <h3 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="mb-3">
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                    <span class="badge bg-light text-dark"><?php echo ucfirst($product['product_type']); ?></span>
                                    <?php if ($product['variety']): ?>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($product['variety']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-2"><?php echo htmlspecialchars($product['short_description'] ?? substr($product['description'], 0, 150) . '...'); ?></p>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="border rounded p-2 text-center">
                                        <small class="text-muted d-block">Stock</small>
                                        <strong class="d-block"><?php echo number_format($product['stock_quantity'], 2); ?> <?php echo $product['unit']; ?></strong>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded p-2 text-center">
                                        <small class="text-muted d-block">Min Order</small>
                                        <strong class="d-block"><?php echo number_format($product['min_order_quantity'], 2); ?> <?php echo $product['unit']; ?></strong>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded p-2 text-center">
                                        <small class="text-muted d-block">Price</small>
                                        <strong class="d-block text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?></strong>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded p-2 text-center">
                                        <small class="text-muted d-block">Created</small>
                                        <strong class="d-block"><?php echo date('M j, Y', strtotime($product['created_at'])); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Update Status</label>
                            <div class="input-group">
                                <select class="form-select" name="status" required>
                                    <option value="draft" <?php echo $product['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="pending" <?php echo $product['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $product['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $product['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="suspended" <?php echo $product['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                                <button name="update_status" class="btn btn-primary">
                                    <i class="bi bi-check"></i>
                                    <span class="d-none d-md-inline">Update</span>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Featured Status</label>
                            <form method="POST" class="d-grid">
                                <button name="toggle_featured" class="btn btn-<?php echo $product['is_featured'] ? 'warning' : 'success'; ?>">
                                    <?php if ($product['is_featured']): ?>
                                        <i class="bi bi-star-fill me-1"></i> Remove Featured
                                    <?php else: ?>
                                        <i class="bi bi-star me-1"></i> Mark as Featured
                                    <?php endif; ?>
                                </button>
                            </form>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <a href="?id=<?php echo $product_id; ?>&duplicate=1" class="btn btn-outline-primary flex-fill">
                                    <i class="bi bi-copy me-1"></i> Duplicate
                                </a>
                                <a href="?id=<?php echo $product_id; ?>&delete=1" 
                                   class="btn btn-outline-danger flex-fill"
                                   onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Product Information Tabs -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0">
                            <ul class="nav nav-tabs nav-fill" id="productTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" 
                                            data-bs-target="#basic" type="button" role="tab">
                                        <i class="bi bi-info-circle d-md-none"></i>
                                        <span class="d-none d-md-inline">Basic Info</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="agricultural-tab" data-bs-toggle="tab" 
                                            data-bs-target="#agricultural" type="button" role="tab">
                                        <i class="bi bi-tree d-md-none"></i>
                                        <span class="d-none d-md-inline">Agricultural</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="seller-tab" data-bs-toggle="tab" 
                                            data-bs-target="#seller" type="button" role="tab">
                                        <i class="bi bi-shop d-md-none"></i>
                                        <span class="d-none d-md-inline">Seller</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="admin-tab" data-bs-toggle="tab" 
                                            data-bs-target="#admin" type="button" role="tab">
                                        <i class="bi bi-gear d-md-none"></i>
                                        <span class="d-none d-md-inline">Admin</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="productTabsContent">
                                <!-- Basic Information -->
                                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 mobile-optimized-table">
                                            <thead class="table-light d-none d-md-table-header-group">
                                                <tr>
                                                    <th width="40%">Field</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Product Name</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Product Name</strong>
                                                            <span><?php echo htmlspecialchars($product['name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['name']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Category</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Category</strong>
                                                            <span><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Product Type</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Product Type</strong>
                                                            <span><?php echo ucfirst($product['product_type']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo ucfirst($product['product_type']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Variety</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Variety</strong>
                                                            <span><?php echo htmlspecialchars($product['variety'] ?? 'N/A'); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['variety'] ?? 'N/A'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Price</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Price</strong>
                                                            <span class="text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?>/<?php echo $product['unit']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <strong class="text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?>/<?php echo $product['unit']; ?></strong>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Stock Quantity</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Stock Quantity</strong>
                                                            <span>
                                                                <?php echo number_format($product['stock_quantity'], 2); ?> <?php echo $product['unit']; ?>
                                                                <?php if ($product['stock_quantity'] <= $product['low_stock_alert_level']): ?>
                                                                    <span class="badge bg-danger ms-1">Low</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <?php echo number_format($product['stock_quantity'], 2); ?> <?php echo $product['unit']; ?>
                                                        <?php if ($product['stock_quantity'] <= $product['low_stock_alert_level']): ?>
                                                            <span class="badge bg-danger ms-2">Low Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Min Order</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Min Order</strong>
                                                            <span><?php echo number_format($product['min_order_quantity'], 2); ?> <?php echo $product['unit']; ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo number_format($product['min_order_quantity'], 2); ?> <?php echo $product['unit']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Max Order</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Max Order</strong>
                                                            <span><?php echo $product['max_order_quantity'] ? number_format($product['max_order_quantity'], 2) . ' ' . $product['unit'] : 'No limit'; ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <?php echo $product['max_order_quantity'] ? number_format($product['max_order_quantity'], 2) . ' ' . $product['unit'] : 'No limit'; ?>
                                                    </td>
                                                </tr>
                                                <?php if ($product['weight_kg']): ?>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Weight</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Weight</strong>
                                                            <span><?php echo number_format($product['weight_kg'], 2); ?> kg</span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo number_format($product['weight_kg'], 2); ?> kg</td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if ($product['dimensions']): ?>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Dimensions</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Dimensions</strong>
                                                            <span><?php echo htmlspecialchars($product['dimensions']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['dimensions']); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <h6>Description</h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                                        
                                        <?php if ($product['short_description']): ?>
                                        <h6 class="mt-3">Short Description</h6>
                                        <p class="mb-0"><?php echo htmlspecialchars($product['short_description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($specifications)): ?>
                                    <div class="mt-4">
                                        <h6>Specifications</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm mobile-optimized-table">
                                                <thead class="table-light d-none d-md-table-header-group">
                                                    <tr>
                                                        <th width="40%">Attribute</th>
                                                        <th>Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($specifications as $spec): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($spec['attribute_name']); ?></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong><?php echo htmlspecialchars($spec['attribute_name']); ?></strong>
                                                                <span><?php echo htmlspecialchars($spec['attribute_value']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($spec['attribute_value']); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($bulk_pricing)): ?>
                                    <div class="mt-4">
                                        <h6>Bulk Pricing</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm mobile-optimized-table">
                                                <thead class="table-light d-none d-md-table-header-group">
                                                    <tr>
                                                        <th>Quantity Range</th>
                                                        <th>Price</th>
                                                        <th>Discount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($bulk_pricing as $pricing): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell">
                                                            <?php echo number_format($pricing['min_quantity'], 2); ?> - 
                                                            <?php echo $pricing['max_quantity'] ? number_format($pricing['max_quantity'], 2) : '∞'; ?> <?php echo $product['unit']; ?>
                                                        </td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <strong>Quantity</strong>
                                                                <span>
                                                                    <?php echo number_format($pricing['min_quantity'], 2); ?> - 
                                                                    <?php echo $pricing['max_quantity'] ? number_format($pricing['max_quantity'], 2) : '∞'; ?> <?php echo $product['unit']; ?>
                                                                </span>
                                                            </div>
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Price</strong>
                                                                <span class="text-success">₦<?php echo number_format($pricing['price_per_unit'], 2); ?></span>
                                                            </div>
                                                            <?php if ($pricing['discount_percentage']): ?>
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Discount</strong>
                                                                <span class="text-success"><?php echo number_format($pricing['discount_percentage'], 1); ?>%</span>
                                                            </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <span class="text-success">₦<?php echo number_format($pricing['price_per_unit'], 2); ?></span>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <?php if ($pricing['discount_percentage']): ?>
                                                                <span class="text-success"><?php echo number_format($pricing['discount_percentage'], 1); ?>%</span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Agricultural Details -->
                                <div class="tab-pane fade" id="agricultural" role="tabpanel">
                                    <?php if ($product['grade'] || $product['is_organic'] || $product['harvest_date']): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0 mobile-optimized-table">
                                                <thead class="table-light d-none d-md-table-header-group">
                                                    <tr>
                                                        <th width="40%">Field</th>
                                                        <th>Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if ($product['grade']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Grade</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Grade</strong>
                                                                <span class="badge bg-success">Grade <?php echo $product['grade']; ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <span class="badge bg-success">Grade <?php echo $product['grade']; ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Organic</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Organic</strong>
                                                                <span>
                                                                    <?php if ($product['is_organic']): ?>
                                                                        <span class="badge bg-success">Yes</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-secondary">No</span>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <?php if ($product['is_organic']): ?>
                                                                <span class="badge bg-success">Yes</span>
                                                                <?php if ($product['organic_certification_number']): ?>
                                                                    <br><small class="text-muted">Cert: <?php echo htmlspecialchars($product['organic_certification_number']); ?></small>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">No</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>GMO</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>GMO</strong>
                                                                <span>
                                                                    <span class="badge bg-<?php echo $product['is_gmo'] ? 'warning' : 'success'; ?>">
                                                                        <?php echo $product['is_gmo'] ? 'Yes' : 'No'; ?>
                                                                    </span>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <span class="badge bg-<?php echo $product['is_gmo'] ? 'warning' : 'success'; ?>">
                                                                <?php echo $product['is_gmo'] ? 'Yes' : 'No'; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php if ($product['harvest_date']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Harvest Date</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Harvest Date</strong>
                                                                <span><?php echo date('M j, Y', strtotime($product['harvest_date'])); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo date('M j, Y', strtotime($product['harvest_date'])); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if ($product['expiry_date']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Expiry Date</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Expiry Date</strong>
                                                                <span>
                                                                    <?php echo date('M j, Y', strtotime($product['expiry_date'])); ?>
                                                                    <?php
                                                                    $today = new DateTime();
                                                                    $expiry = new DateTime($product['expiry_date']);
                                                                    $days_until_expiry = $today->diff($expiry)->days;
                                                                    
                                                                    if ($expiry < $today) {
                                                                        echo '<span class="badge bg-danger ms-1">Expired</span>';
                                                                    } elseif ($days_until_expiry <= 7) {
                                                                        echo '<span class="badge bg-warning ms-1">Soon</span>';
                                                                    }
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell">
                                                            <?php echo date('M j, Y', strtotime($product['expiry_date'])); ?>
                                                            <?php
                                                            if ($expiry < $today) {
                                                                echo '<span class="badge bg-danger ms-2">Expired</span>';
                                                            } elseif ($days_until_expiry <= 7) {
                                                                echo '<span class="badge bg-warning ms-2">Expiring Soon</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if ($product['shelf_life_days']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Shelf Life</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Shelf Life</strong>
                                                                <span><?php echo $product['shelf_life_days']; ?> days</span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo $product['shelf_life_days']; ?> days</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if ($product['farming_method']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Farming Method</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Farming Method</strong>
                                                                <span><?php echo ucfirst(str_replace('_', ' ', $product['farming_method'])); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo ucfirst(str_replace('_', ' ', $product['farming_method'])); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if ($product['irrigation_type']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Irrigation Type</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Irrigation Type</strong>
                                                                <span><?php echo htmlspecialchars($product['irrigation_type']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['irrigation_type']); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if ($product['storage_temperature']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Storage Temperature</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Storage Temp</strong>
                                                                <span><?php echo htmlspecialchars($product['storage_temperature']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['storage_temperature']); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if ($product['storage_humidity']): ?>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Storage Humidity</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Storage Humidity</strong>
                                                                <span><?php echo htmlspecialchars($product['storage_humidity']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['storage_humidity']); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">No agricultural details provided for this product.</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Seller Information -->
                                <div class="tab-pane fade" id="seller" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 mobile-optimized-table">
                                            <thead class="table-light d-none d-md-table-header-group">
                                                <tr>
                                                    <th width="40%">Field</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Business Name</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Business Name</strong>
                                                            <span>
                                                                <a href="../users/user-details.php?id=<?php echo $product['seller_id']; ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($product['business_name']); ?>
                                                                </a>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <a href="../users/user-details.php?id=<?php echo $product['seller_id']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($product['business_name']); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Seller Name</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Seller Name</strong>
                                                            <span><?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Email</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Email</strong>
                                                            <span><?php echo htmlspecialchars($product['email']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['email']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Phone</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div class="d-flex justify-content-between">
                                                            <strong>Phone</strong>
                                                            <span><?php echo htmlspecialchars($product['phone']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo htmlspecialchars($product['phone']); ?></td>
                                                </tr>
                                                <?php if ($product['business_description']): ?>
                                                <tr>
                                                    <td class="d-none d-md-table-cell"><strong>Business Description</strong></td>
                                                    <td class="d-md-none mobile-table-row">
                                                        <div>
                                                            <strong>Business Description</strong>
                                                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($product['business_description'])); ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?php echo nl2br(htmlspecialchars($product['business_description'])); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Admin Notes -->
                                <div class="tab-pane fade" id="admin" role="tabpanel">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="admin_notes" class="form-label">Admin Notes</label>
                                            <textarea class="form-control" id="admin_notes" name="admin_notes" 
                                                      rows="4" placeholder="Add internal notes about this product..."><?php echo htmlspecialchars($product['admin_notes'] ?? ''); ?></textarea>
                                            <div class="form-text">These notes are only visible to administrators.</div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button name="update_status" class="btn btn-primary flex-fill">
                                                <i class="bi bi-save me-1"></i> Save Notes
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <?php if ($product['approved_at']): ?>
                                    <div class="mt-4 pt-3 border-top">
                                        <h6>Approval Information</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm mobile-optimized-table">
                                                <tbody>
                                                    <tr>
                                                        <td class="d-none d-md-table-cell"><strong>Approved On</strong></td>
                                                        <td class="d-md-none mobile-table-row">
                                                            <div class="d-flex justify-content-between">
                                                                <strong>Approved On</strong>
                                                                <span><?php echo date('M j, Y g:i A', strtotime($product['approved_at'])); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?php echo date('M j, Y g:i A', strtotime($product['approved_at'])); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (window.dashboardSidebar) {
                window.dashboardSidebar.toggle();
            }
        });
    }
    
    // Make table rows clickable on mobile to expand details
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('a') && !e.target.closest('button')) {
                // For product details page, we can add expand/collapse functionality
                this.classList.toggle('expanded');
            }
        });
    });
    
    // Initialize carousel with interval
    const productCarousel = document.getElementById('productCarousel');
    if (productCarousel) {
        const carousel = new bootstrap.Carousel(productCarousel, {
            interval: 5000,
            wrap: true
        });
    }
});

// Add CSS for mobile table
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Mobile Table Styles */
    .mobile-table-row {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
    }
    
    .mobile-table-row:last-child {
        border-bottom: none;
    }
    
    @media (max-width: 767.98px) {
        .mobile-optimized-table {
            border: 0;
        }
        
        .mobile-optimized-table thead {
            display: none;
        }
        
        .mobile-optimized-table tr {
            display: block;
            margin-bottom: 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: white;
        }
        
        .mobile-optimized-table td {
            display: block;
            padding: 0 !important;
            border: none;
        }
        
        .mobile-optimized-table td.d-md-none {
            display: block !important;
        }
        
        .mobile-optimized-table td.d-none {
            display: none !important;
        }
        
        /* Touch-friendly buttons */
        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Modal optimization */
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .modal-content {
            border-radius: 0.5rem;
        }
        
        /* Better mobile header */
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Carousel on mobile */
        .carousel-inner {
            border-radius: 0.375rem;
            overflow: hidden;
        }
        
        /* Tabs on mobile */
        .nav-tabs .nav-link {
            padding: 0.75rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Status badges compact */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Price highlighting */
        .text-success {
            font-size: 1.1rem;
            font-weight: 600;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .mobile-optimized-table tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
    }

`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>