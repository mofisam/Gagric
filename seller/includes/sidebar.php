<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_page, 'products/') !== false ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/products/manage-products.php">
                    <i class="bi bi-box-seam me-2"></i>
                    Products
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_page, 'orders/') !== false ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/orders/manage-orders.php">
                    <i class="bi bi-cart me-2"></i>
                    Orders
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_page, 'inventory/') !== false ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/inventory/stock-management.php">
                    <i class="bi bi-clipboard-data me-2"></i>
                    Inventory
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_page, 'finances/') !== false ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/finances/earnings.php">
                    <i class="bi bi-currency-dollar me-2"></i>
                    Finances
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_page, 'analytics/') !== false ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/analytics/sales-analytics.php">
                    <i class="bi bi-graph-up me-2"></i>
                    Analytics
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($current_page, 'store/') !== false ? 'active' : ''; ?>" 
                   href="<?php echo BASE_URL; ?>/seller/store/profile.php">
                    <i class="bi bi-shop me-2"></i>
                    Store Settings
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Quick Links</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/seller/products/add-product.php">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add New Product
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/seller/products/product-approvals.php">
                    <i class="bi bi-clock me-2"></i>
                    Pending Approvals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/seller/inventory/low-stock-alerts.php">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Low Stock Alerts
                </a>
            </li>
        </ul>
    </div>
</nav>