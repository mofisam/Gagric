<?php
require_once '../includes/auth.php';
requireSeller();
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get seller user details
$user = $db->fetchOne("
    SELECT id, uuid, email, first_name, last_name, phone, profile_image, 
           is_email_verified, is_phone_verified, created_at as member_since
    FROM users 
    WHERE id = ?
", [$seller_id]);

// Get seller profile details
$seller_profile = $db->fetchOne("
    SELECT sp.*, 
           ua.address_line, ua.landmark, ua.is_default,
           s.name as state_name, l.name as lga_name, c.name as city_name
    FROM seller_profiles sp
    LEFT JOIN user_addresses ua ON sp.business_address_id = ua.id
    LEFT JOIN states s ON ua.state_id = s.id
    LEFT JOIN lgas l ON ua.lga_id = l.id
    LEFT JOIN cities c ON ua.city_id = c.id
    WHERE sp.user_id = ?
", [$seller_id]);

// Get seller stats
$seller_stats_data = [
    'total_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ?", [$seller_id])['count'],
    'active_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved'", [$seller_id])['count'],
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ?", [$seller_id])['count'],
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(item_total), 0) as total FROM order_items WHERE seller_id = ? AND status = 'delivered'", [$seller_id])['total'],
    'avg_rating' => $seller_profile['avg_rating'] ?? 0,
    'total_reviews' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_ratings WHERE seller_id = ?", [$seller_id])['count']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_personal') {
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $phone = sanitizeInput($_POST['phone']);
        
        // Validate phone
        if (!preg_match('/^[0-9]{10,11}$/', $phone)) {
            $error = 'Please enter a valid phone number (10-11 digits)';
        } else {
            $db->update('users', [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone
            ], 'id = ?', [$seller_id]);
            
            // Update session
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            
            $success = 'Personal information updated successfully!';
            
            // Refresh user data
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$seller_id]);
        }
    }
    
    elseif ($action === 'update_business') {
        $business_name = sanitizeInput($_POST['business_name']);
        $business_description = sanitizeInput($_POST['business_description']);
        $website_url = sanitizeInput($_POST['website_url']);
        
        if (empty($business_name)) {
            $error = 'Business name is required';
        } else {
            $update_data = [
                'business_name' => $business_name,
                'business_description' => $business_description,
                'website_url' => $website_url
            ];
            
            if ($seller_profile) {
                $db->update('seller_profiles', $update_data, 'user_id = ?', [$seller_id]);
            } else {
                $update_data['user_id'] = $seller_id;
                $db->insert('seller_profiles', $update_data);
            }
            
            $success = 'Business information updated successfully!';
            
            // Refresh profile data
            $seller_profile = $db->fetchOne("SELECT * FROM seller_profiles WHERE user_id = ?", [$seller_id]);
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $user_check = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$seller_id]);
        
        if (!password_verify($current_password, $user_check['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $db->update('users', ['password_hash' => $password_hash], 'id = ?', [$seller_id]);
            $success = 'Password changed successfully!';
        }
    }
    
    elseif ($action === 'upload_logo') {
        if (isset($_FILES['business_logo']) && $_FILES['business_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['business_logo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Only JPG, PNG, WEBP images are allowed';
            } elseif ($file['size'] > $max_size) {
                $error = 'File size must be less than 2MB';
            } else {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/sellers/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'seller_' . $seller_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $logo_path = '/uploads/sellers/' . $filename;
                    
                    if ($seller_profile) {
                        $db->update('seller_profiles', ['business_logo' => $logo_path], 'user_id = ?', [$seller_id]);
                    } else {
                        $db->insert('seller_profiles', [
                            'user_id' => $seller_id,
                            'business_name' => '',
                            'business_logo' => $logo_path
                        ]);
                    }
                    
                    $success = 'Business logo uploaded successfully!';
                    
                    // Refresh profile data
                    $seller_profile = $db->fetchOne("SELECT * FROM seller_profiles WHERE user_id = ?", [$seller_id]);
                } else {
                    $error = 'Failed to upload image';
                }
            }
        } else {
            $error = 'Please select a file to upload';
        }
    }
}

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND DATE(created_at) = CURDATE()", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;
$_SESSION['avatar'] = $user['profile_image'] ?? null;
$_SESSION['join_date'] = $user['member_since'] ?? date('Y-m-d');

$page_title = "My Profile";
$page_css = 'dashboard.css';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-3" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0">My Profile</h1>
                        <small class="text-muted">Manage your account settings</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">My Profile</h1>
                    <p class="text-muted mb-0">Manage your account and store information</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Summary Card -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                        <div class="card-body text-center p-4">
                            <!-- Profile Image -->
                            <div class="position-relative d-inline-block mb-3">
                                <?php if ($user['profile_image']): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                         class="rounded-circle" 
                                         style="width: 120px; height: 120px; object-fit: cover;"
                                         alt="<?php echo htmlspecialchars($user['first_name']); ?>">
                                <?php else: ?>
                                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto"
                                         style="width: 120px; height: 120px;">
                                        <i class="bi bi-person-circle text-primary" style="font-size: 80px;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Verification Badge -->
                                <?php if ($user['is_email_verified']): ?>
                                    <span class="position-absolute bottom-0 end-0">
                                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <h4 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                            
                            <?php if ($seller_profile['avg_rating'] > 0): ?>
                                <div class="mb-3">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= round($seller_profile['avg_rating']) ? '-fill' : ''; ?> text-warning"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted ms-2">(<?php echo number_format($seller_stats_data['total_reviews']); ?> reviews)</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row g-2 mt-3">
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Products</small>
                                        <span class="fw-bold fs-5"><?php echo number_format($seller_stats_data['total_products']); ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Orders</small>
                                        <span class="fw-bold fs-5"><?php echo number_format($seller_stats_data['total_orders']); ?></span>
                                    </div>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Total Revenue</small>
                                        <span class="fw-bold fs-5 text-success">₦<?php echo number_format($seller_stats_data['total_revenue'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="text-start">
                                <div class="mb-2">
                                    <i class="bi bi-envelope me-2 text-muted"></i>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                                    <?php if (!$user['is_email_verified']): ?>
                                        <span class="badge bg-warning ms-2">Unverified</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-2">
                                    <i class="bi bi-telephone me-2 text-muted"></i>
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?>
                                </div>
                                <div class="mb-2">
                                    <i class="bi bi-calendar me-2 text-muted"></i>
                                    <strong>Member Since:</strong> <?php echo formatDate($user['member_since'], 'M j, Y'); ?>
                                </div>
                                <?php if ($seller_profile['is_approved']): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i> Verified Seller
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-2">
                                        <span class="badge bg-warning">
                                            <i class="bi bi-clock me-1"></i> Pending Verification
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Edit Forms -->
                <div class="col-lg-8">
                    <!-- Personal Information -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person me-2 text-primary"></i>
                                Personal Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_personal">
                                
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
                                        <input type="email" class="form-control bg-light" id="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                        <div class="form-text">Email cannot be changed</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Update Information
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Business Information -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shop me-2 text-primary"></i>
                                Business Information
                            </h5>
                            <?php if ($seller_profile && !$seller_profile['is_approved']): ?>
                                <span class="badge bg-warning">Pending Approval</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_business">
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="business_name" class="form-label">Business Name *</label>
                                        <input type="text" class="form-control" id="business_name" name="business_name" 
                                               value="<?php echo htmlspecialchars($seller_profile['business_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="website_url" class="form-label">Website (Optional)</label>
                                        <input type="url" class="form-control" id="website_url" name="website_url" 
                                               value="<?php echo htmlspecialchars($seller_profile['website_url'] ?? ''); ?>"
                                               placeholder="https://example.com">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="business_description" class="form-label">Business Description</label>
                                    <textarea class="form-control" id="business_description" name="business_description" 
                                              rows="4"><?php echo htmlspecialchars($seller_profile['business_description'] ?? ''); ?></textarea>
                                    <div class="form-text">Tell customers about your business and the products you offer</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Update Business Info
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Business Logo Upload -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-image me-2 text-primary"></i>
                                Business Logo
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3 text-center">
                                    <?php if ($seller_profile && $seller_profile['business_logo']): ?>
                                        <img src="<?php echo htmlspecialchars($seller_profile['business_logo']); ?>" 
                                             class="img-fluid rounded border" 
                                             style="max-height: 100px;"
                                             alt="Business Logo">
                                    <?php else: ?>
                                        <div class="bg-light rounded p-3">
                                            <i class="bi bi-building text-muted" style="font-size: 60px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload_logo">
                                        
                                        <div class="mb-3">
                                            <label for="business_logo" class="form-label">Upload Logo</label>
                                            <input type="file" class="form-control" id="business_logo" name="business_logo" 
                                                   accept="image/jpeg,image/png,image/webp">
                                            <div class="form-text">Recommended: Square image, max 2MB (JPG, PNG, WEBP)</div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-outline-primary">
                                            <i class="bi bi-upload me-1"></i> Upload Logo
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lock me-2 text-primary"></i>
                                Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-key me-1"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Account Statistics -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart me-2 text-primary"></i>
                                Account Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-primary fs-4"><?php echo number_format($seller_stats_data['total_products']); ?></div>
                                        <small class="text-muted">Total Products</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-success fs-4"><?php echo number_format($seller_stats_data['active_products']); ?></div>
                                        <small class="text-muted">Active Products</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-info fs-4"><?php echo number_format($seller_stats_data['total_orders']); ?></div>
                                        <small class="text-muted">Total Orders</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-2 border rounded">
                                        <div class="text-warning fs-4"><?php echo number_format($seller_stats_data['avg_rating'], 1); ?></div>
                                        <small class="text-muted">Rating</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Password confirmation validation
document.querySelector('form[action=""] input[name="confirm_password"]')?.addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Phone number validation
document.getElementById('phone')?.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Add custom styles
const style = document.createElement('style');
style.textContent = `
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    
    .sticky-top {
        z-index: 1;
    }
    
    @media (max-width: 768px) {
        .sticky-top {
            position: relative;
            top: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>