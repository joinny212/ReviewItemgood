<?php
header('Content-Type: application/json');
require '../../config/database.php';

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
$products = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM reviews");
$reviews = $stmt->fetch()['count'];

echo json_encode([
    'products' => $products,
    'reviews' => $reviews
]);
?>