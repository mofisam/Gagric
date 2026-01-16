<?php
// buyer/orders/invoice.php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../classes/Database.php';
require_once '../../classes/Order.php';

requireBuyer();

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

// Get order ID from URL
$order_id = $_GET['id'] ?? 0;

// Get order details by ID first
$order_data_by_id = $db->fetchOne("
    SELECT * FROM orders 
    WHERE id = ? AND buyer_id = ?
", [$order_id, $user_id]);

if (!$order_data_by_id) {
    setFlashMessage('Order not found or access denied', 'error');
    header('Location: order-history.php');
    exit;
}

// Get full order details using order number
$order_data = $order->getOrder($order_data_by_id['order_number'], $user_id);

if (!$order_data) {
    setFlashMessage('Order not found', 'error');
    header('Location: order-history.php');
    exit;
}

// Get buyer information
$buyer = $db->fetchOne("
    SELECT u.*, ua.*, s.name as state_name, l.name as lga_name, c.name as city_name
    FROM users u
    LEFT JOIN user_addresses ua ON u.id = ua.user_id AND ua.is_default = 1
    LEFT JOIN states s ON ua.state_id = s.id
    LEFT JOIN lgas l ON ua.lga_id = l.id
    LEFT JOIN cities c ON ua.city_id = c.id
    WHERE u.id = ?
", [$user_id]);

// Get order items with seller information
$order_items = $db->fetchAll("
    SELECT 
        oi.*, 
        p.name as product_name,
        p.description as product_description,
        sp.business_name as seller_name,
        sp.business_reg_number,
        sp.business_description,
        ua.address_line as seller_address,
        ua.state_id as seller_state_id,
        ua.lga_id as seller_lga_id,
        ua.city_id as seller_city_id,
        ua.phone as seller_phone,
        s.name as seller_state_name,
        l.name as seller_lga_name,
        c.name as seller_city_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN seller_profiles sp ON oi.seller_id = sp.user_id
    LEFT JOIN user_addresses ua ON sp.business_address_id = ua.id
    LEFT JOIN states s ON ua.state_id = s.id
    LEFT JOIN lgas l ON ua.lga_id = l.id
    LEFT JOIN cities c ON ua.city_id = c.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
", [$order_id]);

// Get shipping details
$shipping = $db->fetchOne("
    SELECT os.*, s.name as state_name, l.name as lga_name, c.name as city_name 
    FROM order_shipping_details os 
    JOIN states s ON os.state_id = s.id 
    JOIN lgas l ON os.lga_id = l.id 
    JOIN cities c ON os.city_id = c.id 
    WHERE os.order_id = ?
", [$order_id]);

// Get payment details
$payment = $db->fetchOne("SELECT * FROM payments WHERE order_id = ?", [$order_id]);

// Platform information
$platform_info = [
    'name' => 'Green Agric Marketplace',
    'address' => '123 Agriculture Street, Ikeja',
    'city' => 'Lagos',
    'phone' => '+234 703 041 9150',
    'email' => 'invoices@greenagric.ng',
    'website' => 'www.greenagric.ng',
    'registration' => 'RC: 1234567',
    'tax_id' => 'TAX ID: NG-123-456-789'
];

// Set headers for download if requested
if (isset($_GET['download'])) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="invoice-' . $order_data['order_number'] . '.pdf"');
    // Note: For actual PDF generation, you'd need a library like TCPDF or Dompdf
    // This is a simplified HTML version
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order_data['order_number']; ?> - Green Agric</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            .container {
                max-width: 100% !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .invoice-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .table {
                border-collapse: collapse !important;
            }
            .table td, .table th {
                border: 1px solid #ddd !important;
            }
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            max-width: 210mm;
            margin: 0 auto;
        }
        
        .invoice-header {
            border-bottom: 3px solid #28a745;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .invoice-title {
            color: #28a745;
            font-weight: 700;
        }
        
        .section-title {
            color: #28a745;
            border-bottom: 2px solid #28a745;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .total-box {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .watermark {
            position: absolute;
            opacity: 0.1;
            font-size: 10rem;
            transform: rotate(-45deg);
            z-index: 0;
            color: #28a745;
            pointer-events: none;
        }
        
        .company-logo {
            max-height: 80px;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .print-area {
            position: relative;
        }
        
        .invoice-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }
        
        .payment-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .signature-area {
            margin-top: 4rem;
            border-top: 2px solid #28a745;
            padding-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4 no-print">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-receipt me-2"></i> Invoice #<?php echo $order_data['order_number']; ?>
                        </h4>
                        <div class="btn-group">
                            <button onclick="window.print()" class="btn btn-light">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                            <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-light">
                                <i class="bi bi-arrow-left me-1"></i> Back to Order
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            This is a printable invoice for your order. Click "Print" to generate a physical copy or save as PDF.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container py-4 print-area">
        <div class="watermark">INVOICE</div>
        
        <div class="invoice-container">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="row">
                    <div class="col-6">
                        <h1 class="invoice-title mb-3">
                            <i class="bi bi-shop me-2"></i> Green Agric Marketplace
                        </h1>
                        <div class="mb-2">
                            <strong>Agricultural Products & Services</strong>
                        </div>
                        <div class="text-muted">
                            <div><?php echo $platform_info['address']; ?></div>
                            <div><?php echo $platform_info['city']; ?></div>
                            <div>Phone: <?php echo $platform_info['phone']; ?></div>
                            <div>Email: <?php echo $platform_info['email']; ?></div>
                            <div>Website: <?php echo $platform_info['website']; ?></div>
                            <div class="mt-2">
                                <small><?php echo $platform_info['registration']; ?> | <?php echo $platform_info['tax_id']; ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="invoice-number mb-3">
                            INVOICE #<?php echo $order_data['order_number']; ?>
                        </div>
                        <div class="mb-3">
                            <span class="status-badge badge bg-<?php echo $order_data['status'] === 'paid' || $order_data['status'] === 'delivered' ? 'success' : 'warning'; ?>">
                                <?php echo strtoupper($order_data['status']); ?>
                            </span>
                        </div>
                        <div class="text-muted">
                            <div><strong>Invoice Date:</strong> <?php echo date('F j, Y'); ?></div>
                            <div><strong>Order Date:</strong> <?php echo formatDate($order_data['created_at'], 'F j, Y'); ?></div>
                            <div><strong>Invoice ID:</strong> INV-<?php echo $order_data['order_number']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <!-- From/Platform Info -->
                <div class="col-md-6">
                    <h5 class="section-title">From</h5>
                    <div class="payment-details">
                        <strong><?php echo $platform_info['name']; ?></strong><br>
                        <?php echo $platform_info['address']; ?><br>
                        <?php echo $platform_info['city']; ?><br>
                        <strong>Phone:</strong> <?php echo $platform_info['phone']; ?><br>
                        <strong>Email:</strong> <?php echo $platform_info['email']; ?><br>
                        <strong>Website:</strong> <?php echo $platform_info['website']; ?>
                    </div>
                </div>
                
                <!-- To/Buyer Info -->
                <div class="col-md-6">
                    <h5 class="section-title">Bill To</h5>
                    <div class="payment-details">
                        <strong><?php echo htmlspecialchars($buyer['first_name'] . ' ' . $buyer['last_name']); ?></strong><br>
                        <?php if ($buyer['address_line']): ?>
                            <?php echo htmlspecialchars($buyer['address_line']); ?><br>
                            <?php echo htmlspecialchars($buyer['city_name'] . ', ' . $buyer['lga_name'] . ', ' . $buyer['state_name']); ?><br>
                        <?php endif; ?>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($buyer['phone']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($buyer['email']); ?><br>
                        <strong>Customer ID:</strong> <?php echo htmlspecialchars($buyer['uuid']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Information -->
            <?php if ($shipping): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="section-title">Shipping Address</h5>
                        <div class="payment-details">
                            <strong><?php echo htmlspecialchars($shipping['shipping_name']); ?></strong><br>
                            <?php echo htmlspecialchars($shipping['address_line']); ?><br>
                            <?php echo htmlspecialchars($shipping['city_name'] . ', ' . $shipping['lga_name'] . ', ' . $shipping['state_name']); ?><br>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($shipping['shipping_phone']); ?>
                            <?php if ($shipping['landmark']): ?><br>
                                <strong>Landmark:</strong> <?php echo htmlspecialchars($shipping['landmark']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Order Items Table -->
            <div class="mb-4">
                <h5 class="section-title">Order Items</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-success">
                            <tr>
                                <th>#</th>
                                <th>Product Description</th>
                                <th>Seller</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $item_counter = 1;
                            foreach ($order_items as $item): 
                            ?>
                                <tr>
                                    <td><?php echo $item_counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                        <small class="text-muted">
                                            Unit: <?php echo htmlspecialchars($item['unit']); ?>
                                            <?php if (isset($item['grade']) && $item['grade']): ?>
                                                • Grade: <?php echo htmlspecialchars($item['grade']); ?>
                                            <?php endif; ?>
                                            <?php if (isset($item['is_organic']) && $item['is_organic']): ?>
                                                • <span class="text-success">Organic Certified</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['seller_name']); ?><br>
                                        <small class="text-muted">
                                            <?php if ($item['seller_state_name']): ?>
                                                <?php echo htmlspecialchars($item['seller_city_name'] . ', ' . $item['seller_state_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-end"><?php echo formatCurrency($item['unit_price']); ?></td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($item['item_total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Totals -->
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <div class="total-box">
                        <div class="row mb-2">
                            <div class="col-6">Subtotal:</div>
                            <div class="col-6 text-end"><?php echo formatCurrency($order_data['subtotal_amount']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">Shipping:</div>
                            <div class="col-6 text-end"><?php echo formatCurrency($order_data['shipping_amount']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">Tax:</div>
                            <div class="col-6 text-end"><?php echo isset($order_data['tax_amount']) && $order_data['tax_amount'] > 0 ? formatCurrency($order_data['tax_amount']) : '₦0.00'; ?></div>
                        </div>
                        <?php if (isset($order_data['discount_amount']) && $order_data['discount_amount'] > 0): ?>
                            <div class="row mb-2">
                                <div class="col-6">Discount:</div>
                                <div class="col-6 text-end">-<?php echo formatCurrency($order_data['discount_amount']); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="row pt-2 border-top border-white">
                            <div class="col-6">
                                <h5 class="mb-0">GRAND TOTAL</h5>
                                <small class="opacity-75">All amounts in Nigerian Naira (NGN)</small>
                            </div>
                            <div class="col-6 text-end">
                                <h3 class="mb-0"><?php echo formatCurrency($order_data['total_amount']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Information -->
            <?php if ($payment): ?>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h5 class="section-title">Payment Information</h5>
                        <div class="payment-details">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Payment Method:</strong><br>
                                    <strong>Payment Date:</strong><br>
                                    <strong>Payment Status:</strong><br>
                                    <strong>Reference:</strong>
                                </div>
                                <div class="col-6">
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'card')); ?><br>
                                    <?php echo $payment['paid_at'] ? formatDate($payment['paid_at'], 'F j, Y g:i A') : 'Pending'; ?><br>
                                    <span class="badge bg-<?php echo $payment['status'] === 'success' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span><br>
                                    <?php echo htmlspecialchars($payment['paystack_reference']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Notes -->
                    <div class="col-md-6">
                        <h5 class="section-title">Order Notes</h5>
                        <div class="payment-details">
                            <p class="mb-2">
                                <strong>Order Status:</strong> 
                                <span class="badge bg-<?php echo $order_data['status'] === 'delivered' ? 'success' : ($order_data['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($order_data['status']); ?>
                                </span>
                            </p>
                            <?php if ($shipping && $shipping['shipping_instructions']): ?>
                                <p class="mb-2">
                                    <strong>Shipping Instructions:</strong><br>
                                    <?php echo htmlspecialchars($shipping['shipping_instructions']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($shipping && $shipping['tracking_number']): ?>
                                <p class="mb-0">
                                    <strong>Tracking Number:</strong> <?php echo htmlspecialchars($shipping['tracking_number']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Terms and Conditions -->
            <div class="row mt-4">
                <div class="col-12">
                    <h5 class="section-title">Terms & Conditions</h5>
                    <div class="payment-details small">
                        <ol>
                            <li>This invoice is valid for 30 days from the invoice date.</li>
                            <li>Goods sold are not returnable unless defective.</li>
                            <li>Agricultural products are subject to seasonal availability.</li>
                            <li>All disputes are subject to Lagos jurisdiction.</li>
                            <li>Payment confirms acceptance of our terms.</li>
                            <li>For quality complaints, contact within 48 hours of delivery.</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <!-- Signatures -->
            <div class="row mt-4 signature-area">
                <div class="col-md-4 text-center">
                    <div class="border-top pt-3">
                        <strong>Customer Signature</strong><br>
                        <div class="mt-2" style="height: 60px;"></div>
                        <small>Date: _________________</small>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="border-top pt-3">
                        <strong>Seller Representative</strong><br>
                        <div class="mt-2" style="height: 60px;"></div>
                        <small>Date: _________________</small>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="border-top pt-3">
                        <strong>Green Agric Marketplace</strong><br>
                        <div class="mt-2" style="height: 60px;"></div>
                        <small>Authorized Signature</small>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="row mt-4 pt-3 border-top text-center text-muted">
                <div class="col-12">
                    <p class="mb-1">
                        <strong>Thank you for your business!</strong>
                    </p>
                    <p class="small mb-0">
                        This is a computer-generated invoice. No signature required.<br>
                        For inquiries, contact: <?php echo $platform_info['email']; ?> | <?php echo $platform_info['phone']; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Control Buttons -->
    <div class="container-fluid py-3 no-print text-center border-top">
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-success btn-lg">
                <i class="bi bi-printer me-2"></i> Print Invoice
            </button>
            <button onclick="downloadAsPDF()" class="btn btn-outline-success btn-lg">
                <i class="bi bi-file-earmark-pdf me-2"></i> Save as PDF
            </button>
            <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary btn-lg">
                <i class="bi bi-arrow-left me-2"></i> Back to Order
            </a>
        </div>
        <p class="text-muted mt-3 small">
            <i class="bi bi-info-circle me-1"></i> 
            For best printing results, use Chrome or Edge browser and select "Save as PDF" in print options.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function downloadAsPDF() {
            // Show loading state
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Generating PDF...';
            event.target.disabled = true;
            
            try {
                // Call server-side PDF generation
                const response = await fetch('../../api/generate-invoice-pdf.php?id=<?php echo $order_id; ?>');
                
                if (response.ok) {
                    // Create blob from response
                    const blob = await response.blob();
                    
                    // Create download link
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'invoice-<?php echo $order_data["order_number"]; ?>.pdf';
                    document.body.appendChild(a);
                    a.click();
                    
                    // Cleanup
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    agriApp.showToast('PDF downloaded successfully!', 'success');
                } else {
                    throw new Error('Failed to generate PDF');
                }
                
            } catch (error) {
                console.error('PDF generation error:', error);
                agriApp.showToast('Failed to generate PDF. Please try the client-side option.', 'error');
                
                // Fallback to client-side generation
                setTimeout(() => {
                    downloadAsPDFClientSide();
                }, 1000);
            } finally {
                // Restore button state
                event.target.innerHTML = originalText;
                event.target.disabled = false;
            }
        }

        // Client-side fallback function
        async function downloadAsPDFClientSide() {
            // ... (the HTML2PDF.js code from Solution 1) ...
        }
        </script>
</body>
</html>