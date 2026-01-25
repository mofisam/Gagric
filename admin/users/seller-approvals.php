<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = new Database();

// Handle approval actions
if (isset($_GET['approve'])) {
    $seller_id = (int)$_GET['approve'];
    $db->query(
        "UPDATE seller_profiles SET is_approved = TRUE, approved_by = ?, approved_at = NOW() WHERE user_id = ?",
        [$_SESSION['user_id'], $seller_id]
    );
    setFlashMessage('Seller approved successfully', 'success');
    header('Location: seller-approvals.php');
    exit;
}

if (isset($_GET['reject'])) {
    $seller_id = (int)$_GET['reject'];
    $db->query("UPDATE seller_profiles SET is_approved = FALSE WHERE user_id = ?", [$seller_id]);
    setFlashMessage('Seller rejected', 'warning');
    header('Location: seller-approvals.php');
    exit;
}

// Get pending seller approvals
$pending_sellers = $db->fetchAll("
    SELECT u.*, sp.* 
    FROM users u 
    JOIN seller_profiles sp ON u.id = sp.user_id 
    WHERE sp.is_approved = FALSE 
    ORDER BY sp.created_at DESC
");

$page_title = "Seller Approvals";

?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Seller Approvals</h1>
                <span class="badge bg-warning"><?php echo count($pending_sellers); ?> Pending</span>
            </div>

            <?php if (empty($pending_sellers)): ?>
                <div class="alert alert-info">
                    <h4>No pending seller approvals</h4>
                    <p>All seller applications have been reviewed.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($pending_sellers as $seller): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($seller['business_name']); ?></h5>
                                <span class="badge bg-warning">Pending</span>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Owner:</strong> <?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($seller['email']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($seller['phone']); ?>
                                </div>
                                <?php if ($seller['business_description']): ?>
                                <div class="mb-3">
                                    <strong>Description:</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($seller['business_description']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($seller['business_reg_number']): ?>
                                <div class="mb-3">
                                    <strong>Registration:</strong> <?php echo htmlspecialchars($seller['business_reg_number']); ?>
                                </div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <strong>Applied:</strong> <?php echo date('M j, Y', strtotime($seller['created_at'])); ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="btn-group w-100">
                                    <a href="?approve=<?php echo $seller['user_id']; ?>" 
                                       class="btn btn-success" 
                                       onclick="return confirm('Approve this seller?')">
                                        Approve
                                    </a>
                                    <a href="?reject=<?php echo $seller['user_id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Reject this seller application?')">
                                        Reject
                                    </a>
                                    <a href="user-details.php?id=<?php echo $seller['user_id']; ?>" 
                                       class="btn btn-outline-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>