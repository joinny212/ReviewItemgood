<?php
header('Content-Type: application/json');
require '../../config/database.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("
    SELECT r.*, p.name as product_name 
    FROM reviews r 
    LEFT JOIN products p ON r.product_id = p.id 
    WHERE r.id = ?
");
$stmt->execute([$id]);
$review = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($review ?: []);
?>