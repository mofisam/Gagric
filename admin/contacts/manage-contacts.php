<?php
// =============================================
// SECURITY & CONFIGURATION
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true
    ]);
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Mailer.php';

$db = new Database();

// =============================================
// HANDLE POST ACTIONS
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_message'] = 'Security validation failed. Please refresh and try again.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: manage-contacts.php');
        exit;
    }
    
    // Handle Send Reply
    if (isset($_POST['send_reply'])) {
        $contact_id = isset($_POST['contact_id']) ? (int)$_POST['contact_id'] : 0;
        $reply_message = isset($_POST['reply_message']) ? trim($_POST['reply_message']) : '';
        $mark_resolved = isset($_POST['mark_resolved']) ? true : false;
        
        if (empty($reply_message)) {
            $_SESSION['flash_message'] = 'Please enter a reply message.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: manage-contacts.php');
            exit;
        }
        
        if ($contact_id > 0) {
            try {
                // Get contact details
                $contact = $db->fetchOne(
                    "SELECT full_name, email, subject, message FROM contacts WHERE id = ?",
                    [$contact_id]
                );
                
                if (!$contact) {
                    $_SESSION['flash_message'] = 'Contact not found.';
                    $_SESSION['flash_type'] = 'danger';
                    header('Location: manage-contacts.php');
                    exit;
                }
                
                // Send email
                $mailer = new Mailer(false);
                $email_sent = $mailer->sendReplyEmail(
                    $contact['email'],
                    $contact['full_name'],
                    $contact['subject'],
                    $reply_message,
                    $contact['message']
                );
                
                // =============================================
                // SAVE REPLY TO DATABASE
                // =============================================
                $admin_id = $_SESSION['user_id'];
                
                // Insert reply into contact_replies table
                $reply_data = [
                    'contact_id' => $contact_id,
                    'admin_id' => $admin_id,
                    'reply_message' => $reply_message,
                    'email_sent' => $email_sent ? 1 : 0,
                    'sent_at' => $email_sent ? date('Y-m-d H:i:s') : null,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $reply_id = $db->insert('contact_replies', $reply_data);
                
                // Update contact with reply count and last reply info
                $db->query(
                    "UPDATE contacts 
                     SET reply_count = reply_count + 1,
                         last_reply_at = NOW(),
                         last_reply_by = ?,
                         admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n\nReply sent on ', NOW(), ' by admin: ', ?)
                     WHERE id = ?",
                    [$admin_id, $reply_message, $contact_id]
                );
                
                // Update contact status based on mark_resolved
                if ($mark_resolved) {
                    $new_status = 'resolved';
                    $db->query(
                        "UPDATE contacts SET status = 'resolved', resolved_by = ?, updated_at = NOW() WHERE id = ?",
                        [$admin_id, $contact_id]
                    );
                } else {
                    $db->query(
                        "UPDATE contacts SET status = 'in_progress', updated_at = NOW() WHERE id = ?",
                        [$contact_id]
                    );
                }
                
                // =============================================
                // LOG THE ACTIVITY
                // =============================================
                // Create admin activity log if table exists
                try {
                    $db->query(
                        "INSERT INTO admin_activity_log (admin_id, action, entity_type, entity_id, details, ip_address, created_at) 
                         VALUES (?, 'send_reply', 'contact', ?, ?, ?, NOW())",
                        [
                            $admin_id,
                            $contact_id,
                            json_encode([
                                'to_email' => $contact['email'],
                                'subject' => 'Re: ' . $contact['subject'],
                                'reply_id' => $reply_id,
                                'email_sent' => $email_sent,
                                'mark_resolved' => $mark_resolved
                            ]),
                            $_SERVER['REMOTE_ADDR']
                        ]
                    );
                } catch (Exception $e) {
                    // Activity log table might not exist, silently continue
                    error_log("Activity log error: " . $e->getMessage());
                }
                
                // Set flash message
                if ($email_sent) {
                    $_SESSION['flash_message'] = 'Reply sent successfully to ' . htmlspecialchars($contact['email']) . ' and saved to database.';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    // Even if email failed, the reply is saved
                    $_SESSION['flash_message'] = 'Reply saved to database but email sending failed. Please check mail configuration.';
                    $_SESSION['flash_type'] = 'warning';
                }
                
            } catch (Exception $e) {
                error_log("Reply error: " . $e->getMessage());
                $_SESSION['flash_message'] = 'Error sending reply: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
        
        header('Location: manage-contacts.php');
        exit;
    }
    
    // Handle Update Status
    if (isset($_POST['update_status'])) {
        $contact_id = isset($_POST['contact_id']) ? (int)$_POST['contact_id'] : 0;
        $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
        
        $allowed_statuses = ['new', 'in_progress', 'resolved', 'closed'];
        if (!in_array($new_status, $allowed_statuses)) {
            $_SESSION['flash_message'] = 'Invalid status value.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: manage-contacts.php');
            exit;
        }
        
        if ($contact_id > 0) {
            try {
                $result = $db->query(
                    "UPDATE contacts SET status = ?, admin_notes = ?, resolved_by = ?, updated_at = NOW() WHERE id = ?",
                    [$new_status, $admin_notes, $_SESSION['user_id'], $contact_id]
                );
                
                if ($result !== false) {
                    $_SESSION['flash_message'] = 'Contact status updated successfully to "' . $new_status . '".';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Failed to update contact status. Please try again.';
                    $_SESSION['flash_type'] = 'danger';
                }
            } catch (Exception $e) {
                error_log("Contact update error: " . $e->getMessage());
                $_SESSION['flash_message'] = 'Database error occurred. Please try again.';
                $_SESSION['flash_type'] = 'danger';
            }
        }
        
        header('Location: manage-contacts.php');
        exit;
    }
    
    // Handle Delete
    if (isset($_POST['delete_contact'])) {
        $contact_id = isset($_POST['contact_id']) ? (int)$_POST['contact_id'] : 0;
        
        if ($contact_id > 0) {
            try {
                // First delete related replies
                $db->query("DELETE FROM contact_replies WHERE contact_id = ?", [$contact_id]);
                
                // Then delete the contact
                $result = $db->query("DELETE FROM contacts WHERE id = ?", [$contact_id]);
                
                if ($result !== false) {
                    $_SESSION['flash_message'] = 'Contact message and all replies deleted successfully.';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Failed to delete contact.';
                    $_SESSION['flash_type'] = 'danger';
                }
            } catch (Exception $e) {
                error_log("Contact delete error: " . $e->getMessage());
                $_SESSION['flash_message'] = 'Database error occurred.';
                $_SESSION['flash_type'] = 'danger';
            }
        }
        
        header('Location: manage-contacts.php');
        exit;
    }
    
    // Handle Mark All as Read
    if (isset($_POST['mark_all_read'])) {
        try {
            $result = $db->query("UPDATE contacts SET status = 'in_progress', updated_at = NOW() WHERE status = 'new'");
            
            if ($result !== false) {
                $_SESSION['flash_message'] = 'All new contacts marked as in progress.';
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Failed to update contacts.';
                $_SESSION['flash_type'] = 'danger';
            }
        } catch (Exception $e) {
            error_log("Bulk update error: " . $e->getMessage());
            $_SESSION['flash_message'] = 'Database error occurred.';
            $_SESSION['flash_type'] = 'danger';
        }
        
        header('Location: manage-contacts.php');
        exit;
    }
}

// =============================================
// GET DATA FOR DISPLAY
// =============================================

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Build query
$conditions = [];
$params = [];

if ($status && in_array($status, ['new', 'in_progress', 'resolved', 'closed'])) {
    $conditions[] = "c.status = ?";
    $params[] = $status;
}

if ($type && in_array($type, ['general', 'order', 'product', 'seller', 'payment', 'delivery', 'technical', 'partnership', 'other'])) {
    $conditions[] = "c.contact_type = ?";
    $params[] = $type;
}

if (!empty($search)) {
    $conditions[] = "(c.full_name LIKE ? OR c.email LIKE ? OR c.subject LIKE ? OR c.message LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from)) {
    $conditions[] = "DATE(c.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $conditions[] = "DATE(c.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM contacts c $where_clause";
$count_result = $db->fetchOne($count_sql, $params);
$total_contacts = $count_result ? $count_result['total'] : 0;
$total_pages = max(1, ceil($total_contacts / $limit));

// Get contacts with reply count
$sql = "
    SELECT c.*, 
           u.first_name as user_first_name, 
           u.last_name as user_last_name,
           u.email as user_email,
           CONCAT(u.first_name, ' ', u.last_name) as user_full_name,
           admin.first_name as resolved_by_first_name,
           admin.last_name as resolved_by_last_name,
           CONCAT(admin.first_name, ' ', admin.last_name) as resolved_by_name,
           (SELECT COUNT(*) FROM contact_replies WHERE contact_id = c.id) as reply_count
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

$contacts = $db->fetchAll($sql, $params);

// Get stats
$stats = [
    'total' => $total_contacts,
    'new' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'today' => 0
];

try {
    $stats['new'] = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE status = 'new'")['count'] ?? 0;
    $stats['in_progress'] = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE status = 'in_progress'")['count'] ?? 0;
    $stats['resolved'] = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE status = 'resolved'")['count'] ?? 0;
    $stats['today'] = $db->fetchOne("SELECT COUNT(*) as count FROM contacts WHERE DATE(created_at) = CURDATE()")['count'] ?? 0;
} catch (Exception $e) {
    error_log("Stats query error: " . $e->getMessage());
}

// Flash messages
$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$flash_type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$page_title = "Manage Contacts";
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
                        <form method="POST" class="d-inline" id="markAllReadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" name="mark_all_read" class="btn btn-sm btn-primary">
                                <i class="bi bi-check-all me-1"></i> Mark All as Read
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if (!empty($flash_message)): ?>
                <div class="alert alert-<?php echo $flash_type ?? 'info'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start h-100">
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
                
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start  h-100">
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
                
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start  h-100">
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
                
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start h-100">
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
                                                <?php echo date('M j, Y g:i A', strtotime($contact['created_at'])); ?>
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
                                            <button class="btn btn-sm btn-outline-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#repliesModal<?php echo $contact['id']; ?>">
                                                <i class="bi bi-chat-dots"></i>
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
                                            <th width="80">Replies</th> <!-- NEW COLUMN -->
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
                                                    <?php if ($contact['reply_count'] > 0): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo $contact['reply_count']; ?>
                                                            <i class="bi bi-reply-all ms-1"></i>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M j', strtotime($contact['created_at'])); ?><br>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($contact['created_at'])); ?></small>
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
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#repliesModal<?php echo $contact['id']; ?>">
                                                            <i class="bi bi-chat-dots"></i>
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

<!-- Hidden Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="contact_id" id="deleteContactId" value="">
    <input type="hidden" name="delete_contact" value="1">
</form>

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
                                <p class="mb-0"><?php echo date('M j, Y g:i A', strtotime($contact['created_at'])); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1">Last Updated</label>
                                <p class="mb-0"><?php echo $contact['updated_at'] ? date('M j, Y g:i A', strtotime($contact['updated_at'])) : 'Never'; ?></p>
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
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                            <input type="hidden" name="update_status" value="1">
                            
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

    <!-- View Replies Modal -->
    <div class="modal fade" id="repliesModal<?php echo $contact['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-chat-dots me-2"></i>
                        Reply History - #<?php echo $contact['id']; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php
                    $replies = $db->fetchAll(
                        "SELECT r.*, 
                                CONCAT(u.first_name, ' ', u.last_name) as admin_name
                        FROM contact_replies r
                        LEFT JOIN users u ON r.admin_id = u.id
                        WHERE r.contact_id = ?
                        ORDER BY r.created_at DESC",
                        [$contact['id']]
                    );
                    ?>
                    
                    <?php if (empty($replies)): ?>
                        <p class="text-muted text-center py-4">No replies sent for this contact.</p>
                    <?php else: ?>
                        <?php foreach ($replies as $reply): ?>
                            <div class="card mb-3 border-<?php echo $reply['email_sent'] ? 'success' : 'warning'; ?>">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($reply['admin_name']); ?></strong>
                                        <span class="text-muted ms-2">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if ($reply['email_sent']): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i> Sent
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="bi bi-exclamation-circle me-1"></i> Not Sent
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                    <input type="hidden" name="send_reply" value="1">
                    
                    <div class="modal-body">
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
                                <?php echo nl2br(htmlspecialchars($contact['message'])); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your Response <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reply_message" rows="6" 
                                      placeholder="Type your response here..." required></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="mark_resolved" id="markResolved<?php echo $contact['id']; ?>" value="1" checked>
                            <label class="form-check-label" for="markResolved<?php echo $contact['id']; ?>">
                                Mark as resolved after sending
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Send Reply
                        </button>
                    </div>
                </form>
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
});

function confirmDelete(contactId) {
    if (confirm('Are you sure you want to delete this contact message? This action cannot be undone.')) {
        document.getElementById('deleteContactId').value = contactId;
        document.getElementById('deleteForm').submit();
    }
}

function exportContacts() {
    alert('Export feature coming soon!');
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