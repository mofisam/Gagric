<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();

// Get parameters
$lga_id = $_GET['lga_id'] ?? 0;
$state_id = $_GET['state_id'] ?? 0;

// Require at least one parameter
if (empty($lga_id) && empty($state_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Either LGA ID or State ID is required']);
    exit;
}

// Validate numeric inputs
if (!empty($lga_id) && !is_numeric($lga_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid LGA ID']);
    exit;
}

if (!empty($state_id) && !is_numeric($state_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid State ID']);
    exit;
}

try {
    // Choose query depending on parameter provided
    if (!empty($lga_id)) {
        $sql = "SELECT id, name FROM cities WHERE lga_id = ? ORDER BY name";
        $stmt = $db->conn->prepare($sql);
        $stmt->bind_param("i", $lga_id);
    } else {
        $sql = "SELECT id, name FROM cities WHERE state_id = ? ORDER BY name";
        $stmt = $db->conn->prepare($sql);
        $stmt->bind_param("i", $state_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $cities = $result->fetch_all(MYSQLI_ASSOC);

    // If no cities found, validate parent exists
    if (empty($cities)) {
        if (!empty($lga_id)) {
            $parent_check = $db->fetchOne("SELECT id FROM lgas WHERE id = ?", [$lga_id]);
            if (!$parent_check) {
                http_response_code(404);
                echo json_encode(['error' => 'LGA not found']);
                exit;
            }
        } else {
            $parent_check = $db->fetchOne("SELECT id FROM states WHERE id = ?", [$state_id]);
            if (!$parent_check) {
                http_response_code(404);
                echo json_encode(['error' => 'State not found']);
                exit;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'lga_id' => !empty($lga_id) ? $lga_id : null,
        'state_id' => !empty($state_id) ? $state_id : null,
        'cities' => $cities,
        'count' => count($cities)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
