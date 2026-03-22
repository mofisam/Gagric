<?php
require_once '../../includes/auth.php';
requireSeller();
require_once '../../classes/Database.php';

$db = new Database();
$seller_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify product belongs to seller and get images
$product = $db->fetchOne("
    SELECT id, name, seller_id 
    FROM products 
    WHERE id = ? AND seller_id = ?
", [$product_id, $seller_id]);

if (!$product) {
    setFlashMessage('Product not found or access denied', 'error');
    header('Location: manage-products.php');
    exit;
}

try {
    // Get all product images before deleting
    $images = $db->fetchAll("
        SELECT image_path FROM product_images 
        WHERE product_id = ?
    ", [$product_id]);
    
    // Delete from product_images table
    $db->query("DELETE FROM product_images WHERE product_id = ?", [$product_id]);
    
    // Delete from product_agricultural_details
    $db->query("DELETE FROM product_agricultural_details WHERE product_id = ?", [$product_id]);
    
    // Delete from product_bulk_pricing if exists
    $db->query("DELETE FROM product_bulk_pricing WHERE product_id = ?", [$product_id]);
    
    // Delete from product_specifications if exists
    $db->query("DELETE FROM product_specifications WHERE product_id = ?", [$product_id]);
    
    // Delete from wishlists (remove from customer wishlists)
    $db->query("DELETE FROM wishlists WHERE product_id = ?", [$product_id]);
    
    // Delete from cart (remove from customer carts)
    $db->query("DELETE FROM cart WHERE product_id = ?", [$product_id]);
    
    // For order_items - set product_id to NULL to preserve order history
    // This ensures customers can still see what they ordered even if product is deleted
    $db->query("UPDATE order_items SET product_id = NULL WHERE product_id = ?", [$product_id]);
    
    // Finally delete the product
    $db->query("DELETE FROM products WHERE id = ? AND seller_id = ?", [$product_id, $seller_id]);
    
    // Delete physical image files
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/';
    $deleted_images = 0;
    $failed_images = 0;
    
    foreach ($images as $image) {
        $image_path = $upload_dir . $image['image_path'];
        if (file_exists($image_path)) {
            if (unlink($image_path)) {
                $deleted_images++;
            } else {
                $failed_images++;
            }
        }
    }
    
    // Also check for and delete product directory if empty
    $product_dir = $upload_dir . 'product_' . $product_id;
    if (is_dir($product_dir) && count(glob($product_dir . '/*')) === 0) {
        rmdir($product_dir);
    }
    
    // Set success message
    $message = "Product '{$product['name']}' has been deleted successfully.";
    if ($deleted_images > 0) {
        $message .= " {$deleted_images} image(s) removed.";
    }
    if ($failed_images > 0) {
        $message .= " Warning: {$failed_images} image(s) could not be deleted from server.";
    }
    
    setFlashMessage($message, 'success');
    
} catch (Exception $e) {
    // Set error message
    setFlashMessage('Error deleting product: ' . $e->getMessage(), 'error');
}

// Redirect back to manage products
header('Location: manage-products.php');
exit;
?>