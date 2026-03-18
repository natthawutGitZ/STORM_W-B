<?php
require_once __DIR__ . '/includes/db.php';

try {
    $stmt = $pdo->query("SELECT id, name, abbreviation, image FROM ranks ORDER BY id ASC");
    $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h1>Database Ranks</h1>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; font-family: monospace;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Abbr</th><th>Image</th></tr>";

    foreach ($ranks as $r) {
        echo "<tr>";
        echo "<td>{$r['id']}</td>";
        echo "<td>{$r['name']}</td>";
        echo "<td>{$r['abbreviation']}</td>";
        echo "<td>{$r['image']}</td>";
        echo "</tr>";
    }

    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
