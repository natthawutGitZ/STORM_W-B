<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$logPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$logPerPage = 6;
$logOffset = ($logPage - 1) * $logPerPage;
$logFilter = $_GET['filter'] ?? '';

$logWhereClause = '';
$logParams = [];
if (!empty($logFilter)) {
    $logWhereClause = 'WHERE al.action LIKE ? OR u_admin.personaname LIKE ? OR al.target_type LIKE ?';
    $logParams = ["%$logFilter%", "%$logFilter%", "%$logFilter%"];
}

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_log al LEFT JOIN users u_admin ON al.admin_id = u_admin.id $logWhereClause");
    $countStmt->execute($logParams);
    $totalLogs = $countStmt->fetchColumn();
    $totalLogPages = max(1, ceil($totalLogs / $logPerPage));

    $logStmt = $pdo->prepare("
        SELECT al.*, u_admin.personaname as admin_name, u_admin.avatar as admin_avatar
        FROM admin_activity_log al
        LEFT JOIN users u_admin ON al.admin_id = u_admin.id
        $logWhereClause
        ORDER BY al.created_at DESC
        LIMIT $logPerPage OFFSET $logOffset
    ");
    $logStmt->execute($logParams);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'page' => $logPage,
        'totalPages' => $totalLogPages,
        'totalLogs' => $totalLogs
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

