<?php
require_once '../../includes/auth.php';
requireAdmin();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = new Database();

// Get counts for stats
$stats = [
    'total_states' => $db->fetchOne("SELECT COUNT(*) as count FROM states")['count'],
    'total_lgas' => $db->fetchOne("SELECT COUNT(*) as count FROM lgas")['count'],
    'total_cities' => $db->fetchOne("SELECT COUNT(*) as count FROM cities")['count'],
    'north_states' => $db->fetchOne("SELECT COUNT(*) as count FROM states WHERE region IN ('North Central', 'North East', 'North West')")['count'],
    'south_states' => $db->fetchOne("SELECT COUNT(*) as count FROM states WHERE region IN ('South East', 'South South', 'South West')")['count']
];

// Handle location actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_state'])) {
        $name = trim($_POST['name']);
        $code = trim($_POST['code']);
        $region = $_POST['region'];
        
        if (!empty($name) && !empty($code)) {
            $db->query(
                "INSERT INTO states (name, code, region) VALUES (?, ?, ?)",
                [$name, $code, $region]
            );
            setFlashMessage('State added successfully', 'success');
        }
    }
    
    if (isset($_POST['add_lga'])) {
        $state_id = (int)$_POST['state_id'];
        $name = trim($_POST['name']);
        
        if (!empty($name) && $state_id > 0) {
            $db->query(
                "INSERT INTO lgas (state_id, name) VALUES (?, ?)",
                [$state_id, $name]
            );
            setFlashMessage('LGA added successfully', 'success');
        }
    }
    
    if (isset($_POST['add_city'])) {
        $state_id = (int)$_POST['state_id'];
        $lga_id = (int)$_POST['lga_id'];
        $name = trim($_POST['name']);
        
        if (!empty($name) && $state_id > 0 && $lga_id > 0) {
            $db->query(
                "INSERT INTO cities (state_id, lga_id, name) VALUES (?, ?, ?)",
                [$state_id, $lga_id, $name]
            );
            setFlashMessage('City added successfully', 'success');
        }
    }
    
    header('Location: location-management.php');
    exit;
}

// Get all locations
$states = $db->fetchAll("SELECT * FROM states ORDER BY name");
$lgas = $db->fetchAll("
    SELECT l.*, s.name as state_name 
    FROM lgas l 
    JOIN states s ON l.state_id = s.id 
    ORDER BY s.name, l.name
");
$cities = $db->fetchAll("
    SELECT c.*, s.name as state_name, l.name as lga_name 
    FROM cities c 
    JOIN states s ON c.state_id = s.id 
    JOIN lgas l ON c.lga_id = l.id 
    ORDER BY s.name, l.name, c.name
");

$page_title = "Location Management";
$page_css = 'dashboard.css';
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
                        <h1 class="h5 mb-0 text-center">Location Management</h1>
                        <small class="text-muted d-block text-center">
                            <?php echo $stats['total_states']; ?> states, <?php echo $stats['total_lgas']; ?> LGAs
                        </small>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="mobileRefreshLocations">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            
            <!-- Desktop page header -->
            <div class="d-none d-md-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Location Management</h1>
                    <p class="text-muted mb-0">Manage Nigerian states, LGAs, and cities</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportLocations()">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshLocations">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <!-- Total States -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">States</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_states']); ?></h3>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-geo-alt fs-5 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total LGAs -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Local Governments</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_lgas']); ?></h3>
                                </div>
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-building fs-5 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Cities -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Cities/Towns</h6>
                                    <h3 class="card-title mb-0"><?php echo number_format($stats['total_cities']); ?></h3>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-house fs-5 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Regions -->
                <div class="col-6 col-md-3">
                    <div class="dashboard-card card border-start shadow-sm h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle text-muted mb-1">Regions</h6>
                                    <h3 class="card-title mb-0">6</h3>
                                    <small class="text-muted">
                                        <?php echo $stats['north_states']; ?> North, <?php echo $stats['south_states']; ?> South
                                    </small>
                                </div>
                                <div class="bg-info bg-opacity-10 p-2 rounded">
                                    <i class="bi bi-globe fs-5 text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Locations Forms -->
            <div class="row mb-4">
                <!-- Add State Form -->
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-plus-circle me-2 text-primary"></i>
                                Add State
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <div class="col-12">
                                    <label for="state_name" class="form-label">State Name</label>
                                    <input type="text" class="form-control" id="state_name" name="name" 
                                           placeholder="e.g., Lagos" required>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <label for="state_code" class="form-label">State Code</label>
                                    <input type="text" class="form-control" id="state_code" name="code" 
                                           maxlength="5" placeholder="e.g., LA" required>
                                </div>
                                
                                <div class="col-12 col-md-6">
                                    <label for="region" class="form-label">Region</label>
                                    <select class="form-select" id="region" name="region" required>
                                        <option value="">Select Region</option>
                                        <option value="North Central">North Central</option>
                                        <option value="North East">North East</option>
                                        <option value="North West">North West</option>
                                        <option value="South East">South East</option>
                                        <option value="South South">South South</option>
                                        <option value="South West">South West</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <button name="add_state" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle me-1"></i> Add State
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Add LGA Form -->
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-plus-circle me-2 text-success"></i>
                                Add LGA
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <div class="col-12">
                                    <label for="lga_state" class="form-label">State</label>
                                    <select class="form-select" id="lga_state" name="state_id" required>
                                        <option value="">Select State</option>
                                        <?php foreach ($states as $state): ?>
                                            <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label for="lga_name" class="form-label">Local Government Name</label>
                                    <input type="text" class="form-control" id="lga_name" name="name" 
                                           placeholder="e.g., Ikeja" required>
                                </div>
                                
                                <div class="col-12">
                                    <button name="add_lga" class="btn btn-success w-100">
                                        <i class="bi bi-plus-circle me-1"></i> Add LGA
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Add City Form -->
                <div class="col-md-12 col-lg-4 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0 pb-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-plus-circle me-2 text-warning"></i>
                                Add City/Town
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <div class="col-12">
                                    <label for="city_state" class="form-label">State</label>
                                    <select class="form-select" id="city_state" name="state_id" required>
                                        <option value="">Select State</option>
                                        <?php foreach ($states as $state): ?>
                                            <option value="<?php echo $state['id']; ?>"><?php echo htmlspecialchars($state['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label for="city_lga" class="form-label">Local Government</label>
                                    <select class="form-select" id="city_lga" name="lga_id" required>
                                        <option value="">Select LGA</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label for="city_name" class="form-label">City/Town Name</label>
                                    <input type="text" class="form-control" id="city_name" name="name" 
                                           placeholder="e.g., Victoria Island" required>
                                </div>
                                
                                <div class="col-12">
                                    <button name="add_city" class="btn btn-warning w-100">
                                        <i class="bi bi-plus-circle me-1"></i> Add City
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Lists - Mobile Accordion -->
            <div class="d-md-none mb-4">
                <div class="accordion" id="locationAccordion">
                    <!-- States Accordion Item -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapseStates" aria-expanded="true">
                                <i class="bi bi-geo-alt me-2 text-primary"></i>
                                States (<?php echo count($states); ?>)
                            </button>
                        </h2>
                        <div id="collapseStates" class="accordion-collapse collapse show" 
                             data-bs-parent="#locationAccordion">
                            <div class="accordion-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($states as $state): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($state['name']); ?></h6>
                                                    <small class="text-muted">Code: <?php echo htmlspecialchars($state['code']); ?></small>
                                                </div>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($state['region']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- LGAs Accordion Item -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapseLgas" aria-expanded="false">
                                <i class="bi bi-building me-2 text-success"></i>
                                Local Governments (<?php echo count($lgas); ?>)
                            </button>
                        </h2>
                        <div id="collapseLgas" class="accordion-collapse collapse" 
                             data-bs-parent="#locationAccordion">
                            <div class="accordion-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($lgas as $lga): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($lga['name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($lga['state_name']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cities Accordion Item -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapseCities" aria-expanded="false">
                                <i class="bi bi-house me-2 text-warning"></i>
                                Cities/Towns (<?php echo count($cities); ?>)
                            </button>
                        </h2>
                        <div id="collapseCities" class="accordion-collapse collapse" 
                             data-bs-parent="#locationAccordion">
                            <div class="accordion-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($cities as $city): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($city['name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($city['lga_name']); ?>, 
                                                        <?php echo htmlspecialchars($city['state_name']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location Lists - Desktop Tables -->
            <div class="d-none d-md-block">
                <div class="row">
                    <!-- States Table -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-geo-alt me-2 text-primary"></i>
                                    States (<?php echo count($states); ?>)
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Code</th>
                                                <th>Region</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($states as $state): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-2">
                                                            <i class="bi bi-geo-alt text-primary"></i>
                                                        </div>
                                                        <strong><?php echo htmlspecialchars($state['name']); ?></strong>
                                                    </div>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($state['code']); ?></code></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($state['region']); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LGAs Table -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-building me-2 text-success"></i>
                                    Local Governments (<?php echo count($lgas); ?>)
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>LGA Name</th>
                                                <th>State</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lgas as $lga): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-success bg-opacity-10 p-2 rounded-circle me-2">
                                                            <i class="bi bi-building text-success"></i>
                                                        </div>
                                                        <?php echo htmlspecialchars($lga['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($lga['state_name']); ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cities Table -->
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white border-0 pb-2">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-house me-2 text-warning"></i>
                                    Cities/Towns (<?php echo count($cities); ?>)
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>City/Town</th>
                                                <th>LGA</th>
                                                <th>State</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cities as $city): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-warning bg-opacity-10 p-2 rounded-circle me-2">
                                                            <i class="bi bi-house text-warning"></i>
                                                        </div>
                                                        <?php echo htmlspecialchars($city['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($city['lga_name']); ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($city['state_name']); ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
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
    
    // Refresh locations
    const refreshBtn = document.getElementById('refreshLocations');
    const mobileRefreshBtn = document.getElementById('mobileRefreshLocations');
    
    function refreshPage() {
        const btn = event?.target?.closest('button');
        if (btn) {
            btn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';
            btn.disabled = true;
        }
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
    
    if (refreshBtn) refreshBtn.addEventListener('click', refreshPage);
    if (mobileRefreshBtn) mobileRefreshBtn.addEventListener('click', refreshPage);
    
    // Dynamic LGA loading based on state selection
    const cityStateSelect = document.getElementById('city_state');
    const cityLgaSelect = document.getElementById('city_lga');
    const lgaStateSelect = document.getElementById('lga_state');
    
    // Preload LGAs data from PHP
    const lgasData = <?php echo json_encode($lgas); ?>;
    
    function updateLGAs(stateId, targetSelect) {
        targetSelect.innerHTML = '<option value="">Select LGA</option>';
        
        if (stateId) {
            const stateLgas = lgasData.filter(lga => lga.state_id == stateId);
            stateLgas.forEach(lga => {
                const option = document.createElement('option');
                option.value = lga.id;
                option.textContent = lga.name;
                targetSelect.appendChild(option);
            });
        }
    }
    
    if (cityStateSelect) {
        cityStateSelect.addEventListener('change', function() {
            updateLGAs(this.value, cityLgaSelect);
        });
    }
    
    // Also update LGAs when LGA form state changes (for consistency)
    if (lgaStateSelect) {
        lgaStateSelect.addEventListener('change', function() {
            // In a real app, you might want to filter cities by LGA state
        });
    }
    
    // Initialize LGAs if state is already selected
    if (cityStateSelect && cityStateSelect.value) {
        updateLGAs(cityStateSelect.value, cityLgaSelect);
    }
});

function exportLocations() {
    const link = document.createElement('a');
    link.href = 'export-locations.php';
    link.download = 'locations-export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    agriApp.showToast('Export started', 'info');
}

// Add CSS for mobile optimizations
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    
    /* Mobile optimizations */
    @media (max-width: 767.98px) {
        .mobile-page-header {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .accordion-item {
            border: 1px solid #dee2e6;
            margin-bottom: 0.5rem;
            border-radius: 0.375rem;
        }
        
        .accordion-button {
            padding: 1rem;
            font-weight: 500;
        }
        
        .accordion-body {
            padding: 0;
        }
        
        .list-group-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
        }
        
        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-control, .form-select {
            padding: 0.5rem;
        }
    }
    
    /* Desktop optimizations */
    @media (min-width: 768px) {
        .card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>