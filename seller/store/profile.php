<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get seller profile
$seller_profile = $db->fetchOne("
    SELECT sp.*, u.first_name, u.last_name, u.email, u.phone
    FROM seller_profiles sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
", [$seller_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile_data = [
        'business_name' => sanitizeInput($_POST['business_name']),
        'business_reg_number' => sanitizeInput($_POST['business_reg_number']),
        'business_description' => sanitizeInput($_POST['business_description']),
        'website_url' => sanitizeInput($_POST['website_url'])
    ];

    if (empty($profile_data['business_name'])) {
        $error = 'Business name is required';
    } else {
        try {
            if ($seller_profile) {
                // Update existing profile
                $db->update('seller_profiles', $profile_data, 'user_id = ?', [$seller_id]);
            } else {
                // Create new profile
                $db->insert('seller_profiles', array_merge($profile_data, ['user_id' => $seller_id]));
            }
            $success = 'Profile updated successfully!';
            // Refresh profile data
            $seller_profile = $db->fetchOne("SELECT * FROM seller_profiles WHERE user_id = ?", [$seller_id]);
        } catch (Exception $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

$page_title = "Store Profile";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Store Profile</h1>
                <div class="btn-group">
                    <a href="settings.php" class="btn btn-outline-primary">Store Settings</a>
                    <a href="verification.php" class="btn btn-outline-secondary">Verification</a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Business Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="business_name" class="form-label">Business Name *</label>
                                    <input type="text" class="form-control" id="business_name" name="business_name" 
                                           value="<?php echo $seller_profile['business_name'] ?? ''; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="business_reg_number" class="form-label">Business Registration Number</label>
                                    <input type="text" class="form-control" id="business_reg_number" name="business_reg_number" 
                                           value="<?php echo $seller_profile['business_reg_number'] ?? ''; ?>" 
                                           placeholder="CAC Registration Number">
                                </div>

                                <div class="mb-3">
                                    <label for="business_description" class="form-label">Business Description</label>
                                    <textarea class="form-control" id="business_description" name="business_description" 
                                              rows="4"><?php echo $seller_profile['business_description'] ?? ''; ?></textarea>
                                    <div class="form-text">Tell customers about your business and products</div>
                                </div>

                                <div class="mb-3">
                                    <label for="website_url" class="form-label">Website URL</label>
                                    <input type="url" class="form-control" id="website_url" name="website_url" 
                                           value="<?php echo $seller_profile['website_url'] ?? ''; ?>" 
                                           placeholder="https://yourwebsite.com">
                                </div>

                                <div class="mb-3">
                                    <label for="business_logo" class="form-label">Business Logo</label>
                                    <input type="file" class="form-control" id="business_logo" name="business_logo" 
                                           accept="image/*">
                                    <?php if ($seller_profile && $seller_profile['business_logo']): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo BASE_URL . '/assets/uploads/profiles/' . $seller_profile['business_logo']; ?>" 
                                                 alt="Business Logo" style="max-height: 100px;" class="rounded">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button class="btn btn-success">Update Profile</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Store Status -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Store Status</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($seller_profile): ?>
                                <div class="mb-3">
                                    <strong>Approval Status:</strong><br>
                                    <span class="badge bg-<?php echo $seller_profile['is_approved'] ? 'success' : 'warning'; ?>">
                                        <?php echo $seller_profile['is_approved'] ? 'Approved' : 'Pending Approval'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($seller_profile['is_approved'] && $seller_profile['approved_at']): ?>
                                    <div class="mb-3">
                                        <strong>Approved On:</strong><br>
                                        <?php echo formatDate($seller_profile['approved_at']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <strong>Total Sales:</strong><br>
                                    <?php echo formatCurrency($seller_profile['total_sales'] ?? 0); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Average Rating:</strong><br>
                                    <?php
                                    $rating = $seller_profile['avg_rating'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star<?php echo $i <= $rating ? '-fill text-warning' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="ms-1">(<?php echo number_format($rating, 1); ?>)</span>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Complete your profile to get approved.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($seller_profile): ?>
                                <div class="mb-2">
                                    <strong>Name:</strong><br>
                                    <?php echo $seller_profile['first_name'] . ' ' . $seller_profile['last_name']; ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Email:</strong><br>
                                    <?php echo $seller_profile['email']; ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Phone:</strong><br>
                                    <?php echo $seller_profile['phone']; ?>
                                </div>
                                <hr>
                                <a href="../../buyer/profile/personal-info.php" class="btn btn-sm btn-outline-primary">
                                    Update Contact Info
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>