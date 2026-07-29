<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dsn = getenv('DB_TEST_DSN') ?: 'mysql:host=localhost;dbname=u963040303_crm_blueant;charset=utf8mb4';
$user = getenv('DB_TEST_USER') ?: 'root';
$pass = getenv('DB_TEST_PASSWORD') ?: '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    echo "DB OK: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
