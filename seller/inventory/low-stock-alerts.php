<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];

// Get low stock products
$low_stock_products = $db->fetchAll("
    SELECT p.*, c.name as category_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.seller_id = ? AND p.status = 'approved' AND p.stock_quantity <= p.low_stock_alert_level
    ORDER BY p.stock_quantity ASC
", [$seller_id]);

$page_title = "Low Stock Alerts";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Low Stock Alerts</h1>
                <span class="badge bg-danger"><?php echo count($low_stock_products); ?> Alerts</span>
            </div>

            <?php if ($low_stock_products): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    You have <strong><?php echo count($low_stock_products); ?> product(s)</strong> with low stock levels.
                    Please restock to avoid running out.
                </div>

                <div class="row">
                    <?php foreach ($low_stock_products as $product): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card border-warning h-100">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Low Stock Alert
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo $product['name']; ?></h6>
                                    <p class="card-text text-muted"><?php echo $product['category_name']; ?></p>
                                    <div class="mb-2">
                                        <strong>Current Stock:</strong> 
                                        <span class="text-danger fw-bold"><?php echo $product['stock_quantity']; ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Alert Level:</strong> <?php echo $product['low_stock_alert_level']; ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Price:</strong> <?php echo formatCurrency($product['price_per_unit']); ?>/<?php echo $product['unit']; ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" data-bs-target="#updateStockModal"
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo $product['name']; ?>"
                                            data-current-stock="<?php echo $product['stock_quantity']; ?>">
                                        <i class="bi bi-arrow-up"></i> Restock
                                    </button>
                                    <a href="../products/edit-product.php?id=<?php echo $product['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle display-1 text-success"></i>
                    <h4 class="text-success mt-3">All products are well stocked!</h4>
                    <p class="text-muted">No low stock alerts at this time.</p>
                    <a href="stock-management.php" class="btn btn-success">View Stock Management</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Update Stock Modal (same as in stock-management.php) -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restock Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="updateStockForm">
                    <input type="hidden" id="product_id" name="product_id">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <p class="form-control-plaintext" id="product_name_display"></p>
                    </div>
                    <div class="mb-3">
                        <label for="stock_quantity" class="form-label">New Stock Quantity</label>
                        <input type="number" step="0.01" class="form-control" id="stock_quantity" name="stock_quantity" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateStock()">Update Stock</button>
            </div>
        </div>
    </div>
</div>

<script>
var updateStockModal = document.getElementById('updateStockModal');
updateStockModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var productId = button.getAttribute('data-product-id');
    var productName = button.getAttribute('data-product-name');
    var currentStock = button.getAttribute('data-current-stock');
    
    document.getElementById('product_id').value = productId;
    document.getElementById('product_name_display').textContent = productName;
    document.getElementById('stock_quantity').value = Math.max(parseInt(currentStock) + 10, 20); // Suggest restocking
});

function updateStock() {
    const formData = new FormData(document.getElementById('updateStockForm'));
    
    fetch('../inventory/update-stock.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>