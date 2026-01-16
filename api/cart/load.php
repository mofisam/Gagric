<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in', 'debug' => 'User not authenticated']);
    exit;
}

$db = new Database();
$user_id = getCurrentUserId();

try {
    error_log("Loading cart for user: $user_id");
    
    $stmt = $db->conn->prepare("
        SELECT 
            c.product_id AS productId, 
            c.quantity,
            p.name AS productName, 
            p.price_per_unit AS productPrice,
            p.unit AS productUnit,

            (
                SELECT image_path 
                FROM product_images 
                WHERE product_id = p.id 
                ORDER BY is_primary DESC, sort_order ASC, id ASC
                LIMIT 1
            ) AS imagePath

        FROM cart c
        LEFT JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");

    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $cart_items = [];
    
    while ($row = $result->fetch_assoc()) {
        // Only include products that still exist
        if ($row['productName']) {
            $cart_items[] = [
                'productId' => intval($row['productId']),
                'productName' => $row['productName'],
                'productPrice' => floatval($row['productPrice']),
                'productUnit' => $row['productUnit'],
                'quantity' => floatval($row['quantity']),
                'imagePath' => $row['imagePath'] ?: 'placeholder-product.jpg'
            ];
        }
    }
    
    $stmt->close();
    
    error_log("Loaded " . count($cart_items) . " items for user $user_id");
    
    echo json_encode([
        'success' => true,
        'cart' => $cart_items,
        'count' => count($cart_items),
        'debug' => ['user_id' => $user_id, 'items_loaded' => count($cart_items)]
    ]);
    
} catch (Exception $e) {
    error_log("Cart load exception: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load cart',
        'debug' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>