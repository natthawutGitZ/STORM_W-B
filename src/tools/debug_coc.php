<?php
require_once ROOT_PATH . '/includes/db.php';

echo "<pre>";

echo "Running query directly:\n";
try {
    $stmt = $pdo->query("SELECT id, personaname as name, `rank`, `position`, parent_id, coc_sort_order 
                        FROM users 
                        WHERE status != 'Inactive'");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total Active Users: " . count($allUsers) . "\n\n";

    $inChain = array_filter($allUsers, function ($u) {
        return !is_null($u['coc_sort_order']);
    });

    echo "Users IN Chain (coc_sort_order IS NOT NULL): " . count($inChain) . "\n";
    print_r($inChain);

    echo "\n\nUsers NOT in Chain (coc_sort_order IS NULL):\n";
    $notInChain = array_filter($allUsers, function ($u) {
        return is_null($u['coc_sort_order']);
    });
    print_r($notInChain);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
?>
