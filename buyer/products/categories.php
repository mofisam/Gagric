<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();

// Get all categories
$categories = $db->fetchAll("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'approved'
    WHERE c.is_active = TRUE 
    GROUP BY c.id 
    ORDER BY c.sort_order, c.name
");

// Get featured categories (top-level)
$featured_categories = array_filter($categories, function($cat) {
    return $cat['parent_id'] === null;
});
?>
<?php 
$page_title = "Product Categories";
$page_css = 'products.css';
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <h2 class="mb-4">Browse by Category</h2>
    <p class="text-muted mb-4">Find fresh agricultural products by category</p>
    
    <!-- Featured Categories -->
    <div class="row mb-5">
        <?php foreach ($featured_categories as $category): ?>
            <div class="col-md-4 mb-4">
                <div class="card category-card border-success h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-basket text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h4 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h4>
                        <p class="text-muted"><?php echo $category['product_count']; ?> products</p>
                        <p class="card-text"><?php echo htmlspecialchars($category['description'] ?? 'Fresh produce'); ?></p>
                        <a href="browse.php?category=<?php echo $category['id']; ?>" class="btn btn-outline-success">
                            Browse Products
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- All Categories -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">All Categories</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($categories as $category): 
                    $parent_name = '';
                    if ($category['parent_id']) {
                        foreach ($categories as $parent) {
                            if ($parent['id'] == $category['parent_id']) {
                                $parent_name = $parent['name'];
                                break;
                            }
                        }
                    }
                ?>
                    <div class="col-md-3 mb-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <a href="browse.php?category=<?php echo $category['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </a>
                                </h6>
                                <?php if ($parent_name): ?>
                                    <small class="text-muted">Under: <?php echo htmlspecialchars($parent_name); ?></small>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <span class="badge bg-success"><?php echo $category['product_count']; ?> products</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.category-card {
    transition: transform 0.3s;
}
.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
</style>

<?php include '../../includes/footer.php'; ?>