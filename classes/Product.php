<?php
class Product {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function create($sellerId, $productData) {
        $productData['seller_id'] = $sellerId;
        $productData['status'] = 'pending'; // Require admin approval
        
        $productId = $this->db->insert('products', $productData);
        
        // Log approval request
        $this->db->insert('product_approvals', [
            'product_id' => $productId,
            'seller_id' => $sellerId,
            'change_type' => 'create',
            'status' => 'pending_review'
        ]);
        
        return $productId;
    }

    public function update($productId, $sellerId, $updateData) {
        // Get current data for audit
        $current = $this->db->fetchOne("SELECT * FROM products WHERE id = ? AND seller_id = ?", [$productId, $sellerId]);
        
        $this->db->update('products', $updateData, 'id = ? AND seller_id = ?', [$productId, $sellerId]);
        
        // Require re-approval for significant changes
        if ($this->requiresReapproval($current, $updateData)) {
            $this->db->insert('product_approvals', [
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'change_type' => 'update',
                'status' => 'pending_review',
                'old_data' => json_encode($current),
                'new_data' => json_encode($updateData)
            ]);
            
            $this->db->query(
                "UPDATE products SET status = 'pending' WHERE id = ?", 
                [$productId]
            );
        }
    }

    public function getApprovedProducts($filters = []) {
        $sql = "SELECT p.*, sp.business_name, c.name as category_name, 
                       pad.grade, pad.is_organic
                FROM products p 
                JOIN seller_profiles sp ON p.seller_id = sp.user_id 
                JOIN categories c ON p.category_id = c.id 
                LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id 
                WHERE p.status = 'approved' AND sp.is_approved = TRUE";
        
        $params = [];
        
        if (!empty($filters['category'])) {
            $sql .= " AND c.id = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR sp.business_name LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['organic'])) {
            $sql .= " AND pad.is_organic = TRUE";
        }
        
        if (!empty($filters['min_price'])) {
            $sql .= " AND p.price_per_unit >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND p.price_per_unit <= ?";
            $params[] = $filters['max_price'];
        }
        
        return $this->db->fetchAll($sql . " ORDER BY p.created_at DESC", $params);
    }

    public function getProductDetails($productId) {
        $sql = "SELECT 
                    p.*, 
                    sp.business_name, 
                    c.name as category_name,
                    pad.grade, 
                    pad.is_organic, 
                    pad.is_gmo,
                    pad.organic_certification_number,
                    pad.harvest_date, 
                    pad.expiry_date, 
                    pad.shelf_life_days,
                    pad.farming_method,
                    pad.irrigation_type,
                    pad.storage_temperature,
                    pad.storage_humidity
                FROM products p 
                JOIN seller_profiles sp ON p.seller_id = sp.user_id 
                JOIN categories c ON p.category_id = c.id 
                LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id 
                WHERE p.id = ? AND p.status = 'approved'";
        
        return $this->db->fetchOne($sql, [$productId]);
    }
    
    public function updateStock($productId, $newQuantity) {
        return $this->db->update(
            'products', 
            ['stock_quantity' => $newQuantity], 
            'id = ?', 
            [$productId]
        );
    }

    private function requiresReapproval($oldData, $newData) {
        $sensitiveFields = ['name', 'description', 'price_per_unit', 'category_id', 'product_type'];
        foreach ($sensitiveFields as $field) {
            if (($oldData[$field] ?? null) !== ($newData[$field] ?? null)) {
                return true;
            }
        }
        return false;
    }

    public function getPaginatedProducts($filters = [], $sort = 'popular', $limit = 12, $offset = 0) {
        $sql = "SELECT p.*, sp.business_name, 
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = TRUE LIMIT 1) as image_path,
                       pad.grade, pad.is_organic,
                       (SELECT COUNT(*) 
                          FROM order_items oi 
                          JOIN orders o ON oi.order_id = o.id 
                         WHERE oi.product_id = p.id 
                           AND o.created_at >= (NOW() - INTERVAL 14 DAY)) as recent_orders_count,
                       (SELECT COUNT(DISTINCT pv.user_id) 
                          FROM product_views pv 
                         WHERE pv.product_id = p.id 
                           AND pv.user_id IS NOT NULL 
                           AND pv.viewed_at >= (NOW() - INTERVAL 14 DAY)) as recent_views_count
                FROM products p 
                JOIN seller_profiles sp ON p.seller_id = sp.user_id 
                LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id 
                WHERE p.status = 'approved' AND sp.is_approved = TRUE";
        
        $params = [];
        $conditions = [];
        
        // Apply filters
        if (!empty($filters['category'])) {
            $conditions[] = "p.category_id = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['organic'])) {
            $conditions[] = "pad.is_organic = ?";
            $params[] = 1;
        }
        
        if (!empty($filters['min_price'])) {
            $conditions[] = "p.price_per_unit >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $conditions[] = "p.price_per_unit <= ?";
            $params[] = $filters['max_price'];
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        // Apply sorting.
        // Default ("popular") ranks products by real recent engagement:
        // purchases + distinct registered-user views in the last 14 days
        // (a product with 5 views from the same user still only counts as 1 view).
        // Products with no recent engagement simply fall back to newest-first.
        $popularityOrder = "(recent_orders_count + recent_views_count) DESC, p.created_at DESC";
        $orderBy = $popularityOrder;
        switch ($sort) {
            case 'price_low':
                $orderBy = "p.price_per_unit ASC";
                break;
            case 'price_high':
                $orderBy = "p.price_per_unit DESC";
                break;
            case 'newest':
                $orderBy = "p.created_at DESC";
                break;
            case 'popular':
            default:
                $orderBy = $popularityOrder;
                break;
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM ($sql) as filtered_products";
        $total = $this->db->fetchOne($countSql, $params)['total'];
        
        // Get paginated results
        $sql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $products = $this->db->fetchAll($sql, $params);
        $total_pages = ceil($total / $limit);
        
        return [
            'products' => $products,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => floor($offset / $limit) + 1
        ];
    }
}
?>