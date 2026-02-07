<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

$page_title = "Green Agric Policies";
$page_css = 'style.css';
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="text-center">
        <h1 class="display-5 fw-bold text-success mb-3">Green Agric Policies</h1>
        <p class="text-muted">Updated: February 1st, 2026</p>
    </div>

    <!-- Quick Navigation -->
    <div class="sticky-top bg-white py-3 border-bottom mb-4" style="top: 70px; z-index: 1000;">
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="#acceptable-use" class="btn btn-sm btn-outline-success">Acceptable Use</a>
            <a href="#refund" class="btn btn-sm btn-outline-success">Refund Policy</a>
            <a href="#dispute" class="btn btn-sm btn-outline-success">Dispute Resolution</a>
            <a href="#terms" class="btn btn-sm btn-outline-success">Terms of Service</a>
            <a href="#privacy" class="btn btn-sm btn-outline-success">Privacy Policy</a>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Navigation (Desktop) -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="sticky-top" style="top: 140px;">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-file-text me-2"></i> Policy Sections</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="#acceptable-use" class="list-group-item list-group-item-action">
                            <i class="bi bi-shield-check me-2"></i> Acceptable Use Policy
                        </a>
                        <a href="#refund" class="list-group-item list-group-item-action">
                            <i class="bi bi-arrow-counterclockwise me-2"></i> Refund Policy
                        </a>
                        <a href="#dispute" class="list-group-item list-group-item-action">
                            <i class="bi bi-people me-2"></i> Dispute Resolution
                        </a>
                        <a href="#terms" class="list-group-item list-group-item-action">
                            <i class="bi bi-file-earmark-text me-2"></i> Terms of Service
                        </a>
                        <a href="#privacy" class="list-group-item list-group-item-action">
                            <i class="bi bi-lock me-2"></i> Privacy Policy
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Acceptable Use Policy -->
            <section id="acceptable-use" class="policy-section mb-5">
                <div class="card border-success border-2">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">
                            <i class="bi bi-shield-check me-2"></i> Acceptable Use Policy
                        </h3>
                        <span class="badge bg-light text-success">Effective: Feb 1, 2026</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Green Agric</strong> ("we", "our", "us") operates an online agricultural marketplace that connects third-party sellers ("Sellers") with buyers ("Buyers"). This Acceptable Use Policy ("Policy") governs the use of the Green Agric platform.
                        </div>
                        
                        <p class="mb-4">By accessing or using the platform, all users agree to comply with this Policy.</p>
                        
                        <div class="accordion" id="acceptableUseAccordion">
                            <!-- Section 1 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#section1">
                                        <strong>1. Permitted Use</strong>
                                    </button>
                                </h4>
                                <div id="section1" class="accordion-collapse collapse" data-bs-parent="#acceptableUseAccordion">
                                    <div class="accordion-body">
                                        <p>Green Agric may only be used for:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Listing, selling, and purchasing agricultural produce and related permitted goods</li>
                                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Lawful transactions conducted in good faith</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Activities that comply with all applicable Nigerian laws and regulations</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 2 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#section2">
                                        <strong>2. Prohibited Activities</strong>
                                    </button>
                                </h4>
                                <div id="section2" class="accordion-collapse collapse" data-bs-parent="#acceptableUseAccordion">
                                    <div class="accordion-body">
                                        <p>Users must not use the platform to:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Sell illegal, restricted, or prohibited goods</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Sell counterfeit, stolen, or misrepresented products</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> List products outside approved agricultural categories</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Engage in fraud, deception, or false advertising</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Use false, misleading, or stolen identity or business information</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Manipulate pricing, orders, reviews, or platform metrics</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Circumvent platform fees, payment flows, or escrow mechanisms</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Engage in money laundering, terrorism financing, or related financial crimes</li>
                                            <li><i class="bi bi-x-circle-fill text-danger me-2"></i> Use the platform in violation of Paystack's Acceptable Use Policy or Nigerian law</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 3 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#section3">
                                        <strong>3. Enforcement & Monitoring</strong>
                                    </button>
                                </h4>
                                <div id="section3" class="accordion-collapse collapse" data-bs-parent="#acceptableUseAccordion">
                                    <div class="accordion-body">
                                        <p>Green Agric reserves the right to:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-shield-fill text-primary me-2"></i> Review and monitor seller activity and transactions</li>
                                            <li class="mb-2"><i class="bi bi-shield-fill text-primary me-2"></i> Suspend or terminate accounts without notice</li>
                                            <li class="mb-2"><i class="bi bi-shield-fill text-primary me-2"></i> Remove listings or restrict platform access</li>
                                            <li class="mb-2"><i class="bi bi-shield-fill text-primary me-2"></i> Withhold or reverse settlements</li>
                                            <li><i class="bi bi-shield-fill text-primary me-2"></i> Report suspicious activity to payment processors or regulatory authorities</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 4 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#section4">
                                        <strong>4. Reporting Violations</strong>
                                    </button>
                                </h4>
                                <div id="section4" class="accordion-collapse collapse" data-bs-parent="#acceptableUseAccordion">
                                    <div class="accordion-body">
                                        <p>Violations of this Policy may be reported to:</p>
                                        <div class="alert alert-light border mt-3">
                                            <i class="bi bi-envelope-fill text-success me-2"></i>
                                            <strong>Email:</strong> support@greenagric.org
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Refund Policy -->
            <section id="refund" class="policy-section mb-5">
                <div class="card border-warning border-2">
                    <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">
                            <i class="bi bi-arrow-counterclockwise me-2"></i> Refund Policy
                        </h3>
                        <span class="badge bg-light text-warning">Effective: Feb 1, 2026</span>
                    </div>
                    <div class="card-body">
                        <p class="mb-4">Green Agric is committed to transparent and fair transactions while recognizing the nature of agricultural goods.</p>
                        
                        <div class="accordion" id="refundAccordion">
                            <!-- Section 1 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#refundSection1">
                                        <strong>1. Refund Eligibility</strong>
                                    </button>
                                </h4>
                                <div id="refundSection1" class="accordion-collapse collapse" data-bs-parent="#refundAccordion">
                                    <div class="accordion-body">
                                        <p>Buyers may request a refund if:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> An order is not delivered</li>
                                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Incorrect goods are delivered</li>
                                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Produce arrives spoiled, damaged, or unsafe for consumption</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Goods materially differ from the seller's description</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 2 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#refundSection2">
                                        <strong>2. Refund Process</strong>
                                    </button>
                                </h4>
                                <div id="refundSection2" class="accordion-collapse collapse" data-bs-parent="#refundAccordion">
                                    <div class="accordion-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-clock-fill text-primary me-2"></i> Refund requests must be submitted within the designated dispute window</li>
                                            <li class="mb-2"><i class="bi bi-clock-fill text-primary me-2"></i> Green Agric reviews transaction details and supporting evidence</li>
                                            <li class="mb-2"><i class="bi bi-clock-fill text-primary me-2"></i> Approved refunds are processed through the original payment method</li>
                                            <li><i class="bi bi-clock-fill text-primary me-2"></i> Refund timelines are subject to Paystack's processing rules</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 3 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#refundSection3">
                                        <strong>3. Non-Refundable Situations</strong>
                                    </button>
                                </h4>
                                <div id="refundSection3" class="accordion-collapse collapse" data-bs-parent="#refundAccordion">
                                    <div class="accordion-body">
                                        <p>Refunds may not be issued for:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Change of mind after delivery</li>
                                            <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Minor natural variations in agricultural produce</li>
                                            <li><i class="bi bi-x-circle-fill text-danger me-2"></i> Orders confirmed as delivered and accepted by the buyer</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 4 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#refundSection4">
                                        <strong>4. Seller Accountability</strong>
                                    </button>
                                </h4>
                                <div id="refundSection4" class="accordion-collapse collapse" data-bs-parent="#refundAccordion">
                                    <div class="accordion-body">
                                        <p>Sellers with repeated refund claims may face:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Account warnings</li>
                                            <li class="mb-2"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Payout delays</li>
                                            <li><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> Suspension or permanent removal</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Dispute Resolution -->
            <section id="dispute" class="policy-section mb-5">
                <div class="card border-info border-2">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">
                            <i class="bi bi-people me-2"></i> Dispute Resolution Policy
                        </h3>
                        <span class="badge bg-light text-info">Effective: Feb 1, 2026</span>
                    </div>
                    <div class="card-body">
                        <p class="mb-4">This policy outlines how disputes between buyers and sellers are handled.</p>
                        
                        <div class="accordion" id="disputeAccordion">
                            <!-- Section 1 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#disputeSection1">
                                        <strong>1. Raising a Dispute</strong>
                                    </button>
                                </h4>
                                <div id="disputeSection1" class="accordion-collapse collapse" data-bs-parent="#disputeAccordion">
                                    <div class="accordion-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-chat-left-text-fill text-info me-2"></i> Buyers may submit disputes within the allowed timeframe via their account dashboard</li>
                                            <li><i class="bi bi-chat-left-text-fill text-info me-2"></i> Disputed funds are temporarily withheld</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 2 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#disputeSection2">
                                        <strong>2. Investigation Process</strong>
                                    </button>
                                </h4>
                                <div id="disputeSection2" class="accordion-collapse collapse" data-bs-parent="#disputeAccordion">
                                    <div class="accordion-body">
                                        <p>Green Agric reviews:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-search text-info me-2"></i> Order and payment records</li>
                                            <li class="mb-2"><i class="bi bi-search text-info me-2"></i> Delivery confirmation</li>
                                            <li class="mb-2"><i class="bi bi-search text-info me-2"></i> Communication between buyer and seller</li>
                                            <li><i class="bi bi-search text-info me-2"></i> Supporting evidence provided by both parties</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 3 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#disputeSection3">
                                        <strong>3. Resolution</strong>
                                    </button>
                                </h4>
                                <div id="disputeSection3" class="accordion-collapse collapse" data-bs-parent="#disputeAccordion">
                                    <div class="accordion-body">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="bi bi-gavel text-info me-2"></i> Green Agric issues a final and binding decision</li>
                                            <li class="mb-2"><i class="bi bi-gavel text-info me-2"></i> Funds are released or refunded based on the outcome</li>
                                            <li><i class="bi bi-gavel text-info me-2"></i> Sellers with frequent disputes may be suspended or removed</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section 4 -->
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#disputeSection4">
                                        <strong>4. Platform Authority</strong>
                                    </button>
                                </h4>
                                <div id="disputeSection4" class="accordion-collapse collapse" data-bs-parent="#disputeAccordion">
                                    <div class="accordion-body">
                                        <p>Green Agric acts as a neutral facilitator and reserves the right to resolve disputes in a manner that protects marketplace integrity and payment security.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Terms of Service -->
            <section id="terms" class="policy-section mb-5">
                <div class="card border-primary border-2">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i> Terms of Service
                        </h3>
                        <span class="badge bg-light text-primary">Effective: Feb 1, 2026</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-primary mb-4">
                            <i class="bi bi-file-text me-2"></i>
                            These Terms of Service ("Terms") govern the use of the Green Agric platform.
                        </div>
                        
                        <div class="accordion" id="termsAccordion">
                            <?php
                            $termsSections = [
                                '1. Platform Overview' => 'Green Agric operates an online marketplace that enables third-party sellers to offer agricultural products directly to buyers. Green Agric is not the seller of goods and does not own or control listed products.',
                                '2. User Accounts' => '• Users must provide accurate and complete information<br>• Sellers must pass identity and compliance verification<br>• Green Agric may suspend or terminate accounts for violations',
                                '3. Payments' => '• Payments are processed through Paystack<br>• Funds may be temporarily held pending order fulfillment<br>• Seller payouts are subject to successful delivery and dispute review',
                                '4. Seller Obligations' => 'Sellers are responsible for:<br>• Accurate product descriptions<br>• Timely fulfillment and delivery<br>• Compliance with applicable laws and platform policies',
                                '5. Buyer Obligations' => 'Buyers agree to:<br>• Provide accurate delivery information<br>• Raise disputes within the allowed timeframe<br>• Use the platform in good faith',
                                '6. Refunds & Disputes' => 'Refunds and disputes are governed by the Refund Policy and Dispute Resolution Policy published on the platform.',
                                '7. Limitation of Liability' => 'Green Agric:<br>• Does not guarantee product quality or delivery<br>• Is not liable for seller misconduct<br>• Acts solely as a transaction facilitator',
                                '8. Compliance & Monitoring' => 'Green Agric reserves the right to:<br>• Monitor transactions<br>• Conduct enhanced due diligence<br>• Report suspicious activity to Paystack or regulatory authorities',
                                '9. Termination' => 'Green Agric may suspend or terminate access without notice for:<br>• Policy violations<br>• Fraud or illegal activity<br>• Risk to platform or payment partners',
                                '10. Governing Law' => 'These Terms are governed by the laws of the Federal Republic of Nigeria.'
                            ];
                            
                            $i = 1;
                            foreach ($termsSections as $title => $content):
                            ?>
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#termsSection<?php echo $i; ?>">
                                        <strong><?php echo $title; ?></strong>
                                    </button>
                                </h4>
                                <div id="termsSection<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#termsAccordion">
                                    <div class="accordion-body">
                                        <?php echo $content; ?>
                                    </div>
                                </div>
                            </div>
                            <?php $i++; endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Privacy Policy -->
            <section id="privacy" class="policy-section">
                <div class="card border-secondary border-2">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">
                            <i class="bi bi-lock me-2"></i> Privacy Policy
                        </h3>
                        <span class="badge bg-light text-secondary">Effective: Feb 1, 2026</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-secondary mb-4">
                            <i class="bi bi-shield-lock me-2"></i>
                            <strong>Green Agric</strong> ("we", "our", "us") is committed to protecting the privacy and personal data of users ("you", "your") who access or use our platform.
                        </div>
                        
                        <p class="mb-4">This Privacy Policy explains how we collect, use, store, and protect personal information in connection with our agricultural marketplace services.</p>
                        
                        <div class="accordion" id="privacyAccordion">
                            <?php
                            $privacySections = [
                                '1. Information We Collect' => '
                                    <p>Green Agric collects only personal data that is necessary for the operation of the platform, compliance with regulatory obligations, and protection against fraud. Personal data is not processed in a manner incompatible with these purposes:</p>
                                    
                                    <h6 class="mt-3">a. Personal Information</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-1"><i class="bi bi-person-fill text-secondary me-2"></i> Full name</li>
                                        <li class="mb-1"><i class="bi bi-envelope-fill text-secondary me-2"></i> Email address</li>
                                        <li class="mb-1"><i class="bi bi-telephone-fill text-secondary me-2"></i> Phone number</li>
                                        <li class="mb-1"><i class="bi bi-card-text text-secondary me-2"></i> Government-issued identification (for sellers)</li>
                                        <li class="mb-1"><i class="bi bi-building text-secondary me-2"></i> Business registration documents (where applicable)</li>
                                        <li><i class="bi bi-bank text-secondary me-2"></i> Bank account details for seller payouts</li>
                                    </ul>
                                    
                                    <h6 class="mt-3">b. Transaction Information</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-1"><i class="bi bi-cart-fill text-secondary me-2"></i> Order details</li>
                                        <li class="mb-1"><i class="bi bi-credit-card-fill text-secondary me-2"></i> Payment status</li>
                                        <li class="mb-1"><i class="bi bi-truck text-secondary me-2"></i> Delivery confirmations</li>
                                        <li><i class="bi bi-arrow-repeat text-secondary me-2"></i> Refund and dispute records</li>
                                    </ul>
                                    
                                    <h6 class="mt-3">c. Technical Information</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-1"><i class="bi bi-globe text-secondary me-2"></i> IP address</li>
                                        <li class="mb-1"><i class="bi bi-laptop text-secondary me-2"></i> Device and browser information</li>
                                        <li><i class="bi bi-graph-up text-secondary me-2"></i> Log data and usage activity</li>
                                    </ul>
                                ',
                                '2. How We Use Your Information' => '
                                    <p>We use collected information to:</p>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-check-square text-secondary me-2"></i> Verify user identities and conduct KYC checks</li>
                                        <li class="mb-2"><i class="bi bi-check-square text-secondary me-2"></i> Facilitate marketplace transactions</li>
                                        <li class="mb-2"><i class="bi bi-check-square text-secondary me-2"></i> Process payments and payouts through Paystack</li>
                                        <li class="mb-2"><i class="bi bi-check-square text-secondary me-2"></i> Prevent fraud and unauthorized activity</li>
                                        <li class="mb-2"><i class="bi bi-check-square text-secondary me-2"></i> Resolve disputes and process refunds</li>
                                        <li class="mb-2"><i class="bi bi-check-square text-secondary me-2"></i> Comply with legal and regulatory obligations</li>
                                        <li><i class="bi bi-check-square text-secondary me-2"></i> Improve platform functionality and user experience</li>
                                    </ul>
                                ',
                                '3. Payment Processing' => 'All payments on Green Agric are processed by Paystack, a third-party payment service provider. Green Agric does not store users\' full card or sensitive payment details. Payment information is handled directly by Paystack in accordance with their security standards and privacy practices.',
                                '4. Data Sharing & Disclosure' => '
                                    <p>We may share personal information with:</p>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-share text-secondary me-2"></i> Payment processors (including Paystack)</li>
                                        <li class="mb-2"><i class="bi bi-share text-secondary me-2"></i> Regulatory or law enforcement authorities, where required by law</li>
                                        <li><i class="bi bi-share text-secondary me-2"></i> Service providers supporting platform operations (under confidentiality obligations)</li>
                                    </ul>
                                    <p class="mt-3">We do not sell or rent personal data to third parties.</p>
                                ',
                                '5. Data Retention' => '
                                    <p>We retain personal information only for as long as necessary to:</p>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-clock-history text-secondary me-2"></i> Provide services</li>
                                        <li class="mb-2"><i class="bi bi-clock-history text-secondary me-2"></i> Fulfill legal, accounting, and regulatory requirements</li>
                                        <li><i class="bi bi-clock-history text-secondary me-2"></i> Resolve disputes and enforce agreements</li>
                                    </ul>
                                    <p class="mt-3">When data is no longer required, it is securely deleted or anonymized.</p>
                                ',
                                '6. Data Security' => '
                                    <p>Green Agric implements appropriate technical and organizational measures to protect personal information against:</p>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-shield-fill text-secondary me-2"></i> Unauthorized access</li>
                                        <li class="mb-2"><i class="bi bi-shield-fill text-secondary me-2"></i> Loss or misuse</li>
                                        <li><i class="bi bi-shield-fill text-secondary me-2"></i> Alteration or disclosure</li>
                                    </ul>
                                    <p class="mt-3">Access to personal data is restricted to authorized personnel only.</p>
                                ',
                                '7. User Rights' => '
                                    <p>In accordance with the Nigeria Data Protection Regulation (NDPR), users have the right to:</p>
                                    <ul class="list-unstyled">
                                        <li class="mb-2"><i class="bi bi-eye-fill text-secondary me-2"></i> Request access to their personal data</li>
                                        <li class="mb-2"><i class="bi bi-pencil-fill text-secondary me-2"></i> Request correction of inaccurate or incomplete data</li>
                                        <li class="mb-2"><i class="bi bi-trash-fill text-secondary me-2"></i> Request deletion of personal data, subject to legal and regulatory requirements</li>
                                        <li class="mb-2"><i class="bi bi-slash-circle-fill text-secondary me-2"></i> Object to or restrict certain data processing activities</li>
                                        <li><i class="bi bi-x-circle-fill text-secondary me-2"></i> Withdraw consent where processing is based on consent</li>
                                    </ul>
                                    <p class="mt-3">Requests can be made by contacting us using the details below.</p>
                                ',
                                '8. Cookies & Tracking' => 'Green Agric may use cookies or similar technologies to:<br>• Improve platform functionality<br>• Analyze usage patterns<br>• Enhance user experience<br><br>Users may control cookie preferences through their browser settings.',
                                '9. Third-Party Links' => 'The platform may contain links to third-party websites. Green Agric is not responsible for the privacy practices of external sites.',
                                '10. Changes to This Policy' => 'Green Agric may update this Privacy Policy from time to time. Updated versions will be published on our website with a revised effective date.<br><br>This Privacy Policy is designed to comply with the Nigeria Data Protection Regulation (NDPR) and applicable data protection laws.',
                                '11. Contact Information' => '
                                    <div class="alert alert-light border mt-3">
                                        <i class="bi bi-envelope-fill text-secondary me-2"></i>
                                        <strong>Email:</strong> support@greenagric.org
                                    </div>
                                '
                            ];
                            
                            $j = 1;
                            foreach ($privacySections as $title => $content):
                            ?>
                            <div class="accordion-item border-0">
                                <h4 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#privacySection<?php echo $j; ?>">
                                        <strong><?php echo $title; ?></strong>
                                    </button>
                                </h4>
                                <div id="privacySection<?php echo $j; ?>" class="accordion-collapse collapse" data-bs-parent="#privacyAccordion">
                                    <div class="accordion-body">
                                        <?php echo $content; ?>
                                    </div>
                                </div>
                            </div>
                            <?php $j++; endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Back to Top -->
            <div class="text-center mt-5">
                <a href="#" class="btn btn-outline-success">
                    <i class="bi bi-arrow-up me-2"></i> Back to Top
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Mobile-First Styles */
.policy-section {
    scroll-margin-top: 120px;
}

.accordion-button {
    font-size: 0.95rem;
    padding: 0.75rem 1rem;
}

.accordion-button:not(.collapsed) {
    background-color: #f8f9fa;
    color: #198754;
    box-shadow: none;
}

.accordion-button:focus {
    box-shadow: none;
    border-color: #198754;
}

.accordion-body {
    font-size: 0.9rem;
    padding: 1rem;
}

.card {
    border-radius: 10px;
    overflow: hidden;
}

.card-header {
    font-weight: 600;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Sticky navigation */
.sticky-top {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95);
}

/* Mobile navigation */
@media (max-width: 991.98px) {
    .sticky-top {
        top: 60px !important;
    }
    
    .policy-section {
        scroll-margin-top: 100px;
    }
    
    .accordion-button {
        font-size: 0.9rem;
        padding: 0.6rem 0.8rem;
    }
}

@media (max-width: 575.98px) {
    .display-5 {
        font-size: 1.8rem;
    }
    
    .card-header h3 {
        font-size: 1rem;
    }
    
    .badge {
        font-size: 0.7rem;
    }
}
</style>

<script>
// Initialize all accordions to be collapsed
document.addEventListener('DOMContentLoaded', function() {
    // Close all accordions by default
    const accordionButtons = document.querySelectorAll('.accordion-button');
    accordionButtons.forEach(button => {
        if (!button.classList.contains('collapsed')) {
            const target = button.getAttribute('data-bs-target');
            const collapse = document.querySelector(target);
            if (collapse) {
                collapse.classList.remove('show');
                button.classList.add('collapsed');
            }
        }
    });
    
    // Smooth scroll with offset for mobile
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                
                // Calculate offset based on screen size
                const offset = window.innerWidth < 992 ? 100 : 140;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Highlight active section on scroll
    const sections = document.querySelectorAll('.policy-section');
    const navLinks = document.querySelectorAll('.list-group-item, .btn-outline-success');
    
    function highlightActiveSection() {
        let current = '';
        const scrollPosition = window.pageYOffset + 150;
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                current = section.getAttribute('id');
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    }
    
    window.addEventListener('scroll', highlightActiveSection);
    highlightActiveSection(); // Initial call
});
</script>

<?php include 'includes/footer.php'; ?>