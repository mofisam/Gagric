<?php
require_once '../../includes/auth.php';
requireAdmin();

$page_title = "Platform Statistics";
$page_css = 'dashboard.css';

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-lg-10 col-md-9 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <div>
                    <h1 class="h2 mb-1">Platform Statistics</h1>
                    <p class="text-muted mb-0">
                        Advanced marketplace insights and reporting.
                    </p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">

                    <div class="mb-4">
                        <i class="bi bi-bar-chart-line-fill text-success"
                           style="font-size:4rem;"></i>
                    </div>

                    <h2 class="fw-bold mb-3">
                        Coming Soon
                    </h2>

                    <p class="text-muted mx-auto mb-4" style="max-width:650px;">
                        We're building a comprehensive statistics dashboard to
                        provide deeper insights into your marketplace. This page
                        will include advanced reports, performance metrics, and
                        visual analytics to help administrators monitor platform
                        growth and make informed decisions.
                    </p>

                    <div class="row justify-content-center g-3 mt-2">

                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <i class="bi bi-graph-up-arrow text-success fs-3"></i>
                                <h6 class="mt-3 mb-2">Growth Trends</h6>
                                <small class="text-muted">
                                    Track users, sellers, products, and revenue
                                    growth over time.
                                </small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <i class="bi bi-pie-chart-fill text-primary fs-3"></i>
                                <h6 class="mt-3 mb-2">Marketplace Insights</h6>
                                <small class="text-muted">
                                    Analyze category performance, customer
                                    behavior, and sales distribution.
                                </small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <i class="bi bi-speedometer2 text-warning fs-3"></i>
                                <h6 class="mt-3 mb-2">Performance Reports</h6>
                                <small class="text-muted">
                                    Monitor platform activity, engagement,
                                    conversion rates, and operational metrics.
                                </small>
                            </div>
                        </div>

                    </div>

                    <div class="mt-5">
                        <span class="badge bg-warning text-dark px-3 py-2">
                            Under Development
                        </span>
                    </div>

                </div>
            </div>

        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>