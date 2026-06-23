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

require_once 'includes/functions.php';
require_once 'classes/Database.php';

$db = new Database();

// Get FAQ categories and questions from database
// If you have a database table, use this:
// $categories = $db->fetchAll("SELECT * FROM faq_categories WHERE is_active = 1 ORDER BY sort_order");
// $faqs = $db->fetchAll("SELECT * FROM faqs WHERE is_active = 1 ORDER BY category_id, sort_order");

// For demo purposes, using hardcoded data
$faq_categories = [
    [
        'id' => 1,
        'name' => 'Getting Started',
        'icon' => 'bi-rocket',
        'color' => '#0d6e3f',
        'questions' => [
            [
                'question' => 'What is Green Agric Nigeria?',
                'answer' => 'Green Agric Nigeria is a technology-enabled agricultural supply chain company that connects farmers directly with businesses. We provide wholesale distribution, aggregation services, and market access for farmers across Nigeria.'
            ],
            [
                'question' => 'How do I create an account?',
                'answer' => 'You can create an account by clicking the "Sign Up" button at the top of our website. Choose between a Buyer or Seller account, fill in your details, and verify your email address to get started.'
            ],
            [
                'question' => 'Is it free to create an account?',
                'answer' => 'Yes! Creating a buyer account is completely free. Seller accounts have a one-time verification fee to ensure quality and trust on our platform.'
            ]
        ]
    ],
    [
        'id' => 2,
        'name' => 'Buying & Orders',
        'icon' => 'bi-cart3',
        'color' => '#f59e0b',
        'questions' => [
            [
                'question' => 'How do I place an order?',
                'answer' => 'Browse our product catalog, select the items you want, choose quantity, and add them to your cart. Proceed to checkout, provide delivery details, and complete payment. You\'ll receive an order confirmation via email.'
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept various payment methods including bank transfers, credit/debit cards, USSD payments, and mobile money through our secure payment gateway, Paystack.'
            ],
            [
                'question' => 'How long does delivery take?',
                'answer' => 'Delivery times vary depending on your location. Major cities may take 2-5 business days depending on the seller. You\'ll receive tracking information once your order ships.'
            ],
            [
                'question' => 'Can I track my order?',
                'answer' => 'Yes! Once your order is dispatched, you\'ll receive a tracking number via dashboard. You can track your order status in your account dashboard under "My Orders".'
            ]
        ]
    ],
    [
        'id' => 3,
        'name' => 'Selling on Green Agric',
        'icon' => 'bi-shop',
        'color' => '#2563eb',
        'questions' => [
            [
                'question' => 'How do I become a seller?',
                'answer' => 'Click "Become a Seller" on our homepage, register and fill in your business details, submit required documents (KYC), and pass verification. Once approved, you can start listing products immediately.'
            ],
            [
                'question' => 'What documents do I need to sell?',
                'answer' => 'You\'ll need a valid government-issued ID, business registration certificate, bank account details, and proof of address. All documents are securely stored and only used for verification.'
            ],
            [
                'question' => 'How much does it cost to sell?',
                'answer' => 'We charge a small commission on each successful sale and monthly subscription fees. Our commission rates are transparent and competitive. Contact us for detailed pricing.'
            ],
            [
                'question' => 'How do I get paid?',
                'answer' => 'Payments are processed through our secure escrow system. Funds are released to your account 24-48 hours after delivery confirmation. You can withdraw to your linked bank account.'
            ]
        ]
    ],
    [
        'id' => 4,
        'name' => 'Products & Quality',
        'icon' => 'bi-box-seam',
        'color' => '#8b5cf6',
        'questions' => [
            [
                'question' => 'Where do your products come from?',
                'answer' => 'Our products come from verified farmers and cooperatives across Nigeria. We work directly with farming communities to ensure quality, freshness, and fair trade practices.'
            ],
            [
                'question' => 'Are your products organic?',
                'answer' => 'We offer both conventional and organic products. Our organic products are clearly labeled and certified by recognized agricultural certification bodies.'
            ],
            [
                'question' => 'How do you ensure product quality?',
                'answer' => 'We implement strict quality control measures including standardized grading, sorting, and handling processes. Each product undergoes quality checks before being listed on our platform.'
            ],
            [
                'question' => 'What happens if my product is damaged?',
                'answer' => 'If you receive damaged products, please contact our support team within 24 hours with photos. We\'ll investigate and arrange for a replacement or refund as per our policy.'
            ]
        ]
    ],
    [
        'id' => 5,
        'name' => 'Payments & Refunds',
        'icon' => 'bi-credit-card',
        'color' => '#dc2626',
        'questions' => [
            [
                'question' => 'Is my payment secure?',
                'answer' => 'Yes! All payments are processed through Paystack, a PCI-DSS compliant payment gateway. We use industry-standard encryption and never store your sensitive payment information.'
            ],
            [
                'question' => 'What is your refund policy?',
                'answer' => 'Refunds are available for undelivered orders, incorrect items, or damaged products. Refund requests must be submitted within 7 days of delivery. Each request is reviewed on a case-by-case basis.'
            ],
            [
                'question' => 'How long do refunds take?',
                'answer' => 'Approved refunds are processed within 5-10 business days. The timeframe depends on your bank\'s processing speed. You\'ll receive confirmation once the refund is initiated.'
            ]
        ]
    ],
    [
        'id' => 6,
        'name' => 'Account & Security',
        'icon' => 'bi-shield-lock',
        'color' => '#4f46e5',
        'questions' => [
            [
                'question' => 'How do I reset my password?',
                'answer' => 'Click "Forgot Password" on the login page, enter your registered email, and follow the instructions sent to your inbox. You\'ll be able to create a new password securely.'
            ],
            [
                'question' => 'How do I update my profile information?',
                'answer' => 'Log in to your account, go to "Profile Settings", and update your information. Changes are saved immediately. Some changes (like email) may require verification.'
            ],
            [
                'question' => 'Is my personal information safe?',
                'answer' => 'Absolutely! We comply with the Nigeria Data Protection Regulation (NDPR) and implement strict security measures to protect your data. We never share your information with third parties without your consent.'
            ]
        ]
    ],
    [
        'id' => 7,
        'name' => 'Delivery & Logistics',
        'icon' => 'bi-truck',
        'color' => '#0891b2',
        'questions' => [
            [
                'question' => 'What areas do you deliver to?',
                'answer' => 'We currently deliver to all states in Nigeria. Coverage may vary by product type. We\'re expanding our logistics network to reach more locations. Check product pages for specific delivery availability.'
            ],
            [
                'question' => 'How much does delivery cost?',
                'answer' => 'Delivery costs vary based on location, order size, and product type. You\'ll see the delivery fee clearly during checkout before confirming your order.'
            ],
            [
                'question' => 'Can I change my delivery address?',
                'answer' => 'You can change your delivery address before the order is processed. Once the order is dispatched, changes may not be possible. Contact our support team immediately if you need assistance.'
            ]
        ]
    ],
    [
        'id' => 8,
        'name' => 'Support & Contact',
        'icon' => 'bi-headset',
        'color' => '#059669',
        'questions' => [
            [
                'question' => 'How do I contact customer support?',
                'answer' => 'You can reach us through our Contact Us page, email at support@greenagric.shop, or call us at +234 703 041 9150. Our support team is available Monday to Saturday, 8AM to 6PM.'
            ],
            [
                'question' => 'What are your support hours?',
                'answer' => 'Our customer support is available Monday to Friday, 8:00 AM to 6:00 PM WAT, and Saturday, 9:00 AM to 4:00 PM WAT. We\'re closed on Sundays and public holidays.'
            ],
            [
                'question' => 'How quickly do you respond to inquiries?',
                'answer' => 'We typically respond within 24-48 hours for email inquiries. Live chat and phone support offer faster responses during business hours. Urgent issues may be escalated for quicker resolution.'
            ]
        ]
    ]
];

$page_title = "Frequently Asked Questions - Green Agric Nigeria";
$page_css = 'faq.css';
$page_description = "Find answers to common questions about Green Agric Nigeria. Learn about buying, selling, payments, delivery, and more.";
include 'includes/header.php';
?>

<style>
/* ============================================
   ROOT VARIABLES & RESET
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
    --radius-md: 16px;
    --radius-lg: 24px;
    --transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ============================================
   HERO SECTION
   ============================================ */
.faq-hero {
    background: var(--primary-gradient);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
}

.faq-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.faq-hero .container {
    position: relative;
    z-index: 2;
}

.faq-hero h1 {
    font-size: 3.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 16px;
}

.faq-hero .lead {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    max-width: 600px;
}

/* Search Bar */
.search-wrapper {
    max-width: 600px;
    margin-top: 30px;
    position: relative;
}

.search-wrapper .search-icon {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 1.1rem;
}

.search-wrapper input {
    width: 100%;
    padding: 18px 20px 18px 50px;
    border: none;
    border-radius: 50px;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    box-shadow: var(--shadow-md);
    transition: var(--transition);
}

.search-wrapper input:focus {
    outline: none;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
}

.search-wrapper input::placeholder {
    color: var(--text-muted);
}

.search-wrapper .clear-btn {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    display: none;
    padding: 4px 8px;
    font-size: 1.1rem;
}

.search-wrapper .clear-btn.visible {
    display: block;
}

/* ============================================
   CATEGORY FILTER TABS
   ============================================ */
.category-filter {
    background: white;
    padding: 20px 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.category-filter .filter-scroll {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding: 4px 0;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}

.category-filter .filter-scroll::-webkit-scrollbar {
    display: none;
}

.filter-btn {
    flex-shrink: 0;
    padding: 10px 24px;
    border: 2px solid rgba(0, 0, 0, 0.08);
    border-radius: 50px;
    background: transparent;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-muted);
    transition: var(--transition);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 15px rgba(13, 110, 63, 0.3);
}

.filter-btn .badge-count {
    background: rgba(0, 0, 0, 0.08);
    padding: 0 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
}

.filter-btn.active .badge-count {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

/* ============================================
   FAQ CONTENT
   ============================================ */
.faq-content {
    padding: 60px 0 80px;
    background: var(--bg-light);
}

.faq-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
}

.faq-category-section {
    display: none;
    animation: fadeSlideUp 0.6s ease-out;
}

.faq-category-section.active {
    display: block;
}

.faq-category-section.show-all {
    display: block;
}

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.category-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid rgba(0, 0, 0, 0.05);
}

.category-header .icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: white;
    flex-shrink: 0;
}

.category-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 0;
}

.category-header .question-count {
    margin-left: auto;
    font-size: 0.85rem;
    color: var(--text-muted);
    background: var(--bg-light);
    padding: 4px 14px;
    border-radius: 50px;
}

/* FAQ Accordion */
.faq-accordion {
    display: grid;
    gap: 12px;
}

.faq-item {
    background: white;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid rgba(0, 0, 0, 0.04);
}

.faq-item:hover {
    box-shadow: var(--shadow-md);
}

.faq-item .question {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    cursor: pointer;
    transition: var(--transition);
    background: white;
    border: none;
    width: 100%;
    text-align: left;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    gap: 16px;
}

.faq-item .question:hover {
    background: var(--bg-light);
}

.faq-item .question .question-text {
    flex: 1;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    line-height: 1.4;
}

.faq-item .question .icon-wrapper {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--bg-light);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.faq-item .question .icon-wrapper i {
    font-size: 1rem;
    color: var(--text-muted);
    transition: var(--transition);
}

.faq-item.active .question .icon-wrapper {
    background: var(--primary);
}

.faq-item.active .question .icon-wrapper i {
    color: white;
    transform: rotate(180deg);
}

.faq-item .answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.faq-item .answer-inner {
    padding: 0 24px 24px;
    color: var(--text-muted);
    line-height: 1.8;
    font-size: 0.95rem;
}

.faq-item.active .answer {
    max-height: 1000px;
}

.faq-item .answer-inner ul {
    margin-top: 10px;
    padding-left: 20px;
}

.faq-item .answer-inner ul li {
    margin-bottom: 6px;
}

/* ============================================
   NO RESULTS STATE
   ============================================ */
.no-results {
    text-align: center;
    padding: 60px 20px;
    display: none;
}

.no-results.visible {
    display: block;
}

.no-results .icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 16px;
}

.no-results h4 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.no-results p {
    color: var(--text-muted);
    max-width: 400px;
    margin: 0 auto;
}

.no-results .btn-contact {
    margin-top: 20px;
    padding: 12px 32px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    transition: var(--transition);
    text-decoration: none;
    display: inline-block;
}

.no-results .btn-contact:hover {
    background: var(--primary-dark);
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    color: white;
}

/* ============================================
   CTA SECTION
   ============================================ */
.faq-cta {
    background: var(--primary-gradient);
    padding: 60px 0;
    text-align: center;
    color: white;
}

.faq-cta h2 {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 16px;
}

.faq-cta p {
    opacity: 0.9;
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto 30px;
}

.faq-cta .btn-cta {
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

.faq-cta .btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    color: var(--primary-dark);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 992px) {
    .faq-hero h1 {
        font-size: 2.8rem;
    }
}

@media (max-width: 768px) {
    .faq-hero {
        padding: 60px 0 40px;
    }
    
    .faq-hero h1 {
        font-size: 2.2rem;
    }
    
    .faq-hero .lead {
        font-size: 1rem;
    }
    
    .search-wrapper input {
        padding: 14px 16px 14px 44px;
        font-size: 0.9rem;
    }
    
    .category-filter {
        padding: 12px 0;
    }
    
    .filter-btn {
        padding: 8px 16px;
        font-size: 0.8rem;
    }
    
    .faq-item .question {
        padding: 16px 18px;
    }
    
    .faq-item .question .question-text {
        font-size: 0.9rem;
    }
    
    .faq-item .answer-inner {
        padding: 0 18px 18px;
        font-size: 0.9rem;
    }
    
    .category-header h3 {
        font-size: 1.2rem;
    }
    
    .faq-cta h2 {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .faq-hero h1 {
        font-size: 1.8rem;
    }
    
    .faq-hero .lead {
        font-size: 0.9rem;
    }
    
    .filter-btn {
        padding: 6px 14px;
        font-size: 0.75rem;
        gap: 4px;
    }
    
    .filter-btn .badge-count {
        font-size: 0.65rem;
        padding: 0 6px;
    }
    
    .faq-item .question {
        padding: 14px 16px;
    }
    
    .faq-item .question .question-text {
        font-size: 0.85rem;
    }
    
    .faq-item .answer-inner {
        font-size: 0.85rem;
    }
    
    .category-header .icon-circle {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .faq-cta h2 {
        font-size: 1.6rem;
    }
    
    .faq-cta .btn-cta {
        padding: 12px 28px;
        font-size: 0.9rem;
    }
}

/* ============================================
   ANIMATIONS
   ============================================ */
.faq-item {
    animation: slideIn 0.4s ease-out both;
}

.faq-item:nth-child(1) { animation-delay: 0.05s; }
.faq-item:nth-child(2) { animation-delay: 0.1s; }
.faq-item:nth-child(3) { animation-delay: 0.15s; }
.faq-item:nth-child(4) { animation-delay: 0.2s; }
.faq-item:nth-child(5) { animation-delay: 0.25s; }
.faq-item:nth-child(6) { animation-delay: 0.3s; }

@keyframes slideIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

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
    .faq-hero {
        min-height: auto;
        padding: 40px 0;
    }
    
    .category-filter,
    .search-wrapper,
    .faq-cta,
    .scroll-progress {
        display: none !important;
    }
    
    .faq-item {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    
    .faq-item .answer {
        max-height: none !important;
        overflow: visible !important;
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
<section class="faq-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h1>Frequently Asked Questions</h1>
                <p class="lead">
                    Find answers to the most common questions about Green Agric Nigeria.
                    Can't find what you're looking for? <a href="<?php echo BASE_URL; ?>/contact" class="text-white fw-bold">Contact us</a>
                </p>
                
                <!-- Search Bar -->
                <div class="search-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="faqSearch" placeholder="Search for answers..." aria-label="Search FAQ">
                    <button class="clear-btn" id="clearSearch" aria-label="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   CATEGORY FILTER
   ============================================ -->
<div class="category-filter" id="categoryFilter">
    <div class="container">
        <div class="filter-scroll" role="tablist">
            <button class="filter-btn active" data-category="all" role="tab" aria-selected="true">
                <i class="bi bi-grid-3x3-gap-fill"></i> All
                <span class="badge-count"><?php echo array_sum(array_map('count', array_column($faq_categories, 'questions'))); ?></span>
            </button>
            <?php foreach ($faq_categories as $category): ?>
                <button class="filter-btn" data-category="cat-<?php echo $category['id']; ?>" role="tab">
                    <i class="bi <?php echo $category['icon']; ?>"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                    <span class="badge-count"><?php echo count($category['questions']); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ============================================
   FAQ CONTENT
   ============================================ -->
<section class="faq-content">
    <div class="container">
        <div class="faq-grid" id="faqGrid">
            <?php foreach ($faq_categories as $category): ?>
                <div class="faq-category-section" id="cat-<?php echo $category['id']; ?>" data-category="cat-<?php echo $category['id']; ?>">
                    <div class="category-header">
                        <div class="icon-circle" style="background: <?php echo $category['color']; ?>;">
                            <i class="bi <?php echo $category['icon']; ?>"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                        <span class="question-count"><?php echo count($category['questions']); ?> questions</span>
                    </div>
                    
                    <div class="faq-accordion">
                        <?php foreach ($category['questions'] as $index => $faq): ?>
                            <div class="faq-item" data-search="<?php echo strtolower(htmlspecialchars($faq['question'] . ' ' . $faq['answer'])); ?>">
                                <button class="question" onclick="toggleFaq(this)" aria-expanded="false">
                                    <span class="question-text"><?php echo htmlspecialchars($faq['question']); ?></span>
                                    <span class="icon-wrapper">
                                        <i class="bi bi-chevron-down"></i>
                                    </span>
                                </button>
                                <div class="answer">
                                    <div class="answer-inner">
                                        <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- No Results -->
            <div class="no-results" id="noResults">
                <div class="icon"><i class="bi bi-search"></i></div>
                <h4>No results found</h4>
                <p>We couldn't find any questions matching your search. Try different keywords or contact our support team.</p>
                <a href="<?php echo BASE_URL; ?>/contact" class="btn-contact">
                    <i class="bi bi-chat-dots me-2"></i> Contact Support
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   CTA SECTION
   ============================================ -->
<section class="faq-cta">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2>Still Have Questions?</h2>
                <p>Our support team is ready to help you with any questions you may have.</p>
                <a href="<?php echo BASE_URL; ?>/contact" class="btn-cta">
                    <i class="bi bi-envelope"></i> Contact Us
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
    // CATEGORY FILTER
    // ============================================
    const filterButtons = document.querySelectorAll('.filter-btn');
    const categorySections = document.querySelectorAll('.faq-category-section');
    const allCategory = document.querySelector('.faq-category-section.show-all');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Update active button
            filterButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            
            const category = this.dataset.category;
            
            // Show/hide categories
            categorySections.forEach(section => {
                if (category === 'all' || section.id === category) {
                    section.classList.add('active');
                    section.style.display = 'block';
                } else {
                    section.classList.remove('active');
                    section.style.display = 'none';
                }
            });
            
            // Hide no results
            document.getElementById('noResults').classList.remove('visible');
            
            // Clear search when switching categories
            const searchInput = document.getElementById('faqSearch');
            if (searchInput.value) {
                searchInput.value = '';
                document.getElementById('clearSearch').classList.remove('visible');
            }
            
            // Reset all FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
                const button = item.querySelector('.question');
                button.setAttribute('aria-expanded', 'false');
            });
        });
    });
    
    // ============================================
    // FAQ ACCORDION TOGGLE
    // ============================================
    window.toggleFaq = function(button) {
        const item = button.closest('.faq-item');
        const isActive = item.classList.contains('active');
        
        // Close other items in the same category
        const parentSection = item.closest('.faq-category-section');
        if (parentSection) {
            parentSection.querySelectorAll('.faq-item.active').forEach(activeItem => {
                if (activeItem !== item) {
                    activeItem.classList.remove('active');
                    const btn = activeItem.querySelector('.question');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        }
        
        // Toggle current item
        item.classList.toggle('active');
        button.setAttribute('aria-expanded', item.classList.contains('active'));
    };
    
    // ============================================
    // SEARCH FUNCTIONALITY
    // ============================================
    const searchInput = document.getElementById('faqSearch');
    const clearBtn = document.getElementById('clearSearch');
    const noResults = document.getElementById('noResults');
    const allItems = document.querySelectorAll('.faq-item');
    const categoryItems = document.querySelectorAll('.faq-category-section');
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        
        // Show/hide clear button
        clearBtn.classList.toggle('visible', query.length > 0);
        
        if (query.length === 0) {
            // Reset to show all
            filterButtons.forEach(btn => {
                if (btn.dataset.category === 'all') {
                    btn.click();
                }
            });
            noResults.classList.remove('visible');
            return;
        }
        
        let found = false;
        
        // Search through all FAQ items
        allItems.forEach(item => {
            const searchText = item.dataset.search || '';
            const matches = searchText.includes(query);
            item.style.display = matches ? 'block' : 'none';
            if (matches) found = true;
        });
        
        // Show/hide categories based on visible items
        categoryItems.forEach(section => {
            const hasVisible = section.querySelector('.faq-item[style*="block"]');
            section.style.display = hasVisible ? 'block' : 'none';
            if (hasVisible) section.classList.add('active');
            else section.classList.remove('active');
        });
        
        // Update filter buttons to show all
        filterButtons.forEach(btn => {
            btn.classList.remove('active');
            btn.setAttribute('aria-selected', 'false');
            if (btn.dataset.category === 'all') {
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
            }
        });
        
        // Show no results if nothing found
        noResults.classList.toggle('visible', !found);
    });
    
    // Clear search
    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        clearBtn.classList.remove('visible');
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl + K or / to focus search
        if ((e.ctrlKey && e.key === 'k') || (e.key === '/' && !e.ctrlKey && !e.metaKey)) {
            e.preventDefault();
            searchInput.focus();
        }
        
        // Escape to clear search
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.blur();
            clearBtn.click();
        }
    });
    
    // ============================================
    // EXPAND ALL / COLLAPSE ALL
    // ============================================
    // Add expand/collapse buttons to each category header
    document.querySelectorAll('.category-header').forEach(header => {
        const section = header.closest('.faq-category-section');
        const items = section.querySelectorAll('.faq-item');
        
        // Create toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'btn btn-sm btn-outline-success ms-auto';
        toggleBtn.style.fontSize = '0.8rem';
        toggleBtn.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Expand All';
        toggleBtn.type = 'button';
        toggleBtn.addEventListener('click', function() {
            const isExpanded = this.innerHTML.includes('Collapse');
            items.forEach(item => {
                const button = item.querySelector('.question');
                if (isExpanded) {
                    item.classList.remove('active');
                    button.setAttribute('aria-expanded', 'false');
                } else {
                    item.classList.add('active');
                    button.setAttribute('aria-expanded', 'true');
                }
            });
            this.innerHTML = isExpanded ? 
                '<i class="bi bi-chevron-down me-1"></i> Expand All' : 
                '<i class="bi bi-chevron-up me-1"></i> Collapse All';
        });
        
        header.appendChild(toggleBtn);
    });
    
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
                const offset = 120;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // ============================================
    // INITIALIZE - Show all categories
    // ============================================
    categoryItems.forEach(section => {
        section.classList.add('active');
        section.style.display = 'block';
    });
});
</script>

<?php include 'includes/footer.php'; ?>