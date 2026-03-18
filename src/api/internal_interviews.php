<?php
header('Content-Type: application/json');
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

// Allow access only from local or internal network (Basic IP check or Secret Header)
// In Docker, 'web' sees 'bot' IP. We'll skip strict auth for now as per project style, 
// but in production consider verifying a header.

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_accepted_interviews') {
    // Fetch all accepted responses to check for interview times
    $stmt = $pdo->query("SELECT r.id, r.user_id, r.form_id, f.title as form_title, f.webhook_url_welcome FROM form_responses r JOIN forms f ON r.form_id = f.id WHERE r.status = 'accepted'");
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($responses as $r) {
        $aStmt = $pdo->prepare("SELECT fa.answer_text, fq.question_text 
                                FROM form_answers fa 
                                JOIN form_questions fq ON fa.question_id = fq.id 
                                WHERE fa.response_id = ?");
        $aStmt->execute([$r['id']]);
        $answers = $aStmt->fetchAll(PDO::FETCH_ASSOC);

        $interviewStr = "";
        $discordId = ""; // Or username

        foreach ($answers as $a) {
            $q = strtolower($a['question_text']);
            $val = trim($a['answer_text']); // Could be ["..."] or JSON

            // Clean array format
            if (strpos($val, '[') === 0 && strpos($val, '{') === false) {
                $decoded = json_decode($val, true);
                if (is_array($decoded))
                    $val = implode(", ", $decoded);
            }

            // Check if discord field - could be JSON from discord_user picker
            if (strpos($q, 'discord') !== false) {
                // Try to parse as JSON (from discord_user picker)
                $discordData = @json_decode($val, true);
                if ($discordData && isset($discordData['id'])) {
                    // Extract Discord User ID from picker JSON
                    $discordId = $discordData['id'];
                } else {
                    // Fallback: raw text (username or ID)
                    $discordId = $val;
                }
            }

            if (strpos($q, 'สัมภาษณ์') !== false || strpos($q, 'interview') !== false) {
                $interviewStr = $val; // format expected: "YYYY-MM-DD HH:MM" or similar
            }
        }

        if (!empty($interviewStr)) {
            $result[] = [
                'id' => $r['id'],
                'discord_id' => $discordId,
                'interview_time_str' => $interviewStr,
                'form_title' => $r['form_title'],
                'webhook_url_welcome' => $r['webhook_url_welcome']
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => $result]);
    exit;

} elseif ($action === 'reschedule') {
    // Receive JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $responseId = $input['response_id'] ?? $_POST['response_id'] ?? 0;
    $newDate = $input['date'] ?? $_POST['date'] ?? ''; // YYYY-MM-DD
    $newTime = $input['time'] ?? $_POST['time'] ?? ''; // HH:MM
    $reason = $input['reason'] ?? $_POST['reason'] ?? '';

    if (!$responseId || !$newDate || !$newTime) {
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        exit;
    }

    $newDateTimeStr = "$newDate $newTime";

    // Find the question ID for 'Interview' or 'สัมภาษณ์'
    $stmt = $pdo->prepare("SELECT form_id FROM form_responses WHERE id = ?");
    $stmt->execute([$responseId]);
    $formId = $stmt->fetchColumn();

    if (!$formId) {
        echo json_encode(['success' => false, 'error' => 'Response not found']);
        exit;
    }

    // Find Question
    $qStmt = $pdo->prepare("SELECT id FROM form_questions WHERE form_id = ? AND (LOWER(question_text) LIKE '%interview%' OR LOWER(question_text) LIKE '%สัมภาษณ์%') LIMIT 1");
    $qStmt->execute([$formId]);
    $qid = $qStmt->fetchColumn();

    if ($qid) {
        // Update Answer
        // Check if answer exists
        $check = $pdo->prepare("SELECT id FROM form_answers WHERE response_id = ? AND question_id = ?");
        $check->execute([$responseId, $qid]);
        if ($check->fetch()) {
            $update = $pdo->prepare("UPDATE form_answers SET answer_text = ? WHERE response_id = ? AND question_id = ?");
            $update->execute([$newDateTimeStr, $responseId, $qid]);
        } else {
            $insert = $pdo->prepare("INSERT INTO form_answers (response_id, question_id, answer_text) VALUES (?, ?, ?)");
            $insert->execute([$responseId, $qid, $newDateTimeStr]);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Interview date question not found']);
    }
    exit;
} elseif ($action === 'update_status') {
    // Wrapper for form_actions.php functionality can be done here or just call form_actions directly.
    // Let's implement basic update here for simplicity.
    $input = json_decode(file_get_contents('php://input'), true);
    $responseId = $input['response_id'] ?? $_POST['response_id'] ?? 0;
    $status = $input['status'] ?? $_POST['status'] ?? '';

    if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }

    // Update DB
    $stmt = $pdo->prepare("UPDATE form_responses SET status = ? WHERE id = ?");
    $stmt->execute([$status, $responseId]);

    // Trigger form_actions logic (Notifications) is hard because it's a monolithic script.
    // Ideally we assume the Bot *just* updated the DB and handles notifications itself if it triggered the change?
    // But form_actions has complex PDF generation and logic.
    // It's better to make the Bot call `form_actions.php` via HTTP if we want full parity.
    // But `form_actions.php` expects POST.
    // For now, I'll update the DB here and trust the Bot to send the "Status Updated" reply in Discord.
    // BUT the prompt says: "Update status... and send message to Welcome Channel (Accepted) again?"
    // Actually, when Approve/Reject is clicked in Discord, the system should behave *as if* clicked on Web.
    // So calling `form_actions.php` is safer.
    // I will skip implementing logic here and use `form_actions.php` from Python.

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
?>
