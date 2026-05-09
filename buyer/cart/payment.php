<?php
// buyer/cart/payment.php - IMPROVED & SECURE VERSION

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

// Get order number
$order_number = $_GET['order_number'] ?? ($_SESSION['pending_order'] ?? null);

if (!$order_number) {
    setFlashMessage('No order found to process payment', 'error');
    header('Location: ../orders/');
    exit;
}

// Get order
$order_details = $order->getOrder($order_number, $user_id);

if (!$order_details) {
    setFlashMessage('Order not found or unauthorized', 'error');
    header('Location: ../orders/');
    exit;
}

// Prevent paying already-paid orders
if ($order_details['payment_status'] === 'paid') {
    setFlashMessage('Order already paid', 'info');
    header("Location: ../orders/order-details.php?order_number={$order_number}");
    exit;
}

// User details
$user = $db->fetchOne(
    "SELECT email, first_name, last_name FROM users WHERE id = ?",
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

        // Prevent duplicate successful payment
        if ($order_details['payment_status'] === 'paid') {
            throw new Exception('This order has already been paid');
        }

        /*
        |--------------------------------------------------------------------------
        | REUSE EXISTING PENDING PAYMENT
        |--------------------------------------------------------------------------
        */

        $existingPayment = $db->fetchOne(
            "SELECT * FROM payments
             WHERE order_id = ?
             AND status = 'pending'
             ORDER BY id DESC
             LIMIT 1",
            [$order_details['id']]
        );

        if ($existingPayment) {

            echo json_encode([
                'success' => true,
                'access_code' => json_decode(
                    $existingPayment['paystack_response'],
                    true
                )['data']['access_code'] ?? null,
                'reference' => $existingPayment['paystack_reference']
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE UNIQUE REFERENCE
        |--------------------------------------------------------------------------
        */

        $paystack_reference =
            'PAY-' .
            date('YmdHis') .
            '-' .
            strtoupper(bin2hex(random_bytes(3)));

        /*
        |--------------------------------------------------------------------------
        | INITIALIZE PAYSTACK
        |--------------------------------------------------------------------------
        */

        $response = PaystackAPI::initializeTransaction(
            $user['email'],
            $order_details['total_amount'],
            $paystack_reference
        );

        if (
            !isset($response['status']) ||
            !$response['status']
        ) {
            throw new Exception(
                $response['message'] ?? 'Failed to initialize payment'
            );
        }

        if (!isset($response['data']['access_code'])) {
            throw new Exception('No access code received from Paystack');
        }

        /*
        |--------------------------------------------------------------------------
        | STORE PAYMENT
        |--------------------------------------------------------------------------
        */

        $payment_id = $db->insert('payments', [
            'order_id' => $order_details['id'],
            'paystack_reference' => $paystack_reference,
            'amount' => $order_details['total_amount'],
            'currency' => 'NGN',
            'status' => 'pending',
            'customer_email' => $user['email'],
            'customer_name' => trim(
                $user['first_name'] . ' ' . $user['last_name']
            ),
            'paystack_response' => json_encode($response),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['current_payment_id'] = $payment_id;
        $_SESSION['current_payment_reference'] = $paystack_reference;

        echo json_encode([
            'success' => true,
            'access_code' => $response['data']['access_code'],
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

if (isset($_GET['reference'])) {

    $reference = trim($_GET['reference']);

    try {

        $db->conn->begin_transaction();

        /*
        |--------------------------------------------------------------------------
        | GET PAYMENT RECORD
        |--------------------------------------------------------------------------
        */

        $payment = $db->fetchOne(
            "SELECT * FROM payments
             WHERE paystack_reference = ?",
            [$reference]
        );

        if (!$payment) {
            throw new Exception('Payment record not found');
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY PAYMENT BELONGS TO ORDER
        |--------------------------------------------------------------------------
        */

        if ($payment['order_id'] != $order_details['id']) {
            throw new Exception('Payment does not belong to this order');
        }

        /*
        |--------------------------------------------------------------------------
        | AVOID DOUBLE PROCESSING
        |--------------------------------------------------------------------------
        */

        if ($payment['status'] === 'success') {

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
            | Paystack returns amount in kobo
            */

            $expectedAmount = (int) ($order_details['total_amount'] * 100);

            if ((int)$payment_data['amount'] !== $expectedAmount) {
                throw new Exception('Payment amount mismatch');
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE PAYMENT
            |--------------------------------------------------------------------------
            */

            $mergedResponse = array_merge(
                json_decode($payment['paystack_response'], true) ?? [],
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
            | UPDATE ORDER
            |--------------------------------------------------------------------------
            */

            $db->update('orders', [
                'payment_status' => 'paid',
                'status' => 'confirmed',
                'paid_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$order_details['id']]);

            /*
            |--------------------------------------------------------------------------
            | CLEAR CART AFTER SUCCESSFUL PAYMENT
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
                $_SESSION['pending_order'],
                $_SESSION['current_payment_id'],
                $_SESSION['current_payment_reference']
            );

            /*
            |--------------------------------------------------------------------------
            | LOG ACTIVITY
            |--------------------------------------------------------------------------
            */

            logActivity(
                $user_id,
                'payment_success',
                "Order {$order_number} paid successfully"
            );

            $payment_success = true;
        }

        $db->conn->commit();

    } catch (Exception $e) {

        $db->conn->rollback();

        error_log("Payment Verification Error: " . $e->getMessage());

        if (isset($payment['id'])) {

            $db->update('payments', [
                'status' => 'failed'
            ], 'id = ?', [$payment['id']]);
        }

        setFlashMessage(
            'Payment verification failed: ' . $e->getMessage(),
            'error'
        );
    }
}

$page_title = "Payment - Order #{$order_number}";
include '../../includes/header.php';
?>

<div class="container py-5">

    <?php if ($payment_success): ?>

        <div class="card border-success">
            <div class="card-body text-center py-5">

                <i class="bi bi-check-circle-fill text-success"
                   style="font-size: 5rem;"></i>

                <h2 class="mt-4">Payment Successful</h2>

                <p class="lead">
                    Your payment for order
                    <strong><?php echo htmlspecialchars($order_number); ?></strong>
                    was successful.
                </p>

                <div class="mt-4">
                    <a href="../orders/order-details.php?order_number=<?php echo urlencode($order_number); ?>"
                       class="btn btn-success btn-lg">
                        View Order
                    </a>
                </div>

            </div>
        </div>

    <?php else: ?>

        <div class="row justify-content-center">

            <div class="col-lg-7">

                <div class="card shadow-sm">

                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            Complete Payment
                        </h4>
                    </div>

                    <div class="card-body">

                        <div id="paymentMessage"></div>

                        <div class="mb-4">

                            <h5>Order Summary</h5>

                            <div class="d-flex justify-content-between">
                                <span>Order Number</span>
                                <strong><?php echo htmlspecialchars($order_number); ?></strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span>Total Amount</span>
                                <strong class="text-success">
                                    <?php echo formatCurrency($order_details['total_amount']); ?>
                                </strong>
                            </div>

                        </div>

                        <div class="form-check mb-4">

                            <input class="form-check-input"
                                   type="checkbox"
                                   id="agreeTerms">

                            <label class="form-check-label" for="agreeTerms">
                                I agree to the Terms & Conditions
                            </label>

                        </div>

                        <button type="button"
                                id="payButton"
                                class="btn btn-success btn-lg w-100">

                            <i class="bi bi-lock-fill me-2"></i>
                            Pay Securely with Paystack

                        </button>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>

<script src="https://js.paystack.co/v2/inline.js"></script>

<script>

document.addEventListener('DOMContentLoaded', () => {

    const payButton = document.getElementById('payButton');
    const agreeTerms = document.getElementById('agreeTerms');
    const paymentMessage = document.getElementById('paymentMessage');

    function showMessage(message, type = 'danger') {

        paymentMessage.innerHTML = `
            <div class="alert alert-${type}">
                ${message}
            </div>
        `;
    }

    if (!payButton) return;

    payButton.addEventListener('click', async () => {

        if (!agreeTerms.checked) {
            showMessage('Please agree to the Terms & Conditions');
            return;
        }

        if (typeof PaystackPop === 'undefined') {
            showMessage('Unable to load payment gateway');
            return;
        }

        const originalText = payButton.innerHTML;

        payButton.disabled = true;

        payButton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';

        try {

            const formData = new FormData();

            formData.append('action', 'initialize_payment');
            formData.append(
                'csrf_token',
                '<?php echo $_SESSION['csrf_token']; ?>'
            );

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error);
            }

            const popup = new PaystackPop();

            popup.resumeTransaction(result.access_code, {

                onSuccess: function(transaction) {

                    showMessage(
                        'Payment successful. Verifying...',
                        'success'
                    );

                    window.location.href =
                        `payment.php?order_number=<?php echo urlencode($order_number); ?>&reference=${transaction.reference}`;
                },

                onCancel: function() {

                    showMessage(
                        'Payment cancelled',
                        'warning'
                    );

                    payButton.disabled = false;
                    payButton.innerHTML = originalText;
                },

                onError: function(error) {

                    showMessage(
                        error.message || 'Payment failed'
                    );

                    payButton.disabled = false;
                    payButton.innerHTML = originalText;
                }
            });

        } catch (error) {

            console.error(error);

            showMessage(error.message || 'Payment failed');

            payButton.disabled = false;
            payButton.innerHTML = originalText;
        }
    });
});

</script>

<?php include '../../includes/footer.php'; ?>