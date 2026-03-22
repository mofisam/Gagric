<?php
// seller/products/duplicate-product.php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get the product to duplicate
$original_product = $db->fetchOne("
    SELECT * FROM products 
    WHERE id = ? AND seller_id = ?
", [$product_id, $seller_id]);

if (!$original_product) {
    setFlashMessage('Product not found or access denied', 'danger');
    header('Location: manage-products.php');
    exit;
}

// Get product images
$product_images = $db->fetchAll("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, sort_order ASC
", [$product_id]);

// Get product agricultural details
$agricultural_details = $db->fetchOne("
    SELECT * FROM product_agricultural_details 
    WHERE product_id = ?
", [$product_id]);

// Get product specifications
$specifications = $db->fetchAll("
    SELECT attribute_id, attribute_value 
    FROM product_specifications 
    WHERE product_id = ?
", [$product_id]);

// Get bulk pricing
$bulk_pricing = $db->fetchAll("
    SELECT * FROM product_bulk_pricing 
    WHERE product_id = ?
", [$product_id]);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitizeInput($_POST['name']);
    $slug = createSlug($name);
    $description = sanitizeInput($_POST['description']);
    $short_description = sanitizeInput($_POST['short_description']);
    $category_id = (int)$_POST['category_id'];
    $product_type = $_POST['product_type'];
    $variety = sanitizeInput($_POST['variety']);
    $price_per_unit = (float)$_POST['price_per_unit'];
    $unit = $_POST['unit'];
    $unit_quantity = (float)$_POST['unit_quantity'];
    $min_order_quantity = (float)$_POST['min_order_quantity'];
    $max_order_quantity = !empty($_POST['max_order_quantity']) ? (float)$_POST['max_order_quantity'] : null;
    $stock_quantity = (float)$_POST['stock_quantity'];
    $low_stock_alert_level = (float)$_POST['low_stock_alert_level'];
    $weight_kg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null;
    $dimensions = sanitizeInput($_POST['dimensions']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Agricultural details
    $grade = $_POST['grade'] ?? null;
    $is_organic = isset($_POST['is_organic']) ? 1 : 0;
    $is_gmo = isset($_POST['is_gmo']) ? 1 : 0;
    $organic_certification_number = sanitizeInput($_POST['organic_certification_number']);
    $harvest_date = !empty($_POST['harvest_date']) ? $_POST['harvest_date'] : null;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $shelf_life_days = !empty($_POST['shelf_life_days']) ? (int)$_POST['shelf_life_days'] : null;
    $farming_method = $_POST['farming_method'] ?? null;
    $irrigation_type = sanitizeInput($_POST['irrigation_type']);
    $storage_temperature = sanitizeInput($_POST['storage_temperature']);
    $storage_humidity = sanitizeInput($_POST['storage_humidity']);
    
    // Validation
    if (empty($name) || empty($description) || $category_id == 0 || $price_per_unit <= 0) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            // Check if slug already exists
            $existing = $db->fetchOne("SELECT id FROM products WHERE slug = ? AND seller_id = ?", [$slug, $seller_id]);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
            
            // Insert duplicated product with draft status
            $product_data = [
                'seller_id' => $seller_id,
                'category_id' => $category_id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'short_description' => $short_description,
                'product_type' => $product_type,
                'variety' => $variety,
                'price_per_unit' => $price_per_unit,
                'unit' => $unit,
                'unit_quantity' => $unit_quantity,
                'min_order_quantity' => $min_order_quantity,
                'max_order_quantity' => $max_order_quantity,
                'stock_quantity' => $stock_quantity,
                'low_stock_alert_level' => $low_stock_alert_level,
                'weight_kg' => $weight_kg,
                'dimensions' => $dimensions,
                'status' => 'draft', // Set as draft to require approval
                'is_featured' => $is_featured,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $new_product_id = $db->insert('products', $product_data);
            
            if ($new_product_id) {
                // Copy agricultural details
                if ($agricultural_details) {
                    $agri_data = [
                        'product_id' => $new_product_id,
                        'grade' => $grade ?? $agricultural_details['grade'],
                        'is_organic' => $is_organic ?? $agricultural_details['is_organic'],
                        'is_gmo' => $is_gmo ?? $agricultural_details['is_gmo'],
                        'organic_certification_number' => $organic_certification_number ?? $agricultural_details['organic_certification_number'],
                        'harvest_date' => $harvest_date ?? $agricultural_details['harvest_date'],
                        'expiry_date' => $expiry_date ?? $agricultural_details['expiry_date'],
                        'shelf_life_days' => $shelf_life_days ?? $agricultural_details['shelf_life_days'],
                        'farming_method' => $farming_method ?? $agricultural_details['farming_method'],
                        'irrigation_type' => $irrigation_type ?? $agricultural_details['irrigation_type'],
                        'storage_temperature' => $storage_temperature ?? $agricultural_details['storage_temperature'],
                        'storage_humidity' => $storage_humidity ?? $agricultural_details['storage_humidity']
                    ];
                    $db->insert('product_agricultural_details', $agri_data);
                }
                
                // Copy specifications
                foreach ($specifications as $spec) {
                    $spec_data = [
                        'product_id' => $new_product_id,
                        'attribute_id' => $spec['attribute_id'],
                        'attribute_value' => $spec['attribute_value']
                    ];
                    $db->insert('product_specifications', $spec_data);
                }
                
                // Copy bulk pricing
                foreach ($bulk_pricing as $price) {
                    $price_data = [
                        'product_id' => $new_product_id,
                        'min_quantity' => $price['min_quantity'],
                        'max_quantity' => $price['max_quantity'],
                        'price_per_unit' => $price['price_per_unit'],
                        'discount_percentage' => $price['discount_percentage']
                    ];
                    $db->insert('product_bulk_pricing', $price_data);
                }
                
                // Copy product images (physical files need to be copied)
                $image_copied = false;
                foreach ($product_images as $image) {
                    $source_path = '../../uploads/products/' . $image['image_path'];
                    $extension = pathinfo($image['image_path'], PATHINFO_EXTENSION);
                    $new_filename = 'product_' . $new_product_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                    $dest_path = '../../uploads/products/' . $new_filename;
                    
                    if (file_exists($source_path)) {
                        if (copy($source_path, $dest_path)) {
                            $image_data = [
                                'product_id' => $new_product_id,
                                'image_path' => $new_filename,
                                'alt_text' => $image['alt_text'],
                                'is_primary' => $image['is_primary'],
                                'sort_order' => $image['sort_order']
                            ];
                            $db->insert('product_images', $image_data);
                            $image_copied = true;
                        }
                    }
                }
                
                $success = 'Product duplicated successfully! It has been saved as a draft and will be reviewed by admin.';
                
                // Redirect to edit page after short delay
                header('Refresh: 2; URL=edit-product.php?id=' . $new_product_id);
            } else {
                $error = 'Failed to duplicate product. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get categories for dropdown
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get seller stats for sidebar
$seller_stats = [
    'pending_products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'low_stock_count' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE seller_id = ? AND status = 'approved' AND stock_quantity <= low_stock_alert_level AND stock_quantity > 0", [$seller_id])['count'],
    'pending_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items WHERE seller_id = ? AND status = 'pending'", [$seller_id])['count'],
    'today_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.seller_id = ? AND o.created_at >= CURDATE() AND o.created_at < CURDATE() + INTERVAL 1 DAY", [$seller_id])['count'],
];

// Set seller info for sidebar
$_SESSION['business_name'] = $seller_profile['business_name'] ?? 'Your Store';
$_SESSION['seller_rating'] = $seller_profile['avg_rating'] ?? 0;

$page_title = "Duplicate Product: " . $original_product['name'];
$page_css = 'dashboard.css';

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <!-- Mobile header -->
            <div class="d-md-none mobile-page-header py-3 border-bottom mb-3 bg-white sticky-top">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-primary me-3" id="mobileSidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h5 mb-0">Duplicate Product</h1>
                        <small class="text-muted"><?php echo htmlspecialchars($original_product['name']); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Duplicate Product</h1>
                    <p class="text-muted mb-0">
                        <i class="bi bi-files me-1"></i> Duplicating: 
                        <strong><?php echo htmlspecialchars($original_product['name']); ?></strong>
                    </p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="manage-products.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Products
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pb-2">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-files me-2 text-primary"></i>
                        Product Information
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="duplicateProductForm">
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Basic Information -->
                                <div class="mb-3">
                                    <label for="name" class="form-label fw-bold">Product Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($original_product['name'] . ' (Copy)'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="category_id" class="form-label fw-bold">Category *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo $category['id'] == $original_product['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="product_type" class="form-label fw-bold">Product Type</label>
                                            <select class="form-select" id="product_type" name="product_type">
                                                <option value="crop" <?php echo $original_product['product_type'] == 'crop' ? 'selected' : ''; ?>>Crop</option>
                                                <option value="livestock" <?php echo $original_product['product_type'] == 'livestock' ? 'selected' : ''; ?>>Livestock</option>
                                                <option value="poultry" <?php echo $original_product['product_type'] == 'poultry' ? 'selected' : ''; ?>>Poultry</option>
                                                <option value="dairy" <?php echo $original_product['product_type'] == 'dairy' ? 'selected' : ''; ?>>Dairy</option>
                                                <option value="equipment" <?php echo $original_product['product_type'] == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                                <option value="inputs" <?php echo $original_product['product_type'] == 'inputs' ? 'selected' : ''; ?>>Agricultural Inputs</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="variety" class="form-label fw-bold">Variety/Strain</label>
                                            <input type="text" class="form-control" id="variety" name="variety" 
                                                   value="<?php echo htmlspecialchars($original_product['variety'] ?? ''); ?>"
                                                   placeholder="e.g., Hybrid, Heirloom, etc.">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label fw-bold">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($original_product['description']); ?></textarea>
                                    <div class="form-text">Detailed product description for customers</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="short_description" class="form-label fw-bold">Short Description</label>
                                    <textarea class="form-control" id="short_description" name="short_description" rows="2"><?php echo htmlspecialchars($original_product['short_description'] ?? ''); ?></textarea>
                                    <div class="form-text">Brief summary for product listings</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Pricing & Stock -->
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle text-muted mb-3">
                                            <i class="bi bi-currency-dollar me-1"></i> Pricing & Stock
                                        </h6>
                                        
                                        <div class="mb-3">
                                            <label for="price_per_unit" class="form-label fw-bold">Price per Unit *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₦</span>
                                                <input type="number" step="0.01" class="form-control" id="price_per_unit" 
                                                       name="price_per_unit" value="<?php echo $original_product['price_per_unit']; ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="unit" class="form-label fw-bold">Unit *</label>
                                                    <select class="form-select" id="unit" name="unit" required>
                                                        <option value="kg" <?php echo $original_product['unit'] == 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                                        <option value="g" <?php echo $original_product['unit'] == 'g' ? 'selected' : ''; ?>>Gram (g)</option>
                                                        <option value="ton" <?php echo $original_product['unit'] == 'ton' ? 'selected' : ''; ?>>Ton</option>
                                                        <option value="bag" <?php echo $original_product['unit'] == 'bag' ? 'selected' : ''; ?>>Bag</option>
                                                        <option value="crate" <?php echo $original_product['unit'] == 'crate' ? 'selected' : ''; ?>>Crate</option>
                                                        <option value="piece" <?php echo $original_product['unit'] == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                                        <option value="dozen" <?php echo $original_product['unit'] == 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                                                        <option value="liter" <?php echo $original_product['unit'] == 'liter' ? 'selected' : ''; ?>>Liter (L)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="unit_quantity" class="form-label fw-bold">Unit Quantity</label>
                                                    <input type="number" step="0.01" class="form-control" id="unit_quantity" 
                                                           name="unit_quantity" value="<?php echo $original_product['unit_quantity']; ?>">
                                                    <div class="form-text">e.g., 1 kg, 500 g, etc.</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="stock_quantity" class="form-label fw-bold">Stock Quantity *</label>
                                            <input type="number" step="0.01" class="form-control" id="stock_quantity" 
                                                   name="stock_quantity" value="<?php echo $original_product['stock_quantity']; ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="low_stock_alert_level" class="form-label fw-bold">Low Stock Alert Level</label>
                                            <input type="number" step="0.01" class="form-control" id="low_stock_alert_level" 
                                                   name="low_stock_alert_level" value="<?php echo $original_product['low_stock_alert_level']; ?>">
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" 
                                                   <?php echo $original_product['is_featured'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_featured">
                                                <i class="bi bi-star-fill text-warning"></i> Featured Product
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-subtitle text-muted mb-3">
                                            <i class="bi bi-truck me-1"></i> Shipping & Physical Details
                                        </h6>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="weight_kg" class="form-label">Weight (kg)</label>
                                                    <input type="number" step="0.01" class="form-control" id="weight_kg" 
                                                           name="weight_kg" value="<?php echo $original_product['weight_kg']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="dimensions" class="form-label">Dimensions</label>
                                                    <input type="text" class="form-control" id="dimensions" name="dimensions" 
                                                           value="<?php echo htmlspecialchars($original_product['dimensions'] ?? ''); ?>"
                                                           placeholder="L x W x H">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="min_order_quantity" class="form-label">Min Order</label>
                                                    <input type="number" step="0.01" class="form-control" id="min_order_quantity" 
                                                           name="min_order_quantity" value="<?php echo $original_product['min_order_quantity']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="max_order_quantity" class="form-label">Max Order</label>
                                                    <input type="number" step="0.01" class="form-control" id="max_order_quantity" 
                                                           name="max_order_quantity" value="<?php echo $original_product['max_order_quantity']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Agricultural Details Section -->
                        <div class="card bg-light mt-4">
                            <div class="card-body">
                                <h6 class="card-subtitle text-muted mb-3">
                                    <i class="bi bi-tree me-1"></i> Agricultural Details
                                </h6>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="grade" class="form-label">Grade</label>
                                            <select class="form-select" id="grade" name="grade">
                                                <option value="">Select Grade</option>
                                                <option value="A" <?php echo ($agricultural_details['grade'] ?? '') == 'A' ? 'selected' : ''; ?>>Grade A</option>
                                                <option value="B" <?php echo ($agricultural_details['grade'] ?? '') == 'B' ? 'selected' : ''; ?>>Grade B</option>
                                                <option value="C" <?php echo ($agricultural_details['grade'] ?? '') == 'C' ? 'selected' : ''; ?>>Grade C</option>
                                                <option value="Premium" <?php echo ($agricultural_details['grade'] ?? '') == 'Premium' ? 'selected' : ''; ?>>Premium</option>
                                                <option value="Commercial" <?php echo ($agricultural_details['grade'] ?? '') == 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label d-block">Organic Status</label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="is_organic" name="is_organic" 
                                                       <?php echo ($agricultural_details['is_organic'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_organic">
                                                    <i class="bi bi-flower1 text-success"></i> Organic
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label d-block">GMO Status</label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="is_gmo" name="is_gmo" 
                                                       <?php echo ($agricultural_details['is_gmo'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_gmo">
                                                    <i class="bi bi-flask"></i> GMO
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="organic_certification_number" class="form-label">Certification #</label>
                                            <input type="text" class="form-control" id="organic_certification_number" 
                                                   name="organic_certification_number" 
                                                   value="<?php echo htmlspecialchars($agricultural_details['organic_certification_number'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="harvest_date" class="form-label">Harvest Date</label>
                                            <input type="date" class="form-control" id="harvest_date" name="harvest_date" 
                                                   value="<?php echo $agricultural_details['harvest_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="expiry_date" class="form-label">Expiry Date</label>
                                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                                   value="<?php echo $agricultural_details['expiry_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="shelf_life_days" class="form-label">Shelf Life (days)</label>
                                            <input type="number" class="form-control" id="shelf_life_days" name="shelf_life_days" 
                                                   value="<?php echo $agricultural_details['shelf_life_days'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="farming_method" class="form-label">Farming Method</label>
                                            <select class="form-select" id="farming_method" name="farming_method">
                                                <option value="">Select Method</option>
                                                <option value="conventional" <?php echo ($agricultural_details['farming_method'] ?? '') == 'conventional' ? 'selected' : ''; ?>>Conventional</option>
                                                <option value="hydroponic" <?php echo ($agricultural_details['farming_method'] ?? '') == 'hydroponic' ? 'selected' : ''; ?>>Hydroponic</option>
                                                <option value="greenhouse" <?php echo ($agricultural_details['farming_method'] ?? '') == 'greenhouse' ? 'selected' : ''; ?>>Greenhouse</option>
                                                <option value="open_field" <?php echo ($agricultural_details['farming_method'] ?? '') == 'open_field' ? 'selected' : ''; ?>>Open Field</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="irrigation_type" class="form-label">Irrigation Type</label>
                                            <input type="text" class="form-control" id="irrigation_type" name="irrigation_type" 
                                                   value="<?php echo htmlspecialchars($agricultural_details['irrigation_type'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="storage_temperature" class="form-label">Storage Temperature</label>
                                            <input type="text" class="form-control" id="storage_temperature" name="storage_temperature" 
                                                   value="<?php echo htmlspecialchars($agricultural_details['storage_temperature'] ?? ''); ?>"
                                                   placeholder="e.g., 4°C, Room temperature">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="storage_humidity" class="form-label">Storage Humidity</label>
                                            <input type="text" class="form-control" id="storage_humidity" name="storage_humidity" 
                                                   value="<?php echo htmlspecialchars($agricultural_details['storage_humidity'] ?? ''); ?>"
                                                   placeholder="e.g., 40-50%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="manage-products.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="bi bi-files me-1"></i> Duplicate Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Form validation
document.getElementById('duplicateProductForm').addEventListener('submit', function(e) {
    const price = parseFloat(document.getElementById('price_per_unit').value);
    const stock = parseFloat(document.getElementById('stock_quantity').value);
    
    if (price <= 0) {
        e.preventDefault();
        alert('Please enter a valid price');
        return false;
    }
    
    if (stock < 0) {
        e.preventDefault();
        alert('Stock quantity cannot be negative');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Duplicating...';
});

// Helper function to create slug (if needed client-side)
function createSlug(text) {
    return text.toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// Update slug when name changes (optional)
document.getElementById('name').addEventListener('blur', function() {
    // You can optionally generate a slug preview here
    const slug = createSlug(this.value);
    console.log('Slug preview:', slug);
});
</script>

<?php include '../../includes/footer.php'; ?>