<?php
// =============================================
// SECURITY & CONFIGURATION
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true
    ]);
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';

$db = new Database();

// Get all categories with product counts
$categories = $db->fetchAll("
    SELECT c.*, 
           COUNT(p.id) as product_count,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'approved') as active_products
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'approved'
    WHERE c.is_active = TRUE 
    GROUP BY c.id 
    ORDER BY c.sort_order, c.name
");

// Build category hierarchy
$category_tree = [];
$category_map = [];

foreach ($categories as $cat) {
    $category_map[$cat['id']] = $cat;
    $cat['children'] = [];
}

foreach ($categories as $cat) {
    if ($cat['parent_id'] === null) {
        $category_tree[] = $cat;
    } else {
        if (isset($category_map[$cat['parent_id']])) {
            $category_map[$cat['parent_id']]['children'][] = $cat;
        }
    }
}

// Get featured categories (top-level with most products)
$featured_categories = array_filter($category_tree, function($cat) {
    return $cat['product_count'] > 0;
});

// Sort featured by product count
usort($featured_categories, function($a, $b) {
    return $b['product_count'] - $a['product_count'];
});

// Limit featured to top 6
$featured_categories = array_slice($featured_categories, 0, 6);

// Category icons mapping
$category_icons = [
    'Grains & Cereals' => 'bi-basket',
    'Vegetables' => 'bi-flower1',
    'Fruits' => 'bi-apple',
    'Livestock' => 'bi-egg',
    'Dairy' => 'bi-cup',
    'Equipment' => 'bi-tools',
    'Seeds' => 'bi-seedling',
    'Fertilizers' => 'bi-droplet',
    'Pesticides' => 'bi-shield',
    'Organic' => 'bi-leaf',
    'Tubers' => 'bi-crop',
    'Legumes' => 'bi-bean',
    'Spices' => 'bi-pepper',
    'Poultry' => 'bi-egg-fried',
    'Fish' => 'bi-fish',
    'Honey' => 'bi-droplet',
    'Herbs' => 'bi-tree',
    'Nuts' => 'bi-nut',
    'Coffee' => 'bi-cup-hot',
    'Cocoa' => 'bi-chocolate',
    'Palm Oil' => 'bi-droplet-half',
    'Rubber' => 'bi-tree',
    'Timber' => 'bi-tree'
];

$default_icon = 'bi-box';

$page_title = "Product Categories - Green Agric Nigeria";
$page_css = 'categories.css';
$page_description = "Browse agricultural products by category. Find fresh produce, grains, livestock, and more from verified farmers.";
include '../../includes/header.php';
?>

<style>
/* ============================================
   ROOT VARIABLES
   ============================================ */
:root {
    --primary: #0d6e3f;
    --primary-dark: #0a5230;
    --primary-light: #e8f5ee;
    --primary-gradient: linear-gradient(135deg, #0a5230 0%, #1a8a4d 50%, #0d6e3f 100%);
    --text-dark: #1a2a3a;
    --text-muted: #6b7a8a;
    --bg-light: #f7faf9;
    --shadow-sm: 0 2px 20px rgba(13, 110, 63, 0.08);
    --shadow-md: 0 10px 40px rgba(13, 110, 63, 0.12);
    --shadow-lg: 0 20px 60px rgba(13, 110, 63, 0.15);
    --shadow-xl: 0 30px 80px rgba(13, 110, 63, 0.2);
    --radius-sm: 8px;
    --radius-md: 16px;
    --radius-lg: 24px;
    --radius-xl: 32px;
    --transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ============================================
   HERO SECTION
   ============================================ */
.categories-hero {
    background: var(--primary-gradient);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
}

.categories-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.categories-hero .container {
    position: relative;
    z-index: 2;
}

.categories-hero h1 {
    font-size: 3.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 16px;
}

.categories-hero .lead {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    max-width: 600px;
}

.categories-hero .hero-stats {
    display: flex;
    gap: 40px;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.categories-hero .hero-stats .stat {
    text-align: center;
}

.categories-hero .hero-stats .stat .number {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    display: block;
}

.categories-hero .hero-stats .stat .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

/* ============================================
   CATEGORIES SECTION
   ============================================ */
.categories-section {
    padding: 60px 0 80px;
    background: var(--bg-light);
}

/* Section Header */
.section-header {
    text-align: center;
    margin-bottom: 48px;
}

.section-header h2 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.section-header .highlight {
    color: var(--primary);
}

.section-header p {
    color: var(--text-muted);
    font-size: 1.1rem;
}

/* Featured Categories Grid */
.featured-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 60px;
}

.category-featured-card {
    background: white;
    border-radius: var(--radius-md);
    padding: 32px 24px;
    text-align: center;
    text-decoration: none;
    color: inherit;
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    display: block;
}

.category-featured-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    opacity: 0;
    transition: var(--transition);
}

.category-featured-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.category-featured-card:hover::before {
    opacity: 1;
}

.category-featured-card .icon-wrapper {
    width: 72px;
    height: 72px;
    background: var(--primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    transition: var(--transition);
}

.category-featured-card:hover .icon-wrapper {
    background: var(--primary);
    transform: scale(1.05) rotate(-5deg);
}

.category-featured-card .icon-wrapper i {
    font-size: 2rem;
    color: var(--primary);
    transition: var(--transition);
}

.category-featured-card:hover .icon-wrapper i {
    color: white;
}

.category-featured-card h4 {
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.category-featured-card .product-count {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.category-featured-card .badge-count {
    display: inline-block;
    padding: 4px 16px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}

.category-featured-card .arrow {
    position: absolute;
    right: 20px;
    bottom: 20px;
    opacity: 0;
    transform: translateX(-10px);
    transition: var(--transition);
    color: var(--primary);
}

.category-featured-card:hover .arrow {
    opacity: 1;
    transform: translateX(0);
}

/* All Categories Grid */
.all-categories-wrapper {
    background: white;
    border-radius: var(--radius-lg);
    padding: 40px;
    box-shadow: var(--shadow-sm);
}

.all-categories-wrapper .header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.all-categories-wrapper .header-row h3 {
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.all-categories-wrapper .header-row .total-badge {
    padding: 6px 20px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
}

.all-categories-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.category-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    background: var(--bg-light);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-dark);
    transition: var(--transition);
    border: 1px solid transparent;
}

.category-item:hover {
    background: var(--primary-light);
    border-color: var(--primary);
    transform: translateX(4px);
}

.category-item .icon {
    width: 36px;
    height: 36px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--primary);
    font-size: 0.9rem;
}

.category-item .info {
    flex: 1;
}

.category-item .info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-dark);
}

.category-item .info .meta {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.category-item .count-badge {
    padding: 2px 12px;
    background: var(--primary);
    color: white;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Subcategories */
.category-item .subcategories {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 2px;
}

.category-item .subcategories span {
    background: rgba(0, 0, 0, 0.05);
    padding: 0 8px;
    border-radius: 4px;
    margin-right: 4px;
}

/* ============================================
   VIEW ALL CTA
   ============================================ */
.view-all-cta {
    text-align: center;
    margin-top: 40px;
}

.view-all-cta .btn-view-all {
    padding: 14px 48px;
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 12px;
}

.view-all-cta .btn-view-all:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    color: white;
}

/* ============================================
   CTA SECTION
   ============================================ */
.cta-section {
    background: var(--primary-gradient);
    padding: 60px 0;
    text-align: center;
    color: white;
}

.cta-section h2 {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 16px;
}

.cta-section p {
    opacity: 0.9;
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto 30px;
}

.cta-section .btn-cta {
    padding: 14px 40px;
    background: white;
    color: var(--primary-dark);
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.cta-section .btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    color: var(--primary-dark);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 1200px) {
    .all-categories-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .categories-hero h1 {
        font-size: 2.8rem;
    }
    
    .featured-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .all-categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .categories-hero {
        padding: 60px 0 40px;
    }
    
    .categories-hero h1 {
        font-size: 2.2rem;
    }
    
    .categories-hero .lead {
        font-size: 1rem;
    }
    
    .categories-hero .hero-stats {
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .categories-hero .hero-stats .stat .number {
        font-size: 1.5rem;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .featured-grid {
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .category-featured-card {
        padding: 24px 16px;
    }
    
    .category-featured-card .icon-wrapper {
        width: 56px;
        height: 56px;
    }
    
    .category-featured-card .icon-wrapper i {
        font-size: 1.5rem;
    }
    
    .all-categories-wrapper {
        padding: 24px;
    }
    
    .all-categories-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .category-item {
        padding: 12px 14px;
    }
    
    .cta-section h2 {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .categories-hero h1 {
        font-size: 1.8rem;
    }
    
    .categories-hero .hero-stats {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .categories-hero .hero-stats .stat {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .categories-hero .hero-stats .stat .number {
        font-size: 1.3rem;
    }
    
    .section-header h2 {
        font-size: 1.6rem;
    }
    
    .featured-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .category-featured-card {
        padding: 20px 12px;
    }
    
    .category-featured-card h4 {
        font-size: 1rem;
    }
    
    .category-featured-card .icon-wrapper {
        width: 48px;
        height: 48px;
    }
    
    .category-featured-card .icon-wrapper i {
        font-size: 1.2rem;
    }
    
    .all-categories-wrapper {
        padding: 16px;
    }
    
    .all-categories-grid {
        grid-template-columns: 1fr;
    }
    
    .all-categories-wrapper .header-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .view-all-cta .btn-view-all {
        padding: 12px 32px;
        font-size: 0.95rem;
        width: 100%;
        justify-content: center;
    }
    
    .cta-section h2 {
        font-size: 1.6rem;
    }
    
    .cta-section .btn-cta {
        width: 100%;
        justify-content: center;
    }
}

/* ============================================
   ANIMATIONS
   ============================================ */
.reveal {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.reveal.visible {
    opacity: 1;
    transform: translateY(0);
}

.reveal-delay-1 { transition-delay: 0.05s; }
.reveal-delay-2 { transition-delay: 0.1s; }
.reveal-delay-3 { transition-delay: 0.15s; }
.reveal-delay-4 { transition-delay: 0.2s; }
.reveal-delay-5 { transition-delay: 0.25s; }
.reveal-delay-6 { transition-delay: 0.3s; }

/* ============================================
   SCROLL PROGRESS BAR
   ============================================ */
.scroll-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 0;
    height: 3px;
    background: var(--primary-gradient);
    z-index: 9999;
    transition: width 0.1s linear;
}

/* ============================================
   PRINT STYLES
   ============================================ */
@media print {
    .categories-hero {
        padding: 40px 0;
        min-height: auto;
    }
    
    .category-featured-card:hover {
        transform: none !important;
    }
    
    .cta-section,
    .scroll-progress {
        display: none !important;
    }
}
</style>

<!-- ============================================
   SCROLL PROGRESS BAR
   ============================================ -->
<div class="scroll-progress" id="scrollProgress"></div>

<!-- ============================================
   HERO SECTION
   ============================================ -->
<section class="categories-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                
                <h1>Product Categories</h1>
                <p class="lead">
                    Browse our wide range of agricultural products by category. 
                    Find fresh produce, grains, livestock, and more from verified farmers.
                </p>
                <div class="hero-stats">
                    <div class="stat">
                        <span class="number"><?php echo count($categories); ?></span>
                        <span class="label">Categories</span>
                    </div>
                    <div class="stat">
                        <span class="number"><?php echo array_sum(array_column($categories, 'product_count')); ?></span>
                        <span class="label">Products Available</span>
                    </div>
                    <div class="stat">
                        <span class="number"><?php echo count($featured_categories); ?>+</span>
                        <span class="label">Featured Categories</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   CATEGORIES SECTION
   ============================================ -->
<section class="categories-section">
    <div class="container">
        <!-- Section Header -->
        <div class="section-header reveal">
            <h2>Browse by <span class="highlight">Category</span></h2>
            <p>Find exactly what you're looking for</p>
        </div>

        <!-- Featured Categories -->
        <?php if (!empty($featured_categories)): ?>
            <div class="featured-grid">
                <?php foreach ($featured_categories as $index => $category): 
                    $icon = $category_icons[$category['name']] ?? $default_icon;
                    $delay = ($index % 6) + 1;
                ?>
                    <a href="browse.php?category=<?php echo (int)$category['id']; ?>" 
                       class="category-featured-card reveal reveal-delay-<?php echo $delay; ?>">
                        <div class="icon-wrapper">
                            <i class="bi <?php echo htmlspecialchars($icon); ?>"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                        <div class="product-count"><?php echo (int)$category['product_count']; ?> products</div>
                        <span class="badge-count">
                            <?php if (!empty($category['children'])): ?>
                                <?php echo count($category['children']); ?> subcategories
                            <?php else: ?>
                                Popular
                            <?php endif; ?>
                        </span>
                        <span class="arrow"><i class="bi bi-arrow-right"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- All Categories -->
        <div class="all-categories-wrapper reveal">
            <div class="header-row">
                <h3>All Categories</h3>
                <span class="total-badge">
                    <i class="bi bi-grid me-1"></i> <?php echo count($categories); ?> categories
                </span>
            </div>

            <div class="all-categories-grid">
                <?php foreach ($categories as $category): 
                    $icon = $category_icons[$category['name']] ?? $default_icon;
                    $has_children = !empty($category['children']);
                    $parent_name = '';
                    
                    if ($category['parent_id']) {
                        foreach ($categories as $parent) {
                            if ($parent['id'] == $category['parent_id']) {
                                $parent_name = $parent['name'];
                                break;
                            }
                        }
                    }
                    
                    // Get subcategory names for display
                    $sub_names = [];
                    if ($has_children) {
                        foreach ($category['children'] as $child) {
                            $sub_names[] = $child['name'];
                        }
                    }
                ?>
                    <a href="browse.php?category=<?php echo (int)$category['id']; ?>" class="category-item">
                        <div class="icon">
                            <i class="bi <?php echo htmlspecialchars($icon); ?>"></i>
                        </div>
                        <div class="info">
                            <div class="name"><?php echo htmlspecialchars($category['name']); ?></div>
                            <?php if ($parent_name): ?>
                                <div class="meta">Under: <?php echo htmlspecialchars($parent_name); ?></div>
                            <?php endif; ?>
                            <?php if ($has_children && !empty($sub_names)): ?>
                                <div class="subcategories">
                                    <?php foreach (array_slice($sub_names, 0, 3) as $name): ?>
                                        <span><?php echo htmlspecialchars($name); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($sub_names) > 3): ?>
                                        <span>+<?php echo count($sub_names) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="count-badge"><?php echo (int)$category['product_count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- View All Button -->
            <div class="view-all-cta">
                <a href="browse.php" class="btn-view-all">
                    <i class="bi bi-grid-3x3-gap-fill"></i> View All Products
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   CTA SECTION
   ============================================ -->
<section class="cta-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2>Can't Find What You're Looking For?</h2>
                <p>We have a wide range of products available. Contact us for special requests or bulk orders.</p>
                <a href="<?php echo BASE_URL; ?>/contact.php" class="btn-cta">
                    <i class="bi bi-chat-dots"></i> Contact Us
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   JAVASCRIPT
   ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // SCROLL PROGRESS BAR
    // ============================================
    const progressBar = document.getElementById('scrollProgress');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const progress = (scrollTop / scrollHeight) * 100;
        progressBar.style.width = progress + '%';
    }, { passive: true });

    // ============================================
    // REVEAL ANIMATIONS
    // ============================================
    const revealElements = document.querySelectorAll('.reveal');
    
    const revealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });
    
    revealElements.forEach(el => revealObserver.observe(el));

    // ============================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const offset = 100;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Escape to close any open modals
        if (e.key === 'Escape') {
            // Handle any modals if present
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>