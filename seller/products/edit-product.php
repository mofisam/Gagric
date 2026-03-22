<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/header.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$product_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

// Get product details
$product = $db->fetchOne("
    SELECT p.*, pad.* 
    FROM products p 
    LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id 
    WHERE p.id = ? AND p.seller_id = ?
", [$product_id, $seller_id]);

if (!$product) {
    setFlashMessage('Product not found or access denied', 'error');
    header('Location: manage-products.php');
    exit;
}

// Get product images
$product_images = $db->fetchAll("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY is_primary DESC, sort_order ASC
", [$product_id]);

// Get bulk pricing
$bulk_pricing = $db->fetchAll("
    SELECT * FROM product_bulk_pricing 
    WHERE product_id = ? 
    ORDER BY min_quantity ASC
", [$product_id]);

// Get categories
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = TRUE ORDER BY name");

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

// Status badge colors
$status_colors = [
    'approved' => 'success',
    'pending' => 'warning',
    'draft' => 'secondary',
    'rejected' => 'danger',
    'suspended' => 'dark'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_data = [
        'name' => sanitizeInput($_POST['name']),
        'description' => sanitizeInput($_POST['description']),
        'short_description' => sanitizeInput($_POST['short_description']),
        'category_id' => $_POST['category_id'],
        'product_type' => $_POST['product_type'],
        'variety' => sanitizeInput($_POST['variety']),
        'price_per_unit' => $_POST['price_per_unit'],
        'unit' => $_POST['unit'],
        'unit_quantity' => $_POST['unit_quantity'] ?? 1,
        'min_order_quantity' => $_POST['min_order_quantity'] ?? 1,
        'max_order_quantity' => !empty($_POST['max_order_quantity']) ? $_POST['max_order_quantity'] : null,
        'stock_quantity' => $_POST['stock_quantity'],
        'weight_kg' => !empty($_POST['weight_kg']) ? $_POST['weight_kg'] : null,
        'low_stock_alert_level' => $_POST['low_stock_alert_level'] ?? 10
    ];

    // Agricultural details
    $agricultural_data = [
        'grade' => $_POST['grade'] ?? null,
        'is_organic' => isset($_POST['is_organic']) ? 1 : 0,
        'is_gmo' => isset($_POST['is_gmo']) ? 1 : 0,
        'harvest_date' => !empty($_POST['harvest_date']) ? $_POST['harvest_date'] : null,
        'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        'farming_method' => $_POST['farming_method'] ?? 'conventional',
        'organic_certification_number' => sanitizeInput($_POST['organic_certification_number'] ?? ''),
        'storage_temperature' => sanitizeInput($_POST['storage_temperature'] ?? ''),
        'storage_humidity' => sanitizeInput($_POST['storage_humidity'] ?? '')
    ];

    // Validate product data
    $errors = validateProduct($product_data);
    
    // Validate new images
    $image_errors = [];
    $uploaded_images = [];
    
    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $existing_images = count($product_images);
        $new_images = count($_FILES['product_images']['name']);
        
        if ($existing_images + $new_images > 5) {
            $image_errors[] = 'Maximum 5 images allowed. You have ' . $existing_images . ' existing images.';
        }
        
        for ($i = 0; $i < $new_images; $i++) {
            $file = [
                'name' => $_FILES['product_images']['name'][$i],
                'type' => $_FILES['product_images']['type'][$i],
                'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                'error' => $_FILES['product_images']['error'][$i],
                'size' => $_FILES['product_images']['size'][$i]
            ];
            
            $file_errors = validateFileUpload($file, ALLOWED_IMAGE_TYPES, MAX_FILE_SIZE);
            if (!empty($file_errors)) {
                $image_errors = array_merge($image_errors, $file_errors);
            } else {
                $uploaded_images[] = $file;
            }
        }
    }
    
    if (!empty($image_errors)) {
        $errors = array_merge($errors, $image_errors);
    }
    
    if (empty($errors)) {
        try {
            $db->conn->begin_transaction();

            // Check if significant changes require re-approval
            $requires_reapproval = false;
            if ($product['status'] == 'approved') {
                $significant_fields = ['name', 'description', 'price_per_unit', 'category_id', 'product_type'];
                foreach ($significant_fields as $field) {
                    if (($product[$field] ?? null) != ($product_data[$field] ?? null)) {
                        $requires_reapproval = true;
                        break;
                    }
                }
            }

            // Update product
            $db->update('products', $product_data, 'id = ? AND seller_id = ?', [$product_id, $seller_id]);

            // Update agricultural details
            $ag_exists = $db->fetchOne("SELECT product_id FROM product_agricultural_details WHERE product_id = ?", [$product_id]);
            if ($ag_exists) {
                $db->update('product_agricultural_details', $agricultural_data, 'product_id = ?', [$product_id]);
            } else {
                $db->insert('product_agricultural_details', array_merge($agricultural_data, ['product_id' => $product_id]));
            }

            // Handle image deletions
            if (!empty($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $image_id) {
                    $image = $db->fetchOne("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?", [$image_id, $product_id]);
                    if ($image) {
                        // Delete file
                        $filepath = '../../assets/uploads/products/' . $image['image_path'];
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                        // Delete from database
                        $db->query("DELETE FROM product_images WHERE id = ? AND product_id = ?", [$image_id, $product_id]);
                    }
                }
            }

            // Handle new image uploads
            if (!empty($uploaded_images)) {
                $upload_path = '../../assets/uploads/products/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
                $sort_order = count($product_images) - count($_POST['delete_images'] ?? []);
                foreach ($uploaded_images as $index => $image) {
                    // Generate unique filename
                    $file_extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
                    $filename = 'product_' . $product_id . '_' . uniqid() . '_' . time() . '.' . $file_extension;
                    $filepath = $upload_path . $filename;
                    
                    if (move_uploaded_file($image['tmp_name'], $filepath)) {
                        // Insert into product_images table
                        $image_data = [
                            'product_id' => $product_id,
                            'image_path' => $filename,
                            'alt_text' => $product_data['name'] . ' image ' . ($index + 1),
                            'is_primary' => 0,
                            'sort_order' => $sort_order + $index
                        ];
                        $db->insert('product_images', $image_data);
                    }
                }
            }

            // Handle primary image selection
            if (!empty($_POST['primary_image'])) {
                // Reset all to non-primary
                $db->query("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [$product_id]);
                // Set selected as primary
                $db->query("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?", [$_POST['primary_image'], $product_id]);
            }

            // Update bulk pricing
            $db->query("DELETE FROM product_bulk_pricing WHERE product_id = ?", [$product_id]);
            
            if (!empty($_POST['bulk_min_qty']) && is_array($_POST['bulk_min_qty'])) {
                foreach ($_POST['bulk_min_qty'] as $index => $min_qty) {
                    if (!empty($min_qty) && !empty($_POST['bulk_price'][$index])) {
                        $bulk_data = [
                            'product_id' => $product_id,
                            'min_quantity' => $min_qty,
                            'price_per_unit' => $_POST['bulk_price'][$index]
                        ];
                        
                        if (!empty($_POST['bulk_max_qty'][$index])) {
                            $bulk_data['max_quantity'] = $_POST['bulk_max_qty'][$index];
                        }
                        
                        $db->insert('product_bulk_pricing', $bulk_data);
                    }
                }
            }

            // If approved product had significant changes, set to pending and create approval record
            if ($requires_reapproval) {
                $db->update('products', ['status' => 'pending'], 'id = ?', [$product_id]);
                
                // Create approval record
                $old_data = [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price_per_unit' => $product['price_per_unit'],
                    'category_id' => $product['category_id']
                ];
                
                $new_data = [
                    'name' => $product_data['name'],
                    'description' => $product_data['description'],
                    'price_per_unit' => $product_data['price_per_unit'],
                    'category_id' => $product_data['category_id']
                ];
                
                $db->insert('product_approvals', [
                    'product_id' => $product_id,
                    'seller_id' => $seller_id,
                    'change_type' => 'update',
                    'status' => 'pending_review',
                    'old_data' => json_encode($old_data),
                    'new_data' => json_encode($new_data),
                    'changed_fields' => json_encode(array_keys($old_data))
                ]);
            }

            $db->conn->commit();
            
            if ($requires_reapproval) {
                $success = 'Product updated successfully! Product is now pending re-approval due to significant changes.';
            } else {
                $success = 'Product updated successfully!';
            }
            
            // Refresh product data
            $product = $db->fetchOne("
                SELECT p.*, pad.* 
                FROM products p 
                LEFT JOIN product_agricultural_details pad ON p.id = pad.product_id 
                WHERE p.id = ? AND p.seller_id = ?
            ", [$product_id, $seller_id]);
            
            $product_images = $db->fetchAll("
                SELECT * FROM product_images 
                WHERE product_id = ? 
                ORDER BY is_primary DESC, sort_order ASC
            ", [$product_id]);
            
            $bulk_pricing = $db->fetchAll("
                SELECT * FROM product_bulk_pricing 
                WHERE product_id = ? 
                ORDER BY min_quantity ASC
            ", [$product_id]);
            
        } catch (Exception $e) {
            $db->conn->rollback();
            $error = 'Error updating product: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$page_title = "Edit Product";
$page_css = 'dashboard.css';
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
                        <h1 class="h5 mb-0">Edit Product</h1>
                        <small class="text-muted">Update product information</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Edit Product</h1>
                    <p class="text-muted mb-0">Update your agricultural product information</p>
                </div>
                <div>
                    <span class="badge bg-<?php echo $status_colors[$product['status']] ?? 'secondary'; ?> fs-6 p-2 me-2">
                        <i class="bi bi-<?php echo $product['status'] == 'approved' ? 'check-circle' : ($product['status'] == 'pending' ? 'clock' : 'circle'); ?> me-1"></i>
                        <?php echo ucfirst($product['status']); ?>
                    </span>
                    <a href="manage-products.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
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

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row g-4">
                    <!-- Left Column - Main Information -->
                    <div class="col-lg-8">
                        <!-- Basic Information Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-info-circle me-2 text-primary"></i>
                                    Basic Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label fw-bold">Product Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($product['name']); ?>" 
                                           placeholder="Enter product name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="short_description" class="form-label fw-bold">Short Description</label>
                                    <textarea class="form-control" id="short_description" name="short_description" 
                                              rows="2" placeholder="Brief description for product listings"><?php echo htmlspecialchars($product['short_description'] ?? ''); ?></textarea>
                                    <div class="form-text">Brief summary shown in product listings (max 200 characters)</div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label fw-bold">Full Description *</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="5" placeholder="Detailed product description" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="category_id" class="form-label fw-bold">Category *</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="product_type" class="form-label fw-bold">Product Type *</label>
                                            <select class="form-select" id="product_type" name="product_type" required>
                                                <option value="">Select Type</option>
                                                <option value="crop" <?php echo $product['product_type'] == 'crop' ? 'selected' : ''; ?>>🌾 Crop</option>
                                                <option value="livestock" <?php echo $product['product_type'] == 'livestock' ? 'selected' : ''; ?>>🐄 Livestock</option>
                                                <option value="poultry" <?php echo $product['product_type'] == 'poultry' ? 'selected' : ''; ?>>🐔 Poultry</option>
                                                <option value="dairy" <?php echo $product['product_type'] == 'dairy' ? 'selected' : ''; ?>>🥛 Dairy</option>
                                                <option value="equipment" <?php echo $product['product_type'] == 'equipment' ? 'selected' : ''; ?>>🔧 Equipment</option>
                                                <option value="inputs" <?php echo $product['product_type'] == 'inputs' ? 'selected' : ''; ?>>🌱 Inputs</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="variety" class="form-label fw-bold">Variety/Breed</label>
                                    <input type="text" class="form-control" id="variety" name="variety" 
                                           value="<?php echo htmlspecialchars($product['variety'] ?? ''); ?>" 
                                           placeholder="e.g., Ofada Rice, Local Chicken, Heirloom Tomato">
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Inventory Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-tag me-2 text-primary"></i>
                                    Pricing & Inventory
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="price_per_unit" class="form-label fw-bold">Price (₦) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₦</span>
                                                <input type="number" step="0.01" min="0" class="form-control" id="price_per_unit" 
                                                       name="price_per_unit" value="<?php echo $product['price_per_unit']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="unit" class="form-label fw-bold">Unit *</label>
                                            <select class="form-select" id="unit" name="unit" required>
                                                <option value="">Select Unit</option>
                                                <option value="kg" <?php echo $product['unit'] == 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                                <option value="g" <?php echo $product['unit'] == 'g' ? 'selected' : ''; ?>>Gram (g)</option>
                                                <option value="ton" <?php echo $product['unit'] == 'ton' ? 'selected' : ''; ?>>Ton</option>
                                                <option value="bag" <?php echo $product['unit'] == 'bag' ? 'selected' : ''; ?>>Bag</option>
                                                <option value="crate" <?php echo $product['unit'] == 'crate' ? 'selected' : ''; ?>>Crate</option>
                                                <option value="piece" <?php echo $product['unit'] == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                                <option value="dozen" <?php echo $product['unit'] == 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                                                <option value="liter" <?php echo $product['unit'] == 'liter' ? 'selected' : ''; ?>>Liter</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="unit_quantity" class="form-label fw-bold">Unit Quantity</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="unit_quantity" 
                                                   name="unit_quantity" value="<?php echo $product['unit_quantity']; ?>">
                                            <div class="form-text">e.g., 2.5 for 2.5kg bags</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="min_order_quantity" class="form-label fw-bold">Min Order</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="min_order_quantity" 
                                                   name="min_order_quantity" value="<?php echo $product['min_order_quantity']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="max_order_quantity" class="form-label fw-bold">Max Order</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="max_order_quantity" 
                                                   name="max_order_quantity" value="<?php echo $product['max_order_quantity'] ?? ''; ?>">
                                            <div class="form-text">Leave empty for no limit</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="stock_quantity" class="form-label fw-bold">Stock Quantity *</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="stock_quantity" 
                                                   name="stock_quantity" value="<?php echo $product['stock_quantity']; ?>" required>
                                            <div class="form-text">Current available quantity</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="low_stock_alert_level" class="form-label fw-bold">Low Stock Alert</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="low_stock_alert_level" 
                                                   name="low_stock_alert_level" value="<?php echo $product['low_stock_alert_level'] ?? 10; ?>">
                                            <div class="form-text">Alert when stock falls below this level</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="weight_kg" class="form-label fw-bold">Weight (kg)</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="weight_kg" 
                                                   name="weight_kg" value="<?php echo $product['weight_kg'] ?? ''; ?>">
                                            <div class="form-text">Shipping weight per unit</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agricultural Details Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-flower1 me-2 text-primary"></i>
                                    Agricultural Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="grade" class="form-label fw-bold">Grade</label>
                                            <select class="form-select" id="grade" name="grade">
                                                <option value="">Select Grade</option>
                                                <option value="Premium" <?php echo ($product['grade'] ?? '') == 'Premium' ? 'selected' : ''; ?>>Premium</option>
                                                <option value="A" <?php echo ($product['grade'] ?? '') == 'A' ? 'selected' : ''; ?>>A</option>
                                                <option value="B" <?php echo ($product['grade'] ?? '') == 'B' ? 'selected' : ''; ?>>B</option>
                                                <option value="C" <?php echo ($product['grade'] ?? '') == 'C' ? 'selected' : ''; ?>>C</option>
                                                <option value="Commercial" <?php echo ($product['grade'] ?? '') == 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="farming_method" class="form-label fw-bold">Farming Method</label>
                                            <select class="form-select" id="farming_method" name="farming_method">
                                                <option value="">Select Method</option>
                                                <option value="conventional" <?php echo ($product['farming_method'] ?? '') == 'conventional' ? 'selected' : ''; ?>>Conventional</option>
                                                <option value="hydroponic" <?php echo ($product['farming_method'] ?? '') == 'hydroponic' ? 'selected' : ''; ?>>Hydroponic</option>
                                                <option value="greenhouse" <?php echo ($product['farming_method'] ?? '') == 'greenhouse' ? 'selected' : ''; ?>>Greenhouse</option>
                                                <option value="open_field" <?php echo ($product['farming_method'] ?? '') == 'open_field' ? 'selected' : ''; ?>>Open Field</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="organic_certification_number" class="form-label fw-bold">Organic Cert. #</label>
                                            <input type="text" class="form-control" id="organic_certification_number" 
                                                   name="organic_certification_number" 
                                                   value="<?php echo htmlspecialchars($product['organic_certification_number'] ?? ''); ?>"
                                                   placeholder="If organic, enter certification number">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="harvest_date" class="form-label fw-bold">Harvest Date</label>
                                            <input type="date" class="form-control" id="harvest_date" 
                                                   name="harvest_date" value="<?php echo $product['harvest_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="expiry_date" class="form-label fw-bold">Expiry Date</label>
                                            <input type="date" class="form-control" id="expiry_date" 
                                                   name="expiry_date" value="<?php echo $product['expiry_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="storage_temperature" class="form-label fw-bold">Storage Temperature</label>
                                            <input type="text" class="form-control" id="storage_temperature" 
                                                   name="storage_temperature" 
                                                   value="<?php echo htmlspecialchars($product['storage_temperature'] ?? ''); ?>"
                                                   placeholder="e.g., 4-8°C">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="storage_humidity" class="form-label fw-bold">Storage Humidity</label>
                                            <input type="text" class="form-control" id="storage_humidity" 
                                                   name="storage_humidity" 
                                                   value="<?php echo htmlspecialchars($product['storage_humidity'] ?? ''); ?>"
                                                   placeholder="e.g., 60-70%">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="is_organic" 
                                               name="is_organic" value="1" <?php echo ($product['is_organic'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_organic">
                                            <i class="bi bi-leaf text-success me-1"></i> Organic Product
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="is_gmo" 
                                               name="is_gmo" value="1" <?php echo ($product['is_gmo'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_gmo">
                                            <i class="bi bi-flask text-warning me-1"></i> GMO Product
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Media & Pricing -->
                    <div class="col-lg-4">
                        <!-- Actions Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-gear me-2 text-primary"></i>
                                    Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle me-2"></i> Update Product
                                    </button>
                                    <?php if ($product['status'] == 'draft'): ?>
                                        <button type="submit" name="submit_for_approval" value="1" class="btn btn-primary">
                                            <i class="bi bi-send-check me-2"></i> Submit for Approval
                                        </button>
                                    <?php endif; ?>
                                    <a href="manage-products.php" class="btn btn-outline-danger">
                                        <i class="bi bi-x-circle me-2"></i> Cancel
                                    </a>
                                </div>
                                
                                <hr>
                                
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <small>Changes to name, description, price, or category will require re-approval.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Product Images Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-images me-2 text-primary"></i>
                                    Product Images
                                    <span class="badge bg-secondary ms-2">Max 5</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Existing Images -->
                                <?php if ($product_images): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Current Images</label>
                                        <div class="row g-2" id="existing-images">
                                            <?php foreach ($product_images as $index => $image): ?>
                                                <div class="col-6 p-0">
                                                    <div class="card h-100">
                                                        <img src="<?php echo BASE_URL . '/assets/uploads/products/' . $image['image_path']; ?>" 
                                                             class="card-img-top" alt="<?php echo $image['alt_text']; ?>"
                                                             style="height: 100px; object-fit: cover;">
                                                        <div class="card-body p-1">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" 
                                                                       name="primary_image" value="<?php echo $image['id']; ?>"
                                                                       id="primary_<?php echo $image['id']; ?>"
                                                                       <?php echo $image['is_primary'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="primary_<?php echo $image['id']; ?>">
                                                                    <i class="bi bi-star-fill text-warning"></i> Primary
                                                                </label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" 
                                                                       name="delete_images[]" value="<?php echo $image['id']; ?>"
                                                                       id="delete_<?php echo $image['id']; ?>">
                                                                <label class="form-check-label text-danger" for="delete_<?php echo $image['id']; ?>">
                                                                    <i class="bi bi-trash"></i> Delete
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- New Image Upload -->
                                <div class="mb-3">
                                    <label for="product_images" class="form-label fw-bold">Add New Images</label>
                                    <input type="file" class="form-control" id="product_images" 
                                           name="product_images[]" multiple accept="image/*">
                                    <div class="form-text">
                                        <i class="bi bi-info-circle"></i>
                                        Upload up to <?php echo max(0, 5 - count($product_images)); ?> more images (JPG, PNG, WEBP). Max 5MB each.
                                    </div>
                                </div>
                                <div id="image-preview" class="mt-3"></div>
                            </div>
                        </div>

                        <!-- Bulk Pricing Card -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calculator me-2 text-primary"></i>
                                    Bulk Pricing
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="bulk-pricing-container">
                                    <?php if ($bulk_pricing): ?>
                                        <?php foreach ($bulk_pricing as $index => $bulk): ?>
                                            <div class="bulk-pricing-item mb-3 p-2 border rounded">
                                                <div class="row g-2">
                                                    <div class="col-5">
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                            name="bulk_min_qty[]" placeholder="Min Qty" required
                                                            value="<?php echo $bulk['min_quantity']; ?>">
                                                    </div>
                                                    <div class="col-5">
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                            name="bulk_price[]" placeholder="Price" required
                                                            value="<?php echo $bulk['price_per_unit']; ?>">
                                                    </div>
                                                    <div class="col-2">
                                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-bulk-btn">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="row g-2 mt-2">
                                                    <div class="col-12">
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                            name="bulk_max_qty[]" placeholder="Max Qty (optional)"
                                                            value="<?php echo $bulk['max_quantity'] ?? ''; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="bulk-pricing-item mb-3 p-2 border rounded">
                                            <div class="row g-2">
                                                <div class="col-5">
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                        name="bulk_min_qty[]" placeholder="Min Qty" required>
                                                </div>
                                                <div class="col-5">
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                        name="bulk_price[]" placeholder="Price" required>
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-bulk-btn" disabled>
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row g-2 mt-2">
                                                <div class="col-12">
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                        name="bulk_max_qty[]" placeholder="Max Qty (optional)">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="add-bulk-pricing" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-plus-circle me-1"></i> Add Bulk Price Tier
                                </button>
                                <div class="form-text mt-2">
                                    <i class="bi bi-lightbulb"></i> Add volume discounts for bulk purchases
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
// Bulk pricing functionality
document.getElementById('add-bulk-pricing').addEventListener('click', function() {
    const container = document.getElementById('bulk-pricing-container');
    const newItem = document.createElement('div');
    newItem.className = 'bulk-pricing-item mb-3 p-2 border rounded';
    newItem.innerHTML = `
        <div class="row g-2">
            <div class="col-5">
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bulk_min_qty[]" placeholder="Min Qty">
            </div>
            <div class="col-5">
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bulk_price[]" placeholder="Price">
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-bulk">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-12">
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                       name="bulk_max_qty[]" placeholder="Max Qty (optional)">
            </div>
        </div>
    `;
    container.appendChild(newItem);
    
    // Enable remove buttons
    const removeButtons = document.querySelectorAll('.remove-bulk');
    removeButtons.forEach(btn => btn.disabled = false);
    
    // Add click event to new remove button
    newItem.querySelector('.remove-bulk').addEventListener('click', function() {
        this.closest('.bulk-pricing-item').remove();
        
        // If only one remains, disable its remove button
        const remaining = document.querySelectorAll('.bulk-pricing-item');
        if (remaining.length === 1) {
            remaining[0].querySelector('.remove-bulk').disabled = true;
        }
    });
});

// Initialize remove buttons
document.querySelectorAll('.remove-bulk').forEach((btn, index, buttons) => {
    btn.addEventListener('click', function() {
        this.closest('.bulk-pricing-item').remove();
        
        // If only one remains, disable its remove button
        const remaining = document.querySelectorAll('.bulk-pricing-item');
        if (remaining.length === 1) {
            remaining[0].querySelector('.remove-bulk').disabled = true;
        }
    });
    
    // Disable remove button if only one item
    if (buttons.length === 1) {
        btn.disabled = true;
    }
});

// Image preview for new uploads
document.getElementById('product_images').addEventListener('change', function(e) {
    const preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    
    const existingCount = <?php echo count($product_images); ?>;
    const maxNew = 5 - existingCount;
    
    Array.from(e.target.files).slice(0, maxNew).forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'd-inline-block me-2 mb-2 text-center';
                imgContainer.style.width = '100px';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-thumbnail';
                img.style.height = '80px';
                img.style.width = '100px';
                img.style.objectFit = 'cover';
                
                const badge = document.createElement('span');
                badge.className = 'badge bg-secondary mt-1 d-block';
                badge.textContent = 'New';
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(badge);
                preview.appendChild(imgContainer);
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Show warning if trying to upload more than allowed
    if (e.target.files.length > maxNew) {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning mt-2';
        warning.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Only ' + maxNew + ' more images allowed (max 5 total).';
        preview.appendChild(warning);
    }
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const price = document.getElementById('price_per_unit').value;
    const stock = document.getElementById('stock_quantity').value;
    
    if (price <= 0) {
        e.preventDefault();
        showToast('Price must be greater than 0', 'danger');
        return false;
    }
    
    if (stock < 0) {
        e.preventDefault();
        showToast('Stock quantity cannot be negative', 'danger');
        return false;
    }
});

function showToast(message, type) {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
}

// Add custom styles
const style = document.createElement('style');
style.textContent = `
    .dashboard-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
    }
    .bulk-pricing-item {
        background-color: #f8f9fa;
        transition: all 0.2s;
    }
    .bulk-pricing-item:hover {
        background-color: #e9ecef;
    }
    @media (max-width: 768px) {
        .card-header h5 {
            font-size: 1rem;
        }
    }
`;
document.head.appendChild(style);
</script>

<script>
// Bulk pricing functionality - FIXED VERSION
document.addEventListener('DOMContentLoaded', function() {
    // Function to add new bulk pricing tier
    function addBulkPricingTier() {
        const container = document.getElementById('bulk-pricing-container');
        if (!container) return;
        
        // Create new bulk pricing item
        const newItem = document.createElement('div');
        newItem.className = 'bulk-pricing-item mb-3 p-2 border rounded';
        newItem.innerHTML = `
            <div class="row g-2">
                <div class="col-5">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                           name="bulk_min_qty[]" placeholder="Min Qty" required>
                </div>
                <div class="col-5">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                           name="bulk_price[]" placeholder="Price" required>
                </div>
                <div class="col-2">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-bulk-btn">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-12">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                           name="bulk_max_qty[]" placeholder="Max Qty (optional)">
                </div>
            </div>
        `;
        
        // Add the new item to the container
        container.appendChild(newItem);
        
        // Add remove functionality to the new remove button
        const removeBtn = newItem.querySelector('.remove-bulk-btn');
        removeBtn.addEventListener('click', function() {
            removeBulkPricingItem(this);
        });
        
        // Show all remove buttons if there are multiple items
        updateRemoveButtons();
        
        // Show success feedback
        showToast('Bulk pricing tier added', 'info');
    }
    
    // Function to remove bulk pricing item
    function removeBulkPricingItem(button) {
        const item = button.closest('.bulk-pricing-item');
        if (item) {
            item.remove();
            updateRemoveButtons();
            showToast('Bulk pricing tier removed', 'info');
        }
    }
    
    // Function to update remove buttons (disable if only one item)
    function updateRemoveButtons() {
        const items = document.querySelectorAll('.bulk-pricing-item');
        const removeButtons = document.querySelectorAll('.remove-bulk-btn');
        
        if (items.length === 1) {
            // Disable remove button if only one item
            removeButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            });
        } else {
            // Enable all remove buttons
            removeButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            });
        }
    }
    
    // Add click event to the "Add Bulk Price Tier" button
    const addButton = document.getElementById('add-bulk-pricing');
    if (addButton) {
        // Remove any existing event listeners
        const newAddButton = addButton.cloneNode(true);
        addButton.parentNode.replaceChild(newAddButton, addButton);
        
        // Add new event listener
        newAddButton.addEventListener('click', function(e) {
            e.preventDefault();
            addBulkPricingTier();
        });
    }
    
    // Initialize existing remove buttons
    document.querySelectorAll('.remove-bulk-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            removeBulkPricingItem(this);
        });
    });
    
    // Initialize remove button states
    updateRemoveButtons();
});

// Helper function for toast notifications
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    const icon = type === 'success' ? 'bi-check-circle' : 
                  type === 'danger' ? 'bi-exclamation-triangle' : 'bi-info-circle';
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${icon} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 2000
    });
    bsToast.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Also add this to handle form submission validation for bulk pricing
document.querySelector('form')?.addEventListener('submit', function(e) {
    // Validate bulk pricing tiers
    const minQtys = document.querySelectorAll('input[name="bulk_min_qty[]"]');
    const prices = document.querySelectorAll('input[name="bulk_price[]"]');
    
    for (let i = 0; i < minQtys.length; i++) {
        if (minQtys[i].value && !prices[i].value) {
            e.preventDefault();
            showToast('Please enter price for bulk pricing tier #' + (i + 1), 'danger');
            return false;
        }
        
        if (prices[i].value && !minQtys[i].value) {
            e.preventDefault();
            showToast('Please enter minimum quantity for bulk pricing tier #' + (i + 1), 'danger');
            return false;
        }
        
        // Validate that min quantity is positive
        if (minQtys[i].value && parseFloat(minQtys[i].value) <= 0) {
            e.preventDefault();
            showToast('Minimum quantity must be greater than 0', 'danger');
            return false;
        }
        
        // Validate that price is positive
        if (prices[i].value && parseFloat(prices[i].value) <= 0) {
            e.preventDefault();
            showToast('Price must be greater than 0', 'danger');
            return false;
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>