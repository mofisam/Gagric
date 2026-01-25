<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();

// Initialize cart items array
$cart_items = [];
$subtotal = 0;

if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    
    // Get cart items from database
    $cart_items = $db->fetchAll("
        SELECT 
            c.*, 
            p.name, 
            p.price_per_unit, 
            p.unit, 
            p.stock_quantity,

            (
                SELECT image_path 
                FROM product_images 
                WHERE product_id = p.id 
                ORDER BY is_primary DESC, sort_order ASC, id ASC
                LIMIT 1
            ) AS image_path

        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? AND p.status = 'approved'
    ", [$user_id]);

    
    // Handle cart actions for logged-in users
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update'])) {
            foreach ($_POST['quantities'] as $product_id => $quantity) {
                if ($quantity <= 0) {
                    $db->query("DELETE FROM cart WHERE user_id = ? AND product_id = ?", 
                              [$user_id, $product_id]);
                } else {
                    $db->query("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?", 
                              [$quantity, $user_id, $product_id]);
                }
            }
            setFlashMessage('Cart updated successfully', 'success');
            header('Location: view-cart.php');
            exit;
        } elseif (isset($_POST['remove'])) {
            $product_id = $_POST['remove'];
            $db->query("DELETE FROM cart WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
            
            // Also remove from localStorage
            echo '<script>
                if (typeof cartManager !== "undefined") {
                    cartManager.removeFromCart(' . $product_id . ');
                }
            </script>';
            
            setFlashMessage('Item removed from cart', 'success');
            header('Location: view-cart.php');
            exit;
        } elseif (isset($_POST['clear'])) {
            $db->query("DELETE FROM cart WHERE user_id = ?", [$user_id]);
            
            // Also clear localStorage
            echo '<script>
                if (typeof cartManager !== "undefined") {
                    cartManager.clearCart();
                }
            </script>';
            
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
    // We'll display an empty cart and let JavaScript populate it
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
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo !empty($item['image_path']) ? '../../assets/uploads/products/' . $item['image_path'] : '../../assets/images/placeholder-product.jpg'; ?>" 
                                                                 class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                                <p class="text-muted mb-0">Unit: <?php echo $item['unit']; ?></p>
                                                                <?php if ($item['stock_quantity'] < $item['quantity']): ?>
                                                                    <small class="text-danger">Only <?php echo $item['stock_quantity']; ?> in stock</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <h6 class="mb-0"><?php echo formatCurrency($item['price_per_unit']); ?></h6>
                                                    </td>
                                                    <td>
                                                        <div class="input-group" style="width: 120px;">
                                                            <input type="number" class="form-control quantity-input" 
                                                                   name="quantities[<?php echo $item['product_id']; ?>]" 
                                                                   value="<?php echo $item['quantity']; ?>" 
                                                                   min="1" max="<?php echo $item['stock_quantity']; ?>"
                                                                   data-product-id="<?php echo $item['product_id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <h6 class="mb-0 text-success item-total">
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
                                        <button name="update" class="btn btn-success">
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
// Cart management for logged-out users
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
    
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value);
            updateQuantity(productId, quantity);
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
            cartManager.clearCart();
            displayGuestCart();
        }
    });
    
    // Add sync button if user just logged in with localStorage items
    <?php if (isLoggedIn() && isset($_GET['sync_cart']) && $_GET['sync_cart'] == '1'): ?>
        syncCartWithDatabase();
    <?php endif; ?>
});

function displayGuestCart() {
    const cart = cartManager.getCart();
    
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
                        <h6 class="mb-1">${item.productName}</h6>
                        <p class="text-muted mb-0">Unit: ${item.productUnit}</p>
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
            cartManager.removeFromCart(productId);
            displayGuestCart();
        });
    });
    
    document.querySelectorAll('.guest-quantity').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value);
            cartManager.updateQuantity(productId, quantity);
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

function removeFromCart(productId) {
    <?php if (isLoggedIn()): ?>
        // Check if item exists in localStorage (for recently logged-in users)
        const localStorageCart = JSON.parse(localStorage.getItem('greenagric_cart') || '[]');
        const itemInLocalStorage = localStorageCart.some(item => item.productId == productId);
        
        // Always try to delete from database first
        const form = document.getElementById('cart-form');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove';
        input.value = productId;
        form.appendChild(input);
        form.submit();
        
        // Also remove from localStorage if it exists there
        if (itemInLocalStorage && typeof cartManager !== 'undefined') {
            cartManager.removeFromCart(productId);
        }
    <?php else: ?>
        // For guests, use cart manager
        cartManager.removeFromCart(productId);
        displayGuestCart();
    <?php endif; ?>
}

function updateQuantity(productId, quantity) {
    <?php if (isLoggedIn()): ?>
        // For logged-in users, update via form submission
        const form = document.getElementById('cart-form');
        const quantityInput = document.querySelector(`input[name="quantities[${productId}]"]`);
        if (quantityInput) {
            quantityInput.value = quantity;
            form.submit();
        }
    <?php else: ?>
        // For guests, use cart manager
        cartManager.updateQuantity(productId, quantity);
        displayGuestCart();
    <?php endif; ?>
}

function clearCart() {
    <?php if (isLoggedIn()): ?>
        if (confirm('Are you sure you want to clear your cart?')) {
            // Clear from database via form submission
            const form = document.getElementById('cart-form');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'clear';
            input.value = '1';
            form.appendChild(input);
            form.submit();
            
            // Also clear localStorage
            if (typeof cartManager !== 'undefined') {
                cartManager.clearCart();
            }
        }
    <?php else: ?>
        // For guests, use cart manager
        cartManager.clearCart();
        displayGuestCart();
    <?php endif; ?>
}

// Function to sync localStorage cart with database after login
async function syncCartWithDatabase() {
    const localCart = cartManager.getCart();
    if (localCart.length === 0) return;
    
    try {
        const response = await fetch('../../api/cart/sync.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart: localCart
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Clear localStorage after successful sync
            cartManager.clearCart();
            // Reload to show synced cart
            window.location.href = window.location.pathname;
        } else {
            console.error('Failed to sync cart:', data.error);
        }
    } catch (error) {
        console.error('Error syncing cart:', error);
    }
}
</script>

<?php 
$page_js = 'cart.js';
include '../../includes/footer.php'; 
?>