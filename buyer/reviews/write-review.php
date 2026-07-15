<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

$order_id = $_GET['order'] ?? 0;
$order_data = $order->getOrderById($order_id, $user_id);

if (!$order_data) {
    setFlashMessage('Order not found.', 'error');
    header('Location: ../orders/order-history.php');
    exit;
}

if ($order_data['status'] !== 'delivered') {
    setFlashMessage('You can only review products and sellers after your order has been delivered.', 'error');
    header('Location: ../orders/order-details.php?id=' . $order_id);
    exit;
}

// Get every item in this order, grouped by seller. The buyer reviews:
//   - each PRODUCT individually (rating + comment -> `reviews` table)
//   - each SELLER once per order (rating + review -> `seller_ratings` table)
$order_items = $db->fetchAll("
    SELECT oi.id as order_item_id, oi.product_id, oi.product_name, oi.seller_id,
           sp.business_name,
           (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = TRUE LIMIT 1) as image_path
    FROM order_items oi
    JOIN seller_profiles sp ON oi.seller_id = sp.user_id
    WHERE oi.order_id = ?
    ORDER BY sp.business_name, oi.product_name
", [$order_id]);

if (empty($order_items)) {
    setFlashMessage('No items found for this order.', 'error');
    header('Location: ../orders/order-history.php');
    exit;
}

// Group items by seller
$sellers = [];
foreach ($order_items as $item) {
    $sid = $item['seller_id'];
    if (!isset($sellers[$sid])) {
        $sellers[$sid] = [
            'seller_id' => $sid,
            'business_name' => $item['business_name'],
            'items' => [],
        ];
    }
    $sellers[$sid]['items'][] = $item;
}

// Existing seller-level reviews for this order
$existing_seller_reviews = $db->fetchAll("
    SELECT seller_id, rating, review, created_at
    FROM seller_ratings
    WHERE order_id = ? AND buyer_id = ?
", [$order_id, $user_id]);
$seller_reviewed_map = [];
foreach ($existing_seller_reviews as $r) {
    $seller_reviewed_map[$r['seller_id']] = $r;
}

// Existing product-level reviews for this order
$existing_product_reviews = $db->fetchAll("
    SELECT product_id, rating, comment, created_at
    FROM reviews
    WHERE order_id = ? AND user_id = ?
", [$order_id, $user_id]);
$product_reviewed_map = [];
foreach ($existing_product_reviews as $r) {
    $product_reviewed_map[$r['product_id']] = $r;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $submitted_any = false;

        // --- Product reviews ---
        foreach ($order_items as $item) {
            $pid = $item['product_id'];

            if (isset($product_reviewed_map[$pid])) {
                continue; // already reviewed this product for this order
            }

            $rating_key = 'product_rating_' . $pid;
            if (empty($_POST[$rating_key])) {
                continue; // buyer chose not to review this product right now
            }

            $rating = (int) $_POST[$rating_key];
            $comment = trim($_POST['product_comment_' . $pid] ?? '');

            if ($rating < 1 || $rating > 5) {
                $errors[] = 'Please select a rating between 1 and 5 stars for ' . htmlspecialchars($item['product_name']) . '.';
                continue;
            }

            $db->insert('reviews', [
                'user_id' => $user_id,
                'product_id' => $pid,
                'order_id' => $order_id,
                'rating' => $rating,
                'comment' => $comment !== '' ? $comment : null,
                'is_verified_purchase' => 1,
            ]);

            $submitted_any = true;
        }

        // --- Seller reviews ---
        foreach ($sellers as $sid => $sellerData) {
            if (isset($seller_reviewed_map[$sid])) {
                continue; // already reviewed this seller for this order
            }

            $rating_key = 'seller_rating_' . $sid;
            if (empty($_POST[$rating_key])) {
                continue;
            }

            $rating = (int) $_POST[$rating_key];
            $reviewText = trim($_POST['seller_review_' . $sid] ?? '');

            if ($rating < 1 || $rating > 5) {
                $errors[] = 'Please select a rating between 1 and 5 stars for ' . htmlspecialchars($sellerData['business_name']) . '.';
                continue;
            }

            $db->insert('seller_ratings', [
                'seller_id' => $sid,
                'buyer_id' => $user_id,
                'order_id' => $order_id,
                'rating' => $rating,
                'review' => $reviewText !== '' ? $reviewText : null,
            ]);

            $submitted_any = true;
        }

        if ($submitted_any && empty($errors)) {
            setFlashMessage('Thank you! Your review has been submitted.', 'success');
            header('Location: write-review.php?order=' . $order_id);
            exit;
        } elseif (!$submitted_any && empty($errors)) {
            $errors[] = 'Please select at least one star rating before submitting.';
        }
    }
}

$page_title = "Write a Review - Order #" . $order_data['order_number'];
include '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Write a Review</h2>
            <p class="text-muted mb-0">Order #<?php echo htmlspecialchars($order_data['order_number']); ?></p>
        </div>
        <a href="../orders/order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-success">
            <i class="bi bi-arrow-left me-1"></i> Back to Order
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">

        <?php foreach ($sellers as $sid => $sellerData): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-shop me-2"></i>
                        <?php echo htmlspecialchars($sellerData['business_name']); ?>
                    </h5>
                </div>
                <div class="card-body">

                    <!-- Per-product reviews -->
                    <?php foreach ($sellerData['items'] as $item):
                        $pid = $item['product_id']; ?>
                        <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                            <img src="<?php echo !empty($item['image_path']) ? '../../assets/uploads/products/' . $item['image_path'] : '../../assets/images/placeholder-product.jpg'; ?>"
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" class="me-3 flex-shrink-0">
                            <div class="flex-grow-1">
                                <a href="../products/product-details.php?id=<?php echo $pid; ?>" class="text-dark text-decoration-none fw-semibold">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </a>

                                <?php if (isset($product_reviewed_map[$pid])):
                                    $existing = $product_reviewed_map[$pid]; ?>
                                    <div class="reviewed-block mt-2">
                                        <span class="badge bg-success mb-1"><i class="bi bi-check-circle me-1"></i> Reviewed</span>
                                        <div class="star-display">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi <?php echo $i <= $existing['rating'] ? 'bi-star-fill text-warning' : 'bi-star text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="text-muted ms-2 small"><?php echo formatDate($existing['created_at']); ?></span>
                                        </div>
                                        <?php if (!empty($existing['comment'])): ?>
                                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($existing['comment'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="star-rating star-rating-sm mt-1 mb-2">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" id="prod_star<?php echo $i . '_' . $pid; ?>" name="product_rating_<?php echo $pid; ?>" value="<?php echo $i; ?>">
                                            <label for="prod_star<?php echo $i . '_' . $pid; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>"><i class="bi bi-star-fill"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                    <textarea class="form-control" name="product_comment_<?php echo $pid; ?>"
                                              rows="2" maxlength="1000" placeholder="What did you think of this product?"></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Seller-level review -->
                    <div class="mt-3">
                        <h6 class="fw-semibold">Rate this Seller</h6>
                        <?php if (isset($seller_reviewed_map[$sid])):
                            $existing = $seller_reviewed_map[$sid]; ?>
                            <div class="reviewed-block">
                                <span class="badge bg-success mb-1"><i class="bi bi-check-circle me-1"></i> Reviewed</span>
                                <div class="star-display">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi <?php echo $i <= $existing['rating'] ? 'bi-star-fill text-warning' : 'bi-star text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted ms-2 small"><?php echo formatDate($existing['created_at']); ?></span>
                                </div>
                                <?php if (!empty($existing['review'])): ?>
                                    <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($existing['review'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="star-rating mb-2">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="seller_star<?php echo $i . '_' . $sid; ?>" name="seller_rating_<?php echo $sid; ?>" value="<?php echo $i; ?>">
                                    <label for="seller_star<?php echo $i . '_' . $sid; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>"><i class="bi bi-star-fill"></i></label>
                                <?php endfor; ?>
                            </div>
                            <textarea class="form-control" name="seller_review_<?php echo $sid; ?>"
                                      rows="2" maxlength="1000" placeholder="How was the packaging, delivery, and overall experience with this seller?"></textarea>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>

        <?php
        $anything_left = false;
        foreach ($order_items as $item) {
            if (!isset($product_reviewed_map[$item['product_id']])) { $anything_left = true; break; }
        }
        if (!$anything_left) {
            foreach ($sellers as $sid => $sellerData) {
                if (!isset($seller_reviewed_map[$sid])) { $anything_left = true; break; }
            }
        }
        ?>
        <?php if ($anything_left): ?>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-send me-2"></i> Submit Review
            </button>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> You've already reviewed every product and seller for this order. Thank you!
            </div>
        <?php endif; ?>
    </form>
</div>

<style>
.star-rating {
    display: inline-flex;
    flex-direction: row-reverse;
    font-size: 1.8rem;
}
.star-rating-sm {
    font-size: 1.3rem;
}
.star-rating input {
    display: none;
}
.star-rating label {
    color: #d3d3d3;
    cursor: pointer;
    padding: 0 2px;
    transition: color 0.15s ease-in-out;
}
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label {
    color: #ffc107;
}
</style>

<?php include '../../includes/footer.php'; ?>