<?php
// Filters Sidebar Component
?>
<!-- Filters Card -->
<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="bi bi-filter me-2"></i> Filters</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="" id="filter-form">
            <!-- Search -->
            <div class="mb-3">
                <label class="form-label fw-bold small">Search</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" name="search" 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           placeholder="Search products...">
                </div>
            </div>
            
            <!-- Categories -->
            <div class="mb-3">
                <label class="form-label fw-bold small">Category</label>
                <select class="form-select form-select-sm" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                            <?php echo ($_GET['category'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Price Range -->
            <div class="mb-3">
                <label class="form-label fw-bold small">Price Range (₦)</label>
                <div class="row g-2">
                    <div class="col-6">
                        <input type="number" class="form-control form-control-sm" 
                               name="min_price" placeholder="Min" 
                               value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>" min="0">
                    </div>
                    <div class="col-6">
                        <input type="number" class="form-control form-control-sm" 
                               name="max_price" placeholder="Max" 
                               value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>" min="0">
                    </div>
                </div>
            </div>
            
            <!-- Organic filter -->
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="organic" 
                           id="organic" value="1" <?php echo isset($_GET['organic']) ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="organic">
                        Organic Products Only
                    </label>
                </div>
            </div>
            
            <!-- Hidden fields -->
            <?php if (!empty($_GET['sort'])): ?>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="d-grid gap-2">
                <button class="btn btn-success btn-sm">
                    <i class="bi bi-funnel me-1"></i> Apply Filters
                </button>
                <?php if (!empty($_GET)): ?>
                    <a href="?" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Clear All
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Quick Categories -->
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Categories</h6>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <a href="?" class="list-group-item list-group-item-action border-0 <?php echo empty($_GET['category']) ? 'active' : ''; ?>">
                <i class="bi bi-grid me-2"></i> All Categories
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?php echo $cat['id']; ?><?php echo !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo !empty($_GET['sort']) ? '&sort=' . urlencode($_GET['sort']) : ''; ?><?php echo isset($_GET['organic']) ? '&organic=1' : ''; ?>" 
                   class="list-group-item list-group-item-action border-0 <?php echo ($_GET['category'] ?? '') == $cat['id'] ? 'active' : ''; ?>">
                    <i class="bi bi-dot me-2"></i> <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Filter form validation
document.getElementById('filter-form')?.addEventListener('submit', function(e) {
    const minPrice = this.querySelector('input[name="min_price"]').value;
    const maxPrice = this.querySelector('input[name="max_price"]').value;
    
    if (minPrice && maxPrice && parseFloat(minPrice) > parseFloat(maxPrice)) {
        e.preventDefault();
        showToast('Minimum price cannot be greater than maximum price', 'error');
        return false;
    }
});
</script>