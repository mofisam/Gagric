<?php
// buyer/cart/payment.php - UPDATED WITH POPUP METHOD
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';
require_once '../../config/paystack.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

// Get order number from URL or session
$order_number = $_GET['order_number'] ?? ($_SESSION['pending_order'] ?? null);

if (!$order_number) {
    setFlashMessage('No order found to process payment', 'error');
    header('Location: ../orders/');
    exit;
}

// Get order details
$order_details = $order->getOrder($order_number, $user_id);
if (!$order_details) {
    setFlashMessage('Order not found or you do not have permission', 'error');
    header('Location: ../orders/');
    exit;
}

// Check if order is already paid
if ($order_details['payment_status'] === 'paid') {
    setFlashMessage('This order has already been paid', 'info');
    header("Location: ../orders/order-details.php?order_number=" . $order_number);
    exit;
}

// Get user information for payment
$user = $db->fetchOne("SELECT email, first_name, last_name FROM users WHERE id = ?", [$user_id]);

// Handle AJAX payment initialization for Popup method
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initialize_payment') {
    header('Content-Type: application/json');
    
    try {
        // Generate unique reference for Paystack
        $paystack_reference = 'PAY_' . time() . '_' . $order_number;
        
        // Initialize Paystack transaction - UPDATED: Add callback_url for redirect method fallback
        $response = PaystackAPI::initializeTransaction(
            $user['email'],
            $order_details['total_amount'],
            $paystack_reference
        );
        
        if (!isset($response['status'])) {
            throw new Exception('Invalid response from Paystack API');
        }
        
        if (!$response['status']) {
            $errorMsg = $response['message'] ?? 'Failed to initialize payment';
            if (isset($response['data']['message'])) {
                $errorMsg .= ': ' . $response['data']['message'];
            }
            throw new Exception($errorMsg);
        }
        
        // Check if access_code exists (required for Popup method)
        if (!isset($response['data']['access_code'])) {
            throw new Exception('No access code received from Paystack');
        }
        
        // Store payment reference in database before showing popup
        $payment_id = $db->insert('payments', [
            'order_id' => $order_details['id'],
            'paystack_reference' => $paystack_reference,
            'amount' => $order_details['total_amount'],
            'currency' => defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'NGN',
            'status' => 'pending',
            'customer_email' => $user['email'],
            'customer_name' => $user['first_name'] . ' ' . $user['last_name'],
            'paystack_response' => json_encode($response)
        ]);
        
        // Store payment ID and reference in session for verification
        $_SESSION['current_payment_id'] = $payment_id;
        $_SESSION['current_payment_reference'] = $paystack_reference;
        
        // Return success with access_code for Popup
        echo json_encode([
            'success' => true,
            'access_code' => $response['data']['access_code'],
            'reference' => $paystack_reference,
            'payment_id' => $payment_id
        ]);
        
    } catch (Exception $e) {
        error_log("Payment initialization error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle Paystack callback (after payment) - BOTH Popup and Redirect methods
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    
    // Verify the transaction
    $verification = PaystackAPI::verifyTransaction($reference);
    
    if ($verification['status'] && isset($verification['data']['status']) && $verification['data']['status'] === 'success') {
        $payment_data = $verification['data'];
        
        // Update payment record
        $db->update('payments', [
            'status' => 'success',
            'paystack_authorization_code' => $payment_data['authorization']['authorization_code'] ?? null,
            'payment_method' => $payment_data['channel'] ?? 'card',
            'paystack_response' => json_encode(array_merge(
                json_decode($payment['paystack_response'] ?? '{}', true) ?? [],
                ['verification' => $verification]
            )),
            'paid_at' => date('Y-m-d H:i:s')
        ], 'paystack_reference = ?', [$reference]);
        
        // Update order payment status
        $db->update('orders', [
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'paid_at' => date('Y-m-d H:i:s')
        ], 'order_number = ?', [$order_number]);
        
        // Clear pending order from session
        unset($_SESSION['pending_order'], $_SESSION['current_payment_id'], $_SESSION['current_payment_reference']);
        
        // Set success flag for display
        $payment_success = true;
        $payment_reference = $reference;
        
        // Log activity
        logActivity($user_id, 'payment_success', "Order: {$order_number}, Amount: " . formatCurrency($order_details['total_amount']));
        
    } else {
        $error = $verification['message'] ?? 'Payment verification failed';
        
        // Update payment record as failed
        $db->update('payments', [
            'status' => 'failed',
            'paystack_response' => json_encode(array_merge(
                json_decode($payment['paystack_response'] ?? '{}', true) ?? [],
                ['verification' => $verification]
            ))
        ], 'paystack_reference = ?', [$reference]);
        
        // Log activity
        logActivity($user_id, 'payment_failed', "Order: {$order_number}, Error: {$error}");
        
        setFlashMessage('Payment verification failed: ' . $error, 'error');
    }
}

$page_title = "Payment - Order #{$order_number}";
include '../../includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($payment_success) && $payment_success): ?>
        <!-- Success Message -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i> Payment Successful!</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Thank You!</h3>
                            <p class="lead">Payment successful! Your order #<?php echo $order_number; ?> has been confirmed.</p>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5>Payment Details</h5>
                                <div class="row text-start">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Order Number:</strong></p>
                                        <p class="mb-1"><strong>Payment Reference:</strong></p>
                                        <p class="mb-1"><strong>Amount Paid:</strong></p>
                                        <p class="mb-1"><strong>Date:</strong></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><?php echo $order_number; ?></p>
                                        <p class="mb-1"><?php echo htmlspecialchars($payment_reference); ?></p>
                                        <p class="mb-1"><?php echo formatCurrency($order_details['total_amount']); ?></p>
                                        <p class="mb-1"><?php echo date('F j, Y g:i A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-center">
                            <a href="../orders/order-details.php?order_number=<?php echo $order_number; ?>" 
                               class="btn btn-success btn-lg">
                                <i class="bi bi-eye me-2"></i> View Order Details
                            </a>
                            <a href="../orders/" class="btn btn-outline-success btn-lg">
                                <i class="bi bi-list me-2"></i> View All Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Payment Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-credit-card me-2"></i> Complete Your Payment</h4>
                    </div>
                    <div class="card-body">
                        <!-- Payment Status Messages -->
                        <div id="paymentMessage" style="display: none;"></div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Click "Pay Securely" to open a secure payment popup. You can pay with card, bank transfer, or USSD.
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Order Summary</h5>
                                <div class="mb-2">
                                    <small class="text-muted">Order Number</small><br>
                                    <strong><?php echo $order_number; ?></strong>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Order Date</small><br>
                                    <strong><?php echo formatDate($order_details['created_at']); ?></strong>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Buyer</small><br>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Payment Summary</h5>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Subtotal:</span>
                                    <span><?php echo formatCurrency($order_details['subtotal_amount']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Shipping:</span>
                                    <span><?php echo formatCurrency($order_details['shipping_amount']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Tax:</span>
                                    <span>₦0.00</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <h5>Total:</h5>
                                    <h4 class="text-success"><?php echo formatCurrency($order_details['total_amount']); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5>Payment Method</h5>
                            <div class="card border-success">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <img src="../../assets/images/paystack-logo.png" height="40" class="me-3" alt="Paystack">
                                        <div>
                                            <h6 class="mb-1">Paystack Secure Payment</h6>
                                            <p class="text-muted mb-0">Pay with card, bank transfer, or USSD</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms Agreement -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I agree to the <a href="#" class="text-success">Terms & Conditions</a> and authorize this payment.
                            </label>
                        </div>
                        
                        <!-- Payment Button -->
                        <div class="d-grid gap-2">
                            <button type="button" id="payButton" class="btn btn-success btn-lg">
                                <i class="bi bi-lock-fill me-2"></i> Pay Securely with Paystack
                            </button>
                            <a href="checkout.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i> Back to Checkout
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i> Secure Payment Guarantee</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <i class="bi bi-lock-fill text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">256-bit SSL Secure</h6>
                                <small class="text-muted">Your payment is encrypted and secure</small>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="bi bi-credit-card text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Multiple Payment Options</h6>
                                <small class="text-muted">Card, Bank Transfer, USSD</small>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="bi bi-headset text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">24/7 Support</h6>
                                <small class="text-muted">Contact us for payment issues</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Details Sidebar -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Need Help?</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><i class="bi bi-envelope me-2"></i> <strong>Email:</strong> support@greenagric.ng</p>
                        <p class="mb-2"><i class="bi bi-telephone me-2"></i> <strong>Phone:</strong> +234 703 041 9150</p>
                        <p class="mb-0"><i class="bi bi-clock me-2"></i> <strong>Hours:</strong> Mon-Fri, 9AM-6PM</p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Payment Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Keep this browser window open</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Complete payment in the popup window</li>
                            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> You'll be redirected back automatically</li>
                            <li class="mb-0"><i class="bi bi-check-circle text-success me-2"></i> Save your payment reference</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Paystack InlineJS Library (Popup V2) -->
<script src="https://js.paystack.co/v2/inline.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const payButton = document.getElementById('payButton');
    const agreeTerms = document.getElementById('agreeTerms');
    const paymentMessage = document.getElementById('paymentMessage');
    
    // Function to show messages
    function showMessage(message, type = 'success') {
        paymentMessage.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        paymentMessage.style.display = 'block';
    }
    
    // Handle payment button click
    payButton.addEventListener('click', async function() {
        // Validate terms agreement
        if (!agreeTerms.checked) {
            showMessage('Please agree to the Terms & Conditions', 'danger');
            agreeTerms.focus();
            return;
        }
        
        // Disable button and show loading state
        const originalText = payButton.innerHTML;
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
        
        try {
            // 1. Initialize payment with backend to get access_code
            const formData = new FormData();
            formData.append('action', 'initialize_payment');
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Failed to initialize payment');
            }
            
            // 2. Use Paystack Popup with the access_code
            const popup = new PaystackPop();
            
            popup.resumeTransaction(result.access_code, {
                // Callback when payment is successful
                onSuccess: (transaction) => {
                    console.log('Payment successful:', transaction);
                    
                    // Show success message
                    showMessage('Payment successful! Redirecting to confirmation...', 'success');
                    
                    // Redirect to payment success page with reference
                    setTimeout(() => {
                        window.location.href = `payment.php?order_number=<?php echo $order_number; ?>&reference=${transaction.reference}`;
                    }, 1500);
                },
                
                // Callback when user closes the popup
                onCancel: () => {
                    console.log('Payment cancelled by user');
                    showMessage('Payment was cancelled. You can try again.', 'warning');
                    
                    // Reset button
                    payButton.disabled = false;
                    payButton.innerHTML = originalText;
                },
                
                // Callback for errors
                onError: (error) => {
                    console.error('Payment error:', error);
                    showMessage('Payment failed: ' + (error.message || 'Unknown error'), 'danger');
                    
                    // Reset button
                    payButton.disabled = false;
                    payButton.innerHTML = originalText;
                }
            });
            
        } catch (error) {
            console.error('Error:', error);
            showMessage('Error: ' + error.message, 'danger');
            
            // Reset button
            payButton.disabled = false;
            payButton.innerHTML = originalText;
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>