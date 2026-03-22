<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get product statistics
$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ?", [$seller_id])['count'],
    'approved' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved'", [$seller_id])['count'],
    'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'draft' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'draft'", [$seller_id])['count'],
    'rejected' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'rejected'", [$seller_id])['count'],
    'low_stock' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'out_of_stock' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND stock_quantity <= 0", [$seller_id])['count']
];

// Build query based on filters
$where = "p.seller_id = ?";
$params = [$seller_id];

if ($status_filter && in_array($status_filter, ['draft', 'pending', 'approved', 'rejected', 'suspended'])) {
    $where .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.short_description LIKE ? OR p.variety LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

// Get products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total for pagination
$total_products = $db->fetchOne("
    SELECT COUNT(*) as count FROM products p WHERE $where
", $params)['count'];
$total_pages = ceil($total_products / $limit);

$products = $db->fetchAll("
    SELECT p.*, 
           c.name as category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
           ag.grade,
           ag.is_organic,
           ag.harvest_date
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN product_agricultural_details ag ON p.id = ag.product_id
    WHERE $where 
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $stats['pending'],
    'low_stock_count' => $stats['low_stock'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Manage Products";
$page_css = 'dashboard.css';

require_once '../../includes/header.php';
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
                        <h1 class="h5 mb-0">Manage Products</h1>
                        <small class="text-muted"><?php echo $stats['total']; ?> total products</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Products</h1>
                    <p class="text-muted mb-0">Manage your product catalog and inventory</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportProducts()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshProducts">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <a href="add-product.php" class="btn btn-sm btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Add Product
                    </a>
                </div>
            </div>

            <!-- Product Statistics Cards -->
            <div class="row g-3 mb-2">
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo !$status_filter ? 'bg-light' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Total</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['total']); ?></h3>
                                <small class="text-secondary">All products</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=approved" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'approved' ? 'bg-success bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Approved</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['approved']); ?></h3>
                                <small class="text-success">Active listings</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=pending" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'pending' ? 'bg-warning bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Pending</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['pending']); ?></h3>
                                <small class="text-warning">Awaiting review</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=draft" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'draft' ? 'bg-info bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Draft</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['draft']); ?></h3>
                                <small class="text-info">Incomplete</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="?status=rejected" class="text-decoration-none">
                        <div class="dashboard-card card shadow-sm h-100 <?php echo $status_filter == 'rejected' ? 'bg-danger bg-opacity-10' : ''; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-subtitle text-muted mb-1">Rejected</h6>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['rejected']); ?></h3>
                                <small class="text-danger">Not approved</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-1">Low Stock</h6>
                            <h3 class="card-title mb-0"><?php echo number_format($stats['low_stock'] + $stats['out_of_stock']); ?></h3>
                            <small class="text-warning">
                                <?php echo $stats['low_stock']; ?> low • <?php echo $stats['out_of_stock']; ?> out
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2">
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Search by product name, variety, or description..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-3 col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter me-1"></i> Filter
                            </button>
                        </div>
                        <div class="col-3 col-md-2">
                            <a href="manage-products.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle me-1"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Summary -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">
                        <?php echo $status_filter ? ucfirst($status_filter) . ' Products' : 'All Products'; ?>
                        <span class="text-muted fs-6 ms-2">(<?php echo number_format($total_products); ?>)</span>
                    </h5>
                    <small class="text-muted">
                        Showing <?php echo min($offset + 1, $total_products); ?>-<?php echo min($offset + $limit, $total_products); ?> of <?php echo $total_products; ?>
                    </small>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleBulkActions()">
                        <i class="bi bi-check2-square"></i> Bulk Actions
                    </button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if ($products): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-center">Stock</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Created</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input product-checkbox" 
                                                       value="<?php echo $product['id']; ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($product['primary_image']): ?>
                                                        <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $product['primary_image']; ?>" 
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                             class="rounded me-3" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="bi bi-image text-muted fs-4"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <?php if ($product['variety']): ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($product['variety']); ?></small>
                                                        <?php endif; ?>
                                                        <?php if ($product['is_organic']): ?>
                                                            <span class="badge bg-success ms-1">Organic</span>
                                                        <?php endif; ?>
                                                        <?php if ($product['grade']): ?>
                                                            <span class="badge bg-info ms-1">Grade <?php echo $product['grade']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                                                <?php if ($product['product_type']): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo ucfirst($product['product_type']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?></strong>
                                                <br>
                                                <small class="text-muted">per <?php echo $product['unit']; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $stock = $product['stock_quantity'];
                                                $low_stock_level = $product['low_stock_alert_level'] ?? 10;
                                                
                                                if ($stock <= 0) {
                                                    $stock_class = 'danger';
                                                    $stock_text = 'Out of Stock';
                                                } elseif ($stock <= $low_stock_level) {
                                                    $stock_class = 'warning';
                                                    $stock_text = 'Low Stock';
                                                } else {
                                                    $stock_class = 'success';
                                                    $stock_text = 'In Stock';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $stock_class; ?>">
                                                    <?php echo $stock_text; ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?php echo number_format($stock); ?> <?php echo $product['unit']; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php echo getStatusBadge($product['status']); ?>
                                                <?php if ($product['admin_notes'] && $product['status'] == 'rejected'): ?>
                                                    <i class="bi bi-info-circle text-danger ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       title="<?php echo htmlspecialchars($product['admin_notes']); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo formatDate($product['created_at'], 'M j, Y'); ?>
                                                <br>
                                                <small class="text-muted"><?php echo formatDate($product['created_at'], 'h:i A'); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-primary" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit Product">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <?php if ($product['status'] == 'approved'): ?>
                                                        <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $product['id']; ?>" 
                                                           target="_blank"
                                                           class="btn btn-outline-success" 
                                                           data-bs-toggle="tooltip" 
                                                           title="View on Store">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" 
                                                            class="btn btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')" 
                                                            data-bs-toggle="tooltip" 
                                                            title="Delete Product">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <?php if ($product['stock_quantity'] <= $product['low_stock_alert_level'] && $product['stock_quantity'] > 0): ?>
                                                    <div class="mt-1">
                                                        <a href="edit-product.php?id=<?php echo $product['id']; ?>#inventory" 
                                                           class="text-warning small text-decoration-none">
                                                            <i class="bi bi-exclamation-triangle"></i> Restock
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Bulk Actions Bar -->
                        <div id="bulkActionsBar" class="d-none p-3 bg-light border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span id="selectedCount" class="fw-bold">0</span> products selected
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-danger" onclick="bulkDelete()">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="bulkApprove()">
                                        <i class="bi bi-check-circle"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="bulkDraft()">
                                        <i class="bi bi-file-text"></i> Move to Draft
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-box-seam display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No products found</h4>
                            <p class="text-muted mb-4">
                                <?php if ($status_filter || $search): ?>
                                    No products match your current filters.
                                    <br>
                                    <a href="manage-products.php">Clear filters</a> to see all products.
                                <?php else: ?>
                                    Get started by adding your first product.
                                <?php endif; ?>
                            </p>
                            <?php if (!$status_filter && !$search): ?>
                                <a href="add-product.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle me-1"></i> Add Your First Product
                                </a>
                            <?php endif; ?>
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
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
                <p class="text-danger">This action cannot be undone. All product data, images, and order history will be affected.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Product</button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteProductId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Refresh button
    document.getElementById('refreshProducts')?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
        btn.disabled = true;
        setTimeout(() => window.location.reload(), 500);
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Select all functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActionsBar();
        });
    }
    
    // Individual checkbox change
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkActionsBar);
    });
});

function updateBulkActionsBar() {
    const selected = document.querySelectorAll('.product-checkbox:checked');
    const count = selected.length;
    const bar = document.getElementById('bulkActionsBar');
    
    if (count > 0) {
        bar.classList.remove('d-none');
        document.getElementById('selectedCount').innerText = count;
    } else {
        bar.classList.add('d-none');
    }
}

function confirmDelete(productId, productName) {
    deleteProductId = productId;
    document.getElementById('deleteProductName').innerText = productName;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
    if (deleteProductId) {
        window.location.href = 'delete-product.php?id=' + deleteProductId;
    }
});

function exportProducts() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = 'export-products.php?' + params.toString();
}

function toggleBulkActions() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.click();
    }
}

function bulkDelete() {
    const selected = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    
    if (confirm(`Are you sure you want to delete ${selected.length} product(s)? This cannot be undone.`)) {
        performBulkAction('delete', selected);
    }
}

function bulkApprove() {
    const selected = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    
    if (confirm(`Approve ${selected.length} product(s)?`)) {
        performBulkAction('approve', selected);
    }
}

function bulkDraft() {
    const selected = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) return;
    
    if (confirm(`Move ${selected.length} product(s) to draft?`)) {
        performBulkAction('draft', selected);
    }
}

function performBulkAction(action, productIds) {
    fetch('bulk-product-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: action,
            product_ids: productIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Successfully ${action}ed ${data.count} product(s)`, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to perform bulk action', 'danger');
    });
}

function showToast(message, type) {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
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
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

// Add CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin { animation: spin 1s linear infinite; }
    .dashboard-card { transition: transform 0.2s, box-shadow 0.2s; }
    .dashboard-card:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important; }
    .dashboard-card.bg-light { background-color: #f8f9fc !important; }
    .table td { vertical-align: middle; }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>