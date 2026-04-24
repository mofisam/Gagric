<?php
/**
 * Reusable Side Cart Component
 * Can be included on any page that has products
 * Requires: Bootstrap 5, Bootstrap Icons
 */
?>
<style>
/* Side Cart Styles */
.side-cart-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1055;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.side-cart-backdrop.show {
    opacity: 1;
    visibility: visible;
}

.side-cart {
    position: fixed;
    top: 0;
    right: -400px;
    width: 400px;
    max-width: 100vw;
    height: 100vh;
    background: white;
    z-index: 1056;
    transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    box-shadow: -4px 0 15px rgba(0, 0, 0, 0.1);
}

.side-cart.show {
    right: 0;
}

.side-cart-header {
    padding: 1.25rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.side-cart-header h5 {
    margin: 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.side-cart-body {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 #f1f1f1;
}

.side-cart-body::-webkit-scrollbar {
    width: 6px;
}

.side-cart-body::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.side-cart-body::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.side-cart-footer {
    border-top: 1px solid #e9ecef;
    padding: 1.25rem;
    background: #f8f9fa;
    flex-shrink: 0;
}

/* Cart Item Styles */
.side-cart-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.side-cart-item:last-child {
    border-bottom: none;
}

.side-cart-item-image {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
}

.side-cart-item-details {
    flex: 1;
    min-width: 0;
}

.side-cart-item-name {
    font-weight: 500;
    font-size: 0.9rem;
    margin-bottom: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.side-cart-item-price {
    color: #198754;
    font-weight: 600;
    font-size: 0.95rem;
}

.side-cart-item-unit {
    color: #6c757d;
    font-size: 0.8rem;
}

.side-cart-item-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.quantity-control {
    display: flex;
    align-items: center;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
}

.quantity-control button {
    background: #f8f9fa;
    border: none;
    padding: 4px 10px;
    cursor: pointer;
    transition: background 0.2s;
    font-size: 0.9rem;
    color: #495057;
}

.quantity-control button:hover {
    background: #e9ecef;
}

.quantity-control button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.quantity-control input {
    width: 45px;
    text-align: center;
    border: none;
    border-left: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    padding: 4px 0;
    font-size: 0.9rem;
    -moz-appearance: textfield;
}

.quantity-control input::-webkit-outer-spin-button,
.quantity-control input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.remove-cart-item {
    color: #dc3545;
    background: none;
    border: none;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 1.1rem;
    transition: color 0.2s;
    border-radius: 4px;
}

.remove-cart-item:hover {
    color: #c82333;
    background: rgba(220, 53, 69, 0.1);
}

/* Empty Cart State */
.side-cart-empty {
    text-align: center;
    padding: 3rem 1rem;
}

.side-cart-empty i {
    font-size: 4rem;
    color: #cbd3da;
    display: block;
    margin-bottom: 1rem;
}

.side-cart-empty p {
    color: #6c757d;
    margin-bottom: 1.5rem;
}

/* Summary Styles */
.side-cart-summary {
    margin-bottom: 1rem;
}

.side-cart-summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.side-cart-summary-row.total {
    font-size: 1.1rem;
    font-weight: 600;
    border-top: 2px solid #dee2e6;
    padding-top: 12px;
    margin-top: 8px;
}

/* Responsive */
@media (max-width: 576px) {
    .side-cart {
        width: 100vw;
        right: -100vw;
    }
}

/* Badge animation */
@keyframes cartBadgePop {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); }
    100% { transform: scale(1); }
}

.cart-badge-animate {
    animation: cartBadgePop 0.3s ease;
}
</style>

<!-- Side Cart HTML -->
<div class="side-cart-backdrop" id="sideCartBackdrop"></div>
<div class="side-cart" id="sideCart">
    <!-- Header -->
    <div class="side-cart-header">
        <h5>
            <i class="bi bi-cart3"></i>
            Shopping Cart
            <span class="badge bg-success ms-2" id="sideCartCount">0</span>
        </h5>
        <button type="button" class="btn-close" id="closeSideCart" aria-label="Close"></button>
    </div>
    
    <!-- Body -->
    <div class="side-cart-body" id="sideCartBody">
        <!-- Empty State (shown when cart is empty) -->
        <div class="side-cart-empty" id="sideCartEmpty">
            <i class="bi bi-cart-x"></i>
            <p>Your cart is empty</p>
            <a href="../products/browse.php" class="btn btn-outline-success btn-sm">
                Browse Products
            </a>
        </div>
        
        <!-- Cart Items Container -->
        <div id="sideCartItems"></div>
    </div>
    
    <!-- Footer -->
    <div class="side-cart-footer" id="sideCartFooter" style="display: none;">
        <!-- Summary -->
        <div class="side-cart-summary">
            <div class="side-cart-summary-row">
                <span>Subtotal</span>
                <span id="sideCartSubtotal">₦0</span>
            </div>
            <div class="side-cart-summary-row">
                <span>Shipping</span>
                <span id="sideCartShipping">₦500</span>
            </div>
            <div class="side-cart-summary-row total">
                <span>Total</span>
                <span id="sideCartTotal">₦0</span>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="d-grid gap-2">
            <a href="view-cart.php" class="btn btn-outline-success">
                <i class="bi bi-cart me-2"></i>View Full Cart
            </a>
            <?php if (isLoggedIn()): ?>
                <a href="checkout.php" class="btn btn-success">
                    <i class="bi bi-arrow-right me-2"></i>Proceed to Checkout
                </a>
            <?php else: ?>
                <a href="../../auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-success">
                    <i class="bi bi-person me-2"></i>Login to Checkout
                </a>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-2">
            <small class="text-muted">
                <i class="bi bi-shield-check me-1"></i> Secure checkout
            </small>
        </div>
    </div>
</div>

<script>
class SideCart {
    constructor(options = {}) {
        this.cartKey = options.cartKey || 'greenagric_cart';
        this.apiBase = options.apiBase || '../../api/cart';
        this.csrfToken = options.csrfToken || '';
        this.isLoggedIn = options.isLoggedIn || false;
        this.shipping = options.shipping || 500;
        this.onCartUpdate = options.onCartUpdate || null;
        
        // DOM Elements
        this.backdrop = document.getElementById('sideCartBackdrop');
        this.sideCart = document.getElementById('sideCart');
        this.countBadge = document.getElementById('sideCartCount');
        this.itemsContainer = document.getElementById('sideCartItems');
        this.emptyState = document.getElementById('sideCartEmpty');
        this.footer = document.getElementById('sideCartFooter');
        this.subtotalEl = document.getElementById('sideCartSubtotal');
        this.shippingEl = document.getElementById('sideCartShipping');
        this.totalEl = document.getElementById('sideCartTotal');
        
        // Initialize
        this.init();
    }
    
    init() {
        // Close button handler
        document.getElementById('closeSideCart')?.addEventListener('click', () => this.close());
        
        // Backdrop click handler
        this.backdrop?.addEventListener('click', () => this.close());
        
        // Escape key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });
        
        // Prevent body scroll when cart is open
        this.sideCart?.addEventListener('scroll', (e) => {
            e.stopPropagation();
        });
    }
    
    open() {
        this.backdrop?.classList.add('show');
        this.sideCart?.classList.add('show');
        document.body.style.overflow = 'hidden';
        this.renderCart();
    }
    
    close() {
        this.backdrop?.classList.remove('show');
        this.sideCart?.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    toggle() {
        if (this.sideCart?.classList.contains('show')) {
            this.close();
        } else {
            this.open();
        }
    }
    
    async addToCart(productId, productName, productPrice, productUnit, quantity = 1, imagePath = null) {
        try {
            if (this.isLoggedIn) {
                // Use API for logged-in users
                const response = await fetch(`${this.apiBase}/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: quantity,
                        csrf_token: this.csrfToken
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await this.loadCartFromAPI();
                } else if (data.error) {
                    // If item exists but needs quantity update
                    if (data.max_quantity) {
                        return await this.addToCart(productId, productName, productPrice, productUnit, data.max_quantity, imagePath);
                    }
                    throw new Error(data.error);
                }
            } else {
                // Use localStorage for guest users
                let cart = this.getLocalCart();
                const existingIndex = cart.findIndex(item => item.productId == productId);
                
                if (existingIndex >= 0) {
                    cart[existingIndex].quantity += quantity;
                } else {
                    cart.push({
                        productId: productId,
                        productName: productName,
                        productPrice: parseFloat(productPrice),
                        productUnit: productUnit,
                        quantity: quantity,
                        imagePath: imagePath || 'placeholder-product.jpg'
                    });
                }
                
                localStorage.setItem(this.cartKey, JSON.stringify(cart));
            }
            
            // Open side cart and render
            this.open();
            this.updateGlobalCount();
            
            if (this.onCartUpdate) {
                this.onCartUpdate();
            }
            
            return true;
        } catch (error) {
            console.error('Error adding to cart:', error);
            throw error;
        }
    }
    
    async updateQuantity(productId, quantity) {
        try {
            if (quantity < 1) {
                await this.removeFromCart(productId);
                return;
            }
            
            if (this.isLoggedIn) {
                const response = await fetch(`${this.apiBase}/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: quantity,
                        csrf_token: this.csrfToken
                    })
                });
                
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Update failed');
                }
                
                await this.loadCartFromAPI();
            } else {
                let cart = this.getLocalCart();
                const index = cart.findIndex(item => item.productId == productId);
                
                if (index !== -1) {
                    cart[index].quantity = quantity;
                    localStorage.setItem(this.cartKey, JSON.stringify(cart));
                    this.renderCart();
                }
            }
            
            this.updateGlobalCount();
            
            if (this.onCartUpdate) {
                this.onCartUpdate();
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            this.showToast(error.message, 'error');
        }
    }
    
    async removeFromCart(productId) {
        try {
            if (this.isLoggedIn) {
                const response = await fetch(`${this.apiBase}/update.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: 0,
                        csrf_token: this.csrfToken
                    })
                });
                
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Remove failed');
                }
                
                await this.loadCartFromAPI();
            } else {
                let cart = this.getLocalCart();
                cart = cart.filter(item => item.productId != productId);
                localStorage.setItem(this.cartKey, JSON.stringify(cart));
                this.renderCart();
            }
            
            this.updateGlobalCount();
            
            if (this.onCartUpdate) {
                this.onCartUpdate();
            }
        } catch (error) {
            console.error('Error removing from cart:', error);
            this.showToast(error.message, 'error');
        }
    }
    
    async clearCart() {
        try {
            if (this.isLoggedIn) {
                // Clear via form submission reload
                window.location.href = 'view-cart.php?clear=1';
            } else {
                localStorage.removeItem(this.cartKey);
                this.renderCart();
                this.updateGlobalCount();
            }
        } catch (error) {
            console.error('Error clearing cart:', error);
        }
    }
    
    getLocalCart() {
        return JSON.parse(localStorage.getItem(this.cartKey) || '[]');
    }
    
    async loadCartFromAPI() {
        try {
            const response = await fetch(`${this.apiBase}/load.php`);
            const data = await response.json();
            
            if (data.success) {
                // Convert API format to local format
                const cartItems = data.cart.map(item => ({
                    productId: item.productId,
                    productName: item.productName,
                    productPrice: item.productPrice,
                    productUnit: item.productUnit,
                    quantity: item.quantity,
                    imagePath: item.imagePath
                }));
                
                localStorage.setItem(this.cartKey, JSON.stringify(cartItems));
                this.renderCart();
            }
        } catch (error) {
            console.error('Error loading cart from API:', error);
        }
    }
    
    async renderCart() {
        const cart = this.getLocalCart();
        const count = cart.length;
        
        // Update badge
        if (this.countBadge) {
            this.countBadge.textContent = count;
            this.countBadge.classList.add('cart-badge-animate');
            setTimeout(() => this.countBadge.classList.remove('cart-badge-animate'), 300);
        }
        
        // Show/hide empty state and footer
        if (count === 0) {
            if (this.emptyState) this.emptyState.style.display = 'block';
            if (this.itemsContainer) this.itemsContainer.innerHTML = '';
            if (this.footer) this.footer.style.display = 'none';
            return;
        }
        
        if (this.emptyState) this.emptyState.style.display = 'none';
        if (this.footer) this.footer.style.display = 'block';
        
        // Render cart items
        if (this.itemsContainer) {
            this.itemsContainer.innerHTML = cart.map(item => this.createCartItemHTML(item)).join('');
            
            // Add event listeners
            this.itemsContainer.querySelectorAll('.quantity-decrease').forEach(btn => {
                btn.addEventListener('click', () => {
                    const productId = btn.dataset.productId;
                    const currentQty = parseInt(btn.closest('.side-cart-item').querySelector('.quantity-input').value);
                    this.updateQuantity(productId, currentQty - 1);
                });
            });
            
            this.itemsContainer.querySelectorAll('.quantity-increase').forEach(btn => {
                btn.addEventListener('click', () => {
                    const productId = btn.dataset.productId;
                    const currentQty = parseInt(btn.closest('.side-cart-item').querySelector('.quantity-input').value);
                    this.updateQuantity(productId, currentQty + 1);
                });
            });
            
            this.itemsContainer.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', () => {
                    const productId = input.dataset.productId;
                    const quantity = parseInt(input.value);
                    if (quantity > 0) {
                        this.updateQuantity(productId, quantity);
                    } else {
                        input.value = 1;
                        this.updateQuantity(productId, 1);
                    }
                });
            });
            
            this.itemsContainer.querySelectorAll('.remove-cart-item').forEach(btn => {
                btn.addEventListener('click', () => {
                    const productId = btn.dataset.productId;
                    this.removeFromCart(productId);
                });
            });
        }
        
        // Update totals
        this.updateTotals(cart);
    }
    
    createCartItemHTML(item) {
        const imageUrl = item.imagePath 
            ? `../../assets/uploads/products/${item.imagePath}` 
            : '../../assets/images/placeholder-product.jpg';
        
        const subtotal = item.productPrice * item.quantity;
        
        return `
            <div class="side-cart-item">
                <img src="${imageUrl}" alt="${this.escapeHtml(item.productName)}" 
                     class="side-cart-item-image" 
                     onerror="this.src='../../assets/images/placeholder-product.jpg'">
                <div class="side-cart-item-details">
                    <div class="side-cart-item-name">${this.escapeHtml(item.productName)}</div>
                    <div class="side-cart-item-price">₦${item.productPrice.toLocaleString('en-NG')}</div>
                    <div class="side-cart-item-unit">per ${this.escapeHtml(item.productUnit)}</div>
                    <div class="side-cart-item-actions">
                        <div class="quantity-control">
                            <button class="quantity-decrease" data-product-id="${item.productId}">−</button>
                            <input type="number" class="quantity-input" value="${item.quantity}" 
                                   min="1" data-product-id="${item.productId}" readonly>
                            <button class="quantity-increase" data-product-id="${item.productId}">+</button>
                        </div>
                        <span class="ms-2 fw-bold text-success">
                            ₦${subtotal.toLocaleString('en-NG')}
                        </span>
                        <button class="remove-cart-item ms-auto" data-product-id="${item.productId}" 
                                title="Remove item">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    updateTotals(cart) {
        const subtotal = cart.reduce((sum, item) => sum + (item.productPrice * item.quantity), 0);
        const total = subtotal + this.shipping;
        
        if (this.subtotalEl) this.subtotalEl.textContent = `₦${subtotal.toLocaleString('en-NG')}`;
        if (this.shippingEl) this.shippingEl.textContent = `₦${this.shipping.toLocaleString('en-NG')}`;
        if (this.totalEl) this.totalEl.textContent = `₦${total.toLocaleString('en-NG')}`;
    }
    
    updateGlobalCount() {
        const cart = this.getLocalCart();
        const cartCountElements = document.querySelectorAll('#cart-count, #sideCartCount, .cart-count-badge');
        cartCountElements.forEach(el => {
            el.textContent = cart.length;
        });
        
        // Also update navbar cart count
        const navbarCount = document.getElementById('cart-count');
        if (navbarCount) {
            navbarCount.textContent = cart.length;
        }
    }
    
    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    showToast(message, type = 'info') {
        // We'll keep a minimal toast for errors only
        if (type === 'error') {
            const toast = document.createElement('div');
            const bgColor = '#dc3545';
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${bgColor};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                transform: translateX(400px);
                transition: transform 0.3s ease;
            `;
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <span>${message}</span>
                    <button class="btn-close btn-close-white ms-3" style="font-size: 10px;" 
                            onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.style.transform = 'translateX(0)', 100);
            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    }
}

// Initialize side cart when DOM is ready
// Initialize side cart when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Get CSRF token - try multiple sources
    let csrfToken = '';
    
    // Try meta tag first
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        csrfToken = metaToken.getAttribute('content');
    }
    
    // Try hidden input field
    if (!csrfToken) {
        const hiddenInput = document.querySelector('input[name="csrf_token"]');
        if (hiddenInput) {
            csrfToken = hiddenInput.value;
        }
    }
    
    // Try PHP session variable passed to JavaScript
    if (!csrfToken && typeof window.CSRF_TOKEN !== 'undefined') {
        csrfToken = window.CSRF_TOKEN;
    }
    
    window.sideCart = new SideCart({
        cartKey: 'greenagric_cart',
        apiBase: '../../api/cart',
        csrfToken: csrfToken,
        isLoggedIn: <?php echo isLoggedIn() ? 'true' : 'false'; ?>,
        shipping: 500,
        onCartUpdate: function() {
            // Update any additional UI elements when cart changes
            const event = new CustomEvent('cartUpdated');
            document.dispatchEvent(event);
        }
    });
    
    // Log initialization status
    console.log('Side cart initialized:', {
        isLoggedIn: <?php echo isLoggedIn() ? 'true' : 'false'; ?>,
        hasCsrfToken: !!csrfToken,
        tokenLength: csrfToken ? csrfToken.length : 0
    });
});
</script>