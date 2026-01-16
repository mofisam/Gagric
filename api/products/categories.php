<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

$db = new Database();
$result = $db->conn->query("SELECT id, name, slug FROM categories WHERE is_active = TRUE");
$categories = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($categories);
?>