<?php
/**
 * Database Configuration
 * Auto-detects environment: Docker, Local XAMPP, or Production (InfinityFree)
 */

// Production (InfinityFree) Configuration
$host = 'sql100.infinityfree.com';
$db = 'if0_40531677_storm_db';
$user = 'if0_40531677';
$pass = 'QNRTRkWjgSGzrjN';

$charset = 'utf8mb4';

// Create PDO connection
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Show detailed error for debugging
    $errorMsg = "Database Connection Failed<br>";
    $errorMsg .= "Error: " . $e->getMessage() . "<br>";
    $errorMsg .= "Host: $host<br>";
    $errorMsg .= "Database: $db<br>";
    $errorMsg .= "User: $user<br>";
    $errorMsg .= "Environment: " . ($isDocker ? 'Docker' : ($isLocal ? 'Local' : 'Production'));

    die("<div style='background:#1a1a1a;color:#fff;padding:20px;font-family:monospace;'>
         <h3 style='color:#f44336;'>$errorMsg</h3>
         </div>");
}