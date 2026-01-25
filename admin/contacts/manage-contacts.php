<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';


$db = new Database();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

// Status filter
if ($status && in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
    $where[] = "c.status = ?";
    $params[] = $status;
    $types .= 's';
}

// Type filter
if ($type && in_array($type, ['general', 'order', 'product', 'seller', 'payment', 'delivery', 'technical', 'partnership', 'other'])) {
    $where[] = "c.contact_type = ?";
    $params[] = $type;
    $types .= 's';
}

// Search filter
if ($search) {
    $where[] = "(c.full_name LIKE ? OR c.email LIKE ? OR c.subject LIKE ? OR c.message LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ssss';
}

// Date filter
if ($date_from) {
    $where[] = "DATE(c.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to) {
    $where[] = "DATE(c.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

// Build WHERE clause
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM contacts c $where_clause";
$count_result = $db->fetchOne($count_query, $params);
$total_contacts = $count_result['total'];
$total_pages = ceil($total_contacts / $limit);

// Get contacts with pagination
$query = "
    SELECT c.*, 
           u.first_name as user_first_name, 
           u.last_name as user_last_name,
           u.email as user_email,
           CONCAT(u.first_name, ' ', u.last_name) as user_full_name,
           admin.first_name as resolved_by_name
    FROM contacts c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN users admin ON c.resolved_by = admin.id 
    $where_clause 
    ORDER BY 
        CASE c.status 
            WHEN 'new' THEN 1 
            WHEN 'in_progress' THEN 2 
            WHEN 'resolved' THEN 3 
            WHEN 'closed' THEN 4 
        END,
        c.created_at DESC 
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$contacts = $db->fetchAll($query, $params);

// Get counts for stats
$stats = [
    'total' => $total_contacts,
    'new' => $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE status = 'new'")['count'],
    'in_progress' => $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE status = 'in_progress'")['count'],
    'resolved' => $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE status = 'resolved'")['count'],
    'today' => $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE DATE(created_at) = CURDATE()")['count']
];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $contact_id = $_POST['contact_id'];
        $new_status = $_POST['status'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        $db->query(
            "UPDATE contacts SET status = ?, admin_notes = ?, resolved_by = ?, updated_at = NOW() WHERE id = ?",
            [$new_status, $admin_notes, $_SESSION['user_id'], $contact_id]
        );
        
        $_SESSION['flash_message'] = 'Contact status updated successfully';
        $_SESSION['flash_type'] = 'success';
        header('Location: manage-contacts.php');
        exit;
    }
    
    if (isset($_POST['delete_contact'])) {
        $contact_id = $_POST['contact_id'];
        $db->query("DELETE FROM contacts WHERE id = ?", [$contact_id]);
        
        $_SESSION['flash_message'] = 'Contact deleted successfully';
        $_SESSION['flash_type'] = 'success';
        header('Location: manage-contacts.php');
        exit;
    }
    
    if (isset($_POST['mark_all_read'])) {
        $db->query("UPDATE contacts SET status = 'in_progress' WHERE status = 'new'");
        
        $_SESSION['flash_message'] = 'All new contacts marked as in progress';
        $_SESSION['flash_type'] = 'success';
        header('Location: manage-contacts.php');
        exit;
    }
}

$page_title = "Manage Contacts";
$page_css = 'dashboard.css';
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
                    <button class="btn btn-outline-primary me-3" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0">Contact Messages</h1>
                        <small class="text-muted">Manage customer inquiries</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Contact Messages</h1>
                    <p class="text-muted mb-0">View and respond to customer inquiries</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportContacts()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshContacts">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="dropdown">
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-sm btn-primary">
                                <i class="bi bi-check-all me-1"></i> Mark All as Read
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <!-- Total Contacts -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Messages</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total']); ?></h3>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-envelope fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- New Messages -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">New Messages</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['new']); ?></h3>
                                    <?php if($stats['new'] > 0): ?>
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            Needs attention
                                        </small>
                                    <?php else: ?>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            All caught up
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-clock fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- In Progress -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">In Progress</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['in_progress']); ?></h3>
                                </div>
                                <div class="bg-info bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-gear fs-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Today's Messages -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start border-3 shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Today's Messages</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['today']); ?></h3>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-calendar-day fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 pb-2">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2 text-primary"></i>
                        Filter Messages
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <option value="general" <?php echo $type === 'general' ? 'selected' : ''; ?>>General</option>
                                <option value="order" <?php echo $type === 'order' ? 'selected' : ''; ?>>Order</option>
                                <option value="product" <?php echo $type === 'product' ? 'selected' : ''; ?>>Product</option>
                                <option value="seller" <?php echo $type === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                <option value="payment" <?php echo $type === 'payment' ? 'selected' : ''; ?>>Payment</option>
                                <option value="delivery" <?php echo $type === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                                <option value="technical" <?php echo $type === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                <option value="partnership" <?php echo $type === 'partnership' ? 'selected' : ''; ?>>Partnership</option>
                                <option value="other" <?php echo $type === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, email, subject..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter me-1"></i> Apply Filters
                                </button>
                                <a href="manage-contacts.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Clear Filters
                                </a>
                                <?php if($stats['new'] > 0): ?>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="mark_all_read" class="btn btn-warning">
                                            <i class="bi bi-check-all me-1"></i> Mark All as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0">Messages (<?php echo $total_contacts; ?>)</h5>
                    <small class="text-muted">
                        Showing <?php echo min($offset + 1, $total_contacts); ?>-<?php echo min($offset + $limit, $total_contacts); ?> of <?php echo $total_contacts; ?> messages
                    </small>
                </div>
                <div class="text-muted d-none d-md-block">
                    <?php if($stats['new'] > 0): ?>
                        <span class="badge bg-warning">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?php echo $stats['new']; ?> new messages
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contacts List -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($contacts)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-envelope-open text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No contact messages found</h4>
                            <p class="text-muted mb-4">No messages match your current filters.</p>
                            <a href="manage-contacts.php" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Mobile View - Cards -->
                        <div class="d-md-none">
                            <div class="list-group list-group-flush">
                                <?php foreach ($contacts as $contact): ?>
                                    <?php 
                                    $status_colors = [
                                        'new' => 'warning',
                                        'in_progress' => 'info',
                                        'resolved' => 'success',
                                        'closed' => 'secondary'
                                    ];
                                    
                                    $type_badges = [
                                        'general' => 'primary',
                                        'order' => 'info',
                                        'product' => 'success',
                                        'seller' => 'warning',
                                        'payment' => 'danger',
                                        'delivery' => 'secondary',
                                        'technical' => 'dark',
                                        'partnership' => 'success',
                                        'other' => 'secondary'
                                    ];
                                    ?>
                                    
                                    <div class="list-group-item <?php echo $contact['status'] === 'new' ? 'bg-warning bg-opacity-10' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-<?php echo $status_colors[$contact['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded">
                                                    <i class="bi bi-envelope text-<?php echo $status_colors[$contact['status']] ?? 'secondary'; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($contact['subject']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($contact['full_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <span class="badge bg-<?php echo $status_colors[$contact['status']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $contact['status'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <span class="badge bg-<?php echo $type_badges[$contact['contact_type']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($contact['contact_type']); ?>
                                            </span>
                                            <?php if ($contact['user_id']): ?>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-person-check me-1"></i> Registered
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="text-muted mb-2">
                                            <small>
                                                <?php echo formatDate($contact['created_at'], 'M j, Y g:i A'); ?>
                                            </small>
                                        </p>
                                        
                                        <p class="mb-3">
                                            <?php echo strlen($contact['message']) > 100 ? substr(htmlspecialchars($contact['message']), 0, 100) . '...' : htmlspecialchars($contact['message']); ?>
                                        </p>
                                        
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary flex-fill" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $contact['id']; ?>">
                                                <i class="bi bi-eye me-1"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-outline-success flex-fill" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#replyModal<?php echo $contact['id']; ?>">
                                                <i class="bi bi-reply me-1"></i> Reply
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $contact['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Desktop View - Table -->
                        <div class="d-none d-md-block">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50">ID</th>
                                            <th>From</th>
                                            <th>Email</th>
                                            <th>Type</th>
                                            <th>Subject</th>
                                            <th width="100">Status</th>
                                            <th width="120">Date</th>
                                            <th width="140" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                            <tr class="<?php echo $contact['status'] === 'new' ? 'table-warning' : ''; ?>">
                                                <td>
                                                    <span class="text-muted">#<?php echo $contact['id']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-<?php echo $status_colors[$contact['status']] ?? 'secondary'; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                            <i class="bi bi-person text-<?php echo $status_colors[$contact['status']] ?? 'secondary'; ?>"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($contact['full_name']); ?></strong>
                                                            <?php if ($contact['user_id']): ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-person-check"></i> Registered
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>" 
                                                       class="text-decoration-none">
                                                        <?php echo htmlspecialchars($contact['email']); ?>
                                                    </a>
                                                    <?php if ($contact['phone']): ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($contact['phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $type_badges[$contact['contact_type']] ?? 'secondary'; ?>">
                                                        <?php echo ucfirst($contact['contact_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($contact['subject']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_colors[$contact['status']] ?? 'secondary'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $contact['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo formatDate($contact['created_at'], 'M j'); ?><br>
                                                    <small class="text-muted"><?php echo formatDate($contact['created_at'], 'g:i A'); ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewModal<?php echo $contact['id']; ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#replyModal<?php echo $contact['id']; ?>">
                                                            <i class="bi bi-reply"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?php echo $contact['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modals for each contact -->
<?php foreach ($contacts as $contact): ?>
    <!-- View Modal -->
    <div class="modal fade" id="viewModal<?php echo $contact['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-envelope me-2"></i>
                        Message #<?php echo $contact['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">Contact Information</h6>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Name</label>
                                <p class="mb-0"><?php echo htmlspecialchars($contact['full_name']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Email</label>
                                <p class="mb-0">
                                    <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>">
                                        <?php echo htmlspecialchars($contact['email']); ?>
                                    </a>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Phone</label>
                                <p class="mb-0"><?php echo $contact['phone'] ? htmlspecialchars($contact['phone']) : 'Not provided'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">User Status</label>
                                <p class="mb-0">
                                    <?php if ($contact['user_id']): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-person-check me-1"></i> Registered User
                                        </span>
                                        <small class="text-muted d-block mt-1">
                                            <?php echo htmlspecialchars($contact['user_full_name'] ?? 'N/A'); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-person-x me-1"></i> Guest
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3">Message Details</h6>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Type</label>
                                <p class="mb-0">
                                    <span class="badge bg-<?php echo $type_badges[$contact['contact_type']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($contact['contact_type']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Subject</label>
                                <p class="mb-0"><?php echo htmlspecialchars($contact['subject']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Submitted</label>
                                <p class="mb-0"><?php echo formatDate($contact['created_at']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Last Updated</label>
                                <p class="mb-0"><?php echo $contact['updated_at'] ? formatDate($contact['updated_at']) : 'Never'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Status</label>
                                <p class="mb-0">
                                    <span class="badge bg-<?php echo $status_colors[$contact['status']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $contact['status'])); ?>
                                    </span>
                                </p>
                            </div>
                            <?php if ($contact['resolved_by_name']): ?>
                                <div class="mb-3">
                                    <label class="form-label text-muted mb-1">Resolved By</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($contact['resolved_by_name']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="border-bottom pb-2 mb-3">Message</h6>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($contact['message'])); ?>
                        </div>
                    </div>

                    <?php if ($contact['admin_notes']): ?>
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3">Admin Notes</h6>
                            <div class="border rounded p-3 bg-info bg-opacity-10">
                                <?php echo nl2br(htmlspecialchars($contact['admin_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="border-top pt-3">
                        <h6 class="mb-3">Update Status</h6>
                        <form method="POST" action="">
                            <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="new" <?php echo $contact['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                        <option value="in_progress" <?php echo $contact['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $contact['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $contact['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Admin Notes</label>
                                    <textarea class="form-control" name="admin_notes" rows="3" 
                                              placeholder="Add internal notes here..."><?php echo htmlspecialchars($contact['admin_notes'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-12">
                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" name="update_status" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i> Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reply Modal -->
    <div class="modal fade" id="replyModal<?php echo $contact['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-reply me-2"></i>
                        Reply to <?php echo htmlspecialchars($contact['full_name']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="replyForm<?php echo $contact['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">To</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($contact['email']); ?>" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" value="Re: <?php echo htmlspecialchars($contact['subject']); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Original Message</label>
                            <div class="border rounded p-2 bg-light">
                                <?php echo strlen($contact['message']) > 100 ? substr(htmlspecialchars($contact['message']), 0, 100) . '...' : htmlspecialchars($contact['message']); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your Response</label>
                            <textarea class="form-control" rows="5" placeholder="Type your response here..." required></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="markResolved<?php echo $contact['id']; ?>" checked>
                            <label class="form-check-label" for="markResolved<?php echo $contact['id']; ?>">
                                Mark as resolved after sending
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="saveTemplate<?php echo $contact['id']; ?>">
                            <label class="form-check-label" for="saveTemplate<?php echo $contact['id']; ?>">
                                Save as response template
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendReply(<?php echo $contact['id']; ?>)">
                        <i class="bi bi-send me-1"></i> Send Reply
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            if (window.dashboardSidebar) {
                window.dashboardSidebar.toggle();
            }
        });
    }
    
    // Refresh contacts
    const refreshBtn = document.getElementById('refreshContacts');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            this.disabled = true;
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    }
    
    // Make table rows clickable on mobile
    document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', function() {
            window.location = this.getAttribute('onclick').match(/window\.location='([^']+)'/)[1];
        });
    });
    
    // Auto-refresh for new messages every 30 seconds if on new tab
    if (window.location.search.includes('status=new') || !window.location.search) {
        setInterval(() => {
            if (!document.hidden) {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        // Check for new messages count change
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newCount = doc.querySelector('.dashboard-card .card-title')?.textContent;
                        const currentCount = document.querySelector('.dashboard-card .card-title')?.textContent;
                        
                        if (newCount && newCount !== currentCount) {
                            // Show notification
                            agriApp.showToast('New messages available. Refreshing...', 'info');
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    });
            }
        }, 30000);
    }
});

function confirmDelete(contactId) {
    agriApp.confirm(
        'Delete Contact Message',
        'Are you sure you want to delete this contact message? This action cannot be undone.',
        'Delete',
        'Cancel',
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'contact_id';
            input.value = contactId;
            form.appendChild(input);
            
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_contact';
            deleteInput.value = '1';
            form.appendChild(deleteInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function sendReply(contactId) {
    const form = document.getElementById('replyForm' + contactId);
    const message = form.querySelector('textarea').value;
    const markResolved = form.querySelector('#markResolved' + contactId).checked;
    
    if (!message.trim()) {
        agriApp.showToast('Please enter a message', 'error');
        return;
    }
    
    // Show loading state
    agriApp.showToast('Sending reply...', 'info');
    
    // In a real implementation, this would send via AJAX
    setTimeout(() => {
        // Simulate API call
        fetch('api/send-reply.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                contact_id: contactId,
                message: message,
                mark_resolved: markResolved
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                agriApp.showToast('Reply sent successfully', 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('replyModal' + contactId));
                modal.hide();
                
                // Reload page after 1 second
                setTimeout(() => window.location.reload(), 1000);
            } else {
                agriApp.showToast('Failed to send reply: ' + data.message, 'error');
            }
        })
        .catch(error => {
            agriApp.showToast('Network error. Please try again.', 'error');
        });
    }, 1000);
}

function exportContacts() {
    // Build export URL with current filters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // Create temporary link
    const link = document.createElement('a');
    link.href = 'export-contacts.php?' + params.toString();
    link.download = 'contacts-export-' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    agriApp.showToast('Export started. Download should begin shortly.', 'info');
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Mobile optimizations */
    @media (max-width: 767.98px) {
        .modal-dialog {
            margin: 0.5rem;
        }
        .modal-content {
            border-radius: 0.5rem;
        }
        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .list-group-item {
            padding: 1rem;
        }
    }
    
    /* Hover effects */
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>