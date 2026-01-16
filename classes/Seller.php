<?php
class Seller {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function createProfile($userId, $profileData) {
        $profileData['user_id'] = $userId;
        return $this->db->insert('seller_profiles', $profileData);
    }

    public function getProducts($sellerId, $status = null) {
        $sql = "SELECT * FROM products WHERE seller_id = ?";
        $params = [$sellerId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        return $this->db->fetchAll($sql . " ORDER BY created_at DESC", $params);
    }

    public function getSalesStats($sellerId) {
        return $this->db->fetchOne(
            "SELECT COUNT(*) as total_orders, SUM(oi.item_total) as total_sales 
             FROM order_items oi 
             WHERE oi.seller_id = ? AND oi.status = 'delivered'", 
            [$sellerId]
        );
    }

    public function getPendingApprovals($sellerId) {
        return $this->db->fetchAll(
            "SELECT * FROM products 
             WHERE seller_id = ? AND status IN ('pending', 'draft')", 
            [$sellerId]
        );
    }

    public function updateBankDetails($sellerId, $bankData) {
        $bankData['seller_id'] = $sellerId;
        return $this->db->insert('seller_financial_info', $bankData);
    }
}
?>