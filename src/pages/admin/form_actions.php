<?php
// src/form_actions.php
require_once ROOT_PATH . '/includes/db.php';

header('Content-Type: application/json');

// Check simple auth if needed (assuming session check or something in real app)
// For now, allow all or check basic logic
ob_start(); // Buffer output to prevent stray characters
ini_set('display_errors', 0); // Hide errors from output
error_reporting(E_ALL); // But report them internally

session_start();
date_default_timezone_set('Asia/Bangkok');

// Robust autoloader inclusion
if (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
} elseif (file_exists('/var/www/html/vendor/autoload.php')) {
    require_once '/var/www/html/vendor/autoload.php';
}

// --- BOT API CONFIGURATION ---
require_once ROOT_PATH . '/includes/bot_api.php';
require_once ROOT_PATH . '/includes/admin_log.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create_form':
            $stmt = $pdo->prepare("INSERT INTO forms (title, description) VALUES ('Untitled Form', 'Form description')");
            $stmt->execute();
            $newFormId = $pdo->lastInsertId();
            logAdminAction($pdo, 'create_form', 'form', $newFormId, ['title' => 'Untitled Form']);
            echo json_encode(['success' => true, 'id' => $newFormId]);
            break;

        case 'update_form_meta':
            $id = $_POST['id'];
            $title = $_POST['title'];
            $description = $_POST['description'];
            $webhook_staff = $_POST['webhook_staff'] ?? '';
            $webhook_welcome = $_POST['webhook_welcome'] ?? '';
            $webhook_public = $_POST['webhook_public'] ?? '';
            $give_role_id = $_POST['give_role_id'] ?? '';

            // Ensure give_role_id column exists
            try {
                $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forms' AND COLUMN_NAME = 'give_role_id'");
                $colCheck->execute();
                if ($colCheck->fetchColumn() == 0) {
                    $pdo->exec("ALTER TABLE forms ADD COLUMN give_role_id VARCHAR(30) DEFAULT NULL");
                }
            } catch (PDOException $e) { /* column may already exist */
            }

            // Handle Banner Upload
            $bannerSql = "";
            $params = [$title, $description, $webhook_staff, $webhook_welcome, $webhook_public, $give_role_id];

            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                // CLEANUP: Delete old banner if exists
                try {
                    $stmtImg = $pdo->prepare("SELECT banner_image FROM forms WHERE id = ?");
                    $stmtImg->execute([$id]);
                    $oldImg = $stmtImg->fetchColumn();
                    if ($oldImg && file_exists('../' . $oldImg)) {
                        unlink('../' . $oldImg);
                    }
                } catch (Exception $e) {
                    // Ignore errors during deletion, proceed with upload
                }

                $uploadDir = ROOT_PATH . '/assets/uploads/forms/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0777, true);

                $ext = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
                $filename = 'form_' . $id . '_' . time() . '.' . $ext;
                $targetFile = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $targetFile)) {
                    $bannerSql = ", banner_image = ?";
                    $params[] = 'assets/uploads/forms/' . $filename;
                }
            }

            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE forms SET title = ?, description = ?, webhook_url_staff = ?, webhook_url_welcome = ?, webhook_url_public = ?, give_role_id = ? $bannerSql WHERE id = ?");
            $stmt->execute($params);
            logAdminAction($pdo, 'update_form', 'form', $id, ['title' => $title]);
            echo json_encode(['success' => true]);
            break;

        case 'add_question':
            $form_id = $_POST['form_id'];
            $type = $_POST['type'] ?? 'text';

            // Default Options for choice-based questions to avoid empty state confusion
            $defaultOptions = '[]';
            if (in_array($type, ['radio', 'checkbox', 'select'])) {
                $defaultOptions = json_encode(['Option 1', 'Option 2']);
            }

            // Get max sort order
            $stmt = $pdo->prepare("SELECT MAX(sort_order) FROM form_questions WHERE form_id = ?");
            $stmt->execute([$form_id]);
            $max = $stmt->fetchColumn();
            $order = $max !== false ? $max + 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO form_questions (form_id, question_text, question_type, sort_order, options) VALUES (?, 'Untitled Question', ?, ?, ?)");
            $stmt->execute([$form_id, $type, $order, $defaultOptions]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'update_question':
            $id = $_POST['id'];
            $text = $_POST['question_text'];
            $description = $_POST['description'] ?? null;
            $type = $_POST['question_type'];
            $required = $_POST['is_required'] ?? 0;
            $allow_other = $_POST['allow_other'] ?? 0; // New field
            $options = $_POST['options'] ?? '[]';

            $stmt = $pdo->prepare("UPDATE form_questions SET question_text = ?, description = ?, question_type = ?, is_required = ?, allow_other = ?, options = ? WHERE id = ?");
            $stmt->execute([$text, $description, $type, $required, $allow_other, $options, $id]);
            logAdminAction($pdo, 'update_question', 'question', $id, ['text' => $text]);
            echo json_encode(['success' => true]);
            break;

        case 'update_order':
            $order = $_POST['order'] ?? []; // Array of IDs
            // If sent as JSON string, decode it
            if (is_string($order))
                $order = json_decode($order, true);

            if (is_array($order)) {
                $sql = "UPDATE form_questions SET sort_order = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($order as $index => $qId) {
                    $stmt->execute([$index, $qId]);
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
            }
            break;

        case 'delete_question':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM form_questions WHERE id = ?");
            $stmt->execute([$id]);
            logAdminAction($pdo, 'delete_question', 'question', $id);
            echo json_encode(['success' => true]);
            break;

        case 'delete_form':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM forms WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_form_data':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
            $stmt->execute([$id]);
            $form = $stmt->fetch();

            if (!$form) {
                echo json_encode(['success' => false, 'message' => 'Form not found']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM form_questions WHERE form_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$id]);
            $questions = $stmt->fetchAll();

            echo json_encode(['success' => true, 'form' => $form, 'questions' => $questions]);
            break;


        case 'submit_response':
            $form_id = $_POST['form_id'];
            $answers = $_POST['answers']; // Array [question_id => answer_value] or JSON string

            // Get User ID from Session (Check Form Session first, then Main Session)
            require_once ROOT_PATH . '/includes/functions.php';
            $user_id = null;
            if (isset($_SESSION['google_form_user'])) {
                $user_id = $_SESSION['google_form_user']['id'];
            } elseif (isLoggedIn()) {
                $user_id = getUser()['id'];
            }

            // 1. Create Response
            $stmt = $pdo->prepare("INSERT INTO form_responses (form_id, user_id) VALUES (?, ?)");
            $stmt->execute([$form_id, $user_id]);
            $response_id = $pdo->lastInsertId();

            // 2. Save Answers
            $stmtIn = $pdo->prepare("INSERT INTO form_answers (response_id, question_id, answer_text) VALUES (?, ?, ?)");

            $discord_fields = [];

            // Re-fetch questions to ensure we have the text and type
            $qStmt = $pdo->prepare("SELECT id, question_text, question_type FROM form_questions WHERE form_id = ?");
            $qStmt->execute([$form_id]);

            $qMap = [];
            while ($row = $qStmt->fetch(PDO::FETCH_ASSOC)) {
                $qMap[$row['id']] = $row;
            }

            foreach ($answers as $qid => $ans) {
                if (!isset($qMap[$qid]))
                    continue;

                // Handle "Other" option
                if ($ans === '__other__' || (is_array($ans) && in_array('__other__', $ans))) {
                    $otherVal = $_POST['answers_other'][$qid] ?? '';
                    if (!empty($otherVal)) {
                        if (is_array($ans)) {
                            // Replace __other__ with actual value in array
                            $key = array_search('__other__', $ans);
                            if ($key !== false) {
                                $ans[$key] = "Other: " . $otherVal;
                            }
                        } else {
                            $ans = "Other: " . $otherVal;
                        }
                    } else {
                        // Ensure '__other__' falls back cleanly if left empty
                        if (is_array($ans)) {
                            $key = array_search('__other__', $ans);
                            if ($key !== false) {
                                $ans[$key] = "Other";
                            }
                        } else {
                            $ans = "Other";
                        }
                    }
                }

                $answers[$qid] = $ans; // Ensure updated answer is retained for PDF/Discord logic

                $ansText = is_array($ans) ? json_encode($ans) : $ans;
                $stmtIn->execute([$response_id, $qid, $ansText]);

                // Limit discord field value to 1024 chars
                $discordVal = (string) $ansText;
                if (strlen($discordVal) > 1000)
                    $discordVal = substr($discordVal, 0, 1000) . '...';
                if (empty($discordVal))
                    $discordVal = "No Answer";

                $discord_fields[] = [
                    'name' => $qMap[$qid]['question_text'],
                    'value' => $discordVal,
                    'inline' => false
                ];
            }

            // 3. Discord Notification
            require_once ROOT_PATH . '/includes/bot_api.php';
            $botApi = new BotAPI();

            $fStmt = $pdo->prepare("SELECT title, webhook_url_staff, webhook_url_public, give_role_id FROM forms WHERE id = ?");
            $fStmt->execute([$form_id]);
            $formMeta = $fStmt->fetch();

            if ($formMeta && !empty($formMeta['webhook_url_staff'])) {
                // ... (Logic to build $descText and $applicantDiscord remains same, assuming it's above this block or I need to preserve it) ...

                // RE-INCLUDED LOGIC TO BUILD $descText for context matching
                // Find "Discord" field and "Interview Date" field
                $applicantDiscord = "Guest/Unknown";
                $applicantDisplayName = "Guest/Unknown"; // For PDF/Receipt (human-readable)
                $interviewDate = "รอการยืนยัน"; // Pending confirmation
                $interviewTime = "";
                $htmlRows = "";

                foreach ($answers as $qid => $ans) {
                    if (!isset($qMap[$qid]))
                        continue;
                    $qTextRaw = $qMap[$qid]['question_text'];
                    $qText = strtolower($qTextRaw);
                    $ansVal = is_array($ans) ? implode(", ", $ans) : $ans;

                    // Fallback aggressive replacement of __other__ just in case JSON/Array structural nesting hid it earlier
                    if (strpos($ansVal, '__other__') !== false) {
                        $otherVal = $_POST['answers_other'][$qid] ?? '';
                        $replacement = !empty($otherVal) ? "Other: $otherVal" : "Other";
                        $ansVal = str_replace('__other__', $replacement, $ansVal);

                        // Also update actual $answers array instance to be 100% sure downstream Discord logic gets it
                        if (is_array($answers[$qid])) {
                            $idx = array_search('__other__', $answers[$qid]);
                            if ($idx !== false)
                                $answers[$qid][$idx] = $replacement;
                        } else {
                            if ($answers[$qid] === '__other__') {
                                $answers[$qid] = $replacement;
                            } else {
                                $answers[$qid] = str_replace('__other__', $replacement, $answers[$qid]);
                            }
                        }
                    }

                    // 1. Identify Discord Name - Check if it's JSON from discord_user picker
                    if (strpos($qText, 'discord') !== false || $qMap[$qid]['question_type'] === 'discord_user') {
                        // Try to parse as JSON (from discord_user picker)
                        $discordData = @json_decode($ansVal, true);
                        if ($discordData && isset($discordData['id'])) {
                            // Store the ID for mention (Discord notifications)
                            $applicantDiscord = '<@' . $discordData['id'] . '>';
                            // Store display_name for PDF/Receipt (human-readable format)
                            $applicantDisplayName = $discordData['display_name'] ?? $discordData['username'] ?? 'Discord User';
                        } else {
                            $applicantDiscord = trim($ansVal);
                            $applicantDisplayName = trim($ansVal);
                        }
                    }

                    // 2. Identify Interview Date/Time
                    if (strpos($qText, 'สัมภาษณ์') !== false || strpos($qText, 'interview') !== false) {
                        $fullDateTime = trim($ansVal);
                        $parts = explode(' ', $fullDateTime);
                        if (count($parts) >= 2) {
                            $interviewDate = $parts[0];
                            $interviewTime = $parts[1];
                        } else {
                            $interviewDate = $fullDateTime;
                        }
                    }

                    // 3. HTML Row Builder (PDF Optimized)
                    // Check if this is a discord_user field - display nicely instead of raw JSON
                    $displayValue = $ansVal;
                    if ($qMap[$qid]['question_type'] === 'discord_user') {
                        $discordCheck = @json_decode($ansVal, true);
                        if ($discordCheck && isset($discordCheck['display_name'])) {
                            $displayValue = $discordCheck['display_name'];
                            if (!empty($discordCheck['username'])) {
                                $displayValue .= ' (@' . $discordCheck['username'] . ')';
                            }
                        }
                    }
                    $htmlRows .= "<tr>
                        <td class='label'>{$qTextRaw}</td>
                        <td class='value'>" . nl2br(htmlspecialchars($displayValue)) . "</td>
                    </tr>";
                }

                if ($applicantDiscord === "Guest/Unknown" && isLoggedIn()) {
                    $u = getUser();
                    $applicantDiscord = $u['personaname'] ?? $u['username'];
                }

                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $adminLink = "$protocol://$host/admin/applications.php?search=$response_id";

                $descText = ">>> ";
                $descText .= "👤 **ผู้สมัคร (Applicant):** $applicantDiscord\n";
                $descText .= "✅ **สถานะ (Status):** ลงทะเบียนเรียบร้อย\n\n";
                $descText .= "**📅 นัดหมายสัมภาษณ์ (Appointment)**\n";

                $ts = 0;
                if (!empty($interviewDate) && $interviewDate !== "รอการยืนยัน") {
                    $timeStr = !empty($interviewTime) ? $interviewTime : "00:00";
                    try {
                        $dt = new DateTime("$interviewDate $timeStr", new DateTimeZone('Asia/Bangkok'));
                        $ts = $dt->getTimestamp();
                    } catch (Exception $e) {
                        $ts = strtotime("$interviewDate $timeStr");
                    }
                }

                if ($ts) {
                    $thai_months = [
                        1 => 'มกราคม',
                        2 => 'กุมภาพันธ์',
                        3 => 'มีนาคม',
                        4 => 'เมษายน',
                        5 => 'พฤษภาคม',
                        6 => 'มิถุนายน',
                        7 => 'กรกฎาคม',
                        8 => 'สิงหาคม',
                        9 => 'กันยายน',
                        10 => 'ตุลาคม',
                        11 => 'พฤศจิกายน',
                        12 => 'ธันวาคม'
                    ];
                    $d = date('j', $ts);
                    $m = $thai_months[(int) date('n', $ts)];
                    $y = date('Y', $ts) + 543;
                    $t = date('H:i', $ts);

                    $descText .= "🗓️ วันที่: **$d $m $y**\n";
                    $descText .= "🕒 เวลา: **$t น.**\n";
                    $descText .= "⏱️ <t:" . $ts . ":F> (<t:" . $ts . ":R>)\n";
                } else {
                    $descText .= "🗓️ วันที่: **$interviewDate**\n";
                }

                $descText .= "\nView Application:\n";
                $descText .= "**[🔍 ตรวจสอบรายละเอียดสำหรับทีมงาน]($adminLink)**";

                // Generate PDF Receipt
                $css = "body{font-family: garuda, sans-serif; padding:20px; font-size:14px;}
                        .header{text-align:center; margin-bottom:20px; border-bottom:1px solid #ddd; padding-bottom:10px;}
                        .header h1{color:#c5a059; margin:0;}
                        .meta{margin-bottom:20px; color:#555; text-align:center;}
                        table{width:100%; border-collapse:collapse;}
                        td{padding:10px; border-bottom:1px solid #eee; vertical-align:top;}
                        .label{width:35%; font-weight:bold; color:#333; background:#f9f9f9;}
                        .value{width:65%; color:#000;}
                        .footer{margin-top:40px; text-align:center; font-size:10px; color:#999;}";
                $htmlPdf = "<div class='header'><h1>Application Receipt</h1></div><div class='meta'><strong>Form:</strong> {$formMeta['title']}<br><strong>Applicant:</strong> $applicantDisplayName<br><strong>Date:</strong> " . date("d M Y H:i") . "<br><strong>Ref ID:</strong> #$response_id</div><table>$htmlRows</table><div class='footer'>Generated by STORM System</div>";

                try {
                    $mpdfTemp = ROOT_PATH . '/tmp/mpdf';
                    if (!is_dir($mpdfTemp))
                        mkdir($mpdfTemp, 0777, true);
                    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'default_font' => 'garuda', 'tempDir' => $mpdfTemp]);
                    $mpdf->autoLangToFont = true;
                    $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
                    $mpdf->WriteHTML($htmlPdf, \Mpdf\HTMLParserMode::HTML_BODY);
                    $uploadDir = ROOT_PATH . '/assets/uploads/applications/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0777, true);
                    $randomHash = bin2hex(random_bytes(8));
                    $fileName = "Application_{$response_id}_{$randomHash}.pdf";
                    $targetFile = $uploadDir . $fileName;
                    $mpdf->Output($targetFile, \Mpdf\Output\Destination::FILE);

                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $pdfUrl = "$protocol://$host/assets/uploads/applications/$fileName";
                } catch (\Throwable $e) {
                    error_log("PDF Generation failed: " . $e->getMessage());
                    $pdfUrl = "";
                }

                if (!empty($pdfUrl)) {
                    $descText .= "\n\n**📄 [คลิกเพื่อดาวน์โหลด PDF (Download PDF)]($pdfUrl)**";
                }

                // Send Staff Notification via Bot API
                $destStaff = $formMeta['webhook_url_staff'];

                if (!empty($destStaff)) {
                    // Try to lookup Discord user ID by username for proper @mention
                    $displayApplicant = $applicantDiscord;
                    if (!empty($applicantDiscord)) {
                        // Check if it's already a Discord mention <@id>
                        if (preg_match('/^<@!?\d+>$/', $applicantDiscord)) {
                            // Already a mention format, use as is
                            $displayApplicant = $applicantDiscord;
                        } elseif (preg_match('/^\d{17,19}$/', $applicantDiscord)) {
                            // It's a snowflake ID, convert to mention
                            $displayApplicant = "<@$applicantDiscord>";
                        } else {
                            // Lookup user by username
                            $userLookup = $botApi->lookupUser($applicantDiscord);
                            if (!empty($userLookup['found']) && !empty($userLookup['mention'])) {
                                $displayApplicant = $userLookup['mention'];
                            }
                        }
                    }

                    $embedData = [
                        'delivery_method' => 'channel',
                        'message_content' => '<@&1378997874918031380>', // Ping Role
                        'content' => [
                            'title' => '⚡ ' . $formMeta['title'],
                            'description' => str_replace($applicantDiscord, $displayApplicant, $descText)
                        ],
                        'style' => [
                            'color' => 12951641,
                            'thumbnail' => "$protocol://$host/assets/images/Pending_A.png"
                        ],
                        'branding' => [
                            'enabled' => true,
                            'author_name' => '📝New Application Received',
                            'author_icon' => 'https://cdn-icons-png.flaticon.com/512/2921/2921222.png',
                            'footer_text' => 'STORM System • ' . date('Y-m-d H:i')
                        ],
                        'components' => [
                            [
                                'type' => 'select_menu',
                                'custom_id' => 'app_select:' . $response_id,
                                'placeholder' => 'เลือกการดำเนินการ (Select Action)',
                                'options' => [
                                    ['label' => 'Approve (อนุมัติ)', 'value' => 'approve', 'emoji' => '✅', 'description' => 'Accept user application'],
                                    ['label' => 'Reject (ไม่อนุมัติ)', 'value' => 'reject', 'emoji' => '❌', 'description' => 'Reject user application'],
                                    ['label' => 'Reschedule (ปรับกำหนดการ)', 'value' => 'reschedule', 'emoji' => '🕒', 'description' => 'Change interview time']
                                ]
                            ]
                        ]
                    ];
                    $botApi->sendEmbed($destStaff, $embedData);
                }
            }

            // --- 3.1 Send Simplified Notification to PUBLIC Channel ---
            $destPublic = isset($formMeta['webhook_url_public']) ? trim($formMeta['webhook_url_public']) : '';

            if (!empty($destPublic)) {
                // Use the displayApplicant which may have been resolved to a @mention already
                $safeDiscord = !empty($displayApplicant) ? $displayApplicant : (!empty($applicantDiscord) ? $applicantDiscord : "Guest/Unknown");

                $publicEmbed = [
                    'delivery_method' => 'channel',
                    'content' => [
                        'title' => '⚡ ' . $formMeta['title'],
                        'fields' => [
                            ['name' => '👤 ผู้สมัคร (Applicant):', 'value' => "$safeDiscord", 'inline' => false],
                            ['name' => '⏳ สถานะ (Status):', 'value' => "กำลังรอการตรวจสอบ (Pending Check)", 'inline' => false]
                        ]
                    ],
                    'style' => [
                        'color' => 16750848, // Orange
                        'thumbnail' => "$protocol://$host/assets/images/Staff_A.png"
                    ],
                    'branding' => [
                        'enabled' => true,
                        'author_name' => '📝 Application Received',
                        'author_icon' => 'https://cdn-icons-png.flaticon.com/512/2921/2921222.png',
                        'footer_text' => 'STORM System • ' . date('Y-m-d H:i')
                    ]
                ];
                $botApi->sendEmbed($destPublic, $publicEmbed);
            }

            // --- 3.2 AUTO ROLE ASSIGNMENT ON SUBMIT ---
            if (!empty($formMeta['give_role_id'])) {
                $discordUserId = null;

                // Extract Discord user ID from answers (discord_user picker stores JSON with id)
                foreach ($answers as $qid => $ans) {
                    if (!isset($qMap[$qid]))
                        continue;
                    if ($qMap[$qid]['question_type'] === 'discord_user') {
                        $ansVal = is_array($ans) ? implode('', $ans) : $ans;
                        $dData = @json_decode($ansVal, true);
                        if ($dData && !empty($dData['id'])) {
                            $discordUserId = $dData['id'];
                            break;
                        }
                    }
                }

                if ($discordUserId) {
                    try {
                        $roleResult = $botApi->assignRole($discordUserId, $formMeta['give_role_id']);
                        if (!empty($roleResult['success'])) {
                            error_log("[ROLE_ASSIGN_SUBMIT] Success: role {$formMeta['give_role_id']} assigned to user {$discordUserId} for response #{$response_id}");
                        } else {
                            $roleErr = $roleResult['error'] ?? 'Unknown error';
                            error_log("[ROLE_ASSIGN_SUBMIT] Failed: {$roleErr} (role: {$formMeta['give_role_id']}, user: {$discordUserId}, response: #{$response_id})");
                        }
                    } catch (Exception $e) {
                        error_log("[ROLE_ASSIGN_SUBMIT] Exception: " . $e->getMessage());
                    }
                } else {
                    error_log("[ROLE_ASSIGN_SUBMIT] No Discord user ID found in answers for response #{$response_id}");
                }
            }

            // --- 3.3 AUTO USER CREATION & LOGIN ---
            $steamId = '';
            $personaName = '';
            $discordData = null;

            // Extract Steam ID and Persona Name from answers based on keywords
            foreach ($answers as $qid => $ans) {
                if (!isset($qMap[$qid]))
                    continue;
                $qText = strtolower($qMap[$qid]['question_text']);
                $ansVal = is_array($ans) ? implode('', $ans) : $ans;
                $qType = $qMap[$qid]['question_type'];

                if ($qType === 'discord_user') {
                    $discordData = @json_decode($ansVal, true);
                } elseif (strpos($qText, 'discord') !== false && !$discordData) {
                    $dData = @json_decode($ansVal, true);
                    if ($dData && !empty($dData['id'])) {
                        $discordData = $dData;
                    }
                }

                if (strpos($qText, 'steam') !== false && empty($steamId)) {
                    // Extract numbers only for steam ID
                    preg_match('/\d{17}/', $ansVal, $matches);
                    if (!empty($matches[0])) {
                        $steamId = $matches[0];
                    } else if (is_numeric(trim($ansVal))) {
                        $steamId = trim($ansVal);
                    }
                }

                if (strpos($qText, 'ชื่อ') !== false && strpos($qText, 'discord') === false && $qType !== 'discord_user') {
                    if (strpos(trim($ansVal), '{') !== 0) {
                        if (strpos($qText, 'ตัวละคร') !== false) {
                            $personaName = trim($ansVal);
                        } elseif (empty($personaName)) {
                            $personaName = trim($ansVal);
                        }
                    }
                }
            }

            $loginToken = null;

            if (!empty($steamId) && !empty($personaName) && !isLoggedIn()) {
                try {
                    // Ensure columns exist
                    try {
                        $pdo->exec("ALTER TABLE users ADD COLUMN discord_id VARCHAR(30) DEFAULT NULL");
                    } catch (PDOException $e) {
                    }
                    try {
                        $pdo->exec("ALTER TABLE form_responses ADD COLUMN promoted_user_id INT(11) DEFAULT NULL");
                    } catch (PDOException $e) {
                    }

                    // Check if user already exists
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid = ?");
                    $stmt->execute([$steamId]);
                    $user = $stmt->fetch();

                    $rank = 'Private (PV2)';
                    $status = 'Pending ';
                    $role = 'user';
                    $discordId = $discordData['id'] ?? null;
                    $avatar = $discordData['avatar'] ?? '/assets/images/default_avatar.png';

                    if (!$user) {
                        // Create new user
                        $baseUsername = str_replace(' ', '', $personaName);
                        // Make sure username doesn't exist
                        $uCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                        $uCheck->execute([$baseUsername]);
                        if ($uCheck->fetchColumn() > 0) {
                            $baseUsername .= rand(100, 999);
                        }

                        if (function_exists('random_bytes')) {
                            $gen_password = bin2hex(random_bytes(4));
                        } else {
                            $gen_password = substr(md5(mt_rand()), 0, 8);
                        }

                        $hashed_password = password_hash($gen_password, PASSWORD_DEFAULT);
                        $profileUrl = "https://steamcommunity.com/profiles/" . $steamId;

                        $stmt = $pdo->prepare("INSERT INTO users (steamid, personaname, avatar, profileurl, username, password, generated_password, `rank`, status, `role`, discord_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $steamId,
                            $personaName,
                            $avatar,
                            $profileUrl,
                            $baseUsername,
                            $hashed_password,
                            $gen_password,
                            $rank,
                            $status,
                            $role,
                            $discordId
                        ]);

                        $userId = $pdo->lastInsertId();

                        // Fetch the newly created user
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $user = $stmt->fetch();

                        error_log("[AUTO_USER_CREATE] Created user {$baseUsername} from form {$form_id}");
                    } else {
                        // Update existing user
                        $userId = $user['id'];
                        $newRank = $user['rank'];
                        if (empty($newRank) || $newRank === 'Recruit' || $newRank === '') {
                            $newRank = $rank;
                        }

                        $pdo->prepare("UPDATE users SET personaname = ?, avatar = ?, status = ?, `rank` = ? WHERE id = ?")
                            ->execute([$personaName, $avatar, $status, $newRank, $userId]);

                        $user['personaname'] = $personaName;
                        $user['avatar'] = $avatar;
                        $user['rank'] = $newRank;
                        $user['status'] = $status;
                    }

                    // Link user to form, but keep form status as pending
                    $stmt = $pdo->prepare("UPDATE form_responses SET promoted_user_id = ? WHERE id = ?");
                    $stmt->execute([$userId, $response_id]);

                    // Auto Login the user
                    if ($user) {
                        $_SESSION['user'] = $user;
                        $loginToken = 'success';
                        require_once ROOT_PATH . '/includes/admin_log.php';
                        if (function_exists('logAdminAction')) {
                            logAdminAction($pdo, 'auto_login_form', 'user', $user['id'], ['name' => $user['personaname']]);
                        }
                        error_log("[AUTO_USER_LOGIN] Logged in user {$user['username']} from form {$form_id}");
                    }

                } catch (Exception $e) {
                    error_log("[AUTO_USER_ERROR] " . $e->getMessage());
                }
            }

            echo json_encode(['success' => true, 'login_token' => $loginToken]);
            break;

        case 'update_response_status':
            $id = $_POST['id'];
            $status = $_POST['status']; // 'pending', 'accepted', 'rejected'
            $reason = $_POST['reason'] ?? '';

            // Validate status
            if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }

            // Ensure rejection_reason column exists before update
            try {
                $colCheckReason = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'rejection_reason'");
                $colCheckReason->execute();
                if ($colCheckReason->fetchColumn() == 0) {
                    $pdo->exec("ALTER TABLE form_responses ADD COLUMN rejection_reason TEXT DEFAULT NULL");
                }
            } catch (PDOException $e) { /* column may already exist */
            }

            if ($status === 'rejected' && !empty($reason)) {
                $stmt = $pdo->prepare("UPDATE form_responses SET status = ?, rejection_reason = ? WHERE id = ?");
                $stmt->execute([$status, $reason, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE form_responses SET status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
            }

            // --- Update Linked User Status ---
            // If the form has an auto-created user, update their status to Active (if accepted) or Inactive (if rejected)
            $uStmt = $pdo->prepare("SELECT promoted_user_id FROM form_responses WHERE id = ?");
            $uStmt->execute([$id]);
            $linkedUserId = $uStmt->fetchColumn();

            if ($linkedUserId) {
                if ($status === 'accepted') {
                    $pdo->prepare("UPDATE users SET status = 'Active' WHERE id = ?")->execute([$linkedUserId]);
                } elseif ($status === 'rejected') {
                    $pdo->prepare("UPDATE users SET status = 'Inactive' WHERE id = ?")->execute([$linkedUserId]);
                }
            }

            // --- RECORD ADMIN WHO REVIEWED ---
            require_once ROOT_PATH . '/includes/functions.php';
            $currentAdmin = getUser();

            // Handle Admin details (either from web session or discord bot)
            $adminDiscordId = $_POST['admin_discord_id'] ?? null;
            $adminDiscordName = $_POST['admin_discord_name'] ?? null;

            if ($currentAdmin) {
                $adminId = $currentAdmin['id'];
                $adminName = $currentAdmin['personaname'] ?? $currentAdmin['username'] ?? 'Unknown';
            } elseif ($adminDiscordId) {
                // Try to find the admin by Discord ID in the users table
                // Assume steamid or another field might hold discord ID if applicable,
                // but since we lack a clear column in the snapshot, we'll assign it to ID 0 and put the discord name in the log.
                // Or if we check the 'discord_id' column:
                try {
                    $dStmt = $pdo->prepare("SELECT id, personaname, username FROM users WHERE steamid = ? OR username = ?");
                    // Note: since they use discord_user JSON for applicants, staff discord IDs might not be mapped in `users`.
                    // To be safe, we'll log them as ID = 0 (System/Bot) but preserve their Discord Name in the JSON details
                    $adminId = 0;
                    $adminName = $adminDiscordName ? "Discord: " . $adminDiscordName : "Discord User";
                } catch (Exception $e) {
                    $adminId = 0;
                    $adminName = "Discord User";
                }
            } else {
                $adminId = 0; // Fallback or System
                $adminName = 'Unknown';
            }

            // Update reviewed_by
            try {
                $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'reviewed_by'");
                $colCheck->execute();
                if ($colCheck->fetchColumn() == 0) {
                    $pdo->exec("ALTER TABLE form_responses ADD COLUMN reviewed_by INT(11) DEFAULT NULL");
                    $pdo->exec("ALTER TABLE form_responses ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL");
                }
            } catch (PDOException $e) { /* columns may already exist */
            }

            if ($adminId !== null) { // allows 0 for Discord / System actions
                $rvStmt = $pdo->prepare("UPDATE form_responses SET reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                // if adminId is 0, we'll store NULL in reviewed_by to match foreign key constraints (if any)
                $rvID = $adminId > 0 ? $adminId : null;
                $rvStmt->execute([$rvID, $id]);
            }

            // Insert into admin_activity_log
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_log (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    admin_id INT(11) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    target_type VARCHAR(50) DEFAULT NULL,
                    target_id INT(11) DEFAULT NULL,
                    details TEXT DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_admin_id (admin_id),
                    KEY idx_created_at (created_at DESC)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $actionName = $status === 'accepted' ? 'approve_application' : ($status === 'rejected' ? 'reject_application' : 'update_application_status');
                $logStmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, 'application', ?, ?, ?)");
                $logStmt->execute([
                    $adminId,
                    $actionName,
                    $id,
                    json_encode(['status' => $status, 'admin_name' => $adminName]),
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            } catch (PDOException $e) {
                error_log("Activity log insert failed: " . $e->getMessage());
            }

            // --- NOTIFICATION & CREDENTIALS LOGIC ---
            require_once ROOT_PATH . '/includes/functions.php';
            require_once ROOT_PATH . '/includes/bot_api.php';
            $botApi = new BotAPI();

            // 1. Get Response & User Info
            $stmt = $pdo->prepare("SELECT r.*, f.title as form_title, f.webhook_url_welcome, f.give_role_id, u.email as user_email, u.personaname, u.username, u.steamid FROM form_responses r JOIN forms f ON r.form_id = f.id LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();

            if ($app) {
                $email = $app['user_email'];
                $name = $app['personaname'] ?? 'Applicant';

                // If no linked user email, try to find in answers (re-query answers)
                if (empty($email)) {
                    $aStmt = $pdo->prepare("SELECT question_id, answer_text FROM form_answers WHERE response_id = ?");
                    $aStmt->execute([$id]);
                    $ansList = $aStmt->fetchAll();

                    // Need question text to identify email
                    $qStmt = $pdo->prepare("SELECT id, question_text FROM form_questions WHERE form_id = ?");
                    $qStmt->execute([$app['form_id']]);
                    $qMap = [];
                    while ($q = $qStmt->fetch())
                        $qMap[$q['id']] = $q['question_text'];

                    foreach ($ansList as $ans) {
                        if (isset($qMap[$ans['question_id']])) {
                            $qText = strtolower($qMap[$ans['question_id']]);
                            if (strpos($qText, 'email') !== false || strpos($qText, 'อีเมล') !== false) {
                                $email = trim($ans['answer_text']);
                            }
                            if (strpos($qText, 'name') !== false && $name === 'Applicant') {
                                $name = trim($ans['answer_text']);
                            }
                        }
                    }
                }


                // Discord notifications should be sent regardless of email validation
                if ($status === 'accepted') {
                    // --- DISCORD WELCOME NOTIFICATION ---
                    if (!empty($app['webhook_url_welcome'])) {
                        // 1. Fetch Answers to populate fields (if needed)
                        $aStmt = $pdo->prepare("SELECT question_id, answer_text FROM form_answers WHERE response_id = ?");
                        $aStmt->execute([$id]);
                        $webhookAnswers = $aStmt->fetchAll();

                        // 2. Fetch Questions Map
                        $qStmt = $pdo->prepare("SELECT id, question_text FROM form_questions WHERE form_id = ?");
                        $qStmt->execute([$app['form_id']]);
                        $qMap = [];
                        while ($q = $qStmt->fetch())
                            $qMap[$q['id']] = $q['question_text'];

                        // 3. Extract Fields
                        $applicantDiscord = "Guest/Unknown";
                        $interviewDate = "รอการยืนยัน";
                        $interviewTime = "";

                        foreach ($webhookAnswers as $ans) {
                            if (!isset($qMap[$ans['question_id']]))
                                continue;
                            $qTextRaw = $qMap[$ans['question_id']];
                            $qText = strtolower($qTextRaw);
                            $ansVal = $ans['answer_text'];

                            if (strpos($qText, 'discord') !== false) {
                                // Try to parse as JSON (from discord_user picker)
                                $discordData = @json_decode($ansVal, true);
                                if ($discordData && isset($discordData['id'])) {
                                    $applicantDiscord = '<@' . $discordData['id'] . '>';
                                } else {
                                    $applicantDiscord = trim($ansVal);
                                }
                            }
                            if (strpos($qText, 'สัมภาษณ์') !== false || strpos($qText, 'interview') !== false) {
                                $fullDateTime = trim($ansVal);
                                $parts = explode(' ', $fullDateTime);
                                if (count($parts) >= 2) {
                                    $interviewDate = $parts[0];
                                    $interviewTime = $parts[1];
                                } else {
                                    $interviewDate = $fullDateTime;
                                }
                            }
                        }

                        if ($applicantDiscord === "Guest/Unknown" && !empty($app['personaname'])) {
                            $applicantDiscord = $app['personaname'];
                        }

                        // 4. Construct Embed (Standard "Staff" Style)
                        $descText = ">>> ";
                        $descText .= "👤 **ผู้สมัคร (Applicant):** $applicantDiscord\n";
                        $descText .= "✅ **สถานะ (Status):** ผ่านการคัดเลือก (Accepted)\n\n";
                        $descText .= "**📅 นัดหมายสัมภาษณ์ (Appointment)**\n";

                        $ts = 0;
                        if (!empty($interviewDate) && $interviewDate !== "รอการยืนยัน") {
                            $timeStr = !empty($interviewTime) ? $interviewTime : "00:00";
                            try {
                                $dt = new DateTime("$interviewDate $timeStr", new DateTimeZone('Asia/Bangkok'));
                                $ts = $dt->getTimestamp();
                            } catch (Exception $e) {
                                $ts = strtotime("$interviewDate $timeStr");
                            }
                        }

                        if ($ts) {
                            $thai_months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
                            $d = date('j', $ts);
                            $m = $thai_months[(int) date('n', $ts)];
                            $y = date('Y', $ts) + 543;
                            $t = date('H:i', $ts);
                            $descText .= "🗓️ วันที่: **$d $m $y**\n";
                            $descText .= "🕒 เวลา: **$t น.**\n";
                            $descText .= "⏱️ <t:" . $ts . ":F> (<t:" . $ts . ":R>)\n";
                        } else {
                            $descText .= "🗓️ วันที่: **$interviewDate**\n";
                        }

                        // Send Accepted Notification via Bot API
                        $destWelcome = $app['webhook_url_welcome'];

                        if (!empty($destWelcome)) {
                            $embedData = [
                                'delivery_method' => 'channel',
                                'content' => [
                                    'title' => '⚡ ' . $app['form_title'],
                                    'description' => $descText
                                ],
                                'style' => [
                                    'color' => 12951641, // Gold
                                    'thumbnail' => 'https://cdn-icons-png.flaticon.com/512/906/906334.png'
                                ],
                                'branding' => [
                                    'enabled' => true,
                                    'author_name' => '🎉 Application Accepted',
                                    'author_icon' => 'https://cdn-icons-png.flaticon.com/512/2921/2921222.png',
                                    'footer_text' => 'STORM System • ' . date('Y-m-d H:i')
                                ]
                            ];
                            $botApi->sendEmbed($destWelcome, $embedData);
                        }
                    }

                    // --- AUTO ROLE ASSIGNMENT ---
                    if (!empty($app['give_role_id'])) {
                        // Find the applicant's Discord user ID from answers
                        $discordUserId = null;

                        // Fetch answers & question types if not already loaded
                        if (empty($webhookAnswers)) {
                            $aStmt = $pdo->prepare("SELECT a.answer_text, q.question_type, q.question_text FROM form_answers a JOIN form_questions q ON a.question_id = q.id WHERE a.response_id = ?");
                            $aStmt->execute([$id]);
                            $roleAnswers = $aStmt->fetchAll();
                        } else {
                            // Re-fetch with question_type
                            $aStmt = $pdo->prepare("SELECT a.answer_text, q.question_type, q.question_text FROM form_answers a JOIN form_questions q ON a.question_id = q.id WHERE a.response_id = ?");
                            $aStmt->execute([$id]);
                            $roleAnswers = $aStmt->fetchAll();
                        }

                        foreach ($roleAnswers as $ra) {
                            // Priority 1: discord_user picker type (contains JSON with id)
                            if ($ra['question_type'] === 'discord_user') {
                                $dData = @json_decode($ra['answer_text'], true);
                                if ($dData && !empty($dData['id'])) {
                                    $discordUserId = $dData['id'];
                                    break;
                                }
                            }
                        }

                        if ($discordUserId) {
                            try {
                                $roleResult = $botApi->assignRole($discordUserId, $app['give_role_id']);
                                if (!empty($roleResult['success'])) {
                                    error_log("[ROLE_ASSIGN] Success: role {$app['give_role_id']} assigned to user {$discordUserId} for response #{$id}");
                                } else {
                                    $roleErr = $roleResult['error'] ?? 'Unknown error';
                                    error_log("[ROLE_ASSIGN] Failed: {$roleErr} (role: {$app['give_role_id']}, user: {$discordUserId}, response: #{$id})");
                                }
                            } catch (Exception $e) {
                                error_log("[ROLE_ASSIGN] Exception: " . $e->getMessage());
                            }
                        } else {
                            error_log("[ROLE_ASSIGN] No Discord user ID found in answers for response #{$id}");
                        }
                    }

                } elseif ($status === 'rejected') {
                    // --- DISCORD REJECTED NOTIFICATION ---
                    if (!empty($app['webhook_url_welcome'])) {
                        // 1. Fetch Answers
                        $aStmt = $pdo->prepare("SELECT question_id, answer_text FROM form_answers WHERE response_id = ?");
                        $aStmt->execute([$id]);
                        $webhookAnswers = $aStmt->fetchAll();

                        // 2. Fetch Questions Map
                        $qStmt = $pdo->prepare("SELECT id, question_text FROM form_questions WHERE form_id = ?");
                        $qStmt->execute([$app['form_id']]);
                        $qMap = [];
                        while ($q = $qStmt->fetch())
                            $qMap[$q['id']] = $q['question_text'];

                        // 3. Extract Applicant Name
                        $applicantDiscord = "Guest/Unknown";
                        foreach ($webhookAnswers as $ans) {
                            if (!isset($qMap[$ans['question_id']]))
                                continue;
                            $qText = strtolower($qMap[$ans['question_id']]);
                            if (strpos($qText, 'discord') !== false) {
                                $ansVal = $ans['answer_text'];
                                // Try to parse as JSON (from discord_user picker)
                                $discordData = @json_decode($ansVal, true);
                                if ($discordData && isset($discordData['id'])) {
                                    $applicantDiscord = '<@' . $discordData['id'] . '>';
                                } else {
                                    $applicantDiscord = trim($ansVal);
                                }
                            }
                        }
                        if ($applicantDiscord === "Guest/Unknown" && !empty($app['personaname'])) {
                            $applicantDiscord = $app['personaname'];
                        }

                        $rejectText = ">>> ";
                        $rejectText .= "👤 **ผู้สมัคร (Applicant):** $applicantDiscord\n";
                        $rejectText .= "❌ **สถานะ (Status):** ไม่ผ่านการคัดเลือก (Rejected)\n\n";
                        $rejectText .= "📝 **รายละเอียด (Note):**\n";

                        if (!empty($reason)) {
                            $rejectText .= $reason;
                        } else {
                            $rejectText .= "ท่านไม่ผ่านการรับเข้าเลือก โปรดอ่านรายละเอียดและกฏ\n";
                            $rejectText .= "หากมีความพร้อมในการสมัครให้ กรอกใบสมัครมาอีกรอบ\n";
                            $rejectText .= "ขอบคุณสำหรับการสนใจเข้าร่วม";
                        }

                        // Send Rejected Notification via Bot API
                        $destWelcome = $app['webhook_url_welcome'];

                        if (!empty($destWelcome)) {
                            $embedData = [
                                'delivery_method' => 'channel',
                                'content' => [
                                    'title' => '⚡ ' . $app['form_title'],
                                    'description' => $rejectText
                                ],
                                'style' => [
                                    'color' => 15158332, // Red
                                    'thumbnail' => 'https://cdn-icons-png.flaticon.com/512/1828/1828843.png'
                                ],
                                'branding' => [
                                    'enabled' => true,
                                    'author_name' => 'Application Update',
                                    'author_icon' => 'https://cdn-icons-png.flaticon.com/512/1828/1828843.png',
                                    'footer_text' => 'STORM System • ' . date('Y-m-d H:i')
                                ]
                            ];
                            $botApi->sendEmbed($destWelcome, $embedData);
                        }
                    }
                }
            }

            echo json_encode(['success' => true]);
            break;



        case 'promote_to_member':
            $response_id = $_POST['response_id'] ?? null;
            if (!$response_id) {
                echo json_encode(['success' => false, 'message' => 'Missing response ID']);
                exit;
            }

            // Ensure promoted_user_id column exists
            try {
                $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'promoted_user_id'");
                $colCheck->execute();
                if ($colCheck->fetchColumn() == 0) {
                    $pdo->exec("ALTER TABLE form_responses ADD COLUMN promoted_user_id INT(11) DEFAULT NULL");
                }
            } catch (PDOException $e) { /* column may already exist */
            }

            // Fetch response
            $stmt = $pdo->prepare("SELECT * FROM form_responses WHERE id = ?");
            $stmt->execute([$response_id]);
            $response = $stmt->fetch();

            if (!$response) {
                echo json_encode(['success' => false, 'message' => 'Response not found']);
                exit;
            }

            if ($response['promoted_user_id']) {
                echo json_encode(['success' => false, 'message' => 'Already promoted']);
                exit;
            }

            if ($response['status'] !== 'accepted') {
                echo json_encode(['success' => false, 'message' => 'Application must be accepted before promoting']);
                exit;
            }

            // Fetch answers
            $aStmt = $pdo->prepare("SELECT a.answer_text, q.question_type, q.question_text FROM form_answers a JOIN form_questions q ON a.question_id = q.id WHERE a.response_id = ?");
            $aStmt->execute([$response_id]);
            $answers = $aStmt->fetchAll();

            $discordData = null;
            $steamId = '';
            $personaName = '';

            foreach ($answers as $ans) {
                $qType = $ans['question_type'];
                $qText = strtolower($ans['question_text']);
                $ansVal = $ans['answer_text'];

                if ($qType === 'discord_user') {
                    $dData = @json_decode($ansVal, true);
                    if ($dData && !empty($dData['id'])) {
                        $discordData = $dData;
                    }
                } elseif (strpos($qText, 'discord') !== false && !$discordData) {
                    $dData = @json_decode($ansVal, true);
                    if ($dData && !empty($dData['id'])) {
                        $discordData = $dData;
                    }
                }

                if (strpos($qText, 'steam') !== false && empty($steamId)) {
                    preg_match('/\d{17}/', $ansVal, $matches);
                    if (!empty($matches[0])) {
                        $steamId = $matches[0];
                    } else if (is_numeric(trim($ansVal)) && strlen(trim($ansVal)) >= 15) {
                        $steamId = trim($ansVal);
                    }
                }

                if (strpos($qText, 'ชื่อ') !== false && strpos($qText, 'discord') === false && $qType !== 'discord_user') {
                    if (strpos(trim($ansVal), '{') !== 0) {
                        if (strpos($qText, 'ตัวละคร') !== false) {
                            $personaName = trim($ansVal);
                        } elseif (empty($personaName)) {
                            $personaName = trim($ansVal);
                        }
                    }
                }
            }

            if (!$discordData && empty($personaName)) {
                echo json_encode(['success' => false, 'message' => 'Could not find Discord user data or Persona name in application']);
                exit;
            }

            // Prioritize Character Name (personaName) over Discord Display Name
            $displayName = !empty($personaName) ? $personaName : ($discordData['display_name'] ?? $discordData['username'] ?? 'New Member');
            $username = $discordData['username'] ?? str_replace(' ', '', $displayName);
            $avatar = $discordData['avatar'] ?? '/assets/images/default_avatar.png';
            $discordId = $discordData['id'] ?? null;

            $loginToken = null;
            $genPassword = null;

            try {
                // Check if user exists
                $user = null;
                if (!empty($steamId)) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid = ?");
                    $stmt->execute([$steamId]);
                    $user = $stmt->fetch();
                }

                if (!$user && !empty($username)) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                }

                $rank = 'Private (PV2)';
                $status = 'Active';
                $role = 'user';

                if (!$user) {
                    // Create new user
                    if (function_exists('random_bytes')) {
                        $genPassword = bin2hex(random_bytes(4));
                    } else {
                        $genPassword = substr(md5(mt_rand()), 0, 8);
                    }
                    $hashedPassword = password_hash($genPassword, PASSWORD_DEFAULT);
                    $profileUrl = $steamId ? "https://steamcommunity.com/profiles/" . $steamId : "";

                    // Ensure discord_id column exists
                    try {
                        $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'discord_id'");
                        $colCheck->execute();
                        if ($colCheck->fetchColumn() == 0) {
                            $pdo->exec("ALTER TABLE users ADD COLUMN discord_id VARCHAR(30) DEFAULT NULL");
                        }
                    } catch (PDOException $e) {
                    }

                    $stmt = $pdo->prepare("INSERT INTO users (steamid, personaname, avatar, profileurl, username, password, generated_password, `rank`, status, `role`, discord_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $steamId,
                        $displayName,
                        $avatar,
                        $profileUrl,
                        $username,
                        $hashedPassword,
                        $genPassword,
                        $rank,
                        $status,
                        $role,
                        $discordId
                    ]);
                    $userId = $pdo->lastInsertId();
                } else {
                    $userId = $user['id'];
                    $newRank = $user['rank'];
                    if (empty($newRank) || $newRank === 'Recruit' || $newRank === '') {
                        $newRank = $rank;
                    }

                    $stmt = $pdo->prepare("UPDATE users SET personaname = ?, avatar = ?, status = ?, `rank` = ? WHERE id = ?");
                    $stmt->execute([$displayName, $avatar, $status, $newRank, $userId]);
                }

                // Update form_responses
                $stmt = $pdo->prepare("UPDATE form_responses SET promoted_user_id = ? WHERE id = ?");
                $stmt->execute([$userId, $response_id]);

                // Log admin action
                if (file_exists(ROOT_PATH . '/includes/admin_log.php')) {
                    require_once ROOT_PATH . '/includes/admin_log.php';
                    $currentAdmin = function_exists('getUser') ? getUser() : ($_SESSION['user'] ?? null);
                    $adminId = $currentAdmin ? $currentAdmin['id'] : 0;
                    if (function_exists('logAdminAction')) {
                        logAdminAction($pdo, 'promote_application_to_member', 'application', $response_id, ['promoted_user_id' => $userId]);
                    }
                }

                echo json_encode([
                    'success' => true,
                    'user_id' => $userId,
                    'is_new' => !$user,
                    'username' => $username,
                    'password' => $genPassword
                ]);

            } catch (Exception $e) {
                error_log("[PROMOTE_MEMBER_ERROR] " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'test_webhook':
            $channelId = trim($_POST['webhook_url']);

            if (empty($channelId)) {
                echo json_encode(['success' => false, 'message' => 'Channel ID is empty']);
                exit;
            }

            // Using Bot API to send test message
            require_once ROOT_PATH . '/includes/bot_api.php';
            $botApi = new BotAPI();

            $embedData = [
                'delivery_method' => 'channel',
                'content' => [
                    'title' => 'Test Notification',
                    'description' => "✅ **Connection Successful**\n\nThe bot can successfully send messages to this channel ($channelId).",
                    'fields' => [
                        ['name' => 'Time', 'value' => date('H:i:s'), 'inline' => true]
                    ]
                ],
                'style' => [
                    'color' => 5763719, // Green
                ],
                'branding' => [
                    'enabled' => true,
                    'author_name' => 'Connection Test',
                    'footer_text' => 'STORM System'
                ]
            ];

            $res = $botApi->sendEmbed($channelId, $embedData);
            echo json_encode($res);
            break;

        case 'simulate_application':
            $form_id = $_POST['form_id'];

            // Fetch Form & Questions
            $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
            $stmt->execute([$form_id]);
            $formMeta = $stmt->fetch();

            if (!$formMeta) {
                echo json_encode(['success' => false, 'message' => 'Form not found']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM form_questions WHERE form_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$form_id]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Generate Dummy Answers
            $dummyFiles = [
                'text' => ["John Doe", "Jane Smith", "Commander Shepard", "Soap MacTavish"],
                'textarea' => "I have over 1000 hours in Arma 3 and have led multiple operations.",
                'radio' => 0, // Will pick first option
                'checkbox' => 0, // Will pick first
                'select' => 0, // Will pick first
                'date' => date('Y-m-d'),
                'email' => 'test@example.com'
            ];

            $discord_fields = [];
            $applicantDiscord = "SimulatedUser#1234";

            foreach ($questions as $q) {
                $qText = $q['question_text'];
                $val = "Test Answer";

                if ($q['question_type'] == 'text') {
                    if (strpos(strtolower($qText), 'name') !== false)
                        $val = $dummyFiles['text'][array_rand($dummyFiles['text'])];
                    elseif (strpos(strtolower($qText), 'discord') !== false)
                        $val = "<@0>"; // Simulated Mention
                    elseif (strpos(strtolower($qText), 'email') !== false)
                        $val = "test@simulation.com";
                    else
                        $val = "Simulated short answer";
                } elseif ($q['question_type'] == 'textarea') {
                    $val = "This is a simulated paragraph answer generated for testing purposes. It represents what a user might type.";
                } elseif (in_array($q['question_type'], ['radio', 'select', 'checkbox'])) {
                    $opts = json_decode($q['options'] ?? '[]', true);
                    if (!empty($opts)) {
                        $val = $opts[0]; // Pick first option
                    }
                } elseif ($q['question_type'] == 'date') {
                    $val = date('Y-m-d');
                }

                // Build Discord Field
                $discord_fields[] = [
                    'name' => $qText,
                    'value' => (string) $val,
                    'inline' => false
                ];
            }

            require_once ROOT_PATH . '/includes/bot_api.php';
            $botApi = new BotAPI();
            $results = [];

            // 1. Send to Staff Channel (Full Application)
            if (!empty($formMeta['webhook_url_staff'])) {
                $embedData = [
                    'delivery_method' => 'channel',
                    'content' => [
                        'title' => '📝 New Application (Simulation)',
                        'description' => ">>> **Form:** {$formMeta['title']}\n**Applicant:** <@0>\n**Status:** 🟡 Pending Review (Test)\n\n_This is a simulation test._",
                        'fields' => $discord_fields
                    ],
                    'style' => [
                        'color' => 16776960, // Yellow
                        'thumbnail' => 'https://cdn-icons-png.flaticon.com/512/2921/2921222.png'
                    ],
                    'branding' => [
                        'enabled' => true,
                        'author_name' => 'STORM Recruitment',
                        'footer_text' => 'Simulation Mode • ' . date('Y-m-d H:i')
                    ]
                ];
                $results['staff'] = $botApi->sendEmbed($formMeta['webhook_url_staff'], $embedData);
            }

            // 2. Send to Public Channel (Alert)
            if (!empty($formMeta['webhook_url_public'])) {
                $publicMsg = ">>> **New Application Received (Test)**\n\n👤 **Applicant:** <@0>\n📄 **Form:** {$formMeta['title']}\n🕒 **Time:** " . date('H:i');
                $embedData = [
                    'delivery_method' => 'channel',
                    'content' => [
                        'title' => '🔔 Update (Simulation)',
                        'description' => $publicMsg
                    ],
                    'style' => [
                        'color' => 5793266, // Blurple
                    ],
                    'branding' => [
                        'enabled' => true,
                        'author_name' => 'STORM Alert',
                        'footer_text' => 'Simulation Mode'
                    ]
                ];
                $results['public'] = $botApi->sendEmbed($formMeta['webhook_url_public'], $embedData);
            }

            // 3. Send to Welcome Channel (Accepted Simulation)
            if (!empty($formMeta['webhook_url_welcome'])) {
                $welcomeMsg = ">>> **Welcome to the Team! (Test)**\n\n👤 **Member:** <@0>\n🎉 **Position:** Recruit (Simulated)\n\n_This is a test notification._";
                $embedData = [
                    'delivery_method' => 'channel',
                    'content' => [
                        'title' => '🎉 User Accepted (Simulation)',
                        'description' => $welcomeMsg
                    ],
                    'style' => [
                        'color' => 5763719, // Green
                    ],
                    'branding' => [
                        'enabled' => true,
                        'author_name' => 'STORM Command',
                        'footer_text' => 'Simulation Mode'
                    ]
                ];
                $results['welcome'] = $botApi->sendEmbed($formMeta['webhook_url_welcome'], $embedData);
            }

            echo json_encode(['success' => true, 'results' => $results]);
            break;

        case 'test_form_webhook':
            $form_id = $_POST['form_id'];
            $type = $_POST['type'] ?? 'staff'; // 'staff', 'welcome', 'public'

            $stmt = $pdo->prepare("SELECT title, webhook_url_staff, webhook_url_welcome, webhook_url_public FROM forms WHERE id = ?");
            $stmt->execute([$form_id]);
            $form = $stmt->fetch();

            $channelId = '';
            if ($type === 'welcome')
                $channelId = $form['webhook_url_welcome'];
            elseif ($type === 'public')
                $channelId = $form['webhook_url_public'];
            else
                $channelId = $form['webhook_url_staff'];

            if (!$form || empty($channelId)) {
                echo json_encode(['success' => false, 'message' => 'No channel configured for this type.']);
                exit;
            }

            // Generate Admin Link (Simulated for Test)
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $adminLink = "$protocol://$host/admin/applications.php?view=forms";

            $desc = "✅ **Connection Successful**\n\nThis is a test notification for the **" . strtoupper($type) . "** channel.\n\n[Manage Forms]($adminLink)";

            // Send via Bot API
            $botApi = new BotAPI();

            $embedData = [
                'delivery_method' => 'channel',
                'content' => [
                    'title' => $form['title'],
                    'description' => $desc,
                    'fields' => [
                        ['name' => 'Channel Type', 'value' => ucfirst($type), 'inline' => true],
                        ['name' => 'Test Time', 'value' => date('d M Y, H:i'), 'inline' => true]
                    ]
                ],
                'style' => [
                    'color' => 5763719, // Green
                    'thumbnail' => 'https://cdn-icons-png.flaticon.com/512/906/906334.png'
                ],
                'branding' => [
                    'enabled' => true,
                    'author_name' => '🔧 Connection Test',
                    'author_icon' => 'https://cdn-icons-png.flaticon.com/512/2921/2921222.png',
                    'footer_text' => 'STORM System • Bot API'
                ]
            ];

            $res = $botApi->sendEmbed($channelId, $embedData);
            echo json_encode($res);
            break;

        case 'set_main_form':
            $id = $_POST['id'];

            // 1. Reset all
            $pdo->exec("UPDATE forms SET is_main_application = 0");

            // 2. Set new main
            $stmt = $pdo->prepare("UPDATE forms SET is_main_application = 1 WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        case 'update_application_answers':
            $response_id = $_POST['response_id'] ?? null;
            $answers = $_POST['answers'] ?? [];

            if (!$response_id) {
                echo json_encode(['success' => false, 'message' => 'Missing response ID']);
                break;
            }

            // Verify response exists
            $checkStmt = $pdo->prepare("SELECT id FROM form_responses WHERE id = ?");
            $checkStmt->execute([$response_id]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Response not found']);
                break;
            }

            foreach ($answers as $question_id => $answer_value) {
                // Handle checkbox arrays - convert to JSON
                if (is_array($answer_value)) {
                    $answer_value = json_encode($answer_value, JSON_UNESCAPED_UNICODE);
                }

                // Check if answer exists
                $existsStmt = $pdo->prepare("SELECT id FROM form_answers WHERE response_id = ? AND question_id = ?");
                $existsStmt->execute([$response_id, $question_id]);

                if ($existsStmt->fetch()) {
                    // Update existing answer
                    $updateStmt = $pdo->prepare("UPDATE form_answers SET answer_text = ? WHERE response_id = ? AND question_id = ?");
                    $updateStmt->execute([$answer_value, $response_id, $question_id]);
                } else {
                    // Insert new answer if it doesn't exist
                    $insertStmt = $pdo->prepare("INSERT INTO form_answers (response_id, question_id, answer_text) VALUES (?, ?, ?)");
                    $insertStmt->execute([$response_id, $question_id, $answer_value]);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Application updated successfully']);
            break;

        case 'delete_response':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM form_responses WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action ' . $action]);
            break;
    }
} catch (Throwable $e) {
    ob_end_clean(); // Clean buffer so only JSON is output
    error_log("Global Error in form_actions.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
    exit;
}
?>