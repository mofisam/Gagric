<?php
// verify-account.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/paystack.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'seller') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$account_number = sanitizeInput($input['account_number'] ?? '');
$bank_code = sanitizeInput($input['bank_code'] ?? '');

// Validate input
if (empty($account_number) || empty($bank_code)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Account number and bank code are required']);
    exit;
}

if (!preg_match('/^[0-9]{10}$/', $account_number)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit account number']);
    exit;
}

// Verify account with Paystack
$result = PaystackAPI::resolveBankAccount($account_number, $bank_code);

if (!empty($result['status']) && !empty($result['data']['account_name'])) {
    echo json_encode([
        'success' => true,
        'account_name' => $result['data']['account_name'],
        'message' => 'Account verified successfully'
    ]);
} else {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Verification failed'
    ]);
}
?>
