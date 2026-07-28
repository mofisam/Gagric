<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

$db = new Database();

$error_log_path = ROOT_PATH . '/classes/logs/error.log';
$message = '';

// Handle clearing the error log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_error_log'])) {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = '<div class="alert alert-danger">Security validation failed. Please try again.</div>';
    } elseif (file_exists($error_log_path)) {
        file_put_contents($error_log_path, '');
        setFlashMessage('Error log cleared.', 'success');
        header('Location: system-logs.php');
        exit;
    }
}

// --- Application error log (file-based) ---
$error_lines = [];
$error_log_size = 0;
if (file_exists($error_log_path)) {
    $error_log_size = filesize($error_log_path);
    $raw = file($error_log_path, FILE_IGNORE_NEW_LINES);
    $error_lines = array_reverse(array_slice($raw, -300)); // most recent first, capped for page weight
}

// --- Activity log (from the activity_logs table) ---
$activity_action = trim($_GET['action'] ?? '');
$activity_page = max(1, (int)($_GET['activity_page'] ?? 1));
$activity_per_page = 20;
$activity_offset = ($activity_page - 1) * $activity_per_page;

$where = '1=1';
$params = [];
if ($activity_action !== '') {
    $where .= " AND al.action LIKE ?";
    $params[] = '%' . $activity_action . '%';
}

$activity_logs = $db->fetchAll(
    "SELECT al.*, u.first_name, u.last_name, u.role
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     WHERE $where
     ORDER BY al.created_at DESC
     LIMIT $activity_per_page OFFSET $activity_offset",
    $params
);

$total_activity = $db->fetchOne("SELECT COUNT(*) as count FROM activity_logs al WHERE $where", $params)['count'];
$total_activity_pages = max(1, ceil($total_activity / $activity_per_page));

$page_title = "System Logs";
$page_css = 'dashboard.css';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">System Logs</h1>
                    <p class="text-muted mb-0">Application errors and user activity</p>
                </div>
            </div>

            <?php echo $message; ?>

            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#errorLogTab" type="button">
                        <i class="bi bi-exclamation-triangle me-1"></i> Error Log
                        <?php if ($error_log_size > 0): ?>
                            <span class="badge bg-secondary ms-1"><?php echo number_format(count($error_lines)); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activityLogTab" type="button">
                        <i class="bi bi-clock-history me-1"></i> Activity Log
                        <span class="badge bg-secondary ms-1"><?php echo number_format($total_activity); ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Error Log -->
                <div class="tab-pane fade show active" id="errorLogTab">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small">
                                    <?php echo htmlspecialchars($error_log_path); ?>
                                    <?php if ($error_log_size > 0): ?>
                                        (<?php echo number_format($error_log_size / 1024, 1); ?> KB, showing last <?php echo count($error_lines); ?> lines)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($error_lines)): ?>
                                <form method="POST" onsubmit="return confirm('Clear the entire error log? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                    <button type="submit" name="clear_error_log" value="1" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash me-1"></i> Clear Log
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($error_lines)): ?>
                                <pre class="log-viewer mb-0"><?php
                                    foreach ($error_lines as $line) {
                                        $is_error = (stripos($line, 'error') !== false || stripos($line, 'failed') !== false);
                                        echo '<span class="' . ($is_error ? 'log-line-error' : 'log-line') . '">' . htmlspecialchars($line) . "</span>\n";
                                    }
                                ?></pre>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-3 mb-0">No errors logged - or the log file doesn't exist yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Activity Log -->
                <div class="tab-pane fade" id="activityLogTab">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end">
                                <input type="hidden" name="tab" value="activity">
                                <div class="col-auto">
                                    <label class="form-label small mb-1">Filter by action</label>
                                    <input type="text" name="action" class="form-control form-control-sm"
                                           placeholder="e.g. login, register, order_placed"
                                           value="<?php echo htmlspecialchars($activity_action); ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-sm btn-success">Filter</button>
                                    <a href="system-logs.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <?php if (!empty($activity_logs)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">User</th>
                                                <th>Action</th>
                                                <th>Details</th>
                                                <th>IP Address</th>
                                                <th class="pe-3">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activity_logs as $log): ?>
                                                <tr>
                                                    <td class="ps-3">
                                                        <?php if ($log['first_name']): ?>
                                                            <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($log['role']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">User #<?php echo $log['user_id']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                                    <td class="text-muted small"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                                                    <td class="text-muted small"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                                                    <td class="pe-3 text-muted small"><?php echo formatDate($log['created_at'], 'M j, Y g:i A'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($total_activity_pages > 1): ?>
                                    <div class="d-flex justify-content-center py-3">
                                        <nav>
                                            <ul class="pagination pagination-sm mb-0">
                                                <?php for ($i = 1; $i <= $total_activity_pages; $i++): ?>
                                                    <li class="page-item <?php echo $i === $activity_page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?action=<?php echo urlencode($activity_action); ?>&activity_page=<?php echo $i; ?>#activityLogTab">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                            </ul>
                                        </nav>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-clock-history text-muted" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mt-3 mb-0">No activity recorded yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.log-viewer {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 1rem;
    max-height: 600px;
    overflow-y: auto;
    font-size: 0.8rem;
    font-family: 'Courier New', monospace;
    white-space: pre-wrap;
    word-break: break-all;
}
.log-line-error {
    color: #f28b82;
    display: block;
}
.log-line {
    color: #d4d4d4;
    display: block;
}
</style>

<script>
// Keep the Activity tab active after filtering/paginating it
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.search.includes('action=') || window.location.search.includes('activity_page=') || window.location.hash === '#activityLogTab') {
        const trigger = document.querySelector('[data-bs-target="#activityLogTab"]');
        if (trigger) { new bootstrap.Tab(trigger).show(); }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>