<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

$page_title = "Terms and Conditions";
$page_css = 'style.css';
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="text-center mb-4">
        <h1 class="display-5 fw-bold text-success mb-3">Terms and Conditions</h1>
        <div class="alert alert-warning border-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Effective Date:</strong> February 1st, 2026
        </div>
    </div>

    <!-- Quick Navigation -->
    <div class="sticky-top bg-white py-2 border-bottom mb-4" style="top: 70px; z-index: 1000;">
        <div class="d-flex flex-wrap gap-1 justify-content-center">
            <a href="#section1" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">1. About</a>
            <a href="#section2" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">2. Eligibility</a>
            <a href="#section3" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">3. Account Types</a>
            <a href="#section4" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">4. Security</a>
            <a href="#section5" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">5. Seller</a>
            <a href="#section6" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">6. Payments</a>
            <a href="#section7" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">7. Orders</a>
            <a href="#section8" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">8. Disputes</a>
            <a href="#section9" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">9. Prohibited</a>
            <a href="#section10" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">10. Termination</a>
            <a href="#section11" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">11. Liability</a>
            <a href="#section12" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">12. Privacy</a>
            <a href="#section13" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">13. Updates</a>
            <a href="#section14" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">14. Law</a>
            <a href="#section15" class="btn btn-xs btn-outline-success py-1 px-2" style="font-size: 0.75rem;">15. Contact</a>
        </div>
    </div>

    <!-- Introduction Box -->
    <div class="card border-success mb-4">
        <div class="card-body">
            <p class="mb-2"><strong>TERMS & CONDITIONS</strong></p>
            <p class="mb-2"><strong>Green Agric</strong></p>
            <p class="mb-2"><strong>Effective Date:</strong> [Insert Date]</p>
            <hr>
            <p class="mb-3">These Terms and Conditions ("Terms") govern the registration and use of accounts on the Green Agric platform ("Platform"). By creating an account, you agree to be bound by these Terms.</p>
            <div class="alert alert-danger mb-0">
                <i class="bi bi-x-circle-fill me-2"></i>
                <strong>If you do not agree, do not create an account or use the Platform.</strong>
            </div>
        </div>
    </div>

    <!-- Terms Content -->
    <div class="row">
        <!-- Desktop Sidebar -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="sticky-top" style="top: 140px;">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="bi bi-list-ol me-2"></i> Sections</h6>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                        <a href="#section1" class="list-group-item list-group-item-action py-2">1. About Green Agric</a>
                        <a href="#section2" class="list-group-item list-group-item-action py-2">2. Eligibility to Register</a>
                        <a href="#section3" class="list-group-item list-group-item-action py-2">3. Account Types</a>
                        <a href="#section4" class="list-group-item list-group-item-action py-2">4. Account Registration & Security</a>
                        <a href="#section5" class="list-group-item list-group-item-action py-2">5. Seller Verification & Compliance</a>
                        <a href="#section6" class="list-group-item list-group-item-action py-2">6. Payments & Escrow</a>
                        <a href="#section7" class="list-group-item list-group-item-action py-2">7. Orders, Delivery & Fulfillment</a>
                        <a href="#section8" class="list-group-item list-group-item-action py-2">8. Refunds & Disputes</a>
                        <a href="#section9" class="list-group-item list-group-item-action py-2">9. Prohibited Conduct</a>
                        <a href="#section10" class="list-group-item list-group-item-action py-2">10. Account Suspension & Termination</a>
                        <a href="#section11" class="list-group-item list-group-item-action py-2">11. Limitation of Liability</a>
                        <a href="#section12" class="list-group-item list-group-item-action py-2">12. Privacy & Data Protection</a>
                        <a href="#section13" class="list-group-item list-group-item-action py-2">13. Modifications to Terms</a>
                        <a href="#section14" class="list-group-item list-group-item-action py-2">14. Governing Law</a>
                        <a href="#section15" class="list-group-item list-group-item-action py-2">15. Contact Information</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Mobile Offcanvas Toggle -->
            <div class="d-lg-none mb-3">
                <button class="btn btn-success w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#termsSidebar">
                    <i class="bi bi-list me-2"></i> Jump to Section
                </button>
            </div>

            <!-- Section 1 -->
            <section id="section1" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">1. About Green Agric</h3>
                        <span class="badge bg-success">Section 1</span>
                    </div>
                    <div class="card-body">
                        <p>Green Agric operates an online agricultural marketplace that connects third-party sellers (farmers, cooperatives, and agribusinesses) with buyers. Green Agric facilitates listings, payments, and dispute resolution but does not own, produce, or warehouse listed goods.</p>
                    </div>
                </div>
            </section>

            <!-- Section 2 -->
            <section id="section2" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">2. Eligibility to Register</h3>
                        <span class="badge bg-success">Section 2</span>
                    </div>
                    <div class="card-body">
                        <p>To create an account, you must:</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Be at least 18 years old</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Provide accurate, complete, and truthful information</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Have the legal capacity to enter into a binding agreement</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Use the Platform in compliance with applicable laws</li>
                        </ul>
                        <p class="mt-3 mb-0">Green Agric reserves the right to refuse or terminate registration at its discretion.</p>
                    </div>
                </div>
            </section>

            <!-- Section 3 -->
            <section id="section3" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">3. Account Types</h3>
                        <span class="badge bg-success">Section 3</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="card h-100 border-success">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="bi bi-cart3 me-2"></i> a. Buyer Accounts</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Buyer accounts allow users to:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-1"><i class="bi bi-eye-fill text-success me-2"></i> Browse listings</li>
                                            <li class="mb-1"><i class="bi bi-bag-fill text-success me-2"></i> Place orders</li>
                                            <li class="mb-1"><i class="bi bi-credit-card-fill text-success me-2"></i> Make payments</li>
                                            <li><i class="bi bi-chat-left-text-fill text-success me-2"></i> Raise disputes or request refunds where applicable</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-warning">
                                    <div class="card-header bg-warning text-white">
                                        <h6 class="mb-0"><i class="bi bi-shop me-2"></i> b. Seller Accounts</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>Seller accounts allow approved vendors to:</p>
                                        <ul class="list-unstyled">
                                            <li class="mb-1"><i class="bi bi-tag-fill text-warning me-2"></i> List agricultural products</li>
                                            <li class="mb-1"><i class="bi bi-check-circle-fill text-warning me-2"></i> Accept orders and payments</li>
                                            <li class="mb-1"><i class="bi bi-truck text-warning me-2"></i> Arrange fulfillment</li>
                                            <li><i class="bi bi-cash-stack text-warning me-2"></i> Receive payouts subject to verification and delivery confirmation</li>
                                        </ul>
                                        <div class="bg-warning p-3 rounded-3 bg-opacity-25 mt-3 mb-0 p-2">
                                            <small><i class="bi bi-info-circle me-1"></i> Seller accounts are subject to additional verification and approval.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 4 -->
            <section id="section4" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">4. Account Registration & Security</h3>
                        <span class="badge bg-success">Section 4</span>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-shield-lock text-primary me-2"></i> You are responsible for maintaining the confidentiality of your login credentials</li>
                            <li class="mb-2"><i class="bi bi-shield-lock text-primary me-2"></i> You are responsible for all activity conducted under your account</li>
                            <li class="mb-2"><i class="bi bi-shield-lock text-primary me-2"></i> You must notify Green Agric immediately of any unauthorized use</li>
                        </ul>
                        <div class="bg-danger p-3 rounded-3 bg-opacity-25  mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Green Agric is not liable for losses caused by compromised accounts due to user negligence.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 5 -->
            <section id="section5" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">5. Seller Verification & Compliance</h3>
                        <span class="badge bg-success">Section 5</span>
                    </div>
                    <div class="card-body">
                        <p>Sellers must:</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-file-earmark-text-fill text-info me-2"></i> Submit required KYC and business information</li>
                            <li class="mb-2"><i class="bi bi-file-earmark-text-fill text-info me-2"></i> Provide accurate product descriptions</li>
                            <li class="mb-2"><i class="bi bi-file-earmark-text-fill text-info me-2"></i> Sell only permitted agricultural goods</li>
                            <li><i class="bi bi-file-earmark-text-fill text-info me-2"></i> Comply with Green Agric policies and applicable laws</li>
                        </ul>
                        <div class="bg-warning p-3 rounded-3 bg-opacity-25 mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Green Agric may suspend, review, or terminate seller accounts that fail verification or violate platform rules.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 6 -->
            <section id="section6" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">6. Payments & Escrow</h3>
                        <span class="badge bg-success">Section 6</span>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-credit-card-fill text-success me-2"></i> All payments are processed through approved payment providers (including Paystack)</li>
                            <li class="mb-2"><i class="bi bi-credit-card-fill text-success me-2"></i> Buyer payments may be temporarily held pending order fulfillment</li>
                            <li><i class="bi bi-credit-card-fill text-success me-2"></i> Seller payouts are released after delivery confirmation or expiration of the dispute window</li>
                        </ul>
                        <div class="bg-warning p-3 rounded-3 bg-opacity-25 mt-3 mb-0">
                            <i class="bi bi-shield-exclamation me-2"></i>
                            Green Agric reserves the right to withhold or reverse payouts in cases of fraud, disputes, or policy violations.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 7 -->
            <section id="section7" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">7. Orders, Delivery & Fulfillment</h3>
                        <span class="badge bg-success">Section 7</span>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-box-seam text-primary me-2"></i> Sellers are responsible for fulfilling orders accurately and on time</li>
                            <li class="mb-2"><i class="bi bi-box-seam text-primary me-2"></i> Green Agric is not a fulfillment center and does not physically handle goods</li>
                            <li><i class="bi bi-box-seam text-primary me-2"></i> Delivery timelines may vary depending on seller and logistics provider</li>
                        </ul>
                        <div class="bg-info p-3 rounded-3 bg-opacity-25  mt-3 mb-0">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Buyers acknowledge that agricultural produce may vary naturally in size, color, or appearance.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 8 -->
            <section id="section8" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">8. Refunds & Disputes</h3>
                        <span class="badge bg-success">Section 8</span>
                    </div>
                    <div class="card-body">
                        <p>Refunds and disputes are governed by Green Agric's:</p>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="bg-light p-3 rounded-3 bg-opacity-25  border">
                                    <i class="bi bi-arrow-counterclockwise text-success me-2"></i>
                                    <strong>Refund Policy</strong>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="bg-light p-3 rounded-3 bg-opacity-25  border">
                                    <i class="bi bi-people text-success me-2"></i>
                                    <strong>Dispute Resolution Policy</strong>
                                </div>
                            </div>
                        </div>
                        <div class="bg-warning p-3 rounded-3 bg-opacity-25  mt-2 mb-0">
                            <i class="bi bi-file-earmark-check-fill me-2"></i>
                            By registering an account, you agree to be bound by the outcomes of Green Agric's dispute resolution process.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 9 -->
            <section id="section9" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">9. Prohibited Conduct</h3>
                        <span class="badge bg-success">Section 9</span>
                    </div>
                    <div class="card-body">
                        <p>Users must not:</p>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Provide false or misleading information</li>
                                    <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Engage in fraud or deceptive practices</li>
                                    <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Sell prohibited or illegal goods</li>
                                    <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Circumvent platform fees or payment systems</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Abuse dispute or refund mechanisms</li>
                                    <li class="mb-2"><i class="bi bi-x-circle-fill text-danger me-2"></i> Violate any Green Agric policy or applicable law</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 10 -->
            <section id="section10" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">10. Account Suspension & Termination</h3>
                        <span class="badge bg-success">Section 10</span>
                    </div>
                    <div class="card-body">
                        <p>Green Agric may suspend or terminate accounts without notice if:</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-slash-circle-fill text-danger me-2"></i> Terms or policies are violated</li>
                            <li class="mb-2"><i class="bi bi-slash-circle-fill text-danger me-2"></i> Fraud or illegal activity is suspected</li>
                            <li><i class="bi bi-slash-circle-fill text-danger me-2"></i> The account poses risk to the Platform or payment partners</li>
                        </ul>
                        <div class="bg-danger p-3 rounded-3 bg-opacity-25  mt-3 mb-0">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>
                            Outstanding funds may be withheld pending investigation.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 11 -->
            <section id="section11" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">11. Limitation of Liability</h3>
                        <span class="badge bg-success">Section 11</span>
                    </div>
                    <div class="card-body">
                        <p>Green Agric:</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-shield-slash text-secondary me-2"></i> Acts solely as a marketplace facilitator</li>
                            <li class="mb-2"><i class="bi bi-shield-slash text-secondary me-2"></i> Does not guarantee product quality, delivery, or seller performance</li>
                            <li class="mb-2"><i class="bi bi-shield-slash text-secondary me-2"></i> Is not liable for losses arising from third-party seller actions</li>
                        </ul>
                        <div class="bg-secondary p-3 rounded-3 bg-opacity-25 mt-3 mb-0">
                            <i class="bi bi-file-text me-2"></i>
                            To the fullest extent permitted by law, Green Agric disclaims liability for indirect or consequential damages.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 12 -->
            <section id="section12" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">12. Privacy & Data Protection</h3>
                        <span class="badge bg-success">Section 12</span>
                    </div>
                    <div class="card-body">
                        <div class="bg-info p-3 rounded-3 bg-opacity-25 ">
                            <i class="bi bi-shield-check me-2"></i>
                            By registering an account, you consent to the collection and processing of personal data in accordance with Green Agric's Privacy Policy and the Nigeria Data Protection Regulation (NDPR).
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 13 -->
            <section id="section13" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">13. Modifications to Terms</h3>
                        <span class="badge bg-success">Section 13</span>
                    </div>
                    <div class="card-body">
                        <div class="bg-warning p-3 rounded-3 bg-opacity-25 ">
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Green Agric may update these Terms from time to time. Continued use of the Platform constitutes acceptance of the revised Terms.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 14 -->
            <section id="section14" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">14. Governing Law</h3>
                        <span class="badge bg-success">Section 14</span>
                    </div>
                    <div class="card-body">
                        <div class="text-center py-3">
                            <i class="bi bi-globe-africa text-success" style="font-size: 2rem;"></i>
                            <h5 class="mt-3">These Terms are governed by the laws of the Federal Republic of Nigeria.</h5>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 15 -->
            <section id="section15" class="mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">15. Contact Information</h3>
                        <span class="badge bg-success">Section 15</span>
                    </div>
                    <div class="card-body text-center">
                        <p>For questions regarding these Terms, contact:</p>
                        <div class="bg-success p-3 rounded-3 bg-opacity-25 d-inline-block">
                            <i class="bi bi-envelope-fill me-2"></i>
                            <strong>Email:</strong> support@greenagric.shop
                        </div>
                    </div>
                </div>
            </section>

            <!-- Back to Top -->
            <div class="text-center mt-5">
                <a href="#" class="btn btn-success">
                    <i class="bi bi-arrow-up me-2"></i> Back to Top
                </a>
                <a href="policy.php" class="btn btn-outline-success ms-2">
                    <i class="bi bi-file-text me-2"></i> View All Policies
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="termsSidebar">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Jump to Section</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
            <a href="#section1" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">1. About Green Agric</a>
            <a href="#section2" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">2. Eligibility to Register</a>
            <a href="#section3" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">3. Account Types</a>
            <a href="#section4" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">4. Account Registration & Security</a>
            <a href="#section5" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">5. Seller Verification & Compliance</a>
            <a href="#section6" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">6. Payments & Escrow</a>
            <a href="#section7" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">7. Orders, Delivery & Fulfillment</a>
            <a href="#section8" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">8. Refunds & Disputes</a>
            <a href="#section9" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">9. Prohibited Conduct</a>
            <a href="#section10" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">10. Account Suspension & Termination</a>
            <a href="#section11" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">11. Limitation of Liability</a>
            <a href="#section12" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">12. Privacy & Data Protection</a>
            <a href="#section13" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">13. Modifications to Terms</a>
            <a href="#section14" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">14. Governing Law</a>
            <a href="#section15" class="list-group-item list-group-item-action py-3" data-bs-dismiss="offcanvas">15. Contact Information</a>
        </div>
    </div>
</div>

<style>
/* Mobile-First Styles */
section {
    scroll-margin-top: 120px;
}

.card {
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 0;
}

.card-header {
    font-weight: 600;
}

.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Quick navigation */
.btn-xs {
    font-size: 0.7rem !important;
    padding: 0.2rem 0.4rem !important;
}

/* Sticky navigation */
.sticky-top {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95);
    z-index: 1000;
}

/* Smooth scrolling */
html {
    scroll-behavior: smooth;
}

/* Mobile optimization */
@media (max-width: 991.98px) {
    .sticky-top {
        top: 60px !important;
        padding: 0.5rem 0;
    }
    
    section {
        scroll-margin-top: 100px;
    }
    
    .display-5 {
        font-size: 1.8rem;
    }
    
    .card-header h3 {
        font-size: 1rem;
    }
}

@media (max-width: 575.98px) {
    .display-5 {
        font-size: 1.5rem;
    }
    
    .alert {
        font-size: 0.9rem;
        padding: 0.75rem;
    }
    
    .list-group-item {
        font-size: 0.9rem;
    }
}

/* Print styles */
@media print {
    .sticky-top,
    .btn,
    .offcanvas {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    
    section {
        page-break-inside: avoid;
    }
}
</style>

<script>
// Smooth scrolling with offset
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling to all anchor links
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
    
    // Back to top button
    const backToTop = document.querySelector('a[href="#"]');
    if (backToTop && backToTop.textContent.includes('Back to Top')) {
        backToTop.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Highlight active section on scroll
    const sections = document.querySelectorAll('section[id]');
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