<?php
// Determine active menu item
$current_uri = $_SERVER['REQUEST_URI'];
$active_dashboard = (strpos($current_uri, '/admin/dashboard.php') !== false) ? 'active' : '';
$active_users = (strpos($current_uri, '/admin/users/') !== false) ? 'active' : '';
$active_products = (strpos($current_uri, '/admin/products/') !== false) ? 'active' : '';
$active_orders = (strpos($current_uri, '/admin/orders/') !== false) ? 'active' : '';
$active_financial = (strpos($current_uri, '/admin/financial/') !== false) ? 'active' : '';
$active_reports = (strpos($current_uri, '/admin/reports/') !== false) ? 'active' : '';
$active_settings = (strpos($current_uri, '/admin/settings/') !== false) ? 'active' : '';
?>

<!-- Mobile backdrop for sidebar -->
<div class="sidebar-backdrop"></div>

<!-- Sidebar -->
<aside id="sidebar" class="col-lg-2 col-md-3 d-md-block bg-white sidebar shadow-sm border-end">
    <!-- Mobile sidebar header -->
    <div class="sidebar-mobile-header d-md-none p-3 border-bottom bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.jpeg" 
                     alt="Green Agric Logo" 
                     style="height:32px; width:auto; margin-right:10px;">
                <h5 class="mb-0">Admin Menu</h5>
            </div>
            <button class="btn-close btn-close-white" id="closeSidebar"></button>
        </div>
    </div>
    
    <div class="position-sticky pt-3">
        <!-- User profile mini -->
        <div class="text-center mb-4 p-3 border-bottom d-none d-md-block">
            <div class="mb-2">
                <i class="bi bi-person-circle fs-1 text-primary"></i>
            </div>
            <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></h6>
            <small class="text-muted">Administrator</small>
        </div>
        
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_dashboard; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    <span>Dashboard</span>
                    <?php if( ($stats['pending_approvals'] ?? 0) > 0 ): ?>
                        <span class="badge bg-warning float-end mt-1">
                            <?php echo $stats['pending_approvals']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Users Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_users; ?>" 
                   data-bs-toggle="collapse" 
                   href="#usersCollapse" 
                   aria-expanded="<?php echo $active_users ? 'true' : 'false'; ?>">
                    <i class="bi bi-people me-2"></i>
                    <span>Users</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_users ? 'show' : ''; ?>" id="usersCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/users/manage-users.php">
                                <i class="bi bi-person-lines-fill me-1"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/users/seller-approvals.php">
                                <i class="bi bi-shield-check me-1"></i>
                                Seller Approvals
                                <?php
                                // Get pending seller approvals count
                                $db = new Database();
                                $pending_sellers = $db->fetchOne("SELECT COUNT(*) as count FROM seller_profiles WHERE is_approved = FALSE");
                                if($pending_sellers && $pending_sellers['count'] > 0): ?>
                                    <span class="badge bg-danger float-end">
                                        <?php echo $pending_sellers['count']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Products Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_products; ?>" 
                   data-bs-toggle="collapse" 
                   href="#productsCollapse"
                   aria-expanded="<?php echo $active_products ? 'true' : 'false'; ?>">
                    <i class="bi bi-box-seam me-2"></i>
                    <span>Products</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_products ? 'show' : ''; ?>" id="productsCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/products/product-approvals.php">
                                <i class="bi bi-clock-history me-1"></i>
                                Approvals
                                <?php if($stats['pending_approvals'] ?? 0 > 0): ?>
                                    <span class="badge bg-warning float-end">
                                        <?php echo $stats['pending_approvals']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/products/manage-products.php">
                                <i class="bi bi-gear me-1"></i>
                                Manage Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/products/categories.php">
                                <i class="bi bi-tags me-1"></i>
                                Categories
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Orders Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_orders; ?>" 
                   href="<?php echo BASE_URL; ?>/admin/orders/manage-orders.php">
                    <i class="bi bi-bag me-2"></i>
                    <span>Orders</span>
                    <?php 
                    $pending_orders = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
                    if($pending_orders && $pending_orders['count'] > 0): ?>
                        <span class="badge bg-info float-end mt-1">
                            <?php echo $pending_orders['count']; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Financial Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_financial; ?>" 
                   data-bs-toggle="collapse" 
                   href="#financialCollapse"
                   aria-expanded="<?php echo $active_financial ? 'true' : 'false'; ?>">
                    <i class="bi bi-currency-exchange me-2"></i>
                    <span>Financial</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_financial ? 'show' : ''; ?>" id="financialCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/financial/payments.php">
                                <i class="bi bi-credit-card me-1"></i>
                                Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/financial/payouts.php">
                                <i class="bi bi-cash-stack me-1"></i>
                                Payouts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/financial/commissions.php">
                                <i class="bi bi-percent me-1"></i>
                                Commissions
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_reports; ?>" 
                   data-bs-toggle="collapse" 
                   href="#reportsCollapse"
                   aria-expanded="<?php echo $active_reports ? 'true' : 'false'; ?>">
                    <i class="bi bi-graph-up me-2"></i>
                    <span>Reports</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_reports ? 'show' : ''; ?>" id="reportsCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/reports/sales-reports.php">
                                <i class="bi bi-bar-chart me-1"></i>
                                Sales Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/reports/analytics.php">
                                <i class="bi bi-pie-chart me-1"></i>
                                Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/reports/platform-stats.php">
                                <i class="bi bi-server me-1"></i>
                                Platform Stats
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Settings -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_settings; ?>" 
                   data-bs-toggle="collapse" 
                   href="#settingsCollapse"
                   aria-expanded="<?php echo $active_settings ? 'true' : 'false'; ?>">
                    <i class="bi bi-gear me-2"></i>
                    <span>Settings</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_settings ? 'show' : ''; ?>" id="settingsCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/settings/platform-settings.php">
                                <i class="bi bi-sliders me-1"></i>
                                Platform Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/settings/location-management.php">
                                <i class="bi bi-geo-alt me-1"></i>
                                Locations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/admin/settings/system-logs.php">
                                <i class="bi bi-journal-text me-1"></i>
                                System Logs
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        
        <!-- Quick Actions (Desktop only) -->
        <div class="border-top mt-4 pt-3 d-none d-md-block">
            <h6 class="text-muted mb-2">Quick Actions</h6>
            <div class="d-grid gap-2">
                <a href="<?php echo BASE_URL; ?>/admin/products/product-approvals.php" 
                   class="btn btn-sm btn-warning">
                    <i class="bi bi-shield-check me-1"></i>
                    Review Approvals
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/orders/manage-orders.php?filter=pending" 
                   class="btn btn-sm btn-info">
                    <i class="bi bi-bag-check me-1"></i>
                    Process Orders
                </a>
            </div>
        </div>
    </div>
</aside>


<!-- Add this CSS to your style.css file -->
<style>
/* Mobile sidebar styles */
@media (max-width: 767.98px) {
    #sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 280px;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1040;
        overflow-y: auto;
    }
    
    #sidebar.show {
        transform: translateX(0);
        display: inline;
    }
    
    .sidebar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1039;
        display: none;
    }
    
    .sidebar-backdrop.show {
        display: block;
    }
    
    .sidebar-toggle {
        position: fixed;
        left: 15px;
        top: 70px;
        z-index: 1030;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
}

/* Sidebar link styles */
#sidebar .nav-link {
    border-radius: 0.375rem;
    margin: 0.125rem 0.5rem;
    padding: 0.75rem 1rem;
    transition: all 0.2s;
}

#sidebar .nav-link:hover,
#sidebar .nav-link.active {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
}

#sidebar .sub-menu .nav-link {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}
</style>

<!-- Add this JavaScript to your main.js or include it -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const openBtn = document.getElementById('mobileSidebarToggle');
    const closeBtn = document.getElementById('closeSidebar');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
        document.body.style.overflow =
            sidebar.classList.contains('show') ? 'hidden' : '';
    }

    if (openBtn) openBtn.addEventListener('click', toggleSidebar);
    if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
    if (backdrop) backdrop.addEventListener('click', toggleSidebar);
});
</script>
