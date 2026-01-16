<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$input = json_decode(file_get_contents('php://input'), true);

$required = ['email', 'password', 'first_name', 'last_name', 'phone', 'role'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
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
$stmt = $db->conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $input['email'], $password_hash, $input['first_name'], $input['last_name'], $input['phone'], $input['role']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User registered successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed']);
}
?>