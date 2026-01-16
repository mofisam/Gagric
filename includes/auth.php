<?php
/**
 * Authentication helper functions
 */
require_once __DIR__ . '/../config/constants.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is seller
 */
function isSeller() {
    return hasRole('seller');
}

/**
 * Check if user is buyer
 */
function isBuyer() {
    return hasRole('buyer');
}

/**
 * Require authentication - redirect to login if not logged in
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['flash_message'] = 'Please login to access this page';
        $_SESSION['flash_type'] = 'warning';
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Require specific role - redirect if user doesn't have required role
 */
function requireRole($role) {
    requireAuth();
    
    if (!hasRole($role)) {
        $_SESSION['flash_message'] = 'Access denied. Insufficient permissions.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireRole('admin');
}

/**
 * Require seller access
 */
function requireSeller() {
    requireRole('seller');
}
/**
 * Require buyer access
 */
function requireBuyer() {
    requireRole('buyer');
}
/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Redirect if already logged in
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        $role = getCurrentUserRole();
        if ($role === 'admin') {
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
        } elseif ($role === 'seller') {
            header('Location: ' . BASE_URL . '/seller/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/index.php');
        }
        exit;
    }
}

/**
 * Set flash message for next request
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get CSRF token
 */
function getCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}