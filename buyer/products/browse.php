<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Product.php';
require_once '../../config/constants.php';

$db = new Database();
$product = new Product($db);

// Get filters
$category_id = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, $_GET['page'] ?? 1);
$organic = isset($_GET['organic']) ? 1 : 0;
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build filters
$filters = [
    'category' => $category_id,
    'search' => $search,
    'organic' => $organic,
    'min_price' => $min_price,
    'max_price' => $max_price
];

// Get products with pagination directly
$result = $product->getPaginatedProducts($filters, $sort, $limit, $offset);
$products = $result['products'];
$total_products = $result['total'];
$total_pages = $result['total_pages'];

// Get categories
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = TRUE");

$page_title = "Browse Products";
$page_css = 'products.css';
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-lg-3 d-none d-lg-block">
            <?php include '../../includes/filters-sidebar.php'; ?>
        </div>
        
        <!-- Mobile Filters Toggle -->
        <div class="d-lg-none mb-3">
            <button class="btn btn-success w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtersOffcanvas">
                <i class="bi bi-filter me-2"></i> Filters & Categories
            </button>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Product Grid Header -->
            <div class="card mb-4">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h4 mb-0">Agricultural Products</h1>
                            <p class="text-muted small mb-0">
                                <?php echo $total_products; ?> product<?php echo $total_products != 1 ? 's' : ''; ?> found
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" style="width: auto;" id="sortSelect" onchange="updateSort(this.value)">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Filters -->
            <?php if (!empty($_GET)): ?>
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!empty($search)): ?>
                            <span class="badge bg-success d-flex align-items-center">
                                Search: "<?php echo htmlspecialchars($search); ?>"
                                <a href="<?php echo removeFilter('search'); ?>" class="text-white ms-2 text-decoration-none">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($category_id)): ?>
                            <?php 
                            $cat_name = '';
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $category_id) {
                                    $cat_name = $cat['name'];
                                    break;
                                }
                            }
                            ?>
                            <span class="badge bg-success d-flex align-items-center">
                                <?php echo htmlspecialchars($cat_name); ?>
                                <a href="<?php echo removeFilter('category'); ?>" class="text-white ms-2 text-decoration-none">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['organic'])): ?>
                            <span class="badge bg-success d-flex align-items-center">
                                Organic
                                <a href="<?php echo removeFilter('organic'); ?>" class="text-white ms-2 text-decoration-none">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($min_price)): ?>
                            <span class="badge bg-success d-flex align-items-center">
                                Min: ₦<?php echo number_format($min_price); ?>
                                <a href="<?php echo removeFilter('min_price'); ?>" class="text-white ms-2 text-decoration-none">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($max_price)): ?>
                            <span class="badge bg-success d-flex align-items-center">
                                Max: ₦<?php echo number_format($max_price); ?>
                                <a href="<?php echo removeFilter('max_price'); ?>" class="text-white ms-2 text-decoration-none">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($_GET)): ?>
                            <a href="?" class="badge bg-secondary d-flex align-items-center text-decoration-none">
                                Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Product Grid -->
            <?php if ($total_products > 0): ?>
                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3" id="productsGrid">
                    <?php foreach ($products as $prod): ?>
                        <div class="col">
                            <div class="card h-100 product-card border-0 shadow-sm">
                                <!-- Product Image -->
                                <div class="position-relative">
                                    <a href="product-details.php?id=<?php echo $prod['id']; ?>" class="text-decoration-none">
                                        <img src="<?php echo getProductImage($prod['id'], $prod['image_path'] ?? null); ?>" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($prod['name']); ?>" 
                                             style="height: 160px; object-fit: cover;" loading="lazy">
                                    </a>
                                    <!-- Stock Badge -->
                                    <?php if ($prod['stock_quantity'] <= 0): ?>
                                        <span class="badge bg-danger position-absolute top-0 start-0 m-2">Sold Out</span>
                                    <?php elseif ($prod['stock_quantity'] <= 10): ?>
                                        <span class="badge bg-warning position-absolute top-0 start-0 m-2">Low Stock</span>
                                    <?php endif; ?>
                                    
                                    <!-- Wishlist Button -->
                                    <?php if (isLoggedIn()): ?>
                                        <button class="btn btn-sm btn-light position-absolute top-0 end-0 m-2 p-1 wishlist-btn" 
                                                onclick="toggleWishlist(this, <?php echo $prod['id']; ?>)" 
                                                title="Add to Wishlist"
                                                style="width: 32px; height: 32px; border-radius: 50%;">
                                            <i class="bi bi-heart"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Product Info -->
                                <div class="card-body p-3">
                                    <a href="product-details.php?id=<?php echo $prod['id']; ?>" class="text-decoration-none text-dark">
                                        <h6 class="card-title mb-1 line-clamp-2" style="font-size: 0.9rem; min-height: 2.8rem;">
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                        </h6>
                                        <p class="card-text text-muted small mb-2">By <?php echo htmlspecialchars($prod['business_name']); ?></p>
                                    </a>
                                    
                                    <!-- Price -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="text-success mb-0" style="font-size: 1.1rem;">
                                            ₦<?php echo number_format($prod['price_per_unit']); ?>
                                        </h5>
                                        <small class="text-muted">/<?php echo $prod['unit']; ?></small>
                                    </div>
                                    
                                    <!-- Tags -->
                                    <div class="mb-2">
                                        <?php if (!empty($prod['is_organic']) && $prod['is_organic']): ?>
                                            <span class="badge bg-success me-1" style="font-size: 0.7rem;">Organic</span>
                                        <?php endif; ?>
                                        <?php if (!empty($prod['grade'])): ?>
                                            <span class="badge bg-info" style="font-size: 0.7rem;">Grade <?php echo $prod['grade']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Add to Cart Button -->
                                    <button class="btn btn-sm btn-success w-100 add-to-cart-btn" 
                                            data-product-id="<?php echo $prod['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($prod['name']); ?>"
                                            data-product-price="<?php echo $prod['price_per_unit']; ?>"
                                            data-product-unit="<?php echo $prod['unit']; ?>"
                                            data-stock="<?php echo $prod['stock_quantity']; ?>"
                                            <?php echo $prod['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                        <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-5">
                        <?php echo generatePagination($page, $total_pages, '?' . http_build_query(array_merge($_GET, ['page' => '%d']))); ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- No Results -->
                <div class="text-center py-5">
                    <div class="py-5">
                        <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">No products found</h4>
                        <p class="text-muted mb-4">Try adjusting your search or filter criteria</p>
                        <a href="?" class="btn btn-success">
                            <i class="bi bi-x-circle me-1"></i> Clear All Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Mobile Filters Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filtersOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Filters</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <?php include '../../includes/filters-sidebar.php'; ?>
    </div>
</div>

<style>
/* Product Card Styles */
.product-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 8px;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08) !important;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Wishlist button styles */
.wishlist-btn {
    opacity: 0.8;
    transition: all 0.2s ease;
}

.wishlist-btn:hover {
    opacity: 1;
    background-color: #ff6b6b !important;
    color: white !important;
}

.wishlist-btn.active {
    background-color: #ff6b6b !important;
    color: white !important;
}

/* Pagination */
.pagination .page-link {
    color: #198754;
}

.pagination .page-item.active .page-link {
    background-color: #198754;
    border-color: #198754;
}

/* Responsive */
@media (max-width: 768px) {
    .product-card .card-title {
        font-size: 0.85rem;
        min-height: 2.4rem;
    }
    
    .product-card .text-success {
        font-size: 1rem;
    }
}
</style>

<script>
// Function to update sort parameter
function updateSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page'); // Reset to first page when sorting
    window.location.href = url.toString();
}

// Add to cart functionality
document.addEventListener('DOMContentLoaded', function() {
    // Load wishlist status for logged-in users
    <?php if (isLoggedIn()): ?>
        loadWishlistStatus();
    <?php endif; ?>

    // Use the global cartManager if available, otherwise fallback to localStorage
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
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = cart.length;
            }
            
            return true;
        }
    };

    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const productPrice = this.dataset.productPrice;
            const productUnit = this.dataset.productUnit;
            
            // Add to cart
            cartManager.addToCart(productId, productName, productPrice, productUnit, 1);
            
            // Show success message
            showToast(`${productName} added to cart!`, 'success');
            
            // Update cart count
            updateCartCount();
        });
    });
    
    // Filter form submission with validation
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            const minPrice = this.querySelector('input[name="min_price"]').value;
            const maxPrice = this.querySelector('input[name="max_price"]').value;
            
            if (minPrice && maxPrice && parseFloat(minPrice) > parseFloat(maxPrice)) {
                e.preventDefault();
                alert('Minimum price cannot be greater than maximum price');
                return false;
            }
        });
    }
});

// Wishlist functionality
async function loadWishlistStatus() {
    try {
        const response = await fetch('../../api/wishlist/status.php');
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.wishlist) {
                data.wishlist.forEach(productId => {
                    const wishlistBtn = document.querySelector(`.wishlist-btn[onclick*="${productId}"]`);
                    if (wishlistBtn) {
                        wishlistBtn.classList.add('active');
                        wishlistBtn.innerHTML = '<i class="bi bi-heart-fill"></i>';
                        wishlistBtn.title = 'Remove from Wishlist';
                    }
                });
            }
        }
    } catch (error) {
        console.error('Error loading wishlist:', error);
    }
}

async function toggleWishlist(button, productId) {
    const isActive = button.classList.contains('active');
    
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
            if (isActive) {
                // Remove from wishlist
                button.classList.remove('active');
                button.innerHTML = '<i class="bi bi-heart"></i>';
                button.title = 'Add to Wishlist';
                showToast('Removed from wishlist', 'info');
            } else {
                // Add to wishlist
                button.classList.add('active');
                button.innerHTML = '<i class="bi bi-heart-fill"></i>';
                button.title = 'Remove from Wishlist';
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

// Toast notification function
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
            background: ${type === 'success' ? '#198754' : type === 'error' ? '#dc3545' : '#0dcaf0'};
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
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
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

// Initialize cart count on page load
updateCartCount();
</script>

<?php 
include '../../includes/footer.php'; 

// Helper functions
function getProductImage($productId, $imagePath) {
    if (!empty($imagePath)) {
        return BASE_URL . '/assets/uploads/products/' . $imagePath;
    }
    return BASE_URL . '/assets/images/placeholder-product.jpg';
}

function removeFilter($filterName) {
    $query = $_GET;
    unset($query[$filterName]);
    unset($query['page']);
    return '?' . http_build_query($query);
}
?>