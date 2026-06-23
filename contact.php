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
$is_logged_in = isLoggedIn();
$user_data = [];

if ($is_logged_in) {
    $user_id = getCurrentUserId();
    $user_data = $db->fetchOne("SELECT first_name, last_name, email, phone FROM users WHERE id = ?", [$user_id]);
}

$message = '';
$error = '';
$form_data = [];

// Contact types with icons
$contact_types = [
    'general' => ['label' => 'General Inquiry', 'icon' => 'bi-chat-dots', 'color' => '#0d6e3f'],
    'order' => ['label' => 'Order Related', 'icon' => 'bi-box-seam', 'color' => '#f59e0b'],
    'product' => ['label' => 'Product Inquiry', 'icon' => 'bi-basket', 'color' => '#2563eb'],
    'seller' => ['label' => 'Seller Support', 'icon' => 'bi-shop', 'color' => '#8b5cf6'],
    'payment' => ['label' => 'Payment Issue', 'icon' => 'bi-credit-card', 'color' => '#dc2626'],
    'delivery' => ['label' => 'Delivery Issue', 'icon' => 'bi-truck', 'color' => '#0891b2'],
    'technical' => ['label' => 'Technical Support', 'icon' => 'bi-gear', 'color' => '#4f46e5'],
    'partnership' => ['label' => 'Partnership/Business', 'icon' => 'bi-handshake', 'color' => '#059669'],
    'other' => ['label' => 'Other', 'icon' => 'bi-three-dots', 'color' => '#6b7280']
];

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-shield-exclamation me-2"></i>
            <strong>Security validation failed.</strong> Please refresh the page and try again.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        // Sanitize inputs
        $full_name = isset($_POST['full_name']) ? trim(strip_tags($_POST['full_name'])) : '';
        $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
        $phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
        $contact_type = isset($_POST['contact_type']) ? preg_replace('/[^a-zA-Z_-]/', '', $_POST['contact_type']) : 'general';
        $subject = isset($_POST['subject']) ? trim(strip_tags($_POST['subject'])) : '';
        $message_text = isset($_POST['message']) ? trim(strip_tags($_POST['message'])) : '';
        
        $form_data = compact('full_name', 'email', 'phone', 'contact_type', 'subject', 'message_text');
        
        // Validation
        $errors = [];
        
        if (empty($subject) || strlen($subject) < 3) {
            $errors[] = 'Please enter a valid subject (minimum 3 characters).';
        }
        
        if (empty($message_text) || strlen($message_text) < 10) {
            $errors[] = 'Please provide more details in your message (minimum 10 characters).';
        }
        
        if (!$is_logged_in) {
            if (empty($full_name) || strlen($full_name) < 2) {
                $errors[] = 'Please provide your full name.';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address.';
            }
        }
        
        if (!isset($contact_types[$contact_type])) {
            $errors[] = 'Please select a valid contact type.';
        }
        
        if (empty($errors)) {
            // Prepare contact data
            $contact_data = [
                'user_id' => $is_logged_in ? $user_id : null,
                'contact_type' => $contact_type,
                'subject' => $subject,
                'message' => $message_text,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'status' => 'new',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if ($is_logged_in) {
                $contact_data['full_name'] = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
                $contact_data['email'] = $user_data['email'];
                $contact_data['phone'] = $user_data['phone'] ?? '';
            } else {
                $contact_data['full_name'] = $full_name;
                $contact_data['email'] = $email;
                $contact_data['phone'] = $phone ?? '';
            }
            
            try {
                $contact_id = $db->insert('contacts', $contact_data);
                
                if (!$contact_id) {
                    throw new Exception('Failed to save contact message');
                }
                
                require_once 'classes/Mailer.php';
                $mailer = new Mailer(false);
                
                $reference_id = 'CONTACT-' . date('Ymd') . '-' . str_pad($contact_id, 4, '0', STR_PAD_LEFT);
                $contact_data['reference_id'] = $reference_id;
                
                $admin_sent = $mailer->sendContactToAdmin($contact_data);
                $user_sent = $mailer->sendContactToUser($contact_data);
                
                $message = '
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong class="fs-5">Thank you for contacting us!</strong>
                            <div class="mt-2">
                                <p class="mb-1"><i class="bi bi-envelope me-2"></i> Confirmation sent to: <strong>' . htmlspecialchars($contact_data['email']) . '</strong></p>
                                <p class="mb-1"><i class="bi bi-hash me-2"></i> Reference ID: <strong class="text-success">' . $reference_id . '</strong></p>
                                <p class="mb-0"><i class="bi bi-clock me-2"></i> Response time: <strong>24-48 hours</strong></p>
                            </div>
                            <div class="mt-3 p-3 bg-light rounded-3">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Tip:</strong> Check your spam folder. Save your Reference ID for future correspondence.
                                </small>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
                
                $form_data = [];
                
            } catch (Exception $e) {
                error_log("Contact form error: " . $e->getMessage());
                $error = '
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Oops! Something went wrong.</strong>
                    <p class="mt-2 mb-0">Please try again or contact us directly at support@greenagric.shop</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            }
        } else {
            $error = '
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mt-2 mb-0">';
            foreach ($errors as $err) {
                $error .= '<li>' . htmlspecialchars($err) . '</li>';
            }
            $error .= '</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
        }
    }
}

$page_title = "Contact Us - Green Agric Nigeria";
$page_css = 'contact.css';
$page_description = "Get in touch with Green Agric Nigeria. Our support team is here to help with any questions about buying, selling, or partnerships.";
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
.contact-hero {
    background: var(--primary-gradient);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
}

.contact-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255,255,255,0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.contact-hero .container {
    position: relative;
    z-index: 2;
}

.contact-hero h1 {
    font-size: 3.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 16px;
}

.contact-hero .lead {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    max-width: 600px;
}

.contact-hero .hero-badge {
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

/* ============================================
   CONTACT FORM - MODERN DESIGN
   ============================================ */
.contact-section {
    padding: 60px 0 80px;
    background: var(--bg-light);
}

.contact-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    align-items: start;
}

/* Form Card */
.contact-form-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 40px;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0, 0, 0, 0.04);
}

.contact-form-card .form-header {
    margin-bottom: 32px;
}

.contact-form-card .form-header h2 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.contact-form-card .form-header p {
    color: var(--text-muted);
}

/* Form Groups */
.form-group {
    margin-bottom: 24px;
}

.form-group label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-dark);
    margin-bottom: 8px;
    display: block;
}

.form-group label .required {
    color: #dc2626;
    margin-left: 2px;
}

.form-group .form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e5e7eb;
    border-radius: var(--radius-sm);
    font-size: 1rem;
    transition: var(--transition);
    background: #fafbfc;
}

.form-group .form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(13, 110, 63, 0.1);
    background: white;
    outline: none;
}

.form-group .form-control.error {
    border-color: #dc2626;
    background: #fef2f2;
}

.form-group .form-control:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
    opacity: 0.7;
}

.form-group .form-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 6px;
}

.form-group .error-text {
    font-size: 0.8rem;
    color: #dc2626;
    margin-top: 4px;
    display: none;
}

.form-group .error-text.visible {
    display: block;
}

/* Contact Type Selector */
.contact-type-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 4px;
}

.contact-type-option {
    position: relative;
}

.contact-type-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.contact-type-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 8px;
    border: 2px solid #e5e7eb;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: var(--transition);
    background: #fafbfc;
    text-align: center;
    font-weight: 500;
    font-size: 0.8rem;
    color: var(--text-dark);
    margin: 0;
    gap: 6px;
}

.contact-type-option label i {
    font-size: 1.3rem;
    color: var(--text-muted);
    transition: var(--transition);
}

.contact-type-option input[type="radio"]:checked + label {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 0 0 4px rgba(13, 110, 63, 0.1);
}

.contact-type-option input[type="radio"]:checked + label i {
    color: var(--primary);
}

.contact-type-option input[type="radio"]:checked + label .check-mark {
    display: block;
}

.contact-type-option label .check-mark {
    display: none;
    position: absolute;
    top: -6px;
    right: -6px;
    width: 20px;
    height: 20px;
    background: var(--primary);
    border-radius: 50%;
    color: white;
    font-size: 0.6rem;
    display: none;
    align-items: center;
    justify-content: center;
}

.contact-type-option input[type="radio"]:checked + label .check-mark {
    display: flex;
}

/* Character Counter */
.char-counter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
}

.char-counter .count {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.char-counter .count.warning {
    color: #f59e0b;
}

.char-counter .count.danger {
    color: #dc2626;
}

/* Submit Button */
.btn-submit {
    width: 100%;
    padding: 16px 32px;
    background: var(--primary-gradient);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 1.1rem;
    font-weight: 600;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
}

.btn-submit:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    color: white;
}

.btn-submit:active {
    transform: translateY(0);
}

.btn-submit .spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

.btn-submit.loading .spinner {
    display: inline-block;
}

.btn-submit.loading .btn-text {
    opacity: 0.8;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ============================================
   CONTACT INFO SIDEBAR
   ============================================ */
.contact-info-sidebar {
    display: grid;
    gap: 24px;
}

.info-card {
    background: white;
    border-radius: var(--radius-md);
    padding: 28px 24px;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: var(--transition);
}

.info-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

.info-card .icon-wrapper {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 16px;
}

.info-card .icon-wrapper.green {
    background: var(--primary-light);
    color: var(--primary);
}

.info-card .icon-wrapper.yellow {
    background: #fef3c7;
    color: #f59e0b;
}

.info-card .icon-wrapper.blue {
    background: #dbeafe;
    color: #2563eb;
}

.info-card .icon-wrapper.purple {
    background: #ede9fe;
    color: #8b5cf6;
}

.info-card h5 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.info-card p {
    color: var(--text-muted);
    margin-bottom: 4px;
    line-height: 1.6;
}

.info-card .contact-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.info-card .contact-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Quick Response Badge */
.quick-response {
    background: white;
    border-radius: var(--radius-md);
    padding: 24px;
    border: 2px dashed var(--primary);
    text-align: center;
}

.quick-response .icon {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 12px;
}

.quick-response h6 {
    font-weight: 700;
    margin-bottom: 4px;
}

.quick-response p {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 0;
}

/* ============================================
   FAQ MINI SECTION
   ============================================ */
.faq-mini {
    margin-top: 60px;
}

.faq-mini .section-title {
    text-align: center;
    margin-bottom: 32px;
}

.faq-mini .section-title h2 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-dark);
}

.faq-mini .section-title p {
    color: var(--text-muted);
}

.faq-mini-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.faq-mini-item {
    background: white;
    padding: 20px 24px;
    border-radius: var(--radius-sm);
    border: 1px solid rgba(0, 0, 0, 0.04);
    transition: var(--transition);
    cursor: pointer;
}

.faq-mini-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
    transform: translateX(4px);
}

.faq-mini-item .question {
    font-weight: 600;
    color: var(--text-dark);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.faq-mini-item .question i {
    color: var(--primary);
    transition: var(--transition);
}

.faq-mini-item .answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.faq-mini-item.active .answer {
    max-height: 200px;
    margin-top: 12px;
}

.faq-mini-item.active .question i {
    transform: rotate(180deg);
}

/* ============================================
   RESPONSIVE DESIGN
   ============================================ */
@media (max-width: 992px) {
    .contact-wrapper {
        grid-template-columns: 1fr;
    }
    
    .contact-hero h1 {
        font-size: 2.8rem;
    }
    
    .contact-type-selector {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .contact-hero {
        padding: 60px 0 40px;
    }
    
    .contact-hero h1 {
        font-size: 2.2rem;
    }
    
    .contact-hero .lead {
        font-size: 1rem;
    }
    
    .contact-form-card {
        padding: 24px;
    }
    
    .contact-type-selector {
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    
    .contact-type-option label {
        padding: 10px 6px;
        font-size: 0.7rem;
    }
    
    .contact-type-option label i {
        font-size: 1rem;
    }
    
    .faq-mini-grid {
        grid-template-columns: 1fr;
    }
    
    .btn-submit {
        padding: 14px 24px;
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .contact-hero h1 {
        font-size: 1.8rem;
    }
    
    .contact-form-card {
        padding: 18px;
    }
    
    .contact-form-card .form-header h2 {
        font-size: 1.4rem;
    }
    
    .contact-type-selector {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .info-card {
        padding: 20px 16px;
    }
    
    .faq-mini-item {
        padding: 16px 18px;
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

.reveal-delay-1 { transition-delay: 0.1s; }
.reveal-delay-2 { transition-delay: 0.2s; }
.reveal-delay-3 { transition-delay: 0.3s; }
.reveal-delay-4 { transition-delay: 0.4s; }

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
<section class="contact-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="hero-badge">
                    <span class="dot"></span>
                    We're Here to Help
                </div>
                <h1>Get in Touch</h1>
                <p class="lead">
                    Have questions about buying, selling, or partnerships? Our support team is ready to assist you.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
   CONTACT SECTION
   ============================================ -->
<section class="contact-section">
    <div class="container">
        <?php if ($is_logged_in): ?>
            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                <i class="bi bi-person-check me-2"></i>
                You're logged in as <strong><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></strong>. 
                Your contact details will be automatically filled.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php echo $message; ?>
        <?php echo $error; ?>

        <div class="contact-wrapper">
            <!-- Contact Form -->
            <div class="contact-form-card reveal">
                <div class="form-header">
                    <h2>Send us a Message</h2>
                    <p>Fill in the form below and we'll get back to you within 24-48 hours.</p>
                </div>

                <form method="POST" action="" id="contactForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <?php if (!$is_logged_in): ?>
                        <div class="form-group">
                            <label for="full_name">Full Name <span class="required">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" 
                                   placeholder="e.g., John Doe" required>
                            <div class="error-text">Please enter your full name.</div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                                   placeholder="e.g., john@example.com" required>
                            <div class="error-text">Please enter a valid email address.</div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" 
                                   placeholder="+234 801 234 5678">
                            <div class="form-text">International format preferred.</div>
                        </div>
                    <?php else: ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Your Name</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>" 
                                           disabled>
                                    <input type="hidden" name="full_name" value="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Your Email</label>
                                    <input type="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                                           disabled>
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Your Phone</label>
                                    <input type="tel" class="form-control" 
                                           value="<?php echo htmlspecialchars($user_data['phone'] ?? 'Not provided'); ?>" 
                                           disabled>
                                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Account Type</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars(ucfirst(getCurrentUserRole())); ?>" 
                                           disabled>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Contact Type -->
                    <div class="form-group">
                        <label>What is this regarding? <span class="required">*</span></label>
                        <div class="contact-type-selector">
                            <?php foreach ($contact_types as $key => $type): ?>
                                <div class="contact-type-option">
                                    <input type="radio" name="contact_type" id="type_<?php echo $key; ?>" 
                                           value="<?php echo $key; ?>"
                                           <?php echo ($form_data['contact_type'] ?? '') == $key ? 'checked' : ''; ?>
                                           <?php echo $key === 'general' && empty($form_data['contact_type']) ? 'checked' : ''; ?>>
                                    <label for="type_<?php echo $key; ?>">
                                        <span class="check-mark"><i class="bi bi-check-lg"></i></span>
                                        <i class="bi <?php echo $type['icon']; ?>"></i>
                                        <span><?php echo $type['label']; ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Subject -->
                    <div class="form-group">
                        <label for="subject">Subject <span class="required">*</span></label>
                        <input type="text" class="form-control" id="subject" name="subject" 
                               value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>" 
                               placeholder="Brief description of your inquiry" required>
                        <div class="error-text">Please enter a subject (minimum 3 characters).</div>
                    </div>

                    <!-- Message -->
                    <div class="form-group">
                        <label for="message">Your Message <span class="required">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="6" 
                                  placeholder="Please provide detailed information about your inquiry..." 
                                  required maxlength="2000"><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                        <div class="char-counter">
                            <span class="form-text">Include relevant details like order numbers if applicable.</span>
                            <span class="count" id="charCount">0 / 2000</span>
                        </div>
                        <div class="error-text">Please provide more details (minimum 10 characters).</div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="spinner"></span>
                        <span class="btn-text">
                            <i class="bi bi-send me-2"></i> Send Message
                        </span>
                    </button>

                    <p class="text-center text-muted small mt-3">
                        <i class="bi bi-shield-lock me-1"></i>
                        Your information is secure and will not be shared with third parties.
                    </p>
                </form>
            </div>

            <!-- Contact Info Sidebar -->
            <div class="contact-info-sidebar">
                <div class="info-card reveal reveal-delay-1">
                    <div class="icon-wrapper green">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                    <h5>Visit Our Office</h5>
                    <p>123 Agric Street<br>Ikeja, Lagos<br>Nigeria</p>
                    <a href="#" class="contact-link">Get Directions <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="info-card reveal reveal-delay-2">
                    <div class="icon-wrapper yellow">
                        <i class="bi bi-telephone"></i>
                    </div>
                    <h5>Call Us</h5>
                    <p><strong>Customer Support:</strong><br>
                    <a href="tel:+2347030419150" class="contact-link">+234 703 041 9150</a></p>
                    <p class="mb-0"><small>Mon-Fri: 8AM-6PM<br>Sat: 9AM-4PM</small></p>
                </div>

                <div class="info-card reveal reveal-delay-3">
                    <div class="icon-wrapper blue">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <h5>Email Us</h5>
                    <p><strong>General Inquiries:</strong><br>
                    <a href="mailto:info@greenagric.shop" class="contact-link">info@greenagric.shop</a></p>
                    <p><strong>Support:</strong><br>
                    <a href="mailto:support@greenagric.shop" class="contact-link">support@greenagric.shop</a></p>
                </div>

                <div class="quick-response reveal reveal-delay-4">
                    <div class="icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h6>Quick Response Time</h6>
                    <p>We typically respond within <strong>24-48 hours</strong> during business days.</p>
                </div>
            </div>
        </div>

        <!-- FAQ Mini Section -->
        <div class="faq-mini reveal">
            <div class="section-title">
                <h2>Frequently Asked Questions</h2>
                <p>Quick answers to common questions</p>
            </div>
            <div class="faq-mini-grid">
                <div class="faq-mini-item" onclick="toggleFaqMini(this)">
                    <div class="question">
                        <span>How long does it take to get a response?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="answer">We typically respond within <strong>24-48 hours</strong> during business days (Monday to Friday). Urgent inquiries may receive faster responses.</div>
                </div>
                <div class="faq-mini-item" onclick="toggleFaqMini(this)">
                    <div class="question">
                        <span>Can I track my order through customer support?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="answer">Yes! Our support team can help you track your order. Please have your <strong>order number</strong> ready when contacting us.</div>
                </div>
                <div class="faq-mini-item" onclick="toggleFaqMini(this)">
                    <div class="question">
                        <span>Do you provide support for sellers?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="answer">Yes, we have dedicated support for sellers. Please select <strong>"Seller Support"</strong> in the contact type above.</div>
                </div>
                <div class="faq-mini-item" onclick="toggleFaqMini(this)">
                    <div class="question">
                        <span>What information should I include in my message?</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="answer">Include order number, product name, date of issue, screenshots, and your preferred contact method for faster resolution.</div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="<?php echo BASE_URL; ?>/faq.php" class="btn btn-outline-success">
                    <i class="bi bi-question-circle me-2"></i> View All FAQs
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
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    });
    
    revealElements.forEach(el => revealObserver.observe(el));

    // ============================================
    // CHARACTER COUNTER
    // ============================================
    const messageInput = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    
    if (messageInput && charCount) {
        function updateCharCount() {
            const length = messageInput.value.length;
            const max = 2000;
            charCount.textContent = length + ' / ' + max;
            
            charCount.classList.remove('warning', 'danger');
            if (length > max - 100) {
                charCount.classList.add('warning');
            }
            if (length > max - 20) {
                charCount.classList.add('danger');
            }
        }
        
        messageInput.addEventListener('input', updateCharCount);
        updateCharCount();
    }

    // ============================================
    // FORM VALIDATION
    // ============================================
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            const errorEl = field.parentElement.querySelector('.error-text');
            if (!field.value.trim()) {
                field.classList.add('error');
                if (errorEl) errorEl.classList.add('visible');
                isValid = false;
            } else {
                field.classList.remove('error');
                if (errorEl) errorEl.classList.remove('visible');
            }
        });
        
        // Validate email
        const emailField = document.getElementById('email');
        if (emailField && emailField.value.trim()) {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailField.value.trim())) {
                emailField.classList.add('error');
                const errorEl = emailField.parentElement.querySelector('.error-text');
                if (errorEl) {
                    errorEl.textContent = 'Please enter a valid email address.';
                    errorEl.classList.add('visible');
                }
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            // Scroll to first error
            const firstError = form.querySelector('.error');
            if (firstError) {
                firstError.focus();
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }
    });

    // ============================================
    // FAQ MINI TOGGLE
    // ============================================
    window.toggleFaqMini = function(element) {
        const isActive = element.classList.contains('active');
        
        // Close others
        document.querySelectorAll('.faq-mini-item').forEach(item => {
            if (item !== element) {
                item.classList.remove('active');
            }
        });
        
        element.classList.toggle('active');
    };

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Escape to close alerts
        if (e.key === 'Escape') {
            document.querySelectorAll('.alert .btn-close').forEach(btn => {
                btn.click();
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
                const offset = 100;
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