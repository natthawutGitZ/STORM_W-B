<?php
// Must start session and include db cleanly without HTML
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Make sure we only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Ensure the user is still verified as an admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get the JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['order']) || !is_array($data['order'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Prepare the update statement
    $stmt = $pdo->prepare("UPDATE awards SET order_index = ? WHERE id = ?");

    // Loop through the received order array
    // The index in the array + 1 will be the new order_index within the category
    foreach ($data['order'] as $index => $awardId) {
        $stmt->execute([$index + 1, (int) $awardId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order updated successfully.']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Award Reorder Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error during update.']);
}
?>