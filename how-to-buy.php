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

// Get steps data (could be from database in production)
$steps = [
    [
        'number' => 1,
        'title' => 'Browse & Select',
        'icon' => 'bi-search',
        'color' => '#0d6e3f',
        'description' => 'Explore our wide range of agricultural products from verified farmers and sellers across Nigeria.',
        'details' => [
            'Browse categories or use the search bar to find specific products',
            'Filter by price or product type',
            'Check product details, prices, and seller ratings',
            'Add items to your shopping cart'
        ],
        'image' => 'browse-products.jpg',
        'time' => '5-10 minutes'
    ],
    [
        'number' => 2,
        'title' => 'Review Cart & Checkout',
        'icon' => 'bi-cart-check',
        'color' => '#f59e0b',
        'description' => 'Review your selected items, adjust quantities, and proceed to checkout securely.',
        'details' => [
            'Verify product quantities and prices in your cart',
            'Apply discount codes or promotional offers',
            'Choose your delivery address and preferred delivery date',
            'Review order summary before payment'
        ],
        'image' => 'checkout.jpg',
        'time' => '3-5 minutes'
    ],
    [
        'number' => 3,
        'title' => 'Make Payment',
        'icon' => 'bi-credit-card',
        'color' => '#2563eb',
        'description' => 'Pay securely using your preferred payment method through our trusted payment gateway.',
        'details' => [
            'Choose from multiple payment options (Card, Bank Transfer, USSD, Mobile Money)',
            'All payments are processed securely via Paystack',
            'Your payment is held in escrow until delivery confirmation',
        ],
        'image' => 'payment.jpg',
        'time' => '2-3 minutes'
    ],
    [
        'number' => 4,
        'title' => 'Order Processing',
        'icon' => 'bi-clock-history',
        'color' => '#8b5cf6',
        'description' => 'Your order is confirmed and processed by the seller for fulfillment.',
        'details' => [
            //'Order confirmation sent to your email and SMS',
            'Seller receives order notification and prepares your items',
            'Quality check performed before dispatch',
            'Order status updates available in your account'
        ],
        'image' => 'processing.jpg',
        'time' => '1-24 hours'
    ],
    [
        'number' => 5,
        'title' => 'Delivery & Tracking',
        'icon' => 'bi-truck',
        'color' => '#0891b2',
        'description' => 'Track your order in real-time as it makes its way to your doorstep.',
        'details' => [
            'Receive tracking number once order is dispatched',
            'Track delivery progress in real-time',
            //'Get SMS/Email notifications at each stage',
            'Contact delivery agent directly if needed'
        ],
        'image' => 'delivery.jpg',
        'time' => '1-5 days'
    ],
    [
        'number' => 6,
        'title' => 'Receive & Review',
        'icon' => 'bi-star',
        'color' => '#dc2626',
        'description' => 'Receive your order, inspect the products, and share your experience.',
        'details' => [
            'Inspect products upon delivery',
            'Confirm receipt in your account',
            'Leave a review and rating for the seller',
            'Contact support if you have any issues'
        ],
        'image' => 'review.jpg',
        'time' => '5 minutes'
    ]
];

// Payment methods
$payment_methods = [
    [
        'name' => 'Credit/Debit Card',
        'icon' => 'bi-credit-card-2-front',
        'color' => '#0d6e3f',
        'description' => 'Visa, Mastercard, Verve, and American Express',
        'features' => ['Instant payment', 'Secure encryption', 'Global acceptance']
    ],
    [
        'name' => 'Bank Transfer',
        'icon' => 'bi-bank',
        'color' => '#2563eb',
        'description' => 'Direct bank transfers from any Nigerian bank',
        'features' => ['No transaction fees', 'Widely available', 'Trackable']
    ],
    [
        'name' => 'USSD Payment',
        'icon' => 'bi-phone',
        'color' => '#8b5cf6',
        'description' => 'Pay using USSD codes on any mobile phone',
        'features' => ['No internet required', 'Works on all phones', 'Quick and easy']
    ],
    [
        'name' => 'Mobile Money',
        'icon' => 'bi-wallet2',
        'color' => '#f59e0b',
        'description' => 'Pay using mobile money wallets',
        'features' => ['Convenient', 'Widely used', 'Instant confirmation']
    ]
];

// Delivery options
$delivery_options = [
    [
        'name' => 'Standard Delivery',
        'icon' => 'bi-truck',
        'time' => '3-5 business days',
        'price' => '₦1,500 - ₦5,000',
        'description' => 'Reliable delivery to major cities across Nigeria',
        'features' => ['Trackable', 'Signature required', 'Insurance included']
    ],
    [
        'name' => 'Express Delivery',
        'icon' => 'bi-rocket-takeoff',
        'time' => '1-2 business days',
        'price' => '₦5,000 - ₦15,000',
        'description' => 'Fast delivery for urgent orders in select locations',
        'features' => ['Priority handling', 'Real-time tracking', 'Phone notification']
    ],
    [
        'name' => 'Same-Day Delivery',
        'icon' => 'bi-clock',
        'time' => 'Same day (Lagos only)',
        'price' => '₦8,000 - ₦20,000',
        'description' => 'Get your orders delivered on the same day in Lagos',
        'features' => ['24-hour handling', 'Direct dispatch', 'Live tracking']
    ],
    [
        'name' => 'Pickup Station',
        'icon' => 'bi-geo-alt',
        'time' => '2-3 business days',
        'price' => '₦1,000 - ₦2,500',
        'description' => 'Pick up your order from designated collection points',
        'features' => ['Cost-effective', 'Flexible timing', 'Secure storage']
    ]
];

// FAQ specific to buying process
$buying_faqs = [
    [
        'question' => 'Can I cancel my order after placing it?',
        'answer' => 'Yes, you can cancel your order within 1 hour of placing it. After that, the order enters processing and may not be cancellable. Contact support immediately for assistance.'
    ],
    [
        'question' => 'What if I receive damaged products?',
        'answer' => 'If you receive damaged or incorrect products, contact our support team within 24 hours with photos of the items. We\'ll investigate and arrange for a replacement or refund.'
    ],
    [
        'question' => 'Is my payment information secure?',
        'answer' => 'Yes! All payments are processed through Paystack, a PCI-DSS compliant payment gateway. We never store your card details on our servers.'
    ],
    [
        'question' => 'Can I buy from multiple sellers in one order?',
        'answer' => 'Currently, each order is placed with a single seller. You can place multiple orders from different sellers.'
    ]
];

$page_title = "How to Buy - Green Agric Nigeria";
$page_css = 'how-to-buy.css';
$page_description = "Learn how to buy agricultural products on Green Agric Nigeria. Step-by-step guide to ordering, payment, delivery, and tracking your purchases.";
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
.how-to-hero {
    background: var(--primary-gradient);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
}

.how-to-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.how-to-hero .container {
    position: relative;
    z-index: 2;
}

.how-to-hero h1 {
    font-size: 3.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 16px;
}

.how-to-hero .lead {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    max-width: 600px;
}

.how-to-hero .hero-badge {
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

/* Quick Stats */
.hero-stats {
    display: flex;
    gap: 40px;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.hero-stats .stat {
    text-align: center;
}

.hero-stats .stat .number {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    display: block;
}

.hero-stats .stat .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

/* ============================================
   STEPS SECTION
   ============================================ */
.steps-section {
    padding: 80px 0 100px;
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
    position: relative;
}

.section-header p {
    color: var(--text-muted);
    font-size: 1.1rem;
}

/* Steps Timeline */
.steps-timeline {
    position: relative;
    max-width: 900px;
    margin: 0 auto;
    padding: 20px 0;
}

.steps-timeline::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, var(--primary), var(--primary-light));
    transform: translateX(-50%);
}

.step-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 60px;
    position: relative;
}

.step-item:last-child {
    margin-bottom: 0;
}

.step-item:nth-child(odd) {
    flex-direction: row;
}

.step-item:nth-child(even) {
    flex-direction: row-reverse;
}

.step-item .step-number {
    flex-shrink: 0;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 800;
    position: relative;
    z-index: 2;
    box-shadow: 0 4px 20px rgba(13, 110, 63, 0.3);
    margin: 0 30px;
}

.step-item .step-content {
    flex: 1;
    background: white;
    padding: 32px 36px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: var(--transition);
    position: relative;
}

.step-item .step-content:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-4px);
}

.step-item .step-content .step-icon {
    position: absolute;
    top: -20px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: white;
    box-shadow: var(--shadow-md);
}

.step-item .step-content h3 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 8px;
    font-size: 1.3rem;
}

.step-item .step-content .step-desc {
    color: var(--text-muted);
    margin-bottom: 16px;
    font-size: 0.95rem;
}

.step-item .step-content .step-details {
    list-style: none;
    padding: 0;
    margin: 0;
}

.step-item .step-content .step-details li {
    padding: 6px 0;
    padding-left: 24px;
    position: relative;
    font-size: 0.9rem;
    color: var(--text-muted);
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}

.step-item .step-content .step-details li:last-child {
    border-bottom: none;
}

.step-item .step-content .step-details li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: var(--primary);
    font-weight: 700;
}

.step-item .step-content .step-time {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 4px 14px;
    background: var(--bg-light);
    border-radius: 50px;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.step-item .step-content .step-time i {
    color: var(--primary);
}

/* ============================================
   PAYMENT METHODS
   ============================================ */
.payment-section {
    padding: 80px 0;
    background: white;
}

.payment-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-top: 40px;
}

.payment-card {
    background: var(--bg-light);
    padding: 32px 24px;
    border-radius: var(--radius-md);
    text-align: center;
    transition: var(--transition);
    border: 2px solid transparent;
}

.payment-card:hover {
    border-color: var(--primary);
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
}

.payment-card .icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.8rem;
    color: white;
}

.payment-card h5 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.payment-card .desc {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.payment-card .features {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: left;
}

.payment-card .features li {
    padding: 4px 0;
    font-size: 0.8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}

.payment-card .features li i {
    color: var(--primary);
    font-size: 0.7rem;
}

/* ============================================
   DELIVERY OPTIONS
   ============================================ */
.delivery-section {
    padding: 80px 0;
    background: var(--bg-light);
}

.delivery-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
    margin-top: 40px;
}

.delivery-card {
    background: white;
    padding: 28px 24px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    border: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.delivery-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary-gradient);
    opacity: 0;
    transition: var(--transition);
}

.delivery-card:hover::before {
    opacity: 1;
}

.delivery-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
}

.delivery-card .icon {
    font-size: 2.2rem;
    color: var(--primary);
    margin-bottom: 12px;
}

.delivery-card h5 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.delivery-card .delivery-time {
    font-size: 0.85rem;
    color: var(--primary);
    font-weight: 600;
}

.delivery-card .delivery-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-dark);
    margin: 8px 0;
}

.delivery-card .delivery-desc {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.delivery-card .features {
    list-style: none;
    padding: 0;
    margin: 0;
}

.delivery-card .features li {
    padding: 3px 0;
    font-size: 0.8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 8px;
}

.delivery-card .features li i {
    color: var(--primary);
    font-size: 0.7rem;
}

.delivery-card .badge-popular {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 2px 12px;
    background: #fef3c7;
    color: #d97706;
    border-radius: 50px;
    font-size: 0.65rem;
    font-weight: 600;
}

/* ============================================
   FAQ SECTION
   ============================================ */
.faq-section {
    padding: 80px 0;
    background: white;
}

.faq-grid {
    max-width: 800px;
    margin: 40px auto 0;
    display: grid;
    gap: 16px;
}

.faq-item {
    background: var(--bg-light);
    border-radius: var(--radius-sm);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid transparent;
}

.faq-item:hover {
    border-color: var(--primary);
}

.faq-item .question {
    padding: 20px 24px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    color: var(--text-dark);
    transition: var(--transition);
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    font-size: 1rem;
}

.faq-item .question:hover {
    color: var(--primary);
}

.faq-item .question .icon {
    transition: var(--transition);
    flex-shrink: 0;
}

.faq-item.active .question .icon {
    transform: rotate(180deg);
}

.faq-item .answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease;
}

.faq-item .answer-inner {
    padding: 0 24px 20px;
    color: var(--text-muted);
    line-height: 1.8;
    font-size: 0.95rem;
}

.faq-item.active .answer {
    max-height: 500px;
}

/* ============================================
   CTA SECTION
   ============================================ */
.cta-section {
    background: var(--primary-gradient);
    padding: 70px 0;
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
    font-size: 2.8rem;
    font-weight: 800;
    margin-bottom: 16px;
}

.cta-section p {
    opacity: 0.9;
    font-size: 1.15rem;
    max-width: 500px;
    margin: 0 auto 32px;
}

.cta-section .btn-cta {
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

.cta-section .btn-cta:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    color: var(--primary-dark);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 1200px) {
    .payment-grid,
    .delivery-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .how-to-hero h1 {
        font-size: 2.8rem;
    }
    
    .steps-timeline::before {
        left: 30px;
    }
    
    .step-item {
        flex-direction: column !important;
        padding-left: 70px;
    }
    
    .step-item .step-number {
        position: absolute;
        left: 0;
        top: 0;
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
        margin: 0;
    }
    
    .step-item .step-content {
        margin-left: 0;
    }
    
    .step-item .step-content .step-icon {
        top: -15px;
        right: 15px;
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}

@media (max-width: 768px) {
    .how-to-hero {
        padding: 60px 0 40px;
    }
    
    .how-to-hero h1 {
        font-size: 2.2rem;
    }
    
    .how-to-hero .lead {
        font-size: 1rem;
    }
    
    .hero-stats {
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .hero-stats .stat .number {
        font-size: 1.5rem;
    }
    
    .section-header h2 {
        font-size: 2rem;
    }
    
    .step-item .step-content {
        padding: 24px 20px;
    }
    
    .step-item .step-content h3 {
        font-size: 1.1rem;
    }
    
    .payment-grid,
    .delivery-grid {
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .payment-card {
        padding: 24px 16px;
    }
    
    .delivery-card {
        padding: 20px 16px;
    }
    
    .cta-section h2 {
        font-size: 2rem;
    }
}

@media (max-width: 576px) {
    .how-to-hero h1 {
        font-size: 1.8rem;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .hero-stats .stat {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .section-header h2 {
        font-size: 1.6rem;
    }
    
    .step-item {
        padding-left: 60px;
    }
    
    .step-item .step-number {
        width: 44px;
        height: 44px;
        font-size: 1rem;
    }
    
    .step-item .step-content {
        padding: 20px 16px;
    }
    
    .step-item .step-content .step-details li {
        font-size: 0.85rem;
        padding-left: 20px;
    }
    
    .payment-grid,
    .delivery-grid {
        grid-template-columns: 1fr;
    }
    
    .payment-card .features {
        text-align: center;
    }
    
    .payment-card .features li {
        justify-content: center;
    }
    
    .delivery-card .features li {
        justify-content: center;
    }
    
    .faq-item .question {
        padding: 16px 18px;
        font-size: 0.9rem;
    }
    
    .faq-item .answer-inner {
        padding: 0 18px 16px;
        font-size: 0.85rem;
    }
    
    .cta-section h2 {
        font-size: 1.6rem;
    }
    
    .cta-section .btn-cta {
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
    .how-to-hero {
        padding: 40px 0;
        min-height: auto;
    }
    
    .step-item .step-content:hover {
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
<section class="how-to-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="hero-badge">
                    <span class="dot"></span>
                    Easy & Secure Shopping
                </div>
                <h1>How to <span style="color: #fcd34d;">Buy</span></h1>
                <p class="lead">
                    A simple step-by-step guide to ordering fresh agricultural products from our platform.
                    From browsing to delivery — we make it easy.
                </p>
                <div class="hero-stats">
                    <div class="stat">
                        <span class="number">6</span>
                        <span class="label">Simple Steps</span>
                    </div>
                    <div class="stat">
                        <span class="number"><?php echo count($payment_methods); ?></span>
                        <span class="label">Payment Options</span>
                    </div>
                    <div class="stat">
                        <span class="number"><?php echo count($delivery_options); ?></span>
                        <span class="label">Delivery Methods</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   STEPS SECTION
   ============================================ -->
<section class="steps-section">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-list-check me-1"></i> Guide</span>
            <h2>How It <span class="highlight">Works</span></h2>
            <p>Follow these simple steps to complete your purchase</p>
        </div>

        <div class="steps-timeline">
            <?php foreach ($steps as $index => $step): ?>
                <div class="step-item reveal reveal-delay-<?php echo ($index % 6) + 1; ?>">
                    <div class="step-number"><?php echo $step['number']; ?></div>
                    <div class="step-content">
                        <div class="step-icon" style="background: <?php echo $step['color']; ?>;">
                            <i class="bi <?php echo $step['icon']; ?>"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($step['title']); ?></h3>
                        <p class="step-desc"><?php echo htmlspecialchars($step['description']); ?></p>
                        <ul class="step-details">
                            <?php foreach ($step['details'] as $detail): ?>
                                <li><?php echo htmlspecialchars($detail); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="step-time">
                            <i class="bi bi-clock"></i>
                            Estimated time: <?php echo $step['time']; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================
   PAYMENT METHODS
   ============================================ -->
<section class="payment-section">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-credit-card me-1"></i> Payments</span>
            <h2>Secure <span class="highlight">Payment</span> Options</h2>
            <p>Choose from multiple secure payment methods</p>
        </div>

        <div class="payment-grid">
            <?php foreach ($payment_methods as $index => $method): ?>
                <div class="payment-card reveal reveal-delay-<?php echo ($index % 4) + 1; ?>">
                    <div class="icon-wrapper" style="background: <?php echo $method['color']; ?>;">
                        <i class="bi <?php echo $method['icon']; ?>"></i>
                    </div>
                    <h5><?php echo htmlspecialchars($method['name']); ?></h5>
                    <p class="desc"><?php echo htmlspecialchars($method['description']); ?></p>
                    <ul class="features">
                        <?php foreach ($method['features'] as $feature): ?>
                            <li><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================
   DELIVERY OPTIONS
   ============================================ -->
<!--   
<section class="delivery-section">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-truck me-1"></i> Delivery</span>
            <h2>Flexible <span class="highlight">Delivery</span> Options</h2>
            <p>Choose the delivery method that works best for you</p>
        </div>

        <div class="delivery-grid">
            <?php foreach ($delivery_options as $index => $option): ?>
                <div class="delivery-card reveal reveal-delay-<?php echo ($index % 4) + 1; ?>">
                    <?php if ($index === 0): ?>
                        <span class="badge-popular"><i class="bi bi-star-fill me-1"></i> Most Popular</span>
                    <?php endif; ?>
                    <div class="icon"><i class="bi <?php echo $option['icon']; ?>"></i></div>
                    <h5><?php echo htmlspecialchars($option['name']); ?></h5>
                    <div class="delivery-time"><i class="bi bi-clock me-1"></i> <?php echo $option['time']; ?></div>
                    <div class="delivery-price"><?php echo $option['price']; ?></div>
                    <p class="delivery-desc"><?php echo htmlspecialchars($option['description']); ?></p>
                    <ul class="features">
                        <?php foreach ($option['features'] as $feature): ?>
                            <li><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
                        -->
<!-- ============================================
   FAQ SECTION
   ============================================ -->
<section class="faq-section">
    <div class="container">
        <div class="section-header reveal">
            <span class="tag"><i class="bi bi-question-circle me-1"></i> FAQ</span>
            <h2>Common <span class="highlight">Questions</span></h2>
            <p>Quick answers to common buying questions</p>
        </div>

        <div class="faq-grid">
            <?php foreach ($buying_faqs as $index => $faq): ?>
                <div class="faq-item reveal reveal-delay-<?php echo ($index % 4) + 1; ?>">
                    <button class="question" onclick="toggleFaq(this)">
                        <span><?php echo htmlspecialchars($faq['question']); ?></span>
                        <span class="icon"><i class="bi bi-chevron-down"></i></span>
                    </button>
                    <div class="answer">
                        <div class="answer-inner"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
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
                <h2>Ready to Start Shopping?</h2>
                <p>Browse our wide range of fresh agricultural products and place your order today.</p>
                <a href="<?php echo BASE_URL; ?>/buyer/products/browse.php" class="btn-cta">
                    <i class="bi bi-basket"></i> Start Shopping
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
    // FAQ TOGGLE
    // ============================================
    window.toggleFaq = function(button) {
        const item = button.closest('.faq-item');
        const isActive = item.classList.contains('active');
        
        // Close other FAQs
        document.querySelectorAll('.faq-item.active').forEach(activeItem => {
            if (activeItem !== item) {
                activeItem.classList.remove('active');
            }
        });
        
        item.classList.toggle('active');
    };

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.faq-item.active').forEach(item => {
                item.classList.remove('active');
            });
        }
    });

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