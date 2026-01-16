<?php
ob_start();
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';
requireAdmin();

$db = new Database();

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        setFlashMessage('Test add_category', 'success');
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $sort_order = (int)$_POST['sort_order'];
        if (!empty($name)) {
            $slug = strtolower(str_replace(' ', '-', $name));
            
            // Check if category already exists
            $existing = $db->fetchOne("SELECT id FROM categories WHERE name = ? OR slug = ?", [$name, $slug]);
            if ($existing) {
                setFlashMessage('Category with this name already exists', 'error');
            } else {
                $db->query(
                    "INSERT INTO categories (name, slug, description, parent_id, sort_order) VALUES (?, ?, ?, ?, ?)",
                    [$name, $slug, $description, $parent_id, $sort_order]
                );
                setFlashMessage('Category added successfully', 'success');
            }
        }
    }
    
    if (isset($_POST['update_category'])) {
        setFlashMessage('Test update_category', 'success');
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)$_POST['sort_order'];
        
        if (!empty($name)) {
            $slug = strtolower(str_replace(' ', '-', $name));
            
            // Check if category name conflicts (excluding current category)
            $existing = $db->fetchOne("SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id != ?", [$name, $slug, $category_id]);
            if ($existing) {
                setFlashMessage('Another category with this name already exists', 'error');
            } else {
                $db->query(
                    "UPDATE categories SET name = ?, slug = ?, description = ?, is_active = ?, sort_order = ? WHERE id = ?",
                    [$name, $slug, $description, $is_active, $sort_order, $category_id]
                );
                setFlashMessage('Category updated successfully', 'success');
            }
        }
    }
    
    if (isset($_POST['delete_category'])) {
        setFlashMessage('Test delete_category', 'success');
        $category_id = (int)$_POST['category_id'];
        
        // Check if category has subcategories
        $subcategories = $db->fetchOne("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?", [$category_id])['count'];
        if ($subcategories > 0) {
            setFlashMessage('Cannot delete category that has subcategories', 'error');
        } else {
            // Check if category has products
            $product_count = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE category_id = ?", [$category_id])['count'];
            if ($product_count > 0) {
                setFlashMessage('Cannot delete category that has products. Please reassign products first.', 'error');
            } else {
                $db->query("DELETE FROM categories WHERE id = ?", [$category_id]);
                setFlashMessage('Category deleted successfully', 'success');
            }
        }
    }
    
    header('Location: categories.php');
    exit;
}

// Get all categories with hierarchy
$categories = $db->fetchAll("
    SELECT c.*, 
           p.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count,
           (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategory_count
    FROM categories c 
    LEFT JOIN categories p ON c.parent_id = p.id 
    ORDER BY c.parent_id IS NULL DESC, c.sort_order, c.name
");

// Get parent categories for dropdowns
$parent_categories = $db->fetchAll("
    SELECT id, name 
    FROM categories 
    WHERE parent_id IS NULL AND is_active = TRUE 
    ORDER BY sort_order, name
");

// Build category tree for display
function buildCategoryTree($categories, $parent_id = null, $level = 0) {
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parent_id) {
            $category['level'] = $level;
            $tree[] = $category;
            // Add subcategories
            $subcategories = buildCategoryTree($categories, $category['id'], $level + 1);
            $tree = array_merge($tree, $subcategories);
        }
    }
    return $tree;
}

$category_tree = buildCategoryTree($categories);

// Stats
$stats = [
    'total_categories' => count($categories),
    'main_categories' => count(array_filter($categories, function($cat) { return $cat['parent_id'] === null; })),
    'active_categories' => count(array_filter($categories, function($cat) { return $cat['is_active']; })),
    'total_products' => array_sum(array_column($categories, 'product_count')),
    'categories_with_products' => count(array_filter($categories, function($cat) { return $cat['product_count'] > 0; })),
    'empty_categories' => count(array_filter($categories, function($cat) { 
        return $cat['product_count'] == 0 && $cat['subcategory_count'] == 0; 
    }))
];

$page_title = "Manage Categories";
ob_end_flush();
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
                        <h1 class="h5 mb-0 text-center">Categories</h1>
                        <small class="text-muted d-block text-center"><?php echo $stats['total_categories']; ?> categories</small>
                    </div>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Manage Categories</h1>
                    <p class="text-muted mb-0">Organize products with categories and subcategories</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-circle me-1"></i> Add Category
                    </button>
                </div>
            </div>

            <!-- Mobile Quick Stats -->
            <div class="d-md-none mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Total</small>
                                <h6 class="mb-0"><?php echo number_format($stats['total_categories']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Active</small>
                                <h6 class="mb-0 text-success"><?php echo number_format($stats['active_categories']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Products</small>
                                <h6 class="mb-0 text-info"><?php echo number_format($stats['total_products']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-2 text-center">
                                <small class="text-muted d-block">Main</small>
                                <h6 class="mb-0 text-primary"><?php echo number_format($stats['main_categories']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop Stats Cards -->
            <div class="d-none d-md-flex row g-3 mb-4">
                <!-- Total Categories -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Categories</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_categories']); ?></h3>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-tags fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Categories -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Main Categories</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['main_categories']); ?></h3>
                                    <small class="text-success">
                                        <?php echo number_format($stats['total_categories'] - $stats['main_categories']); ?> subcategories
                                    </small>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-folder fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Categories -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Active Categories</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['active_categories']); ?></h3>
                                    <small class="text-info">
                                        <?php echo round(($stats['active_categories'] / max(1, $stats['total_categories'])) * 100); ?>% active
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-check-circle fs-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Products -->
                <div class="col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Total Products</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_products']); ?></h3>
                                    <small class="text-warning">
                                        <?php echo $stats['categories_with_products']; ?> categories used
                                    </small>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-box-seam fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0 d-none d-md-block">Categories (<?php echo $stats['total_categories']; ?>)</h5>
                    <small class="text-muted">
                        <?php echo $stats['main_categories']; ?> main, <?php echo $stats['total_categories'] - $stats['main_categories']; ?> subcategories
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary d-md-none" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus"></i> Add
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="refreshCategories">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <?php if (empty($category_tree)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-tags text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">No categories found</h4>
                            <p class="text-muted mb-4">Start by adding your first category</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-plus-circle me-1"></i> Add Category
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Responsive Table for all devices -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 mobile-optimized-table">
                                <thead class="table-light d-none d-md-table-header-group">
                                    <tr>
                                        <th width="50">ID</th>
                                        <th>Category Name</th>
                                        <th width="120">Parent</th>
                                        <th width="80">Products</th>
                                        <th width="80">Sub</th>
                                        <th width="80">Sort</th>
                                        <th width="80">Status</th>
                                        <th width="120" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_tree as $category): ?>
                                        <?php 
                                        $status_color = $category['is_active'] ? 'success' : 'secondary';
                                        $has_image = $category['image_path'] ? true : false;
                                        $indent_px = $category['level'] * 20;
                                        ?>
                                        
                                        <tr class="category-row" data-category-id="<?php echo $category['id']; ?>">
                                            <!-- Desktop View -->
                                            <td class="d-none d-md-table-cell">
                                                <span class="text-muted">#<?php echo $category['id']; ?></span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <div style="padding-left: <?php echo $indent_px; ?>px;">
                                                    <?php if ($category['level'] > 0): ?>
                                                        <i class="bi bi-arrow-return-right text-muted me-1"></i>
                                                    <?php endif; ?>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                    <?php if ($has_image): ?>
                                                        <i class="bi bi-image text-info ms-1" title="Has image"></i>
                                                    <?php endif; ?>
                                                    <?php if ($category['description']): ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($category['description'], 0, 50)); ?>...</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php if ($category['parent_name']): ?>
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($category['parent_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Main</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-info"><?php echo $category['product_count']; ?></span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <?php if ($category['subcategory_count'] > 0): ?>
                                                    <span class="badge bg-secondary"><?php echo $category['subcategory_count']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-light text-dark"><?php echo $category['sort_order']; ?></span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editCategoryModal"
                                                            data-category-id="<?php echo $category['id']; ?>"
                                                            data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                            data-category-description="<?php echo htmlspecialchars($category['description']); ?>"
                                                            data-category-parent="<?php echo $category['parent_id']; ?>"
                                                            data-category-sort="<?php echo $category['sort_order']; ?>"
                                                            data-category-active="<?php echo $category['is_active']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    
                                                    <?php if ($category['subcategory_count'] == 0 && $category['product_count'] == 0): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                        <button name="delete_category" 
                                                                class="btn btn-outline-danger"
                                                                onclick="return confirm('Are you sure you want to delete this category?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary" disabled
                                                            title="Cannot delete category with subcategories or products">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Mobile View - Stacked Layout -->
                                            <td class="d-md-none">
                                                <div class="mobile-table-row">
                                                    <!-- Row 1: Category Name & Status -->
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-<?php echo $status_color; ?> bg-opacity-10 p-2 rounded-circle me-2">
                                                                <i class="bi bi-tag text-<?php echo $status_color; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <div style="padding-left: <?php echo $indent_px; ?>px;">
                                                                    <?php if ($category['level'] > 0): ?>
                                                                        <i class="bi bi-arrow-return-right text-muted me-1"></i>
                                                                    <?php endif; ?>
                                                                    <strong><?php echo htmlspecialchars(substr($category['name'], 0, 20)); ?></strong>
                                                                    <?php if ($has_image): ?>
                                                                        <i class="bi bi-image text-info ms-1"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                                    <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-info"><?php echo $category['product_count']; ?> products</span>
                                                            <?php if ($category['subcategory_count'] > 0): ?>
                                                                <br>
                                                                <span class="badge bg-secondary"><?php echo $category['subcategory_count']; ?> sub</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 2: Parent & Sort -->
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <?php if ($category['parent_name']): ?>
                                                                    <small class="text-muted d-block">Parent:</small>
                                                                    <small><?php echo htmlspecialchars(substr($category['parent_name'], 0, 15)); ?></small>
                                                                <?php else: ?>
                                                                    <span class="badge bg-primary">Main Category</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-end">
                                                                <small class="text-muted d-block">Sort:</small>
                                                                <small><?php echo $category['sort_order']; ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Actions -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <?php if ($category['description']): ?>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars(substr($category['description'], 0, 30)); ?>...
                                                            </small>
                                                        <?php else: ?>
                                                            <small class="text-muted">No description</small>
                                                        <?php endif; ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editCategoryModal"
                                                                    data-category-id="<?php echo $category['id']; ?>"
                                                                    data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                                    data-category-description="<?php echo htmlspecialchars($category['description']); ?>"
                                                                    data-category-parent="<?php echo $category['parent_id']; ?>"
                                                                    data-category-sort="<?php echo $category['sort_order']; ?>"
                                                                    data-category-active="<?php echo $category['is_active']; ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            
                                                            <?php if ($category['subcategory_count'] == 0 && $category['product_count'] == 0): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                <button name="delete_category" 
                                                                        class="btn btn-sm btn-outline-danger"
                                                                        onclick="return confirm('Delete this category?')">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                            <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Category Insights -->
            <div class="row mt-4">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart me-2 text-primary"></i>
                                Most Used Categories
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $top_categories = array_slice(
                                array_filter($categories, function($cat) { return $cat['product_count'] > 0; }),
                                0, 5
                            );
                            usort($top_categories, function($a, $b) {
                                return $b['product_count'] - $a['product_count'];
                            });
                            ?>
                            
                            <?php if (empty($top_categories)): ?>
                                <p class="text-muted mb-0">No category usage data</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($top_categories as $category): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-2">
                                                <i class="bi bi-tag text-primary"></i>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars(substr($category['name'], 0, 20)); ?></strong>
                                                <?php if ($category['parent_name']): ?>
                                                    <small class="text-muted">in <?php echo htmlspecialchars(substr($category['parent_name'], 0, 15)); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?php echo $category['product_count']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle me-2 text-warning"></i>
                                Empty Categories
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $empty_categories = array_filter($categories, function($cat) { 
                                return $cat['product_count'] == 0 && $cat['subcategory_count'] == 0; 
                            });
                            ?>
                            
                            <?php if (empty($empty_categories)): ?>
                                <p class="text-muted mb-0">No empty categories</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($empty_categories, 0, 5) as $category): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-warning bg-opacity-10 p-2 rounded-circle me-2">
                                                <i class="bi bi-tag text-warning"></i>
                                            </div>
                                            <div>
                                                <span class="d-block"><?php echo htmlspecialchars(substr($category['name'], 0, 20)); ?></span>
                                                <?php if ($category['parent_name']): ?>
                                                    <small class="text-muted">in <?php echo htmlspecialchars(substr($category['parent_name'], 0, 15)); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-warning rounded-pill">Empty</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($empty_categories) > 5): ?>
                                    <div class="mt-2 text-center">
                                        <small class="text-muted">
                                            and <?php echo count($empty_categories) - 5; ?> more empty categories
                                        </small>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add New Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="add_name" name="name" required 
                               placeholder="Enter category name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_description" name="description" rows="3" 
                                  placeholder="Optional description"></textarea>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_parent_id" class="form-label">Parent Category</label>
                                <select class="form-select" id="add_parent_id" name="parent_id">
                                    <option value="">No Parent (Main Category)</option>
                                    <?php foreach ($parent_categories as $parent): ?>
                                        <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="add_sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="add_sort_order" name="sort_order" value="0" min="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button name="add_category" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>
                    Edit Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_parent_id" class="form-label">Parent Category</label>
                                <select class="form-select" id="edit_parent_id" name="parent_id">
                                    <option value="">No Parent (Main Category)</option>
                                    <?php foreach ($parent_categories as $parent): ?>
                                        <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="edit_sort_order" name="sort_order" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1" role="switch">
                        <label class="form-check-label" for="edit_is_active">Active Category</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button name="update_category" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    
    // Refresh categories
    const refreshBtn = document.getElementById('refreshCategories');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            this.disabled = true;
            setTimeout(() => {
                window.location.reload();
            }, 500);
        });
    }
    
    // Make table rows clickable on mobile to show edit modal
    document.querySelectorAll('.mobile-table-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('a')) {
                const categoryId = this.closest('.category-row').dataset.categoryId;
                const button = document.querySelector(`[data-category-id="${categoryId}"]`);
                if (button) {
                    button.click();
                }
            }
        });
    });
});

// Populate edit modal with category data
document.addEventListener('DOMContentLoaded', function() {
    var editModal = document.getElementById('editCategoryModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        
        document.getElementById('edit_category_id').value = button.getAttribute('data-category-id');
        document.getElementById('edit_name').value = button.getAttribute('data-category-name');
        document.getElementById('edit_description').value = button.getAttribute('data-category-description');
        document.getElementById('edit_parent_id').value = button.getAttribute('data-category-parent');
        document.getElementById('edit_sort_order').value = button.getAttribute('data-category-sort');
        
        var isActive = button.getAttribute('data-category-active') === '1';
        document.getElementById('edit_is_active').checked = isActive;
        
        // Disable current category from parent selection
        var categoryId = button.getAttribute('data-category-id');
        var parentSelect = document.getElementById('edit_parent_id');
        
        for (var i = 0; i < parentSelect.options.length; i++) {
            if (parentSelect.options[i].value === categoryId) {
                parentSelect.options[i].disabled = true;
                parentSelect.options[i].textContent += ' (current)';
            }
        }
    });
    
    // Reset parent select when modal is hidden
    editModal.addEventListener('hidden.bs.modal', function () {
        var parentSelect = document.getElementById('edit_parent_id');
        for (var i = 0; i < parentSelect.options.length; i++) {
            parentSelect.options[i].disabled = false;
            parentSelect.options[i].textContent = parentSelect.options[i].textContent.replace(' (current)', '');
        }
    });
});

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
        
        /* Indentation for hierarchy */
        [style*="padding-left:"] {
            max-width: calc(100% - 20px);
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    
    /* Desktop hover effects */
    @media (min-width: 768px) {
        .category-row:hover {
            background-color: rgba(0,0,0,0.02);
        }
        
        .category-row:hover .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        }
    }
    
    /* Custom styles for hierarchy */
    .bi-arrow-return-right
        opacity: 0.6;
    }
    
    /* Form switch styling */
    .form-switch .form-check-input {
        height: 1.5em;
        width: 3em;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>