<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/admin_log.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle GET Request (Fetch Resume)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['user_id'])) {
        $target_id = $_GET['user_id'];

        // Allow any logged-in user to view resumes
        try {
            $stmt = $pdo->prepare("SELECT resume_data, avatar, `rank`, personaname, steamid FROM users WHERE id = ?");
            $stmt->execute([$target_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $resumeData = $user['resume_data'] ? json_decode($user['resume_data']) : null;

                // If no resume data exists, create empty defaults
                if (!$resumeData) {
                    $resumeData = [
                        'header_l1' => '',
                        'header_l2' => '',
                        'header_l3' => '',
                        'header_l4' => '',
                        'header_logo_left' => 'unit_1.png',
                        'header_logo_right' => 'unit_1.png',
                        'subject' => '',
                        'remarks' => '',
                        'asoc_id' => $user['steamid'] ?? '',
                        'rank' => '',
                        'name' => strtoupper($user['personaname'] ?? ''),
                        'dob' => '',
                        'age' => '',
                        'unit' => '',
                        'position' => '',
                        'mos' => '',
                        'vmet' => '',
                        'background' => '',
                        'sig_style' => '1',
                        'sig_name' => '',
                        'sig_rank' => '',
                        'sig_title' => ''
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'resume_data' => $resumeData,
                    'avatar' => get_avatar($user['avatar'])
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}

// Handle POST Request (Update Resume)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = $_SESSION['user']['id'];

        // If admin is updating another user
        if (isset($_POST['target_user_id']) && isAdmin()) {
            $user_id = $_POST['target_user_id'];
        }

        // Collect all resume fields
        $resume_data = [
            'header_l1' => sanitize($_POST['header_l1'] ?? ''),
            'header_l2' => sanitize($_POST['header_l2'] ?? ''),
            'header_l3' => sanitize($_POST['header_l3'] ?? ''),
            'header_l4' => sanitize($_POST['header_l4'] ?? ''),
            'header_logo_left' => sanitize($_POST['header_logo_left'] ?? 'unit_1.png'),
            'header_logo_right' => sanitize($_POST['header_logo_right'] ?? 'unit_1.png'),
            'subject' => sanitize($_POST['subject'] ?? ''),
            'remarks' => sanitize($_POST['remarks'] ?? ''),
            'asoc_id' => sanitize($_POST['asoc_id'] ?? ''),
            'rank' => sanitize($_POST['rank'] ?? ''),
            'name' => sanitize($_POST['name'] ?? ''),
            'dob' => sanitize($_POST['dob'] ?? ''),
            'age' => sanitize($_POST['age'] ?? ''),
            'unit' => sanitize($_POST['unit'] ?? ''),
            'position' => sanitize($_POST['position'] ?? ''),
            'mos' => sanitize($_POST['mos'] ?? ''),
            'vmet' => sanitize($_POST['vmet'] ?? ''),
            'background' => sanitize($_POST['background'] ?? ''),
            'sig_style' => sanitize($_POST['sig_style'] ?? '1'),
            'sig_name' => sanitize($_POST['sig_name'] ?? ''),
            'sig_rank' => sanitize($_POST['sig_rank'] ?? ''),
            'sig_title' => sanitize($_POST['sig_title'] ?? '')
        ];

        $json_data = json_encode($resume_data);

        $stmt = $pdo->prepare("UPDATE users SET resume_data = ? WHERE id = ?");
        $stmt->execute([$json_data, $user_id]);

        // Update session if updating own profile
        if ($user_id == $_SESSION['user']['id']) {
            $_SESSION['user']['resume_data'] = $json_data;
        }

        logAdminAction($pdo, 'update_resume', 'user', $user_id, ['name' => $resume_data['name']]);
        echo json_encode(['success' => true, 'message' => 'Resume updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
