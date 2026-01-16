<?php
class User {
    private $db;
    public $id, $email, $role, $first_name, $last_name, $phone;

    public function __construct($db) {
        $this->db = $db;
    }

    public function login($email, $password) {
        $user = $this->db->fetchOne(
            "SELECT id, email, password_hash, role, first_name, last_name, phone, is_active 
             FROM users WHERE email = ?", 
            [$email]
        );

        if ($user && password_verify($password, $user['password_hash']) && $user['is_active']) {
            $this->id = $user['id'];
            $this->email = $user['email'];
            $this->role = $user['role'];
            $this->first_name = $user['first_name'];
            $this->last_name = $user['last_name'];
            $this->phone = $user['phone'];
            
            $this->updateLastLogin();
            return true;
        }
        return false;
    }

    public function register($userData) {
        $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        unset($userData['password']);
        
        return $this->db->insert('users', $userData);
    }

    public function getProfile($userId) {
        return $this->db->fetchOne(
            "SELECT u.*, sp.business_name, sp.business_description 
             FROM users u 
             LEFT JOIN seller_profiles sp ON u.id = sp.user_id 
             WHERE u.id = ?", 
            [$userId]
        );
    }

    public function updateProfile($userId, $data) {
        return $this->db->update('users', $data, 'id = ?', [$userId]);
    }

    private function updateLastLogin() {
        $this->db->query(
            "UPDATE users SET last_login = NOW() WHERE id = ?", 
            [$this->id]
        );
    }

    public function isSellerApproved($userId) {
        $result = $this->db->fetchOne(
            "SELECT is_approved FROM seller_profiles WHERE user_id = ?", 
            [$userId]
        );
        return $result ? $result['is_approved'] : false;
    }
}
?>