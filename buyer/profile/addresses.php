<?php
// ============ KEEP YOUR EXACT BACKEND LOGIC (unchanged) ============
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';

requireBuyer();

$db = new Database();
$user_id = getCurrentUserId();

$message = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

function normalizeCityName($city) {
    return preg_replace('/\s+/', ' ', trim($city));
}

function normalizeNigerianPhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 13 && str_starts_with($digits, '234')) {
        return '+' . $digits;
    }
    if (strlen($digits) === 10 && preg_match('/^[789]/', $digits)) {
        return '0' . $digits;
    }
    return $digits;
}

function getAddressFormData($db) {
    $allowed_types = ['home', 'business', 'billing', 'shipping'];
    $address_type = $_POST['address_type'] ?? 'home';
    $state_id = (int)($_POST['state_id'] ?? 0);
    $lga_id = (int)($_POST['lga_id'] ?? 0);
    $city = normalizeCityName($_POST['city'] ?? '');
    if (!in_array($address_type, $allowed_types, true)) $address_type = 'home';
    $data = [
        'address_type' => $address_type, 'address_label' => trim($_POST['address_label'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''), 'phone' => normalizeNigerianPhone($_POST['phone'] ?? ''),
        'state_id' => $state_id, 'lga_id' => $lga_id, 'city' => $city,
        'address_line' => trim($_POST['address_line'] ?? ''), 'landmark' => trim($_POST['landmark'] ?? ''),
        'is_default' => isset($_POST['is_default']) ? 1 : 0
    ];
    if (empty($data['address_label']) || empty($data['contact_person']) || empty($data['phone']) ||
        empty($data['state_id']) || empty($data['lga_id']) || empty($data['city']) || empty($data['address_line'])) {
        throw new Exception('Please fill in all required fields');
    }
    if (!preg_match('/^(0[0-9]{10}|\+234[0-9]{10})$/', $data['phone'])) {
        throw new Exception('Invalid Nigerian phone number');
    }
    $valid_location = $db->fetchOne("SELECT l.id FROM lgas l JOIN states s ON l.state_id = s.id WHERE s.id = ? AND l.id = ? AND s.is_active = TRUE", [$data['state_id'], $data['lga_id']]);
    if (!$valid_location) throw new Exception('Please select a valid state and LGA');
    return $data;
}

$addresses = $db->fetchAll("SELECT ua.*, s.name as state_name, l.name as lga_name FROM user_addresses ua JOIN states s ON ua.state_id = s.id JOIN lgas l ON ua.lga_id = l.id WHERE ua.user_id = ? ORDER BY ua.is_default DESC, ua.created_at DESC", [$user_id]);
$states = $db->fetchAll("SELECT id, name FROM states WHERE is_active = TRUE ORDER BY name");
$lgas = $db->fetchAll("SELECT id, state_id, name FROM lgas ORDER BY name");
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Form handling (same as original, no changes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die('Invalid CSRF token');
    if (isset($_POST['action']) && $_POST['action'] === 'add_address') {
        try {
            $address_data = getAddressFormData($db);
            $existingDefault = $db->fetchOne("SELECT id FROM user_addresses WHERE user_id = ? AND is_default = TRUE", [$user_id]);

            if (!$existingDefault) $address_data['is_default'] = 1;
            if ($address_data['is_default']) $db->query("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?", [$user_id]);
            
            $address_data['user_id'] = $user_id;
            $inserted = $db->insert('user_addresses', $address_data);

            if (!$inserted) throw new Exception('Could not add address. Please try again.');
            $_SESSION['success'] = 'Address added successfully';
        } catch (Exception $e) { $_SESSION['error'] = $e->getMessage(); }
        header('Location: addresses.php'); exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_address') {
        $address_id = (int)$_POST['address_id'];
        try {
            $address = $db->fetchOne("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?", [$address_id, $user_id]);
            if (!$address) throw new Exception('Address not found');
            $address_data = getAddressFormData($db);
            $existingDefault = $db->fetchOne("SELECT id FROM user_addresses WHERE user_id = ? AND is_default = TRUE AND id <> ?", [$user_id, $address_id]);
            if (!$existingDefault) $address_data['is_default'] = 1;
            if ($address_data['is_default']) $db->query("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ? AND id <> ?", [$user_id, $address_id]);
            $updated = $db->update('user_addresses', $address_data, 'id = ? AND user_id = ?', [$address_id, $user_id]);
            if ($updated === false) throw new Exception('Could not update address');
            $_SESSION['success'] = 'Address updated successfully';
        } catch (Exception $e) { $_SESSION['error'] = $e->getMessage(); }
        header('Location: addresses.php'); exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'set_default') {
        $address_id = (int)($_POST['address_id'] ?? 0);
        $db->query("UPDATE user_addresses SET is_default = FALSE WHERE user_id = ?", [$user_id]);
        $db->query("UPDATE user_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?", [$address_id, $user_id]);
        $_SESSION['success'] = 'Default address updated';
        header('Location: addresses.php'); exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $address_id = (int)($_POST['address_id'] ?? 0);
        $address = $db->fetchOne("SELECT id, is_default FROM user_addresses WHERE id = ? AND user_id = ?", [$address_id, $user_id]);
        if (!$address) $_SESSION['error'] = 'Address not found';
        else {
            $db->query("DELETE FROM user_addresses WHERE id = ? AND user_id = ?", [$address_id, $user_id]);
            if ($address['is_default']) {
                $next_address = $db->fetchOne("SELECT id FROM user_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", [$user_id]);
                if ($next_address) $db->query("UPDATE user_addresses SET is_default = TRUE WHERE id = ? AND user_id = ?", [$next_address['id'], $user_id]);
            }
            $_SESSION['success'] = 'Address deleted';
        }
        header('Location: addresses.php'); exit;
    }
}
$checkout_redirect = isset($_GET['checkout']);
$page_title = "My Addresses";
?>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<!-- Responsive container with fluid padding -->
<div class="container py-3 py-md-4 px-3 px-md-4">
    <div class="row g-4">
        <!-- Sidebar - full width on mobile, 3 on desktop -->
        <div class="col-12 col-md-4 col-lg-3">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden sticky-md-top" style="top: 20px;">
                <div class="card-body p-3 p-md-4 text-center text-md-start">
                    <div class="d-flex d-md-block align-items-center gap-3 flex-md-column">
                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mx-auto mb-md-3">
                            <i class="bi bi-geo-alt-fill text-success" style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1">Address Book</h5>
                            <p class="text-muted small">Manage delivery locations</p>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="list-group list-group-flush">
                        <a href="personal-info.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 ps-0 py-2">
                            <i class="bi bi-person fs-5"></i> Personal Info
                        </a>
                        <a href="addresses.php" class="list-group-item list-group-item-action active bg-success bg-opacity-10 text-success fw-semibold border-0 ps-0 py-2 d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt-fill fs-5"></i> Addresses
                        </a>
                        <!--
                        <a href="payment-methods.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 ps-0 py-2">
                            <i class="bi bi-credit-card fs-5"></i> Payment Methods
                        </a>
                        -->
                        <a href="../dashboard.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 ps-0 py-2 mt-2 text-muted">
                            <i class="bi bi-arrow-left-short fs-5"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content - full width on small screens -->
        <div class="col-12 col-md-8 col-lg-9">
            <?php if ($checkout_redirect): ?>
                <div class="alert alert-info alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i> Please add or select a shipping address to continue checkout.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert"><i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Add Address Card (Fully responsive) -->
            <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-plus-circle me-2"></i> Add New Address</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_address">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Address Label <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="address_label" placeholder="e.g., Home, Office" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Address Type</label>
                                <select class="form-select" name="address_type">
                                    <option value="home">🏠 Home</option><option value="business">🏢 Business</option>
                                    <option value="shipping">📦 Shipping</option><option value="billing">💰 Billing</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="contact_person" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="phone" placeholder="0803 123 4567" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">State <span class="text-danger">*</span></label>
                                <select class="form-select" id="state_id" name="state_id" required>
                                    <option value="">-- Select State --</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">LGA <span class="text-danger">*</span></label>
                                <select class="form-select" id="lga_id" name="lga_id" required disabled>
                                    <option value="">First select state</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">City/Town <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="city" placeholder="City" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address Line <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="address_line" rows="2" placeholder="Street, building, apartment" required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Landmark (optional)</label>
                                <input type="text" class="form-control" name="landmark" placeholder="Near the bus stop, landmark">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default">
                                    <label class="form-check-label" for="is_default">Set as default shipping address</label>
                                </div>
                            </div>
                            <div class="col-12 mt-2">
                                <button type="submit" class="btn btn-success w-100 w-md-auto px-4 py-2 fw-semibold rounded-pill"><i class="bi bi-save me-2"></i> Add Address</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Saved Addresses Grid (responsive columns) -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bookmark-check me-2 text-success"></i> Saved Addresses</h5>
                </div>
                <div class="card-body p-3 p-md-0">
                    <?php if (empty($addresses)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-geo-alt-slash text-muted" style="font-size: 4rem;"></i>
                            <h5 class="mt-3">No addresses yet</h5>
                            <p class="text-muted">Add your first delivery address using the form above.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($addresses as $address): ?>
                                <div class="col-12 col-md-6 col-xl-6">
                                    <div class="card address-card h-100 border rounded-4 shadow-sm <?php echo $address['is_default'] ? 'border-success border-2 shadow' : ''; ?>">
                                        <div class="card-body p-3 p-md-0">
                                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 address-header">
                                                <div>
                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($address['address_label']); ?></h6>
                                                    <div class="badge-group">
                                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1"><?php echo ucfirst(htmlspecialchars($address['address_type'])); ?></span>
                                                        <?php if ($address['is_default']): ?>
                                                            <span class="badge bg-success rounded-pill px-3 py-1 ms-1"><i class="bi bi-star-fill me-1"></i>Default</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="btn-group-sm-responsive d-flex gap-2 address-actions">
                                                    <?php if (!$address['is_default']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="action" value="set_default">
                                                            <input type="hidden" name="address_id" value="<?php echo (int)$address['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-success rounded-pill px-3 py-1" title="Set as default"><i class="bi bi-check-lg"></i> Default</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-1" data-bs-toggle="modal" data-bs-target="#editAddressModal<?php echo (int)$address['id']; ?>" title="Edit"><i class="bi bi-pencil-square"></i> Edit</button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this address?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="address_id" value="<?php echo (int)$address['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger rounded-pill px-3 py-1" title="Delete"><i class="bi bi-trash3"></i> Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                            <p class="mb-2 small fw-semibold"><i class="bi bi-person-badge me-1"></i> <?php echo htmlspecialchars($address['contact_person']); ?> | <i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($address['phone']); ?></p>
                                            <p class="mb-1 text-secondary small"><i class="bi bi-geo-alt me-1"></i> <?php echo nl2br(htmlspecialchars($address['address_line'])); ?><br><?php echo htmlspecialchars($address['city'] . ', ' . $address['lga_name'] . ', ' . $address['state_name']); ?></p>
                                            <?php if (!empty($address['landmark'])): ?>
                                                <p class="mt-2 mb-0 text-muted small"><i class="bi bi-signpost-split me-1"></i> Landmark: <?php echo htmlspecialchars($address['landmark']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Modal (fully responsive) -->
                                <div class="modal fade" id="editAddressModal<?php echo (int)$address['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content rounded-4 border-0 shadow-lg">
                                            <form method="POST">
                                                <div class="modal-header bg-light border-0">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-success"></i>Edit Address</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="address_id" value="<?php echo (int)$address['id']; ?>">
                                                    <input type="hidden" name="action" value="update_address">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-md-6"><label class="form-label">Address Label *</label><input type="text" class="form-control" name="address_label" value="<?php echo htmlspecialchars($address['address_label']); ?>" required></div>
                                                        <div class="col-12 col-md-6"><label class="form-label">Address Type</label><select class="form-select" name="address_type"><?php foreach (['home'=>'Home','business'=>'Business','shipping'=>'Shipping','billing'=>'Billing'] as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo ($address['address_type']??'home')==$k?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></div>
                                                        <div class="col-12 col-md-6"><label class="form-label">Contact Person *</label><input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($address['contact_person']); ?>" required></div>
                                                        <div class="col-12 col-md-6"><label class="form-label">Phone *</label><input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($address['phone']); ?>" required></div>
                                                        <div class="col-12 col-md-4"><label class="form-label">State *</label><select class="form-select js-state-select" name="state_id" data-lga-target="edit_lga_<?php echo (int)$address['id']; ?>" required><?php foreach ($states as $st): ?><option value="<?php echo $st['id']; ?>" <?php echo $st['id']==$address['state_id']?'selected':''; ?>><?php echo htmlspecialchars($st['name']); ?></option><?php endforeach; ?></select></div>
                                                        <div class="col-12 col-md-4"><label class="form-label">LGA *</label><select class="form-select" id="edit_lga_<?php echo (int)$address['id']; ?>" name="lga_id" required><?php foreach ($lgas as $lga): if($lga['state_id']==$address['state_id']): ?><option value="<?php echo $lga['id']; ?>" <?php echo $lga['id']==$address['lga_id']?'selected':''; ?>><?php echo htmlspecialchars($lga['name']); ?></option><?php endif; endforeach; ?></select></div>
                                                        <div class="col-12 col-md-4"><label class="form-label">City *</label><input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($address['city']); ?>" required></div>
                                                        <div class="col-12"><label class="form-label">Address Line *</label><textarea class="form-control" name="address_line" rows="2" required><?php echo htmlspecialchars($address['address_line']); ?></textarea></div>
                                                        <div class="col-12"><label class="form-label">Landmark</label><input class="form-control" name="landmark" value="<?php echo htmlspecialchars($address['landmark']); ?>"></div>
                                                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" id="edit_default_<?php echo (int)$address['id']; ?>" <?php echo $address['is_default']?'checked':''; ?>><label class="form-check-label" for="edit_default_<?php echo (int)$address['id']; ?>">Set as default shipping address</label></div></div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 bg-light">
                                                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success rounded-pill px-5">Save changes</button>
                                                </div>
                                            </form>
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
    // LGA data and dynamic loader (fully preserved)
    const lgaData = <?php echo json_encode($lgas, JSON_HEX_TAG); ?>;
    function loadLgas(stateId, lgaSelect, selectedLgaId = '') {
        lgaSelect.innerHTML = '<option value="">-- Select LGA --</option>';
        if (!stateId) { lgaSelect.disabled = true; return; }
        lgaData.filter(lga => String(lga.state_id) === String(stateId)).forEach(lga => {
            const opt = document.createElement('option');
            opt.value = lga.id;
            opt.textContent = lga.name;
            if (String(lga.id) === String(selectedLgaId)) opt.selected = true;
            lgaSelect.appendChild(opt);
        });
        lgaSelect.disabled = false;
    }
    document.getElementById('state_id')?.addEventListener('change', function() {
        loadLgas(this.value, document.getElementById('lga_id'), '');
    });
    document.querySelectorAll('.js-state-select').forEach(sel => {
        sel.addEventListener('change', function() {
            const targetId = this.dataset.lgaTarget;
            if(targetId) loadLgas(this.value, document.getElementById(targetId), '');
        });
    });
    // initialize any pre-selected state on edit modals after page load
    document.querySelectorAll('.js-state-select').forEach(sel => {
        const targetId = sel.dataset.lgaTarget;
        if(targetId && sel.value) loadLgas(sel.value, document.getElementById(targetId), '<?php echo isset($address) ? (int)$address['lga_id'] : ''; ?>');
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>