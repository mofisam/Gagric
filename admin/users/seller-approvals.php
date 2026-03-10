<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

// Handle seller approval actions
if (isset($_GET['approve'])) {
    $seller_id = (int)$_GET['approve'];
    
    // Update seller profile
    $db->update('seller_profiles', [
        'is_approved' => 1,
        'approved_at' => date('Y-m-d H:i:s'),
        'approved_by' => $_SESSION['user_id']
    ], 'user_id = ?', [$seller_id]);
    
    // Update user role if needed (check if already seller)
    $db->update('users', [
        'role' => 'seller'
    ], 'id = ? AND role != ?', [$seller_id, 'admin']);
    
    // Log the action
    $db->insert('user_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'approve_seller',
        'details' => 'Approved seller ID: ' . $seller_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $_SESSION['flash_message'] = 'Seller approved successfully';
    $_SESSION['flash_type'] = 'success';
    header('Location: seller-approvals.php');
    exit;
}

if (isset($_GET['reject'])) {
    $seller_id = (int)$_GET['reject'];
    $reason = $_GET['reason'] ?? 'No reason provided';
    
    // Update seller profile (reject by setting is_approved = 0, but we need a rejection_reason field)
    // Note: Your schema doesn't have rejection_reason - consider adding it
    $db->update('seller_profiles', [
        'is_approved' => 0,
        'approved_at' => null,
        'approved_by' => null
    ], 'user_id = ?', [$seller_id]);
    
    // Log the action
    $db->insert('user_logs', [
        'user_id' => $_SESSION['user_id'],
        'action' => 'reject_seller',
        'details' => 'Rejected seller ID: ' . $seller_id . ' - Reason: ' . $reason,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $_SESSION['flash_message'] = 'Seller rejected';
    $_SESSION['flash_type'] = 'warning';
    header('Location: seller-approvals.php');
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'pending'; // Default to pending
$has_docs = isset($_GET['has_docs']) ? (int)$_GET['has_docs'] : 0;

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (sp.business_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status)) {
    if ($status === 'pending') {
        $where .= " AND sp.is_approved = 0";
    } elseif ($status === 'approved') {
        $where .= " AND sp.is_approved = 1";
    }
    // Note: the schema doesn't have suspended/rejected status fields
}

if ($has_docs) {
    $where .= " AND sp.verification_documents IS NOT NULL AND sp.verification_documents != '[]' AND sp.verification_documents != 'null'";
}

// Get sellers with their information
$sellers = $db->fetchAll("
    SELECT 
        sp.*,
        u.id as user_id,
        u.email,
        u.phone,
        u.first_name,
        u.last_name,
        u.profile_image,
        u.created_at as user_created_at,
        u.last_login,
        (SELECT COUNT(*) FROM products WHERE seller_id = u.id AND status = 'approved') as approved_products,
        (SELECT COUNT(*) FROM products WHERE seller_id = u.id AND status = 'pending') as pending_products,
        (SELECT COUNT(*) FROM products WHERE seller_id = u.id AND status = 'suspended') as suspended_products,
        (SELECT SUM(oi.unit_price * oi.quantity) 
         FROM order_items oi 
         WHERE oi.seller_id = u.id) as total_sales,
        (SELECT AVG(CAST(sr.rating AS DECIMAL)) FROM seller_ratings sr WHERE sr.seller_id = u.id) as avg_rating,
        (SELECT COUNT(*) FROM seller_ratings WHERE seller_id = u.id) as total_reviews,
        (SELECT COUNT(*) FROM user_addresses WHERE user_id = u.id) as address_count,
        sp.created_at as seller_since
    FROM seller_profiles sp
    JOIN users u ON sp.user_id = u.id
    $where
    ORDER BY 
        CASE WHEN sp.is_approved = 0 THEN 0 ELSE 1 END,
        sp.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

// Get total count for pagination
$total_sellers = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM seller_profiles sp 
    JOIN users u ON sp.user_id = u.id 
    $where
", $params)['count'];
$total_pages = ceil($total_sellers / $limit);

// Get statistics
$stats = [
    'total_sellers' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles")['count'],
    'pending_sellers' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE is_approved = 0")['count'],
    'approved_sellers' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE is_approved = 1")['count'],
    'sellers_with_docs' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE verification_documents IS NOT NULL AND verification_documents != '[]' AND verification_documents != 'null'")['count'],
    'new_today' => $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE DATE(created_at) = CURDATE()")['count']
];

// Get categories for reference
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = TRUE");

$page_title = "Seller Approvals";
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
                        <h1 class="h5 mb-0 text-center">Seller Approvals</h1>
                        <small class="text-muted d-block text-center"><?php echo $stats['pending_sellers']; ?> pending</small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshSellers">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Seller Approvals</h1>
                    <p class="text-muted mb-0">Review and manage seller applications</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportSellers()">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshSellers">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Pending</small>
                                <h6 class="mb-0 text-warning"><?php echo number_format($stats['pending_sellers']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Approved</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['approved_sellers']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">With Docs</small>
                                <h6 class="mb-0 text-info"><?php echo number_format($stats['sellers_with_docs']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Total</small>
                                <h6 class="mb-0 text-primary"><?php echo number_format($stats['total_sellers']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="d-none d-md-flex row g-3 mb-4">
                <!-- Total Sellers -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Sellers</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_sellers']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo $stats['new_today']; ?> today
                                    </small>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-shop fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Approvals -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Pending</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['pending_sellers']); ?></h3>
                                    <small class="text-warning">
                                        Needs review
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-clock-history fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Approved Sellers -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Approved</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['approved_sellers']); ?></h3>
                                    <small class="text-success">
                                        Active sellers
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-check-circle fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- With Documents -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Has Documents</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['sellers_with_docs']); ?></h3>
                                    <small class="text-info">
                                        Ready for review
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-file-earmark-text fs-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Filter Sellers
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-funnel"></i>
                    </button>
                </div>
                <div class="collapse d-md-block" id="filterCollapse">
                    <div class="card-body">
                        <form method="GET" class="row g-2">
                            <div class="col-12 col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search business, name, email, phone..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="has_docs" value="1" id="hasDocs" 
                                           <?php echo $has_docs ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="hasDocs">
                                        Has Documents
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-filter me-1"></i> Filter
                                    </button>
                                    <a href="seller-approvals.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">Sellers (<?php echo $total_sellers; ?>)</h5>
                    <small class="text-muted">
                        Showing <?php echo min($offset + 1, $total_sellers); ?>-<?php echo min($offset + $limit, $total_sellers); ?> of <?php echo $total_sellers; ?> sellers
                    </small>
                </div>
                <div class="text-muted d-none d-md-block">
                    <?php if($stats['pending_sellers'] > 0): ?>
                        <span class="badge bg-warning">
                            <i class="bi bi-clock me-1"></i>
                            <?php echo $stats['pending_sellers']; ?> pending
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sellers Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($sellers)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-shop text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No sellers found</h4>
                            <p class="text-muted mb-4">No sellers match your current filters.</p>
                            <a href="seller-approvals.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="200">Seller / Business</th>
                                        <th width="150">Contact Info</th>
                                        <th width="100">Products</th>
                                        <th width="120">Sales</th>
                                        <th width="100">Documents</th>
                                        <th width="80">Status</th>
                                        <th width="100">Joined</th>
                                        <th width="150" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sellers as $seller): ?>
                                        <?php 
                                        $status_color = $seller['is_approved'] ? 'success' : 'warning';
                                        $status_text = $seller['is_approved'] ? 'Approved' : 'Pending';
                                        
                                        $has_documents = !empty($seller['verification_documents']) && 
                                                         $seller['verification_documents'] != '[]' && 
                                                         $seller['verification_documents'] != 'null';
                                        $documents = $has_documents ? json_decode($seller['verification_documents'], true) : [];
                                        $full_name = $seller['first_name'] . ' ' . $seller['last_name'];
                                        ?>
                                        
                                        <tr class="seller-row" data-seller-id="<?php echo $seller['user_id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <div class="d-flex align-items-center">
                                                    <?php if ($seller['business_logo']): ?>
                                                        <img src="<?php echo BASE_URL; ?>/assets/uploads/profiles/<?php echo $seller['business_logo']; ?>" 
                                                             alt="Logo" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-<?php echo $status_color; ?> bg-opacity-10 p-2 rounded-circle me-2" 
                                                             style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="bi bi-shop text-<?php echo $status_color; ?>"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($seller['business_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($full_name); ?></small>
                                                        <?php if ($seller['business_reg_number']): ?>
                                                            <br><small class="text-muted">Reg: <?php echo htmlspecialchars($seller['business_reg_number']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <i class="bi bi-envelope me-1 small"></i><?php echo htmlspecialchars(substr($seller['email'], 0, 20)); ?><br>
                                                    <i class="bi bi-telephone me-1 small"></i><?php echo htmlspecialchars($seller['phone']); ?>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <span class="badge bg-success" title="Approved"><?php echo $seller['approved_products']; ?></span>
                                                    <span class="badge bg-warning" title="Pending"><?php echo $seller['pending_products']; ?></span>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div>
                                                    <strong class="text-success">₦<?php echo number_format($seller['total_sales'] ?? 0, 2); ?></strong>
                                                    <br>
                                                    <?php if ($seller['avg_rating']): ?>
                                                        <small class="text-warning">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= round($seller['avg_rating']) ? '-fill' : ''; ?>"></i>
                                                            <?php endfor; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php if ($has_documents): ?>
                                                    <span class="badge bg-info" data-bs-toggle="modal" data-bs-target="#docModal<?php echo $seller['user_id']; ?>" style="cursor: pointer;">
                                                        <i class="bi bi-file-earmark-text me-1"></i>
                                                        <?php echo count($documents); ?> file(s)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No docs</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php echo date('M j, Y', strtotime($seller['seller_since'])); ?>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $seller['user_id']; ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    
                                                    <?php if (!$seller['is_approved']): ?>
                                                        <a href="?approve=<?php echo $seller['user_id']; ?>" 
                                                           class="btn btn-outline-success"
                                                           onclick="return confirm('Approve this seller? They will be able to sell products immediately.')">
                                                            <i class="bi bi-check-lg"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $seller['user_id']; ?>">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Mobile View -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Business Name & Status -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $status_color; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <i class="bi bi-shop text-<?php echo $status_color; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars(substr($seller['business_name'], 0, 20)); ?></strong>
                                                                <br>
                                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                                    <?php echo $status_text; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <strong class="text-success">₦<?php echo number_format($seller['total_sales'] ?? 0, 0); ?></strong>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 2: Contact Info -->
                                                    <div class="mb-2">
                                                        <small class="text-muted d-block">
                                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($full_name); ?>
                                                        </small>
                                                        <small class="text-muted d-block">
                                                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars(substr($seller['email'], 0, 15)); ?>...
                                                        </small>
                                                        <small class="text-muted d-block">
                                                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($seller['phone']); ?>
                                                        </small>
                                                    </div>
                                                    
                                                    <!-- Row 3: Products & Documents -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <small class="text-muted">Products:</small>
                                                                <span class="badge bg-success ms-1"><?php echo $seller['approved_products']; ?></span>
                                                                <span class="badge bg-warning"><?php echo $seller['pending_products']; ?></span>
                                                            </div>
                                                            <div>
                                                                <?php if ($has_documents): ?>
                                                                    <span class="badge bg-info" data-bs-toggle="modal" data-bs-target="#docModal<?php echo $seller['user_id']; ?>" style="cursor: pointer;">
                                                                        <i class="bi bi-file-earmark-text"></i> <?php echo count($documents); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 4: Actions -->
                                                    <div class="d-flex justify-content-end align-items-center">
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-primary" 
                                                                    data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $seller['user_id']; ?>">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            
                                                            <?php if (!$seller['is_approved']): ?>
                                                                <a href="?approve=<?php echo $seller['user_id']; ?>" 
                                                                   class="btn btn-outline-success"
                                                                   onclick="return confirm('Approve this seller?')">
                                                                    <i class="bi bi-check-lg"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-outline-danger" 
                                                                        data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $seller['user_id']; ?>">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Document Modal for each seller -->
                                        <?php if ($has_documents): ?>
                                        <div class="modal fade" id="docModal<?php echo $seller['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="bi bi-file-earmark-text me-2"></i>
                                                            Verification Documents - <?php echo htmlspecialchars($seller['business_name']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <?php 
                                                        // Decode the JSON documents
                                                        $docs = json_decode($seller['verification_documents'], true);
                                                        
                                                        // Check if it's a valid array and not empty
                                                        if (is_array($docs) && !empty($docs)): 
                                                        ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Document Name</th>
                                                                            <th>Type</th>
                                                                            <th>Uploaded</th>
                                                                            <th>Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php 
                                                                        // Handle different possible JSON structures
                                                                        if (isset($docs[0]) && is_array($docs[0])) {
                                                                            // Array of documents format
                                                                            foreach ($docs as $doc): 
                                                                                // Try different possible field names
                                                                                $filename = $doc['filename'] ?? $doc['file'] ?? $doc['path'] ?? null;
                                                                                $original_name = $doc['original_name'] ?? $doc['name'] ?? $doc['file_name'] ?? 'Document';
                                                                                $uploaded_at = $doc['uploaded_at'] ?? $doc['date'] ?? $doc['created_at'] ?? null;
                                                                                $doc_type = $doc['type'] ?? $doc['document_type'] ?? 'Verification';
                                                                        ?>
                                                                            <tr>
                                                                                <td>
                                                                                    <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>
                                                                                    <?php echo htmlspecialchars($original_name); ?>
                                                                                </td>
                                                                                <td>
                                                                                    <span class="badge bg-info"><?php echo htmlspecialchars($doc_type); ?></span>
                                                                                </td>
                                                                                <td>
                                                                                    <?php 
                                                                                    if ($uploaded_at) {
                                                                                        echo date('M j, Y', strtotime($uploaded_at));
                                                                                    } else {
                                                                                        echo 'N/A';
                                                                                    }
                                                                                    ?>
                                                                                </td>
                                                                                <td>
                                                                                    <?php if ($filename): ?>
                                                                                        <div class="btn-group btn-group-sm">
                                                                                            <a href="<?php echo BASE_URL; ?>/assets/uploads/documents/<?php echo $filename; ?>" 
                                                                                            target="_blank" class="btn btn-outline-primary">
                                                                                                <i class="bi bi-eye"></i> View
                                                                                            </a>
                                                                                            <a href="<?php echo BASE_URL; ?>/assets/uploads/documents/<?php echo $filename; ?>" 
                                                                                            download class="btn btn-outline-secondary">
                                                                                                <i class="bi bi-download"></i>
                                                                                            </a>
                                                                                        </div>
                                                                                    <?php else: ?>
                                                                                        <span class="text-muted">File not found</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            </tr>
                                                                        <?php 
                                                                            endforeach;
                                                                        } elseif (is_string($seller['verification_documents']) && !empty($seller['verification_documents'])) {
                                                                            // Single document as string (just filename)
                                                                            ?>
                                                                            <tr>
                                                                                <td>
                                                                                    <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>
                                                                                    <?php echo htmlspecialchars($seller['verification_documents']); ?>
                                                                                </td>
                                                                                <td><span class="badge bg-info">Verification</span></td>
                                                                                <td><?php echo date('M j, Y', strtotime($seller['created_at'])); ?></td>
                                                                                <td>
                                                                                    <div class="btn-group btn-group-sm">
                                                                                        <a href="<?php echo BASE_URL; ?>/assets/uploads/documents/<?php echo $seller['verification_documents']; ?>" 
                                                                                        target="_blank" class="btn btn-outline-primary">
                                                                                            <i class="bi bi-eye"></i> View
                                                                                        </a>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                            <?php
                                                                        }
                                                                        ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-center py-4">
                                                                <i class="bi bi-file-earmark-x text-muted" style="font-size: 3rem;"></i>
                                                                <p class="text-muted mt-3">No documents found or invalid document format.</p>
                                                                <pre class="text-start small bg-light p-2 rounded d-none">Debug: <?php print_r($seller['verification_documents']); ?></pre>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                        
                                        <!-- Reject Modal for each seller -->
                                        <div class="modal fade" id="rejectModal<?php echo $seller['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Seller Application</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="GET" action="seller-approvals.php">
                                                        <input type="hidden" name="reject" value="<?php echo $seller['user_id']; ?>">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="reason" class="form-label">Rejection Reason</label>
                                                                <textarea class="form-control" id="reason" name="reason" 
                                                                          rows="3" required
                                                                          placeholder="Please provide a reason for rejection..."></textarea>
                                                                <div class="form-text">
                                                                    This reason will be logged for audit purposes.
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Reject Application</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- View Full Details Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $seller['user_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="bi bi-person-badge me-2"></i>
                                                            Seller Details - <?php echo htmlspecialchars($seller['business_name']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <!-- Business Information -->
                                                            <div class="col-md-6">
                                                                <h6 class="border-bottom pb-2">Business Information</h6>
                                                                <dl class="row small">
                                                                    <dt class="col-5">Business Name:</dt>
                                                                    <dd class="col-7"><?php echo htmlspecialchars($seller['business_name']); ?></dd>
                                                                    
                                                                    <dt class="col-5">Reg Number:</dt>
                                                                    <dd class="col-7"><?php echo htmlspecialchars($seller['business_reg_number'] ?? 'N/A'); ?></dd>
                                                                    
                                                                    <dt class="col-5">Description:</dt>
                                                                    <dd class="col-7"><?php echo nl2br(htmlspecialchars(substr($seller['business_description'] ?? 'N/A', 0, 100))); ?>...</dd>
                                                                    
                                                                    <dt class="col-5">Website:</dt>
                                                                    <dd class="col-7">
                                                                        <?php if ($seller['website_url']): ?>
                                                                            <a href="<?php echo $seller['website_url']; ?>" target="_blank"><?php echo $seller['website_url']; ?></a>
                                                                        <?php else: ?>
                                                                            N/A
                                                                        <?php endif; ?>
                                                                    </dd>
                                                                </dl>
                                                            </div>
                                                            
                                                            <!-- Personal Information -->
                                                            <div class="col-md-6">
                                                                <h6 class="border-bottom pb-2">Personal Information</h6>
                                                                <dl class="row small">
                                                                    <dt class="col-5">Full Name:</dt>
                                                                    <dd class="col-7"><?php echo htmlspecialchars($full_name); ?></dd>
                                                                    
                                                                    <dt class="col-5">Email:</dt>
                                                                    <dd class="col-7"><?php echo htmlspecialchars($seller['email']); ?></dd>
                                                                    
                                                                    <dt class="col-5">Phone:</dt>
                                                                    <dd class="col-7"><?php echo htmlspecialchars($seller['phone']); ?></dd>
                                                                    
                                                                    <dt class="col-5">Joined:</dt>
                                                                    <dd class="col-7"><?php echo date('F j, Y', strtotime($seller['user_created_at'])); ?></dd>
                                                                    
                                                                    <dt class="col-5">Last Login:</dt>
                                                                    <dd class="col-7"><?php echo $seller['last_login'] ? date('M j, Y H:i', strtotime($seller['last_login'])) : 'Never'; ?></dd>
                                                                </dl>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Statistics -->
                                                        <div class="row mt-3">
                                                            <div class="col-12">
                                                                <h6 class="border-bottom pb-2">Statistics</h6>
                                                                <div class="row text-center">
                                                                    <div class="col-4">
                                                                        <div class="bg-light rounded p-2">
                                                                            <div class="h5 mb-0"><?php echo $seller['approved_products']; ?></div>
                                                                            <small class="text-muted">Products</small>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <div class="bg-light rounded p-2">
                                                                            <div class="h5 mb-0 text-success">₦<?php echo number_format($seller['total_sales'] ?? 0, 0); ?></div>
                                                                            <small class="text-muted">Total Sales</small>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <div class="bg-light rounded p-2">
                                                                            <div class="h5 mb-0">
                                                                                <?php echo number_format($seller['avg_rating'] ?? 0, 1); ?>
                                                                                <small class="text-warning">★</small>
                                                                            </div>
                                                                            <small class="text-muted">Rating (<?php echo $seller['total_reviews']; ?>)</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Addresses -->
                                                        <?php if ($seller['address_count'] > 0): ?>
                                                            <?php 
                                                            $addresses = $db->fetchAll("
                                                                SELECT * FROM user_addresses 
                                                                WHERE user_id = ? 
                                                                ORDER BY is_default DESC
                                                            ", [$seller['user_id']]);
                                                            ?>
                                                            <div class="row mt-3">
                                                                <div class="col-12">
                                                                    <h6 class="border-bottom pb-2">Saved Addresses</h6>
                                                                    <?php foreach ($addresses as $address): ?>
                                                                        <div class="bg-light p-2 rounded mb-2 small">
                                                                            <strong><?php echo ucfirst($address['address_type']); ?></strong>
                                                                            <?php if ($address['is_default']): ?>
                                                                                <span class="badge bg-primary ms-2">Default</span>
                                                                            <?php endif; ?>
                                                                            <br>
                                                                            <?php echo htmlspecialchars($address['address_line']); ?><br>
                                                                            <?php if ($address['landmark']): ?>
                                                                                <i class="bi bi-pin"></i> <?php echo htmlspecialchars($address['landmark']); ?><br>
                                                                            <?php endif; ?>
                                                                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($address['phone'] ?? $seller['phone']); ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if (!$seller['is_approved']): ?>
                                                            <a href="?approve=<?php echo $seller['user_id']; ?>" class="btn btn-success">
                                                                <i class="bi bi-check-lg"></i> Approve Seller
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                    <span class="d-none d-md-inline">Previous</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Mobile: Simple pagination -->
                        <div class="d-md-none">
                            <li class="page-item disabled">
                                <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            </li>
                        </div>
                        
                        <!-- Desktop: Full pagination -->
                        <div class="d-none d-md-flex">
                            <?php
                            $range = 1;
                            $ellipsisShownLeft = false;
                            $ellipsisShownRight = false;

                            for ($i = 1; $i <= $total_pages; $i++) {
                                if (
                                    $i == 1 ||
                                    $i == $total_pages ||
                                    ($i >= $page - $range && $i <= $page + $range)
                                ) {
                                    ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link"
                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php
                                } elseif ($i < $page && !$ellipsisShownLeft) {
                                    $ellipsisShownLeft = true;
                                    ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php
                                } elseif ($i > $page && !$ellipsisShownRight) {
                                    $ellipsisShownRight = true;
                                    ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <?php
                                }
                            }
                            ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <span class="d-none d-md-inline">Next</span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Flash Message -->
<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> text-white">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                <?php echo $_SESSION['flash_message']; ?>
            </div>
        </div>
    </div>
    <?php 
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (window.dashboardSidebar) {
                window.dashboardSidebar.toggle();
            }
        });
    }
    
    // Refresh sellers
    const refreshBtn = document.getElementById('refreshSellers');
    const mobileRefreshBtn = document.getElementById('mobileRefreshSellers');
    
    function refreshPage(e) {
        const btn = e.target?.closest('button');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            btn.disabled = true;
        }
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
    
    if (refreshBtn) refreshBtn.addEventListener('click', refreshPage);
    if (mobileRefreshBtn) mobileRefreshBtn.addEventListener('click', refreshPage);
    
    // Make table rows clickable on mobile
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('a') && !e.target.closest('span[data-bs-toggle="modal"]')) {
                const sellerId = this.closest('.seller-row').dataset.sellerId;
                const modal = document.getElementById('viewModal' + sellerId);
                if (modal) {
                    new bootstrap.Modal(modal).show();
                }
            }
        });
    });
});

function exportSellers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    const link = document.createElement('a');
    link.href = 'export-sellers.php?' + params.toString();
    link.download = 'sellers-export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    alert('Export started');
}

// Add CSS for mobile table
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Mobile Table Styles */
    .mobile-table-row {
        padding: 0.75rem;
        border-bottom: 1px solid #dee2e6;
        cursor: pointer;
    }
    
    .mobile-table-row:last-child {
        border-bottom: none;
    }
    
    @media (max-width: 767.98px) {
        .mobile-optimized-table {
            border: 0;
        }
        
        .mobile-optimized-table thead {
            display: none;
        }
        
        .mobile-optimized-table tr {
            display: block;
            margin-bottom: 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: white;
        }
        
        .mobile-optimized-table td {
            display: block;
            padding: 0 !important;
            border: none;
        }
        
        .mobile-optimized-table td.d-md-none {
            display: block !important;
        }
        
        .mobile-optimized-table td.d-none {
            display: none !important;
        }
        
        /* Touch-friendly buttons */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            min-height: 36px;
            min-width: 36px;
        }
        
        /* Modal optimization */
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .modal-content {
            border-radius: 0.5rem;
        }
        
        /* Better mobile header */
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Compact filters */
        .form-select-sm, .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Status badges compact */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .seller-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .seller-row:hover .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        }
    }
    
    /* Modal styles */
    .modal-dialog {
        max-width: 800px;
    }
    
    .modal-body dl.row {
        margin-bottom: 0.5rem;
    }
    
    .modal-body dt {
        color: #6c757d;
        font-weight: normal;
    }
    
    .modal-body dd {
        font-weight: 500;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>