<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();

// Get state_id from query parameter
$state_id = $_GET['state_id'] ?? 0;

if (empty($state_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'State ID is required']);
    exit;
}

// Validate state_id is numeric
if (!is_numeric($state_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid State ID']);
    exit;
}

// Fetch LGAs for the given state
try {
    $sql = "SELECT id, name FROM lgas WHERE state_id = ? ORDER BY name";
    $stmt = $db->conn->prepare($sql);
    $stmt->bind_param("i", $state_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lgas = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($lgas)) {
        // Check if state exists
        $state_check = $db->fetchOne("SELECT id FROM states WHERE id = ?", [$state_id]);
        if (!$state_check) {
            http_response_code(404);
            echo json_encode(['error' => 'State not found']);
            exit;
        }
    }
    
    echo json_encode([
        'success' => true,
        'state_id' => $state_id,
        'lgas' => $lgas,
        'count' => count($lgas)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>