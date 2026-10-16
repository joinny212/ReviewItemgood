<?php
header('Content-Type: application/json');
require '../../config/database.php';
require_once '../../config/image_storage.php';
require_once '../../config/product_price.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    $product['price'] = formatProductPrice($product['price']);
    try {
        $image_stmt = $pdo->prepare("SELECT image FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
        $image_stmt->execute([$id]);
        $product['images'] = $image_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $exception) {
        if (!in_array($exception->getCode(), ['42S02', '42S22'], true)) {
            throw $exception;
        }
        error_log('Product image gallery schema is missing: ' . $exception->getMessage());
        $product['images'] = [];
    }
    $product['images'] = normalizeProductImages(array_merge(
        [$product['image'] ?? ''],
        $product['images']
    ));
}

echo json_encode($product ?: []);
?>