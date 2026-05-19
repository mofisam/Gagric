<?php
require_once 'classes/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

$db = new Database();

// Get featured products
$featured_products = $db->fetchAll("
    SELECT p.*, sp.business_name, c.name as category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as image_path
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'approved' AND p.is_featured = TRUE AND sp.is_approved = TRUE 
    ORDER BY p.created_at DESC 
    LIMIT 8
");

// Get best selling products (by order count)
$best_selling = $db->fetchAll("
    SELECT p.*, sp.business_name, COUNT(oi.id) as sales_count,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as image_path
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    LEFT JOIN order_items oi ON p.id = oi.product_id 
    WHERE p.status = 'approved' AND sp.is_approved = TRUE 
    GROUP BY p.id 
    ORDER BY sales_count DESC 
    LIMIT 8
");

// Get new arrivals
$new_arrivals = $db->fetchAll("
    SELECT p.*, sp.business_name, c.name as category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as image_path
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'approved' AND sp.is_approved = TRUE 
    ORDER BY p.created_at DESC 
    LIMIT 8
");

// Get popular categories with images
$categories = $db->fetchAll("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'approved'
    WHERE c.is_active = TRUE AND c.parent_id IS NULL 
    GROUP BY c.id 
    ORDER BY product_count DESC, c.name 
    LIMIT 6
");

// Get organic products
$organic_products = $db->fetchAll("
    SELECT p.*, sp.business_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as image_path
    FROM products p 
    JOIN seller_profiles sp ON p.seller_id = sp.user_id 
    JOIN product_agricultural_details pad ON p.id = pad.product_id 
    WHERE p.status = 'approved' AND sp.is_approved = TRUE 
    AND pad.is_organic = TRUE 
    ORDER BY p.created_at DESC 
    LIMIT 4
");
?>
<?php 
$page_title = "Green Agric LTD - Fresh Agricultural Products Online Store";
$page_css = 'style.css';
include 'includes/header.php'; 
?>

<!-- Hero Slider/Carousel -->
<section class="hero-section">
    <div class="container-fluid px-0">
        <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <div class="hero-slide bg-success text-white" style="background: linear-gradient(rgba(25, 135, 84, 0.5), rgba(25, 135, 84, 0.5)), url('assets/images/hero-bg-1.jpg');">
                        <div class="container">
                            <div class="row align-items-center min-vh-70">
                                <div class="col-lg-6">
                                    <h1 class="display-4 fw-bold mb-4">Fresh Farm Produce Delivered to Your Doorstep</h1>
                                    <p class="lead mb-4">Get farm-fresh vegetables, fruits, grains, and livestock products directly from Nigerian farmers.</p>
                                    <a href="buyer/products/browse.php" class="btn btn-light btn-lg px-5 py-3">
                                        <i class="bi bi-cart3 me-2"></i> Shop Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="hero-slide bg-warning text-white" style="background: linear-gradient(rgba(25, 135, 84, 0.5), rgba(25, 135, 84, 0.5)), url('assets/images/hero-bg-2.jpg');">
                        <div class="container">
                            <div class="row align-items-center min-vh-70">
                                <div class="col-lg-6">
                                    <h1 class="display-4 fw-bold mb-4">Organic & Quality Certified Products</h1>
                                    <p class="lead mb-4">Premium organic produce with quality certification. Healthy eating made easy and affordable.</p>
                                    <a href="buyer/products/browse.php?organic=1" class="btn btn-light btn-lg px-5 py-3">
                                        <i class="bi bi-shield-check me-2"></i> Shop Organic
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-item">
                    <div class="hero-slide bg-info text-white" style="background: linear-gradient(rgba(25, 135, 84, 0.5), rgba(25, 135, 84, 0.5)), url('assets/images/hero-bg-3.jpg');">
                        <div class="container">
                            <div class="row align-items-center min-vh-70">
                                <div class="col-lg-6">
                                    <h1 class="display-4 fw-bold mb-4">Become a Seller & Grow Your Business</h1>
                                    <p class="lead mb-4">Join thousands of farmers selling directly to customers nationwide. No middlemen, better profits.</p>
                                    <a href="auth/register.php?role=seller" class="btn btn-light btn-lg px-5 py-3">
                                        <i class="bi bi-person-plus me-2"></i> Start Selling
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</section>

<!-- Features Bar -->
<section class="features-bar py-4 bg-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="d-flex align-items-center">
                    <div class="feature-icon me-3">
                        <i class="bi bi-truck text-success fs-3"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Nationwide Shipping</h6>
                        <p class="text-muted mb-0">Delivery across Nigeria</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex align-items-center">
                    <div class="feature-icon me-3">
                        <i class="bi bi-arrow-clockwise text-success fs-3"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Easy Returns</h6>
                        <p class="text-muted mb-0">Quality guarantee</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex align-items-center">
                    <div class="feature-icon me-3">
                        <i class="bi bi-shield-check text-success fs-3"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Quality Guarantee</h6>
                        <p class="text-muted mb-0">Admin verified products</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex align-items-center">
                    <div class="feature-icon me-3">
                        <i class="bi bi-headset text-success fs-3"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">24/7 Support</h6>
                        <p class="text-muted mb-0">Customer service</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="categories-section py-5">
    <div class="container">
        <div class="section-header mb-5">
            <h2 class="section-title">Shop by Category</h2>
            <p class="section-subtitle">Browse our wide range of agricultural products</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($categories as $category): 
                $category_icons = [
                    'Grains & Cereals' => 'bi-basket',
                    'Vegetables' => 'bi-flower1',
                    'Fruits' => 'bi-apple',
                    'Livestock' => 'bi-egg',
                    'Dairy' => 'bi-cup',
                    'Equipment' => 'bi-tools'
                ];
                $icon = $category_icons[$category['name']] ?? 'bi-basket';
            ?>
                <div class="col-md-4 col-6 col-lg-2">
                    <a href="buyer/products/browse.php?category=<?php echo $category['id']; ?>" class="category-card text-decoration-none">
                        <div class="card border-0 shadow-sm h-100 text-center hover-lift">
                            <div class="card-body p-4">
                                <div class="category-icon mb-3">
                                    <i class="bi <?php echo $icon; ?> text-success" style="font-size: 2.5rem;"></i>
                                </div>
                                <h6 class="category-name mb-2"><?php echo htmlspecialchars($category['name']); ?></h6>
                                <span class="badge bg-success rounded-pill"><?php echo $category['product_count']; ?> items</span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <a href="buyer/products/categories.php" class="btn btn-outline-success btn-lg px-5">
                View All Categories <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="featured-products py-5 bg-light">
    <div class="container">
        <div class="section-header mb-5">
            <h2 class="section-title">Featured Products</h2>
            <p class="section-subtitle">Handpicked quality products from our best sellers</p>
        </div>
        
        <div class="row g-4">
            <?php if (!empty($featured_products)): ?>
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card product-card h-100 border-0 shadow-sm">
                            <div class="position-relative">
                                <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo !empty($product['image_path']) ? BASE_URL . '/assets/uploads/products/' . $product['image_path'] : BASE_URL . '/assets/images/placeholder-product.jpg'; ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                                </a>
                                <div class="product-badges position-absolute top-0 start-0 p-3">
                                    <?php if ($product['stock_quantity'] <= 10 && $product['stock_quantity'] > 0): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php elseif ($product['stock_quantity'] <= 0): ?>
                                        <span class="badge bg-danger">Sold Out</span>
                                    <?php endif; ?>
                                    <?php if ($product['is_featured']): ?>
                                        <span class="badge bg-info">Featured</span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-light btn-sm wishlist-btn position-absolute top-0 end-0 m-3" 
                                        onclick="addToWishlist(this, <?php echo $product['id']; ?>)"
                                        data-product-id="<?php echo $product['id']; ?>">
                                    <i class="bi bi-heart"></i>
                                </button>>
                            </div>
                            <div class="card-body">
                                <div class="product-category mb-2">
                                    <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                </div>
                                <h6 class="product-title mb-2">
                                    <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>" class="text-dark text-decoration-none">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h6>
                                <div class="product-seller mb-2">
                                    <small class="text-muted">By <?php echo htmlspecialchars($product['business_name']); ?></small>
                                </div>
                                <div class="product-price mb-3">
                                    <h5 class="text-success mb-0">
                                        <?php echo formatCurrency($product['price_per_unit']); ?>
                                        <small class="text-muted"> per <?php echo $product['unit']; ?></small>
                                    </h5>
                                </div>
                                <div class="d-grid">
                                    <button class="btn btn-success add-to-cart-btn" 
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            data-product-price="<?php echo $product['price_per_unit']; ?>"
                                            data-product-unit="<?php echo $product['unit']; ?>"
                                            data-stock="<?php echo $product['stock_quantity']; ?>">
                                        <i class="bi bi-cart-plus me-2"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-basket text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No featured products available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Organic Products Section -->
<?php if (!empty($organic_products)): ?>
<section class="organic-products py-5">
    <div class="container">
        <div class="section-header mb-5">
            <h2 class="section-title">Organic Products</h2>
            <p class="section-subtitle">Certified organic produce for healthy living</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($organic_products as $product): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card product-card h-100 border-0 shadow-sm">
                        <div class="position-relative">
                            <span class="badge bg-success position-absolute top-0 start-0 m-3">Organic</span>
                            <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>">
                                <img src="<?php echo !empty($product['image_path']) ? BASE_URL . '/assets/uploads/products/' . $product['image_path'] : BASE_URL . '/assets/images/placeholder-product.jpg'; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                            </a>
                        </div>
                        <div class="card-body">
                            <h6 class="product-title mb-2">
                                <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>" class="text-dark text-decoration-none">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h6>
                            <div class="product-price mb-3">
                                <h5 class="text-success mb-0"><?php echo formatCurrency($product['price_per_unit']); ?></h5>
                                <small class="text-muted">per <?php echo $product['unit']; ?></small>
                            </div>
                            <div class="d-grid">
                                <button class="btn btn-outline-success add-to-cart-btn" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                        data-product-price="<?php echo $product['price_per_unit']; ?>"
                                        data-product-unit="<?php echo $product['unit']; ?>"
                                        data-stock="<?php echo $product['stock_quantity']; ?>">
                                    <i class="bi bi-cart-plus me-2"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <a href="buyer/products/browse.php?organic=1" class="btn btn-success btn-lg px-5">
                View All Organic Products <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Best Selling Products -->
<section class="best-selling py-5 bg-light">
    <div class="container">
        <div class="section-header mb-5">
            <h2 class="section-title">Best Selling Products</h2>
            <p class="section-subtitle">Most popular items our customers love</p>
        </div>
        
        <div class="row g-4">
            <?php if (!empty($best_selling)): ?>
                <?php foreach ($best_selling as $product): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card product-card h-100 border-0 shadow-sm">
                            <div class="position-relative">
                                <span class="badge bg-danger position-absolute top-0 start-0 m-3">Bestseller</span>
                                <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo !empty($product['image_path']) ? BASE_URL . '/assets/uploads/products/' . $product['image_path'] : BASE_URL . '/assets/images/placeholder-product.jpg'; ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                                </a>
                            </div>
                            <div class="card-body">
                                <h6 class="product-title mb-2">
                                    <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>" class="text-dark text-decoration-none">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h6>
                                <div class="product-price mb-3">
                                    <h5 class="text-success mb-0"><?php echo formatCurrency($product['price_per_unit']); ?></h5>
                                    <small class="text-muted">per <?php echo $product['unit']; ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-warning">
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-half"></i>
                                        <small class="text-muted ms-1">(<?php echo $product['sales_count']; ?>)</small>
                                    </span>
                                    <button class="btn btn-sm btn-outline-success add-to-cart-btn" 
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            data-product-price="<?php echo $product['price_per_unit']; ?>"
                                            data-product-unit="<?php echo $product['unit']; ?>"
                                            data-stock="<?php echo $product['stock_quantity']; ?>">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- New Arrivals -->
<section class="new-arrivals py-5">
    <div class="container">
        <div class="section-header mb-5">
            <h2 class="section-title">New Arrivals</h2>
            <p class="section-subtitle">Freshly added products to our marketplace</p>
        </div>
        
        <div class="row g-4">
            <?php if (!empty($new_arrivals)): ?>
                <?php foreach ($new_arrivals as $product): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card product-card h-100 border-0 shadow-sm">
                            <div class="position-relative">
                                <span class="badge bg-info position-absolute top-0 start-0 m-3">New</span>
                                <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo !empty($product['image_path']) ? BASE_URL . '/assets/uploads/products/' . $product['image_path'] : BASE_URL . '/assets/images/placeholder-product.jpg'; ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                                </a>
                            </div>
                            <div class="card-body">
                                <h6 class="product-title mb-2">
                                    <a href="buyer/products/details.php?id=<?php echo $product['id']; ?>" class="text-dark text-decoration-none">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h6>
                                <div class="product-price mb-3">
                                    <h5 class="text-success mb-0"><?php echo formatCurrency($product['price_per_unit']); ?></h5>
                                    <small class="text-muted">per <?php echo $product['unit']; ?></small>
                                </div>
                                <div class="d-grid">
                                    <button class="btn btn-outline-success add-to-cart-btn" 
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            data-product-price="<?php echo $product['price_per_unit']; ?>"
                                            data-product-unit="<?php echo $product['unit']; ?>"
                                            data-stock="<?php echo $product['stock_quantity']; ?>">
                                        <i class="bi bi-cart-plus me-2"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section py-5 bg-success text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="display-5 fw-bold mb-3">Ready to Sell Your Products?</h2>
                <p class="lead mb-4">Join Green Agric LTD and reach thousands of customers across Nigeria. No middlemen, higher profits.</p>
                <a href="auth/register.php?role=seller" class="btn btn-light btn-lg px-5 py-3">
                    <i class="bi bi-shop me-2"></i> Become a Seller
                </a>
            </div>
            <div class="col-lg-6">
                <div class="bg-white rounded-3 p-5 text-dark">
                    <h3 class="mb-4">Why Sell on Green Agric LTD?</h3>
                    <ul class="list-unstyled">
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Zero listing fees</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Nationwide customer base</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Secure payment system</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Logistics support</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Marketing & promotion</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter -->
<section class="newsletter py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="mb-3">Stay Updated</h2>
                <p class="text-muted mb-4">Subscribe to our newsletter for updates on new products, seasonal offers, and farming tips.</p>
                <form class="newsletter-form">
                    <div class="input-group input-group-lg mb-3">
                        <input type="email" class="form-control" placeholder="Enter your email address" required>
                        <button class="btn btn-success" type="submit">Subscribe</button>
                    </div>
                    <small class="text-muted">We respect your privacy. Unsubscribe at any time.</small>
                </form>
            </div>
        </div>
    </div>
</section>

<style>
/* Hero Section */
.hero-section .carousel-item {
    height: 70vh;
    min-height: 500px;
}

.hero-slide {
    height: 100%;
    display: flex;
    align-items: center;
    background-size: cover !important;
    background-position: center !important;
}

/* Sections */
.section-header {
    text-align: center;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #2c3e50;
}

.section-subtitle {
    color: #6c757d;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Cards */
.product-card {
    transition: all 0.3s ease;
    border-radius: 10px;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
}

.hover-lift:hover {
    transform: translateY(-5px);
    transition: transform 0.3s ease;
}

.category-card {
    color: inherit;
}

.category-card:hover {
    color: inherit;
}

.category-icon {
    transition: transform 0.3s ease;
}

.category-card:hover .category-icon {
    transform: scale(1.1);
}

/* Product Badges */
.product-badges .badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Wishlist Button */
.wishlist-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.wishlist-btn:hover {
    background-color: #ff6b6b !important;
    color: white !important;
}

/* Add to Cart Button */
.add-to-cart-btn {
    transition: all 0.3s ease;
}

.add-to-cart-btn:hover {
    transform: scale(1.05);
}

/* Responsive */
@media (max-width: 768px) {
    .hero-section .carousel-item {
        height: 60vh;
        min-height: 400px;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .features-bar .col-md-3 {
        margin-bottom: 1rem;
    }
}

@media (max-width: 576px) {
    .hero-section .carousel-item {
        height: 50vh;
        min-height: 300px;
    }
    
    .display-4 {
        font-size: 2rem;
    }
    
    .lead {
        font-size: 1rem;
    }
}
</style>

<script>
// Cart functionality
document.addEventListener('DOMContentLoaded', function() {
    loadWishlistStatus();
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const productPrice = this.dataset.productPrice;
            const productUnit = this.dataset.productUnit;
            const stock = parseInt(this.dataset.stock);
            
            if (stock <= 0) {
                agriApp.showToast('This product is out of stock!', 'error');
                return;
            }
            
            addToCart(productId, productName, productPrice, productUnit);
        });
    });
    
    // Update cart count
    updateCartCount();
});

function addToCart(productId, productName, productPrice, productUnit) {
    // Get existing cart
    let cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
    
    // Check if product already in cart
    const existingIndex = cart.findIndex(item => item.productId == productId);
    
    if (existingIndex >= 0) {
        // Update quantity
        cart[existingIndex].quantity += 1;
    } else {
        // Add new item
        cart.push({
            productId: productId,
            productName: productName,
            productPrice: parseFloat(productPrice),
            productUnit: productUnit,
            quantity: 1
        });
    }
    
    // Save to localStorage
    localStorage.setItem('greenagric_cart', JSON.stringify(cart));
    
    // Show success message
    agriApp.showToast(`${productName} added to cart!`, 'success');
    
    // Update cart count
    updateCartCount();
}

// Replace the entire addToWishlist function in index.php with this:
function addToWishlist(button, productId) {
    <?php if (!isset($_SESSION['user_id'])): ?>
        agriApp.showToast('Please login to add to wishlist', 'warning');
        window.location.href = 'auth/login.php';
        return;
    <?php endif; ?>
    
    const isActive = button.classList.contains('active');
    
    // Use the correct toggle endpoint
    fetch('api/wishlist/toggle.php', {
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
            if (data.action === 'added') {
                button.classList.add('active');
                button.innerHTML = '<i class="bi bi-heart-fill"></i>';
                agriApp.showToast('Added to wishlist!', 'success');
            } else if (data.action === 'removed') {
                button.classList.remove('active');
                button.innerHTML = '<i class="bi bi-heart"></i>';
                agriApp.showToast('Removed from wishlist', 'info');
            }
        } else {
            agriApp.showToast(data.error || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        agriApp.showToast('Network error. Please try again.', 'error');
    });
}

// Load wishlist status for logged-in users
function loadWishlistStatus() {
    <?php if (!isset($_SESSION['user_id'])): ?>
        return; // Don't load for guests
    <?php endif; ?>
    
    try {
        fetch('api/wishlist/status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.wishlist) {
                    data.wishlist.forEach(productId => {
                        // Update all wishlist buttons for this product
                        const wishlistBtns = document.querySelectorAll(`.wishlist-btn[data-product-id="${productId}"]`);
                        wishlistBtns.forEach(btn => {
                            btn.classList.add('active');
                            btn.innerHTML = '<i class="bi bi-heart-fill"></i>';
                        });
                    });
                }
            })
            .catch(error => console.error('Error loading wishlist:', error));
    } catch (error) {
        console.error('Error loading wishlist:', error);
    }
}


function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
    }
}

// Newsletter subscription
document.querySelector('.newsletter-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const email = this.querySelector('input[type="email"]').value;
    
    // AJAX subscription
    fetch('api/newsletter/subscribe.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            agriApp.showToast('Thank you for subscribing!', 'success');
            this.reset();
        } else {
            agriApp.showToast(data.error || 'Subscription failed', 'error');
        }
    })
    .catch(error => {
        agriApp.showToast('Network error. Please try again.', 'error');
    });
});

// Auto-rotate carousel
const myCarousel = document.getElementById('heroCarousel');
if (myCarousel) {
    const carousel = new bootstrap.Carousel(myCarousel, {
        interval: 5000,
        wrap: true
    });
}
</script>

<?php include 'includes/footer.php'; ?>