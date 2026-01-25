<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';

$db = new Database();

$seller_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? '';

// Build query based on filters
$where = "seller_id = ?";
$params = [$seller_id];

if ($status_filter && in_array($status_filter, ['draft', 'pending', 'approved', 'rejected'])) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
}

$products = $db->fetchAll("
    SELECT p.*, c.name as category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as primary_image
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE $where 
    ORDER BY p.created_at DESC
", $params);

$page_title = "Manage Products";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Products</h1>
                <a href="add-product.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Add New Product
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button class="btn btn-primary">Filter</button>
                                <a href="manage-products.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-body">
                    <?php if ($products): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($product['primary_image']): ?>
                                                        <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $product['primary_image']; ?>" 
                                                             alt="<?php echo $product['name']; ?>" 
                                                             class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="bi bi-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo $product['name']; ?></strong>
                                                        <?php if ($product['variety']): ?>
                                                            <br><small class="text-muted"><?php echo $product['variety']; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $product['category_name']; ?></td>
                                            <td><?php echo formatCurrency($product['price_per_unit']); ?>/<?php echo $product['unit']; ?></td>
                                            <td>
                                                <span class="<?php echo $product['stock_quantity'] <= 10 ? 'text-danger fw-bold' : ''; ?>">
                                                    <?php echo $product['stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo getStatusBadge($product['status']); ?></td>
                                            <td><?php echo formatDate($product['created_at']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($product['status'] == 'approved'): ?>
                                                        <a href="#" class="btn btn-outline-success" title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $product['id']; ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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
                            <p class="text-muted">Get started by adding your first product.</p>
                            <a href="add-product.php" class="btn btn-success">Add Your First Product</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function confirmDelete(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        window.location.href = 'delete-product.php?id=' + productId;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>