<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once ROOT_PATH . '/includes/db.php';

echo "Database Connection Check:\n";
echo "Host: " . $host . "\n";
echo "User: " . $user . "\n";
echo "Docker Mode: " . ($isDocker ? 'Yes' : 'No') . "\n";

try {
    $stmt = $pdo->query("SELECT count(*) FROM users");
    echo "Users Count: " . $stmt->fetchColumn() . "\n";
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
