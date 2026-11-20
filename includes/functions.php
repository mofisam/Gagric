<?php
/**
 * General helper functions for Green Agric
 */

/**
 * Work out a seller's badge tier from their real rating data. Requires a
 * minimum review count at each tier so a single 5-star review can't jump
 * someone straight to Platinum - reputation has to be earned over a few
 * genuine reviews, not just a lucky first one.
 */
function calculateSellerLevel($avg_rating, $review_count) {
    $avg_rating = (float) $avg_rating;
    $review_count = (int) $review_count;

    if ($avg_rating >= 4.8 && $review_count >= 10) {
        return 'Platinum';
    }
    if ($avg_rating >= 4.3 && $review_count >= 5) {
        return 'Gold';
    }
    if ($avg_rating >= 3.5 && $review_count >= 3) {
        return 'Silver';
    }
    return 'Bronze';
}

/**
 * Store a product image resized and compressed for faster page delivery.
 * Returns the saved filename, or false if the image cannot be processed.
 */
function saveOptimizedProductImage($uploadedFile, $uploadPath, $filenamePrefix) {
    if (empty($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
        return false;
    }

    $imageInfo = @getimagesize($uploadedFile['tmp_name']);
    if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return false;
    }

    $imageType = $imageInfo[2];
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (defined('IMAGETYPE_WEBP')) {
        $extensions[IMAGETYPE_WEBP] = 'webp';
    }
    if (!isset($extensions[$imageType])) {
        return false;
    }

    if (!is_dir($uploadPath) && !mkdir($uploadPath, 0755, true) && !is_dir($uploadPath)) {
        return false;
    }

    $loaders = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
    ];
    if (defined('IMAGETYPE_WEBP')) {
        $loaders[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    }

    if (!isset($loaders[$imageType]) || !function_exists($loaders[$imageType])) {
        $filename = $filenamePrefix . '.' . $extensions[$imageType];
        return move_uploaded_file($uploadedFile['tmp_name'], rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $filename)
            ? $filename
            : false;
    }

    $source = @$loaders[$imageType]($uploadedFile['tmp_name']);
    if (!$source) {
        return false;
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    $maxDimension = 1600;
    $scale = min(1, $maxDimension / max($width, $height));
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $output = imagecreatetruecolor($targetWidth, $targetHeight);

    $preserveAlpha = in_array($imageType, [IMAGETYPE_PNG, defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1], true);
    if ($preserveAlpha) {
        imagealphablending($output, false);
        imagesavealpha($output, true);
        $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefilledrectangle($output, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    imagecopyresampled($output, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    if (function_exists('imagewebp')) {
        $filename = $filenamePrefix . '.webp';
        $saved = imagewebp($output, rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $filename, 82);
    } elseif ($imageType === IMAGETYPE_JPEG) {
        $filename = $filenamePrefix . '.jpg';
        $saved = imagejpeg($output, rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $filename, 82);
    } elseif ($imageType === IMAGETYPE_PNG) {
        $filename = $filenamePrefix . '.png';
        $saved = imagepng($output, rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $filename, 6);
    } else {
        $filename = $filenamePrefix . '.webp';
        $saved = move_uploaded_file(
            $uploadedFile['tmp_name'],
            rtrim($uploadPath, '/\\') . DIRECTORY_SEPARATOR . $filename
        );
    }

    imagedestroy($source);
    imagedestroy($output);
    return $saved ? $filename : false;
}

/**
 * Format currency for Nigerian Naira
 */
function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}

/**
 * Render a star-rating HTML snippet from real avg_rating / review_count values
 * (e.g. from the seller_ratings table), instead of a hardcoded placeholder rating.
 * Returns "No reviews yet" when there is no rating data.
 */
function renderStarRating($avg_rating, $review_count = 0) {
    $review_count = (int) $review_count;

    if ($review_count <= 0 || $avg_rating === null) {
        return '<div class="product-rating text-muted small">'
            . '<i class="bi bi-star"></i> No reviews yet'
            . '</div>';
    }

    $avg_rating = (float) $avg_rating;
    $full_stars = (int) floor($avg_rating);
    $has_half = ($avg_rating - $full_stars) >= 0.25 && ($avg_rating - $full_stars) < 0.75;
    if (($avg_rating - $full_stars) >= 0.75) {
        $full_stars++;
        $has_half = false;
    }
    $empty_stars = 5 - $full_stars - ($has_half ? 1 : 0);

    $html = '<div class="product-rating">';
    $html .= str_repeat('<i class="bi bi-star-fill"></i>', max(0, $full_stars));
    if ($has_half) {
        $html .= '<i class="bi bi-star-half"></i>';
    }
    $html .= str_repeat('<i class="bi bi-star"></i>', max(0, $empty_stars));
    $html .= ' <small class="text-muted">' . number_format($avg_rating, 1)
        . ' (' . $review_count . ')</small>';
    $html .= '</div>';

    return $html;
}

/**
 * Format a decimal quantity for display: "1.00" -> "1", "2.00" -> "2",
 * but "2.01", "1.50", etc. are left with their decimal places intact.
 */
function formatQuantity($value) {
    $formatted = number_format((float) $value, 2, '.', '');
    if (substr($formatted, -3) === '.00') {
        return substr($formatted, 0, -3);
    }
    return $formatted;
}

/**
 * Format date in a readable way
 */
function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Calculate days until expiry
 */
function daysUntilExpiry($expiry_date) {
    if (empty($expiry_date)) return null;
    
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $interval = $today->diff($expiry);
    
    return $interval->days;
}

/**
 * Get product status badge
 */
function getStatusBadge($status) {
    $badges = [
        'draft' => 'secondary',
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'suspended' => 'dark'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

/**
 * Get order status badge
 */
function getOrderStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'processing' => 'primary',
        'shipped' => 'info',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'refunded' => 'secondary'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Upload file with validation
 */
function uploadFile($file, $allowed_types, $max_size, $upload_path) {
    $errors = [];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed with error code: ' . $file['error'];
        return [false, $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds maximum allowed size of ' . ($max_size / 1024 / 1024) . 'MB';
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types);
    }
    
    if (!empty($errors)) {
        return [false, $errors];
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $filepath = $upload_path . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [true, $filename];
    } else {
        return [false, ['Failed to move uploaded file']];
    }
}

/**
 * Calculate commission amount
 */
function calculateCommission($amount, $commission_rate = null) {
    if ($commission_rate === null) {
        $commission_rate = COMMISSION_RATE;
    }
    return ($amount * $commission_rate) / 100;
}


/**
 * Generate pagination
 */
function generatePagination($current_page, $total_pages, $url_pattern) {
    if ($total_pages <= 1) return '';
    
    $pagination = '<nav><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($current_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page - 1) . '">Previous</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $pagination .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page + 1) . '">Next</a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $pagination .= '</ul></nav>';
    return $pagination;
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $details = null) {
    $db = new Database();
    $db->query(
        "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
        [$user_id, $action, $details, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']
    );
}

/**
 * Create a URL-friendly slug from a string
 * 
 * @param string $string The string to convert to slug
 * @param int $max_length Maximum length of the slug (default: 100)
 * @return string The generated slug
 */
function createSlug($string, $max_length = 100) {
    // Convert to lowercase
    $slug = strtolower($string);
    
    // Remove special characters and replace spaces with hyphens
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    
    // Trim hyphens from beginning and end
    $slug = trim($slug, '-');
    
    // Limit length
    if (strlen($slug) > $max_length) {
        $slug = substr($slug, 0, $max_length);
        $slug = preg_replace('/-[^-]*$/', '', $slug); // Remove last partial word
    }
    
    // If slug is empty, generate a random one
    if (empty($slug)) {
        $slug = 'product-' . uniqid();
    }
    
    return $slug;
}
