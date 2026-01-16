<?php
// api/generate-invoice-pdf.php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if vendor autoload exists
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    die('Vendor autoload not found. Run: composer require dompdf/dompdf');
}

require_once $vendorPath;
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/Order.php';

// Start session before auth check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireBuyer();

use Dompdf\Dompdf;
use Dompdf\Options;

$db = new Database();
$order = new Order($db);
$user_id = getCurrentUserId();

$order_id = $_GET['id'] ?? 0;

if (!$order_id) {
    die('No order ID specified');
}

// Get order details
$order_data_by_id = $db->fetchOne("
    SELECT * FROM orders 
    WHERE id = ? AND buyer_id = ?
", [$order_id, $user_id]);

if (!$order_data_by_id) {
    die('Order not found or access denied');
}

// Get full order details
$order_data = $order->getOrder($order_data_by_id['order_number'], $user_id);
if (!$order_data) {
    die('Order details not found');
}

// Get order items
$order_items = $db->fetchAll("
    SELECT oi.*, sp.business_name as seller_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    JOIN seller_profiles sp ON oi.seller_id = sp.user_id 
    WHERE oi.order_id = ?
", [$order_id]);

// Get buyer information
$buyer = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

// Platform information
$platform_info = [
    'name' => 'Green Agric Marketplace',
    'address' => '123 Agriculture Street, Ikeja',
    'city' => 'Lagos',
    'phone' => '+234 703 041 9150',
    'email' => 'invoices@greenagric.ng',
    'website' => 'www.greenagric.ng',
];

// Build HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice #' . $order_data['order_number'] . '</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .container { width: 100%; max-width: 210mm; margin: 0 auto; }
        .header { border-bottom: 2px solid #28a745; padding-bottom: 10px; margin-bottom: 20px; }
        .company { color: #28a745; font-size: 20px; font-weight: bold; }
        .invoice-no { font-size: 18px; font-weight: bold; text-align: right; }
        .section { margin-bottom: 15px; }
        .section-title { color: #28a745; border-bottom: 1px solid #28a745; padding-bottom: 5px; margin-bottom: 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #28a745; color: white; padding: 8px; text-align: left; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .total { background: #f8f9fa; padding: 10px; border: 1px solid #28a745; margin-top: 20px; }
        .total-row { display: flex; justify-content: space-between; margin: 5px 0; }
        .grand-total { font-size: 16px; font-weight: bold; color: #28a745; border-top: 2px solid #28a745; padding-top: 10px; }
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 10px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <table>
                <tr>
                    <td width="60%">
                        <div class="company">' . $platform_info['name'] . '</div>
                        <div>' . $platform_info['address'] . '</div>
                        <div>' . $platform_info['city'] . '</div>
                        <div>Phone: ' . $platform_info['phone'] . '</div>
                        <div>Email: ' . $platform_info['email'] . '</div>
                    </td>
                    <td width="40%" style="text-align: right;">
                        <div class="invoice-no">INVOICE #' . $order_data['order_number'] . '</div>
                        <div style="margin-top: 5px;">
                            <strong>Invoice Date:</strong> ' . date('F j, Y') . '<br>
                            <strong>Order Date:</strong> ' . date('F j, Y', strtotime($order_data['created_at'])) . '
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Bill To -->
        <div class="section">
            <div class="section-title">Bill To</div>
            <div>
                <strong>' . htmlspecialchars($buyer['first_name'] . ' ' . $buyer['last_name']) . '</strong><br>
                Phone: ' . htmlspecialchars($buyer['phone']) . '<br>
                Email: ' . htmlspecialchars($buyer['email']) . '
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="section">
            <div class="section-title">Order Items</div>
            <table>
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="50%">Product</th>
                        <th width="25%">Seller</th>
                        <th width="10%" style="text-align: right;">Price</th>
                        <th width="10%" style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>';

$item_counter = 1;
foreach ($order_items as $item) {
    $html .= '
                    <tr>
                        <td>' . $item_counter++ . '</td>
                        <td>' . htmlspecialchars($item['product_name']) . '</td>
                        <td>' . htmlspecialchars($item['seller_name']) . '</td>
                        <td style="text-align: right;">₦' . number_format($item['unit_price'], 2) . '</td>
                        <td style="text-align: right;">₦' . number_format($item['item_total'], 2) . '</td>
                    </tr>';
}

$html .= '
                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="section">
            <div class="total">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>₦' . number_format($order_data['subtotal_amount'], 2) . '</span>
                </div>
                <div class="total-row">
                    <span>Shipping:</span>
                    <span>₦' . number_format($order_data['shipping_amount'], 2) . '</span>
                </div>
                <div class="total-row">
                    <span>Tax:</span>
                    <span>₦' . number_format($order_data['tax_amount'] ?? 0, 2) . '</span>
                </div>';
                
if (isset($order_data['discount_amount']) && $order_data['discount_amount'] > 0) {
    $html .= '
                <div class="total-row">
                    <span>Discount:</span>
                    <span>-₦' . number_format($order_data['discount_amount'], 2) . '</span>
                </div>';
}

$html .= '
                <div class="total-row grand-total">
                    <span>GRAND TOTAL:</span>
                    <span>₦' . number_format($order_data['total_amount'], 2) . '</span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            This is a computer-generated invoice. No signature required.<br>
            For inquiries, contact: ' . $platform_info['email'] . ' | ' . $platform_info['phone'] . '<br>
            Generated on: ' . date('F j, Y g:i A') . '
        </div>
    </div>
</body>
</html>';

// Debug: Save HTML to file (remove in production)
// file_put_contents('debug_invoice.html', $html);

try {
    // Configure Dompdf with minimal options first
    $options = new Options();
    $options->set('defaultFont', 'Helvetica'); // Use basic font first
    $options->set('isRemoteEnabled', false); // Disable remote URLs first
    $options->set('isPhpEnabled', false); // Disable PHP execution in HTML
    $options->set('isHtml5ParserEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // Load HTML
    $dompdf->loadHtml($html, 'UTF-8');
    
    // Set paper
    $dompdf->setPaper('A4', 'portrait');
    
    // Render
    $dompdf->render();
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Output PDF
    $dompdf->stream(
        'invoice-' . $order_data['order_number'] . '.pdf',
        [
            'Attachment' => true,
            'compress' => true
        ]
    );
    
} catch (Exception $e) {
    // Log error
    error_log('PDF Generation Error: ' . $e->getMessage());
    
    // Show user-friendly error
    die('PDF generation failed. Please contact support. Error: ' . $e->getMessage());
}

exit;
?>