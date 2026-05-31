<?php
require_once __DIR__ . '/../../includes/auth.php';
requireSeller();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid session token. Please refresh the page and try again.']);
    exit;
}

$seller_id = (int)$_SESSION['user_id'];
$db = null;
$transaction_open = false;

try {
    $db = new Database();

    $bank_details = $db->fetchOne("
        SELECT id
        FROM seller_financial_info
        WHERE seller_id = ? AND is_bank_verified = 1
    ", [$seller_id]);

    if (!$bank_details) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please add and verify your bank details first.']);
        exit;
    }

    $db->conn->begin_transaction();
    $transaction_open = true;

    $eligible_items = $db->fetchAll("
        SELECT oi.id, oi.item_total
        FROM order_items oi
        WHERE oi.seller_id = ?
            AND oi.status = 'delivered'
            AND NOT EXISTS (
                SELECT 1
                FROM seller_payouts sp
                WHERE sp.order_item_id = oi.id
                    AND sp.status IN ('paid', 'processing', 'pending')
            )
        FOR UPDATE
    ", [$seller_id]);

    $commission_rate = (float)COMMISSION_RATE;
    $commission_multiplier = $commission_rate / 100;
    $gross_amount = 0.0;
    $net_amount = 0.0;

    foreach ($eligible_items as $item) {
        $item_total = (float)$item['item_total'];
        $gross_amount += $item_total;
        $net_amount += round($item_total - ($item_total * $commission_multiplier), 2);
    }

    $net_amount = round($net_amount, 2);

    if (empty($eligible_items) || $net_amount < MIN_PAYOUT_AMOUNT) {
        $db->conn->rollback();
        $transaction_open = false;
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Your available balance is below the minimum payout amount.'
        ]);
        exit;
    }

    $request_reference = 'REQ_' . time() . '_' . $seller_id;

    $insert = $db->conn->prepare("
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
            CONCAT(?, '_', oi.id)
        FROM order_items oi
        WHERE oi.seller_id = ?
            AND oi.status = 'delivered'
            AND NOT EXISTS (
                SELECT 1
                FROM seller_payouts sp
                WHERE sp.order_item_id = oi.id
                    AND sp.status IN ('paid', 'processing', 'pending')
            )
    ");

    if (!$insert) {
        throw new Exception('Could not prepare payout request.');
    }

    $insert->bind_param(
        'dddsi',
        $commission_rate,
        $commission_multiplier,
        $commission_multiplier,
        $request_reference,
        $seller_id
    );

    if (!$insert->execute()) {
        throw new Exception('Could not save payout request: ' . $insert->error);
    }

    $created_count = $insert->affected_rows;
    $insert->close();

    if ($created_count <= 0) {
        $db->conn->rollback();
        $transaction_open = false;
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'No new eligible earnings were found for payout.'
        ]);
        exit;
    }

    $db->conn->commit();
    $transaction_open = false;

    try {
        logActivity($seller_id, 'payout_requested', json_encode([
            'items' => $created_count,
            'gross_amount' => round($gross_amount, 2),
            'net_amount' => $net_amount,
            'request_reference' => $request_reference
        ]));
    } catch (Exception $log_error) {
        error_log('Payout request activity log failed: ' . $log_error->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payout request submitted. Admin can now process it via Paystack.',
        'items' => $created_count,
        'amount' => $net_amount
    ]);
} catch (Exception $e) {
    if ($transaction_open && $db instanceof Database && $db->conn) {
        $db->conn->rollback();
    }

    error_log('Payout request failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to submit payout request. Please try again.'
    ]);
}
?>
