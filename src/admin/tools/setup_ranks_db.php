<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS ranks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        abbreviation VARCHAR(50) NOT NULL,
        category VARCHAR(100) NOT NULL,
        image VARCHAR(255) NOT NULL DEFAULT '',
        order_index INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Ranks table created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>