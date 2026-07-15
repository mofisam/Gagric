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

// Get every item in this order, grouped by seller, so the buyer reviews each
// seller they bought from in this order (their review also counts as the
// review for every product bought from that seller in this order - this
// matches how reviews are already read elsewhere in the app, e.g.
// seller/products/view-product.php, which pulls product reviews from
// seller_ratings via order_items).
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

// Find which sellers in this order the buyer has already reviewed
$existing_reviews = $db->fetchAll("
    SELECT seller_id, rating, review, created_at
    FROM seller_ratings
    WHERE order_id = ? AND buyer_id = ?
", [$order_id, $user_id]);

$reviewed_map = [];
foreach ($existing_reviews as $r) {
    $reviewed_map[$r['seller_id']] = $r;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $submitted_any = false;

        foreach ($sellers as $sid => $sellerData) {
            // Skip sellers already reviewed for this order - one review per seller per order.
            if (isset($reviewed_map[$sid])) {
                continue;
            }

            $rating_key = 'rating_' . $sid;
            $review_key = 'review_' . $sid;

            // A seller block only needs to be processed if the buyer actually
            // gave it a star rating - other sellers in the same order can be
            // reviewed later without forcing all of them at once.
            if (empty($_POST[$rating_key])) {
                continue;
            }

            $rating = (int) $_POST[$rating_key];
            $reviewText = trim($_POST[$review_key] ?? '');

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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-shop me-2"></i>
                        <?php echo htmlspecialchars($sellerData['business_name']); ?>
                    </h5>
                    <?php if (isset($reviewed_map[$sid])): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Reviewed</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Products from this seller in this order:</p>
                    <ul class="list-unstyled mb-4">
                        <?php foreach ($sellerData['items'] as $item): ?>
                            <li class="d-flex align-items-center mb-2">
                                <img src="<?php echo !empty($item['image_path']) ? '../../assets/uploads/products/' . $item['image_path'] : '../../assets/images/placeholder-product.jpg'; ?>"
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" class="me-2">
                                <a href="../products/product-details.php?id=<?php echo $item['product_id']; ?>" class="text-dark text-decoration-none">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (isset($reviewed_map[$sid])):
                        $existing = $reviewed_map[$sid]; ?>
                        <div class="reviewed-block">
                            <div class="star-display mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi <?php echo $i <= $existing['rating'] ? 'bi-star-fill text-warning' : 'bi-star text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="text-muted ms-2"><?php echo formatDate($existing['created_at']); ?></span>
                            </div>
                            <?php if (!empty($existing['review'])): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($existing['review'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <label class="form-label fw-semibold d-block">Your Rating</label>
                        <div class="star-rating mb-3">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?php echo $i . '_' . $sid; ?>" name="rating_<?php echo $sid; ?>" value="<?php echo $i; ?>">
                                <label for="star<?php echo $i . '_' . $sid; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>"><i class="bi bi-star-fill"></i></label>
                            <?php endfor; ?>
                        </div>

                        <label for="review_<?php echo $sid; ?>" class="form-label fw-semibold">Your Review (optional)</label>
                        <textarea class="form-control" id="review_<?php echo $sid; ?>" name="review_<?php echo $sid; ?>"
                                  rows="3" maxlength="1000" placeholder="How was the product quality, packaging, and delivery from this seller?"></textarea>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (count(array_diff_key($sellers, $reviewed_map)) > 0): ?>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-send me-2"></i> Submit Review<?php echo count($sellers) > 1 ? 's' : ''; ?>
            </button>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> You've already reviewed every seller for this order. Thank you!
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