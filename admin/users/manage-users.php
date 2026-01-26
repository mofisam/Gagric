<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = new Database();

// Handle user actions
if (isset($_GET['activate'])) {
    $user_id = (int)$_GET['activate'];
    $db->query("UPDATE users SET is_active = TRUE WHERE id = ?", [$user_id]);
    setFlashMessage('User activated successfully', 'success');
    header('Location: manage-users.php');
    exit;
}

if (isset($_GET['deactivate'])) {
    $user_id = (int)$_GET['deactivate'];
    $db->query("UPDATE users SET is_active = FALSE WHERE id = ?", [$user_id]);
    setFlashMessage('User deactivated successfully', 'warning');
    header('Location: manage-users.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Search and filter
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR sp.business_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($role)) {
    $where .= " AND u.role = ?";
    $params[] = $role;
}

if (!empty($status)) {
    if ($status === 'active') {
        $where .= " AND u.is_active = TRUE";
    } elseif ($status === 'inactive') {
        $where .= " AND u.is_active = FALSE";
    }
}

// Get users
$users = $db->fetchAll("
    SELECT u.*, sp.business_name, sp.is_approved as seller_approved 
    FROM users u 
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id 
    $where 
    ORDER BY u.created_at DESC 
    LIMIT $limit OFFSET $offset
", $params);

// Total count for pagination
$total_users = $db->fetchOne("SELECT COUNT(*) as count FROM users u $where", $params)['count'];
$total_pages = ceil($total_users / $limit);

// Stats
$stats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
    'total_buyers' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'buyer' AND is_active = TRUE")['count'],
    'total_sellers' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'seller' AND is_active = TRUE")['count'],
    'active_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE")['count'],
    'inactive_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = FALSE")['count'],
    'today_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")['count']
];

$page_title = "Manage Users";
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile page header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-2" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="flex-grow-1">
                        <h1 class="h5 mb-0 text-center">User Management</h1>
                        <small class="text-muted d-block text-center"><?php echo $stats['total_users']; ?> total users</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshUsers">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Users</h1>
                    <p class="text-muted mb-0">Manage platform users and permissions</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportUsers()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshUsers">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <a href="add-user.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-person-plus me-1"></i> Add User
                    </a>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Total</small>
                                <h6 class="mb-0"><?php echo number_format($stats['total_users']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Buyers</small>
                                <h6 class="mb-0 text-info"><?php echo number_format($stats['total_buyers']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Sellers</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['total_sellers']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Active</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['active_users']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="row g-3 mb-4">
                <!-- Total Users -->
                <div class="col-md-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Users</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo $stats['today_users']; ?> joined today
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-people fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Users -->
                <div class="col-md-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Active Users</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['active_users']); ?></h3>
                                    <small class="text-success">
                                        <?php echo $stats['total_users'] > 0 ? number_format(($stats['active_users'] / $stats['total_users']) * 100, 1) : 0; ?>% active rate
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-check-circle fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Buyers -->
                <div class="col-md-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Buyers</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_buyers']); ?></h3>
                                    <small class="text-info">
                                        <?php echo $stats['total_buyers'] > 0 ? number_format(($stats['total_buyers'] / $stats['total_users']) * 100, 1) : 0; ?>% of users
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-cart fs-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sellers -->
                <div class="col-md-3">
                    <div class="dashboard-card card shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Sellers</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_sellers']); ?></h3>
                                    <small class="text-warning">
                                        <?php echo $stats['total_sellers'] > 0 ? number_format(($stats['total_sellers'] / $stats['total_users']) * 100, 1) : 0; ?>% of users
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-shop fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Filter Users
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
                <div class="collapse d-md-block" id="filterCollapse">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-12 col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search name, email, phone..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="role" class="form-select form-select-sm">
                                    <option value="">All Roles</option>
                                    <option value="buyer" <?php echo $role === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                    <option value="seller" <?php echo $role === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-filter me-1"></i> Filter
                                </button>
                            </div>
                            <div class="col-6 col-md-2">
                                <a href="manage-users.php" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="bi bi-x-circle me-1"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">Users (<?php echo $total_users; ?>)</h5>
                    <small class="text-muted">
                        <?php echo $total_users; ?> total • 
                        Showing <?php echo min($offset + 1, $total_users); ?>-<?php echo min($offset + $limit, $total_users); ?>
                    </small>
                </div>
                <div class="text-muted d-none d-md-block">
                    <?php if($stats['inactive_users'] > 0): ?>
                        <span class="badge bg-warning">
                            <i class="bi bi-person-x me-1"></i>
                            <?php echo $stats['inactive_users']; ?> inactive
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No users found</h4>
                            <p class="text-muted mb-4">No users match your current filters.</p>
                            <a href="manage-users.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th>User</th>
                                        <th width="120">Role</th>
                                        <th width="100">Status</th>
                                        <th width="120">Registered</th>
                                        <th width="100" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <?php 
                                        $role_colors = [
                                            'admin' => 'danger',
                                            'seller' => 'success',
                                            'buyer' => 'info'
                                        ];
                                        
                                        $status_colors = [
                                            'active' => 'success',
                                            'inactive' => 'secondary'
                                        ];
                                        
                                        $user_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                        $user_email = htmlspecialchars($user['email']);
                                        $user_phone = htmlspecialchars($user['phone']);
                                        ?>
                                        
                                        <tr class="user-row" data-user-id="<?php echo $user['id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-<?php echo $status_colors[$user['is_active'] ? 'active' : 'inactive']; ?> bg-opacity-10 p-2 rounded-circle me-3">
                                                        <i class="bi bi-person text-<?php echo $status_colors[$user['is_active'] ? 'active' : 'inactive']; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo $user_name; ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo $user_email; ?></small>
                                                        <?php if ($user['role'] === 'seller' && $user['business_name']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="bi bi-shop me-1"></i>
                                                                <?php echo htmlspecialchars($user['business_name']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $role_colors[$user['role']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                                <?php if ($user['role'] === 'seller'): ?>
                                                    <br>
                                                    <small class="badge bg-<?php echo $user['seller_approved'] ? 'success' : 'warning'; ?> mt-1">
                                                        <?php echo $user['seller_approved'] ? 'Approved' : 'Pending'; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $status_colors[$user['is_active'] ? 'active' : 'inactive']; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="user-details.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (!$user['is_active']): ?>
                                                        <a href="?activate=<?php echo $user['id']; ?>" 
                                                           class="btn btn-outline-success"
                                                           onclick="return confirm('Activate this user?')">
                                                            <i class="bi bi-check"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?deactivate=<?php echo $user['id']; ?>" 
                                                           class="btn btn-outline-warning"
                                                           onclick="return confirm('Deactivate this user?')">
                                                            <i class="bi bi-x"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: User Info & Status -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $status_colors[$user['is_active'] ? 'active' : 'inactive']; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <i class="bi bi-person text-<?php echo $status_colors[$user['is_active'] ? 'active' : 'inactive']; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars(substr($user_name, 0, 20)); ?></strong>
                                                                <br>
                                                                <span class="badge bg-<?php echo $role_colors[$user['role']] ?? 'secondary'; ?>">
                                                                    <?php echo ucfirst($user['role']); ?>
                                                                </span>
                                                                <?php if ($user['role'] === 'seller' && $user['business_name']): ?>
                                                                    <span class="badge bg-<?php echo $user['seller_approved'] ? 'success' : 'warning'; ?> ms-1">
                                                                        <?php echo $user['seller_approved'] ? 'Approved' : 'Pending'; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <span class="badge bg-<?php echo $status_colors[$user['is_active'] ? 'active' : 'inactive']; ?>">
                                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <!-- Row 2: Contact Details -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <small class="text-muted d-block">Email:</small>
                                                                <small><?php echo htmlspecialchars(substr($user_email, 0, 20)); ?>...</small>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted d-block">Phone:</small>
                                                                <small><?php echo $user_phone; ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Date & Actions -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                                        </small>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="user-details.php?id=<?php echo $user['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if (!$user['is_active']): ?>
                                                                <a href="?activate=<?php echo $user['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-success"
                                                                   onclick="return confirm('Activate this user?')">
                                                                    <i class="bi bi-check"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="?deactivate=<?php echo $user['id']; ?>" 
                                                                   class="btn btn-sm btn-outline-warning"
                                                                   onclick="return confirm('Deactivate this user?')">
                                                                    <i class="bi bi-x"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                    <span class="d-none d-md-inline">Previous</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Mobile: Simple pagination -->
                        <div class="d-md-none">
                            <li class="page-item disabled">
                                <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            </li>
                        </div>
                        
                        <!-- Desktop: Full pagination -->
                        <div class="d-none d-md-flex">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <span class="d-none d-md-inline">Next</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- User Actions Modal (for bulk actions) -->
<div class="modal fade" id="userActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Action</label>
                    <select class="form-select" id="bulkAction">
                        <option value="">Choose action...</option>
                        <option value="activate">Activate Selected Users</option>
                        <option value="deactivate">Deactivate Selected Users</option>
                        <option value="delete">Delete Selected Users</option>
                    </select>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    This action will affect all selected users.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="performBulkAction()">
                    <i class="bi bi-play me-1"></i> Execute Action
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (window.dashboardSidebar) {
                window.dashboardSidebar.toggle();
            }
        });
    }
    
    // Refresh users
    const refreshBtn = document.getElementById('refreshUsers');
    const mobileRefreshBtn = document.getElementById('mobileRefreshUsers');
    
    function refreshPage() {
        const btn = event?.target?.closest('button');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            btn.disabled = true;
        }
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
    
    if (refreshBtn) refreshBtn.addEventListener('click', refreshPage);
    if (mobileRefreshBtn) mobileRefreshBtn.addEventListener('click', refreshPage);
    
    // Make table rows clickable on mobile to show details
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('a')) {
                const userId = this.closest('.user-row').dataset.userId;
                window.location.href = 'user-details.php?id=' + userId;
            }
        });
    });
});

function exportUsers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = 'export-users.php?' + params.toString();
    link.download = 'users-export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    agriApp.showToast('Export started', 'info');
}

function performBulkAction() {
    const action = document.getElementById('bulkAction').value;
    if (!action) {
        agriApp.showToast('Please select an action', 'error');
        return;
    }
    
    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedUsers.length === 0) {
        agriApp.showToast('Please select at least one user', 'error');
        return;
    }
    
    if (confirm(`Are you sure you want to ${action} ${selectedUsers.length} user(s)?`)) {
        // In real app, send AJAX request
        fetch('bulk-user-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                users: selectedUsers
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                agriApp.showToast(`Action completed on ${data.updated} user(s)`, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                agriApp.showToast('Action failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            agriApp.showToast('Network error. Please try again.', 'error');
        });
    }
}

// Add CSS for mobile table
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Mobile Table Styles */
    .mobile-table-row {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
        cursor: pointer;
    }
    
    .mobile-table-row:last-child {
        border-bottom: none;
    }
    
    @media (max-width: 767.98px) {
        .mobile-optimized-table {
            border: 0;
        }
        
        .mobile-optimized-table thead {
            display: none;
        }
        
        .mobile-optimized-table tr {
            display: block;
            margin-bottom: 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: white;
        }
        
        .mobile-optimized-table td {
            display: block;
            padding: 0 !important;
            border: none;
        }
        
        .mobile-optimized-table td.d-md-none {
            display: block !important;
        }
        
        .mobile-optimized-table td.d-none {
            display: none !important;
        }
        
        /* Touch-friendly buttons */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            min-height: 36px;
            min-width: 36px;
        }
        
        /* Modal optimization */
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .modal-content {
            border-radius: 0.5rem;
        }
        
        /* Better mobile header */
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Compact filters */
        .form-select-sm, .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Status badges compact */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .user-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .user-row:hover .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        }
    }

`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>