<?php
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

// Contact types
$contact_types = [
    'general' => 'General Inquiry',
    'order' => 'Order Related',
    'product' => 'Product Inquiry',
    'seller' => 'Seller Support',
    'payment' => 'Payment Issue',
    'delivery' => 'Delivery Issue',
    'technical' => 'Technical Support',
    'partnership' => 'Partnership/Business',
    'other' => 'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $contact_type = $_POST['contact_type'] ?? 'general';
    $subject = trim($_POST['subject']);
    $message_text = trim($_POST['message']);
    
    // Validation
    if (empty($subject) || empty($message_text)) {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Please complete all required fields.</strong><br>
            Subject and message are required.
        </div>
        ';
    } elseif (!$is_logged_in && (empty($full_name) || empty($email))) {
        $error = '
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Please provide your contact details.</strong><br>
            Name and email are required for non-logged in users.
        </div>
        ';
    } else {
        // Prepare contact data
        $contact_data = [
            'user_id' => $is_logged_in ? $user_id : null,
            'contact_type' => $contact_type,
            'subject' => $subject,
            'message' => $message_text,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Add user details
        if ($is_logged_in) {
            $contact_data['full_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
            $contact_data['email'] = $user_data['email'];
            $contact_data['phone'] = $user_data['phone'];
        } else {
            $contact_data['full_name'] = $full_name;
            $contact_data['email'] = $email;
            $contact_data['phone'] = $phone ?? '';
        }
        
        // Save to database
        try {
            $contact_id = $db->insert('contacts', $contact_data);
            
            // Send emails using Mailer
            require_once 'classes/Mailer.php';
            
            // Initialize mailer (set debug to false for production, true for testing)
            $mailer = new Mailer(false);
            
            // Send to admin
            $admin_sent = $mailer->sendContactToAdmin($contact_data);
            
            // Send confirmation to user
            $user_sent = $mailer->sendContactToUser($contact_data);
            
            // Generate reference ID
            $reference_id = 'CONTACT-' . date('Ymd') . '-' . str_pad($contact_id, 4, '0', STR_PAD_LEFT);
            
            if ($admin_sent && $user_sent) {
                $message = '
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Thank you for contacting us!</strong><br>
                    <div class="mt-2">
                        <p>✓ <strong>Confirmation email sent to:</strong> ' . htmlspecialchars($contact_data['email']) . '</p>
                        <p>✓ <strong>Reference ID:</strong> ' . $reference_id . '</p>
                        <p>✓ <strong>Our team will respond within 24 hours</strong></p>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Tip:</strong> Check your spam folder if you don\'t see our email.
                            Save your Reference ID for future correspondence.
                        </small>
                    </div>
                </div>
                ';
            } elseif ($admin_sent) {
                $message = '
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Message received successfully!</strong><br>
                    <div class="mt-2">
                        <p>✓ <strong>Our team has received your message</strong></p>
                        <p>⚠ <strong>Reference ID:</strong> ' . $reference_id . ' (Please save this)</p>
                        <p>⚠ <strong>Note:</strong> Confirmation email could not be sent</p>
                    </div>
                    <div class="mt-3">
                        <p>Our support team will contact you at: <strong>' . htmlspecialchars($contact_data['email']) . '</strong></p>
                    </div>
                </div>
                ';
            } else {
                $message = '
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Message saved successfully!</strong><br>
                    <div class="mt-2">
                        <p>✓ <strong>Your message has been saved</strong></p>
                        <p>⚠ <strong>Reference ID:</strong> ' . $reference_id . ' (Please save this)</p>
                        <p>⚠ <strong>Note:</strong> Email service temporarily unavailable</p>
                    </div>
                    <div class="mt-3">
                        <p>We will contact you at: <strong>' . htmlspecialchars($contact_data['email']) . '</strong> within 24-48 hours.</p>
                    </div>
                </div>
                ';
            }
            
            // Clear form for non-logged in users
            if (!$is_logged_in) {
                $_POST = [];
            }
            
        } catch (Exception $e) {
            $error = '
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Oops! Something went wrong.</strong><br>
                Please try again or contact us directly at support@greenagric.ng
                <div class="mt-2">
                    <small>Error: ' . htmlspecialchars($e->getMessage()) . '</small>
                </div>
            </div>
            ';
            error_log("Contact form error: " . $e->getMessage());
        }
    }
}
?>
<?php 
$page_title = "Contact Us";
$page_css = 'contact.css';
include 'includes/header.php'; 
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold text-success">Contact Us</h1>
                <p class="lead">We're here to help you with any questions or concerns</p>
                <?php if ($is_logged_in): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-person-check me-2"></i>
                        You're logged in as <strong><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></strong>. 
                        Your contact details will be automatically filled.
                    </div>
                <?php endif; ?>
            </div>

            <?php echo $message; ?>
            <?php echo $error; ?>

            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="bi bi-envelope me-2"></i> Send us a Message</h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <?php if (!$is_logged_in): ?>
                            <!-- User details for non-logged in users -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                               placeholder="+2348012345678">
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Display user info for logged in users -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Your Name</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>" 
                                               readonly>
                                        <input type="hidden" name="full_name" value="<?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Your Email</label>
                                        <input type="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                                               readonly>
                                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Your Phone</label>
                                        <input type="tel" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['phone']); ?>" 
                                               readonly>
                                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Account Type</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars(ucfirst(getCurrentUserRole())); ?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Contact Type -->
                        <div class="mb-4">
                            <label for="contact_type" class="form-label">What is this regarding? *</label>
                            <select class="form-select" id="contact_type" name="contact_type" required>
                                <option value="">Select an option</option>
                                <?php foreach ($contact_types as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($_POST['contact_type'] ?? '') == $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Subject -->
                        <div class="mb-4">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" 
                                   placeholder="Brief description of your inquiry" required>
                        </div>

                        <!-- Message -->
                        <div class="mb-4">
                            <label for="message" class="form-label">Your Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" 
                                      placeholder="Please provide detailed information about your inquiry..." 
                                      required maxlength="2000"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <div class="form-text">Maximum 2000 characters. Please include relevant details like order numbers if applicable.</div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg py-3">
                                <i class="bi bi-send me-2"></i> Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="row mt-5">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-success text-center">
                        <div class="card-body">
                            <i class="bi bi-geo-alt text-success" style="font-size: 2.5rem;"></i>
                            <h5 class="card-title mt-3">Our Office</h5>
                            <p class="card-text">
                                123 Agric Street<br>
                                Ikeja, Lagos<br>
                                Nigeria
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-success text-center">
                        <div class="card-body">
                            <i class="bi bi-telephone text-success" style="font-size: 2.5rem;"></i>
                            <h5 class="card-title mt-3">Call Us</h5>
                            <p class="card-text">
                                <strong>Customer Support:</strong><br>
                                +234 800 123 4567<br>
                                Mon-Fri: 8AM-6PM<br>
                                Sat: 9AM-4PM
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-success text-center">
                        <div class="card-body">
                            <i class="bi bi-envelope text-success" style="font-size: 2.5rem;"></i>
                            <h5 class="card-title mt-3">Email Us</h5>
                            <p class="card-text">
                                <strong>General Inquiries:</strong><br>
                                info@greenagric.ng<br>
                                <strong>Support:</strong><br>
                                support@greenagric.ng
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="card mt-5">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-question-circle me-2"></i> Frequently Asked Questions</h4>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How long does it take to get a response?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We typically respond within <strong>24-48 hours</strong> during business days (Monday to Friday). 
                                    Urgent inquiries may receive faster responses. Check your spam folder if you don't see our reply.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Can I track my order through customer support?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! Our support team can help you track your order. Please have your <strong>order number</strong> 
                                    ready when contacting us for faster assistance.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Do you provide support for sellers?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, we have dedicated support for sellers. Please select <strong>"Seller Support"</strong> 
                                    in the contact type above. Seller support hours are Monday to Friday, 9AM to 5PM.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    What information should I include in my message?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    For faster resolution, please include:
                                    <ul>
                                        <li>Order number (if related to an order)</li>
                                        <li>Product name and seller</li>
                                        <li>Date of the issue</li>
                                        <li>Any relevant screenshots or details</li>
                                        <li>Your preferred contact method</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>