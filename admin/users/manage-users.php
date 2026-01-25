<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = new Database();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search and filter
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
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

$page_title = "Manage Users";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Users</h1>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search users..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="buyer" <?php echo $role === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                <option value="seller" <?php echo $role === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary">Filter</button>
                            <a href="manage-users.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                        <?php if ($user['role'] === 'seller' && $user['business_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($user['business_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['role'] === 'admin' ? 'danger' : 
                                                 ($user['role'] === 'seller' ? 'success' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                        <?php if ($user['role'] === 'seller'): ?>
                                            <br>
                                            <small class="badge bg-<?php echo $user['seller_approved'] ? 'success' : 'warning'; ?>">
                                                <?php echo $user['seller_approved'] ? 'Approved' : 'Pending'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="user-details.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-primary">View</a>
                                            <?php if (!$user['is_active']): ?>
                                                <a href="?activate=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-success">Activate</a>
                                            <?php else: ?>
                                                <a href="?deactivate=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-warning">Deactivate</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>