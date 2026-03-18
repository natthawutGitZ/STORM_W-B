<?php
require_once ROOT_PATH . '/includes/db.php';

echo "Migrating database...\n";

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM saved_messages LIKE 'is_pinned'");
    $exists = $stmt->fetch();

    if (!$exists) {
        echo "Adding is_pinned column to saved_messages...\n";
        $pdo->exec("ALTER TABLE saved_messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0");
        echo "Column added successfully.\n";
    } else {
        echo "Column is_pinned already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";
?>

