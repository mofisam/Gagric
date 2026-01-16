<?php
class Validation {
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function phone($phone) {
        // Nigerian phone validation
        return preg_match('/^(\+234|0)[789][01]\d{8}$/', $phone);
    }

    public static function password($password) {
        return strlen($password) >= 8;
    }

    public static function nigerianState($state) {
        $validStates = ['Lagos', 'Abuja', 'Rivers', 'Kano', 'Oyo', 'Ogun'];
        return in_array($state, $validStates);
    }

    public static function productData($data) {
        $required = ['name', 'description', 'price_per_unit', 'category_id', 'unit'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        return is_numeric($data['price_per_unit']) && $data['price_per_unit'] > 0;
    }

    public static function fileUpload($file, $allowedTypes, $maxSize) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($fileType, $allowedTypes) && $file['size'] <= $maxSize;
    }

    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
?>