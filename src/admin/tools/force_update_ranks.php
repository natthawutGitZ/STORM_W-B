<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $ranks = $pdo->query("SELECT id, abbreviation FROM ranks")->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("UPDATE ranks SET image = ? WHERE id = ?");
    $count = 0;
    foreach ($ranks as $rank) {
        $imageName = $rank['abbreviation'] . '.png';
        $stmt->execute([$imageName, $rank['id']]);
        $count++;
    }

    echo "Successfully updated $count ranks.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
