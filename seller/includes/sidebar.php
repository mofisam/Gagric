<?php
// Determine active menu item
$current_uri = $_SERVER['REQUEST_URI'];
$active_dashboard = (strpos($current_uri, '/seller/dashboard.php') !== false) ? 'active' : '';
$active_products = (strpos($current_uri, '/seller/products/') !== false) ? 'active' : '';
$active_orders = (strpos($current_uri, '/seller/orders/') !== false) ? 'active' : '';
$active_inventory = (strpos($current_uri, '/seller/inventory/') !== false) ? 'active' : '';
$active_finances = (strpos($current_uri, '/seller/finances/') !== false) ? 'active' : '';
$active_analytics = (strpos($current_uri, '/seller/analytics/') !== false) ? 'active' : '';
$active_store = (strpos($current_uri, '/seller/store/') !== false) ? 'active' : '';

// Get seller stats for badges (you'll need to pass these from the parent page)
if (!isset($seller_stats)) {
    // Default values - these should be overridden by the including page
    $seller_stats = [
        'pending_products' => 0,
        'low_stock_count' => 0,
        'pending_orders' => 0,
        'unread_messages' => 0,
        'today_orders' => 0
    ];
}

// Get seller info from session
$seller_name = $_SESSION['user_name'] ?? 'Seller';
$seller_business = $_SESSION['business_name'] ?? 'Your Store';
$seller_avatar = $_SESSION['avatar'] ?? null;
$seller_join_date = $_SESSION['join_date'] ?? 'New Seller';
$seller_rating = $_SESSION['seller_rating'] ?? 4.5;
$seller_level = $_SESSION['seller_level'] ?? 'Bronze';
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
                <h5 class="mb-0">Seller Menu</h5>
            </div>
            <button class="btn-close btn-close-white" id="closeSidebar"></button>
        </div>
    </div>
    
    <div class="position-sticky ">
        <!-- Seller Profile Card - Enhanced -->
        <div class="seller-profile-card text-center mb-1 p-1 border-bottom">
            <div class="position-relative d-inline-block">
                <?php if ($seller_avatar): ?>
                    <img src="<?php echo htmlspecialchars($seller_avatar); ?>" 
                         alt="Profile" 
                         class="rounded-circle mb-2" 
                         style="width: 80px; height: 80px; object-fit: cover;">
                <?php else: ?>
                    <div class="mb-2 position-relative">
                        <i class="bi bi-person-circle fs-1 text-primary" style="font-size: 4rem!important;"></i>
                    </div>
                <?php endif; ?>
                
                <!-- Seller Level Badge -->
                <span class="position-absolute bottom-0 end-0 translate-middle-y badge rounded-pill bg-<?php 
                    echo $seller_level == 'Gold' ? 'warning' : ($seller_level == 'Silver' ? 'secondary' : 'bronze'); 
                ?> p-2" style="font-size: 0.7rem;">
                    <i class="bi bi-star-fill"></i>
                </span>
            </div>
            
            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($seller_name); ?></h6>
            <small class="text-muted d-block"><?php echo htmlspecialchars($seller_business); ?></small>
            
            <!-- Rating Stars -->
            <div class="mt-1">
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star<?php echo $i <= $seller_rating ? '-fill text-warning' : ''; ?>"></i>
                <?php endfor; ?>
                <small class="text-muted ms-1">(<?php echo number_format($seller_rating, 1); ?>)</small>
            </div>
            
            <!-- Join Date -->
             <!--
            <small class="text-muted d-block mt-1">
                <i class="bi bi-calendar-check me-1"></i>
                Member since <?php echo $seller_join_date; ?>
            </small>
                -->
        </div>
        
        <!-- Main Navigation -->
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_dashboard; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    <span>Dashboard</span>
                    <?php if($seller_stats['today_orders'] > 0): ?>
                        <span class="badge bg-primary float-end mt-1">
                            <?php echo $seller_stats['today_orders']; ?>
                        </span>
                    <?php endif; ?>
                </a>
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
                               href="<?php echo BASE_URL; ?>/seller/products/manage-products.php">
                                <i class="bi bi-grid me-1"></i>
                                All Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/products/add-product.php">
                                <i class="bi bi-plus-circle me-1"></i>
                                Add New Product
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/products/product-approvals.php">
                                <i class="bi bi-clock-history me-1"></i>
                                Pending Approval
                                <?php if($seller_stats['pending_products'] > 0): ?>
                                    <span class="badge bg-warning float-end">
                                        <?php echo $seller_stats['pending_products']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/products/categories.php">
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
                   data-bs-toggle="collapse" 
                   href="#ordersCollapse"
                   aria-expanded="<?php echo $active_orders ? 'true' : 'false'; ?>">
                    <i class="bi bi-cart me-2"></i>
                    <span>Orders</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_orders ? 'show' : ''; ?>" id="ordersCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/orders/manage-orders.php">
                                <i class="bi bi-list-ul me-1"></i>
                                All Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/orders/pending-orders.php">
                                <i class="bi bi-clock me-1"></i>
                                Pending
                                <?php if($seller_stats['pending_orders'] > 0): ?>
                                    <span class="badge bg-warning float-end">
                                        <?php echo $seller_stats['pending_orders']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/orders/processing-orders.php">
                                <i class="bi bi-gear me-1"></i>
                                Processing
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/orders/completed-orders.php">
                                <i class="bi bi-check-circle me-1"></i>
                                Completed
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Inventory Management -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_inventory; ?>" 
                   data-bs-toggle="collapse" 
                   href="#inventoryCollapse"
                   aria-expanded="<?php echo $active_inventory ? 'true' : 'false'; ?>">
                    <i class="bi bi-clipboard-data me-2"></i>
                    <span>Inventory</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_inventory ? 'show' : ''; ?>" id="inventoryCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/inventory/stock-management.php">
                                <i class="bi bi-boxes me-1"></i>
                                Stock Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/inventory/low-stock-alerts.php">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Low Stock Alerts
                                <?php if($seller_stats['low_stock_count'] > 0): ?>
                                    <span class="badge bg-danger float-end">
                                        <?php echo $seller_stats['low_stock_count']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/inventory/bulk-upload.php">
                                <i class="bi bi-cloud-upload me-1"></i>
                                Bulk Upload
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Finances -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_finances; ?>" 
                   data-bs-toggle="collapse" 
                   href="#financesCollapse"
                   aria-expanded="<?php echo $active_finances ? 'true' : 'false'; ?>">
                    <i class="bi bi-currency-dollar me-2"></i>
                    <span>Finances</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_finances ? 'show' : ''; ?>" id="financesCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/finances/earnings.php">
                                <i class="bi bi-cash-stack me-1"></i>
                                Earnings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/finances/payouts.php">
                                <i class="bi bi-bank me-1"></i>
                                Payouts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/finances/transactions.php">
                                <i class="bi bi-arrow-left-right me-1"></i>
                                Transactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/finances/invoices.php">
                                <i class="bi bi-file-text me-1"></i>
                                Invoices
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Analytics -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_analytics; ?>" 
                   data-bs-toggle="collapse" 
                   href="#analyticsCollapse"
                   aria-expanded="<?php echo $active_analytics ? 'true' : 'false'; ?>">
                    <i class="bi bi-graph-up me-2"></i>
                    <span>Analytics</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_analytics ? 'show' : ''; ?>" id="analyticsCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/analytics/sales-analytics.php">
                                <i class="bi bi-bar-chart me-1"></i>
                                Sales Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/analytics/performance.php">
                                <i class="bi bi-star me-1"></i>
                                Product Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/analytics/customer-insights.php">
                                <i class="bi bi-people me-1"></i>
                                Customer Insights
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/analytics/reports.php">
                                <i class="bi bi-file-earmark-text me-1"></i>
                                Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Store Settings -->
            <li class="nav-item">
                <a class="nav-link <?php echo $active_store; ?>" 
                   data-bs-toggle="collapse" 
                   href="#storeCollapse"
                   aria-expanded="<?php echo $active_store ? 'true' : 'false'; ?>">
                    <i class="bi bi-shop me-2"></i>
                    <span>Store Settings</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_store ? 'show' : ''; ?>" id="storeCollapse">
                    <ul class="nav flex-column sub-menu ms-3">
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/store/profile.php">
                                <i class="bi bi-building me-1"></i>
                                Store Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/store/shipping.php">
                                <i class="bi bi-truck me-1"></i>
                                Shipping Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/store/returns.php">
                                <i class="bi bi-arrow-return-left me-1"></i>
                                Return Policy
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/store/banners.php">
                                <i class="bi bi-images me-1"></i>
                                Store Banners
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        
        <!-- Messages Section -->
        <div class="border-top mt-4 pt-3">
            <a href="<?php echo BASE_URL; ?>/seller/messages.php" 
               class="text-decoration-none d-flex align-items-center justify-content-between px-3">
                <div>
                    <i class="bi bi-chat-dots me-2"></i>
                    <span>Messages</span>
                </div>
                <?php if($seller_stats['unread_messages'] > 0): ?>
                    <span class="badge bg-danger rounded-pill">
                        <?php echo $seller_stats['unread_messages']; ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- Quick Actions -->
        <div class="border-top mt-4 pt-3">
            <h6 class="text-muted mb-3 px-3">Quick Actions</h6>
            <div class="d-grid gap-2 px-3">
                <a href="<?php echo BASE_URL; ?>/seller/products/add-product.php" 
                   class="btn btn-sm btn-success">
                    <i class="bi bi-plus-circle me-1"></i>
                    Add New Product
                </a>
                <a href="<?php echo BASE_URL; ?>/seller/orders/process-order.php" 
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-box-seam me-1"></i>
                    Process Orders
                </a>
                <a href="<?php echo BASE_URL; ?>/seller/inventory/update-stock.php" 
                   class="btn btn-sm btn-warning">
                    <i class="bi bi-pencil-square me-1"></i>
                    Update Stock
                </a>
            </div>
        </div>
        
        <!-- Seller Performance Summary -->
        <div class="border-top mt-4 pt-3 px-3">
            <h6 class="text-muted mb-3">Performance</h6>
            <div class="progress mb-2" style="height: 8px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: 75%"></div>
            </div>
            <small class="text-muted">Seller Score: 75%</small>
            
            <div class="mt-2 d-flex justify-content-between text-center">
                <div>
                    <small class="text-muted d-block">Orders</small>
                    <span class="fw-bold"><?php echo number_format($seller_stats['total_orders'] ?? 0); ?></span>
                </div>
                <div>
                    <small class="text-muted d-block">Revenue</small>
                    <span class="fw-bold">₦<?php echo number_format($seller_stats['total_revenue'] ?? 0, 0); ?></span>
                </div>
                <div>
                    <small class="text-muted d-block">Products</small>
                    <span class="fw-bold"><?php echo number_format($seller_stats['total_products'] ?? 0); ?></span>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Add this CSS to your style.css file -->
<style>
/* Bronze color for seller level */
.bg-bronze {
    background-color: #cd7f32 !important;
}

/* Seller Profile Card */
.seller-profile-card {
    background: linear-gradient(135deg,rgb(187, 252, 246) 0%,rgb(39, 162, 39) 100%);
    margin: -1rem -1rem 1rem -1rem;
    padding: 2rem 1rem !important;
    color: white;
    border-radius: 0;
}

.seller-profile-card .text-muted {
    color: rgba(255,255,255,0.8) !important;
}

.seller-profile-card i {
    color: white;
}

.seller-profile-card .bg-opacity-10 {
    background-color: rgba(255,255,255,0.1) !important;
}

/* Enhanced sidebar styles */
#sidebar {
    height: 100vh;
    position: sticky;
    top: 0;
    overflow-y: auto;
    scrollbar-width: thin;
}

#sidebar::-webkit-scrollbar {
    width: 4px;
}

#sidebar::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#sidebar::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

#sidebar::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Navigation link styles */
#sidebar .nav-link {
    border-radius: 0.375rem;
    margin: 0.125rem 0.5rem;
    padding: 0.75rem 1rem;
    transition: all 0.2s;
    color: #4a5568;
    font-weight: 500;
}

#sidebar .nav-link:hover {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
    transform: translateX(5px);
}

#sidebar .nav-link.active {
    background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
    color: white;
    box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
}

#sidebar .nav-link.active i {
    color: white;
}

#sidebar .sub-menu .nav-link {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    margin-left: 1.5rem;
    background: transparent;
    color: #6c757d;
}

#sidebar .sub-menu .nav-link:hover {
    background-color: rgba(78, 115, 223, 0.05);
    color: #4e73df;
    transform: translateX(3px);
}

#sidebar .sub-menu .nav-link.active {
    background: linear-gradient(90deg, #4e73df20 0%, #224abe10 100%);
    color: #4e73df;
    border-left: 3px solid #4e73df;
    box-shadow: none;
}

/* Chevron rotation */
#sidebar .nav-link[data-bs-toggle="collapse"] i:last-child {
    transition: transform 0.3s;
}

#sidebar .nav-link[data-bs-toggle="collapse"][aria-expanded="true"] i:last-child {
    transform: rotate(180deg);
}

/* Mobile sidebar styles */
@media (max-width: 767.98px) {
    #sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 300px;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1040;
        overflow-y: auto;
        background: white;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
    
    #sidebar.show {
        transform: translateX(0);
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
    
    /* Mobile header adjustments */
    .sidebar-mobile-header {
        margin-top: -1rem;
        margin-bottom: 1rem;
    }
    
    /* Adjust profile card for mobile */
    .seller-profile-card {
        margin-top: 0;
        border-radius: 0;
    }
}

/* Desktop specific styles */
@media (min-width: 768px) {
    #sidebar {
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
    }
    
    .seller-profile-card {
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
}

/* Performance summary styles */
.progress {
    background-color: rgba(78, 115, 223, 0.1);
}

.progress-bar {
    background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
}

/* Hover effects for quick links */
#sidebar a:not(.btn) {
    position: relative;
    overflow: hidden;
}

#sidebar a:not(.btn)::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
    transform: scaleX(0);
    transform-origin: right;
    transition: transform 0.3s;
}

#sidebar a:not(.btn):hover::after {
    transform: scaleX(1);
    transform-origin: left;
}
</style>

<!-- JavaScript for sidebar functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const openBtn = document.getElementById('mobileSidebarToggle');
    const closeBtn = document.getElementById('closeSidebar');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }

    if (openBtn) openBtn.addEventListener('click', toggleSidebar);
    if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);
    if (backdrop) backdrop.addEventListener('click', toggleSidebar);
    
    // Auto-collapse other menus when one is opened
    const collapseLinks = document.querySelectorAll('[data-bs-toggle="collapse"]');
    collapseLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            const targetCollapse = document.querySelector(targetId);
            
            // Close other open collapses
            if (!targetCollapse.classList.contains('show')) {
                document.querySelectorAll('.collapse.show').forEach(collapse => {
                    if (collapse.id !== targetId.substring(1)) {
                        bootstrap.Collapse.getInstance(collapse)?.hide();
                    }
                });
            }
        });
    });
});

// Optional: Add tooltips for collapsed items
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>