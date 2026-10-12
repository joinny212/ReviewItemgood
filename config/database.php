<?php
// Tự động nhận biến môi trường khi deploy lên Render/Cloud
// Nếu chạy ở máy local thì fallback về localhost
$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: '3306';
$dbname   = getenv('DB_NAME') ?: 'affiliate_review';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // Bắt buộc bật mã hóa SSL/TLS khi kết nối tới Cloud DB (TiDB Cloud, Aiven...)
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        $ca_file = __DIR__ . '/isrgrootx1.pem';
        if (file_exists($ca_file)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca_file;
        }
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $username, $password, $options);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>