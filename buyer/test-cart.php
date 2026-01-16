<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

session_start();
$_SESSION['user_id'] = 1; // Test with user ID 1
$_SESSION['user_role'] = 'buyer';

$db = new Database();

echo "<h2>Cart System Test</h2>";

// Test 1: Check if table exists
echo "<h3>1. Checking cart table...</h3>";
$result = $db->conn->query("SHOW TABLES LIKE 'cart'");
if ($result->num_rows > 0) {
    echo "✓ Cart table exists<br>";
} else {
    echo "✗ Cart table does not exist<br>";
}

// Test 2: Check table structure
echo "<h3>2. Checking table structure...</h3>";
$result = $db->conn->query("DESCRIBE cart");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test 3: Test inserting into cart
echo "<h3>3. Testing cart insertion...</h3>";
$test_user_id = 1;
$test_product_id = 1; // Make sure this product exists in your products table

// First, check if product exists
$check_product = $db->conn->query("SELECT id FROM products WHERE id = $test_product_id");
if ($check_product->num_rows > 0) {
    echo "✓ Product $test_product_id exists<br>";
    
    // Try to insert
    $insert_sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES ($test_user_id, $test_product_id, 2) 
                   ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)";
    
    if ($db->conn->query($insert_sql)) {
        echo "✓ Cart insertion successful<br>";
    } else {
        echo "✗ Cart insertion failed: " . $db->conn->error . "<br>";
    }
} else {
    echo "✗ Product $test_product_id does not exist. Please add a product first.<br>";
}

// Test 4: Check current cart contents
echo "<h3>4. Current cart contents...</h3>";
$cart_result = $db->conn->query("
    SELECT c.*, p.name as product_name 
    FROM cart c 
    LEFT JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = $test_user_id
");

if ($cart_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Product ID</th><th>Product Name</th><th>Quantity</th><th>Created</th></tr>";
    while ($row = $cart_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['user_id']}</td>";
        echo "<td>{$row['product_id']}</td>";
        echo "<td>{$row['product_name']}</td>";
        echo "<td>{$row['quantity']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Cart is empty<br>";
}

// Test 5: Check foreign key constraints
echo "<h3>5. Checking foreign key constraints...</h3>";
$fk_result = $db->conn->query("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'cart' AND CONSTRAINT_NAME != 'PRIMARY'
");

if ($fk_result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Table</th><th>Column</th><th>Constraint</th><th>References Table</th><th>References Column</th></tr>";
    while ($row = $fk_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['TABLE_NAME']}</td>";
        echo "<td>{$row['COLUMN_NAME']}</td>";
        echo "<td>{$row['CONSTRAINT_NAME']}</td>";
        echo "<td>{$row['REFERENCED_TABLE_NAME']}</td>";
        echo "<td>{$row['REFERENCED_COLUMN_NAME']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No foreign key constraints found<br>";
}

// Test 6: Test the API endpoints
echo "<h3>6. Testing API endpoints...</h3>";
echo "<button onclick='testLoadAPI()'>Test Load Cart API</button> ";
echo "<button onclick='testSyncAPI()'>Test Sync Cart API</button>";
echo "<div id='api-result'></div>";

// Cleanup
echo "<h3>7. Cleanup...</h3>";
$db->conn->query("DELETE FROM cart WHERE user_id = $test_user_id AND product_id = $test_product_id");
echo "Test data cleaned up<br>";
?>

<script>
function testLoadAPI() {
    fetch('../api/cart/load.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('api-result').innerHTML = 
                '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('api-result').innerHTML = 
                'Error: ' + error;
        });
}

function testSyncAPI() {
    const testCart = [
        {
            productId: 1,
            productName: "Test Product",
            productPrice: 1000,
            productUnit: "kg",
            quantity: 3
        }
    ];
    
    fetch('../api/cart/sync.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cart: testCart
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('api-result').innerHTML = 
            '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        document.getElementById('api-result').innerHTML = 
            'Error: ' + error;
    });
}
</script>