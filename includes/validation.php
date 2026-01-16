<?php
/**
 * Input validation functions
 */

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Nigerian phone number
 */
function validatePhone($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Nigerian phone numbers: +2348012345678 or 08012345678
    if (strlen($phone) === 11 && in_array($phone[0], ['0', '7', '8'])) {
        return preg_match('/^(0|7|8)[0-9]{10}$/', $phone);
    } elseif (strlen($phone) === 13 && strpos($phone, '234') === 0) {
        return preg_match('/^234[0-9]{10}$/', $phone);
    }
    
    return false;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

/**
 * Validate product data
 */
function validateProduct($data) {
    $errors = [];
    
    $required_fields = ['name', 'description', 'price_per_unit', 'category_id', 'unit'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (isset($data['price_per_unit']) && (!is_numeric($data['price_per_unit']) || $data['price_per_unit'] <= 0)) {
        $errors[] = 'Price must be a positive number';
    }
    
    if (isset($data['stock_quantity']) && (!is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0)) {
        $errors[] = 'Stock quantity must be a non-negative number';
    }
    
    return $errors;
}

/**
 * Validate user registration data
 */
function validateRegistration($data) {
    $errors = [];
    
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'password', 'role'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (isset($data['email']) && !validateEmail($data['email'])) {
        $errors[] = 'Please enter a valid email address';
    }
    
    if (isset($data['phone']) && !validatePhone($data['phone'])) {
        $errors[] = 'Please enter a valid Nigerian phone number';
    }
    
    if (isset($data['password']) && !validatePassword($data['password'])) {
        $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and number';
    }
    
    if (isset($data['role']) && !in_array($data['role'], ['buyer', 'seller'])) {
        $errors[] = 'Invalid role selected';
    }
    
    return $errors;
}

/**
 * Validate address data
 */
function validateAddress($data) {
    $errors = [];
    
    $required_fields = ['state_id', 'lga_id', 'city_id', 'address_line'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    return $errors;
}

/**
 * Sanitize and validate file upload
 */
function validateFileUpload($file, $allowed_types, $max_size) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds maximum allowed size';
    }
    
    // Check file type
    $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types);
    }
    
    return $errors;
}

/**
 * Validate order data
 */
function validateOrder($data) {
    $errors = [];
    
    if (empty($data['items']) || !is_array($data['items'])) {
        $errors[] = 'Order must contain at least one item';
    }
    
    if (empty($data['shipping_address'])) {
        $errors[] = 'Shipping address is required';
    }
    
    return $errors;
}

/**
 * Escape HTML output
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate CSRF token
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}