<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get categories for dropdown
$categories = $db->fetchAll("SELECT id, name, description FROM categories WHERE is_active = TRUE ORDER BY name");

// Get seller profile for sidebar
$seller_profile = $db->fetchOne("
    SELECT business_name, business_logo as avatar, avg_rating
    FROM seller_profiles WHERE user_id = ?
", [$seller_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_data = [
        'name' => sanitizeInput($_POST['name']),
        'description' => sanitizeInput($_POST['description']),
        'short_description' => sanitizeInput($_POST['short_description']),
        'category_id' => (int)$_POST['category_id'],
        'product_type' => sanitizeInput($_POST['product_type']),
        'variety' => sanitizeInput($_POST['variety']),
        'price_per_unit' => (float)$_POST['price_per_unit'],
        'unit' => sanitizeInput($_POST['unit']),
        'unit_quantity' => (float)($_POST['unit_quantity'] ?? 1),
        'min_order_quantity' => (float)($_POST['min_order_quantity'] ?? 1),
        'max_order_quantity' => !empty($_POST['max_order_quantity']) ? (float)$_POST['max_order_quantity'] : null,
        'stock_quantity' => (float)$_POST['stock_quantity'],
        'low_stock_alert_level' => (float)($_POST['low_stock_alert_level'] ?? 10),
        'weight_kg' => !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null,
        'dimensions' => !empty($_POST['dimensions']) ? sanitizeInput($_POST['dimensions']) : null
    ];

    // Agricultural details
    $agricultural_data = [
        'grade' => !empty($_POST['grade']) ? sanitizeInput($_POST['grade']) : null,
        'is_organic' => isset($_POST['is_organic']) ? 1 : 0,
        'is_gmo' => isset($_POST['is_gmo']) ? 1 : 0,
        'organic_certification_number' => !empty($_POST['organic_certification_number']) ? sanitizeInput($_POST['organic_certification_number']) : null,
        'harvest_date' => !empty($_POST['harvest_date']) ? $_POST['harvest_date'] : null,
        'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        'shelf_life_days' => !empty($_POST['shelf_life_days']) ? (int)$_POST['shelf_life_days'] : null,
        'farming_method' => !empty($_POST['farming_method']) ? sanitizeInput($_POST['farming_method']) : 'conventional',
        'irrigation_type' => !empty($_POST['irrigation_type']) ? sanitizeInput($_POST['irrigation_type']) : null,
        'storage_temperature' => !empty($_POST['storage_temperature']) ? sanitizeInput($_POST['storage_temperature']) : null,
        'storage_humidity' => !empty($_POST['storage_humidity']) ? sanitizeInput($_POST['storage_humidity']) : null
    ];

    // Validate product data
    $errors = validateProduct($product_data);
    
    // Validate images
    $image_errors = [];
    $uploaded_images = [];
    
    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $total_images = count($_FILES['product_images']['name']);
        if ($total_images > 5) {
            $image_errors[] = 'Maximum 5 images allowed';
        }
        
        for ($i = 0; $i < min($total_images, 5); $i++) {
            $file = [
                'name' => $_FILES['product_images']['name'][$i],
                'type' => $_FILES['product_images']['type'][$i],
                'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                'error' => $_FILES['product_images']['error'][$i],
                'size' => $_FILES['product_images']['size'][$i]
            ];
            
            $file_errors = validateFileUpload($file, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
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

            // Generate slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_data['name'])));
            
            // Determine status
            $status = isset($_POST['save_draft']) ? 'draft' : 'pending';
            
            // Insert product
            $product_id = $db->insert('products', array_merge($product_data, [
                'seller_id' => $seller_id,
                'slug' => $slug . '-' . time() . '-' . uniqid(),
                'status' => $status
            ]));

            // Insert agricultural details
            $db->insert('product_agricultural_details', array_merge($agricultural_data, [
                'product_id' => $product_id
            ]));

            // Handle bulk pricing
            if (!empty($_POST['bulk_min_qty']) && is_array($_POST['bulk_min_qty'])) {
                foreach ($_POST['bulk_min_qty'] as $index => $min_qty) {
                    if (!empty($min_qty) && !empty($_POST['bulk_price'][$index])) {
                        $bulk_data = [
                            'product_id' => $product_id,
                            'min_quantity' => (float)$min_qty,
                            'price_per_unit' => (float)$_POST['bulk_price'][$index],
                            'discount_percentage' => !empty($_POST['bulk_discount'][$index]) ? (float)$_POST['bulk_discount'][$index] : null
                        ];
                        
                        if (!empty($_POST['bulk_max_qty'][$index])) {
                            $bulk_data['max_quantity'] = (float)$_POST['bulk_max_qty'][$index];
                        }
                        
                        $db->insert('product_bulk_pricing', $bulk_data);
                    }
                }
            }

            // Handle image uploads
            if (!empty($uploaded_images)) {
                $upload_path = '../../assets/uploads/products/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                
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
                            'is_primary' => ($index === 0) ? 1 : 0,
                            'sort_order' => $index
                        ];
                        $db->insert('product_images', $image_data);
                    }
                }
            }

            // Create approval record if not draft
            if ($status === 'pending') {
                // Get current product data as JSON for approval record
                $product_record = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$product_id]);
                $agricultural_record = $db->fetchOne("SELECT * FROM product_agricultural_details WHERE product_id = ?", [$product_id]);
                
                $new_data = [
                    'product' => $product_record,
                    'agricultural' => $agricultural_record
                ];
                
                $db->insert('product_approvals', [
                    'product_id' => $product_id,
                    'seller_id' => $seller_id,
                    'new_data' => json_encode($new_data),
                    'change_type' => 'create',
                    'status' => 'pending_review'
                ]);
            }

            $db->conn->commit();
            
            $success_message = $status === 'draft' ? 
                'Product saved as draft successfully! You can edit and submit later.' : 
                'Product submitted for approval successfully! You will be notified once approved.';
            
            setFlashMessage($success_message, 'success');
            
            if ($status === 'draft') {
                header('Location: manage-products.php');
                exit;
            } else {
                header('Location: manage-products.php');
                exit;
            }
            
        } catch (Exception $e) {
            $db->conn->rollback();
            $error = 'Error adding product: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

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

$page_title = "Add New Product";
$page_css = 'dashboard.css';

require_once '../../includes/header.php';
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
                        <h1 class="h5 mb-0">Add Product</h1>
                        <small class="text-muted">List your agricultural product</small>
                    </div>
                </div>
            </div>
            
            <!-- Desktop header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Add New Product</h1>
                    <p class="text-muted mb-0">Fill in the details to list your agricultural product</p>
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

            <form method="POST" action="" enctype="multipart/form-data" id="productForm">
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
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                           placeholder="e.g., Organic Brown Beans, Fresh Tomatoes"
                                           required>
                                    <div class="form-text">Use a clear and descriptive name for your product.</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="category_id" class="form-label fw-bold">Category *</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
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
                                                <option value="crop" <?php echo ($_POST['product_type'] ?? '') == 'crop' ? 'selected' : ''; ?>>🌾 Crop</option>
                                                <option value="livestock" <?php echo ($_POST['product_type'] ?? '') == 'livestock' ? 'selected' : ''; ?>>🐄 Livestock</option>
                                                <option value="poultry" <?php echo ($_POST['product_type'] ?? '') == 'poultry' ? 'selected' : ''; ?>>🐔 Poultry</option>
                                                <option value="dairy" <?php echo ($_POST['product_type'] ?? '') == 'dairy' ? 'selected' : ''; ?>>🥛 Dairy</option>
                                                <option value="equipment" <?php echo ($_POST['product_type'] ?? '') == 'equipment' ? 'selected' : ''; ?>>🔧 Equipment</option>
                                                <option value="inputs" <?php echo ($_POST['product_type'] ?? '') == 'inputs' ? 'selected' : ''; ?>>🌱 Inputs</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="variety" class="form-label fw-bold">Variety/Breed</label>
                                    <input type="text" class="form-control" id="variety" name="variety" 
                                           value="<?php echo htmlspecialchars($_POST['variety'] ?? ''); ?>" 
                                           placeholder="e.g., Ofada Rice, Local Chicken, Hybrid Maize">
                                    <div class="form-text">Specify the variety or breed if applicable.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="short_description" class="form-label fw-bold">Short Description</label>
                                    <textarea class="form-control" id="short_description" name="short_description" 
                                              rows="2" placeholder="Brief summary of your product (max 200 characters)"><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label fw-bold">Full Description *</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="5" placeholder="Detailed description including features, benefits, and usage instructions" 
                                              required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Provide comprehensive information about your product.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Inventory Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calculator me-2 text-primary"></i>
                                    Pricing & Inventory
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="price_per_unit" class="form-label fw-bold">Price per Unit (₦) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₦</span>
                                                <input type="number" step="0.01" min="0" class="form-control" id="price_per_unit" 
                                                       name="price_per_unit" value="<?php echo $_POST['price_per_unit'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="unit" class="form-label fw-bold">Unit *</label>
                                            <select class="form-select" id="unit" name="unit" required>
                                                <option value="">Select Unit</option>
                                                <option value="kg" <?php echo ($_POST['unit'] ?? '') == 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                                <option value="g" <?php echo ($_POST['unit'] ?? '') == 'g' ? 'selected' : ''; ?>>Gram (g)</option>
                                                <option value="ton" <?php echo ($_POST['unit'] ?? '') == 'ton' ? 'selected' : ''; ?>>Ton</option>
                                                <option value="bag" <?php echo ($_POST['unit'] ?? '') == 'bag' ? 'selected' : ''; ?>>Bag</option>
                                                <option value="crate" <?php echo ($_POST['unit'] ?? '') == 'crate' ? 'selected' : ''; ?>>Crate</option>
                                                <option value="piece" <?php echo ($_POST['unit'] ?? '') == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                                <option value="dozen" <?php echo ($_POST['unit'] ?? '') == 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                                                <option value="liter" <?php echo ($_POST['unit'] ?? '') == 'liter' ? 'selected' : ''; ?>>Liter (L)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="unit_quantity" class="form-label fw-bold">Unit Quantity</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="unit_quantity" 
                                                   name="unit_quantity" value="<?php echo $_POST['unit_quantity'] ?? '1'; ?>">
                                            <div class="form-text">e.g., 5kg bag</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="stock_quantity" class="form-label fw-bold">Stock Quantity *</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="stock_quantity" 
                                                   name="stock_quantity" value="<?php echo $_POST['stock_quantity'] ?? '0'; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="low_stock_alert_level" class="form-label fw-bold">Low Stock Alert</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="low_stock_alert_level" 
                                                   name="low_stock_alert_level" value="<?php echo $_POST['low_stock_alert_level'] ?? '10'; ?>">
                                            <div class="form-text">Alert when stock falls below this level</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="weight_kg" class="form-label fw-bold">Weight (kg)</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="weight_kg" 
                                                   name="weight_kg" value="<?php echo $_POST['weight_kg'] ?? ''; ?>">
                                            <div class="form-text">For shipping calculations</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="min_order_quantity" class="form-label fw-bold">Minimum Order Quantity</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="min_order_quantity" 
                                                   name="min_order_quantity" value="<?php echo $_POST['min_order_quantity'] ?? '1'; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_order_quantity" class="form-label fw-bold">Maximum Order Quantity</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="max_order_quantity" 
                                                   name="max_order_quantity" value="<?php echo $_POST['max_order_quantity'] ?? ''; ?>">
                                            <div class="form-text">Leave blank for no limit</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="dimensions" class="form-label fw-bold">Dimensions (L×W×H)</label>
                                            <input type="text" class="form-control" id="dimensions" name="dimensions" 
                                                   value="<?php echo htmlspecialchars($_POST['dimensions'] ?? ''); ?>" 
                                                   placeholder="e.g., 30×20×10 cm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agricultural Details Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-flower2 me-2 text-success"></i>
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
                                                <option value="Premium" <?php echo ($_POST['grade'] ?? '') == 'Premium' ? 'selected' : ''; ?>>Premium (Highest Quality)</option>
                                                <option value="A" <?php echo ($_POST['grade'] ?? '') == 'A' ? 'selected' : ''; ?>>Grade A</option>
                                                <option value="B" <?php echo ($_POST['grade'] ?? '') == 'B' ? 'selected' : ''; ?>>Grade B</option>
                                                <option value="C" <?php echo ($_POST['grade'] ?? '') == 'C' ? 'selected' : ''; ?>>Grade C</option>
                                                <option value="Commercial" <?php echo ($_POST['grade'] ?? '') == 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="farming_method" class="form-label fw-bold">Farming Method</label>
                                            <select class="form-select" id="farming_method" name="farming_method">
                                                <option value="">Select Method</option>
                                                <option value="conventional" <?php echo ($_POST['farming_method'] ?? '') == 'conventional' ? 'selected' : ''; ?>>Conventional</option>
                                                <option value="hydroponic" <?php echo ($_POST['farming_method'] ?? '') == 'hydroponic' ? 'selected' : ''; ?>>Hydroponic</option>
                                                <option value="greenhouse" <?php echo ($_POST['farming_method'] ?? '') == 'greenhouse' ? 'selected' : ''; ?>>Greenhouse</option>
                                                <option value="open_field" <?php echo ($_POST['farming_method'] ?? '') == 'open_field' ? 'selected' : ''; ?>>Open Field</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="shelf_life_days" class="form-label fw-bold">Shelf Life (days)</label>
                                            <input type="number" class="form-control" id="shelf_life_days" name="shelf_life_days" 
                                                   value="<?php echo $_POST['shelf_life_days'] ?? ''; ?>" placeholder="e.g., 30">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="harvest_date" class="form-label fw-bold">Harvest Date</label>
                                            <input type="date" class="form-control" id="harvest_date" 
                                                   name="harvest_date" value="<?php echo $_POST['harvest_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="expiry_date" class="form-label fw-bold">Expiry Date</label>
                                            <input type="date" class="form-control" id="expiry_date" 
                                                   name="expiry_date" value="<?php echo $_POST['expiry_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="storage_temperature" class="form-label fw-bold">Storage Temperature</label>
                                            <input type="text" class="form-control" id="storage_temperature" name="storage_temperature" 
                                                   value="<?php echo htmlspecialchars($_POST['storage_temperature'] ?? ''); ?>" 
                                                   placeholder="e.g., 4°C, Room temperature">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="storage_humidity" class="form-label fw-bold">Storage Humidity</label>
                                            <input type="text" class="form-control" id="storage_humidity" name="storage_humidity" 
                                                   value="<?php echo htmlspecialchars($_POST['storage_humidity'] ?? ''); ?>" 
                                                   placeholder="e.g., 60-70%">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="organic_certification_number" class="form-label fw-bold">Organic Certification Number</label>
                                            <input type="text" class="form-control" id="organic_certification_number" name="organic_certification_number" 
                                                   value="<?php echo htmlspecialchars($_POST['organic_certification_number'] ?? ''); ?>" 
                                                   placeholder="Enter certification number if applicable">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="is_organic" 
                                               name="is_organic" value="1" <?php echo isset($_POST['is_organic']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_organic">
                                            <i class="bi bi-tree-fill text-success"></i> Organic Product
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="is_gmo" 
                                               name="is_gmo" value="1" <?php echo isset($_POST['is_gmo']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_gmo">
                                            <i class="bi bi-flask"></i> GMO Product
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Actions & Media -->
                    <div class="col-lg-4">
                        <!-- Actions Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-play-circle me-2 text-primary"></i>
                                    Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-3">
                                    <button type="submit" name="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle me-2"></i> Submit for Approval
                                    </button>
                                    <button type="submit" name="save_draft" value="1" class="btn btn-outline-secondary">
                                        <i class="bi bi-save me-2"></i> Save as Draft
                                    </button>
                                    <a href="manage-products.php" class="btn btn-outline-danger">
                                        <i class="bi bi-x-circle me-2"></i> Cancel
                                    </a>
                                </div>
                                
                                <hr>
                                
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Note:</strong> Products submitted for approval will be reviewed by admin before becoming visible to customers.
                                </div>
                            </div>
                        </div>

                        <!-- Product Images Card -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-images me-2 text-primary"></i>
                                    Product Images
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="product_images" class="form-label fw-bold">Upload Images</label>
                                    <input type="file" class="form-control" id="product_images" 
                                           name="product_images[]" multiple accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text mt-2">
                                        <i class="bi bi-info-circle"></i> 
                                        Upload up to 5 images (JPG, PNG, WEBP). Max 5MB each.<br>
                                        <strong>First image will be the main product image.</strong>
                                    </div>
                                </div>
                                <div id="image-preview" class="row g-2 mt-3"></div>
                            </div>
                        </div>

                        <!-- Bulk Pricing Card -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-pie-chart me-2 text-primary"></i>
                                    Bulk Pricing
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="bulk-pricing-container">
                                    <div class="bulk-pricing-item mb-3 p-3 bg-light rounded">
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <label class="small text-muted">Min Quantity</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                       name="bulk_min_qty[]" placeholder="e.g., 10">
                                            </div>
                                            <div class="col-12">
                                                <label class="small text-muted">Max Quantity (optional)</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                       name="bulk_max_qty[]" placeholder="e.g., 50">
                                            </div>
                                            <div class="col-12">
                                                <label class="small text-muted">Price per Unit (₦)</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" 
                                                       name="bulk_price[]" placeholder="e.g., 5000">
                                            </div>
                                            <div class="col-12">
                                                <label class="small text-muted">Discount (%)</label>
                                                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" 
                                                       name="bulk_discount[]" placeholder="e.g., 10">
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-bulk" disabled>
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="add-bulk-pricing" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="bi bi-plus me-1"></i> Add Bulk Price Tier
                                </button>
                                <div class="form-text mt-2">
                                    <i class="bi bi-info-circle"></i> Set different prices for bulk purchases.
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
// Image preview functionality
document.getElementById('product_images').addEventListener('change', function(e) {
    const preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    
    const files = Array.from(e.target.files).slice(0, 5);
    
    files.forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const col = document.createElement('div');
                col.className = 'col-4';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-thumbnail" style="height: 100px; width: 100%; object-fit: cover;">
                        <span class="position-absolute top-0 end-0 badge ${index === 0 ? 'bg-primary' : 'bg-secondary'} m-1">
                            ${index === 0 ? 'Main' : index + 1}
                        </span>
                    </div>
                `;
                preview.appendChild(col);
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Show warning if more than 5 files
    if (e.target.files.length > 5) {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning mt-2 small';
        warning.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Only first 5 images will be uploaded';
        preview.appendChild(warning);
    }
});

// Bulk pricing functionality
document.getElementById('add-bulk-pricing').addEventListener('click', function() {
    const container = document.getElementById('bulk-pricing-container');
    const itemCount = container.querySelectorAll('.bulk-pricing-item').length;
    const newItem = document.createElement('div');
    newItem.className = 'bulk-pricing-item mb-3 p-3 bg-light rounded';
    newItem.innerHTML = `
        <div class="row g-2">
            <div class="col-12">
                <label class="small text-muted">Min Quantity</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bulk_min_qty[]" placeholder="e.g., 10">
            </div>
            <div class="col-12">
                <label class="small text-muted">Max Quantity (optional)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bulk_max_qty[]" placeholder="e.g., 50">
            </div>
            <div class="col-12">
                <label class="small text-muted">Price per Unit (₦)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bulk_price[]" placeholder="e.g., 5000">
            </div>
            <div class="col-12">
                <label class="small text-muted">Discount (%)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="bulk_discount[]" placeholder="e.g., 10">
            </div>
            <div class="col-12 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-bulk">
                    <i class="bi bi-trash"></i> Remove
                </button>
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
        
        // If only one left, disable its remove button
        const remainingItems = document.querySelectorAll('.bulk-pricing-item');
        if (remainingItems.length === 1) {
            remainingItems[0].querySelector('.remove-bulk').disabled = true;
        }
    });
});

// Initialize remove buttons (first item disabled)
document.querySelectorAll('.remove-bulk').forEach((btn, index) => {
    if (index === 0) btn.disabled = true;
    btn.addEventListener('click', function() {
        this.closest('.bulk-pricing-item').remove();
        
        // If only one left, disable its remove button
        const remainingItems = document.querySelectorAll('.bulk-pricing-item');
        if (remainingItems.length === 1) {
            remainingItems[0].querySelector('.remove-bulk').disabled = true;
        }
    });
});

// Auto-generate slug (optional)
document.getElementById('name').addEventListener('blur', function() {
    // For reference only - slug is generated server-side
    const name = this.value;
    const slug = name.toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    console.log('Suggested slug:', slug);
});

// Form validation
document.getElementById('productForm').addEventListener('submit', function(e) {
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
    // Simple alert for now - can be enhanced
    alert(message);
}
</script>

<?php include '../../includes/footer.php'; ?>