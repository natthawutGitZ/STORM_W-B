<?php
require_once ROOT_PATH . '/includes/db.php';

try {
    echo "Updating database schema...<br>";

    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER steamid");
        echo "Added column 'google_id'.<br>";
    } else {
        echo "Column 'google_id' already exists.<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER google_id");
        echo "Added column 'email'.<br>";
    } else {
        echo "Column 'email' already exists.<br>";
    }

    echo "Schema update completed successfully.";

} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage();
}
?>
