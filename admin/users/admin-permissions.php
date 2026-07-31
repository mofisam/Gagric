<?php
require_once '../../includes/auth.php';
requireAdmin();

if (!adminIsSuperAdmin()) {
    setFlashMessage("Only a super-admin can manage admin permissions.", 'error');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

require_once '../../classes/Database.php';

$db = new Database();
$current_admin_id = $_SESSION['user_id'];
$modules = getAdminPermissionModules();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $target_admin_id = (int)($_POST['admin_id'] ?? 0);
        $target_admin = $db->fetchOne("SELECT id, is_super_admin FROM users WHERE id = ? AND role = 'admin'", [$target_admin_id]);

        if (!$target_admin) {
            $errors[] = 'Admin account not found.';
        } elseif (!empty($target_admin['is_super_admin'])) {
            $errors[] = 'Super-admins always have full access - there is nothing to configure for this account.';
        } else {
            $selected = array_intersect($_POST['permissions'] ?? [], array_keys($modules));
            $db->update(
                'users',
                ['admin_permissions' => json_encode(array_values($selected))],
                'id = ?',
                [$target_admin_id]
            );
            setFlashMessage('Permissions updated.', 'success');
            header('Location: admin-permissions.php?admin_id=' . $target_admin_id);
            exit;
        }
    }
}

$admins = $db->fetchAll("
    SELECT id, first_name, last_name, email, is_super_admin, admin_permissions, created_at
    FROM users
    WHERE role = 'admin'
    ORDER BY is_super_admin DESC, first_name ASC
");

$selected_admin_id = (int)($_GET['admin_id'] ?? ($admins[0]['id'] ?? 0));
$selected_admin = null;
$selected_permissions = [];
foreach ($admins as $a) {
    if ($a['id'] == $selected_admin_id) {
        $selected_admin = $a;
        $selected_permissions = !empty($a['admin_permissions']) ? (json_decode($a['admin_permissions'], true) ?: []) : [];
        break;
    }
}

$page_title = "Admin Permissions";
$page_css = 'dashboard.css';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Admin Permissions</h1>
                    <p class="text-muted mb-0">Control which sections each admin account can access</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <!-- Admin list -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0">Admin Accounts</h6>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($admins as $a): ?>
                                <a href="?admin_id=<?php echo $a['id']; ?>"
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $a['id'] == $selected_admin_id ? 'active' : ''; ?>">
                                    <span>
                                        <?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?>
                                        <br><small class="<?php echo $a['id'] == $selected_admin_id ? 'text-white-50' : 'text-muted'; ?>"><?php echo htmlspecialchars($a['email']); ?></small>
                                    </span>
                                    <?php if ($a['is_super_admin']): ?>
                                        <span class="badge bg-warning text-dark">Super Admin</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Permission editor -->
                <div class="col-lg-8">
                    <?php if ($selected_admin): ?>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="mb-0">
                                    Permissions for <?php echo htmlspecialchars($selected_admin['first_name'] . ' ' . $selected_admin['last_name']); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($selected_admin['is_super_admin'])): ?>
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-shield-check me-2"></i>
                                        This account is a <strong>super-admin</strong> and always has full access to every section. There's nothing to configure here.
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                        <input type="hidden" name="admin_id" value="<?php echo $selected_admin['id']; ?>">

                                        <p class="text-muted small">Check every section this admin should be able to see and use. Unchecked sections are hidden from their sidebar and blocked if they try to access the URL directly.</p>

                                        <div class="row g-2 mb-4">
                                            <?php foreach ($modules as $key => $mod): ?>
                                                <div class="col-md-6">
                                                    <div class="form-check p-3 border rounded">
                                                        <input class="form-check-input" type="checkbox" name="permissions[]"
                                                               value="<?php echo $key; ?>" id="perm_<?php echo $key; ?>"
                                                               <?php echo in_array($key, $selected_permissions, true) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label w-100" for="perm_<?php echo $key; ?>">
                                                            <i class="bi <?php echo $mod['icon']; ?> me-2"></i>
                                                            <?php echo htmlspecialchars($mod['label']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-circle me-1"></i> Save Permissions
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">No admin account selected.</div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>