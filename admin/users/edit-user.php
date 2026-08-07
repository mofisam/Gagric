<?php
require_once '../../includes/auth.php';
requireAdmin();
requireAdminPermission('users');
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';

$db = new Database();

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    setFlashMessage('User ID required', 'error');
    header('Location: manage-users.php');
    exit;
}

$current_admin_id = $_SESSION['user_id'];
$errors = [];

$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

if (!$user) {
    setFlashMessage('User not found', 'error');
    header('Location: manage-users.php');
    exit;
}

if (!empty($user['deleted_at'])) {
    setFlashMessage('This user has been deleted and can\'t be edited. Restore them first from the Deleted filter.', 'error');
    header('Location: manage-users.php');
    exit;
}

$is_self = ((int)$user['id'] === (int)$current_admin_id);
$target_is_admin = $user['role'] === 'admin';

// Only a super-admin can edit another admin account.
if ($target_is_admin && !adminIsSuperAdmin()) {
    setFlashMessage('Only a super-admin can edit an admin account.', 'error');
    header('Location: manage-users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $new_role = $_POST['role'] ?? $user['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
            $errors[] = 'Name, email, and phone are required.';
        }
        if (!empty($email) && !validateEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!empty($phone) && !validatePhone($phone)) {
            $errors[] = 'Please enter a valid Nigerian phone number.';
        }
        if (!in_array($new_role, ['buyer', 'seller', 'admin'], true)) {
            $errors[] = 'Please select a valid role.';
        }

        // Same privilege-escalation guard used when creating a new admin:
        // only a super-admin can promote to admin or change an existing
        // admin's role, and nobody can change their own role here.
        if ($new_role !== $user['role']) {
            if ($is_self) {
                $errors[] = "You can't change your own role.";
            } elseif (($new_role === 'admin' || $user['role'] === 'admin') && !adminIsSuperAdmin()) {
                $errors[] = 'Only a super-admin can change a role to or from admin.';
            }
        }

        if ($is_self && !$is_active) {
            $errors[] = "You can't deactivate your own account.";
        }

        if (empty($errors)) {
            $duplicate = $db->fetchOne(
                "SELECT id FROM users WHERE (email = ? OR phone = ?) AND id != ?",
                [$email, $phone, $user_id]
            );
            if ($duplicate) {
                $errors[] = 'Another user already has that email or phone number.';
            }
        }

        if (empty($errors)) {
            $db->update('users', [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'role' => $new_role,
                'is_active' => $is_active,
            ], 'id = ?', [$user_id]);

            setFlashMessage('User updated successfully.', 'success');
            header('Location: manage-users.php');
            exit;
        }

        // Keep the form showing what was submitted, not the stale DB values
        $user = array_merge($user, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'role' => $new_role,
            'is_active' => $is_active,
        ]);
    }
}

$page_title = "Edit User - " . $user['first_name'] . ' ' . $user['last_name'];
$page_css = 'dashboard.css';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Edit User</h1>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                </div>
                <div class="btn-toolbar">
                    <a href="user-details.php?id=<?php echo $user_id; ?>" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-eye me-1"></i> View Details
                    </a>
                    <a href="manage-users.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Users
                    </a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <?php if ($is_self): ?>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                                    <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                                    <div class="form-text">You can't change your own role.</div>
                                <?php elseif (!adminIsSuperAdmin() && $user['role'] !== 'admin'): ?>
                                    <select class="form-select" id="role" name="role">
                                        <option value="buyer" <?php echo $user['role'] === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                        <option value="seller" <?php echo $user['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                    </select>
                                    <div class="form-text">Only a super-admin can promote a user to admin.</div>
                                <?php else: ?>
                                    <select class="form-select" id="role" name="role">
                                        <option value="buyer" <?php echo $user['role'] === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                        <option value="seller" <?php echo $user['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                           value="1" <?php echo $user['is_active'] ? 'checked' : ''; ?>
                                           <?php echo $is_self ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Account active</label>
                                    <?php if ($is_self): ?>
                                        <input type="hidden" name="is_active" value="1">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success mt-4">
                            <i class="bi bi-check-circle me-1"></i> Save Changes
                        </button>
                        <a href="manage-users.php" class="btn btn-outline-secondary mt-4">Cancel</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>