<?php
require_once '../../includes/auth.php';
requireAdmin();

if (!adminHasPermission('users')) {
    setFlashMessage("You do not have the permission to view this page.", 'error');
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';

$db = new Database();
$user = new User($db);

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$errors = [];
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'date_of_birth' => '',
    'role' => 'buyer',
    'business_name' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Security validation failed. Please refresh the page and try again.';
    } else {
        $form_data['first_name'] = trim($_POST['first_name'] ?? '');
        $form_data['last_name'] = trim($_POST['last_name'] ?? '');
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['phone'] = trim($_POST['phone'] ?? '');
        $form_data['date_of_birth'] = trim($_POST['date_of_birth'] ?? '');
        $form_data['role'] = $_POST['role'] ?? 'buyer';
        $form_data['business_name'] = trim($_POST['business_name'] ?? '');

        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_email_verified = isset($_POST['is_email_verified']) ? 1 : 0;
        $seller_approved = isset($_POST['seller_approved']) ? 1 : 0;

        // --- Validation (mirrors auth/register.php so admin-created accounts
        // behave identically to self-registered ones) ---
        if (empty($form_data['first_name']) || empty($form_data['last_name']) ||
            empty($form_data['email']) || empty($form_data['phone']) ||
            empty($form_data['date_of_birth']) || empty($password)) {
            $errors[] = 'All required fields must be filled.';
        }

        if (!in_array($form_data['role'], ['buyer', 'seller', 'admin'], true)) {
            $errors[] = 'Please select a valid role.';
        }

        $dobDate = null;
        if (!empty($form_data['date_of_birth'])) {
            $dobDate = DateTimeImmutable::createFromFormat('!Y-m-d', $form_data['date_of_birth']);
            $dobErrors = DateTimeImmutable::getLastErrors();
            $hasDobErrors = $dobErrors !== false && ($dobErrors['warning_count'] > 0 || $dobErrors['error_count'] > 0);
            $today = new DateTimeImmutable('today');

            if (!$dobDate || $hasDobErrors || $dobDate->format('Y-m-d') !== $form_data['date_of_birth']) {
                $errors[] = 'Please enter a valid date of birth.';
            } elseif ($dobDate > $today->modify('-18 years')) {
                $errors[] = 'User must be at least 18 years old.';
            }
        }

        if (!empty($form_data['email']) && !validateEmail($form_data['email'])) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (!empty($form_data['phone']) && !validatePhone($form_data['phone'])) {
            $errors[] = 'Please enter a valid Nigerian phone number (e.g., 08012345678 or +2348012345678).';
        }

        if (!empty($password) && !validatePassword($password)) {
            $errors[] = 'Password must be at least 8 characters with at least one uppercase letter, one lowercase letter, and one number.';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }

        if ($form_data['role'] === 'seller' && empty($form_data['business_name'])) {
            $errors[] = 'Business name is required for seller accounts.';
        }

        if (empty($errors)) {
            $existing = $db->fetchOne(
                "SELECT id FROM users WHERE email = ? OR phone = ?",
                [$form_data['email'], $form_data['phone']]
            );

            if ($existing) {
                $errors[] = 'A user with this email or phone number already exists.';
            }
        }

        if ($form_data['role'] === 'seller' && empty($errors)) {
            $existing_business = $db->fetchOne(
                "SELECT id FROM seller_profiles WHERE business_name = ?",
                [$form_data['business_name']]
            );

            if ($existing_business) {
                $errors[] = 'That business name is already in use by another seller.';
            }
        }

        if (empty($errors)) {
            try {
                $db->conn->begin_transaction();

                $new_user_id = $user->register([
                    'uuid' => generateUUID(),
                    'first_name' => $form_data['first_name'],
                    'last_name' => $form_data['last_name'],
                    'email' => $form_data['email'],
                    'phone' => $form_data['phone'],
                    'date_of_birth' => $form_data['date_of_birth'],
                    'password' => $password,
                    'role' => $form_data['role'],
                    'is_active' => $is_active,
                    'is_email_verified' => $is_email_verified,
                ]);

                if (!$new_user_id) {
                    throw new Exception('Failed to create user record.');
                }

                if ($form_data['role'] === 'seller') {
                    $seller_profile_id = $db->insert('seller_profiles', [
                        'user_id' => $new_user_id,
                        'business_name' => $form_data['business_name'],
                        'is_approved' => $seller_approved,
                    ]);

                    if (!$seller_profile_id) {
                        throw new Exception('Failed to create seller profile.');
                    }
                }

                $db->conn->commit();

                setFlashMessage('User "' . htmlspecialchars($form_data['first_name'] . ' ' . $form_data['last_name']) . '" was created successfully.', 'success');
                header('Location: manage-users.php');
                exit;

            } catch (Exception $e) {
                $db->conn->rollback();
                error_log('Admin add-user failed: ' . $e->getMessage());
                $errors[] = 'Something went wrong while creating the user. Please try again.';
            }
        }
    }
}

$page_title = "Add User";
$page_css = 'dashboard.css';
require_once '../../includes/header.php';
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
                        <h1 class="h5 mb-0 text-center">Add User</h1>
                    </div>
                    <a href="manage-users.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>

            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Add User</h1>
                    <p class="text-muted mb-0">Create a new buyer, seller, or admin account</p>
                </div>
                <a href="manage-users.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Users
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" action="" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">

                        <h5 class="mb-3">Account Details</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       placeholder="e.g. 08012345678" value="<?php echo htmlspecialchars($form_data['phone']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                       max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                       value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>" required>
                                <small class="text-muted">Must be 18 years or older.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="buyer" <?php echo $form_data['role'] === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
                                    <option value="seller" <?php echo $form_data['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                    <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>

                        <div id="sellerFields" class="mb-4" style="<?php echo $form_data['role'] === 'seller' ? '' : 'display:none;'; ?>">
                            <h5 class="mb-3">Seller Details</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="business_name" name="business_name"
                                           value="<?php echo htmlspecialchars($form_data['business_name']); ?>">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="seller_approved" name="seller_approved" checked>
                                        <label class="form-check-label" for="seller_approved">
                                            Approve seller immediately
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mb-3">Password</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="password" name="password" required>
                                    <button type="button" class="btn btn-outline-secondary" id="generatePasswordBtn">
                                        <i class="bi bi-shuffle me-1"></i> Generate
                                    </button>
                                </div>
                                <small class="text-muted">At least 8 characters, with an uppercase letter, a lowercase letter, and a number.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <p class="text-muted small mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            Share this password with the user securely - they can change it after logging in.
                        </p>

                        <h5 class="mb-3">Account Status</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Account active</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_email_verified" name="is_email_verified" checked>
                                    <label class="form-check-label" for="is_email_verified">Mark email as verified</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-person-plus me-1"></i> Create User
                        </button>
                        <a href="manage-users.php" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const sellerFields = document.getElementById('sellerFields');
    const businessNameInput = document.getElementById('business_name');

    function toggleSellerFields() {
        if (roleSelect.value === 'seller') {
            sellerFields.style.display = '';
            businessNameInput.setAttribute('required', 'required');
        } else {
            sellerFields.style.display = 'none';
            businessNameInput.removeAttribute('required');
        }
    }

    roleSelect.addEventListener('change', toggleSellerFields);
    toggleSellerFields();

    document.getElementById('generatePasswordBtn').addEventListener('click', function () {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        let pwd = '';
        for (let i = 0; i < 12; i++) {
            pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('password').value = pwd;
        document.getElementById('confirm_password').value = pwd;
    });
});
</script>

<?php include '../../includes/footer.php'; ?>