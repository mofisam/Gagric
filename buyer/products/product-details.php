<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Product.php';

$db = new Database();
$product = new Product($db);

$product_id = $_GET['id'] ?? 0;
$product_data = $product->getProductDetails($product_id);

if (!$product_data) {
    setFlashMessage('Product not found', 'error');
    header('Location: browse.php');
    exit;
}

// Get all product images
$product_images = $db->fetchAll(
    "SELECT image_path, alt_text, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order",
    [$product_id]
);

// Get bulk pricing
$bulk_pricing = $db->fetchAll(
    "SELECT * FROM product_bulk_pricing WHERE product_id = ? ORDER BY min_quantity",
    [$product_id]
);

// Get product specifications
$specifications = $db->fetchAll("
    SELECT pa.attribute_name, ps.attribute_value 
    FROM product_specifications ps 
    JOIN product_attributes pa ON ps.attribute_id = pa.id 
    WHERE ps.product_id = ?
", [$product_id]);

// Increment view count
$viewed = $db->fetchOne(
    "SELECT id FROM product_views WHERE product_id = ? AND user_id = ? AND DATE(viewed_at) = CURDATE()",
    [$product_id, getCurrentUserId()]
);

if (!$viewed) {
    $db->query(
        "INSERT INTO product_views (product_id, user_id, ip_address) VALUES (?, ?, ?)",
        [$product_id, getCurrentUserId(), $_SERVER['REMOTE_ADDR']]
    );
}

// Get seller information
$seller_info = $db->fetchOne(
    "SELECT sp.*, u.email, u.phone, u.first_name, u.last_name 
     FROM seller_profiles sp 
     JOIN users u ON sp.user_id = u.id 
     WHERE sp.user_id = ?",
    [$product_data['seller_id']]
);

// Get related products (same category)
$related_products = $db->fetchAll("
    SELECT p.*, sp.business_name 
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'approved' 
    ORDER BY RAND() 
    LIMIT 4
", [$product_data['category_id'], $product_id]);

// Check if product is in wishlist (for logged-in users)
$in_wishlist = false;
if (isLoggedIn()) {
    $wishlist_check = $db->fetchOne(
        "SELECT id FROM wishlist WHERE product_id = ? AND user_id = ?",
        [$product_id, getCurrentUserId()]
    );
    $in_wishlist = (bool)$wishlist_check;
}

$page_title = htmlspecialchars($product_data['name']);
$page_css = 'products.css';
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="browse.php">Products</a></li>
            <li class="breadcrumb-item"><a href="categories.php"><?php echo htmlspecialchars($product_data['category_name'] ?? 'Products'); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product_data['name']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Product Images -->
        <div class="col-md-6">
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="product-image-main text-center mb-3">
                        <?php 
                        $main_image = !empty($product_data['image_path']) ? '../../assets/uploads/products/' . $product_data['image_path'] : '../../assets/images/placeholder-product.jpg';
                        if (!empty($product_images)) {
                            foreach ($product_images as $img) {
                                if ($img['is_primary']) {
                                    $main_image = '../../assets/uploads/products/' . $img['image_path'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <img id="mainImage" 
                             src="<?php echo $main_image; ?>" 
                             class="img-fluid rounded" 
                             alt="<?php echo htmlspecialchars($product_data['name']); ?>" 
                             style="max-height: 400px; object-fit: contain;">
                    </div>
                    
                    <!-- Thumbnails -->
                    <?php if (!empty($product_data['image_path']) || !empty($product_images)): ?>
                        <div class="product-thumbnails">
                            <h6 class="mb-3">Gallery</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                // Add main image as first thumbnail if no product_images
                                if (empty($product_images) && !empty($product_data['image_path'])): 
                                ?>
                                    <img src="../../assets/uploads/products/<?php echo $product_data['image_path']; ?>" 
                                         class="img-thumbnail thumbnail-active" 
                                         style="width: 80px; height: 80px; cursor: pointer; object-fit: cover;"
                                         onclick="changeMainImage(this.src)"
                                         alt="Main Image">
                                <?php endif; ?>
                                
                                <?php foreach ($product_images as $index => $img): 
                                    if (!empty($img['image_path'])): 
                                ?>
                                    <img src="../../assets/uploads/products/<?php echo $img['image_path']; ?>" 
                                         class="img-thumbnail <?php echo $index === 0 ? 'thumbnail-active' : ''; ?>" 
                                         style="width: 80px; height: 80px; cursor: pointer; object-fit: cover;"
                                         onclick="changeMainImage(this.src); setActiveThumbnail(this)"
                                         alt="<?php echo htmlspecialchars($img['alt_text'] ?? 'Product Image ' . ($index + 1)); ?>">
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Seller Info -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-shop me-2"></i> Seller Information</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="bi bi-person-circle text-success" style="font-size: 2.5rem;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1"><?php echo htmlspecialchars($product_data['business_name'] ?? 'Seller'); ?></h6>
                            <p class="text-muted small mb-2">
                                <?php echo htmlspecialchars($seller_info['business_description'] ?? 'Verified seller on Green Agric LTD'); ?>
                            </p>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success me-2">
                                    <i class="bi bi-star-fill me-1"></i>
                                    <?php echo number_format($seller_info['avg_rating'] ?? 0, 1); ?>
                                </span>
                                <span class="text-muted small">
                                    <?php echo number_format($seller_info['total_sales'] ?? 0); ?> sales
                                </span>
                            </div>
                            <?php if (!empty($seller_info['website_url'])): ?>
                                <div class="mt-2">
                                    <a href="<?php echo htmlspecialchars($seller_info['website_url']); ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-globe me-1"></i> Visit Website
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Pricing -->
            <?php if (!empty($bulk_pricing)): ?>
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-percent me-2"></i> Bulk Pricing</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Quantity Range</th>
                                    <th>Price per Unit</th>
                                    <th>Discount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bulk_pricing as $bulk): ?>
                                    <tr>
                                        <td>
                                            <?php echo number_format($bulk['min_quantity'], 2); ?> 
                                            <?php if (!empty($bulk['max_quantity'])): ?>
                                                - <?php echo number_format($bulk['max_quantity'], 2); ?>
                                            <?php else: ?>
                                                +
                                            <?php endif; ?>
                                            <?php echo $product_data['unit']; ?>
                                        </td>
                                        <td class="text-success fw-bold">
                                            <?php echo formatCurrency($bulk['price_per_unit']); ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($bulk['discount_percentage'])): ?>
                                                <span class="badge bg-success">
                                                    Save <?php echo $bulk['discount_percentage']; ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Info -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h1 class="product-title mb-2"><?php echo htmlspecialchars($product_data['name']); ?></h1>
                    
                    <!-- Product Badges -->
                    <div class="product-badges mb-3">
                        <span class="badge bg-success">Sold by: <?php echo htmlspecialchars($product_data['business_name']); ?></span>
                        <?php if (!empty($product_data['variety'])): ?>
                            <span class="badge bg-info ms-2"><?php echo htmlspecialchars($product_data['variety']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($product_data['is_organic']) && $product_data['is_organic']): ?>
                            <span class="badge bg-success ms-2">
                                <i class="bi bi-check-circle me-1"></i> Organic
                                <?php if (!empty($product_data['organic_certification_number'])): ?>
                                    (Certified)
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($product_data['is_gmo']) && $product_data['is_gmo']): ?>
                            <span class="badge bg-warning ms-2">GMO</span>
                        <?php endif; ?>
                        <?php if (!empty($product_data['grade'])): ?>
                            <span class="badge bg-primary ms-2">Grade <?php echo htmlspecialchars($product_data['grade']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($product_data['product_type'])): ?>
                            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars(ucfirst($product_data['product_type'])); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Price & Stock -->
                    <div class="product-price-section mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <h2 class="text-success mb-0 me-3" id="unitPriceDisplay"><?php echo formatCurrency($product_data['price_per_unit']); ?></h2>
                            <small class="text-muted">per <?php echo $product_data['unit']; ?></small>
                            <?php if (!empty($product_data['unit_quantity']) && $product_data['unit_quantity'] != 1): ?>
                                <small class="text-muted ms-2">(<?php echo number_format($product_data['unit_quantity'], 2); ?> <?php echo $product_data['unit']; ?> per unit)</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stock-status mb-3">
                            <?php if ($product_data['stock_quantity'] > 0): ?>
                                <span class="text-success">
                                    <i class="bi bi-check-circle-fill"></i> 
                                    <strong>In Stock:</strong> <?php echo number_format($product_data['stock_quantity'], 2); ?> <?php echo $product_data['unit']; ?> available
                                </span>
                                <?php if ($product_data['stock_quantity'] <= ($product_data['low_stock_alert_level'] ?? 10)): ?>
                                    <div class="alert alert-warning mt-2 py-2 small">
                                        <i class="bi bi-exclamation-triangle"></i> Low stock - Order soon!
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-danger">
                                    <i class="bi bi-x-circle-fill"></i> 
                                    <strong>Out of Stock</strong>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Product Description -->
                    <div class="product-description mb-4">
                        <?php if (!empty($product_data['short_description'])): ?>
                            <p class="lead text-muted mb-3"><?php echo htmlspecialchars($product_data['short_description']); ?></p>
                        <?php endif; ?>
                        
                        <h5>Description</h5>
                        <div class="description-content">
                            <?php echo nl2br(htmlspecialchars($product_data['description'])); ?>
                        </div>
                    </div>
                    
                    <!-- Agricultural Details -->
                    <div class="agricultural-details mb-4">
                        <h5>Product Specifications</h5>
                        <div class="row">
                            <?php if (!empty($product_data['grade'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-award text-success me-2"></i> Grade:</strong>
                                        <div><?php echo htmlspecialchars($product_data['grade']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['harvest_date'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-calendar-check text-success me-2"></i> Harvest Date:</strong>
                                        <div><?php echo formatDate($product_data['harvest_date']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['expiry_date'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-calendar-x text-success me-2"></i> Expiry Date:</strong>
                                        <div><?php echo formatDate($product_data['expiry_date']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['shelf_life_days'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-clock-history text-success me-2"></i> Shelf Life:</strong>
                                        <div><?php echo $product_data['shelf_life_days']; ?> days</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['farming_method'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-tree text-success me-2"></i> Farming Method:</strong>
                                        <div><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $product_data['farming_method']))); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['irrigation_type'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-droplet text-success me-2"></i> Irrigation Type:</strong>
                                        <div><?php echo htmlspecialchars($product_data['irrigation_type']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['weight_kg'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-speedometer2 text-success me-2"></i> Weight:</strong>
                                        <div><?php echo number_format($product_data['weight_kg'], 2); ?> kg</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['storage_temperature'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-thermometer-half text-success me-2"></i> Storage Temperature:</strong>
                                        <div><?php echo htmlspecialchars($product_data['storage_temperature']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['storage_humidity'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-moisture text-success me-2"></i> Storage Humidity:</strong>
                                        <div><?php echo htmlspecialchars($product_data['storage_humidity']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product_data['dimensions'])): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="detail-item">
                                        <strong><i class="bi bi-aspect-ratio text-success me-2"></i> Dimensions:</strong>
                                        <div><?php echo htmlspecialchars($product_data['dimensions']); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Organic Certification -->
                        <?php if (!empty($product_data['is_organic']) && $product_data['is_organic'] && !empty($product_data['organic_certification_number'])): ?>
                            <div class="alert alert-success mt-3">
                                <h6><i class="bi bi-shield-check me-2"></i> Organic Certification</h6>
                                <p class="mb-0">Certification Number: <?php echo htmlspecialchars($product_data['organic_certification_number']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add to Cart Section -->
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="add-to-cart-section">
                                <input type="hidden" id="productId" value="<?php echo $product_id; ?>">
                                <input type="hidden" id="productName" value="<?php echo htmlspecialchars($product_data['name']); ?>">
                                <input type="hidden" id="productPrice" value="<?php echo $product_data['price_per_unit']; ?>">
                                <input type="hidden" id="productUnit" value="<?php echo $product_data['unit']; ?>">
                                <input type="hidden" id="maxQuantity" value="<?php echo $product_data['stock_quantity']; ?>">
                                <input type="hidden" id="minOrderQuantity" value="<?php echo $product_data['min_order_quantity'] ?? 1; ?>">
                                <input type="hidden" id="bulkPricing" value='<?php echo json_encode($bulk_pricing); ?>'>
                                
                                <div class="row align-items-center mb-4">
                                    <div class="col-md-6">
                                        <label for="quantity" class="form-label fw-semibold">Quantity (<?php echo $product_data['unit']; ?>)</label>
                                        <div class="quantity-selector d-flex align-items-center">
                                            <button type="button" class="btn btn-outline-success quantity-btn" onclick="updateQuantity(-1)">
                                                <i class="bi bi-dash-lg"></i>
                                            </button>
                                            <input type="number" class="form-control text-center mx-2" 
                                                   id="quantity" 
                                                   value="<?php echo max(($product_data['min_order_quantity'] ?? 1), 1); ?>" 
                                                   min="<?php echo $product_data['min_order_quantity'] ?? 1; ?>" 
                                                   max="<?php echo $product_data['stock_quantity']; ?>"
                                                   style="width: 80px;">
                                            <button type="button" class="btn btn-outline-success quantity-btn" onclick="updateQuantity(1)">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted mt-2 d-block">
                                            Min order: <?php echo $product_data['min_order_quantity'] ?? 1; ?> <?php echo $product_data['unit']; ?>
                                            <?php if (!empty($product_data['max_order_quantity'])): ?>
                                                • Max order: <?php echo $product_data['max_order_quantity']; ?> <?php echo $product_data['unit']; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-end">
                                            <div class="total-price">
                                                <small class="text-muted d-block">Total Price</small>
                                                <h3 class="text-success mb-0" id="totalPrice">
                                                    <?php echo formatCurrency($product_data['price_per_unit'] * max(($product_data['min_order_quantity'] ?? 1), 1)); ?>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="action-buttons d-grid gap-2">
                                    <?php if ($product_data['stock_quantity'] > 0): ?>
                                        <button type="button" class="btn btn-success btn-lg py-3" onclick="addToCart()">
                                            <i class="bi bi-cart-plus me-2"></i> Add to Cart
                                        </button>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-success flex-fill" 
                                                    id="wishlistBtn" 
                                                    onclick="toggleWishlist()">
                                                <i class="bi <?php echo $in_wishlist ? 'bi-heart-fill' : 'bi-heart'; ?> me-2"></i> 
                                                <?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary flex-fill" onclick="buyNow()">
                                                <i class="bi bi-lightning me-2"></i> Buy Now
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-lg py-3" disabled>
                                            <i class="bi bi-x-circle me-2"></i> Out of Stock
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="notifyWhenAvailable()">
                                            <i class="bi bi-bell me-2"></i> Notify When Available
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Specifications -->
            <?php if (!empty($specifications)): ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2"></i> Additional Specifications</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($specifications as $spec): ?>
                            <div class="col-md-6 mb-2">
                                <strong><?php echo htmlspecialchars($spec['attribute_name']); ?>:</strong>
                                <span class="text-muted"><?php echo htmlspecialchars($spec['attribute_value']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="related-products mt-5">
            <h3 class="mb-4">Related Products</h3>
            <div class="row g-4">
                <?php foreach ($related_products as $related): ?>
                    <div class="col-md-3">
                        <div class="card product-card h-100">
                            <a href="product-details.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                <img src="<?php echo !empty($related['image_path']) ? '../../assets/uploads/products/' . $related['image_path'] : '../../assets/images/placeholder-product.jpg'; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($related['name']); ?>" style="height: 180px; object-fit: cover;">
                                <div class="card-body">
                                    <h6 class="card-title text-dark mb-1"><?php echo htmlspecialchars($related['name']); ?></h6>
                                    <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($related['business_name']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="text-success mb-0"><?php echo formatCurrency($related['price_per_unit']); ?></h5>
                                        <small class="text-muted">/<?php echo $related['unit']; ?></small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Cart Manager - Consistent with browse.php
const cartManager = window.cartManager || {
    addToCart: function(productId, productName, productPrice, productUnit, quantity = 1) {
        const cartKey = 'greenagric_cart';
        let cart = JSON.parse(localStorage.getItem(cartKey) || '[]');
        
        // Check if product already in cart
        const existingIndex = cart.findIndex(item => item.productId == productId);
        
        if (existingIndex >= 0) {
            // Update quantity
            cart[existingIndex].quantity += quantity;
        } else {
            // Add new item
            cart.push({
                productId: productId,
                productName: productName,
                productPrice: parseFloat(productPrice),
                productUnit: productUnit,
                quantity: quantity
            });
        }
        
        // Save to localStorage
        localStorage.setItem(cartKey, JSON.stringify(cart));
        
        // Update cart count
        updateCartCount();
        
        return true;
    },
    
    updateCartItem: function(productId, quantity) {
        const cartKey = 'greenagric_cart';
        let cart = JSON.parse(localStorage.getItem(cartKey) || '[]');
        
        const existingIndex = cart.findIndex(item => item.productId == productId);
        
        if (existingIndex >= 0) {
            if (quantity <= 0) {
                // Remove item if quantity is 0 or less
                cart.splice(existingIndex, 1);
            } else {
                // Update quantity
                cart[existingIndex].quantity = quantity;
            }
            
            // Save to localStorage
            localStorage.setItem(cartKey, JSON.stringify(cart));
            updateCartCount();
            return true;
        }
        
        return false;
    },
    
    getCartItem: function(productId) {
        const cartKey = 'greenagric_cart';
        let cart = JSON.parse(localStorage.getItem(cartKey) || '[]');
        return cart.find(item => item.productId == productId);
    }
};

// Wishlist functionality
async function toggleWishlist() {
    const productId = document.getElementById('productId').value;
    const wishlistBtn = document.getElementById('wishlistBtn');
    const heartIcon = wishlistBtn.querySelector('i');
    const isCurrentlyInWishlist = heartIcon.classList.contains('bi-heart-fill');
    
    try {
        const response = await fetch('../../api/wishlist/toggle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (isCurrentlyInWishlist) {
                // Remove from wishlist
                heartIcon.classList.remove('bi-heart-fill');
                heartIcon.classList.add('bi-heart');
                wishlistBtn.innerHTML = '<i class="bi bi-heart me-2"></i> Add to Wishlist';
                showToast('Removed from wishlist', 'info');
            } else {
                // Add to wishlist
                heartIcon.classList.remove('bi-heart');
                heartIcon.classList.add('bi-heart-fill');
                wishlistBtn.innerHTML = '<i class="bi bi-heart-fill me-2"></i> Remove from Wishlist';
                showToast('Added to wishlist!', 'success');
            }
        } else {
            showToast(data.error || 'Operation failed', 'error');
        }
    } catch (error) {
        console.error('Error toggling wishlist:', error);
        showToast('Network error. Please try again.', 'error');
    }
}

function changeMainImage(src) {
    document.getElementById('mainImage').src = src;
}

function setActiveThumbnail(thumb) {
    document.querySelectorAll('.img-thumbnail').forEach(t => {
        t.classList.remove('thumbnail-active');
    });
    thumb.classList.add('thumbnail-active');
}

function updateQuantity(change) {
    const input = document.getElementById('quantity');
    const max = parseInt(document.getElementById('maxQuantity').value) || 999;
    const min = parseInt(document.getElementById('minOrderQuantity').value) || 1;
    let value = parseInt(input.value) + change;
    
    if (value < min) value = min;
    if (value > max) value = max;
    
    input.value = value;
    updateTotalPrice();
}

function updateTotalPrice() {
    const quantity = parseInt(document.getElementById('quantity').value);
    const price = parseFloat(document.getElementById('productPrice').value);
    const total = quantity * price;
    
    document.getElementById('totalPrice').textContent = '₦' + total.toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    
    // Check bulk pricing
    const bulkPricing = JSON.parse(document.getElementById('bulkPricing').value || '[]');
    if (bulkPricing.length > 0) {
        let applicablePrice = price;
        let bulkDiscount = null;
        
        for (const bulk of bulkPricing) {
            if (quantity >= bulk.min_quantity && (!bulk.max_quantity || quantity <= bulk.max_quantity)) {
                if (bulk.price_per_unit < applicablePrice) {
                    applicablePrice = bulk.price_per_unit;
                    if (bulk.discount_percentage) {
                        bulkDiscount = bulk.discount_percentage;
                    }
                }
            }
        }
        
        // Update unit price display if different
        if (applicablePrice !== price) {
            document.getElementById('unitPriceDisplay').innerHTML = 
                `<span class="text-decoration-line-through text-muted me-2">₦${price.toLocaleString('en-NG')}</span>
                 <span class="text-success">₦${applicablePrice.toLocaleString('en-NG')}</span>`;
            
            // Update total with bulk price
            const bulkTotal = quantity * applicablePrice;
            document.getElementById('totalPrice').innerHTML = 
                `<span class="text-decoration-line-through text-muted d-block" style="font-size: 0.8rem;">₦${total.toLocaleString('en-NG')}</span>
                 <span class="text-success">₦${bulkTotal.toLocaleString('en-NG')}</span>`;
        } else {
            document.getElementById('unitPriceDisplay').textContent = '₦' + price.toLocaleString('en-NG');
        }
    }
}

function addToCart() {
    const productId = document.getElementById('productId').value;
    const productName = document.getElementById('productName').value;
    const productPrice = parseFloat(document.getElementById('productPrice').value);
    const productUnit = document.getElementById('productUnit').value;
    const quantity = parseInt(document.getElementById('quantity').value);
    const minQuantity = parseInt(document.getElementById('minOrderQuantity').value) || 1;
    
    // Validate min order quantity
    if (quantity < minQuantity) {
        showToast(`Minimum order quantity is ${minQuantity} ${productUnit}`, 'warning');
        document.getElementById('quantity').value = minQuantity;
        updateTotalPrice();
        return;
    }
    
    // Add to cart using cart manager
    cartManager.addToCart(productId, productName, productPrice, productUnit, quantity);
    
    // Show success message
    showToast(`${quantity} ${productUnit} of ${productName} added to cart!`, 'success');
}

function buyNow() {
    addToCart();
    // Redirect to checkout
    setTimeout(() => {
        window.location.href = '../../buyer/cart/checkout.php';
    }, 500);
}

function notifyWhenAvailable() {
    const productId = document.getElementById('productId').value;
    
    if (confirm('We will notify you by email when this product is back in stock. Continue?')) {
        fetch('../../api/products/notify.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('We\'ll notify you when this product is available!', 'success');
            } else {
                showToast(data.error || 'Failed to set notification', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error', 'error');
        });
    }
}

// Toast notification function (consistent with browse.php)
function showToast(message, type = 'info') {
    // Check if agriApp exists
    if (typeof agriApp !== 'undefined' && agriApp.showToast) {
        agriApp.showToast(message, type);
    } else {
        // Fallback toast implementation
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#198754' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#0dcaf0'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        `;
        
        toast.innerHTML = `
            <div class="toast-content d-flex align-items-center">
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                <span>${message}</span>
                <button class="btn-close btn-close-white ms-3" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => toast.style.transform = 'translateX(0)', 100);
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            toast.style.transform = 'translateX(400px)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}

// Update cart count
function updateCartCount() {
    if (typeof cartManager !== 'undefined' && cartManager.updateCartCount) {
        cartManager.updateCartCount();
    } else {
        const cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
        const cartCount = document.getElementById('cart-count');
        if (cartCount) {
            cartCount.textContent = cart.length;
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTotalPrice();
    
    // Add event listener for quantity input
    const quantityInput = document.getElementById('quantity');
    if (quantityInput) {
        quantityInput.addEventListener('input', updateTotalPrice);
        quantityInput.addEventListener('change', function() {
            const min = parseInt(this.min) || 1;
            const max = parseInt(this.max) || 999;
            let value = parseInt(this.value) || min;
            
            if (value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
            updateTotalPrice();
        });
    }
    
    // Set first thumbnail as active
    const firstThumbnail = document.querySelector('.img-thumbnail.thumbnail-active');
    if (firstThumbnail) {
        firstThumbnail.classList.add('thumbnail-active');
    }
    
    // Initialize cart count
    updateCartCount();
    
    // Check if product is already in cart and update quantity
    const productId = document.getElementById('productId').value;
    const existingCartItem = cartManager.getCartItem(productId);
    if (existingCartItem) {
        const quantityInput = document.getElementById('quantity');
        const totalQuantity = existingCartItem.quantity;
        
        // Update quantity input to show total in cart + minimum
        const minQuantity = parseInt(document.getElementById('minOrderQuantity').value) || 1;
        quantityInput.value = Math.max(minQuantity, totalQuantity + minQuantity);
        updateTotalPrice();
    }
});
</script>

<style>
/* CSS remains the same as previous version */
.product-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
}

.product-badges .badge {
    font-size: 0.8rem;
    padding: 0.4rem 0.8rem;
}

.quantity-selector {
    max-width: 200px;
}

.quantity-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.total-price h3 {
    font-weight: 700;
}

.action-buttons .btn {
    padding: 0.75rem;
}

.thumbnail-active {
    border: 2px solid #198754 !important;
}

.detail-item {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.5rem;
    border-left: 3px solid #198754;
}

.product-card {
    transition: transform 0.3s, box-shadow 0.3s;
    border: 1px solid #dee2e6;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.description-content {
    line-height: 1.8;
    color: #495057;
}

@media (max-width: 768px) {
    .product-title {
        font-size: 1.5rem;
    }
    
    .action-buttons .btn {
        font-size: 0.9rem;
        padding: 0.6rem;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>