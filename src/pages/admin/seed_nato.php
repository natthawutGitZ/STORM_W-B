<?php
$dsn = "mysql:host=db;dbname=appdb;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, 'appuser', 'apppass', $options);

    try {
        $pdo->exec("ALTER TABLE ranks ADD COLUMN nato_code VARCHAR(20) DEFAULT '' AFTER abbreviation");
        echo "Column nato_code added.\n";
    } catch (Exception $e) {
        // Ignore if exists
    }

    $updates = [
        'GA' => 'OF-10',
        'GEN' => 'OF-9',
        'LTG' => 'OF-8',
        'MG' => 'OF-7',
        'BG' => 'OF-6',
        'COL' => 'OF-5',
        'LTC' => 'OF-4',
        'MAJ' => 'OF-3',
        'CPT' => 'OF-2',
        '1LT' => 'OF-1',
        '2LT' => 'OF-1',

        'CW5' => 'W-5',
        'CW4' => 'W-4',
        'CW3' => 'W-3',
        'CW2' => 'W-2',
        'WO1' => 'W-1',

        'SMA' => 'OR-9',
        'CSM' => 'OR-9',
        'SGM' => 'OR-9',
        '1SG' => 'OR-8',
        'MSG' => 'OR-8',
        'SFC' => 'OR-7',
        'SSG' => 'OR-6',
        'SGT' => 'OR-5',
        'CPL' => 'OR-4',
        'SPC' => 'OR-4',
        'PFC' => 'OR-3',
        'PV2' => 'OR-2',
        'PV1' => 'OR-1'
    ];

    $stmt = $pdo->prepare("UPDATE ranks SET nato_code = ? WHERE abbreviation = ?");
    $count = 0;
    foreach ($updates as $abbr => $code) {
        $stmt->execute([$code, $abbr]);
        $count += $stmt->rowCount();
    }

    echo "Updated $count rank NATO codes successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
