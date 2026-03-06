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

<div class="container py-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="bg-success rounded-circle d-inline-flex p-3 mb-2">
                            <i class="bi bi-person text-white" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                        <p class="text-muted">Buyer Account</p>
                    </div>
                    
                    <div class="list-group">
                        <a href="personal-info.php" class="list-group-item list-group-item-action active">
                            <i class="bi bi-person me-2"></i> Personal Info
                        </a>
                        <a href="addresses.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-geo-alt me-2"></i> Addresses
                        </a>
                        <a href="payment-methods.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-credit-card me-2"></i> Payment Methods
                        </a>
                        <a href="../dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Personal Information</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type ?? 'success'; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    <?php if (!$user['is_email_verified']): ?>
                                        <a href="?verify_email=send" class="btn btn-outline-warning" 
                                           onclick="return confirm('Send verification email to <?php echo htmlspecialchars($user['email']); ?>?')">
                                             Verify
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-outline-success disabled">
                                            <i class="bi bi-check-circle me-1"></i> Verified
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text">
                                    <?php if (!$user['is_email_verified']): ?>
                                        <i class="bi bi-info-circle text-warning me-1"></i>
                                        Email not verified. Click Verify to receive verification link.
                                    <?php else: ?>
                                        <i class="bi bi-check-circle text-success me-1"></i>
                                        Your email is verified.
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Change Password</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <div class="form-text">Leave blank if not changing password</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <div class="form-text">Minimum 8 characters</div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="../dashboard.php" class="btn btn-outline-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-success">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>Account Type:</th>
                                    <td>Buyer</td>
                                </tr>
                                <tr>
                                    <th>Member Since:</th>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Login:</th>
                                    <td><?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Never'; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>Email Verified:</th>
                                    <td>
                                        <?php if ($user['is_email_verified']): ?>
                                            <span class="badge bg-success">Verified</span>
                                            <i class="bi bi-check-circle-fill text-success ms-1" title="Verified"></i>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Not Verified</span>
                                            <a href="?verify_email=send" class="btn btn-sm btn-outline-success ms-2" 
                                               onclick="return confirm('Send verification email to <?php echo htmlspecialchars($user['email']); ?>?')">
                                                 Verify Now
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Account Status:</th>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include '../../includes/footer.php'; ?>