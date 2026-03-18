<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['order']) || !is_array($data['order'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE positions SET order_index = ? WHERE id = ?");
    foreach ($data['order'] as $index => $posId) {
        $stmt->execute([$index + 1, (int) $posId]);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order updated successfully.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    error_log("Position Reorder Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during update.']);
}
?>