<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/paystack.php';
require_once '../../includes/auth.php';

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check if it's an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Require admin authentication
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$db = new Database();

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$reference = $input['reference'] ?? '';
$payment_id = $input['payment_id'] ?? 0;

// Log the request for debugging
error_log("Payment verification request - Reference: $reference, Payment ID: $payment_id");

// Validate input
if (empty($reference) && empty($payment_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payment reference or ID required']);
    exit;
}

try {
    // If payment_id is provided but no reference, get reference from database
    if (empty($reference) && $payment_id > 0) {
        $stmt = $db->conn->prepare("
            SELECT paystack_reference, order_id, status, amount 
            FROM payments 
            WHERE id = ? AND status IN ('pending', 'success', 'failed')
        ");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Payment not found or already processed');
        }
        
        $payment = $result->fetch_assoc();
        $reference = $payment['paystack_reference'];
        $order_id = $payment['order_id'];
        $current_status = $payment['status'];
        $payment_amount = $payment['amount'];
        
        $stmt->close();
    } else {
        // Get payment details by reference
        $stmt = $db->conn->prepare("
            SELECT id as payment_id, order_id, status, amount 
            FROM payments 
            WHERE paystack_reference = ? AND status IN ('pending', 'success', 'failed')
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Payment not found or already processed');
        }
        
        $payment = $result->fetch_assoc();
        $payment_id = $payment['payment_id'];
        $order_id = $payment['order_id'];
        $current_status = $payment['status'];
        $payment_amount = $payment['amount'];
        
        $stmt->close();
    }
    
    // Verify transaction with Paystack
    $paystack_response = PaystackAPI::verifyTransaction($reference);
    
    if (!$paystack_response['status']) {
        throw new Exception('Paystack API error: ' . ($paystack_response['message'] ?? 'Unknown error'));
    }
    
    $transaction_data = $paystack_response['data'];
    $paystack_status = $transaction_data['status'];
    $paystack_amount = $transaction_data['amount'] / 100; // Convert from kobo to Naira
    
    // Check if amount matches
    if ($paystack_amount != $payment_amount) {
        error_log("Amount mismatch: DB=$payment_amount, Paystack=$paystack_amount");
        // Amount mismatch - flag for review but continue processing
        $amount_mismatch = true;
    } else {
        $amount_mismatch = false;
    }
    
    // Determine new payment status based on Paystack response
    $new_payment_status = match($paystack_status) {
        'success' => 'success',
        'failed' => 'failed',
        'abandoned' => 'abandoned',
        default => 'pending'
    };
    
    // Check if status has changed
    $status_changed = ($current_status !== $new_payment_status);
    
    // Start transaction
    $db->conn->begin_transaction();
    
    try {
        // Update payment record
        $update_payment = $db->conn->prepare("
            UPDATE payments 
            SET 
                status = ?,
                paystack_response = ?,
                updated_at = NOW(),
                paid_at = CASE WHEN ? = 'success' THEN NOW() ELSE NULL END
            WHERE id = ?
        ");
        
        // Prepare response data for storage
        $response_json = json_encode($transaction_data, JSON_UNESCAPED_SLASHES);
        
        $update_payment->bind_param(
            "sssi",
            $new_payment_status,
            $response_json,
            $new_payment_status,
            $payment_id
        );
        
        if (!$update_payment->execute()) {
            throw new Exception('Failed to update payment record: ' . $update_payment->error);
        }
        
        $update_payment->close();
        
        // If payment is successful, update order and create seller payouts
        if ($new_payment_status === 'success') {
            // Update order payment status
            $update_order = $db->conn->prepare("
                UPDATE orders 
                SET 
                    payment_status = 'paid',
                    status = CASE WHEN status = 'pending' THEN 'confirmed' ELSE status END,
                    paid_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update_order->bind_param("i", $order_id);
            
            if (!$update_order->execute()) {
                throw new Exception('Failed to update order: ' . $update_order->error);
            }
            $update_order->close();
            
            // Create seller payouts for each order item
            $payout_stmt = $db->conn->prepare("
                INSERT INTO seller_payouts (
                    seller_id, 
                    order_item_id, 
                    amount, 
                    commission_rate, 
                    commission_amount, 
                    net_amount, 
                    status,
                    paystack_transfer_reference
                )
                SELECT 
                    oi.seller_id,
                    oi.id,
                    oi.item_total,
                    ?,
                    ROUND(oi.item_total * ?, 2),
                    ROUND(oi.item_total - (oi.item_total * ?), 2),
                    'pending',
                    CONCAT('TRF_', UNIX_TIMESTAMP(), '_', oi.id)
                FROM order_items oi
                WHERE oi.order_id = ?
                AND oi.status NOT IN ('cancelled')
            ");
            
            // Use platform commission rate (e.g., 5%)
            $commission_rate = 0.05; // 5%
            $payout_stmt->bind_param("dddi", $commission_rate, $commission_rate, $commission_rate, $order_id);
            
            if (!$payout_stmt->execute()) {
                error_log("Payout creation error: " . $payout_stmt->error);
                // Don't fail the transaction if payouts fail - just log it
            }
            $payout_stmt->close();
            
            // Log successful payment
            logActivity(
                $_SESSION['user_id'],
                'payment_verified',
                json_encode([
                    'payment_id' => $payment_id,
                    'order_id' => $order_id,
                    'amount' => $payment_amount,
                    'reference' => $reference,
                    'verified_by' => $_SESSION['user_id']
                ])
            );
        } else if ($status_changed) {
            // Log status change for non-successful payments
            logActivity(
                $_SESSION['user_id'],
                'payment_status_changed',
                json_encode([
                    'payment_id' => $payment_id,
                    'old_status' => $current_status,
                    'new_status' => $new_payment_status,
                    'reference' => $reference,
                    'changed_by' => $_SESSION['user_id']
                ])
            );
        }
        
        // Commit transaction
        $db->conn->commit();
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => 'Payment verified successfully',
            'data' => [
                'payment_id' => $payment_id,
                'order_id' => $order_id,
                'old_status' => $current_status,
                'new_status' => $new_payment_status,
                'reference' => $reference,
                'amount' => $payment_amount,
                'paystack_amount' => $paystack_amount,
                'status_changed' => $status_changed,
                'amount_mismatch' => $amount_mismatch,
                'transaction_data' => [
                    'channel' => $transaction_data['channel'] ?? null,
                    'paid_at' => $transaction_data['paid_at'] ?? null,
                    'currency' => $transaction_data['currency'] ?? null,
                    'fees' => $transaction_data['fees'] ?? null
                ]
            ]
        ];
        
        // Add warning if amount mismatch
        if ($amount_mismatch) {
            $response['warning'] = 'Amount mismatch detected. Please review manually.';
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'reference' => $reference ?? null,
            'payment_id' => $payment_id ?? null
        ]
    ]);
}

// Helper function to log activity
function logActivity($user_id, $action, $details = null) {
    global $db;
    
    $stmt = $db->conn->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt->bind_param(
        "issss",
        $user_id,
        $action,
        $details,
        $ip_address,
        $user_agent
    );
    
    $stmt->execute();
    $stmt->close();
}
?>