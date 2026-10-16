<?php
header('Content-Type: application/json');
require '../../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Mã bài review không hợp lệ.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*, p.name as product_name
        FROM reviews r
        LEFT JOIN products p ON r.product_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$review) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy bài review này.']);
        exit;
    }

    echo json_encode($review);
} catch (PDOException $exception) {
    error_log('Unable to load review ' . $id . ': ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Không thể tải bài review từ cơ sở dữ liệu.']);
}
?>