<?php
// Tự động nhận biến môi trường khi deploy lên Render/Cloud
// Nếu chạy ở máy local thì fallback về localhost
$host     = getenv('DB_HOST') ?: 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$port     = getenv('DB_PORT') ?: '4000';
$dbname   = getenv('DB_NAME') ?: 'affiliate_review';
$username = getenv('DB_USER') ?: '3zQWQun3ZmLaKA4.root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'RkLLk75eipO1kwjx';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    // Nếu dùng TiDB Cloud hoặc SSL Cloud
    if (getenv('DB_SSL') === 'true') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = true;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $username, $password, $options);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>