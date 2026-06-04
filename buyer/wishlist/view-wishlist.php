<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

requireBuyer();

$db = new Database();
$user_id = getCurrentUserId();
$csrf_token = getCSRFToken();

function wishlistProductImage($image_path) {
    if (!empty($image_path)) {
        return BASE_URL . '/assets/uploads/products/' . $image_path;
    }

    return BASE_URL . '/assets/images/placeholder-product.jpg';
}

// Handle actions before loading the list so the page always reflects the latest state.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request. Please try again.', 'danger');
        header('Location: view-wishlist.php');
        exit;
    }

    if (isset($_POST['remove'])) {
        $product_id = (int)$_POST['remove'];
        $db->query("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
        setFlashMessage('Item removed from wishlist', 'success');
        header('Location: view-wishlist.php');
        exit;
    }

    if (isset($_POST['add_to_cart'])) {
        $product_id = (int)$_POST['add_to_cart'];
        $product_for_cart = $db->fetchOne("
            SELECT id, stock_quantity
            FROM products
            WHERE id = ? AND status = 'approved'
            LIMIT 1
        ", [$product_id]);

        if (!$product_for_cart) {
            setFlashMessage('This product is not currently available for purchase.', 'warning');
        } elseif ((float)$product_for_cart['stock_quantity'] <= 0) {
            setFlashMessage('This product is currently out of stock.', 'warning');
        } else {
            $existing = $db->fetchOne("SELECT id FROM cart WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);

            if ($existing) {
                $db->query("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
            } else {
                $db->query("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)", [$user_id, $product_id]);
            }

            setFlashMessage('Item added to cart', 'success');
        }

        header('Location: view-wishlist.php');
        exit;
    }

    if (isset($_POST['clear_all'])) {
        $db->query("DELETE FROM wishlists WHERE user_id = ?", [$user_id]);
        setFlashMessage('Wishlist cleared', 'success');
        header('Location: view-wishlist.php');
        exit;
    }
}

// Load wishlist items. Product images and seller profiles are optional, so use LEFT JOINs.
$wishlist_items = $db->fetchAll("
    SELECT
        w.product_id,
        w.created_at as wished_at,
        p.name,
        p.price_per_unit,
        p.unit,
        p.stock_quantity,
        p.status as product_status,
        product_img.image_path,
        COALESCE(sp.business_name, 'Green Agric Seller') as seller_name
    FROM wishlists w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN (
        SELECT
            product_id,
            COALESCE(
                MAX(CASE WHEN is_primary = 1 THEN image_path END),
                MIN(image_path)
            ) as image_path
        FROM product_images
        GROUP BY product_id
    ) product_img ON p.id = product_img.product_id
    LEFT JOIN seller_profiles sp ON p.seller_id = sp.user_id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
", [$user_id]);

$total_items = count($wishlist_items);
$available_items = count(array_filter($wishlist_items, function ($item) {
    return $item['product_status'] === 'approved' && (float)$item['stock_quantity'] > 0;
}));
$unavailable_items = $total_items - $available_items;
$total_value = array_reduce($wishlist_items, function ($sum, $item) {
    return $sum + (float)$item['price_per_unit'];
}, 0);
?>
<?php
$page_title = "My Wishlist";
include '../../includes/header.php';
?>

<style>
body {
    background: #f8fafc;
}

.wishlist-hero,
.wishlist-panel,
.wishlist-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.wishlist-hero {
    padding: 1.5rem;
}

.wishlist-stat {
    padding: 1rem;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    height: 100%;
}

.wishlist-card {
    height: 100%;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.wishlist-card:hover {
    transform: translateY(-3px);
    border-color: rgba(25, 135, 84, 0.35);
    box-shadow: 0 16px 36px rgba(25, 135, 84, 0.12);
}

.wishlist-image {
    aspect-ratio: 4 / 3;
    width: 100%;
    object-fit: cover;
    background: #eef2f7;
}

.wishlist-title {
    min-height: 2.6rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.wishlist-card .btn {
    min-height: 42px;
}

.empty-wishlist {
    min-height: 360px;
}

@media (max-width: 576px) {
    .wishlist-hero {
        padding: 1.25rem;
    }
}
</style>

<div class="container py-4">
    <div class="wishlist-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php" class="text-success text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Wishlist</li>
                    </ol>
                </nav>
                <h1 class="h3 fw-bold mb-2">My Wishlist</h1>
                <p class="text-muted mb-0">Keep products you like close by and move available items into your cart when you are ready.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="../products/browse.php" class="btn btn-success fw-semibold">
                    <i class="bi bi-plus-circle me-2"></i> Add More
                </a>
                <?php if (!empty($wishlist_items)): ?>
                    <form method="POST" onsubmit="return confirm('Clear all items from wishlist?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button type="submit" name="clear_all" value="1" class="btn btn-outline-danger fw-semibold">
                            <i class="bi bi-trash me-2"></i> Clear All
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($wishlist_items)): ?>
        <div class="wishlist-panel empty-wishlist d-flex align-items-center justify-content-center text-center p-4">
            <div>
                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 86px; height: 86px;">
                    <i class="bi bi-heart text-success" style="font-size: 2.5rem;"></i>
                </div>
                <h4 class="fw-bold">Your wishlist is empty</h4>
                <p class="text-muted mb-4">Save products you like so you can find them faster next time.</p>
                <a href="../products/browse.php" class="btn btn-success px-4">
                    <i class="bi bi-search me-2"></i> Browse Products
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="wishlist-stat">
                    <small class="text-muted">Total Items</small>
                    <div class="h4 fw-bold mb-0"><?php echo $total_items; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="wishlist-stat">
                    <small class="text-muted">Available</small>
                    <div class="h4 fw-bold text-success mb-0"><?php echo $available_items; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="wishlist-stat">
                    <small class="text-muted">Unavailable</small>
                    <div class="h4 fw-bold text-warning mb-0"><?php echo $unavailable_items; ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="wishlist-stat">
                    <small class="text-muted">Wishlist Value</small>
                    <div class="h5 fw-bold text-success mb-0"><?php echo formatCurrency($total_value); ?></div>
                </div>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php foreach ($wishlist_items as $item): ?>
                <?php
                    $is_available = $item['product_status'] === 'approved' && (float)$item['stock_quantity'] > 0;
                    $is_approved = $item['product_status'] === 'approved';
                    $image_url = wishlistProductImage($item['image_path']);
                ?>
                <div class="col">
                    <div class="wishlist-card">
                        <div class="position-relative">
                            <?php if ($is_approved): ?>
                                <a href="../products/product-details.php?id=<?php echo (int)$item['product_id']; ?>">
                                    <img src="<?php echo htmlspecialchars($image_url); ?>" class="wishlist-image" alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy">
                                </a>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($image_url); ?>" class="wishlist-image opacity-75" alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy">
                            <?php endif; ?>

                            <span class="badge <?php echo $is_available ? 'bg-success' : 'bg-warning text-dark'; ?> position-absolute top-0 start-0 m-2">
                                <?php echo $is_available ? 'In Stock' : 'Unavailable'; ?>
                            </span>
                        </div>

                        <div class="p-3">
                            <p class="small text-muted mb-1"><?php echo htmlspecialchars($item['seller_name']); ?></p>
                            <h2 class="h6 fw-bold wishlist-title mb-2">
                                <?php if ($is_approved): ?>
                                    <a href="../products/product-details.php?id=<?php echo (int)$item['product_id']; ?>" class="text-dark text-decoration-none">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                <?php endif; ?>
                            </h2>

                            <div class="d-flex justify-content-between align-items-end mb-3">
                                <div>
                                    <div class="text-success fw-bold"><?php echo formatCurrency($item['price_per_unit']); ?></div>
                                    <small class="text-muted">per <?php echo htmlspecialchars($item['unit']); ?></small>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-calendar-heart me-1"></i><?php echo formatDate($item['wished_at'], 'M j'); ?>
                                </small>
                            </div>

                            <?php if (!$is_approved): ?>
                                <div class="alert alert-light border py-2 small mb-3">
                                    This product is no longer available for purchase.
                                </div>
                            <?php elseif (!$is_available): ?>
                                <div class="alert alert-light border py-2 small mb-3">
                                    Out of stock right now.
                                </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="add_to_cart" value="<?php echo (int)$item['product_id']; ?>">
                                    <button type="submit" class="btn btn-success w-100" <?php echo !$is_available ? 'disabled' : ''; ?>>
                                        <i class="bi bi-cart-plus me-2"></i> Add to Cart
                                    </button>
                                </form>

                                <form method="POST" onsubmit="return confirm('Remove from wishlist?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="remove" value="<?php echo (int)$item['product_id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i class="bi bi-trash me-2"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
