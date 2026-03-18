<?php
// Migration: Change form_responses.user_id from INT to VARCHAR
// This allows Google login users with string IDs like "google_123456..."

require_once ROOT_PATH . '/includes/db.php';

echo "<h2>Migration: form_responses.user_id INT -> VARCHAR</h2>";

try {
    // Check current column type
    $stmt = $pdo->query("SHOW COLUMNS FROM form_responses LIKE 'user_id'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($col) {
        echo "<p>Current type: <code>" . htmlspecialchars($col['Type']) . "</code></p>";

        // Run ALTER
        $pdo->exec("ALTER TABLE form_responses MODIFY COLUMN user_id VARCHAR(255) DEFAULT NULL");

        // Verify change
        $stmt = $pdo->query("SHOW COLUMNS FROM form_responses LIKE 'user_id'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<p style='color: green; font-weight: bold;'>✅ Migration successful!</p>";
        echo "<p>New type: <code>" . htmlspecialchars($col['Type']) . "</code></p>";
    } else {
        echo "<p style='color: orange;'>Column user_id not found in form_responses table.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<br><a href='index.php'>Back to Home</a>";
?>
