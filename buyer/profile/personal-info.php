<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Mailer.php';

requireBuyer();

$db = new Database();
$user_id = getCurrentUserId();

// Handle verification email request
if (isset($_GET['verify_email']) && $_GET['verify_email'] == 'send') {
    // Check if already verified
    $user_check = $db->fetchOne("SELECT is_email_verified, email, first_name, last_name FROM users WHERE id = ?", [$user_id]);
    
    if ($user_check['is_email_verified']) {
        $_SESSION['verification_message'] = 'Your email is already verified.';
        $_SESSION['verification_type'] = 'info';
    } else {
        // Generate new verification token
        $verification_token = bin2hex(random_bytes(32));
        $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Save token to database
        $db->query(
            "UPDATE users SET email_verification_token = ?, email_verification_expires = ? WHERE id = ?",
            [$verification_token, $verification_expires, $user_id]
        );
        
        // Send verification email
        try {
            $mailer = new Mailer();
            $verificationLink = BASE_URL . "/auth/verify-email.php?token=" . $verification_token . "&email=" . urlencode($user_check['email']);
            $userName = $user_check['first_name'] . ' ' . $user_check['last_name'];
            
            $email_sent = $mailer->sendEmailVerification($user_check['email'], $userName, $verificationLink);
            
            if ($email_sent) {
                $_SESSION['verification_message'] = 'Verification email has been sent to ' . htmlspecialchars($user_check['email']) . '. Please check your inbox and spam folder.';
                $_SESSION['verification_type'] = 'success';
            } else {
                $_SESSION['verification_message'] = 'Failed to send verification email. Please try again later or contact support.';
                $_SESSION['verification_type'] = 'danger';
            }
        } catch (Exception $e) {
            error_log("Verification email error for user $user_id: " . $e->getMessage());
            $_SESSION['verification_message'] = 'An error occurred while sending the verification email. Please try again.';
            $_SESSION['verification_type'] = 'danger';
        }
    }
    
    header('Location: personal-info.php');
    exit;
}

// Get user data
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
$profile = $db->fetchOne("SELECT * FROM seller_profiles WHERE user_id = ?", [$user_id]);

$message = '';
$error = '';

// Check for session messages from verification request
if (isset($_SESSION['verification_message'])) {
    $message = $_SESSION['verification_message'];
    $message_type = $_SESSION['verification_type'] ?? 'success';
    unset($_SESSION['verification_message']);
    unset($_SESSION['verification_type']);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($phone)) {
        $error = 'All fields are required';
    } else {
        // Update user info
        $update_data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone
        ];
        
        // Check if password should be updated
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $error = 'Current password is required to set new password';
            } elseif (!password_verify($current_password, $user['password_hash'])) {
                $error = 'Current password is incorrect';
            } elseif (strlen($new_password) < 8) {
                $error = 'New password must be at least 8 characters';
            } else {
                $update_data['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
        }
        
        if (!$error) {
            $db->update('users', $update_data, 'id = ?', [$user_id]);
            
            // Update session
            $_SESSION['user_name'] = $first_name;
            
            $message = 'Profile updated successfully';
            $message_type = 'success';
            
            // Refresh user data
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
        }
    }
}
?>
<?php 
$page_title = "Personal Information";
include '../../includes/header.php'; 
?>

<!-- Custom Responsive Styles -->
<style>
    /* card hover effect for address cards (consistency) */
    .profile-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .profile-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.08);
    }
    /* make buttons on tiny screens stack nicely */
    @media (max-width: 480px) {
        .btn-group-sm-responsive {
            display: flex;
            gap: 0.35rem;
            flex-wrap: wrap;
        }
        .address-actions {
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .address-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }
    /* sidebar becomes cleaner on small screens */
    @media (max-width: 767px) {
        .sidebar-stats {
            margin-bottom: 1rem;
        }
        .list-group-item {
            padding: 0.7rem 1rem;
        }
    }
    .form-label {
        font-weight: 500;
        font-size: 0.9rem;
    }
    .card-header.bg-success {
        background: #198754 !important;
    }
    .rounded-pill-icon {
        transition: all 0.2s;
    }
    .table-sm td, .table-sm th {
        padding: 0.5rem;
    }
    .badge.rounded-pill {
        font-weight: 500;
    }
    .verification-status {
        transition: all 0.2s;
    }
</style>

<div class="container py-3 py-md-4 px-3 px-md-4">
    <div class="row g-4">
        <!-- Sidebar - full width on mobile, 3 on desktop (consistent with addresses page) -->
        <div class="col-12 col-md-4 col-lg-3">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden sticky-md-top" style="top: 20px;">
                <div class="card-body p-3 p-md-4 text-center text-md-start">
                    <div class="d-flex d-md-block align-items-center gap-3 flex-md-column">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mx-auto mb-md-3">
                            <i class="bi bi-person-fill text-success" style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                            <p class="text-muted small">Buyer Account</p>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="list-group list-group-flush">
                        <a href="personal-info.php" class="list-group-item list-group-item-action active bg-success bg-opacity-10 text-success fw-semibold border-0 ps-0 py-2 d-flex align-items-center gap-2">
                            <i class="bi bi-person fs-5"></i> Personal Info
                        </a>
                        <a href="addresses.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 ps-0 py-2">
                            <i class="bi bi-geo-alt fs-5"></i> Addresses
                        </a>
                        <!--
                        <a href="payment-methods.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 ps-0 py-2">
                            <i class="bi bi-credit-card fs-5"></i> Payment Methods
                        </a>
                        -->
                        <a href="../dashboard.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 ps-0 py-2 mt-2 text-muted">
                            <i class="bi bi-arrow-left-short fs-5"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content - full width on small screens -->
        <div class="col-12 col-md-8 col-lg-9">
            <!-- Profile Update Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden profile-card">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-pencil-square me-2"></i> Personal Information</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type ?? 'success'; ?> alert-dismissible fade show rounded-3" role="alert">
                            <i class="bi bi-<?php echo ($message_type ?? 'success') == 'success' ? 'check-circle-fill' : 'info-circle-fill'; ?> me-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <input type="email" class="form-control form-control-lg bg-light" id="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    <?php if (!$user['is_email_verified']): ?>
                                        <a href="?verify_email=send" class="btn btn-outline-warning" 
                                           onclick="return confirm('Send verification email to <?php echo htmlspecialchars($user['email']); ?>?')">
                                            <i class="bi bi-envelope-paper me-1"></i> Verify
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-outline-success disabled">
                                            <i class="bi bi-check-circle me-1"></i> Verified
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text mt-2">
                                    <?php if (!$user['is_email_verified']): ?>
                                        <i class="bi bi-info-circle text-warning me-1"></i>
                                        Email not verified. Click Verify to receive verification link.
                                    <?php else: ?>
                                        <i class="bi bi-check-circle text-success me-1"></i>
                                        Your email is verified.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control form-control-lg" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                <div class="form-text">Include area code (e.g., 0803 123 4567)</div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3 fw-semibold"><i class="bi bi-shield-lock me-2"></i> Change Password</h5>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <div class="form-text">Leave blank if not changing password</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <div class="form-text">Minimum 8 characters</div>
                            </div>
                        </div>
                        
                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                            <a href="../dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 py-2 order-2 order-sm-1">
                                <i class="bi bi-arrow-left me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success rounded-pill px-5 py-2 order-1 order-sm-2">
                                <i class="bi bi-save me-1"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Card (responsive two-column layout) -->
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden profile-card">
                <div class="card-header bg-white border-bottom-0 pt-4 px-4">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-info-circle-fill me-2 text-success"></i> Account Information</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <div class="bg-light rounded-3 p-3 h-100">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <th class="ps-0 pt-0" style="width: 40%;">Account Type:</th>
                                            <td class="pt-0"><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2">Buyer</span></td>
                                        </tr>
                                        <tr>
                                            <th class="ps-0">Member Since:</th>
                                            <td><?php echo formatDate($user['created_at']); ?></td>
                                        </tr>
                                        <tr>
                                            <th class="ps-0">Last Login:</th>
                                            <td><?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="bg-light rounded-3 p-3 h-100">
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <th class="ps-0 pt-0" style="width: 40%;">Email Verified:</th>
                                            <td class="pt-0">
                                                <?php if ($user['is_email_verified']): ?>
                                                    <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 me-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Not Verified</span>
                                                    <a href="?verify_email=send" class="btn btn-sm btn-outline-success mt-2 mt-sm-0" 
                                                       onclick="return confirm('Send verification email to <?php echo htmlspecialchars($user['email']); ?>?')">
                                                        <i class="bi bi-envelope-paper me-1"></i> Verify Now
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="ps-0">Account Status:</th>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-shield-check me-1"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger rounded-pill px-3 py-2"><i class="bi bi-shield-slash me-1"></i> Suspended</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include '../../includes/footer.php'; ?>