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

// Get all active subscription plans
$plans = $db->fetchAll("
    SELECT * FROM subscription_plans 
    WHERE is_active = 1 
    ORDER BY price ASC
");

if (empty($plans)) {
    die("No subscription plans available. Please contact support.");
}

// Paystack Configuration
$paystack_public_key = 'pk_test_3d8772ab51c1407f1302d2fffc114220b0b1d9ee'; // Replace with your actual key

$page_title = "Seller Pricing - Green Agric Nigeria";
$page_css = 'pricing.css';
$page_description = "Choose the perfect subscription plan for your agricultural business. Transparent pricing with no hidden fees.";
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
    --transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ============================================
   HERO SECTION
   ============================================ */
.pricing-hero {
    background: var(--primary-gradient);
    padding: 60px 0 50px;
    position: relative;
    overflow: hidden;
}

.pricing-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.pricing-hero .container {
    position: relative;
    z-index: 2;
}

.pricing-hero h1 {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 12px;
}

.pricing-hero .lead {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.15rem;
    max-width: 600px;
}

.pricing-hero .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 6px 18px 6px 10px;
    border-radius: 50px;
    color: white;
    font-size: 0.8rem;
    font-weight: 500;
    margin-bottom: 20px;
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

/* ============================================
   PRICING PLANS
   ============================================ */
.pricing-plans {
    padding: 60px 0 80px;
    background: var(--bg-light);
}

.section-header {
    text-align: center;
    margin-bottom: 40px;
}

.section-header .tag {
    display: inline-block;
    padding: 4px 16px;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.section-header h2 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 6px;
}

.section-header h2 .highlight {
    color: var(--primary);
}

.section-header p {
    color: var(--text-muted);
    font-size: 1.05rem;
}

/* Plans Grid */
.pricing-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    max-width: 1100px;
    margin: 0 auto;
}

.pricing-card {
    background: white;
    border-radius: var(--radius-md);
    padding: 30px 24px;
    box-shadow: var(--shadow-sm);
    border: 2px solid transparent;
    transition: var(--transition);
    position: relative;
    display: flex;
    flex-direction: column;
}

.pricing-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
}

.pricing-card.highlight {
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
    transform: scale(1.02);
}

.pricing-card.highlight:hover {
    transform: scale(1.02) translateY(-6px);
    box-shadow: var(--shadow-lg);
}

.pricing-card .badge {
    position: absolute;
    top: -12px;
    right: 20px;
    padding: 4px 16px;
    background: var(--primary);
    color: white;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(13, 110, 63, 0.3);
}

.pricing-card .plan-header {
    text-align: center;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--bg-light);
}

.pricing-card .plan-header .icon-wrapper {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 1.5rem;
    color: var(--primary);
}

.pricing-card .plan-header .plan-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
}

.pricing-card .plan-header .plan-price {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1.2;
    margin-top: 4px;
}

.pricing-card .plan-header .plan-price .period {
    font-size: 0.9rem;
    font-weight: 400;
    color: var(--text-muted);
}

.pricing-card .plan-header .plan-duration {
    display: inline-block;
    padding: 2px 14px;
    background: var(--bg-light);
    border-radius: 50px;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 6px;
}

.pricing-card .plan-description {
    text-align: center;
    color: var(--text-muted);
    font-size: 0.9rem;
    margin: 14px 0 18px;
    min-height: 40px;
}

.pricing-card .features {
    list-style: none;
    padding: 0;
    margin: 0 0 20px;
    flex: 1;
}

.pricing-card .features li {
    padding: 6px 0;
    font-size: 0.88rem;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}

.pricing-card .features li:last-child {
    border-bottom: none;
}

.pricing-card .features li i {
    font-size: 1rem;
    flex-shrink: 0;
}

.pricing-card .features li i.text-success {
    color: var(--primary);
}

.pricing-card .features li i.text-danger {
    color: #dc2626;
}

.pricing-card .btn-plan {
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: var(--transition);
    text-align: center;
    text-decoration: none;
    display: block;
    width: 100%;
}

.pricing-card .btn-plan:hover {
    transform: translateY(-2px);
}

.pricing-card .btn-plan.btn-success {
    background: var(--primary);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(13, 110, 63, 0.3);
}

.pricing-card .btn-plan.btn-success:hover {
    background: var(--primary-dark);
    box-shadow: 0 8px 30px rgba(13, 110, 63, 0.4);
}

.pricing-card .btn-plan.btn-outline-success {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.pricing-card .btn-plan.btn-outline-success:hover {
    background: var(--primary);
    color: white;
}

.pricing-card .btn-plan.btn-secondary {
    background: #e5e7eb;
    color: var(--text-muted);
    border: none;
    cursor: default;
}

/* ============================================
   COMPARISON TABLE
   ============================================ */
.comparison-section {
    padding: 60px 0;
    background: white;
}

.table-wrapper {
    overflow-x: auto;
    max-width: 1000px;
    margin: 30px auto 0;
    background: white;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0, 0, 0, 0.04);
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.comparison-table thead {
    background: var(--primary);
    color: white;
}

.comparison-table thead th {
    padding: 14px 18px;
    text-align: left;
    font-weight: 600;
}

.comparison-table thead th:first-child {
    border-radius: var(--radius-sm) 0 0 0;
}

.comparison-table thead th:last-child {
    border-radius: 0 var(--radius-sm) 0 0;
}

.comparison-table tbody td {
    padding: 12px 18px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
    color: var(--text-dark);
}

.comparison-table tbody tr:hover {
    background: var(--bg-light);
}

.comparison-table tbody td:first-child {
    font-weight: 600;
    color: var(--text-dark);
}

.comparison-table .check {
    color: var(--primary);
    font-weight: 700;
}

.comparison-table .x {
    color: #dc2626;
}

/* ============================================
   CALCULATOR
   ============================================ */
.calculator-section {
    padding: 60px 0;
    background: var(--bg-light);
}

.calculator-wrapper {
    max-width: 700px;
    margin: 30px auto 0;
    background: white;
    border-radius: var(--radius-md);
    padding: 32px 36px;
    box-shadow: var(--shadow-sm);
}

.calculator-wrapper h4 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.calculator-wrapper .subtitle {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 20px;
}

.calculator-wrapper .calc-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.calculator-wrapper .calc-row .form-group label {
    display: block;
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 4px;
    font-size: 0.85rem;
}

.calculator-wrapper .calc-row .form-group input,
.calculator-wrapper .calc-row .form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e5e7eb;
    border-radius: var(--radius-sm);
    font-size: 0.95rem;
    transition: var(--transition);
    background: white;
}

.calculator-wrapper .calc-row .form-group input:focus,
.calculator-wrapper .calc-row .form-group select:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(13, 110, 63, 0.1);
}

.calculator-wrapper .calc-result {
    background: var(--bg-light);
    border-radius: var(--radius-sm);
    padding: 16px 20px;
    margin-top: 16px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.calculator-wrapper .calc-result .result-item {
    text-align: center;
}

.calculator-wrapper .calc-result .result-item .label {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.calculator-wrapper .calc-result .result-item .value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary);
}

.calculator-wrapper .calc-result .result-item .value.commission {
    color: #dc2626;
}

/* ============================================
   CTA SECTION
   ============================================ */
.cta-section {
    background: var(--primary-gradient);
    padding: 50px 0;
    text-align: center;
    color: white;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.cta-section .container {
    position: relative;
    z-index: 2;
}

.cta-section h2 {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 12px;
}

.cta-section p {
    opacity: 0.9;
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto 28px;
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
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.cta-section .btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    color: var(--primary-dark);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 992px) {
    .pricing-hero h1 {
        font-size: 2.5rem;
    }
    
    .pricing-grid {
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .pricing-card.highlight {
        transform: scale(1);
    }
    
    .pricing-card.highlight:hover {
        transform: translateY(-6px);
    }
}

@media (max-width: 768px) {
    .pricing-hero {
        padding: 40px 0 30px;
    }
    
    .pricing-hero h1 {
        font-size: 2rem;
    }
    
    .pricing-hero .lead {
        font-size: 1rem;
    }
    
    .section-header h2 {
        font-size: 1.8rem;
    }
    
    .pricing-grid {
        grid-template-columns: 1fr;
        max-width: 400px;
    }
    
    .pricing-card.highlight {
        transform: scale(1);
        order: -1;
    }
    
    .pricing-card.highlight:hover {
        transform: translateY(-6px);
    }
    
    .comparison-table {
        font-size: 0.8rem;
    }
    
    .comparison-table thead th,
    .comparison-table tbody td {
        padding: 10px 12px;
    }
    
    .calculator-wrapper {
        padding: 24px 18px;
    }
    
    .calculator-wrapper .calc-row {
        grid-template-columns: 1fr;
    }
    
    .calculator-wrapper .calc-result {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .cta-section h2 {
        font-size: 1.8rem;
    }
}

@media (max-width: 576px) {
    .pricing-hero h1 {
        font-size: 1.6rem;
    }
    
    .section-header h2 {
        font-size: 1.4rem;
    }
    
    .pricing-card {
        padding: 20px 16px;
    }
    
    .pricing-card .plan-header .plan-price {
        font-size: 2rem;
    }
    
    .cta-section h2 {
        font-size: 1.4rem;
    }
    
    .cta-section .btn-cta {
        padding: 12px 28px;
        font-size: 0.9rem;
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
    transition: all 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.reveal.visible {
    opacity: 1;
    transform: translateY(0);
}

.reveal-delay-1 { transition-delay: 0.05s; }
.reveal-delay-2 { transition-delay: 0.1s; }
.reveal-delay-3 { transition-delay: 0.15s; }

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
<section class="pricing-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb text-white-50">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>" class="text-white text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active text-white-50" aria-current="page">Pricing</li>
                    </ol>
                </nav>
                <div class="hero-badge">
                    <span class="dot"></span>
                    Transparent Pricing
                </div>
                <h1>Choose Your <span style="color: #fcd34d;">Plan</span></h1>
                <p class="lead">
                    Select the perfect subscription plan for your agricultural business. 
                    No hidden fees, flexible options.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   PRICING PLANS
   ============================================ -->
<section class="pricing-plans">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-tag me-1"></i> Plans</span>
            <h2>Simple, Transparent <span class="highlight">Pricing</span></h2>
            <p>Choose the plan that fits your business needs</p>
        </div>

        <div class="pricing-grid">
            <?php foreach ($plans as $index => $plan): 
                $features = json_decode($plan['features'], true);
                $is_popular = ($plan['display_order'] == 2);
                $plan_price = (float)$plan['price'];
                $is_current = false; // Check if this is the user's current plan (if logged in)
            ?>
                <div class="pricing-card <?php echo $is_popular ? 'highlight' : ''; ?> reveal reveal-delay-<?php echo ($index % 3) + 1; ?>">
                    <?php if ($is_popular): ?>
                        <span class="badge">Most Popular</span>
                    <?php endif; ?>
                    
                    <div class="plan-header">
                        <div class="icon-wrapper">
                            <i class="bi <?php echo $plan['icon'] ?? 'bi-box'; ?>"></i>
                        </div>
                        <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                        <div class="plan-price">
                            ₦<?php echo number_format($plan_price, 0); ?>
                            <span class="period">/ <?php echo $plan['duration_days']; ?> days</span>
                        </div>
                        <div class="plan-duration">
                            <?php echo $plan['duration_days']; ?>-day subscription
                        </div>
                    </div>
                    
                    <p class="plan-description"><?php echo htmlspecialchars($plan['description'] ?? 'Perfect for your business needs'); ?></p>
                    
                    <ul class="features">
                        <?php if (is_array($features)): ?>
                            <?php foreach ($features as $feature): ?>
                                <li>
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php echo htmlspecialchars($feature); ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    
                    <?php if ($is_current): ?>
                        <button class="btn-plan btn-secondary" disabled>
                            <i class="bi bi-check-circle me-1"></i> Current Plan
                        </button>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/seller/subscription/upgrade.php?plan=<?php echo $plan['id']; ?>" 
                           class="btn-plan <?php echo $is_popular ? 'btn-success' : 'btn-outline-success'; ?>">
                            <?php echo $plan_price == 0 ? 'Get Started Free' : 'Subscribe Now'; ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================
   COMPARISON TABLE
   ============================================ -->
<section class="comparison-section">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-table me-1"></i> Compare</span>
            <h2>Plan <span class="highlight">Comparison</span></h2>
            <p>See exactly what each plan offers</p>
        </div>

        <div class="table-wrapper reveal">
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>Features</th>
                        <?php foreach ($plans as $plan): ?>
                            <th><?php echo htmlspecialchars($plan['name']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Build comparison data from plan features
                    $all_features = [];
                    foreach ($plans as $plan) {
                        $features = json_decode($plan['features'], true);
                        if (is_array($features)) {
                            foreach ($features as $feature) {
                                if (!in_array($feature, $all_features)) {
                                    $all_features[] = $feature;
                                }
                            }
                        }
                    }
                    
                    foreach ($all_features as $feature): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($feature); ?></td>
                            <?php foreach ($plans as $plan):
                                $plan_features = json_decode($plan['features'], true);
                                $has_feature = is_array($plan_features) && in_array($feature, $plan_features);
                            ?>
                                <td class="<?php echo $has_feature ? 'check' : 'x'; ?>">
                                    <?php echo $has_feature ? '✅' : '❌'; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ============================================
   EARNINGS CALCULATOR
   ============================================ -->
<section class="calculator-section">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-calculator me-1"></i> Calculator</span>
            <h2>Estimate Your <span class="highlight">Earnings</span></h2>
            <p>Calculate your potential earnings after subscription fees</p>
        </div>

        <div class="calculator-wrapper reveal">
            <h4>Earnings Calculator</h4>
            <p class="subtitle">See how much you can earn after platform fees</p>
            
            <div class="calc-row">
                <div class="form-group">
                    <label for="calcPlan">Subscription Plan</label>
                    <select id="calcPlan">
                        <?php foreach ($plans as $plan): 
                            $plan_price = (float)$plan['price'];
                        ?>
                            <option value="<?php echo $plan_price; ?>" <?php echo $plan['display_order'] == 2 ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($plan['name']); ?> (₦<?php echo number_format($plan_price, 0); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="calcRevenue">Monthly Revenue (₦)</label>
                    <input type="number" id="calcRevenue" value="100000" min="0" step="10000">
                </div>
            </div>

            <div class="calc-result">
                <div class="result-item">
                    <div class="label">Total Revenue</div>
                    <div class="value" id="calcTotalRevenue">₦100,000</div>
                </div>
                <div class="result-item">
                    <div class="label">Subscription Fee</div>
                    <div class="value commission" id="calcFees">- ₦5,000</div>
                </div>
                <div class="result-item">
                    <div class="label">Your Earnings</div>
                    <div class="value" id="calcEarnings">₦95,000</div>
                </div>
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
                <h2>Ready to Start Selling?</h2>
                <p>Join thousands of farmers and agribusinesses already growing their business on Green Agric Nigeria.</p>
                <a href="<?php echo BASE_URL; ?>/auth/register.php?role=seller" class="btn-cta">
                    <i class="bi bi-person-plus"></i> Create Your Seller Account
                    <i class="bi bi-arrow-right"></i>
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
    // EARNINGS CALCULATOR
    // ============================================
    const calcPlan = document.getElementById('calcPlan');
    const calcRevenue = document.getElementById('calcRevenue');
    const calcTotalRevenue = document.getElementById('calcTotalRevenue');
    const calcFees = document.getElementById('calcFees');
    const calcEarnings = document.getElementById('calcEarnings');
    
    function updateCalculator() {
        const subscriptionFee = parseFloat(calcPlan.value) || 0;
        const revenue = parseFloat(calcRevenue.value) || 0;
        const earnings = revenue - subscriptionFee;
        
        calcTotalRevenue.textContent = '₦' + revenue.toLocaleString();
        calcFees.textContent = '- ₦' + subscriptionFee.toLocaleString();
        calcEarnings.textContent = '₦' + earnings.toLocaleString();
        
        // Color coding
        if (earnings < 0) {
            calcEarnings.style.color = '#dc2626';
        } else {
            calcEarnings.style.color = '';
        }
    }
    
    calcPlan.addEventListener('change', updateCalculator);
    calcRevenue.addEventListener('input', updateCalculator);
    updateCalculator();

    // ============================================
    // SMOOTH SCROLL
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const offset = 80;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>