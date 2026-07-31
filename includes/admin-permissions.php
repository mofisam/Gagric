<?php
/**
 * Admin permission system.
 *
 * Every admin page belongs to a "module" (matching the sidebar's sections).
 * A super-admin (users.is_super_admin = 1) always has full access. A regular
 * admin only sees/can access the modules listed in their admin_permissions
 * JSON column, managed via admin/users/admin-permissions.php.
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/functions.php';

function getAdminPermissionModules() {
    return [
        'users' => ['label' => 'Users & Sellers', 'icon' => 'bi-people'],
        'products' => ['label' => 'Products', 'icon' => 'bi-box-seam'],
        'orders' => ['label' => 'Orders', 'icon' => 'bi-cart-check'],
        'contacts' => ['label' => 'Contact Messages', 'icon' => 'bi-envelope'],
        'financial' => ['label' => 'Financial (Payments, Payouts, Commissions)', 'icon' => 'bi-cash-coin'],
        'subscriptions' => ['label' => 'Subscriptions & Coupons', 'icon' => 'bi-star'],
        'reports' => ['label' => 'Reports & Analytics', 'icon' => 'bi-graph-up'],
        'settings' => ['label' => 'Settings & System Logs', 'icon' => 'bi-gear'],
    ];
}

/**
 * Fetches the current admin's access (super-admin flag + permission list)
 * fresh from the database. Cached with a static variable so repeated calls
 * within the same request (e.g. one in requireAdminPermission(), others
 * while rendering the sidebar) don't re-query, but nothing persists across
 * requests - so a super-admin changing someone's permissions takes effect
 * on that admin's very next page load, no re-login required.
 */
function getCurrentAdminAccess() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        $cached = ['is_super_admin' => false, 'permissions' => []];
        return $cached;
    }

    $db = new Database();
    $row = $db->fetchOne(
        "SELECT is_super_admin, admin_permissions FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );

    $permissions = [];
    if (!empty($row['admin_permissions'])) {
        $decoded = json_decode($row['admin_permissions'], true);
        if (is_array($decoded)) {
            $permissions = $decoded;
        }
    }

    $cached = [
        'is_super_admin' => !empty($row['is_super_admin']),
        'permissions' => $permissions,
    ];
    return $cached;
}

function adminIsSuperAdmin() {
    return getCurrentAdminAccess()['is_super_admin'];
}

function adminHasPermission($module) {
    $access = getCurrentAdminAccess();
    return $access['is_super_admin'] || in_array($module, $access['permissions'], true);
}

/**
 * Call this right after requireAdmin() on any page that belongs to a
 * specific module. Redirects to the dashboard with an explanation if the
 * logged-in admin isn't permitted to access it.
 */
function requireAdminPermission($module) {
    if (!adminHasPermission($module)) {
        setFlashMessage("You don't have permission to access that section. Contact a super-admin if you need access.", 'error');
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}