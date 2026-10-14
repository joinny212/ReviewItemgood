<?php
// Cấu hình kết nối Database TiDB Cloud (có hỗ trợ biến môi trường Render)
$host     = getenv('DB_HOST') ?: 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$port     = getenv('DB_PORT') ?: '4000';
$dbname   = getenv('DB_NAME') ?: 'affiliate_review';
$username = getenv('DB_USER') ?: '3zQWQun3ZmLaKA4.root';
$password = (getenv('DB_PASS') !== false && getenv('DB_PASS') !== '') ? getenv('DB_PASS') : 'ZFcnye8hWfLRyQQ4';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    // Bắt buộc cấu hình SSL cho TiDB Cloud
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        $ca_file = __DIR__ . '/isrgrootx1.pem';
        if (file_exists($ca_file)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca_file;
        }
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $username, $password, $options);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage() . " (Host: $host, Port: $port, DB: $dbname)");
}
?>