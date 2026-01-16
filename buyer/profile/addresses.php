<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

requireBuyer();

$db = new Database();
$user_id = getCurrentUserId();

// Get user addresses
$addresses = $db->fetchAll("
    SELECT ua.*, s.name as state_name, l.name as lga_name, c.name as city_name 
    FROM user_addresses ua 
    JOIN states s ON ua.state_id = s.id 
    JOIN lgas l ON ua.lga_id = l.id 
    JOIN cities c ON ua.city_id = c.id 
    WHERE ua.user_id = ? 
    ORDER BY ua.is_default DESC, ua.created_at DESC
", [$user_id]);

// Get Nigerian states for dropdown
$states = $db->fetchAll("SELECT id, name FROM states WHERE is_active = TRUE ORDER BY name");

$message = '';
$error = '';

// Handle address actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_address'])) {
        $address_type = $_POST['address_type'];
        $address_label = trim($_POST['address_label']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $state_id = $_POST['state_id'];
        $lga_id = $_POST['lga_id'];
        $city_id = $_POST['city_id'];
        $address_line = trim($_POST['address_line']);
        $landmark = trim($_POST['landmark']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // Validation
        if (empty($contact_person) || empty($phone) || empty($state_id) || empty($address_line)) {
            $error = 'Please fill in all required fields';
        } else {
            // If setting as default, update other addresses
            if ($is_default) {
                $db->query("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?", [$user_id]);
            }
            
            // Insert new address
            $db->insert('user_addresses', [
                'user_id' => $user_id,
                'address_type' => $address_type,
                'address_label' => $address_label,
                'contact_person' => $contact_person,
                'phone' => $phone,
                'state_id' => $state_id,
                'lga_id' => $lga_id,
                'city_id' => $city_id,
                'address_line' => $address_line,
                'landmark' => $landmark,
                'is_default' => $is_default
            ]);
            
            $message = 'Address added successfully';
            header('Location: addresses.php');
            exit;
        }
    } elseif (isset($_POST['set_default'])) {
        $address_id = $_POST['set_default'];
        $db->query("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?", [$user_id]);
        $db->query("UPDATE user_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?", [$address_id, $user_id]);
        $message = 'Default address updated';
        header('Location: addresses.php');
        exit;
    } elseif (isset($_POST['delete'])) {
        $address_id = $_POST['delete'];
        $db->query("DELETE FROM user_addresses WHERE id = ? AND user_id = ?", [$address_id, $user_id]);
        $message = 'Address deleted';
        header('Location: addresses.php');
        exit;
    }
}

// Check if redirected from checkout
$checkout_redirect = isset($_GET['checkout']);
?>
<?php 
$page_title = "My Addresses";
include '../../includes/header.php'; 
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="bg-success rounded-circle d-inline-flex p-3 mb-2">
                            <i class="bi bi-geo-alt text-white" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="card-title">Addresses</h5>
                        <p class="text-muted">Manage your delivery addresses</p>
                    </div>
                    
                    <div class="list-group">
                        <a href="personal-info.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person me-2"></i> Personal Info
                        </a>
                        <a href="addresses.php" class="list-group-item list-group-item-action active">
                            <i class="bi bi-geo-alt me-2"></i> Addresses
                        </a>
                        <a href="payment-methods.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-credit-card me-2"></i> Payment Methods
                        </a>
                        <a href="../dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php if ($checkout_redirect): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Please add or select a shipping address to complete your checkout.
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Add New Address Form -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Add New Address</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="address_label" class="form-label">Address Label</label>
                                <input type="text" class="form-control" id="address_label" name="address_label" 
                                       placeholder="e.g., Home, Office" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address_type" class="form-label">Address Type</label>
                                <select class="form-select" id="address_type" name="address_type">
                                    <option value="home">Home</option>
                                    <option value="business">Business</option>
                                    <option value="shipping">Shipping</option>
                                    <option value="billing">Billing</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_person" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="state_id" class="form-label">State *</label>
                                <select class="form-select" id="state_id" name="state_id" required>
                                    <option value="">Select State</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="lga_id" class="form-label">LGA *</label>
                                <select class="form-select" id="lga_id" name="lga_id" required disabled>
                                    <option value="">Select LGA</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="city_id" class="form-label">City *</label>
                                <select class="form-select" id="city_id" name="city_id" required disabled>
                                    <option value="">Select City</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address_line" class="form-label">Address Line *</label>
                            <textarea class="form-control" id="address_line" name="address_line" rows="2" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="landmark" class="form-label">Landmark</label>
                            <input type="text" class="form-control" id="landmark" name="landmark" 
                                   placeholder="e.g., Near the market">
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_default" name="is_default">
                            <label class="form-check-label" for="is_default">
                                Set as default shipping address
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button name="add_address" class="btn btn-success">
                                <i class="bi bi-plus-circle me-1"></i> Add Address
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Saved Addresses -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Saved Addresses</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($addresses)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-geo-alt text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No addresses saved</h5>
                            <p class="text-muted">Add your first address above</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($addresses as $address): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border h-100 <?php echo $address['is_default'] ? 'border-success' : ''; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($address['address_label']); ?></h6>
                                                    <span class="badge bg-<?php 
                                                        echo $address['address_type'] === 'home' ? 'primary' : 
                                                             ($address['address_type'] === 'business' ? 'info' : 'secondary');
                                                    ?>">
                                                        <?php echo ucfirst($address['address_type']); ?>
                                                    </span>
                                                    <?php if ($address['is_default']): ?>
                                                        <span class="badge bg-success ms-2">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                            data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if (!$address['is_default']): ?>
                                                            <li>
                                                                <form method="POST" action="">
                                                                    <input type="hidden" name="set_default" value="<?php echo $address['id']; ?>">
                                                                    <button class="dropdown-item">
                                                                        <i class="bi bi-check-circle me-2"></i> Set as Default
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <form method="POST" action="" onsubmit="return confirm('Delete this address?')">
                                                                <input type="hidden" name="delete" value="<?php echo $address['id']; ?>">
                                                                <button class="dropdown-item text-danger">
                                                                    <i class="bi bi-trash me-2"></i> Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <p class="mb-1">
                                                <strong>Contact:</strong> <?php echo htmlspecialchars($address['contact_person'] . ' - ' . $address['phone']); ?>
                                            </p>
                                            <p class="mb-1 text-muted">
                                                <?php echo htmlspecialchars($address['address_line']); ?><br>
                                                <?php echo htmlspecialchars($address['city_name'] . ', ' . $address['lga_name'] . ', ' . $address['state_name']); ?>
                                            </p>
                                            <?php if ($address['landmark']): ?>
                                                <p class="mb-0 text-muted">
                                                    <strong>Landmark:</strong> <?php echo htmlspecialchars($address['landmark']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if ($checkout_redirect): ?>
                                                <div class="mt-3">
                                                    <a href="../cart/checkout.php?address=<?php echo $address['id']; ?>" 
                                                       class="btn btn-sm btn-success w-100">
                                                        Use This Address
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load LGAs based on selected state
document.getElementById('state_id').addEventListener('change', function() {
    const stateId = this.value;
    const lgaSelect = document.getElementById('lga_id');
    const citySelect = document.getElementById('city_id');
    
    if (!stateId) {
        lgaSelect.disabled = true;
        citySelect.disabled = true;
        lgaSelect.innerHTML = '<option value="">Select LGA</option>';
        citySelect.innerHTML = '<option value="">Select City</option>';
        return;
    }
    
    // Fetch LGAs for this state
    fetch(`../../api/locations/lgas.php?state_id=${stateId}`)
        .then(response => response.json())
        .then(result => {
            console.log('LGAs API response:', result); // Debug log
            
            // Check if the request was successful
            if (result.success && result.lgas) {
                lgaSelect.innerHTML = '<option value="">Select LGA</option>';
                
                // Loop through the lgas array in the response
                result.lgas.forEach(lga => {
                    lgaSelect.innerHTML += `<option value="${lga.id}">${lga.name}</option>`;
                });
                
                lgaSelect.disabled = false;
            } else {
                // Handle error case
                console.error('Failed to load LGAs:', result.error || 'Unknown error');
                lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
            }
            
            citySelect.disabled = true;
            citySelect.innerHTML = '<option value="">Select City</option>';
        })
        .catch(err => {
            console.error("Error loading LGAs:", err);
            lgaSelect.innerHTML = '<option value="">Network error</option>';
        });
});

// Load Cities based on selected LGA
document.getElementById('lga_id').addEventListener('change', function() {
    const lgaId = this.value;
    const citySelect = document.getElementById('city_id');
    
    if (!lgaId) {
        citySelect.disabled = true;
        citySelect.innerHTML = '<option value="">Select City</option>';
        return;
    }
    
    // Fetch Cities for this LGA
    fetch(`../../api/locations/cities.php?lga_id=${lgaId}`)
        .then(response => response.json())
        .then(result => {
            console.log('Cities API response:', result); // Debug log
            
            // Check if the request was successful and has cities data
            // Adjust this based on your actual cities.php API response structure
            let cities = [];
            
            if (result.success && result.cities) {
                cities = result.cities;
            } else if (Array.isArray(result)) {
                // If API returns array directly
                cities = result;
            } else if (result.data && Array.isArray(result.data)) {
                cities = result.data;
            }
            
            citySelect.innerHTML = '<option value="">Select City</option>';
            cities.forEach(city => {
                citySelect.innerHTML += `<option value="${city.id}">${city.name}</option>`;
            });
            citySelect.disabled = false;
        })
        .catch(err => {
            console.error("Error loading cities:", err);
            citySelect.innerHTML = '<option value="">Network error</option>';
        });
});
</script>

<?php include '../../includes/footer.php'; ?>