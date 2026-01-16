</main>

<!-- Footer -->
<footer class="bg-dark text-light mt-5">
    <div class="container py-5">
        <div class="row">
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold text-success">
                    <i class="bi bi-shop"></i> Green Agric
                </h5>
                <p class="text-light">
                    Connecting Nigerian farmers directly with buyers. Fresh produce, fair prices, quality guaranteed.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/officialgreenagric" class="text-light me-3"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.linkedin.com/company/greenagric" class="text-light me-3"><i class="bi bi-linkedin"></i></a>
                    <a href="https://www.instagram.com/officialgreenagric" class="text-light me-3"><i class="bi bi-instagram"></i></a>
                    <a href="https://www.we" class="text-light"><i class="bi bi-whatsapp"></i></a>
                </div>
            </div>
            
            <div class="col-md-2 mb-4">
                <h6 class="fw-bold">For Buyers</h6>
                <ul class="list-unstyled">
                    <li><a href="<?php echo BASE_URL; ?>/buyer/products/browse.php" class="text-light text-decoration-none">Browse Products</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/auth/register.php" class="text-light text-decoration-none">Create Account</a></li>
                    <li><a href="#" class="text-light text-decoration-none">How to Buy</a></li>
                    <li><a href="#" class="text-light text-decoration-none">FAQ</a></li>
                </ul>
            </div>
            
            <div class="col-md-2 mb-4">
                <h6 class="fw-bold">For Sellers</h6>
                <ul class="list-unstyled">
                    <li><a href="<?php echo BASE_URL; ?>/auth/register.php" class="text-light text-decoration-none">Sell on AgriMarket</a></li>
                    <li><a href="#" class="text-light text-decoration-none">Seller Guidelines</a></li>
                    <li><a href="#" class="text-light text-decoration-none">Pricing</a></li>
                    <li><a href="#" class="text-light text-decoration-none">Support</a></li>
                </ul>
            </div>
            
            <div class="col-md-2 mb-4">
                <h6 class="fw-bold">Company</h6>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-light text-decoration-none">About Us</a></li>
                    <li><a href="#" class="text-light text-decoration-none">Contact</a></li>
                    <li><a href="#" class="text-light text-decoration-none">Privacy Policy</a></li>
                    <li><a href="#" class="text-light text-decoration-none">Terms of Service</a></li>
                </ul>
            </div>
            
            <div class="col-md-2 mb-4">
                <h6 class="fw-bold">Contact</h6>
                <ul class="list-unstyled text-light">
                    <li><i class="bi bi-envelope me-2"></i> support@greenagric.ng</li>
                    <li><i class="bi bi-telephone me-2"></i> +234 703 041 9150</li>
                    <li><i class="bi bi-geo-alt me-2"></i> Lagos, Nigeria</li>
                </ul>
            </div>
        </div>
        
        <hr class="my-4 bg-secondary">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="text-light mb-0">&copy; <?php echo date('Y'); ?> Green Agric. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Page-specific JS -->
<?php if (isset($page_js)): ?>
    <script src="<?php echo BASE_URL; ?>/assets/js/<?php echo $page_js; ?>"></script>
<?php endif; ?>

<body class="<?php echo isLoggedIn() ? 'user-logged-in' : ''; ?>">



<!-- Initialize page-specific functionality -->
</body>
</html>