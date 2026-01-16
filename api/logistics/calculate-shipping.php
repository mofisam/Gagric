<?php
header('Content-Type: application/json');
require_once '../../config/logistics.php';

$origin_state = $_GET['origin'] ?? '';
$destination_state = $_GET['destination'] ?? '';
$weight = floatval($_GET['weight'] ?? 0);

if (empty($origin_state) || empty($destination_state) || $weight <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Origin, destination and weight required']);
    exit;
}

$shipping = LogisticsAPI::calculateShipping($origin_state, $destination_state, $weight);
echo json_encode($shipping);
?>