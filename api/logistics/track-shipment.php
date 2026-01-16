<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$tracking_number = $_GET['tracking_number'] ?? '';

if (empty($tracking_number)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tracking number required']);
    exit;
}

// Get shipping details
$sql = "SELECT os.*, o.order_number, o.status as order_status 
        FROM order_shipping_details os 
        JOIN orders o ON os.order_id = o.id 
        WHERE os.tracking_number = ?";
        
$stmt = $db->conn->prepare($sql);
$stmt->bind_param("s", $tracking_number);
$stmt->execute();
$shipment = $stmt->get_result()->fetch_assoc();

if ($shipment) {
    // Mock tracking events - integrate with actual logistics API
    $tracking_events = [
        ['status' => 'dispatched', 'location' => 'Warehouse', 'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['status' => 'in_transit', 'location' => $shipment['state_id'], 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day'))],
        ['status' => 'out_for_delivery', 'location' => 'Local Hub', 'timestamp' => date('Y-m-d H:i:s')]
    ];
    
    echo json_encode([
        'shipment' => $shipment,
        'tracking_events' => $tracking_events,
        'estimated_delivery' => $shipment['estimated_delivery']
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Shipment not found']);
}
?>