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

// Get products with stock info and agricultural details
$products = $db->fetchAll("
    SELECT p.*, 
           c.name as category_name,
           ag.grade,
           ag.is_organic,
           ag.harvest_date,
           ag.shelf_life_days,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image,
           (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi 
            WHERE oi.product_id = p.id AND oi.status IN ('confirmed', 'shipped', 'pending')) as reserved_stock
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN product_agricultural_details ag ON p.id = ag.product_id
    WHERE p.seller_id = ? AND p.status = 'approved'
    ORDER BY 
        CASE 
            WHEN p.stock_quantity <= 0 THEN 1
            WHEN p.stock_quantity <= p.low_stock_alert_level THEN 2
            ELSE 3
        END,
        p.stock_quantity ASC
", [$seller_id]);

// Calculate stock statistics
$total_products = count($products);
$in_stock = 0;
$low_stock = 0;
$out_of_stock = 0;
$total_stock_value = 0;
$total_reserved = 0;

foreach ($products as $product) {
    $reserved = $product['reserved_stock'] ?? 0;
    $total_reserved += $reserved;
    $total_stock_value += $product['stock_quantity'] * $product['price_per_unit'];
    
    if ($product['stock_quantity'] <= 0) {
        $out_of_stock++;
    } elseif ($product['stock_quantity'] <= $product['low_stock_alert_level']) {
        $low_stock++;
    } else {
        $in_stock++;
    }
}

// Get recent stock movements (you'll need a stock_movements table for this)
// For now, we'll use a placeholder

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $low_stock,
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Stock Management";
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
                        <h1 class="h5 mb-0">Stock Management</h1>
                        <small class="text-muted">Manage your inventory</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Stock Management</h1>
                    <p class="text-muted mb-0">Monitor and update your product inventory</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportStock()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshData">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <a href="low-stock-alerts.php" class="btn btn-sm btn-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i> Alerts
                        <?php if ($low_stock > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $low_stock; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Stock Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-6 col-lg-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Products</h6>
                                    <h3 class="card-title mb-0"><?php echo $total_products; ?></h3>
                                    <small class="text-primary">
                                        <i class="bi bi-box-seam me-1"></i>
                                        Active products
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-boxes fs-4 text-primary"></i>
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
                                    <h6 class="card-subtitle text-muted mb-1">In Stock</h6>
                                    <h3 class="card-title mb-0"><?php echo $in_stock; ?></h3>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Healthy stock
                                    </small>
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
                                    <h6 class="card-subtitle text-muted mb-1">Low Stock</h6>
                                    <h3 class="card-title mb-0"><?php echo $low_stock; ?></h3>
                                    <small class="text-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Need attention
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-exclamation-triangle fs-4 text-warning"></i>
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
                                    <h6 class="card-subtitle text-muted mb-1">Out of Stock</h6>
                                    <h3 class="card-title mb-0"><?php echo $out_of_stock; ?></h3>
                                    <small class="text-danger">
                                        <i class="bi bi-x-circle me-1"></i>
                                        Needs restock
                                    </small>
                                </div>
                                <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-x-circle fs-4 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Summary Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-3">
                                <i class="bi bi-pie-chart me-2 text-primary"></i>
                                Stock Distribution
                            </h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>In Stock</span>
                                    <span class="fw-bold text-success"><?php echo $in_stock; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($in_stock / max($total_products, 1)) * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Low Stock</span>
                                    <span class="fw-bold text-warning"><?php echo $low_stock; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($low_stock / max($total_products, 1)) * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Out of Stock</span>
                                    <span class="fw-bold text-danger"><?php echo $out_of_stock; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo ($out_of_stock / max($total_products, 1)) * 100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-3">
                                <i class="bi bi-calculator me-2 text-primary"></i>
                                Stock Value
                            </h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Total Inventory Value</span>
                                    <h5 class="mb-0 text-success">₦<?php echo number_format($total_stock_value, 2); ?></h5>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Reserved Stock</span>
                                    <h5 class="mb-0 text-warning">₦<?php echo number_format($total_reserved * array_sum(array_column($products, 'price_per_unit')) / max(count($products), 1), 2); ?></h5>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Available Stock</span>
                                    <h5 class="mb-0 text-primary">₦<?php echo number_format($total_stock_value - ($total_reserved * array_sum(array_column($products, 'price_per_unit')) / max(count($products), 1)), 2); ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h6 class="text-muted mb-3">
                                <i class="bi bi-lightning-charge me-2 text-primary"></i>
                                Quick Actions
                            </h6>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                                    <i class="bi bi-arrow-repeat me-2"></i> Bulk Update Stock
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="window.location.href='low-stock-alerts.php'">
                                    <i class="bi bi-exclamation-triangle me-2"></i> View Low Stock Alerts
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="window.location.href='../products/add-product.php'">
                                    <i class="bi bi-plus-circle me-2"></i> Add New Product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="searchProduct" 
                                       placeholder="Search products...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="stockFilter">
                                <option value="all">All Products</option>
                                <option value="in_stock">In Stock</option>
                                <option value="low_stock">Low Stock</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="categoryFilter">
                                <option value="all">All Categories</option>
                                <?php
                                $categories = $db->fetchAll("SELECT DISTINCT c.id, c.name FROM categories c 
                                                              JOIN products p ON c.id = p.category_id 
                                                              WHERE p.seller_id = ?", [$seller_id]);
                                foreach ($categories as $cat):
                                ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Stock Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-boxes me-2 text-primary"></i>
                        Product Stock Levels
                    </h5>
                    <span class="badge bg-primary"><?php echo $total_products; ?> Products</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($products): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="stockTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th class="text-center">Current Stock</th>
                                        <th class="text-center">Reserved</th>
                                        <th class="text-center">Available</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <?php
                                        $reserved = $product['reserved_stock'] ?? 0;
                                        $available = $product['stock_quantity'] - $reserved;
                                        
                                        if ($product['stock_quantity'] <= 0) {
                                            $status_class = 'danger';
                                            $status_text = 'Out of Stock';
                                        } elseif ($product['stock_quantity'] <= $product['low_stock_alert_level']) {
                                            $status_class = 'warning';
                                            $status_text = 'Low Stock';
                                        } else {
                                            $status_class = 'success';
                                            $status_text = 'In Stock';
                                        }
                                        
                                        $stock_level_class = $product['stock_quantity'] <= $product['low_stock_alert_level'] ? 'text-danger fw-bold' : '';
                                        ?>
                                        <tr class="product-row" 
                                            data-product-name="<?php echo strtolower($product['name']); ?>"
                                            data-category="<?php echo $product['category_id']; ?>"
                                            data-stock-status="<?php echo $status_text; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($product['product_image']): ?>
                                                        <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $product['product_image']; ?>" 
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                             class="rounded me-3" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="bi bi-box text-muted fs-4"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            SKU: <?php echo $product['id']; ?>
                                                            <?php if ($product['grade']): ?>
                                                                • Grade: <?php echo $product['grade']; ?>
                                                            <?php endif; ?>
                                                            <?php if ($product['is_organic']): ?>
                                                                • <span class="text-success">Organic</span>
                                                            <?php endif; ?>
                                                        </small>
                                                        <?php if ($product['harvest_date']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                Harvest: <?php echo date('M j, Y', strtotime($product['harvest_date'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark"><?php echo $product['category_name']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="<?php echo $stock_level_class; ?>">
                                                    <?php echo number_format($product['stock_quantity']); ?> 
                                                    <small class="text-muted"><?php echo $product['unit']; ?></small>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($reserved > 0): ?>
                                                    <span class="text-warning"><?php echo number_format($reserved); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-bold text-<?php echo $available <= 0 ? 'danger' : 'success'; ?>">
                                                    <?php echo number_format($available); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" 
                                                            class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#updateStockModal"
                                                            data-product-id="<?php echo $product['id']; ?>"
                                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                            data-current-stock="<?php echo $product['stock_quantity']; ?>"
                                                            data-unit="<?php echo $product['unit']; ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <a href="../products/edit-product.php?id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-secondary"
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit Product">
                                                        <i class="bi bi-gear"></i>
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
                            <i class="bi bi-box-seam display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No products found</h4>
                            <p class="text-muted mb-4">Add products to start managing your inventory.</p>
                            <a href="../products/add-product.php" class="btn btn-success">
                                <i class="bi bi-plus-circle me-1"></i> Add Your First Product
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>
                    Update Stock
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateStockForm">
                    <input type="hidden" id="product_id" name="product_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Product</label>
                        <div class="alert alert-light border" id="product_name_display"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Current Stock</label>
                                <div class="form-control bg-light" id="current_stock_display" readonly></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="stock_quantity" class="form-label fw-bold">New Stock Quantity</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="stock_quantity" name="stock_quantity" required>
                                    <span class="input-group-text" id="unit_display"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adjustment_type" class="form-label fw-bold">Adjustment Type</label>
                        <select class="form-select" id="adjustment_type" name="adjustment_type">
                            <option value="set">Set to this value (Replace)</option>
                            <option value="add">Add to current stock</option>
                            <option value="subtract">Subtract from current stock</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label fw-bold">Reason for adjustment</label>
                        <input type="text" class="form-control" id="reason" name="reason" 
                               placeholder="e.g., New harvest, Spoilage, Restock, etc.">
                        <div class="form-text">This will be logged for audit purposes</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> Stock updates are recorded and can be viewed in stock history.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateStock()">
                    <i class="bi bi-check-circle me-1"></i> Update Stock
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-arrow-repeat me-2"></i>
                    Bulk Stock Update
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkUpdateForm">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Enter new stock quantities for each product. Leave unchanged if no update needed.
                    </div>
                    
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-sm table-hover">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Current Stock</th>
                                    <th class="text-center">New Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($product['product_image']): ?>
                                                    <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $product['product_image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         style="width: 30px; height: 30px; object-fit: cover;"
                                                         class="rounded me-2">
                                                <?php endif; ?>
                                                <div>
                                                    <small class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></small>
                                                    <br>
                                                    <small class="text-muted"><?php echo $product['unit']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="<?php echo $product['stock_quantity'] <= $product['low_stock_alert_level'] ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo number_format($product['stock_quantity']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center align-middle" style="width: 150px;">
                                            <input type="number" step="0.01" 
                                                   name="stock[<?php echo $product['id']; ?>]" 
                                                   value="<?php echo $product['stock_quantity']; ?>"
                                                   class="form-control form-control-sm"
                                                   min="0">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mb-3 mt-3">
                        <label for="bulk_reason" class="form-label fw-bold">Reason for bulk update</label>
                        <input type="text" class="form-control" id="bulk_reason" name="reason" 
                               placeholder="e.g., Monthly stock take, Inventory count">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="bulkUpdateStock()">
                    <i class="bi bi-check-all me-1"></i> Update All
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Refresh button
    document.getElementById('refreshData')?.addEventListener('click', function() {
        location.reload();
    });
    
    // Search functionality
    document.getElementById('searchProduct').addEventListener('keyup', filterTable);
    document.getElementById('stockFilter').addEventListener('change', filterTable);
    document.getElementById('categoryFilter').addEventListener('change', filterTable);
});

// Stock modal data
var updateStockModal = document.getElementById('updateStockModal');
updateStockModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var productId = button.getAttribute('data-product-id');
    var productName = button.getAttribute('data-product-name');
    var currentStock = button.getAttribute('data-current-stock');
    var unit = button.getAttribute('data-unit');
    
    document.getElementById('product_id').value = productId;
    document.getElementById('product_name_display').textContent = productName;
    document.getElementById('current_stock_display').value = currentStock + ' ' + unit;
    document.getElementById('stock_quantity').value = currentStock;
    document.getElementById('unit_display').textContent = unit;
    document.getElementById('reason').value = '';
});

function filterTable() {
    var searchTerm = document.getElementById('searchProduct').value.toLowerCase();
    var stockFilter = document.getElementById('stockFilter').value;
    var categoryFilter = document.getElementById('categoryFilter').value;
    
    var rows = document.querySelectorAll('.product-row');
    
    rows.forEach(row => {
        var productName = row.getAttribute('data-product-name');
        var category = row.getAttribute('data-category');
        var status = row.getAttribute('data-stock-status').toLowerCase().replace(' ', '_');
        
        var matchesSearch = productName.includes(searchTerm);
        var matchesCategory = categoryFilter === 'all' || category === categoryFilter;
        var matchesStock = stockFilter === 'all' || status === stockFilter;
        
        row.style.display = (matchesSearch && matchesCategory && matchesStock) ? '' : 'none';
    });
}

function resetFilters() {
    document.getElementById('searchProduct').value = '';
    document.getElementById('stockFilter').value = 'all';
    document.getElementById('categoryFilter').value = 'all';
    filterTable();
}

function updateStock() {
    var stockQuantity = document.getElementById('stock_quantity').value;
    if (!stockQuantity || stockQuantity < 0) {
        showToast('Please enter a valid stock quantity', 'warning');
        return;
    }
    
    var formData = new FormData(document.getElementById('updateStockForm'));
    
    // Show loading state
    var submitBtn = event.target;
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
    submitBtn.disabled = true;
    
    fetch('update-stock.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Stock updated successfully!', 'success');
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('updateStockModal'));
            modal.hide();
            
            // Reload after delay
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to update stock. Please try again.', 'danger');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function bulkUpdateStock() {
    var formData = new FormData(document.getElementById('bulkUpdateForm'));
    
    // Confirm action
    if (!confirm('Are you sure you want to update stock for all products?')) {
        return;
    }
    
    // Show loading state
    var submitBtn = event.target;
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
    submitBtn.disabled = true;
    
    fetch('bulk-update-stock.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Stock updated successfully!', 'success');
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('bulkUpdateModal'));
            modal.hide();
            
            // Reload after delay
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast('Error: ' + data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to update stock. Please try again.', 'danger');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function exportStock() {
    window.location.href = 'export-stock.php';
}

function showToast(message, type) {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'danger' ? 'bi-exclamation-triangle' : 'bi-info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 3000
    });
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

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
    
    .modal-header.bg-primary {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    }
    
    .modal-header.bg-success {
        background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
    }
    
    .progress {
        background-color: #e9ecef;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .sticky-top {
        top: 0;
        z-index: 1;
    }
    
    @media (max-width: 768px) {
        .table-responsive {
            border: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>