<?php
// seller/orders/update-order-status.php
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';

requireSeller();

$db = new Database();
$seller_id = $_SESSION['user_id'];

// Debug mode
if (isset($_GET['debug'])) {
    echo json_encode([
        'status' => 'debug',
        'seller_id' => $seller_id,
        'message' => 'API is working',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No input data']);
    exit;
}

if (!isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit;
}

try {
    $db->conn->begin_transaction();
    
    if ($input['action'] === 'update_item_status') {
        // Update item status
        if (!isset($input['order_item_id']) || !isset($input['status'])) {
            echo json_encode(['success' => false, 'error' => 'Missing item ID or status']);
            exit;
        }
        
        $order_item_id = (int)$input['order_item_id'];
        $new_status = $input['status'];
        $notes = $input['notes'] ?? '';
        
        // Verify seller owns this item
        $item = $db->fetchOne("
            SELECT oi.*, o.id as order_id 
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            WHERE oi.id = ? AND oi.seller_id = ?
        ", [$order_item_id, $seller_id]);
        
        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item not found or access denied']);
            exit;
        }
        
        // Update item status
        $db->update('order_items', [
            'status' => $new_status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_item_id]);
        
        // Log history
        $db->insert('order_item_status_history', [
            'order_item_id' => $order_item_id,
            'status' => $new_status,
            'changed_by' => $seller_id,
            'notes' => $notes,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // Auto-update tracking if shipped
        if ($new_status === 'shipped') {
            $tracking_number = 'TRK' . time() . $order_item_id;
            $db->update('order_shipping_details', [
                'tracking_number' => $tracking_number,
                'logistics_partner' => 'Seller Logistics',
                'estimated_delivery' => date('Y-m-d', strtotime('+3 days')),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'order_item_id = ?', [$order_item_id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Item status updated',
            'item_id' => $order_item_id,
            'new_status' => $new_status
        ]);
        
    } elseif ($input['action'] === 'update_order_status') {
        // Update order status
        if (!isset($input['order_id']) || !isset($input['status'])) {
            echo json_encode(['success' => false, 'error' => 'Missing order ID or status']);
            exit;
        }
        
        $order_id = (int)$input['order_id'];
        $new_status = $input['status'];
        $notes = $input['notes'] ?? '';
        
        // Verify seller has items in this order
        $order = $db->fetchOne("
            SELECT o.* FROM orders o 
            WHERE o.id = ? AND EXISTS (
                SELECT 1 FROM order_items oi 
                WHERE oi.order_id = o.id AND oi.seller_id = ?
            )
        ", [$order_id, $seller_id]);
        
        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Order not found or access denied']);
            exit;
        }
        
        // Update order status
        $db->update('orders', [
            'status' => $new_status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        // Log order history
        $db->insert('order_status_history', [
            'order_id' => $order_id,
            'status' => $new_status,
            'changed_by' => $seller_id,
            'notes' => $notes,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // Update all seller's items in this order
        $db->update('order_items', [
            'status' => $new_status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'order_id = ? AND seller_id = ?', [$order_id, $seller_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated',
            'order_id' => $order_id,
            'new_status' => $new_status
        ]);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }
    
    $db->conn->commit();
    
} catch (Exception $e) {
    $db->conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>