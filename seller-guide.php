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

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';

$db = new Database();

// Seller guidelines sections
$guidelines = [
    [
        'id' => 'overview',
        'title' => 'Overview',
        'icon' => 'bi-info-circle',
        'color' => '#0d6e3f',
        'content' => '
            <p>Green Agric Nigeria is a technology-enabled agricultural marketplace connecting farmers and agribusinesses directly with buyers. As a seller on our platform, you gain access to a growing network of customers across Nigeria, streamlined order management, and secure payment processing.</p>
            <p>This guide outlines everything you need to know to succeed as a seller on Green Agric Nigeria.</p>
        '
    ],
    [
        'id' => 'requirements',
        'title' => 'Seller Requirements',
        'icon' => 'bi-check-circle',
        'color' => '#10b981',
        'content' => '
            <p>To become a seller on Green Agric Nigeria, you must meet the following requirements:</p>
            <ul>
                <li><strong>Business Registration:</strong> Valid business registration with CAC (or equivalent)</li>
                <li><strong>KYC Documentation:</strong> Government-issued ID (National ID, Voter\'s Card, Driver\'s License, or International Passport)</li>
                <li><strong>Bank Account:</strong> Active Nigerian bank account for payouts</li>
                <li><strong>Product Quality:</strong> Commitment to providing quality agricultural products</li>
                <li><strong>Compliance:</strong> Adherence to all platform policies and Nigerian regulations</li>
            </ul>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Note:</strong> All documents are securely stored and only used for verification purposes.
            </div>
        '
    ],
    [
        'id' => 'listing',
        'title' => 'Product Listing Guidelines',
        'icon' => 'bi-tag',
        'color' => '#f59e0b',
        'content' => '
            <h6>1. Product Descriptions</h6>
            <ul>
                <li>Use clear, accurate, and detailed product descriptions</li>
                <li>Include product specifications (weight, size, grade, etc.)</li>
                <li>Mention origin/farm location and harvest date</li>
                <li>Highlight certifications (organic, fair trade, etc.)</li>
            </ul>
            <h6 class="mt-3">2. Product Images</h6>
            <ul>
                <li>Upload high-quality, clear images of the actual product</li>
                <li>Include multiple angles and packaging photos</li>
                <li>Use natural lighting for accurate color representation</li>
                <li>Minimum image resolution: 800x800 pixels</li>
            </ul>
            <h6 class="mt-3">3. Pricing</h6>
            <ul>
                <li>Set competitive and fair prices</li>
                <li>Include all applicable taxes in the listed price</li>
                <li>Update prices regularly to reflect market changes</li>
                <li>Consider volume discounts for bulk orders</li>
            </ul>
            <h6 class="mt-3">4. Prohibited Items</h6>
            <ul>
                <li>Illegal or restricted substances</li>
                <li>Counterfeit or misrepresented products</li>
                <li>Products that violate Nigerian agricultural regulations</li>
                <li>Items that cannot be legally sold or distributed</li>
            </ul>
        '
    ],
    [
        'id' => 'fulfillment',
        'title' => 'Order Fulfillment',
        'icon' => 'bi-box-seam',
        'color' => '#2563eb',
        'content' => '
            <h6>1. Order Processing</h6>
            <ul>
                <li>Acknowledge and confirm orders within 24 hours</li>
                <li>Process orders in a timely manner</li>
                <li>Communicate any delays or issues to the buyer promptly</li>
            </ul>
            <h6 class="mt-3">2. Packaging</h6>
            <ul>
                <li>Use proper packaging to protect products during transit</li>
                <li>Use food-grade packaging for agricultural produce</li>
                <li>Include proper labeling and handling instructions</li>
                <li>Consider temperature-controlled packaging for perishables</li>
            </ul>
            <h6 class="mt-3">3. Shipping</h6>
            <ul>
                <li>Ship orders within 2-3 business days</li>
                <li>Provide tracking information to buyers</li>
                <li>Use reliable shipping partners</li>
                <li>Maintain proper documentation for shipments</li>
            </ul>
            <h6 class="mt-3">4. Quality Control</h6>
            <ul>
                <li>Inspect products before shipping</li>
                <li>Ensure products meet quality standards</li>
                <li>Replace damaged or defective items promptly</li>
            </ul>
        '
    ],
    [
        'id' => 'customer-service',
        'title' => 'Customer Service Standards',
        'icon' => 'bi-headset',
        'color' => '#8b5cf6',
        'content' => '
            <p>Maintaining high customer service standards is essential for success on our platform:</p>
            <ul>
                <li><strong>Response Time:</strong> Respond to customer inquiries within 24 hours</li>
                <li><strong>Professional Communication:</strong> Use polite, professional language in all interactions</li>
                <li><strong>Problem Resolution:</strong> Address customer concerns promptly and professionally</li>
                <li><strong>Feedback:</strong> Encourage and respond to customer reviews</li>
                <li><strong>Disputes:</strong> Cooperate with Green Agric\'s dispute resolution process</li>
            </ul>
            <div class="alert alert-warning mt-3">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Important:</strong> Poor customer service ratings may result in account suspension or removal from the platform.
            </div>
        '
    ],
    [
        'id' => 'policies',
        'title' => 'Platform Policies',
        'icon' => 'bi-file-earmark-text',
        'color' => '#dc2626',
        'content' => '
            <h6>1. Prohibited Conduct</h6>
            <ul>
                <li>Fraudulent or deceptive practices</li>
                <li>Misrepresentation of products</li>
                <li>Manipulation of reviews or ratings</li>
                <li>Violation of Nigerian agricultural regulations</li>
            </ul>
            <h6 class="mt-3">2. Intellectual Property</h6>
            <ul>
                <li>Respect copyright and trademark rights</li>
                <li>Do not use unauthorized images or content</li>
                <li>Report any IP violations to Green Agric</li>
            </ul>
            <h6 class="mt-3">3. Data Privacy</h6>
            <ul>
                <li>Protect customer data and privacy</li>
                <li>Comply with NDPR regulations</li>
                <li>Do not share customer information with third parties</li>
            </ul>
            <h6 class="mt-3">4. Consequences of Violations</h6>
            <ul>
                <li>Warning and corrective action request</li>
                <li>Temporary account suspension</li>
                <li>Permanent account termination</li>
                <li>Legal action in severe cases</li>
            </ul>
        '
    ],
    [
        'id' => 'success-tips',
        'title' => 'Tips for Success',
        'icon' => 'bi-rocket',
        'color' => '#0891b2',
        'content' => '
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <h6><i class="bi bi-camera text-success me-2"></i> High-Quality Photos</h6>
                            <p class="small text-muted">Products with clear, attractive images get 50% more views and 30% more sales.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <h6><i class="bi bi-tags text-success me-2"></i> Competitive Pricing</h6>
                            <p class="small text-muted">Regularly research market prices to ensure your products are competitively priced.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <h6><i class="bi bi-clock text-success me-2"></i> Fast Response</h6>
                            <p class="small text-muted">Quick responses to buyer inquiries build trust and increase conversion rates.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body">
                            <h6><i class="bi bi-star text-success me-2"></i> Great Reviews</h6>
                            <p class="small text-muted">Consistently high ratings increase your visibility and credibility on the platform.</p>
                        </div>
                    </div>
                </div>
            </div>
        '
    ],
    [
        'id' => 'support',
        'title' => 'Seller Support',
        'icon' => 'bi-life-preserver',
        'color' => '#059669',
        'content' => '
            <p>We\'re committed to your success as a seller. Our support team is available to help you with:</p>
            <ul>
                <li>Account setup and verification</li>
                <li>Product listing optimization</li>
                <li>Order management assistance</li>
                <li>Payment and payout inquiries</li>
                <li>Technical support</li>
                <li>Marketing and promotion</li>
            </ul>
            <div class="mt-3 p-3 bg-success text-white rounded-3">
                <i class="bi bi-envelope me-2"></i>
                <strong>Contact Seller Support:</strong>support@greenagric.shop
            </div>
        '
    ]
];

$page_title = "Seller Guidelines - Green Agric Nigeria";
$page_css = 'seller-guidelines.css';
$page_description = "Complete guidelines for sellers on Green Agric Nigeria. Learn about requirements, product listing, fulfillment, customer service, and platform policies.";
include 'includes/header.php';
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
    --radius-sm: 8px;
    --radius-md: 16px;
    --radius-lg: 24px;
    --radius-xl: 32px;
    --transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ============================================
   HERO SECTION
   ============================================ */
.seller-hero {
    background: var(--primary-gradient);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
}

.seller-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.seller-hero .container {
    position: relative;
    z-index: 2;
}

.seller-hero h1 {
    font-size: 3.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 16px;
}

.seller-hero .lead {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    max-width: 600px;
}

.seller-hero .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 8px 20px 8px 12px;
    border-radius: 50px;
    color: white;
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.hero-badge .dot {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    display: inline-block;
    animation: pulseDot 2s ease-in-out infinite;
}

@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.hero-actions {
    display: flex;
    gap: 16px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn-hero-primary {
    background: white;
    color: var(--primary-dark);
    padding: 14px 36px;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    color: var(--primary-dark);
}

.btn-hero-secondary {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: white;
    padding: 14px 36px;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-hero-secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    transform: translateY(-3px);
}

/* ============================================
   GUIDELINES SECTION
   ============================================ */
.guidelines-section {
    padding: 80px 0;
    background: var(--bg-light);
}

.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-header .tag {
    display: inline-block;
    padding: 4px 16px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.section-header h2 {
    font-size: 2.8rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.section-header h2 .highlight {
    color: var(--primary);
}

.section-header p {
    color: var(--text-muted);
    font-size: 1.1rem;
}

/* Guidelines Grid */
.guidelines-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
}

.guideline-card {
    background: white;
    border-radius: var(--radius-md);
    padding: 32px 28px;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: var(--transition);
    height: 100%;
}

.guideline-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
}

.guideline-card .card-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
}

.guideline-card .card-header .icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.guideline-card .card-header h3 {
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--text-dark);
    margin: 0;
}

.guideline-card .card-body {
    color: var(--text-muted);
    font-size: 0.95rem;
    line-height: 1.8;
}

.guideline-card .card-body ul {
    padding-left: 20px;
    margin-bottom: 0;
}

.guideline-card .card-body ul li {
    margin-bottom: 6px;
}

.guideline-card .card-body ul li:last-child {
    margin-bottom: 0;
}

.guideline-card .card-body h6 {
    font-weight: 600;
    color: var(--text-dark);
    margin-top: 12px;
}

.guideline-card .card-body h6:first-child {
    margin-top: 0;
}

/* ============================================
   BECOME A SELLER CTA
   ============================================ */
.seller-cta {
    background: var(--primary-gradient);
    padding: 70px 0;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.seller-cta::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.seller-cta .container {
    position: relative;
    z-index: 2;
}

.seller-cta h2 {
    font-size: 2.8rem;
    font-weight: 800;
    margin-bottom: 16px;
}

.seller-cta p {
    opacity: 0.9;
    font-size: 1.15rem;
    max-width: 500px;
    margin: 0 auto 32px;
}

.seller-cta .btn-cta {
    padding: 16px 44px;
    background: white;
    color: var(--primary-dark);
    border: none;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1.05rem;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.seller-cta .btn-cta:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    color: var(--primary-dark);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 992px) {
    .seller-hero h1 {
        font-size: 2.8rem;
    }
    
    .guidelines-grid {
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .seller-hero {
        padding: 60px 0 40px;
    }
    
    .seller-hero h1 {
        font-size: 2.2rem;
    }
    
    .seller-hero .lead {
        font-size: 1rem;
    }
    
    .hero-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .hero-actions .btn {
        text-align: center;
        justify-content: center;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .guidelines-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .guideline-card {
        padding: 24px 20px;
    }
    
    .seller-cta h2 {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .seller-hero h1 {
        font-size: 1.8rem;
    }
    
    .section-header h2 {
        font-size: 1.6rem;
    }
    
    .guideline-card .card-header h3 {
        font-size: 1rem;
    }
    
    .seller-cta h2 {
        font-size: 1.6rem;
    }
    
    .seller-cta .btn-cta {
        padding: 14px 32px;
        font-size: 0.95rem;
        width: 100%;
        justify-content: center;
    }
}

/* ============================================
   ANIMATIONS
   ============================================ */
.reveal {
    opacity: 0;
    transform: translateY(40px);
    transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.reveal.visible {
    opacity: 1;
    transform: translateY(0);
}

.reveal-delay-1 { transition-delay: 0.05s; }
.reveal-delay-2 { transition-delay: 0.1s; }
.reveal-delay-3 { transition-delay: 0.15s; }
.reveal-delay-4 { transition-delay: 0.2s; }

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
</style>

<!-- ============================================
   SCROLL PROGRESS BAR
   ============================================ -->
<div class="scroll-progress" id="scrollProgress"></div>

<!-- ============================================
   HERO SECTION
   ============================================ -->
<section class="seller-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="hero-badge">
                    <span class="dot"></span>
                    Seller Resources
                </div>
                <h1>Seller <span style="color: #fcd34d;">Guidelines</span></h1>
                <p class="lead">
                    Everything you need to know to succeed as a seller on Green Agric Nigeria.
                    From requirements to best practices — we've got you covered.
                </p>
                <div class="hero-actions">
                    <a href="<?php echo BASE_URL; ?>/auth/register.php?role=seller" class="btn-hero-primary">
                        <i class="bi bi-person-plus"></i> Start Selling Today
                    </a>
                    <a href="#guidelines" class="btn-hero-secondary">
                        <i class="bi bi-arrow-down"></i> View Guidelines
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   GUIDELINES SECTION
   ============================================ -->
<section class="guidelines-section" id="guidelines">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-list-check me-1"></i> Guide</span>
            <h2>Complete <span class="highlight">Seller Guidelines</span></h2>
            <p>Everything you need to know to succeed on our platform</p>
        </div>

        <div class="guidelines-grid">
            <?php foreach ($guidelines as $index => $section): ?>
                <div class="guideline-card reveal reveal-delay-<?php echo ($index % 4) + 1; ?>">
                    <div class="card-header">
                        <div class="icon-wrapper" style="background: <?php echo $section['color']; ?>;">
                            <i class="bi <?php echo $section['icon']; ?>"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($section['title']); ?></h3>
                    </div>
                    <div class="card-body">
                        <?php echo $section['content']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================
   BECOME A SELLER CTA
   ============================================ -->
<section class="seller-cta">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2>Ready to Start Selling?</h2>
                <p>Join thousands of farmers and agribusinesses already growing their business on Green Agric Nigeria.</p>
                <a href="<?php echo BASE_URL; ?>/auth/register.php?role=seller" class="btn-cta">
                    <i class="bi bi-person-plus"></i> Become a Seller
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll Progress
    const progressBar = document.getElementById('scrollProgress');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const progress = (scrollTop / scrollHeight) * 100;
        progressBar.style.width = progress + '%';
    }, { passive: true });

    // Reveal Animations
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
});
</script>

<?php include 'includes/footer.php'; ?>