// Cart page specific functionality
class CartPage {
    constructor() {
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.updateTotals();
    }
    
    bindEvents() {
        // Quantity changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', (e) => {
                this.updateItemTotal(e.target);
                this.updateTotals();
            });
        });
        
        // Remove item buttons
        document.querySelectorAll('.remove-item-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                this.removeItem(e.target.closest('tr'));
            });
        });
    }
    
    updateItemTotal(input) {
        const row = input.closest('tr');
        const price = parseFloat(row.querySelector('.item-price').textContent.replace(/[^0-9.-]+/g, ''));
        const quantity = parseInt(input.value);
        const total = price * quantity;
        
        row.querySelector('.item-total').textContent = '₦' + total.toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    removeItem(row) {
        if (confirm('Remove this item from cart?')) {
            row.style.opacity = '0.5';
            setTimeout(() => {
                row.remove();
                this.updateTotals();
                this.checkEmptyCart();
            }, 300);
        }
    }
    
    updateTotals() {
        let subtotal = 0;
        
        document.querySelectorAll('.item-total').forEach(element => {
            const total = parseFloat(element.textContent.replace(/[^0-9.-]+/g, ''));
            if (!isNaN(total)) {
                subtotal += total;
            }
        });
        
        const shipping = 500;
        const tax = 0;
        const total = subtotal + shipping + tax;
        
        // Update display
        const subtotalEl = document.getElementById('subtotal');
        const totalEl = document.getElementById('total');
        
        if (subtotalEl) subtotalEl.textContent = '₦' + subtotal.toLocaleString('en-NG');
        if (totalEl) totalEl.textContent = '₦' + total.toLocaleString('en-NG');
    }
    
    checkEmptyCart() {
        const rows = document.querySelectorAll('tbody tr');
        if (rows.length === 0) {
            document.querySelector('.table-responsive').innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-cart-x text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">Your cart is empty</h4>
                    <p class="text-muted">Add some fresh agricultural products to get started!</p>
                    <a href="../products/browse.php" class="btn btn-success">Browse Products</a>
                </div>
            `;
        }
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.quantity-input')) {
        new CartPage();
    }
});