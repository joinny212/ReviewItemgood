<?php
header('Content-Type: application/json');
require '../../config/database.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($product ?: []);
?>