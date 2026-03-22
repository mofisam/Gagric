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

// Get low stock products with agricultural details
$low_stock_products = $db->fetchAll("
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
    WHERE p.seller_id = ? AND p.status = 'approved' AND p.stock_quantity <= p.low_stock_alert_level
    ORDER BY 
        CASE 
            WHEN p.stock_quantity <= 0 THEN 1
            ELSE 2
        END,
        p.stock_quantity ASC
", [$seller_id]);

// Separate out of stock and low stock
$out_of_stock = array_filter($low_stock_products, fn($p) => $p['stock_quantity'] <= 0);
$low_stock = array_filter($low_stock_products, fn($p) => $p['stock_quantity'] > 0 && $p['stock_quantity'] <= $p['low_stock_alert_level']);

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => count($low_stock_products),
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Low Stock Alerts";
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
                        <h1 class="h5 mb-0">Low Stock Alerts</h1>
                        <small class="text-muted">Products needing attention</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Low Stock Alerts</h1>
                    <p class="text-muted mb-0">Products that need to be restocked</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="stock-management.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-boxes me-1"></i> Stock Management
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshData">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-warning" onclick="bulkRestock()">
                        <i class="bi bi-arrow-repeat me-1"></i> Bulk Restock
                    </button>
                </div>
            </div>

            <!-- Alert Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Critical (Out of Stock)</h6>
                                    <h3 class="card-title mb-0"><?php echo count($out_of_stock); ?></h3>
                                    <small class="text-danger">Immediate attention needed</small>
                                </div>
                                <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-x-circle fs-4 text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Warning (Low Stock)</h6>
                                    <h3 class="card-title mb-0"><?php echo count($low_stock); ?></h3>
                                    <small class="text-warning">Restock soon</small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-exclamation-triangle fs-4 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Alerts</h6>
                                    <h3 class="card-title mb-0"><?php echo count($low_stock_products); ?></h3>
                                    <small class="text-info">Products need attention</small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                                    <i class="bi bi-bell fs-4 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($low_stock_products): ?>
                <!-- Urgent Alert Banner for Out of Stock -->
                <?php if (count($out_of_stock) > 0): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
                            <div>
                                <strong>Critical Alert!</strong> 
                                You have <strong><?php echo count($out_of_stock); ?> product(s)</strong> out of stock.
                                These products are not visible to customers. Please restock immediately.
                            </div>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Low Stock Products Grid -->
                <div class="row g-4">
                    <?php foreach ($low_stock_products as $product): 
                        $is_out_of_stock = $product['stock_quantity'] <= 0;
                        $card_class = $is_out_of_stock ? 'border-danger' : 'border-warning';
                        $header_class = $is_out_of_stock ? 'bg-danger text-white' : 'bg-warning';
                        $badge_class = $is_out_of_stock ? 'bg-danger' : 'bg-warning';
                        $progress_class = $is_out_of_stock ? 'bg-danger' : 'bg-warning';
                        $stock_percentage = $product['low_stock_alert_level'] > 0 ? 
                            min(100, ($product['stock_quantity'] / $product['low_stock_alert_level']) * 100) : 0;
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card shadow-sm border-0 <?php echo $card_class; ?> h-100">
                                <!-- Card Header -->
                                <div class="card-header <?php echo $header_class; ?> border-0 d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="bi bi-<?php echo $is_out_of_stock ? 'x-circle' : 'exclamation-triangle'; ?> me-2"></i>
                                        <?php echo $is_out_of_stock ? 'Out of Stock' : 'Low Stock Alert'; ?>
                                    </h6>
                                    <span class="badge bg-light text-dark">Alert Level: <?php echo $product['low_stock_alert_level']; ?></span>
                                </div>
                                
                                <div class="card-body">
                                    <div class="d-flex mb-3">
                                        <!-- Product Image -->
                                        <div class="flex-shrink-0 me-3">
                                            <?php if ($product['product_image']): ?>
                                                <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $product['product_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     class="rounded" 
                                                     style="width: 80px; height: 80px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                     style="width: 80px; height: 80px;">
                                                    <i class="bi bi-box text-muted fs-2"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Product Info -->
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($product['name']); ?></h5>
                                            <p class="text-muted small mb-2"><?php echo $product['category_name']; ?></p>
                                            
                                            <?php if ($product['grade']): ?>
                                                <span class="badge bg-light text-dark me-1">Grade: <?php echo $product['grade']; ?></span>
                                            <?php endif; ?>
                                            <?php if ($product['is_organic']): ?>
                                                <span class="badge bg-success">Organic</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Stock Level Progress Bar -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small">Stock Level</span>
                                            <span class="small fw-bold text-<?php echo $is_out_of_stock ? 'danger' : 'warning'; ?>">
                                                <?php echo $product['stock_quantity']; ?> / <?php echo $product['low_stock_alert_level']; ?> <?php echo $product['unit']; ?>
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar <?php echo $progress_class; ?>" 
                                                 style="width: <?php echo $stock_percentage; ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Stock Details -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Current Stock</small>
                                            <span class="fw-bold <?php echo $is_out_of_stock ? 'text-danger' : ''; ?>">
                                                <?php echo number_format($product['stock_quantity']); ?> <?php echo $product['unit']; ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Reserved</small>
                                            <span class="fw-bold text-warning">
                                                <?php echo number_format($product['reserved_stock'] ?? 0); ?> <?php echo $product['unit']; ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Price</small>
                                            <span class="fw-bold text-success">
                                                ₦<?php echo number_format($product['price_per_unit'], 2); ?>/<?php echo $product['unit']; ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Available</small>
                                            <span class="fw-bold <?php echo ($product['stock_quantity'] - ($product['reserved_stock'] ?? 0)) <= 0 ? 'text-danger' : 'text-primary'; ?>">
                                                <?php echo number_format(max(0, $product['stock_quantity'] - ($product['reserved_stock'] ?? 0))); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Harvest Info (if available) -->
                                    <?php if ($product['harvest_date']): ?>
                                        <div class="alert alert-light border small p-2 mb-3">
                                            <i class="bi bi-calendar3 me-2"></i>
                                            Harvest: <?php echo date('M j, Y', strtotime($product['harvest_date'])); ?>
                                            <?php if ($product['shelf_life_days']): ?>
                                                • Shelf life: <?php echo $product['shelf_life_days']; ?> days
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Restock Suggestion -->
                                    <div class="alert alert-info small p-2 mb-0">
                                        <i class="bi bi-lightbulb me-2"></i>
                                        Suggested restock: 
                                        <strong><?php echo $product['low_stock_alert_level'] * 2 - $product['stock_quantity']; ?> <?php echo $product['unit']; ?></strong>
                                    </div>
                                </div>

                                <!-- Card Footer -->
                                <div class="card-footer bg-white border-0 pt-0 pb-3">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-<?php echo $is_out_of_stock ? 'danger' : 'warning'; ?> flex-grow-1" 
                                                data-bs-toggle="modal" data-bs-target="#updateStockModal"
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                data-current-stock="<?php echo $product['stock_quantity']; ?>"
                                                data-unit="<?php echo $product['unit']; ?>"
                                                data-alert-level="<?php echo $product['low_stock_alert_level']; ?>">
                                            <i class="bi bi-arrow-up me-1"></i> Restock Now
                                        </button>
                                        <a href="../products/edit-product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-secondary" 
                                           data-bs-toggle="tooltip" 
                                           title="Edit Product">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="../products/view-product.php?id=<?php echo $product['id']; ?>" 
                                           class="btn btn-outline-primary"
                                           data-bs-toggle="tooltip" 
                                           title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Restock Summary -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm border-0 bg-light">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <i class="bi bi-truck text-primary fs-4 me-3"></i>
                                        <span class="fw-bold">Quick Restock Summary</span>
                                    </div>
                                    <div>
                                        <span class="text-muted me-3">
                                            <i class="bi bi-box-seam me-1"></i>
                                            Total to restock: <?php echo count($low_stock_products); ?> products
                                        </span>
                                        <button class="btn btn-sm btn-primary" onclick="bulkRestock()">
                                            <i class="bi bi-arrow-repeat me-1"></i> Bulk Restock
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Alerts State -->
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill display-1 text-success"></i>
                        </div>
                        <h3 class="text-success mb-3">All Products are Well Stocked!</h3>
                        <p class="text-muted mb-4">No low stock alerts at this time. Your inventory levels are healthy.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="stock-management.php" class="btn btn-primary">
                                <i class="bi bi-boxes me-1"></i> View Stock Management
                            </a>
                            <a href="../products/add-product.php" class="btn btn-success">
                                <i class="bi bi-plus-circle me-1"></i> Add New Product
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Placeholder -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-clock-history me-2 text-primary"></i>
                                    Recent Stock Updates
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted text-center py-3">No recent stock updates</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Update Stock Modal (Enhanced) -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-white">
                    <i class="bi bi-arrow-up me-2"></i>
                    Restock Product
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
                                <label class="form-label fw-bold">Alert Level</label>
                                <div class="form-control bg-light" id="alert_level_display" readonly></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label fw-bold">New Stock Quantity</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="stock_quantity" name="stock_quantity" required>
                            <span class="input-group-text" id="unit_display"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="restock_note" class="form-label fw-bold">Restock Note (Optional)</label>
                        <textarea class="form-control" id="restock_note" name="note" rows="2" 
                                  placeholder="e.g., New harvest, Supplier restock"></textarea>
                    </div>
                    
                    <div class="alert alert-success">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Suggested:</strong> Restock to at least <span id="suggested_stock"></span> units
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="updateStock()">
                    <i class="bi bi-check-circle me-1"></i> Restock
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
});

// Stock modal data
var updateStockModal = document.getElementById('updateStockModal');
updateStockModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var productId = button.getAttribute('data-product-id');
    var productName = button.getAttribute('data-product-name');
    var currentStock = button.getAttribute('data-current-stock');
    var unit = button.getAttribute('data-unit');
    var alertLevel = button.getAttribute('data-alert-level');
    
    // Calculate suggested stock (alert level * 2)
    var suggested = parseInt(alertLevel) * 2;
    
    document.getElementById('product_id').value = productId;
    document.getElementById('product_name_display').textContent = productName;
    document.getElementById('current_stock_display').value = currentStock + ' ' + unit;
    document.getElementById('alert_level_display').value = alertLevel + ' ' + unit;
    document.getElementById('stock_quantity').value = suggested;
    document.getElementById('unit_display').textContent = unit;
    document.getElementById('suggested_stock').textContent = suggested + ' ' + unit;
    document.getElementById('restock_note').value = '';
});

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
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restocking...';
    submitBtn.disabled = true;
    
    fetch('../inventory/update-stock.php', {
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

function bulkRestock() {
    if (confirm('Prepare bulk restock for all low stock products?')) {
        window.location.href = 'bulk-restock.php';
    }
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
    
    .card.border-danger {
        border-left: 4px solid #dc3545 !important;
    }
    
    .card.border-warning {
        border-left: 4px solid #ffc107 !important;
    }
    
    .modal-header.bg-warning {
        background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%);
    }
    
    .progress {
        background-color: #e9ecef;
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
    }
    
    @media (max-width: 768px) {
        .card-footer .btn {
            padding: 0.375rem 0.75rem;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>