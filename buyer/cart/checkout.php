<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

// Get user data for email
$user = $db->fetchOne("SELECT email, first_name, last_name FROM users WHERE id = ?", [$user_id]);

// Get user addresses
$addresses = $db->fetchAll("
    SELECT ua.*, s.name as state_name, l.name as lga_name, c.name as city_name 
    FROM user_addresses ua 
    JOIN states s ON ua.state_id = s.id 
    JOIN lgas l ON ua.lga_id = l.id 
    JOIN cities c ON ua.city_id = c.id 
    WHERE ua.user_id = ? 
    ORDER BY ua.is_default DESC
", [$user_id]);

// Get cart items
$cart_items = $db->fetchAll("
    SELECT c.*, p.name, p.price_per_unit, p.unit, p.stock_quantity 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ? AND p.status = 'approved'
", [$user_id]);

if (empty($cart_items)) {
    setFlashMessage('Your cart is empty', 'error');
    header('Location: view-cart.php');
    exit;
}

// Validate stock before checkout
foreach ($cart_items as $item) {
    if ($item['stock_quantity'] < $item['quantity']) {
        setFlashMessage("{$item['name']} only has {$item['stock_quantity']} units in stock", 'error');
        header('Location: view-cart.php');
        exit;
    }
}

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price_per_unit'] * $item['quantity'];
}

// Get shipping address from session or default
$selected_address_id = $_SESSION['checkout_address_id'] ?? null;
$shipping_cost = 500; // Default shipping

$total = $subtotal + $shipping_cost;

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address_id = $_POST['address_id'] ?? null;
    $payment_method = $_POST['payment_method'] ?? 'paystack';
    $notes = $_POST['notes'] ?? '';
    
    if (!$address_id) {
        setFlashMessage('Please select a shipping address', 'error');
    } else {
        // Get selected address
        $selected_address = null;
        foreach ($addresses as $addr) {
            if ($addr['id'] == $address_id) {
                $selected_address = $addr;
                break;
            }
        }
        
        if ($selected_address) {
            // Prepare cart items for order creation
            $order_items = [];
            foreach ($cart_items as $item) {
                $order_items[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity']
                ];
            }
            
            // Create order
            try {
                $shipping_info = [
                    'shipping_name' => $selected_address['contact_person'] ?? $_SESSION['user_name'],
                    'shipping_phone' => $selected_address['phone'],
                    'state_id' => $selected_address['state_id'],
                    'lga_id' => $selected_address['lga_id'],
                    'city_id' => $selected_address['city_id'],
                    'address_line' => $selected_address['address_line'],
                    'landmark' => $selected_address['landmark'] ?? '',
                    'shipping_instructions' => $notes
                ];
                
                $order_number = $order->create($user_id, $order_items, $shipping_info);
                
                // Clear cart
                $db->query("DELETE FROM cart WHERE user_id = ?", [$user_id]);
                
                // Store order number in session for payment
                $_SESSION['pending_order'] = $order_number;
                
                // Redirect to payment page with order details
                header("Location: payment.php?order_number=" . $order_number);
                exit;
                
            } catch (Exception $e) {
                $error = $e->getMessage();
                error_log("Checkout error: " . $e->getMessage());
            }
        }
    }
}
?>
<?php 
$page_title = "Checkout";
$page_css = 'cart.css';
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <h2 class="mb-4">Checkout</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="checkoutForm">
        <div class="row">
            <!-- Left Column - Shipping & Payment -->
            <div class="col-lg-8">
                <!-- Shipping Address -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i> Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($addresses)): ?>
                            <div class="alert alert-warning">
                                You haven't saved any addresses yet. 
                                <a href="../profile/addresses.php?checkout=1" class="alert-link">Add an address</a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($addresses as $address): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border h-100 <?php echo $address['id'] == $selected_address_id ? 'border-success' : ''; ?>">
                                            <div class="card-body">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" 
                                                           name="address_id" id="address<?php echo $address['id']; ?>" 
                                                           value="<?php echo $address['id']; ?>" 
                                                           <?php echo $address['id'] == $selected_address_id || $address['is_default'] ? 'checked' : ''; ?>
                                                           required>
                                                    <label class="form-check-label w-100" for="address<?php echo $address['id']; ?>">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($address['address_label'] ?? 'Address'); ?></h6>
                                                        <p class="text-muted mb-1">
                                                            <?php echo htmlspecialchars($address['address_line']); ?><br>
                                                            <?php echo htmlspecialchars($address['city_name'] . ', ' . $address['lga_name'] . ', ' . $address['state_name']); ?>
                                                        </p>
                                                        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($address['contact_person'] . ' - ' . $address['phone']); ?></p>
                                                        <?php if ($address['is_default']): ?>
                                                            <span class="badge bg-success">Default</span>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-end mt-3">
                                <a href="../profile/addresses.php?checkout=1" class="btn btn-outline-success">
                                    <i class="bi bi-plus-circle me-1"></i> Add New Address
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shipping Notes -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Delivery Instructions</h5>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="Any special delivery instructions? (e.g., call before delivery, leave at security)"></textarea>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i> Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="paystack" value="paystack" checked required>
                            <label class="form-check-label w-100" for="paystack">
                                <div class="d-flex align-items-center">
                                    <img src="../../assets/images/paystack-logo.png" height="30" class="me-3" alt="Paystack">
                                    <div>
                                        <h6 class="mb-1">Paystack Payment</h6>
                                        <p class="text-muted mb-0">Pay with card, bank transfer, or USSD</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Order Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <!-- Cart Items -->
                        <div class="mb-3">
                            <h6>Order Items</h6>
                            <?php foreach ($cart_items as $item): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <small><?php echo htmlspecialchars($item['name']); ?></small>
                                        <br><small class="text-muted"><?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['price_per_unit']); ?></small>
                                    </div>
                                    <small class="text-success"><?php echo formatCurrency($item['price_per_unit'] * $item['quantity']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <hr>
                        
                        <!-- Totals -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($subtotal); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Shipping</span>
                                <span><?php echo formatCurrency($shipping_cost); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Tax</span>
                                <span>₦0.00</span>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <h5>Total</h5>
                            <h5 class="text-success"><?php echo formatCurrency($total); ?></h5>
                        </div>
                        
                        <!-- Terms -->
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="text-success">Terms & Conditions</a>
                            </label>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-success btn-lg w-100" id="submitBtn">
                            <i class="bi bi-lock me-2"></i> Complete Order & Pay
                        </button>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i> Your payment is secure and encrypted
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Add form validation and loading state
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    
    // Validate terms checkbox
    const terms = document.getElementById('terms');
    if (!terms.checked) {
        e.preventDefault();
        agriApp.showToast('Please agree to the Terms & Conditions', 'error');
        return;
    }
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Creating Order...';
});
</script>

<?php include '../../includes/footer.php'; ?>