<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';

requireAdmin();

$db = new Database();

// Handle product actions
if (isset($_GET['suspend'])) {
    $product_id = (int)$_GET['suspend'];
    $db->query("UPDATE products SET status = 'suspended' WHERE id = ?", [$product_id]);
    setFlashMessage('Product suspended', 'warning');
    header('Location: manage-products.php');
    exit;
}

if (isset($_GET['activate'])) {
    $product_id = (int)$_GET['activate'];
    $db->query("UPDATE products SET status = 'approved' WHERE id = ?", [$product_id]);
    setFlashMessage('Product activated', 'success');
    header('Location: manage-products.php');
    exit;
}

if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    $db->query("DELETE FROM products WHERE id = ?", [$product_id]);
    setFlashMessage('Product deleted', 'success');
    header('Location: manage-products.php');
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR sp.business_name LIKE ? OR p.variety LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

if (!empty($category)) {
    $where .= " AND p.category_id = ?";
    $params[] = $category;
}

// Get products
$products = $db->fetchAll("
    SELECT p.*, 
           sp.business_name, 
           c.name as category_name,
           u.first_name as seller_first_name,
           u.last_name as seller_last_name
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN categories c ON p.category_id = c.id 
    JOIN users u ON p.seller_id = u.id
    $where 
    ORDER BY p.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

// Get categories for filter
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = TRUE");

// Total count for pagination
$total_products = $db->fetchOne("SELECT COUNT(*) as count FROM products p JOIN seller_profiles sp ON p.seller_id = sp.user_id $where", $params)['count'];
$total_pages = ceil($total_products / $limit);

// Stats
$stats = [
    'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'],
    'approved_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'approved'")['count'],
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'pending'")['count'],
    'suspended_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'suspended'")['count'],
    'low_stock_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= low_stock_alert_level AND status = 'approved'")['count'],
    'featured_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_featured = TRUE AND status = 'approved'")['count'],
    'today_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE DATE(created_at) = CURDATE()")['count']
];

$page_title = "Manage Products";
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
                        <h1 class="h5 mb-0 text-center">Products</h1>
                        <small class="text-muted d-block text-center"><?php echo $stats['total_products']; ?> products</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshProducts">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Products</h1>
                    <p class="text-muted mb-0">Manage all products on the platform</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportProducts()">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshProducts">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Total</small>
                                <h6 class="mb-0"><?php echo number_format($stats['total_products']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Approved</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['approved_products']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Pending</small>
                                <h6 class="mb-0 text-warning"><?php echo number_format($stats['pending_products']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Low Stock</small>
                                <h6 class="mb-0 text-danger"><?php echo number_format($stats['low_stock_products']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="d-none d-md-flex row g-3 mb-4">
                <!-- Total Products -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Products</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_products']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo $stats['today_products']; ?> today
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-box-seam fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Approved Products -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Approved</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['approved_products']); ?></h3>
                                    <small class="text-success">
                                        <?php echo round(($stats['approved_products'] / max(1, $stats['total_products'])) * 100); ?>% approved
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-check-circle fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Products -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['pending_products']); ?></h3>
                                    <small class="text-warning">
                                        Needs review
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-clock fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Low Stock</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['low_stock_products']); ?></h3>
                                    <small class="text-danger">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Needs attention
                                    </small>
                                </div>
                                <div class="bg-danger bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-box fs-5 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Filter Products
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
                <div class="collapse d-md-block" id="filterCollapse">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-12 col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search products, sellers..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="category" class="form-select form-select-sm">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(substr($cat['name'], 0, 15)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-filter me-1"></i> Filter
                                    </button>
                                    <a href="manage-products.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle me-1"></i> Clear
                                    </a>
                                    <?php if($stats['low_stock_products'] > 0): ?>
                                        <a href="manage-products.php?status=approved" class="btn btn-danger btn-sm">
                                            <i class="bi bi-exclamation-triangle me-1"></i> Low Stock (<?php echo $stats['low_stock_products']; ?>)
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">Products (<?php echo $total_products; ?>)</h5>
                    <small class="text-muted">
                        Showing <?php echo min($offset + 1, $total_products); ?>-<?php echo min($offset + $limit, $total_products); ?> of <?php echo $total_products; ?> products
                    </small>
                </div>
                <div class="text-muted d-none d-md-block">
                    <?php if($stats['pending_products'] > 0): ?>
                        <span class="badge bg-warning">
                            <i class="bi bi-clock me-1"></i>
                            <?php echo $stats['pending_products']; ?> pending
                        </span>
                    <?php endif; ?>
                    <?php if($stats['low_stock_products'] > 0): ?>
                        <span class="badge bg-danger ms-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <?php echo $stats['low_stock_products']; ?> low stock
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($products)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-box-seam text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No products found</h4>
                            <p class="text-muted mb-4">No products match your current filters.</p>
                            <a href="manage-products.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="200">Product</th>
                                        <th width="120">Seller</th>
                                        <th width="100">Category</th>
                                        <th width="100">Price</th>
                                        <th width="80">Stock</th>
                                        <th width="90">Status</th>
                                        <th width="100">Created</th>
                                        <th width="140" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <?php 
                                        $status_colors = [
                                            'approved' => 'success',
                                            'pending' => 'warning',
                                            'rejected' => 'danger',
                                            'suspended' => 'secondary',
                                            'draft' => 'secondary'
                                        ];
                                        
                                        $is_low_stock = $product['stock_quantity'] <= $product['low_stock_alert_level'];
                                        $is_featured = $product['is_featured'] ?? false;
                                        $seller_name = $product['seller_first_name'] . ' ' . $product['seller_last_name'];
                                        ?>
                                        
                                        <tr class="product-row" data-product-id="<?php echo $product['id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-<?php echo $status_colors[$product['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                        <i class="bi bi-box text-<?php echo $status_colors[$product['status']] ?? 'secondary'; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars(substr($product['name'], 0, 30)); ?></strong>
                                                        <?php if ($is_featured): ?>
                                                            <span class="badge bg-warning ms-1">Featured</span>
                                                        <?php endif; ?>
                                                        <?php if ($product['variety']): ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($product['variety']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <strong><?php echo htmlspecialchars(substr($product['business_name'], 0, 15)); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($seller_name, 0, 15)); ?></small>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars(substr($product['category_name'], 0, 12)); ?></span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong class="text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?></strong>
                                                <br>
                                                <small class="text-muted">/<?php echo $product['unit']; ?></small>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo number_format($product['stock_quantity'], 2); ?> <?php echo $product['unit']; ?>
                                                <?php if ($is_low_stock): ?>
                                                    <br>
                                                    <span class="badge bg-danger">Low</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $status_colors[$product['status']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($product['status']); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($product['status'] === 'approved'): ?>
                                                        <a href="?suspend=<?php echo $product['id']; ?>" 
                                                           class="btn btn-outline-warning"
                                                           onclick="return confirm('Suspend this product?')">
                                                            <i class="bi bi-pause"></i>
                                                        </a>
                                                    <?php elseif ($product['status'] === 'suspended'): ?>
                                                        <a href="?activate=<?php echo $product['id']; ?>" 
                                                           class="btn btn-outline-success"
                                                           onclick="return confirm('Activate this product?')">
                                                            <i class="bi bi-play"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="?delete=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-danger"
                                                       onclick="return confirm('Delete this product permanently?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Product Name & Status -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $status_colors[$product['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <i class="bi bi-box text-<?php echo $status_colors[$product['status']] ?? 'secondary'; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars(substr($product['name'], 0, 20)); ?></strong>
                                                                <?php if ($is_featured): ?>
                                                                    <span class="badge bg-warning ms-1">F</span>
                                                                <?php endif; ?>
                                                                <br>
                                                                <span class="badge bg-<?php echo $status_colors[$product['status']] ?? 'secondary'; ?>">
                                                                    <?php echo ucfirst($product['status']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <strong class="text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?></strong>
                                                            <br>
                                                            <small class="text-muted">/<?php echo $product['unit']; ?></small>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 2: Seller & Category -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <small class="text-muted d-block">Seller:</small>
                                                                <small><?php echo htmlspecialchars(substr($product['business_name'], 0, 15)); ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted d-block">Category:</small>
                                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars(substr($product['category_name'], 0, 10)); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Stock & Actions -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <small class="text-muted d-block">
                                                                Stock: <?php echo number_format($product['stock_quantity'], 2); ?> <?php echo $product['unit']; ?>
                                                                <?php if ($is_low_stock): ?>
                                                                    <span class="badge bg-danger ms-1">Low</span>
                                                                <?php endif; ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <?php echo date('M j', strtotime($product['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            
                                                            <?php if ($product['status'] === 'approved'): ?>
                                                                <a href="?suspend=<?php echo $product['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-warning"
                                                                   onclick="return confirm('Suspend this product?')">
                                                                    <i class="bi bi-pause"></i>
                                                                </a>
                                                            <?php elseif ($product['status'] === 'suspended'): ?>
                                                                <a href="?activate=<?php echo $product['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-success"
                                                                   onclick="return confirm('Activate this product?')">
                                                                    <i class="bi bi-play"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <a href="?delete=<?php echo $product['id']; ?>" 
                                                               class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('Delete this product?')">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                    <span class="d-none d-md-inline">Previous</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Mobile: Simple pagination -->
                        <div class="d-md-none">
                            <li class="page-item disabled">
                                <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            </li>
                        </div>
                        
                        <!-- Desktop: Full pagination -->
                        <div class="d-none d-md-flex">
                            <?php
                            $range = 1; // pages before & after current
                            $ellipsisShownLeft = false;
                            $ellipsisShownRight = false;

                            for ($i = 1; $i <= $total_pages; $i++) {

                                // Always show first, last, and nearby pages
                                if (
                                    $i == 1 ||
                                    $i == $total_pages ||
                                    ($i >= $page - $range && $i <= $page + $range)
                                ) {
                                    ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link"
                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php
                                }
                                // Left ellipsis
                                elseif ($i < $page && !$ellipsisShownLeft) {
                                    $ellipsisShownLeft = true;
                                    ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php
                                }
                                // Right ellipsis
                                elseif ($i > $page && !$ellipsisShownRight) {
                                    $ellipsisShownRight = true;
                                    ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php
                                }
                            }
                            ?>
                        </div>

                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <span class="d-none d-md-inline">Next</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
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
    
    // Refresh products
    const refreshBtn = document.getElementById('refreshProducts');
    const mobileRefreshBtn = document.getElementById('mobileRefreshProducts');
    
    function refreshPage() {
        const btn = event?.target?.closest('button');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            btn.disabled = true;
        }
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
    
    if (refreshBtn) refreshBtn.addEventListener('click', refreshPage);
    if (mobileRefreshBtn) mobileRefreshBtn.addEventListener('click', refreshPage);
    
    // Make table rows clickable on mobile to view details
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('a')) {
                const productId = this.closest('.product-row').dataset.productId;
                window.location.href = `product-details.php?id=${productId}`;
            }
        });
    });
});

function exportProducts() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = 'export-products.php?' + params.toString();
    link.download = 'products-export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    agriApp.showToast('Export started', 'info');
}

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
        cursor: pointer;
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
        .btn-sm {
            padding: 0.25rem 0.5rem;
            min-height: 36px;
            min-width: 36px;
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
        
        /* Compact filters */
        .form-select-sm, .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Status badges compact */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Price styling */
        .text-success {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        /* Low stock warning */
        .bg-danger {
            background-color: #dc3545 !important;
        }
        
        /* Featured badge */
        .bg-warning {
            background-color: #ffc107 !important;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .product-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .product-row:hover .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        }
    }

`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>