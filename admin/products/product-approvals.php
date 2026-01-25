<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';
require_once '../../classes/Product.php';


$db = new Database();
$productManager = new Product($db);

// Handle approval actions
if (isset($_POST['action']) && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve') {
        $db->query(
            "UPDATE products SET status = 'approved', approved_at = NOW() WHERE id = ?",
            [$product_id]
        );
        setFlashMessage('Product approved successfully', 'success');
    } elseif ($action === 'reject') {
        $db->query(
            "UPDATE products SET status = 'rejected', admin_notes = ? WHERE id = ?",
            [$notes, $product_id]
        );
        setFlashMessage('Product rejected', 'warning');
    }
    
    header('Location: product-approvals.php');
    exit;
}

// Bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_products'])) {
    $selected_ids = $_POST['selected_products'];
    $bulk_action = $_POST['bulk_action'];
    
    if (!empty($selected_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        
        if ($bulk_action === 'approve') {
            $db->query(
                "UPDATE products SET status = 'approved', approved_at = NOW() WHERE id IN ($placeholders)",
                $selected_ids
            );
            setFlashMessage(count($selected_ids) . ' products approved', 'success');
        } elseif ($bulk_action === 'reject') {
            $db->query(
                "UPDATE products SET status = 'rejected' WHERE id IN ($placeholders)",
                $selected_ids
            );
            setFlashMessage(count($selected_ids) . ' products rejected', 'warning');
        }
    }
    
    header('Location: product-approvals.php');
    exit;
}

// Get pending products for approval
$pending_products = $db->fetchAll("
    SELECT p.*, 
           sp.business_name, 
           u.first_name, 
           u.last_name, 
           u.email as seller_email,
           c.name as category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as primary_image
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN users u ON p.seller_id = u.id 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'pending' 
    ORDER BY p.created_at DESC
");

// Stats
$total_pending = count($pending_products);
$today_pending = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE status = 'pending' AND DATE(created_at) = CURDATE()")['count'];
$pending_by_category = $db->fetchAll("
    SELECT c.name, COUNT(p.id) as count 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'pending' 
    GROUP BY c.id 
    ORDER BY count DESC
");

$page_title = "Product Approvals";
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
                        <h1 class="h5 mb-0 text-center">Product Approvals</h1>
                        <small class="text-muted d-block text-center"><?php echo $total_pending; ?> pending</small>
                    </div>
                    <?php if($total_pending > 0): ?>
                        <span class="badge bg-warning"><?php echo $total_pending; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Product Approvals</h1>
                    <p class="text-muted mb-0">Review and approve seller products</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if($total_pending > 0): ?>
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshApprovals">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="bi bi-lightning-charge me-1"></i> Quick Actions
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <button class="dropdown-item" type="button" 
                                            onclick="approveAllPending()">
                                        <i class="bi bi-check-circle me-2"></i> Approve All1
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item" type="button" 
                                            onclick="exportPendingProducts()">
                                        <i class="bi bi-download me-2"></i> Export List
                                    </button>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Pending</small>
                                <h6 class="mb-0 text-warning"><?php echo $total_pending; ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Today</small>
                                <h6 class="mb-0"><?php echo $today_pending; ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 mt-2">
                        <?php if(!empty($pending_by_category)): ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach($pending_by_category as $cat): ?>
                                    <span class="badge bg-info">
                                        <?php echo $cat['name']; ?>: <?php echo $cat['count']; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="d-none d-md-flex row g-3 mb-4">
                <!-- Total Pending -->
                <div class="col-md-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending Approval</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($total_pending); ?></h3>
                                    <small class="text-warning">
                                        <?php echo $today_pending; ?> submitted today
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-clock fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- By Category Stats -->
                <div class="col-md-9">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <h6 class="card-subtitle text-muted mb-3">Pending by Category</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <?php if(empty($pending_by_category)): ?>
                                    <p class="text-muted mb-0">No pending products by category</p>
                                <?php else: ?>
                                    <?php foreach($pending_by_category as $cat): ?>
                                        <div class="text-center">
                                            <div class="bg-info bg-opacity-10 p-3 rounded-circle mb-2">
                                                <i class="bi bi-tag fs-4 text-info"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $cat['count']; ?></h6>
                                                <small class="text-muted"><?php echo $cat['name']; ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <?php if($total_pending > 0): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-check-all me-2 text-primary"></i>
                            Bulk Actions
                        </h5>
                        <small class="text-muted">Select multiple products for batch processing</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="bulkForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <select name="bulk_action" class="form-select" required>
                                            <option value="">Choose action...</option>
                                            <option value="approve">Approve Selected</option>
                                            <option value="reject">Reject Selected</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary">
                                            Apply to Selected
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllProducts">
                                        <label class="form-check-label" for="selectAllProducts">
                                            Select all <?php echo $total_pending; ?> pending products
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div id="selectedProductsInputs"></div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">Pending Products (<?php echo $total_pending; ?>)</h5>
                    <small class="text-muted">
                        <?php echo $total_pending; ?> products awaiting review
                    </small>
                </div>
                <?php if($total_pending > 0): ?>
                    <div class="text-muted d-none d-md-block">
                        <button class="btn btn-sm btn-outline-success" onclick="approveAllPending()">
                            <i class="bi bi-check-all me-1"></i> Approve All3
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Products Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($pending_products)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">All caught up!</h4>
                            <p class="text-muted mb-4">No pending products need approval.</p>
                            <a href="manage-products.php" class="btn btn-primary">
                                <i class="bi bi-box-seam me-1"></i> Manage Products
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="60" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllHeader">
                                        </th>
                                        <th>Product</th>
                                        <th width="120">Seller</th>
                                        <th width="100">Category</th>
                                        <th width="100">Price</th>
                                        <th width="80">Stock</th>
                                        <th width="120">Submitted</th>
                                        <th width="140" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_products as $product): ?>
                                        <?php 
                                        $unit = $product['unit'];
                                        $price = $product['price_per_unit'];
                                        $stock = $product['stock_quantity'];
                                        $min_order = $product['min_order_quantity'];
                                        $product_type = $product['product_type'];
                                        ?>
                                        
                                        <tr class="product-row" data-product-id="<?php echo $product['id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell text-center">
                                                <input type="checkbox" class="form-check-input product-checkbox" 
                                                       name="selected_products[]" value="<?php echo $product['id']; ?>">
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <?php if($product['primary_image']): ?>
                                                        <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $product['primary_image']; ?>" 
                                                             class="rounded me-2" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary bg-opacity-10 p-2 rounded-circle me-2">
                                                            <i class="bi bi-image text-secondary"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo ucfirst($product_type); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($product['business_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <strong class="text-success">₦<?php echo number_format($price, 2); ?></strong>
                                                <br>
                                                <small class="text-muted">per <?php echo $unit; ?></small>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo number_format($stock, 2); ?> <?php echo $unit; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo date('M j', strtotime($product['created_at'])); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($product['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#approveModal<?php echo $product['id']; ?>">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#rejectModal<?php echo $product['id']; ?>">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewModal<?php echo $product['id']; ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Product & Selection -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <input type="checkbox" class="form-check-input product-checkbox me-2" 
                                                                   name="selected_products[]" value="<?php echo $product['id']; ?>">
                                                            <?php if($product['primary_image']): ?>
                                                                <img src="<?php echo BASE_URL . '/' . $product['primary_image']; ?>" 
                                                                     class="rounded me-2" 
                                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                            <?php endif; ?>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars(substr($product['name'], 0, 30)); ?></strong>
                                                                <br>
                                                                <span class="badge bg-info">
                                                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <strong class="text-success">₦<?php echo number_format($price, 2); ?></strong>
                                                    </div>
                                                    
                                                    <!-- Row 2: Seller & Stock -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <small class="text-muted">Seller:</small>
                                                                <br>
                                                                <small><?php echo htmlspecialchars(substr($product['business_name'], 0, 20)); ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted">Stock:</small>
                                                                <br>
                                                                <small><?php echo number_format($stock, 2); ?> <?php echo $unit; ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Details & Actions -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <small class="text-muted d-block">
                                                                <?php echo date('M j, g:i A', strtotime($product['created_at'])); ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                Min: <?php echo number_format($min_order, 2); ?> <?php echo $unit; ?>
                                                            </small>
                                                        </div>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-sm btn-outline-success" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#approveModal<?php echo $product['id']; ?>">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#rejectModal<?php echo $product['id']; ?>">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewModal<?php echo $product['id']; ?>">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
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
        </main>
    </div>
</div>

<!-- Main form for individual actions - Kept at the page level -->
<form method="POST" id="actionForm" style="display: none;">
    <input type="hidden" name="product_id" id="actionProductId">
    <input type="hidden" name="action" id="actionType">
    <textarea name="notes" id="actionNotes"></textarea>
</form>

<!-- Modals for each product -->
<?php foreach ($pending_products as $product): ?>
    <!-- View Modal -->
    <div class="modal fade" id="viewModal<?php echo $product['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-eye me-2"></i>
                        Product Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Product Information</h6>
                                    <div class="mb-2">
                                        <small class="text-muted">Name</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($product['name']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Category</small>
                                        <p class="mb-0">
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Type</small>
                                        <p class="mb-0"><?php echo ucfirst($product['product_type']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Description</small>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Seller Information</h6>
                                    <div class="mb-2">
                                        <small class="text-muted">Business</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($product['business_name']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Seller</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Email</small>
                                        <p class="mb-0"><?php echo htmlspecialchars($product['seller_email']); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Submitted</small>
                                        <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($product['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Pricing & Inventory</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-2">
                                                <small class="text-muted">Price</small>
                                                <h5 class="mb-0 text-success">₦<?php echo number_format($product['price_per_unit'], 2); ?></h5>
                                                <small class="text-muted">per <?php echo $product['unit']; ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-2">
                                                <small class="text-muted">Stock</small>
                                                <h5 class="mb-0"><?php echo number_format($product['stock_quantity'], 2); ?></h5>
                                                <small class="text-muted"><?php echo $product['unit']; ?> available</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-2">
                                                <small class="text-muted">Min Order</small>
                                                <h5 class="mb-0"><?php echo number_format($product['min_order_quantity'], 2); ?></h5>
                                                <small class="text-muted"><?php echo $product['unit']; ?> minimum</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-2">
                                                <small class="text-muted">Max Order</small>
                                                <h5 class="mb-0"><?php echo $product['max_order_quantity'] ? number_format($product['max_order_quantity'], 2) : 'No limit'; ?></h5>
                                                <small class="text-muted">per order</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="d-flex gap-2 w-100">
                        <button class="btn btn-success flex-fill" 
                                onclick="submitApproval(<?php echo $product['id']; ?>)">
                            <i class="bi bi-check me-1"></i> Approve
                        </button>
                        <button class="btn btn-danger flex-fill" 
                                onclick="submitRejection(<?php echo $product['id']; ?>)">
                            <i class="bi bi-x me-1"></i> Reject
                        </button>
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal<?php echo $product['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle me-2"></i>
                        Approve Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="approveProductId" value="<?php echo $product['id']; ?>">
                    
                    <p>Are you sure you want to approve this product?</p>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" id="approveNotes<?php echo $product['id']; ?>" 
                                  rows="3" placeholder="Add approval notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" 
                            onclick="submitApproval(<?php echo $product['id']; ?>)">
                        <i class="bi bi-check me-1"></i> Approve Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal<?php echo $product['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle me-2"></i>
                        Reject Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rejectProductId" value="<?php echo $product['id']; ?>">
                    
                    <p>Please provide a reason for rejecting this product:</p>
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectNotes<?php echo $product['id']; ?>" 
                                  rows="4" required
                                  placeholder="Explain why this product is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" 
                            onclick="submitRejection(<?php echo $product['id']; ?>)">
                        <i class="bi bi-x me-1"></i> Reject Product
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

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
    
    // Refresh approvals
    const refreshBtn = document.getElementById('refreshApprovals');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            this.disabled = true;
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    }
    
    // Select all checkboxes
    const selectAllHeader = document.getElementById('selectAllHeader');
    const selectAllProducts = document.getElementById('selectAllProducts');
    
    function toggleAllCheckboxes(checked) {
        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
        });
    }
    
    if (selectAllHeader) {
        selectAllHeader.addEventListener('change', function() {
            toggleAllCheckboxes(this.checked);
        });
    }
    
    if (selectAllProducts) {
        selectAllProducts.addEventListener('change', function() {
            toggleAllCheckboxes(this.checked);
        });
    }
    
    // Make table rows clickable on mobile to show details
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('input') && !e.target.closest('a')) {
                const productId = this.closest('.product-row').dataset.productId;
                const modal = new bootstrap.Modal(document.getElementById('viewModal' + productId));
                modal.show();
            }
        });
    });
    
    // Handle bulk form submission
    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            const selectedProducts = document.querySelectorAll('.product-checkbox:checked');
            if (selectedProducts.length === 0) {
                e.preventDefault();
                agriApp.showToast('Please select at least one product', 'error');
                return false;
            }
            
            // Create hidden inputs for selected products
            const container = document.getElementById('selectedProductsInputs');
            container.innerHTML = '';
            
            selectedProducts.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_products[]';
                input.value = checkbox.value;
                container.appendChild(input);
            });
        });
    }
});

function submitApproval(productId) {
    const notes = document.getElementById('approveNotes' + productId)?.value || '';
    
    // Submit via hidden form
    document.getElementById('actionProductId').value = productId;
    document.getElementById('actionType').value = 'approve';
    document.getElementById('actionNotes').value = notes;
    document.getElementById('actionForm').submit();
}

function submitRejection(productId) {
    const notesInput = document.getElementById('rejectNotes' + productId);
    const notes = notesInput?.value || '';
    
    if (!notes.trim()) {
        agriApp.showToast('Please provide a rejection reason', 'error');
        notesInput?.focus();
        return;
    }
    
    // Submit via hidden form
    document.getElementById('actionProductId').value = productId;
    document.getElementById('actionType').value = 'reject';
    document.getElementById('actionNotes').value = notes;
    document.getElementById('actionForm').submit();
}

function approveAllPending() {
    agriApp.confirm(
        'Approve All Products',
        'Are you sure you want to approve all pending products? This action cannot be undone.',
        'Approve All',
        'Cancel',
        function() {
            // Check all checkboxes
            document.querySelectorAll('.product-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            
            // Set bulk action and submit
            document.querySelector('#bulkForm [name="bulk_action"]').value = 'approve';
            document.getElementById('bulkForm').submit();
        }
    );
}

function exportPendingProducts() {
    agriApp.showToast('Export feature coming soon', 'info');
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
        
        /* Status badges compact */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        /* Product images */
        img {
            max-width: 100%;
            height: auto;
        }
        
        /* Checkboxes */
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-top: 0.25rem;
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