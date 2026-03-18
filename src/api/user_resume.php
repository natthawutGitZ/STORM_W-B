<?php
header('Content-Type: application/json');
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

try {
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    if (!$userId) {
        throw new Exception("Missing user_id parameter");
    }

    // Get user info
    $userStmt = $pdo->prepare("SELECT id, steamid, personaname, `rank`, position, avatar, status, created_at, last_login FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // Format dates
    if ($user['created_at']) {
        $user['created_at'] = date('d M Y', strtotime($user['created_at']));
    }
    if ($user['last_login']) {
        $user['last_login'] = date('d M Y H:i', strtotime($user['last_login']));
    }

    // Get form responses for this user (accepted applications)
    $responses = [];

    // Find submissions by this user (matching by steamid or user_id)
    $submissionStmt = $pdo->prepare("
        SELECT fs.id as submission_id, fs.form_id, fs.status, fs.submitted_at, f.form_name as form_title
        FROM form_submissions fs
        JOIN forms f ON f.id = fs.form_id
        WHERE (fs.steamid = ? OR fs.user_id = ?)
        AND fs.status = 'Accepted'
        ORDER BY fs.submitted_at DESC
        LIMIT 5
    ");
    $submissionStmt->execute([$user['steamid'], $userId]);
    $submissions = $submissionStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($submissions as $submission) {
        // Get answers for this submission
        $answerStmt = $pdo->prepare("
            SELECT fq.question_text as question, fa.answer_value as answer
            FROM form_answers fa
            JOIN form_questions fq ON fq.id = fa.question_id
            WHERE fa.submission_id = ?
            AND fq.question_type NOT IN ('section')
            ORDER BY fq.sort_order ASC
        ");
        $answerStmt->execute([$submission['submission_id']]);
        $answers = $answerStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($answers) > 0) {
            $responses[] = [
                'form_title' => $submission['form_title'],
                'submitted_at' => date('d M Y', strtotime($submission['submitted_at'])),
                'answers' => $answers
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'responses' => $responses
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
