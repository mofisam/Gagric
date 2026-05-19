<?php
// buyer/cart/payment.php - MULTI-ORDER PAYMENT VERSION (FIXED)

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';
require_once '../../config/paystack.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get order numbers (multiple orders from checkout)
$order_numbers = [];
if (isset($_GET['orders'])) {
    $order_numbers = explode(',', $_GET['orders']);
} elseif (isset($_SESSION['pending_orders'])) {
    $order_numbers = is_array($_SESSION['pending_orders'])
        ? $_SESSION['pending_orders']
        : explode(',', $_SESSION['pending_orders']);
} elseif (isset($_GET['order_number'])) {
    // Fallback for single order (backward compatibility)
    $order_numbers = [$_GET['order_number']];
} elseif (isset($_SESSION['pending_order'])) {
    // Fallback for single order
    $order_numbers = [$_SESSION['pending_order']];
}

$order_numbers = array_values(array_unique(array_filter(array_map('trim', $order_numbers))));

// Paystack may return with only a reference. Recover linked orders from the payment record.
if (empty($order_numbers) && isset($_GET['reference'])) {
    $reference_payment = $db->fetchOne(
        "SELECT id FROM payments WHERE paystack_reference = ? AND buyer_id = ? LIMIT 1",
        [trim($_GET['reference']), $user_id]
    );

    if ($reference_payment) {
        $linked_orders = $db->fetchAll(
            "SELECT order_number FROM orders WHERE payment_id = ? AND buyer_id = ? ORDER BY id ASC",
            [$reference_payment['id'], $user_id]
        );

        $order_numbers = array_column($linked_orders, 'order_number');
    }
}

if (empty($order_numbers)) {
    setFlashMessage('No orders found to process payment', 'error');
    header('Location: ../orders/');
    exit;
}

// Get all orders
$orders = [];
$total_amount = 0;
$order_ids = [];

foreach ($order_numbers as $order_number) {
    $order_details = $order->getOrder($order_number, $user_id);

    if (!$order_details) {
        setFlashMessage("Order {$order_number} not found or unauthorized", 'error');
        header('Location: ../orders/');
        exit;
    }

    // Get REAL order ID directly from orders table
    $real_order = $db->fetchOne("
        SELECT id
        FROM orders
        WHERE order_number = ?
        AND buyer_id = ?
    ", [$order_details['order_number'], $user_id]);

    if (!$real_order) {
        setFlashMessage("Unable to resolve order ID for {$order_number}", 'error');
        header('Location: ../orders/');
        exit;
    }

    // Attach correct order ID
    $order_details['real_order_id'] = $real_order['id'];

    // Prevent paying already-paid orders
    if ($order_details['payment_status'] === 'paid') {
        setFlashMessage("Order {$order_number} has already been paid", 'info');
        header("Location: ../orders/order-details.php?order_number={$order_number}");
        exit;
    }

    $orders[] = $order_details;
    $total_amount += (float) $order_details['total_amount'];
    $order_ids[] = $real_order['id'];
}

$total_amount = round($total_amount, 2);

if ($total_amount <= 0) {
    setFlashMessage('Invalid payment amount for the selected order(s)', 'error');
    header('Location: ../orders/');
    exit;
}

// User details
$user = $db->fetchOne(
    "SELECT email, first_name, last_name, phone FROM users WHERE id = ?",
    [$user_id]
);

/*
|--------------------------------------------------------------------------
| AJAX PAYMENT INITIALIZATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'initialize_payment'
) {
    header('Content-Type: application/json');

    try {
        // CSRF CHECK
        if (
            !isset($_POST['csrf_token']) ||
            $_POST['csrf_token'] !== $_SESSION['csrf_token']
        ) {
            throw new Exception('Invalid CSRF token');
        }

        // Check if any order is already paid
        foreach ($orders as $order_details) {
            if ($order_details['payment_status'] === 'paid') {
                throw new Exception("Order {$order_details['order_number']} has already been paid");
            }
        }

        /*
        |--------------------------------------------------------------------------
        | RETIRE OLD PENDING PAYMENTS FOR THESE ORDERS
        |--------------------------------------------------------------------------
        |
        | Older pending attempts may have been initialized with the wrong amount
        | or an unusable inline access code. Create a fresh Paystack transaction
        | for this click and point every order to the new payment record.
        */

        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

            $db->query(
                "UPDATE payments p
                 INNER JOIN orders o ON o.payment_id = p.id
                 SET p.status = 'abandoned'
                 WHERE o.id IN ($placeholders)
                 AND p.status = 'pending'",
                $order_ids
            );

            $db->query(
                "UPDATE orders
                 SET payment_id = NULL
                 WHERE id IN ($placeholders)
                 AND payment_status <> 'paid'",
                $order_ids
            );
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE UNIQUE REFERENCE
        |--------------------------------------------------------------------------
        */

        $paystack_reference = 'PAY-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

        /*
        |--------------------------------------------------------------------------
        | INITIALIZE PAYSTACK (Single payment for all orders)
        |--------------------------------------------------------------------------
        */

        $response = PaystackAPI::initializeTransaction(
            $user['email'],
            $total_amount,
            $paystack_reference,
            [
                'buyer_id' => (string) $user_id,
                'order_numbers' => implode(',', $order_numbers),
                'payment_type' => count($orders) > 1 ? 'multi_order' : 'single_order'
            ],
            BASE_URL . '/buyer/cart/payment.php'
        );

        if (!isset($response['status']) || !$response['status']) {
            throw new Exception($response['message'] ?? 'Failed to initialize payment');
        }

        if (empty($response['data']['access_code'])) {
            throw new Exception('No popup access code received from Paystack');
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE ONE PAYMENT RECORD FOR THE SHARED TRANSACTION
        |--------------------------------------------------------------------------
        */

        $payment_id = $db->insert('payments', [
            'buyer_id' => $user_id,
            'paystack_reference' => $paystack_reference,
            'amount' => $total_amount,
            'currency' => 'NGN',
            'status' => 'pending',
            'customer_email' => $user['email'],
            'customer_name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'paystack_response' => json_encode($response),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if (!$payment_id) {
            if (property_exists($db, 'conn') && $db->conn) {
                error_log($db->conn->error);
            }
            throw new Exception('Failed to create payment record');
        }

        foreach ($order_ids as $order_id) {
            $db->update('orders', [
                'payment_id' => $payment_id
            ], 'id = ?', [$order_id]);
        }

        // Store which orders this payment is for
        $_SESSION['payment_orders'] = $order_ids;
        $_SESSION['payment_order_numbers'] = $order_numbers;
        $_SESSION['payment_reference'] = $paystack_reference;

        echo json_encode([
            'success' => true,
            'authorization_url' => $response['data']['authorization_url'],
            'access_code' => $response['data']['access_code'] ?? null,
            'reference' => $paystack_reference
        ]);

    } catch (Exception $e) {
        error_log("Payment Initialization Error: " . $e->getMessage());

        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

/*
|--------------------------------------------------------------------------
| PAYMENT VERIFICATION
|--------------------------------------------------------------------------
*/

$payment_success = false;
$verified_orders = [];

if (isset($_GET['reference'])) {
    $reference = trim($_GET['reference']);

    try {
        if (property_exists($db, 'conn') && $db->conn) {
            $db->conn->begin_transaction();
        }

        /*
        |--------------------------------------------------------------------------
        | GET PAYMENT RECORD
        |--------------------------------------------------------------------------
        */

        $payment = $db->fetchOne(
            "SELECT * FROM payments WHERE paystack_reference = ? LIMIT 1",
            [$reference]
        );

        if (!$payment) {
            throw new Exception('Payment record not found');
        }

        /*
        |--------------------------------------------------------------------------
        | ALREADY VERIFIED
        |--------------------------------------------------------------------------
        */

        if ($payment['status'] === 'success') {
            $paid_orders = $db->fetchAll(
                "SELECT order_number
                 FROM orders
                 WHERE payment_id = ?",
                [$payment['id']]
            );

            foreach ($paid_orders as $ord) {
                $verified_orders[] = $ord['order_number'];
            }
            $payment_success = true;
        } else {
            /*
            |--------------------------------------------------------------------------
            | VERIFY WITH PAYSTACK
            |--------------------------------------------------------------------------
            */

            $verification = PaystackAPI::verifyTransaction($reference);

            if (
                !isset($verification['status']) ||
                !$verification['status']
            ) {
                throw new Exception(
                    $verification['message'] ?? 'Verification failed'
                );
            }

            $payment_data = $verification['data'];

            /*
            |--------------------------------------------------------------------------
            | ENSURE PAYMENT SUCCESS
            |--------------------------------------------------------------------------
            */

            if ($payment_data['status'] !== 'success') {
                throw new Exception('Payment not successful');
            }

            /*
            |--------------------------------------------------------------------------
            | VERIFY AMOUNT
            |--------------------------------------------------------------------------
            */

            $expectedAmount = (int) round((float) $payment['amount'] * 100);

            if ((int)$payment_data['amount'] !== $expectedAmount) {
                throw new Exception('Payment amount mismatch');
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE PAYMENT
            |--------------------------------------------------------------------------
            */

            $existingResponse = json_decode($payment['paystack_response'], true);
            $mergedResponse = array_merge(
                $existingResponse ?: [],
                ['verification' => $verification]
            );

            $db->update('payments', [
                'status' => 'success',
                'payment_method' => $payment_data['channel'] ?? 'card',
                'paystack_authorization_code' =>
                    $payment_data['authorization']['authorization_code'] ?? null,
                'paystack_response' => json_encode($mergedResponse),
                'paid_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$payment['id']]);

            /*
            |--------------------------------------------------------------------------
            | UPDATE ORDERS
            |--------------------------------------------------------------------------
            */

            $orders_to_update = $db->fetchAll(
                "SELECT id, order_number
                 FROM orders
                 WHERE payment_id = ?",
                [$payment['id']]
            );

            foreach ($orders_to_update as $ord) {
                $db->update('orders', [
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'paid_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$ord['id']]);

                $verified_orders[] = $ord['order_number'];
            }

            /*
            |--------------------------------------------------------------------------
            | CLEAR CART
            |--------------------------------------------------------------------------
            */

            $db->query(
                "DELETE FROM cart WHERE user_id = ?",
                [$user_id]
            );

            /*
            |--------------------------------------------------------------------------
            | CLEAN SESSION
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['pending_orders'],
                $_SESSION['pending_order'],
                $_SESSION['payment_orders'],
                $_SESSION['payment_order_numbers'],
                $_SESSION['payment_reference']
            );

            /*
            |--------------------------------------------------------------------------
            | LOG ACTIVITY
            |--------------------------------------------------------------------------
            */

            if (function_exists('logActivity')) {
                logActivity(
                    $user_id,
                    'payment_success',
                    "Payment for orders: " .
                    implode(', ', $verified_orders) .
                    " completed successfully"
                );
            }

            $payment_success = true;
        }

        if (property_exists($db, 'conn') && $db->conn) {
            $db->conn->commit();
        }

    } catch (Exception $e) {
        if (property_exists($db, 'conn') && $db->conn) {
            $db->conn->rollback();
        }

        error_log("Payment Verification Error: " . $e->getMessage());

        if (isset($payment['id'])) {
            $failedResponse = json_decode($payment['paystack_response'] ?? '', true);
            if (!is_array($failedResponse)) {
                $failedResponse = [];
            }
            $failedResponse['verification_error'] = $e->getMessage();

            $db->update('payments', [
                'status' => 'failed',
                'paystack_response' => json_encode($failedResponse)
            ], 'id = ?', [$payment['id']]);
        }

        setFlashMessage(
            'Payment verification failed: ' .
            $e->getMessage(),
            'error'
        );
    }
}

$page_title = "Payment - " . count($orders) . " Order(s)";
include '../../includes/header.php';
?>

<style>
.order-card {
    border-left: 4px solid #198754;
    transition: all 0.3s ease;
    margin-bottom: 15px;
}
.order-card:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.payment-summary {
    background: linear-gradient(135deg, #198754 0%, #0d6efd 100%);
    border-radius: 15px;
    padding: 20px;
    color: white;
}
.seller-info {
    background: #f8f9fa;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
}
</style>

<div class="container py-5">

    <?php if ($payment_success): ?>

        <div class="card border-success shadow-lg">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                </div>

                <h2 class="mt-3 text-success">Payment Successful!</h2>
                
                <p class="lead mb-4">
                    Your payment has been processed successfully.
                </p>

                <div class="alert alert-success bg-light border-0">
                    <strong>Payment Reference:</strong> <?php echo htmlspecialchars($_GET['reference'] ?? 'N/A'); ?>
                </div>

                <div class="mb-4">
                    <h5>Orders Paid:</h5>
                    <?php 
                    $display_orders = !empty($verified_orders) ? $verified_orders : $order_numbers;
                    foreach ($display_orders as $ord_num): 
                    ?>
                        <span class="badge bg-success m-1 p-2"><?php echo htmlspecialchars($ord_num); ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <a href="../orders/" class="btn btn-success btn-lg me-2">
                        <i class="bi bi-box-seam me-2"></i> View My Orders
                    </a>
                    <a href="../products/browse.php" class="btn btn-outline-success btn-lg">
                        <i class="bi bi-shop me-2"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>

    <?php else: ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <!-- Payment Header -->
                <div class="payment-summary mb-4 text-center">
                    <h3 class="mb-2">
                        <i class="bi bi-credit-card me-2"></i>
                        Complete Payment
                    </h3>
                    <p class="mb-0 opacity-75">You're about to pay for <?php echo count($orders); ?> order(s)</p>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-receipt me-2"></i>
                            Payment Summary
                        </h4>
                    </div>

                    <div class="card-body">
                        <div id="paymentMessage"></div>

                        <!-- Orders List -->
                        <div class="mb-4">
                            <h5 class="mb-3">Orders Being Paid:</h5>
                            <?php foreach ($orders as $index => $order_details): ?>
                                <?php 
                                // Get seller info for this order
                                $seller_info = $db->fetchOne("
                                    SELECT DISTINCT oi.seller_id, sp.business_name, u.first_name, u.last_name 
                                    FROM order_items oi 
                                    LEFT JOIN users u ON oi.seller_id = u.id 
                                    LEFT JOIN seller_profiles sp ON oi.seller_id = sp.user_id 
                                    WHERE oi.order_id = ?
                                ", [$order_details['real_order_id']]);
                                
                                $seller_name = 'Unknown Seller';
                                if ($seller_info) {
                                    $seller_name = !empty($seller_info['business_name']) 
                                        ? $seller_info['business_name'] 
                                        : ($seller_info['first_name'] . ' ' . $seller_info['last_name']);
                                }
                                ?>
                                <div class="order-card card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <i class="bi bi-receipt me-1"></i>
                                                    Order #<?php echo htmlspecialchars($order_details['order_number']); ?>
                                                </h6>
                                                <div class="seller-info d-inline-block">
                                                    <i class="bi bi-shop me-1"></i>
                                                    Seller: <?php echo htmlspecialchars($seller_name); ?>
                                                </div>
                                                <div class="mt-2">
                                                    <?php 
                                                    // Get item count for this order
                                                    $item_count_result = $db->fetchOne("SELECT COUNT(*) as total FROM order_items WHERE order_id = ?", [$order_details['real_order_id']]);
                                                    $item_count = $item_count_result ? $item_count_result['total'] : 0;
                                                    ?>
                                                    <small class="text-muted">
                                                        <i class="bi bi-box-seam me-1"></i>
                                                        <?php echo $item_count; ?> item(s)
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold text-success fs-5"><?php echo formatCurrency($order_details['total_amount']); ?></span>
                                                <br>
                                                <small class="text-muted">Status: Pending</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Total Amount -->
                        <div class="alert alert-success bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="fs-5">Total Amount to Pay:</strong>
                                <strong class="fs-2 text-success"><?php echo formatCurrency($total_amount); ?></strong>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> This is a single payment for all <?php echo count($orders); ?> order(s)
                            </small>
                        </div>

                        <!-- Order Details Table -->
                        <div class="mb-4">
                            <h6>Order Summary:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order #</th>
                                            <th>Seller</th>
                                            <th>Items</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order_details): ?>
                                            <?php 
                                            // Get seller info for this order
                                            $seller_info = $db->fetchOne("
                                                SELECT DISTINCT oi.seller_id, sp.business_name, u.first_name, u.last_name 
                                                FROM order_items oi 
                                                LEFT JOIN users u ON oi.seller_id = u.id 
                                                LEFT JOIN seller_profiles sp ON oi.seller_id = sp.user_id 
                                                WHERE oi.order_id = ?
                                            ", [$order_details['real_order_id']]);
                                            
                                            $seller_name = 'Unknown Seller';
                                            if ($seller_info) {
                                                $seller_name = !empty($seller_info['business_name']) 
                                                    ? $seller_info['business_name'] 
                                                    : ($seller_info['first_name'] . ' ' . $seller_info['last_name']);
                                            }
                                            
                                            $item_count_result = $db->fetchOne("SELECT COUNT(*) as total FROM order_items WHERE order_id = ?", [$order_details['real_order_id']]);
                                            $item_count = $item_count_result ? $item_count_result['total'] : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order_details['order_number']); ?></td>
                                                <td><?php echo htmlspecialchars($seller_name); ?></td>
                                                <td><?php echo $item_count; ?> item(s)</td>
                                                <td class="text-end"><?php echo formatCurrency($order_details['total_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td class="text-end"><strong class="text-success fs-5"><?php echo formatCurrency($total_amount); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Terms -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="agreeTerms">
                            <label class="form-check-label" for="agreeTerms">
                                I confirm that all order information is correct and agree to the 
                                <a href="#" class="text-success" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a>
                            </label>
                        </div>

                        <!-- Pay Button -->
                        <button type="button" id="payButton" class="btn btn-success btn-lg w-100">
                            <i class="bi bi-lock-fill me-2"></i>
                            Pay <?php echo formatCurrency($total_amount); ?> Securely with Paystack
                        </button>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i> 
                                Secure payment by Paystack • Multiple orders, single payment
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods Info -->
                <div class="card mt-3 bg-light">
                    <div class="card-body">
                        <h6 class="mb-2">Accepted Payment Methods:</h6>
                        <div class="d-flex gap-3 flex-wrap">
                            <span><i class="bi bi-credit-card"></i> Cards (Visa, Mastercard)</span>
                            <span><i class="bi bi-bank"></i> Bank Transfer</span>
                            <span><i class="bi bi-phone"></i> USSD</span>
                            <span><i class="bi bi-wallet"></i> Mobile Money</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Terms & Conditions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Multiple Orders</h6>
                <p>You are making a single payment for multiple orders from different sellers. Each order will be processed independently by its respective seller.</p>
                
                <h6>2. Payment Confirmation</h6>
                <p>Once payment is successful, all orders will be confirmed automatically. You will receive separate order confirmations.</p>
                
                <h6>3. Shipping & Delivery</h6>
                <p>Each seller handles their own shipping. Delivery times may vary by seller location.</p>
                
                <h6>4. Returns & Refunds</h6>
                <p>Returns and refunds are handled per order. Contact the respective seller for returns.</p>
                
                <h6>5. Payment Security</h6>
                <p>All payments are processed securely through Paystack. We don't store your card details.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">I Agree</button>
            </div>
        </div>
    </div>
</div>

<script src="https://js.paystack.co/v2/inline.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const payButton = document.getElementById('payButton');
    const agreeTerms = document.getElementById('agreeTerms');
    const paymentMessage = document.getElementById('paymentMessage');

    function showMessage(message, type = 'danger') {
        if (paymentMessage) {
            const alertType = type === 'error' ? 'danger' : type;
            paymentMessage.innerHTML = `<div class="alert alert-${alertType} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        }
    }

    if (!payButton) return;

    payButton.addEventListener('click', async () => {
        if (!agreeTerms.checked) {
            showMessage('Please agree to the Terms & Conditions', 'warning');
            return;
        }

        const originalText = payButton.innerHTML;
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Initializing Payment...';

        function resetPayButton() {
            payButton.disabled = false;
            payButton.innerHTML = originalText;
        }

        try {
            if (typeof PaystackPop === 'undefined') {
                throw new Error('Unable to load Paystack popup. Please refresh the page.');
            }

            const formData = new FormData();
            formData.append('action', 'initialize_payment');
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Payment initialization failed');
            }

            if (!result.access_code) {
                throw new Error('Payment gateway did not return a popup access code');
            }

            payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Opening Payment...';

            const popup = new PaystackPop();
            popup.resumeTransaction(result.access_code, {
                onSuccess: function(transaction) {
                    const reference = transaction && (transaction.reference || transaction.trxref)
                        ? (transaction.reference || transaction.trxref)
                        : result.reference;

                    showMessage('Payment successful! Verifying your orders...', 'success');
                    window.location.href = 'payment.php?reference=' + encodeURIComponent(reference);
                },
                onCancel: function() {
                    showMessage('Payment was cancelled. You can try again.', 'warning');
                    resetPayButton();
                },
                onError: function(error) {
                    showMessage((error && error.message) || 'Payment failed. Please try again.', 'danger');
                    resetPayButton();
                }
            });
        } catch (error) {
            console.error('Payment Error:', error);
            showMessage(error.message || 'Failed to initialize payment. Please try again.', 'error');
            resetPayButton();
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
