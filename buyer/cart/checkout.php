<?php 
// buyer/cart/checkout.php - MULTI-SELLER VERSION (MATCHING YOUR DB SCHEMA)

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user data
$user = $db->fetchOne("SELECT email, first_name, last_name, phone FROM users WHERE id = ?", [$user_id]);

// Get user addresses
$addresses = $db->fetchAll("
    SELECT ua.*, s.name as state_name, l.name as lga_name
    FROM user_addresses ua
    JOIN states s ON ua.state_id = s.id
    JOIN lgas l ON ua.lga_id = l.id
    WHERE ua.user_id = ?
    ORDER BY ua.is_default DESC
", [$user_id]);

// Get cart items with seller information
$cart_items = $db->fetchAll("
    SELECT 
        c.*, 
        p.name as product_name, 
        p.description as product_description,
        p.price_per_unit as unit_price, 
        p.unit, 
        p.stock_quantity,
        p.seller_id,
        pad.grade,
        pad.is_organic,
        pad.harvest_date,
        u.first_name as seller_first_name,
        u.last_name as seller_last_name,
        u.email as seller_email,
        u.phone as seller_phone,
        sp.business_name
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    JOIN users u ON p.seller_id = u.id
    LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id
    LEFT JOIN seller_profiles sp ON p.seller_id = sp.user_id
    WHERE c.user_id = ? AND p.status = 'approved'
", [$user_id]);

if (empty($cart_items)) {
    setFlashMessage('Your cart is empty', 'error');
    header('Location: view-cart.php');
    exit;
}

// Group cart items by seller
$cart_by_seller = [];
foreach ($cart_items as $item) {
    $seller_id = $item['seller_id'];
    if (!isset($cart_by_seller[$seller_id])) {
        $cart_by_seller[$seller_id] = [
            'seller_info' => [
                'seller_id' => $seller_id,
                'seller_name' => !empty($item['business_name']) ? $item['business_name'] : ($item['seller_first_name'] . ' ' . $item['seller_last_name']),
                'seller_email' => $item['seller_email'],
                'seller_phone' => $item['seller_phone']
            ],
            'items' => [],
            'subtotal' => 0
        ];
    }
    $cart_by_seller[$seller_id]['items'][] = $item;
    $cart_by_seller[$seller_id]['subtotal'] += $item['unit_price'] * $item['quantity'];
}

// Validate stock before checkout
foreach ($cart_by_seller as $seller_data) {
    foreach ($seller_data['items'] as $item) {
        if ($item['stock_quantity'] < $item['quantity']) {
            setFlashMessage("{$item['product_name']} (from {$seller_data['seller_info']['seller_name']}) only has {$item['stock_quantity']} units in stock", 'error');
            header('Location: view-cart.php');
            exit;
        }
    }
}

// Calculate totals
$global_subtotal = 0;
foreach ($cart_by_seller as $seller_data) {
    $global_subtotal += $seller_data['subtotal'];
}

$shipping_per_seller = 500;
$global_shipping = count($cart_by_seller) * $shipping_per_seller;
$global_total = $global_subtotal + $global_shipping;

$selected_address_id = $_SESSION['checkout_address_id'] ?? null;

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setFlashMessage('Invalid security token', 'error');
        header('Location: checkout.php');
        exit;
    }
    
    $address_id = $_POST['address_id'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    if (!$address_id) {
        setFlashMessage('Please select a shipping address', 'error');
    } else {
        $selected_address = null;
        foreach ($addresses as $addr) {
            if ($addr['id'] == $address_id) {
                $selected_address = $addr;
                break;
            }
        }    
        
        if ($selected_address) {
            try {
                $db->conn->begin_transaction();
                
                $created_orders = [];
                $all_order_numbers = [];
                
                foreach ($cart_by_seller as $seller_id => $seller_data) {
                    // Calculate seller amounts
                    $seller_subtotal = $seller_data['subtotal'];
                    $seller_shipping = $shipping_per_seller;
                    $seller_total = $seller_subtotal + $seller_shipping;
                    
                    // Generate unique order number
                    $seller_order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    
                    // Insert order
                    $order_data = [
                        'order_number' => $seller_order_number,
                        'buyer_id' => $user_id,
                        'payment_id' => null,
                        'subtotal_amount' => $seller_subtotal,
                        'shipping_amount' => $seller_shipping,
                        'tax_amount' => 0,
                        'discount_amount' => 0,
                        'total_amount' => $seller_total,
                        'status' => 'pending',
                        'payment_status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $order_id = $db->insert('orders', $order_data);
                    
                    if (!$order_id) {
                        throw new Exception("Failed to create order");
                    }
                    
                    // Insert order items with correct column names
                    foreach ($seller_data['items'] as $item) {
                        $item_total = $item['unit_price'] * $item['quantity'];
                        
                        $item_data = [
                            'order_id' => $order_id,
                            'product_id' => $item['product_id'],
                            'seller_id' => $seller_id,
                            'product_name' => $item['product_name'],
                            'product_description' => $item['product_description'],
                            'unit_price' => $item['unit_price'],
                            'quantity' => $item['quantity'],
                            'unit' => $item['unit'],
                            'grade' => $item['grade'],
                            'is_organic' => $item['is_organic'],
                            'harvest_date' => $item['harvest_date'],
                            'item_total' => $item_total,
                            'status' => 'pending'
                        ];
                        
                        $item_inserted = $db->insert('order_items', $item_data);
                        
                        if (!$item_inserted) {
                            throw new Exception("Failed to add item to order");
                        }
                        
                        // Update stock
                        $db->query("
                            UPDATE products 
                            SET stock_quantity = stock_quantity - ? 
                            WHERE id = ? AND stock_quantity >= ?
                        ", [$item['quantity'], $item['product_id'], $item['quantity']]);
                    }
                    
                    // Insert shipping details
                    $shipping_data = [
                        'order_id' => $order_id,
                        'shipping_name' => $selected_address['contact_person'] ?? $user['first_name'] . ' ' . $user['last_name'],
                        'shipping_phone' => $selected_address['phone'],
                        'state_id' => $selected_address['state_id'],
                        'lga_id' => $selected_address['lga_id'],
                        'city' => $selected_address['city'],
                        'address_line' => $selected_address['address_line'],
                        'landmark' => $selected_address['landmark'] ?? '',
                        'shipping_instructions' => $notes,
                        'estimated_delivery' => date('Y-m-d', strtotime('+5 days')),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->insert('order_shipping_details', $shipping_data);
                    
                    $created_orders[] = [
                        'order_id' => $order_id,
                        'order_number' => $seller_order_number,
                        'seller_name' => $seller_data['seller_info']['seller_name'],
                        'total' => $seller_total
                    ];
                    
                    $all_order_numbers[] = $seller_order_number;
                }
                
                // Clear cart
                $db->query("DELETE FROM cart WHERE user_id = ?", [$user_id]);
                
                $db->conn->commit();
                
                // After commit(), before the header redirect:
                echo "<script>localStorage.removeItem('greenagric_cart'); if(typeof cartManager!=='undefined') cartManager.clearCart();</script>";

                $_SESSION['pending_orders'] = $all_order_numbers;
                $_SESSION['order_count'] = count($created_orders);
                $_SESSION['payment_total'] = $global_total;
                
                setFlashMessage(count($created_orders) . " order(s) created successfully! Proceed to payment.", 'success');
                header("Location: payment.php?orders=" . implode(',', $all_order_numbers));
                exit;
                
            } catch (Exception $e) {
                $db->conn->rollback();
                error_log("Checkout error: " . $e->getMessage());
                setFlashMessage("Checkout failed: " . $e->getMessage(), 'error');
            }
        }
    }
}

$page_title = "Checkout";
$page_css = 'cart.css';
include '../../includes/header.php'; 
?>

<style>
.checkout-step {
    position: relative;
    padding-left: 45px;
    margin-bottom: 30px;
}
.checkout-step .step-number {
    position: absolute;
    left: 0;
    top: 0;
    width: 32px;
    height: 32px;
    background: #198754;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
.checkout-step .step-title {
    font-weight: 600;
    margin-bottom: 15px;
    color: #2c3e50;
}
.seller-card {
    border-left: 4px solid #198754;
    transition: all 0.3s ease;
}
.seller-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.seller-badge {
    background: #e8f5e9;
    color: #2e7d32;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.product-image-sm {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}
.organic-badge {
    background: #4caf50;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 5px;
}
.grade-badge {
    background: #ff9800;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 5px;
}
.summary-card {
    position: sticky;
    top: 20px;
}
.address-card {
    cursor: pointer;
    transition: all 0.2s ease;
}
.address-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.address-card.selected {
    border: 2px solid #198754;
    background: #f8fff9;
}
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.loading-overlay.active {
    display: flex;
}
.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #198754;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <p class="text-white mt-3">Creating your orders...</p>
</div>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">Complete Your Order</h2>
            <p class="text-muted">Review your items and complete the checkout process</p>
        </div>
    </div>
    
    <?php if (count($cart_by_seller) > 1): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i>
            <strong>Multi-Seller Order:</strong> Your cart contains items from 
            <strong><?php echo count($cart_by_seller); ?> different sellers</strong>. 
            You'll create separate orders for each seller.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="checkoutForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Step 1: Shipping Address -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="checkout-step">
                            <div class="step-number">1</div>
                            <div class="step-title">Shipping Address</div>
                            
                            <?php if (empty($addresses)): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-geo-alt-fill me-2"></i>
                                    You haven't saved any addresses yet. 
                                    <a href="../profile/addresses.php?checkout=1" class="alert-link">Click here to add an address</a>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($addresses as $address): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card address-card h-100 <?php echo ($address['id'] == $selected_address_id || $address['is_default']) ? 'selected border-success' : ''; ?>" 
                                                 onclick="selectAddress(<?php echo $address['id']; ?>)">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="radio" 
                                                               name="address_id" 
                                                               id="address<?php echo $address['id']; ?>" 
                                                               value="<?php echo $address['id']; ?>" 
                                                               <?php echo ($address['id'] == $selected_address_id || $address['is_default']) ? 'checked' : ''; ?>
                                                               required>
                                                        <label class="form-check-label w-100" for="address<?php echo $address['id']; ?>">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1">
                                                                        <?php echo htmlspecialchars($address['address_label'] ?? 'Shipping Address'); ?>
                                                                        <?php if ($address['is_default']): ?>
                                                                            <span class="badge bg-success ms-2">Default</span>
                                                                        <?php endif; ?>
                                                                    </h6>
                                                                    <p class="text-muted mb-1 small">
                                                                        <?php echo htmlspecialchars($address['address_line']); ?><br>
                                                                        <?php echo htmlspecialchars($address['city'] . ', ' . $address['lga_name'] . ', ' . $address['state_name']); ?>
                                                                    </p>
                                                                    <div class="mt-2">
                                                                        <small class="text-muted">
                                                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($address['contact_person'] ?? 'Contact Person'); ?><br>
                                                                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($address['phone']); ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                                <?php if ($address['id'] == $selected_address_id || $address['is_default']): ?>
                                                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-end mt-3">
                                    <a href="../profile/addresses.php?checkout=1" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-plus-circle me-1"></i> Add New Address
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Order Items by Seller -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="checkout-step">
                            <div class="step-number">2</div>
                            <div class="step-title">Review Orders</div>
                            
                            <?php $orderCount = 1; ?>
                            <?php foreach ($cart_by_seller as $seller_id => $seller_data): ?>
                                <div class="seller-card card mb-4">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-shop text-success me-2"></i>
                                            <strong><?php echo htmlspecialchars($seller_data['seller_info']['seller_name']); ?></strong>
                                            <span class="seller-badge ms-2">Seller</span>
                                        </div>
                                        <span class="badge bg-secondary">Order #<?php echo $orderCount; ?></span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Price</th>
                                                        <th>Quantity</th>
                                                        <th class="text-end">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($seller_data['items'] as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                                        <?php if ($item['is_organic']): ?>
                                                                            <span class="organic-badge">Organic</span>
                                                                        <?php endif; ?>
                                                                        <?php if ($item['grade']): ?>
                                                                            <span class="grade-badge">Grade <?php echo $item['grade']; ?></span>
                                                                        <?php endif; ?>
                                                                        <br>
                                                                        <small class="text-muted">Unit: <?php echo htmlspecialchars($item['unit']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?php echo formatCurrency($item['unit_price']); ?></td>
                                                            <td>
                                                                <span class="badge bg-secondary">x<?php echo $item['quantity']; ?></span>
                                                            </td>
                                                            <td class="text-end text-success fw-bold">
                                                                <?php echo formatCurrency($item['unit_price'] * $item['quantity']); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                                        <td class="text-end"><strong><?php echo formatCurrency($seller_data['subtotal']); ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3" class="text-end"><small>Shipping:</small></td>
                                                        <td class="text-end"><small><?php echo formatCurrency($shipping_per_seller); ?></small></td>
                                                    </tr>
                                                    <tr class="table-success">
                                                        <td colspan="3" class="text-end"><strong>Order Total:</strong></td>
                                                        <td class="text-end"><strong class="fs-6"><?php echo formatCurrency($seller_data['subtotal'] + $shipping_per_seller); ?></strong></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <?php $orderCount++; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Delivery Instructions -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="checkout-step">
                            <div class="step-number">3</div>
                            <div class="step-title">Delivery Instructions (Optional)</div>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="e.g., Call before delivery, Leave at security gate, Delivery time preference..."></textarea>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-info-circle"></i> These instructions will apply to all orders from all sellers.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Order Summary -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-receipt me-2"></i> Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <!-- Order Breakdown -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Orders:</span>
                                    <strong><?php echo count($cart_by_seller); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Sellers:</span>
                                    <strong><?php echo count($cart_by_seller); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Items:</span>
                                    <strong><?php echo count($cart_items); ?></strong>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Price Breakdown -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <span><?php echo formatCurrency($global_subtotal); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping</span>
                                    <span><?php echo formatCurrency($global_shipping); ?></span>
                                    <small class="text-muted">(<?php echo count($cart_by_seller); ?> × ₦500)</small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Tax</span>
                                    <span>₦0.00</span>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Total -->
                            <div class="d-flex justify-content-between mb-4">
                                <h5 class="mb-0">Total to Pay</h5>
                                <h5 class="mb-0 text-success fw-bold"><?php echo formatCurrency($global_total); ?></h5>
                            </div>
                            
                            <!-- Per Seller Breakdown -->
                            <div class="mb-3">
                                <small class="text-muted d-block mb-2">Amount per seller:</small>
                                <?php foreach ($cart_by_seller as $seller_data): ?>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><?php echo htmlspecialchars(substr($seller_data['seller_info']['seller_name'], 0, 20)); ?></span>
                                        <span><?php echo formatCurrency($seller_data['subtotal'] + $shipping_per_seller); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <hr>
                            
                            <!-- Terms -->
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label small" for="terms">
                                    I confirm that all information is correct and agree to the 
                                    <a href="<?php echo BASE_URL; ?>/terms-and-conditions.php" class="text-success" >Terms & Conditions</a>
                                </label>
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-success btn-lg w-100" id="submitBtn">
                                <i class="bi bi-lock-fill me-2"></i>
                                Complete Order & Pay
                            </button>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-shield-check me-1"></i> Secure payment by Paystack
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Address selection function
function selectAddress(addressId) {
    document.querySelectorAll('input[name="address_id"]').forEach(radio => {
        radio.checked = (radio.value == addressId);
    });
    
    // Update card styles
    document.querySelectorAll('.address-card').forEach(card => {
        card.classList.remove('selected', 'border-success');
        const radio = card.querySelector('input');
        if (radio && radio.value == addressId) {
            card.classList.add('selected', 'border-success');
        }
    });
}

// Form submission with loading state
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const terms = document.getElementById('terms');
    const addressSelected = document.querySelector('input[name="address_id"]:checked');
    
    if (!addressSelected) {
        e.preventDefault();
        showNotification('Please select a shipping address', 'error');
        return;
    }
    
    if (!terms.checked) {
        e.preventDefault();
        showNotification('Please agree to the Terms & Conditions', 'error');
        return;
    }
    
    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.add('active');
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
});

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const bgColor = type === 'success' ? '#198754' : type === 'error' ? '#dc3545' : '#ffc107';
    const textColor = type === 'warning' ? '#000' : '#fff';
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${bgColor};
        color: ${textColor};
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        font-size: 14px;
        animation: slideIn 0.3s ease;
    `;
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            <span>${message}</span>
            <button class="btn-close btn-close-${textColor === '#fff' ? 'white' : ''} ms-3" style="font-size: 10px;" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Auto-select first address if none selected
document.addEventListener('DOMContentLoaded', function() {
    const selectedAddress = document.querySelector('input[name="address_id"]:checked');
    if (!selectedAddress && document.querySelector('input[name="address_id"]')) {
        document.querySelector('input[name="address_id"]').checked = true;
        const firstCard = document.querySelector('.address-card');
        if (firstCard) firstCard.classList.add('selected', 'border-success');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>