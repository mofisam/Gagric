<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();

// Initialize cart items array
$cart_items = [];
$subtotal = 0;

if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    
    // OPTIMIZED QUERY: Using JOIN instead of subquery for product images
    $cart_items = $db->fetchAll("
        SELECT 
            c.*, 
            p.name, 
            p.price_per_unit, 
            p.unit, 
            p.stock_quantity,
            pi.image_path
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
        WHERE c.user_id = ? AND p.status = 'approved'
    ", [$user_id]);
    
    // Handle cart actions for logged-in users with CSRF protection
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF Validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('Invalid CSRF token');
        }
        
        if (isset($_POST['update'])) {
            foreach ($_POST['quantities'] as $product_id => $quantity) {
                $product_id = (int)$product_id;
                $quantity = (int)$quantity;
                
                if ($quantity <= 0) {
                    $db->query("DELETE FROM cart WHERE user_id = ? AND product_id = ?", 
                              [$user_id, $product_id]);
                } else {
                    // Validate stock before updating
                    $stock = $db->fetchOne("SELECT stock_quantity FROM products WHERE id = ?", [$product_id]);
                    if ($stock && $quantity > $stock['stock_quantity']) {
                        $quantity = $stock['stock_quantity'];
                        setFlashMessage("Quantity adjusted to available stock for one or more items", 'warning');
                    }
                    $db->query("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?", 
                              [$quantity, $user_id, $product_id]);
                }
            }
            setFlashMessage('Cart updated successfully', 'success');
            header('Location: view-cart.php');
            exit;
        } elseif (isset($_POST['remove'])) {
            $product_id = (int)$_POST['remove'];
            $db->query("DELETE FROM cart WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
            
            setFlashMessage('Item removed from cart', 'success');
            header('Location: view-cart.php');
            exit;
        } elseif (isset($_POST['clear'])) {
            $db->query("DELETE FROM cart WHERE user_id = ?", [$user_id]);
            
            setFlashMessage('Cart cleared successfully', 'success');
            header('Location: view-cart.php');
            exit;
        }
    }
    
    // Calculate subtotal
    foreach ($cart_items as $item) {
        $subtotal += $item['price_per_unit'] * $item['quantity'];
    }
    
} else {
    // For guest users, cart is managed via JavaScript
    $cart_items = [];
    $subtotal = 0;
}

$shipping = 500; // Default shipping
$total = $subtotal + $shipping;

$page_title = "Shopping Cart";
$page_css = 'cart.css';
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <h2 class="mb-4">Shopping Cart</h2>
    
    <?php if (!isLoggedIn()): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            You're browsing as a guest. 
            <a href="../../auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="alert-link">
                Login
            </a> to save your cart and access it from any device.
        </div>
    <?php endif; ?>
    
    <div id="cart-container">
        <!-- Cart content will be loaded here -->
        <?php if (empty($cart_items) && isLoggedIn()): ?>
            <div class="card text-center py-5">
                <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
                <h4 class="mt-3">Your cart is empty</h4>
                <p class="text-muted">Add some fresh agricultural products to get started!</p>
                <a href="../products/browse.php" class="btn btn-success">Browse Products</a>
            </div>
        <?php elseif (!empty($cart_items)): ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="" id="cart-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th width="60%">Product</th>
                                                <th>Price</th>
                                                <th>Quantity</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $item): ?>
                                                <?php 
                                                $item_total = $item['price_per_unit'] * $item['quantity'];
                                                ?>
                                                <tr data-product-id="<?php echo $item['product_id']; ?>">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo !empty($item['image_path']) ? '../../assets/uploads/products/' . $item['image_path'] : '../../assets/images/placeholder-product.jpg'; ?>" 
                                                                 class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                                <p class="text-muted mb-0">Unit: <?php echo htmlspecialchars($item['unit']); ?></p>
                                                                <?php if ($item['stock_quantity'] < $item['quantity']): ?>
                                                                    <small class="text-danger">Only <?php echo $item['stock_quantity']; ?> in stock</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <td>
                                                        <h6 class="mb-0"><?php echo formatCurrency($item['price_per_unit']); ?></h6>
                                                   </td>
                                                    <td>
                                                        <div class="input-group" style="width: 120px;">
                                                            <input type="number" class="form-control quantity-input" 
                                                                   name="quantities[<?php echo $item['product_id']; ?>]" 
                                                                   value="<?php echo $item['quantity']; ?>" 
                                                                   min="1" max="<?php echo $item['stock_quantity']; ?>"
                                                                   data-product-id="<?php echo $item['product_id']; ?>"
                                                                   data-stock="<?php echo $item['stock_quantity']; ?>"
                                                                   data-price="<?php echo $item['price_per_unit']; ?>">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <h6 class="mb-0 text-success item-total" data-product-id="<?php echo $item['product_id']; ?>">
                                                            <?php echo formatCurrency($item_total); ?>
                                                        </h6>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item" 
                                                                data-product-id="<?php echo $item['product_id']; ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="../products/browse.php" class="btn btn-outline-success">
                                        <i class="bi bi-arrow-left me-2"></i> Continue Shopping
                                    </a>
                                    <div>
                                        <button type="button" id="clear-cart" class="btn btn-outline-danger me-2">
                                            <i class="bi bi-trash me-1"></i> Clear Cart
                                        </button>
                                        <button type="submit" name="update" class="btn btn-success">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Update Cart
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span id="subtotal"><?php echo formatCurrency($subtotal); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span><?php echo formatCurrency($shipping); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax</span>
                                <span>₦0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-4">
                                <h5>Total</h5>
                                <h5 class="text-success" id="total"><?php echo formatCurrency($total); ?></h5>
                            </div>
                            
                            <div class="d-grid">
                                <?php if (isLoggedIn()): ?>
                                    <a href="checkout.php" class="btn btn-success btn-lg">
                                        Proceed to Checkout <i class="bi bi-arrow-right ms-2"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="../../auth/login.php?redirect=<?php echo urlencode('buyer/cart/checkout.php'); ?>" 
                                       class="btn btn-success btn-lg">
                                        Login to Checkout <i class="bi bi-arrow-right ms-2"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <small class="text-muted">
                                    <i class="bi bi-shield-check me-1"></i> Secure checkout powered by Paystack
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Guest cart template (hidden) -->
    <div id="guest-cart-template" style="display: none;">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="60%">Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="guest-cart-items">
                            <!-- JavaScript will populate this -->
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="../products/browse.php" class="btn btn-outline-success">
                        <i class="bi bi-arrow-left me-2"></i> Continue Shopping
                    </a>
                    <div>
                        <button type="button" id="guest-clear-cart" class="btn btn-outline-danger me-2">
                            <i class="bi bi-trash me-1"></i> Clear Cart
                        </button>
                        <a href="../../auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                           class="btn btn-success">
                            <i class="bi bi-person me-1"></i> Login to Save Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// CSRF Token for AJAX requests
const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

document.addEventListener('DOMContentLoaded', function() {
    // If user is not logged in, display cart from localStorage
    <?php if (!isLoggedIn()): ?>
        displayGuestCart();
    <?php endif; ?>
    
    // Add event listeners for cart actions
    document.querySelectorAll('.remove-item').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            removeFromCart(productId);
        });
    });
    
    // AJAX-based quantity update for better UX
    document.querySelectorAll('.quantity-input').forEach(input => {
        let debounceTimer;
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value);
            const maxStock = parseInt(this.dataset.stock);
            
            if (quantity > maxStock) {
                showToast(`Only ${maxStock} items available in stock`, 'warning');
                this.value = maxStock;
                updateQuantityViaAJAX(productId, maxStock);
            } else if (quantity < 1) {
                this.value = 1;
                updateQuantityViaAJAX(productId, 1);
            } else {
                updateQuantityViaAJAX(productId, quantity);
            }
        });
    });
    
    document.getElementById('clear-cart')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear your cart?')) {
            clearCart();
        }
    });
    
    // For guest users
    document.getElementById('guest-clear-cart')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear your cart?')) {
            if (typeof cartManager !== 'undefined') {
                cartManager.clearCart();
            } else {
                localStorage.removeItem('greenagric_cart');
            }
            displayGuestCart();
            updateCartCount();
        }
    });
    
    // Auto-sync for logged-in users who might have guest cart data (ONLY JavaScript sync)
    <?php if (isLoggedIn()): ?>
        const localCart = localStorage.getItem('greenagric_cart');
        const hasSynced = sessionStorage.getItem('cart_synced');
        if (localCart && localCart !== '[]' && !hasSynced) {
            const cartItems = JSON.parse(localCart);
            if (cartItems.length > 0) {
                syncCartWithDatabase();
                sessionStorage.setItem('cart_synced', 'true');
            }
        }
    <?php endif; ?>
});

// AJAX function to update quantity without page reload
async function updateQuantityViaAJAX(productId, quantity) {
    <?php if (isLoggedIn()): ?>
        try {
            const response = await fetch('../../api/cart/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity,
                    csrf_token: csrfToken
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update UI
                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) {
                    const price = parseFloat(row.querySelector('.quantity-input').dataset.price);
                    const itemTotal = price * quantity;
                    const itemTotalElement = row.querySelector('.item-total');
                    if (itemTotalElement) {
                        itemTotalElement.textContent = formatNaira(itemTotal);
                    }
                }
                
                // Update totals
                await updateTotals();
                showToast('Cart updated', 'success');
            } else {
                showToast(data.error || 'Update failed', 'error');
                // Reload to show correct state
                window.location.reload();
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            showToast('Network error. Please try again.', 'error');
        }
    <?php else: ?>
        // For guests, use localStorage
        if (typeof cartManager !== 'undefined') {
            cartManager.updateQuantity(productId, quantity);
        } else {
            let cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
            const index = cart.findIndex(item => item.productId == productId);
            if (index !== -1) {
                cart[index].quantity = quantity;
                localStorage.setItem('greenagric_cart', JSON.stringify(cart));
            }
        }
        displayGuestCart();
    <?php endif; ?>
}

// Function to update totals via AJAX
async function updateTotals() {
    <?php if (isLoggedIn()): ?>
        try {
            const response = await fetch('../../api/cart/totals.php', {
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            const data = await response.json();
            if (data.success) {
                const subtotalElement = document.getElementById('subtotal');
                const totalElement = document.getElementById('total');
                if (subtotalElement) subtotalElement.textContent = formatNaira(data.subtotal);
                if (totalElement) totalElement.textContent = formatNaira(data.total);
            }
        } catch (error) {
            console.error('Error updating totals:', error);
        }
    <?php endif; ?>
}

function displayGuestCart() {
    let cart;
    if (typeof cartManager !== 'undefined' && cartManager.getCart) {
        cart = cartManager.getCart();
    } else {
        cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
    }
    
    if (cart.length === 0) {
        document.getElementById('cart-container').innerHTML = `
            <div class="card text-center py-5">
                <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
                <h4 class="mt-3">Your cart is empty</h4>
                <p class="text-muted">Add some fresh agricultural products to get started!</p>
                <a href="../products/browse.php" class="btn btn-success">Browse Products</a>
            </div>
        `;
        return;
    }
    
    // Display guest cart
    const template = document.getElementById('guest-cart-template').innerHTML;
    document.getElementById('cart-container').innerHTML = template;
    
    // Populate cart items
    const itemsContainer = document.getElementById('guest-cart-items');
    let subtotal = 0;
    
    cart.forEach(item => {
        const itemTotal = item.productPrice * item.quantity;
        subtotal += itemTotal;
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <img src="../../assets/images/placeholder-product.jpg" 
                         class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                    <div>
                        <h6 class="mb-1">${escapeHtml(item.productName)}</h6>
                        <p class="text-muted mb-0">Unit: ${escapeHtml(item.productUnit)}</p>
                    </div>
                </div>
            </td>
            <td>
                <h6 class="mb-0">₦${item.productPrice.toLocaleString('en-NG')}</h6>
            </td>
            <td>
                <div class="input-group" style="width: 120px;">
                    <input type="number" class="form-control guest-quantity" 
                           value="${item.quantity}" min="1"
                           data-product-id="${item.productId}">
                </div>
            </td>
            <td>
                <h6 class="mb-0 text-success">₦${itemTotal.toLocaleString('en-NG')}</h6>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger guest-remove-item" 
                        data-product-id="${item.productId}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        itemsContainer.appendChild(row);
    });
    
    // Add event listeners for guest cart items
    document.querySelectorAll('.guest-remove-item').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            if (typeof cartManager !== 'undefined') {
                cartManager.removeFromCart(productId);
            } else {
                let cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
                cart = cart.filter(item => item.productId != productId);
                localStorage.setItem('greenagric_cart', JSON.stringify(cart));
            }
            displayGuestCart();
            updateCartCount();
        });
    });
    
    document.querySelectorAll('.guest-quantity').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value);
            if (typeof cartManager !== 'undefined') {
                cartManager.updateQuantity(productId, quantity);
            } else {
                let cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
                const index = cart.findIndex(item => item.productId == productId);
                if (index !== -1) {
                    cart[index].quantity = quantity;
                    localStorage.setItem('greenagric_cart', JSON.stringify(cart));
                }
            }
            displayGuestCart();
        });
    });
    
    // Update totals
    const shipping = 500;
    const total = subtotal + shipping;
    
    // Update summary section
    const summaryDiv = document.querySelector('.col-lg-4');
    if (summaryDiv) {
        summaryDiv.innerHTML = `
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span>₦${subtotal.toLocaleString('en-NG')}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping</span>
                        <span>₦${shipping.toLocaleString('en-NG')}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax</span>
                        <span>₦0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <h5>Total</h5>
                        <h5 class="text-success">₦${total.toLocaleString('en-NG')}</h5>
                    </div>
                    
                    <div class="d-grid">
                        <a href="../../auth/login.php?redirect=${encodeURIComponent('buyer/cart/checkout.php')}" 
                           class="btn btn-success btn-lg">
                            Login to Checkout <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="bi bi-shield-check me-1"></i> Secure checkout powered by Paystack
                        </small>
                    </div>
                </div>
            </div>
        `;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function formatNaira(amount) {
    return '₦' + amount.toLocaleString('en-NG');
}

function removeFromCart(productId) {
    <?php if (isLoggedIn()): ?>
        // Submit form to PHP (with CSRF)
        const form = document.getElementById('cart-form');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove';
        input.value = productId;
        form.appendChild(input);
        // Remove from localStorage
        if (typeof cartManager !== 'undefined') {
            cartManager.removeFromCart(productId);
        } else {
            let cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
            cart = cart.filter(item => item.productId != productId);
            localStorage.setItem('greenagric_cart', JSON.stringify(cart));
        }
        form.submit();
    <?php else: ?>
        // For guests, use cart manager
        if (typeof cartManager !== 'undefined') {
            cartManager.removeFromCart(productId);
        } else {
            let cart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
            cart = cart.filter(item => item.productId != productId);
            localStorage.setItem('greenagric_cart', JSON.stringify(cart));
        }
        displayGuestCart();
        updateCartCount();
    <?php endif; ?>
}

function clearCart() {
    if (confirm('Are you sure you want to clear your cart?')) {

        // ALWAYS clear localStorage (both guest & logged-in)
        if (typeof cartManager !== 'undefined') {
            cartManager.clearCart();
        } else {
            localStorage.removeItem('greenagric_cart');
        }

        // Update UI immediately
        updateCartCount();

        <?php if (isLoggedIn()): ?>
            const form = document.getElementById('cart-form');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'clear';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        <?php else: ?>
            displayGuestCart();
        <?php endif; ?>
    }
}

// ONLY JavaScript-based sync via API (NO PHP sync)
async function syncCartWithDatabase() {
    let localCart;
    if (typeof cartManager !== 'undefined' && cartManager.getCart) {
        localCart = cartManager.getCart();
    } else {
        localCart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
    }
    
    if (localCart.length === 0) return;
    
    // Show loading indicator
    const cartContainer = document.getElementById('cart-container');
    if (cartContainer) {
        cartContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Syncing your cart...</span>
                </div>
                <p class="mt-3">Syncing your guest cart items...</p>
            </div>
        `;
    }
    
    try {
        const response = await fetch('../../api/cart/sync.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart: localCart,
                csrf_token: csrfToken
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Clear localStorage after successful sync
            if (typeof cartManager !== 'undefined') {
                cartManager.clearCart();
            }
            localStorage.removeItem('greenagric_cart');
            
            // Show success message and reload
            updateCartCount();
            showToast('Cart synced successfully!', 'success');
            window.location.reload();
        } else {
            console.error('Failed to sync cart:', data.error);
            showToast(data.error || 'Failed to sync cart', 'error');
            window.location.reload();
        }
    } catch (error) {
        console.error('Error syncing cart:', error);
        showToast('Network error. Please refresh the page.', 'error');
        window.location.reload();
    }
}

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

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? '#198754' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#0dcaf0';
    const textColor = type === 'warning' ? '#000' : '#fff';
    
    toast.className = `toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: ${bgColor};
        color: ${textColor};
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        font-size: 14px;
    `;
    
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            <span>${message}</span>
            <button class="btn-close btn-close-${textColor === '#fff' ? 'white' : ''} ms-3" style="font-size: 10px;" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => toast.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Initialize cart count on page load
updateCartCount();
</script>

<?php 
$page_js = 'cart.js';
include '../../includes/footer.php'; 
?>