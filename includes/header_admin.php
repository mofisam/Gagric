<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../classes/Database.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Green Agric</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <!-- Page-specific CSS -->
    <?php if (isset($page_css)): ?>
        <link href="<?php echo BASE_URL; ?>/assets/css/<?php echo $page_css; ?>" rel="stylesheet">
    <?php endif; ?>

    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
    <?php if ($user_role !== 'admin' && $user_role !== 'seller'): ?>
    <script>
    // Cart management system
    class CartManager {
        constructor() {
            this.cartKey = 'greenagric_cart';
            this.cart = this.getCart();
        }

        getCart() {
            console.log("get cart");
            return JSON.parse(localStorage.getItem(this.cartKey) || '[]');
        }

        saveCart(cart) {
            console.log("cart");
            localStorage.setItem(this.cartKey, JSON.stringify(cart));
            this.updateCartCount();
        }

        updateCartCount() {
            console.log("update cart count");
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = this.cart.length;
            }
        }

        addToCart(productId, productName, productPrice, productUnit, quantity = 1) {
            // Check if product already in cart
            console.log("add to cart");
            const existingIndex = this.cart.findIndex(item => item.productId == productId);
            
            if (existingIndex >= 0) {
                // Update quantity
                this.cart[existingIndex].quantity += quantity;
            } else {
                // Add new item
                this.cart.push({
                    productId: productId,
                    productName: productName,
                    productPrice: parseFloat(productPrice),
                    productUnit: productUnit,
                    quantity: quantity
                });
            }
            
            this.saveCart(this.cart);
            
            // If user is logged in, sync with database
            if (this.isUserLoggedIn()) {
                this.syncCartWithServer();
            }
            
            return true;
        }

        removeFromCart(productId) {
            this.cart = this.cart.filter(item => item.productId != productId);
            this.saveCart(this.cart);
            
            // If user is logged in, sync with database
            if (this.isUserLoggedIn()) {
                this.syncCartWithServer();
            }
        }

        clearCart() {
            this.cart = [];
            this.saveCart(this.cart);
            
            // If user is logged in, sync with database
            if (this.isUserLoggedIn()) {
                this.syncCartWithServer();
            }
        }

        updateQuantity(productId, quantity) {
            console.log("update quanity");
            const index = this.cart.findIndex(item => item.productId == productId);
            if (index >= 0) {
                if (quantity <= 0) {
                    this.removeFromCart(productId);
                } else {
                    this.cart[index].quantity = quantity;
                    this.saveCart(this.cart);
                    
                    // If user is logged in, sync with database
                    if (this.isUserLoggedIn()) {
                        this.syncCartWithServer();
                    }
                }
            }
        }

        getCartTotal() {
            console.log("get cart total");
            return this.cart.reduce((total, item) => {
                return total + (item.productPrice * item.quantity);
            }, 0);
        }

        isUserLoggedIn() {
            // Check if user is logged in (you'll need to set a flag or check session)
            return document.body.classList.contains('user-logged-in') || 
                <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
        }

        async syncCartWithServer() {
            try {
                const response = await fetch('<?php echo BASE_URL; ?>/api/cart/sync.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        cart: this.cart
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    console.log('Cart synced with server');
                }
            } catch (error) {
                console.error('Failed to sync cart:', error);
            }
        }

        async loadCartFromServer() {
            console.log("load card from server");
            try {
                const response = await fetch('<?php echo BASE_URL; ?>/api/cart/load.php');
                const result = await response.json();
                
                if (result.success && result.cart) {
                    // Merge server cart with local cart
                    this.cart = this.mergeCarts(this.cart, result.cart);
                    this.saveCart(this.cart);
                    return true;
                }
            } catch (error) {
                console.error('Failed to load cart from server:', error);
            }
            return false;
        }

        mergeCarts(localCart, serverCart) {
            const mergedCart = [...serverCart];
            
            // Add items from local cart that aren't in server cart
            localCart.forEach(localItem => {
                const existingIndex = mergedCart.findIndex(item => 
                    item.productId == localItem.productId
                );
                
                if (existingIndex >= 0) {
                    // Use the larger quantity
                    mergedCart[existingIndex].quantity = Math.max(
                        mergedCart[existingIndex].quantity,
                        localItem.quantity
                    );
                } else {
                    mergedCart.push(localItem);
                }
            });
            
            return mergedCart;
        }
    }

    // Initialize cart manager
    window.cartManager = new CartManager();

    // Update cart count on page load
    document.addEventListener('DOMContentLoaded', function() {
        cartManager.updateCartCount();
        
        // If user just logged in, load cart from server
        if (cartManager.isUserLoggedIn() && !window.cartLoadedFromServer) {
            cartManager.loadCartFromServer();
            window.cartLoadedFromServer = true;
        }
    });
    </script>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>/index.php">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.jpeg" 
                    alt="Green Agric Logo" 
                    style="height:40px; width:auto; margin-right:8px;">
                <span class="fw-bold text-success">Green Agric</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/buyer/products/browse.php">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/buyer/products/categories.php">Categories</a>
                    </li>
                    <?php if ($is_logged_in && $user_role === 'seller'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/seller/dashboard.php">Seller Dashboard</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($is_logged_in && $user_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Admin Dashboard</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if ($is_logged_in): ?>
                        <!-- User is logged in -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user_name); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/buyer/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/buyer/profile/personal-info.php">
                                    <i class="bi bi-person"></i> Profile
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/buyer/orders/order-history.php">
                                    <i class="bi bi-bag"></i> My Orders
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/auth/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a></li>
                            </ul>
                        </li>
                        
                        <?php if ($user_role === 'buyer'): ?>
                            <li class="nav-item">
                                <a class="nav-link position-relative" href="<?php echo BASE_URL; ?>/buyer/cart/view-cart.php">
                                    <i class="bi bi-cart3"></i> Cart
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                                        0
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- User is not logged in -->
                        <?php if ($user_role === 'null'): ?>
                            <li class="nav-item">
                                <a class="nav-link position-relative" href="<?php echo BASE_URL; ?>/buyer/cart/view-cart.php">
                                    <i class="bi bi-cart3"></i> Cart
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                                        0
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-success ms-2" href="<?php echo BASE_URL; ?>/auth/register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <!-- Display flash messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="container mt-3">
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>