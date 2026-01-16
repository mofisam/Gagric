<?php
class Order {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function create($buyerId, $items, $shippingInfo) {
        $this->db->conn->begin_transaction();
    
        try {
            // Calculate totals
            $subtotal = 0;
            foreach ($items as $item) {
                $product = $this->db->fetchOne(
                    "SELECT price_per_unit, stock_quantity FROM products WHERE id = ? AND status = 'approved'",
                    [$item['product_id']]
                );
                
                if (!$product || $product['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Invalid product or insufficient stock");
                }
                
                $subtotal += $product['price_per_unit'] * $item['quantity'];
            }
    
            $shippingCost = $this->calculateShipping($shippingInfo);
            $total = $subtotal + $shippingCost;
    
            // Create order
            $orderNumber = 'ORD' . time() . rand(100, 999);
            $orderId = $this->db->insert('orders', [
                'order_number' => $orderNumber,
                'buyer_id' => $buyerId,
                'subtotal_amount' => $subtotal,
                'shipping_amount' => $shippingCost,
                'total_amount' => $total
            ]);
    
            // Create order items and track their IDs
            $orderItemIds = [];
            foreach ($items as $item) {
                $product = $this->db->fetchOne(
                    "SELECT * FROM products WHERE id = ?",
                    [$item['product_id']]
                );
    
                $orderItemId = $this->db->insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'seller_id' => $product['seller_id'],
                    'product_name' => $product['name'],
                    'unit_price' => $product['price_per_unit'],
                    'quantity' => $item['quantity'],
                    'unit' => $product['unit'],
                    'item_total' => $product['price_per_unit'] * $item['quantity']
                ]);
                
                $orderItemIds[] = $orderItemId;
    
                // Update stock
                $this->db->update(
                    'products',
                    ['stock_quantity' => $product['stock_quantity'] - $item['quantity']],
                    'id = ?',
                    [$item['product_id']]
                );
            }
    
            // Add shipping details - Use the first order item ID for shipping
            // Note: This assumes one shipping address per order. If you need per-item shipping,
            // you'll need to create a shipping record for each order_item_id
            $this->db->insert('order_shipping_details', [
                'order_id' => $orderId,
                'shipping_name' => $shippingInfo['shipping_name'],
                'shipping_phone' => $shippingInfo['shipping_phone'],
                'state_id' => $shippingInfo['state_id'],
                'lga_id' => $shippingInfo['lga_id'],
                'city_id' => $shippingInfo['city_id'],
                'address_line' => $shippingInfo['address_line'],
                'landmark' => $shippingInfo['landmark'] ?? null,
                'shipping_instructions' => $shippingInfo['shipping_instructions'] ?? null
            ]);
    
            $this->db->conn->commit();
            return $orderNumber;
    
        } catch (Exception $e) {
            $this->db->conn->rollback();
            throw $e;
        }
    }

    public function getOrder($orderNumber, $userId = null) {
        $sql = "SELECT o.*, os.* FROM orders o 
                LEFT JOIN order_shipping_details os ON o.id = os.order_id 
                WHERE o.order_number = ?";
        $params = [$orderNumber];
        
        if ($userId) {
            $sql .= " AND o.buyer_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetchOne($sql, $params);
    }

    // Add this missing method
    public function getUserOrders($userId) {
        $sql = "SELECT o.* FROM orders o WHERE o.buyer_id = ? ORDER BY o.created_at DESC";
        $params = [$userId];
        
        return $this->db->fetchAll($sql, $params);
    }

    public function updateStatus($orderId, $status) {
        return $this->db->update(
            'orders',
            ['status' => $status],
            'id = ?',
            [$orderId]
        );
    }

    private function calculateShipping($shippingInfo) {
        // Basic shipping calculation - integrate with logistics API
        return 500; // Flat rate for demo
    }

    // Additional helper method
    public function getOrderById($orderId, $userId = null) {
        $sql = "SELECT o.*, os.* FROM orders o 
                LEFT JOIN order_shipping_details os ON o.id = os.order_id 
                WHERE o.id = ?";
        $params = [$orderId];
        
        if ($userId) {
            $sql .= " AND o.buyer_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetchOne($sql, $params);
    }
}
?>