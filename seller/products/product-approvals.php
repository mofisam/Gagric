<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';

$db = new Database();

$seller_id = $_SESSION['user_id'];

// Get pending approvals
$pending_products = $db->fetchAll("
    SELECT p.*, c.name as category_name, pa.admin_notes, pa.reviewed_at
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN product_approvals pa ON p.id = pa.product_id AND pa.status = 'pending_review'
    WHERE p.seller_id = ? AND p.status = 'pending'
    ORDER BY p.created_at DESC
", [$seller_id]);

$page_title = "Product Approvals";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Pending Approvals</h1>
                <span class="badge bg-warning"><?php echo count($pending_products); ?> Pending</span>
            </div>

            <?php if ($pending_products): ?>
                <div class="row">
                    <?php foreach ($pending_products as $product): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                    <p class="card-text text-muted"><?php echo $product['category_name']; ?></p>
                                    <p class="card-text">
                                        <strong>Price:</strong> <?php echo formatCurrency($product['price_per_unit']); ?>/<?php echo $product['unit']; ?><br>
                                        <strong>Stock:</strong> <?php echo $product['stock_quantity']; ?><br>
                                        <strong>Submitted:</strong> <?php echo formatDate($product['created_at']); ?>
                                    </p>
                                    <?php if ($product['admin_notes']): ?>
                                        <div class="alert alert-info">
                                            <strong>Admin Note:</strong> <?php echo $product['admin_notes']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="view-product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle display-1 text-success"></i>
                    <h4 class="text-success mt-3">No Pending Approvals</h4>
                    <p class="text-muted">All your products have been reviewed.</p>
                    <a href="add-product.php" class="btn btn-success">Add New Product</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>