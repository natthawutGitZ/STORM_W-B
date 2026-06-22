<?php
header('Content-Type: application/json');
session_start();

$map = $_GET['map'] ?? 'altis';
// Different base coordinates based on map
$baseX = ($map === 'colombia') ? 5000 : 16780; // 16780 is near Pyrgos in Altis
$baseY = ($map === 'colombia') ? 5000 : 12604;

if (!isset($_SESSION['mock_players_' . $map]) || isset($_GET['reset'])) {
    $_SESSION['mock_players_' . $map] = [
        ['id' => 1, 'name' => 'Alpha 1-1', 'x' => $baseX, 'y' => $baseY],
        ['id' => 2, 'name' => 'Bravo 2-1', 'x' => $baseX + 200, 'y' => $baseY - 150],
        ['id' => 3, 'name' => 'Charlie 3', 'x' => $baseX - 300, 'y' => $baseY + 250],
        ['id' => 4, 'name' => 'Eagle 1 (Air)', 'x' => $baseX + 1000, 'y' => $baseY + 1000]
    ];
}

// Simulate movement
foreach ($_SESSION['mock_players_' . $map] as &$p) {
    if (strpos($p['name'], 'Air') !== false) {
        $p['x'] += rand(-100, 100);
        $p['y'] += rand(-100, 100);
    } else {
        $p['x'] += rand(-15, 15);
        $p['y'] += rand(-15, 15);
    }
}

echo json_encode(['success' => true, 'data' => $_SESSION['mock_players_' . $map]]);
