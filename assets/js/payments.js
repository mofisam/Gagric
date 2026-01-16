// Payment related JavaScript
class PaymentManager {
    constructor() {
        this.initializePaystack();
        this.setupPaymentForm();
    }

    initializePaystack() {
        // Paystack inline integration
        if (typeof PaystackPop !== 'undefined') {
            window.paystack = PaystackPop.setup({
                key: PAYSTACK_PUBLIC_KEY, // From constants
                onClose: () => {
                    agriApp.showToast('Payment cancelled', 'warning');
                }
            });
        }
    }

    setupPaymentForm() {
        const paymentForm = document.querySelector('#payment-form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.processPayment(paymentForm);
            });
        }
    }

    async processPayment(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData(form);
            const paymentData = {
                email: formData.get('email'),
                amount: parseFloat(formData.get('amount')),
                order_id: formData.get('order_id')
            };

            const response = await fetch('/api/payments/initialize.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(paymentData)
            });

            const result = await response.json();

            if (result.success) {
                // Redirect to Paystack
                window.location.href = result.authorization_url;
            } else {
                throw new Error(result.error || 'Payment initialization failed');
            }

        } catch (error) {
            console.error('Payment error:', error);
            agriApp.showToast(error.message, 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    // Verify payment after redirect
    async verifyPayment(reference) {
        try {
            const response = await fetch(`/api/payments/verify.php?reference=${reference}`);
            const result = await response.json();

            if (result.success) {
                agriApp.showToast('Payment successful!', 'success');
                // Redirect to order confirmation
                setTimeout(() => {
                    window.location.href = '/orders/confirmation.php';
                }, 2000);
            } else {
                throw new Error('Payment verification failed');
            }
        } catch (error) {
            console.error('Verification error:', error);
            agriApp.showToast('Payment verification failed', 'error');
        }
    }

    // Calculate shipping costs
    async calculateShipping(origin, destination, weight) {
        try {
            const response = await fetch(
                `/api/logistics/calculate-shipping.php?origin=${origin}&destination=${destination}&weight=${weight}`
            );
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Shipping calculation error:', error);
            return { cost: 0, estimated_days: 3 };
        }
    }

    // Update order summary
    updateOrderSummary(subtotal, shipping, tax = 0) {
        const subtotalEl = document.querySelector('#order-subtotal');
        const shippingEl = document.querySelector('#order-shipping');
        const taxEl = document.querySelector('#order-tax');
        const totalEl = document.querySelector('#order-total');

        if (subtotalEl) subtotalEl.textContent = agriApp.formatCurrency(subtotal);
        if (shippingEl) shippingEl.textContent = agriApp.formatCurrency(shipping);
        if (taxEl) taxEl.textContent = agriApp.formatCurrency(tax);
        if (totalEl) totalEl.textContent = agriApp.formatCurrency(subtotal + shipping + tax);
    }
}

// Auto-verify payment if reference exists in URL
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const reference = urlParams.get('reference');
    
    if (reference) {
        const paymentManager = new PaymentManager();
        paymentManager.verifyPayment(reference);
    }

    // Initialize payment manager on payment pages
    if (document.querySelector('#payment-form')) {
        new PaymentManager();
    }
});