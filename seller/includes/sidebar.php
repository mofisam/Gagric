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

// Get seller stats for badges (these should be passed from the including page)
if (!isset($seller_stats)) {
    $seller_stats = [
        'pending_products' => 0,
        'low_stock_count' => 0,
        'pending_orders' => 0,
        'today_orders' => 0,
        'total_orders' => 0,
        'total_revenue' => 0,
        'total_products' => 0
    ];
}

// Get seller info from session
$seller_name = $_SESSION['user_name'] ?? 'Seller';
$seller_business = $_SESSION['business_name'] ?? 'Your Store';
$seller_avatar = $_SESSION['avatar'] ?? null;
$seller_rating = $_SESSION['seller_rating'] ?? 0;
$seller_level = $_SESSION['seller_level'] ?? 'Bronze';
$seller_join_date = $_SESSION['join_date'] ?? date('Y-m-d');
?>

<!-- Mobile backdrop for sidebar -->
<div class="sidebar-backdrop"></div>

<!-- Sidebar -->
<aside id="sidebar" class="col-lg-2 col-md-3 d-md-block bg-white sidebar shadow-sm border-end">
    <!-- Mobile sidebar header -->
    <div class="sidebar-mobile-header d-md-none p-3 border-bottom">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.jpeg" 
                     alt="Green Agric Logo" 
                     style="height:32px; width:auto; margin-right:10px;">
                <h5 class="mb-0 text-white">Seller Menu</h5>
            </div>
            <button class="btn-close btn-close-white" id="closeSidebar"></button>
        </div>
    </div>
    
    <div class="position-sticky pt-3">
        <!-- Enhanced Seller Profile Card -->
        <div class="seller-profile-card text-center mb-4 p-3">
            <div class="position-relative d-inline-block">
                <?php if ($seller_avatar): ?>
                    <img src="<?php echo htmlspecialchars($seller_avatar); ?>" 
                         alt="Profile" 
                         class="rounded-circle mb-2 profile-avatar" 
                         style="width: 80px; height: 80px; object-fit: cover; border: 3px solid white;">
                <?php else: ?>
                    <div class="mb-2 position-relative">
                        <div class="profile-icon-wrapper">
                            <i class="bi bi-person-circle profile-icon"></i>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Seller Level Badge -->
                <span class="position-absolute bottom-0 end-0 translate-middle-y badge rounded-pill seller-level-badge">
                    <i class="bi bi-star-fill"></i> <?php echo $seller_level; ?>
                </span>
            </div>
            
            <h6 class="mb-0 fw-bold seller-name"><?php echo htmlspecialchars($seller_name); ?></h6>
            <small class="seller-business"><?php echo htmlspecialchars($seller_business); ?></small>
            
            <!-- Rating Stars -->
            <?php if ($seller_rating > 0): ?>
            <div class="mt-2">
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star<?php echo $i <= $seller_rating ? '-fill text-warning' : ''; ?> rating-star"></i>
                <?php endfor; ?>
                <small class="rating-count">(<?php echo number_format($seller_rating, 1); ?>)</small>
            </div>
            <?php endif; ?>
            
            <!-- Join Date -->
            <small class="join-date d-block mt-2">
                <i class="bi bi-calendar-check me-1"></i>
                Member since <?php echo date('M Y', strtotime($seller_join_date)); ?>
            </small>
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
                        <span class="badge bg-primary float-end today-badge">
                            +<?php echo $seller_stats['today_orders']; ?>
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
                                    <span class="badge bg-warning float-end pending-badge">
                                        <?php echo $seller_stats['pending_products']; ?>
                                    </span>
                                <?php endif; ?>
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
                                    <span class="badge bg-warning float-end pending-badge">
                                        <?php echo $seller_stats['pending_orders']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" 
                               href="<?php echo BASE_URL; ?>/seller/orders/shipping.php">
                                <i class="bi bi-truck me-1"></i>
                                Shipping
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
                                    <span class="badge bg-danger float-end alert-badge">
                                        <?php echo $seller_stats['low_stock_count']; ?>
                                    </span>
                                <?php endif; ?>
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
                                Performance
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
                               href="<?php echo BASE_URL; ?>/seller/store/bank-details.php">
                                <i class="bi bi-bank me-1"></i>
                                Bank Details
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
        
        <!-- Enhanced Quick Actions Section -->
        <div class="border-top mt-4 pt-3 quick-actions-section">
            <h6 class="text-muted mb-3 px-3">
                <i class="bi bi-lightning-charge me-1"></i> Quick Actions
            </h6>
            <div class="d-grid gap-2 px-3">
                <a href="<?php echo BASE_URL; ?>/seller/products/add-product.php" 
                   class="btn btn-success quick-action-btn">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add New Product
                </a>
                <a href="<?php echo BASE_URL; ?>/seller/orders/shipping.php" 
                   class="btn btn-primary quick-action-btn">
                    <i class="bi bi-truck me-2"></i>
                    Process Shipments
                </a>
                <a href="<?php echo BASE_URL; ?>/seller/inventory/stock-management.php" 
                   class="btn btn-warning quick-action-btn">
                    <i class="bi bi-pencil-square me-2"></i>
                    Update Stock
                </a>
            </div>
        </div>
        
        <!-- Seller Performance Summary -->
        <?php if (isset($seller_stats['total_orders']) || isset($seller_stats['total_revenue'])): ?>
        <div class="border-top mt-4 pt-3 performance-summary px-3">
            <h6 class="text-muted mb-3">
                <i class="bi bi-graph-up me-1"></i> Performance
            </h6>
            <div class="progress mb-2" style="height: 6px;">
                <div class="progress-bar performance-progress" role="progressbar" style="width: 75%"></div>
            </div>
            <div class="row g-2 text-center">
                <?php if (isset($seller_stats['total_orders'])): ?>
                <div class="col-4">
                    <div class="stat-item">
                        <small class="text-muted d-block">Orders</small>
                        <span class="fw-bold stat-number"><?php echo number_format($seller_stats['total_orders']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($seller_stats['total_revenue'])): ?>
                <div class="col-4">
                    <div class="stat-item">
                        <small class="text-muted d-block">Revenue</small>
                        <span class="fw-bold stat-number">₦<?php echo number_format($seller_stats['total_revenue'], 0); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (isset($seller_stats['total_products'])): ?>
                <div class="col-4">
                    <div class="stat-item">
                        <small class="text-muted d-block">Products</small>
                        <span class="fw-bold stat-number"><?php echo number_format($seller_stats['total_products']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</aside>

<!-- Enhanced CSS Styles -->
<style>
/* Mobile sidebar styles - MATCHING ADMIN PANEL */
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
        background: white;
    }
    
    #sidebar.show {
        transform: translateX(0);
        display: block;
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

/* Enhanced Seller Profile Card */
.seller-profile-card {
    background: linear-gradient(135deg, #2c7da0 0%, #1f5068 100%);
    margin: -1rem -1rem 1rem -1rem;
    padding: 1.5rem 1rem !important;
    color: white;
    border-radius: 0;
}

.seller-profile-card .profile-avatar {
    border: 3px solid rgba(255,255,255,0.3);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    transition: transform 0.3s;
}

.seller-profile-card .profile-avatar:hover {
    transform: scale(1.05);
}

.seller-profile-card .profile-icon-wrapper {
    display: inline-block;
    position: relative;
}

.seller-profile-card .profile-icon {
    font-size: 4rem;
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2));
}

.seller-profile-card .seller-name {
    color: white;
    margin-top: 0.5rem;
}

.seller-profile-card .seller-business {
    color: rgba(255,255,255,0.9);
}

.seller-profile-card .rating-star {
    font-size: 0.8rem;
    margin: 0 1px;
}

.seller-profile-card .rating-count {
    color: rgba(255,255,255,0.8);
}

.seller-profile-card .join-date {
    color: rgba(255,255,255,0.7);
    font-size: 0.7rem;
}

.seller-level-badge {
    background: linear-gradient(135deg, #f6b93b 0%, #e58e26 100%);
    border: 2px solid rgba(255,255,255,0.5);
    padding: 0.3rem 0.6rem;
    font-size: 0.7rem;
}

/* Navigation link styles */
#sidebar .nav-link {
    border-radius: 0.5rem;
    margin: 0.125rem 0.5rem;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    color: #4a5568;
    font-weight: 500;
    position: relative;
}

#sidebar .nav-link:hover {
    background-color: rgba(44, 125, 160, 0.08);
    color: #2c7da0;
    transform: translateX(5px);
}

#sidebar .nav-link.active {
    background: linear-gradient(90deg, rgba(44, 125, 160, 0.12) 0%, rgba(31, 80, 104, 0.08) 100%);
    color: #2c7da0;
    border-left: 3px solid #2c7da0;
}

#sidebar .nav-link.active i {
    color: #2c7da0;
}

/* Sub-menu styles */
#sidebar .sub-menu {
    margin-left: 1rem;
    position: relative;
}

#sidebar .sub-menu::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, #2c7da0 0%, rgba(44,125,160,0.2) 100%);
}

#sidebar .sub-menu .nav-link {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    margin-left: 0.5rem;
    background: transparent;
    color: #6c757d;
    position: relative;
}

#sidebar .sub-menu .nav-link::before {
    content: '';
    position: absolute;
    left: -0.75rem;
    top: 50%;
    width: 0.5rem;
    height: 2px;
    background: linear-gradient(90deg, #2c7da0 0%, rgba(44,125,160,0.4) 100%);
    transform: translateY(-50%);
}

#sidebar .sub-menu .nav-link:hover {
    background-color: rgba(44, 125, 160, 0.05);
    color: #2c7da0;
    transform: translateX(3px);
}

#sidebar .sub-menu .nav-link.active {
    background: rgba(44, 125, 160, 0.08);
    color: #2c7da0;
    border-left: 3px solid #2c7da0;
}

/* Badge styles */
#sidebar .badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
    font-weight: 500;
}

.today-badge {
    background: linear-gradient(135deg, #2c7da0 0%, #1f5068 100%);
    border: none;
}

.pending-badge {
    background: #f6b93b;
    color: #1e2a3a;
}

.alert-badge {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
}

/* Quick Actions Section */
.quick-actions-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    margin: 0.5rem 0;
    padding: 0.5rem 0 !important;
}

.quick-action-btn {
    transition: all 0.3s ease;
    border: none;
    padding: 0.6rem;
    font-weight: 500;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-success.quick-action-btn {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
}

.btn-primary.quick-action-btn {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
}

.btn-warning.quick-action-btn {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
}

/* Performance Summary */
.performance-summary {
    margin-bottom: 1rem;
}

.performance-progress {
    background: linear-gradient(90deg, #2c7da0 0%, #1f5068 100%);
    border-radius: 10px;
}

.stat-item {
    background: rgba(44, 125, 160, 0.05);
    border-radius: 8px;
    padding: 0.5rem;
    transition: all 0.3s ease;
}

.stat-item:hover {
    background: rgba(44, 125, 160, 0.1);
    transform: translateY(-2px);
}

.stat-number {
    color: #2c7da0;
    font-size: 0.9rem;
}

/* Chevron rotation animation */
#sidebar .nav-link[data-bs-toggle="collapse"] i:last-child {
    transition: transform 0.3s ease;
}

#sidebar .nav-link[data-bs-toggle="collapse"][aria-expanded="true"] i:last-child {
    transform: rotate(180deg);
}

/* Hover underline effect */
#sidebar .nav-link:not(.btn) {
    position: relative;
    overflow: hidden;
}

#sidebar .nav-link:not(.btn)::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, #2c7da0 0%, #1f5068 100%);
    transform: scaleX(0);
    transform-origin: right;
    transition: transform 0.3s ease;
}

#sidebar .nav-link:not(.btn):hover::after {
    transform: scaleX(1);
    transform-origin: left;
}

/* Custom scrollbar */
#sidebar::-webkit-scrollbar {
    width: 4px;
}

#sidebar::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#sidebar::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #2c7da0 0%, #1f5068 100%);
    border-radius: 4px;
}

#sidebar::-webkit-scrollbar-thumb:hover {
    background: #2c7da0;
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
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            toggleSidebar();
        }
    });
    
    // Auto-collapse other menus when one is opened
    const collapseLinks = document.querySelectorAll('[data-bs-toggle="collapse"]');
    collapseLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId && targetId !== '#') {
                const targetCollapse = document.querySelector(targetId);
                
                // Close other open collapses
                if (!targetCollapse.classList.contains('show')) {
                    document.querySelectorAll('.collapse.show').forEach(collapse => {
                        if (collapse.id !== targetId.substring(1)) {
                            bootstrap.Collapse.getInstance(collapse)?.hide();
                        }
                    });
                }
            }
        });
    });
    
    // Add animation to badges
    const badges = document.querySelectorAll('#sidebar .badge');
    badges.forEach(badge => {
        if (parseInt(badge.textContent) > 0) {
            badge.style.animation = 'pulse 0.5s ease';
        }
    });
});

// Add pulse animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(style);
</script>