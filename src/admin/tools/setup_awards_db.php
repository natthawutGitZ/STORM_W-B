<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create award_categories table
    $sql1 = "CREATE TABLE IF NOT EXISTS `award_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `order_index` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $pdo->exec($sql1);
    echo "Table 'award_categories' created or already exists.\n";

    // 2. Create awards table
    $sql2 = "CREATE TABLE IF NOT EXISTS `awards` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `image` varchar(255) DEFAULT 'default_award.png',
        `order_index` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `category_id` (`category_id`),
        CONSTRAINT `awards_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `award_categories` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $pdo->exec($sql2);
    echo "Table 'awards' created or already exists.\n";

    // 3. Create user_awards table
    $sql3 = "CREATE TABLE IF NOT EXISTS `user_awards` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `award_id` int(11) NOT NULL,
        `date_awarded` date DEFAULT NULL,
        `awarded_by` int(11) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `award_id` (`award_id`),
        KEY `awarded_by` (`awarded_by`),
        CONSTRAINT `user_awards_ibfk_2` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
        CONSTRAINT `user_awards_ibfk_3` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $pdo->exec($sql3);
    echo "Table 'user_awards' created or already exists.\n";

    // Seed initial categories if none exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM award_categories");
    if ($stmt->fetchColumn() == 0) {
        $cats = [
            ['name' => 'Personal Decorations', 'order_index' => 1],
            ['name' => 'Service Awards', 'order_index' => 2],
            ['name' => 'Service & Training Awards', 'order_index' => 3],
            ['name' => 'Badges', 'order_index' => 4]
        ];

        $insertStmt = $pdo->prepare("INSERT INTO award_categories (name, order_index) VALUES (?, ?)");
        foreach ($cats as $cat) {
            $insertStmt->execute([$cat['name'], $cat['order_index']]);
        }
        echo "Inserted initial award categories.\n";
    }

    echo "\nDatabase setup for Awards completed successfully!\n";

} catch (PDOException $e) {
    echo "Error setting up database: " . $e->getMessage() . "\n";
}
?>