<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/validation.php';

requireSeller();
$db = new Database();

$seller_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get categories for dropdown
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = TRUE ORDER BY name");

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
        'max_order_quantity' => $_POST['max_order_quantity'] ?? null,
        'stock_quantity' => $_POST['stock_quantity'],
        'weight_kg' => $_POST['weight_kg'] ?? null
    ];

    // Agricultural details
    $agricultural_data = [
        'grade' => $_POST['grade'] ?? null,
        'is_organic' => isset($_POST['is_organic']) ? 1 : 0,
        'is_gmo' => isset($_POST['is_gmo']) ? 1 : 0,
        'harvest_date' => !empty($_POST['harvest_date']) ? $_POST['harvest_date'] : null,
        'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        'farming_method' => $_POST['farming_method'] ?? 'conventional'
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
        
        for ($i = 0; $i < $total_images; $i++) {
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

            // Generate slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $product_data['name'])));
            
            // Determine status
            $status = isset($_POST['save_draft']) ? 'draft' : 'pending';
            
            // Insert product
            $product_id = $db->insert('products', array_merge($product_data, [
                'seller_id' => $seller_id,
                'slug' => $slug . '-' . time(),
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

            // Handle image uploads
            if (!empty($uploaded_images)) {
                $upload_path = PRODUCT_IMAGE_PATH;
                
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
                $db->insert('product_approvals', [
                    'product_id' => $product_id,
                    'seller_id' => $seller_id,
                    'change_type' => 'create',
                    'status' => 'pending_review'
                ]);
            }

            $db->conn->commit();
            $success = 'Product ' . ($status === 'draft' ? 'saved as draft' : 'submitted for approval') . ' successfully!';
            
            // Redirect if successful
            if ($success) {
                if ($status === 'draft') {
                    header('Location: manage-products.php');
                    exit;
                } else {
                    // Stay on page but show success message
                    $_POST = [];
                }
            }
            
        } catch (Exception $e) {
            $db->conn->rollback();
            $error = 'Error adding product: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$page_title = "Add New Product";
$page_css = "dashboard.css";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Add New Product</h1>
                <a href="manage-products.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Products
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-8">
                        <!-- Basic Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Basic Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="short_description" class="form-label">Short Description</label>
                                    <textarea class="form-control" id="short_description" name="short_description" 
                                              rows="2"><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Full Description *</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="category_id" class="form-label">Category *</label>
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
                                            <label for="product_type" class="form-label">Product Type *</label>
                                            <select class="form-select" id="product_type" name="product_type" required>
                                                <option value="">Select Type</option>
                                                <option value="crop" <?php echo ($_POST['product_type'] ?? '') == 'crop' ? 'selected' : ''; ?>>Crop</option>
                                                <option value="livestock" <?php echo ($_POST['product_type'] ?? '') == 'livestock' ? 'selected' : ''; ?>>Livestock</option>
                                                <option value="poultry" <?php echo ($_POST['product_type'] ?? '') == 'poultry' ? 'selected' : ''; ?>>Poultry</option>
                                                <option value="dairy" <?php echo ($_POST['product_type'] ?? '') == 'dairy' ? 'selected' : ''; ?>>Dairy</option>
                                                <option value="equipment" <?php echo ($_POST['product_type'] ?? '') == 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                                                <option value="inputs" <?php echo ($_POST['product_type'] ?? '') == 'inputs' ? 'selected' : ''; ?>>Inputs</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="variety" class="form-label">Variety/Breed</label>
                                    <input type="text" class="form-control" id="variety" name="variety" 
                                           value="<?php echo htmlspecialchars($_POST['variety'] ?? ''); ?>" 
                                           placeholder="e.g., Ofada Rice, Local Chicken">
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Inventory -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Pricing & Inventory</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="price_per_unit" class="form-label">Price per Unit (₦) *</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="price_per_unit" 
                                                   name="price_per_unit" value="<?php echo $_POST['price_per_unit'] ?? ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="unit" class="form-label">Unit *</label>
                                            <select class="form-select" id="unit" name="unit" required>
                                                <option value="">Select Unit</option>
                                                <option value="kg" <?php echo ($_POST['unit'] ?? '') == 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                                <option value="g" <?php echo ($_POST['unit'] ?? '') == 'g' ? 'selected' : ''; ?>>Gram (g)</option>
                                                <option value="ton" <?php echo ($_POST['unit'] ?? '') == 'ton' ? 'selected' : ''; ?>>Ton</option>
                                                <option value="bag" <?php echo ($_POST['unit'] ?? '') == 'bag' ? 'selected' : ''; ?>>Bag</option>
                                                <option value="crate" <?php echo ($_POST['unit'] ?? '') == 'crate' ? 'selected' : ''; ?>>Crate</option>
                                                <option value="piece" <?php echo ($_POST['unit'] ?? '') == 'piece' ? 'selected' : ''; ?>>Piece</option>
                                                <option value="dozen" <?php echo ($_POST['unit'] ?? '') == 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                                                <option value="liter" <?php echo ($_POST['unit'] ?? '') == 'liter' ? 'selected' : ''; ?>>Liter</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="unit_quantity" class="form-label">Unit Quantity</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="unit_quantity" 
                                                   name="unit_quantity" value="<?php echo $_POST['unit_quantity'] ?? '1'; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="min_order_quantity" class="form-label">Min Order Qty</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="min_order_quantity" 
                                                   name="min_order_quantity" value="<?php echo $_POST['min_order_quantity'] ?? '1'; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="max_order_quantity" class="form-label">Max Order Qty</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="max_order_quantity" 
                                                   name="max_order_quantity" value="<?php echo $_POST['max_order_quantity'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="stock_quantity" 
                                                   name="stock_quantity" value="<?php echo $_POST['stock_quantity'] ?? '0'; ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agricultural Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Agricultural Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="grade" class="form-label">Grade</label>
                                            <select class="form-select" id="grade" name="grade">
                                                <option value="">Select Grade</option>
                                                <option value="Premium" <?php echo ($_POST['grade'] ?? '') == 'Premium' ? 'selected' : ''; ?>>Premium</option>
                                                <option value="A" <?php echo ($_POST['grade'] ?? '') == 'A' ? 'selected' : ''; ?>>A</option>
                                                <option value="B" <?php echo ($_POST['grade'] ?? '') == 'B' ? 'selected' : ''; ?>>B</option>
                                                <option value="C" <?php echo ($_POST['grade'] ?? '') == 'C' ? 'selected' : ''; ?>>C</option>
                                                <option value="Commercial" <?php echo ($_POST['grade'] ?? '') == 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="farming_method" class="form-label">Farming Method</label>
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
                                            <label for="weight_kg" class="form-label">Weight (kg)</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="weight_kg" 
                                                   name="weight_kg" value="<?php echo $_POST['weight_kg'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="harvest_date" class="form-label">Harvest Date</label>
                                            <input type="date" class="form-control" id="harvest_date" 
                                                   name="harvest_date" value="<?php echo $_POST['harvest_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="expiry_date" class="form-label">Expiry Date</label>
                                            <input type="date" class="form-control" id="expiry_date" 
                                                   name="expiry_date" value="<?php echo $_POST['expiry_date'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="is_organic" 
                                               name="is_organic" value="1" <?php echo isset($_POST['is_organic']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_organic">Organic Product</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="is_gmo" 
                                               name="is_gmo" value="1" <?php echo isset($_POST['is_gmo']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_gmo">GMO Product</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="submit" name="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle"></i> Submit for Approval
                                    </button>
                                    <button type="submit" name="save_draft" value="1" class="btn btn-outline-secondary">
                                        <i class="bi bi-save"></i> Save as Draft
                                    </button>
                                    <a href="manage-products.php" class="btn btn-outline-danger">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Product Images -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Product Images</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="product_images" class="form-label">Upload Images</label>
                                    <input type="file" class="form-control" id="product_images" 
                                           name="product_images[]" multiple accept="image/*">
                                    <div class="form-text">Upload up to 5 images (JPG, PNG, WEBP). Max 5MB each. First image will be the main image.</div>
                                </div>
                                <div id="image-preview" class="mt-3"></div>
                            </div>
                        </div>

                        <!-- Bulk Pricing -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Bulk Pricing</h5>
                            </div>
                            <div class="card-body">
                                <div id="bulk-pricing-container">
                                    <div class="bulk-pricing-item mb-3">
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <input type="number" step="0.01" min="0" class="form-control" 
                                                       name="bulk_min_qty[]" placeholder="Min Qty">
                                            </div>
                                            <div class="col-5">
                                                <input type="number" step="0.01" min="0" class="form-control" 
                                                       name="bulk_price[]" placeholder="Price">
                                            </div>
                                            <div class="col-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-bulk" disabled>
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-12">
                                                <input type="number" step="0.01" min="0" class="form-control" 
                                                       name="bulk_max_qty[]" placeholder="Max Qty (optional)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="add-bulk-pricing" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-plus"></i> Add Bulk Price
                                </button>
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
    newItem.className = 'bulk-pricing-item mb-3';
    newItem.innerHTML = `
        <div class="row g-2">
            <div class="col-5">
                <input type="number" step="0.01" min="0" class="form-control" name="bulk_min_qty[]" placeholder="Min Qty">
            </div>
            <div class="col-5">
                <input type="number" step="0.01" min="0" class="form-control" name="bulk_price[]" placeholder="Price">
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-sm btn-outline-danger remove-bulk">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-12">
                <input type="number" step="0.01" min="0" class="form-control" 
                       name="bulk_max_qty[]" placeholder="Max Qty (optional)">
            </div>
        </div>
    `;
    container.appendChild(newItem);
    
    // Enable remove buttons for all items except first
    const removeButtons = document.querySelectorAll('.remove-bulk');
    if (removeButtons.length > 1) {
        removeButtons.forEach(btn => btn.disabled = false);
    }
    
    // Add click event to new remove button
    newItem.querySelector('.remove-bulk').addEventListener('click', function() {
        this.closest('.bulk-pricing-item').remove();
    });
});

// Image preview
document.getElementById('product_images').addEventListener('change', function(e) {
    const preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    
    Array.from(e.target.files).slice(0, 5).forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'd-inline-block me-2 mb-2 text-center';
                imgContainer.style.width = '120px';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-thumbnail';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                
                const badge = document.createElement('span');
                badge.className = 'badge bg-' + (index === 0 ? 'primary' : 'secondary') + ' mt-1';
                badge.textContent = index === 0 ? 'Main' : 'Image ' + (index + 1);
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(document.createElement('br'));
                imgContainer.appendChild(badge);
                preview.appendChild(imgContainer);
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Show warning if more than 5 files
    if (e.target.files.length > 5) {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning mt-2';
        warning.textContent = 'Only first 5 images will be uploaded';
        preview.appendChild(warning);
    }
});

// Initialize remove button functionality
document.querySelectorAll('.remove-bulk').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.bulk-pricing-item').remove();
    });
});
</script>

<?php include '../../includes/footer.php'; ?>