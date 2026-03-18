<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/admin_log.php';

echo "--- Debugging Start ---\n";

// 1. Check Table
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_activity_log");
    echo "Table 'admin_activity_log' exists. Rows: " . $stmt->fetchColumn() . "\n";
} catch (PDOException $e) {
    echo "Table 'admin_activity_log' ERROR: " . $e->getMessage() . "\n";
}

// 2. Check File Permission
$debugFile = __DIR__ . '/admin_log_debug.txt';
echo "Debug file path: $debugFile\n";
if (file_exists($debugFile)) {
    echo "Debug file exists.\n";
    echo "Content:\n" . file_get_contents($debugFile) . "\n";
} else {
    echo "Debug file does NOT exist.\n";
    if (is_writable(__DIR__)) {
        echo "Directory is writable.\n";
    } else {
        echo "Directory is NOT writable. Permissions: " . substr(sprintf('%o', fileperms(__DIR__)), -4) . "\n";
    }
}

// 3. Test Function
echo "Attempting to call logAdminAction...\n";
// Mock session for test
$_SESSION['user'] = ['id' => 1, 'username' => 'DEBUG_TEST', 'personaname' => 'Debugger'];

$result = logAdminAction($pdo, 'debug_test_action', 'test', 999, ['test' => 'data']);
echo "logAdminAction Result: " . ($result ? 'TRUE' : 'FALSE') . "\n";

echo "--- Debugging End ---\n";

