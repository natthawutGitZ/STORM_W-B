<?php
require_once __DIR__ . '/../includes/db.php';

$ranks = [
    // Army Officer Ranks
    ['name' => 'General of the Army', 'abbreviation' => 'GA', 'category' => 'Army Officer', 'order_index' => 1],
    ['name' => 'General', 'abbreviation' => 'GEN', 'category' => 'Army Officer', 'order_index' => 2],
    ['name' => 'Lieutenant General', 'abbreviation' => 'LTG', 'category' => 'Army Officer', 'order_index' => 3],
    ['name' => 'Major General', 'abbreviation' => 'MG', 'category' => 'Army Officer', 'order_index' => 4],
    ['name' => 'Brigadier General', 'abbreviation' => 'BG', 'category' => 'Army Officer', 'order_index' => 5],
    ['name' => 'Colonel', 'abbreviation' => 'COL', 'category' => 'Army Officer', 'order_index' => 6],
    ['name' => 'Lieutenant Colonel', 'abbreviation' => 'LTC', 'category' => 'Army Officer', 'order_index' => 7],
    ['name' => 'Major', 'abbreviation' => 'MAJ', 'category' => 'Army Officer', 'order_index' => 8],
    ['name' => 'Captain', 'abbreviation' => 'CPT', 'category' => 'Army Officer', 'order_index' => 9],
    ['name' => 'First Lieutenant', 'abbreviation' => '1LT', 'category' => 'Army Officer', 'order_index' => 10],
    ['name' => 'Second Lieutenant', 'abbreviation' => '2LT', 'category' => 'Army Officer', 'order_index' => 11],

    // Warrant Officer Ranks
    ['name' => 'Chief Warrant Officer 5', 'abbreviation' => 'CW5', 'category' => 'Warrant Officer', 'order_index' => 12],
    ['name' => 'Chief Warrant Officer 4', 'abbreviation' => 'CW4', 'category' => 'Warrant Officer', 'order_index' => 13],
    ['name' => 'Chief Warrant Officer 3', 'abbreviation' => 'CW3', 'category' => 'Warrant Officer', 'order_index' => 14],
    ['name' => 'Chief Warrant Officer 2', 'abbreviation' => 'CW2', 'category' => 'Warrant Officer', 'order_index' => 15],
    ['name' => 'Warrant Officer 1', 'abbreviation' => 'WO1', 'category' => 'Warrant Officer', 'order_index' => 16],

    // Army Enlisted Ranks
    ['name' => 'Sergeant Major of the Army', 'abbreviation' => 'SMA', 'category' => 'Army Enlisted', 'order_index' => 17],
    ['name' => 'Command Sergeant Major', 'abbreviation' => 'CSM', 'category' => 'Army Enlisted', 'order_index' => 18],
    ['name' => 'Sergeant Major', 'abbreviation' => 'SGM', 'category' => 'Army Enlisted', 'order_index' => 19],
    ['name' => 'First Sergeant', 'abbreviation' => '1SG', 'category' => 'Army Enlisted', 'order_index' => 20],
    ['name' => 'Master Sergeant', 'abbreviation' => 'MSG', 'category' => 'Army Enlisted', 'order_index' => 21],
    ['name' => 'Sergeant First Class', 'abbreviation' => 'SFC', 'category' => 'Army Enlisted', 'order_index' => 22],
    ['name' => 'Staff Sergeant', 'abbreviation' => 'SSG', 'category' => 'Army Enlisted', 'order_index' => 23],
    ['name' => 'Sergeant', 'abbreviation' => 'SGT', 'category' => 'Army Enlisted', 'order_index' => 24],
    ['name' => 'Corporal', 'abbreviation' => 'CPL', 'category' => 'Army Enlisted', 'order_index' => 25],
    ['name' => 'Specialist', 'abbreviation' => 'SPC', 'category' => 'Army Enlisted', 'order_index' => 26],
    ['name' => 'Private First Class', 'abbreviation' => 'PFC', 'category' => 'Army Enlisted', 'order_index' => 27],
    ['name' => 'Private', 'abbreviation' => 'PV2', 'category' => 'Army Enlisted', 'order_index' => 28],
    ['name' => 'Private', 'abbreviation' => 'PV1', 'category' => 'Army Enlisted', 'order_index' => 29]
];

try {
    // Clear existing ranks to avoid duplicates during this import
    $pdo->query("TRUNCATE TABLE ranks");

    $stmt = $pdo->prepare("INSERT INTO ranks (name, abbreviation, category, image, order_index) VALUES (?, ?, ?, ?, ?)");

    $count = 0;
    foreach ($ranks as $rank) {
        $insertName = $rank['name'] . ' (' . $rank['abbreviation'] . ')';
        $image = $rank['abbreviation'] . '.png';

        $stmt->execute([
            $insertName,
            $rank['abbreviation'],
            $rank['category'],
            $image,
            $rank['order_index']
        ]);
        $count++;
    }
    echo "Successfully inserted $count ranks!\n";
} catch (PDOException $e) {
    echo "Error inserting ranks: " . $e->getMessage() . "\n";
}
?>