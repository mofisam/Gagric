<?php
// verify-account.php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';

header('Content-Type: application/json');

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Paystack configuration
require_once '../../config/constants.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$account_number = $input['account_number'] ?? '';
$bank_code = $input['bank_code'] ?? '';

// Validate input
if (empty($account_number) || empty($bank_code)) {
    echo json_encode(['success' => false, 'message' => 'Account number and bank code are required']);
    exit;
}

// Verify account with Paystack
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/bank/resolve?account_number={$account_number}&bank_code={$bank_code}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $result = json_decode($response, true);
    if ($result['status']) {
        echo json_encode([
            'success' => true,
            'account_name' => $result['data']['account_name'],
            'message' => 'Account verified successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Verification failed'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to Paystack'
    ]);
}
?>