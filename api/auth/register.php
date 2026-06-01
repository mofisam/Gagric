<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$input = json_decode(file_get_contents('php://input'), true);

$required = ['email', 'password', 'first_name', 'last_name', 'phone', 'date_of_birth', 'role'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$dob = DateTimeImmutable::createFromFormat('!Y-m-d', $input['date_of_birth']);
$dobErrors = DateTimeImmutable::getLastErrors();
$hasDobErrors = $dobErrors !== false && ($dobErrors['warning_count'] > 0 || $dobErrors['error_count'] > 0);
$today = new DateTimeImmutable('today');

if (!$dob || $hasDobErrors || $dob->format('Y-m-d') !== $input['date_of_birth']) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid date of birth']);
    exit;
}

if ($dob > $today->modify('-18 years')) {
    http_response_code(400);
    echo json_encode(['error' => 'You must be at least 18 years old to register']);
    exit;
}

// Check if user exists
$stmt = $db->conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$stmt->bind_param("ss", $input['email'], $input['phone']);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'User already exists']);
    exit;
}

// Create user
$password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
$stmt = $db->conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, date_of_birth, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $input['email'], $password_hash, $input['first_name'], $input['last_name'], $input['phone'], $input['date_of_birth'], $input['role']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User registered successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed']);
}
?>
