<?php
$host = 'db';
$db = getenv('MYSQL_DATABASE') ?: 'appdb';
$user = getenv('MYSQL_USER') ?: 'appuser';
$pass = getenv('MYSQL_PASSWORD') ?: 'apppass';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;port=3306;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo = new PDO($dsn, $user, $pass, $options);

echo "--- Categories ---\n";
$stmt = $pdo->query("SELECT * FROM award_categories");
$cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($cats);

echo "\n--- Awards ---\n";
$stmt = $pdo->query("SELECT * FROM awards");
$awards = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($awards);
?>