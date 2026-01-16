<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];

// Get products with stock info
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name,
           (SELECT SUM(oi.quantity) FROM order_items oi 
            WHERE oi.product_id = p.id AND oi.status IN ('confirmed', 'shipped')) as reserved_stock
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.seller_id = ? AND p.status = 'approved'
    ORDER BY p.stock_quantity ASC
", [$seller_id]);

$page_title = "Stock Management";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Stock Management</h1>
                <a href="low-stock-alerts.php" class="btn btn-warning">
                    <i class="bi bi-exclamation-triangle"></i> Low Stock Alerts
                </a>
            </div>

            <!-- Stock Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Products</h5>
                            <h2 class="card-text"><?php echo count($products); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">In Stock</h5>
                            <h2 class="card-text">
                                <?php echo count(array_filter($products, fn($p) => $p['stock_quantity'] > 0)); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Low Stock</h5>
                            <h2 class="card-text">
                                <?php echo count(array_filter($products, fn($p) => $p['stock_quantity'] > 0 && $p['stock_quantity'] <= 10)); ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5 class="card-title">Out of Stock</h5>
                            <h2 class="card-text">
                                <?php echo count(array_filter($products, fn($p) => $p['stock_quantity'] <= 0)); ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Stock Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Product Stock Levels</h5>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                        <i class="bi bi-arrow-repeat"></i> Bulk Update
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($products): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Reserved</th>
                                        <th>Available</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <?php
                                        $reserved = $product['reserved_stock'] ?? 0;
                                        $available = $product['stock_quantity'] - $reserved;
                                        $status_class = '';
                                        if ($product['stock_quantity'] <= 0) {
                                            $status_class = 'danger';
                                        } elseif ($product['stock_quantity'] <= 10) {
                                            $status_class = 'warning';
                                        } else {
                                            $status_class = 'success';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $product['name']; ?></strong>
                                                <?php if ($product['variety']): ?>
                                                    <br><small class="text-muted"><?php echo $product['variety']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $product['category_name']; ?></td>
                                            <td>
                                                <span class="fw-bold"><?php echo $product['stock_quantity']; ?></span>
                                            </td>
                                            <td><?php echo $reserved; ?></td>
                                            <td>
                                                <span class="fw-bold text-<?php echo $available <= 0 ? 'danger' : 'success'; ?>">
                                                    <?php echo $available; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php
                                                    if ($product['stock_quantity'] <= 0) echo 'Out of Stock';
                                                    elseif ($product['stock_quantity'] <= 10) echo 'Low Stock';
                                                    else echo 'In Stock';
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#updateStockModal"
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        data-product-name="<?php echo $product['name']; ?>"
                                                        data-current-stock="<?php echo $product['stock_quantity']; ?>">
                                                    <i class="bi bi-pencil"></i> Update
                                                </button>
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
                            <p class="text-muted">Add products to manage your inventory.</p>
                            <a href="../products/add-product.php" class="btn btn-success">Add Products</a>
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
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
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
                    <div class="mb-3">
                        <label for="adjustment_type" class="form-label">Adjustment Type</label>
                        <select class="form-select" id="adjustment_type" name="adjustment_type">
                            <option value="set">Set to this value</option>
                            <option value="add">Add to current stock</option>
                            <option value="subtract">Subtract from current stock</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for adjustment</label>
                        <input type="text" class="form-control" id="reason" name="reason" placeholder="e.g., New harvest, Sales, etc.">
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

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Stock Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkUpdateForm">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current Stock</th>
                                    <th>New Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <?php echo $product['name']; ?>
                                            <?php if ($product['variety']): ?>
                                                <br><small class="text-muted"><?php echo $product['variety']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $product['stock_quantity']; ?></td>
                                        <td>
                                            <input type="number" step="0.01" 
                                                   name="stock[<?php echo $product['id']; ?>]" 
                                                   value="<?php echo $product['stock_quantity']; ?>"
                                                   class="form-control form-control-sm">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="bulkUpdateStock()">Update All</button>
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
    document.getElementById('stock_quantity').value = currentStock;
});

function updateStock() {
    const formData = new FormData(document.getElementById('updateStockForm'));
    
    fetch('update-stock.php', {
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

function bulkUpdateStock() {
    const formData = new FormData(document.getElementById('bulkUpdateForm'));
    
    fetch('bulk-update-stock.php', {
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