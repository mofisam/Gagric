<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

requireBuyer();

$db = new Database();
$user_id = getCurrentUserId();

// Get user data
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
$profile = $db->fetchOne("SELECT * FROM seller_profiles WHERE user_id = ?", [$user_id]);

$message = '';
$error = '';

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
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
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
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <div class="form-text">Email cannot be changed</div>
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
                            <button class="btn btn-success">Update Profile</button>
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
                                        <?php else: ?>
                                            <span class="badge bg-warning">Not Verified</span>
                                            <a href="#" class="btn btn-sm btn-outline-success ms-2">Verify Now</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Phone Verified:</th>
                                    <td>
                                        <?php if ($user['is_phone_verified']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Not Verified</span>
                                            <a href="#" class="btn btn-sm btn-outline-success ms-2">Verify Now</a>
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

<?php include '../../includes/footer.php'; ?>