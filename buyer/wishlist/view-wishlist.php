<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

requireBuyer();

$db = new Database();
$user_id = getCurrentUserId();

// Get wishlist items
$wishlist_items = $db->fetchAll("
    SELECT w.*, p.name, p.price_per_unit, p.unit, pi.image_path, p.stock_quantity, 
           p.status as product_status, sp.business_name as seller_name 
    FROM wishlists w 
    JOIN products p ON w.product_id = p.id 
    JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    WHERE w.user_id = ? AND p.status = 'approved'
    ORDER BY w.created_at DESC
", [$user_id]);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove'])) {
        $product_id = $_POST['remove'];
        $db->query("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
        setFlashMessage('Item removed from wishlist', 'success');
        header('Location: view-wishlist.php');
        exit;
    } elseif (isset($_POST['add_to_cart'])) {
        $product_id = $_POST['add_to_cart'];
        // Check if already in cart
        $existing = $db->fetchOne("SELECT * FROM cart WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
        if ($existing) {
            $db->query("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
        } else {
            $db->query("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)", [$user_id, $product_id]);
        }
        setFlashMessage('Item added to cart', 'success');
        header('Location: view-wishlist.php');
        exit;
    } elseif (isset($_POST['clear_all'])) {
        $db->query("DELETE FROM wishlists WHERE user_id = ?", [$user_id]);
        setFlashMessage('Wishlist cleared', 'success');
        header('Location: view-wishlist.php');
        exit;
    }
}
?>
<?php 
$page_title = "My Wishlist";
$page_css = 'wishlist.css';
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>My Wishlist</h2>
            <p class="text-muted mb-0">Save products you're interested in for later</p>
        </div>
        <?php if (!empty($wishlist_items)): ?>
            <form method="POST" action="" onsubmit="return confirm('Clear all items from wishlist?')">
                <button name="clear_all" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1"></i> Clear All
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($wishlist_items)): ?>
        <div class="card text-center py-5">
            <i class="bi bi-heart text-muted" style="font-size: 4rem;"></i>
            <h4 class="mt-3">Your wishlist is empty</h4>
            <p class="text-muted">Save products you like to your wishlist for easy access later</p>
            <a href="../products/browse.php" class="btn btn-success">Browse Products</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($wishlist_items as $item): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <a href="../products/product-details.php?id=<?php echo $item['product_id']; ?>" class="text-decoration-none">
                            <img src="<?php echo !empty($item['image_path']) ? '../../assets/uploads/products/' . $item['image_path'] : '../../assets/images/placeholder-product.jpg'; ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($item['name']); ?>" style="height: 200px; object-fit: cover;">
                        </a>
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="../products/product-details.php?id=<?php echo $item['product_id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </h6>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($item['seller_name']); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="text-success mb-0"><?php echo formatCurrency($item['price_per_unit']); ?></h5>
                                <small class="text-muted">/<?php echo $item['unit']; ?></small>
                            </div>
                            
                            <?php if ($item['stock_quantity'] > 0): ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0">
                            <div class="d-grid gap-2">
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="add_to_cart" value="<?php echo $item['product_id']; ?>">
                                    <button class="btn btn-success w-100">
                                        <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                    </button>
                                </form>
                                
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Remove from wishlist?')">
                                    <input type="hidden" name="remove" value="<?php echo $item['product_id']; ?>">
                                    <button class="btn btn-outline-danger w-100">
                                        <i class="bi bi-trash me-1"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Wishlist Stats -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3 class="text-success"><?php echo count($wishlist_items); ?></h3>
                        <p class="text-muted mb-0">Total Items</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-success">
                            <?php 
                            $in_stock = count(array_filter($wishlist_items, function($item) {
                                return $item['stock_quantity'] > 0;
                            }));
                            echo $in_stock;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">In Stock</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-success">
                            <?php
                            $total_value = 0;
                            foreach ($wishlist_items as $item) {
                                $total_value += $item['price_per_unit'];
                            }
                            echo formatCurrency($total_value);
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Total Value</p>
                    </div>
                    <div class="col-md-3">
                        <a href="../products/browse.php" class="btn btn-success mt-2">
                            <i class="bi bi-plus-circle me-1"></i> Add More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>