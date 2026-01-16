<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get seller profile and verification status
$seller_profile = $db->fetchOne("
    SELECT sp.*, u.first_name, u.last_name
    FROM seller_profiles sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.user_id = ?
", [$seller_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle document upload
    if (isset($_FILES['verification_documents']) && is_uploaded_file($_FILES['verification_documents']['tmp_name'])) {
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $upload_result = uploadFile(
            $_FILES['verification_documents'], 
            $allowed_types, 
            $max_size, 
            '../../assets/uploads/documents/'
        );
        
        if ($upload_result[0]) {
            // Update seller profile with document info
            $documents = json_decode($seller_profile['verification_documents'] ?? '[]', true);
            $documents[] = [
                'filename' => $upload_result[1],
                'original_name' => $_FILES['verification_documents']['name'],
                'uploaded_at' => date('Y-m-d H:i:s'),
                'type' => 'verification'
            ];
            
            $db->update('seller_profiles', [
                'verification_documents' => json_encode($documents)
            ], 'user_id = ?', [$seller_id]);
            
            $success = 'Document uploaded successfully! Our team will review it.';
        } else {
            $error = 'Error uploading document: ' . implode(', ', $upload_result[1]);
        }
    }
}

$page_title = "Seller Verification";
$page_css = "dashboard.css";
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Seller Verification</h1>
                <a href="profile.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <!-- Verification Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Verification Status</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($seller_profile && $seller_profile['is_approved']): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <strong>Verified Seller</strong>
                                    <p class="mb-0">Your account has been verified and approved.</p>
                                    <?php if ($seller_profile['approved_at']): ?>
                                        <small>Approved on: <?php echo formatDate($seller_profile['approved_at']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-clock-fill"></i>
                                    <strong>Pending Verification</strong>
                                    <p class="mb-0">Your account is pending verification. Please upload the required documents.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Document Upload -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Upload Verification Documents</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="verification_documents" class="form-label">Select Document</label>
                                    <input type="file" class="form-control" id="verification_documents" 
                                           name="verification_documents" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                    <div class="form-text">
                                        Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max: 5MB)<br>
                                        You can upload: Business registration, ID card, utility bill, etc.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="document_type" class="form-label">Document Type</label>
                                    <select class="form-select" id="document_type" name="document_type" required>
                                        <option value="">Select Document Type</option>
                                        <option value="cac_certificate">CAC Certificate</option>
                                        <option value="business_registration">Business Registration</option>
                                        <option value="tax_certificate">Tax Certificate</option>
                                        <option value="id_card">Government ID Card</option>
                                        <option value="utility_bill">Utility Bill</option>
                                        <option value="bank_statement">Bank Statement</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="document_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="document_description" name="document_description" 
                                              rows="2" placeholder="Brief description of the document"></textarea>
                                </div>

                                <button class="btn btn-success">Upload Document</button>
                            </form>
                        </div>
                    </div>

                    <!-- Uploaded Documents -->
                    <?php if ($seller_profile && $seller_profile['verification_documents']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Uploaded Documents</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $documents = json_decode($seller_profile['verification_documents'], true);
                                if ($documents && is_array($documents)):
                                ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Document</th>
                                                    <th>Uploaded</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($documents as $doc): ?>
                                                    <tr>
                                                        <td>
                                                            <i class="bi bi-file-earmark-text"></i>
                                                            <?php echo $doc['original_name']; ?>
                                                        </td>
                                                        <td><?php echo formatDate($doc['uploaded_at']); ?></td>
                                                        <td>
                                                            <a href="<?php echo BASE_URL . '/assets/uploads/documents/' . $doc['filename']; ?>" 
                                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No documents uploaded yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <!-- Verification Requirements -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Verification Requirements</h5>
                        </div>
                        <div class="card-body">
                            <h6>Required Documents:</h6>
                            <ul class="small">
                                <li>Business Registration Certificate (CAC)</li>
                                <li>Valid Government ID Card</li>
                                <li>Proof of Address (Utility Bill)</li>
                                <li>Tax Identification Number (Optional)</li>
                            </ul>

                            <h6>Benefits of Verification:</h6>
                            <ul class="small">
                                <li>Verified badge on your store</li>
                                <li>Higher customer trust</li>
                                <li>Priority in search results</li>
                                <li>Access to premium features</li>
                                <li>Faster payout processing</li>
                            </ul>

                            <div class="alert alert-info small">
                                <i class="bi bi-info-circle"></i>
                                Verification typically takes 2-3 business days. You'll be notified via email once completed.
                            </div>
                        </div>
                    </div>

                    <!-- Support -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Need Help?</h5>
                        </div>
                        <div class="card-body">
                            <p class="small">If you're having trouble with verification, contact our support team:</p>
                            <ul class="small">
                                <li><strong>Email:</strong> support@Green Agric.ng</li>
                                <li><strong>Phone:</strong> +234 703 041 9150</li>
                                <li><strong>Hours:</strong> Mon-Fri, 9AM-5PM</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>