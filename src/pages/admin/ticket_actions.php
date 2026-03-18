<?php
/**
 * Ticket System Backend API
 * Handles CRUD for ticket panels and ticket management
 */
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/bot_api.php';

header('Content-Type: application/json');

// Bot-originating actions don't have admin session - allow them through
$botActions = ['save_ticket', 'close_ticket_by_channel', 'list_tickets', 'get_panel', 'save_transcript', 'get_transcript'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!in_array($action, $botActions) && !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Auto-create tables if not exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ticket_panels` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL DEFAULT 'Support Ticket',
        `description` TEXT,
        `channel_id` VARCHAR(30) NOT NULL COMMENT 'Channel where panel embed is posted',
        `category_id` VARCHAR(30) DEFAULT NULL COMMENT 'Category for new ticket channels',
        `support_role_id` VARCHAR(30) DEFAULT NULL COMMENT 'Role that can see tickets',
        `button_text` VARCHAR(80) DEFAULT 'Open Ticket',
        `button_color` VARCHAR(20) DEFAULT 'green',
        `button_emoji` VARCHAR(10) DEFAULT '🎫',
        `embed_color` VARCHAR(10) DEFAULT '#5865F2',
        `welcome_message` TEXT COMMENT 'Message sent when ticket is created',
        `max_tickets` INT(3) DEFAULT 1 COMMENT 'Max open tickets per user',
        `message_id` VARCHAR(30) DEFAULT NULL COMMENT 'Discord message ID of the panel',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tickets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `panel_id` INT(11) NOT NULL,
        `user_id` VARCHAR(30) NOT NULL COMMENT 'Discord user ID',
        `user_name` VARCHAR(100) DEFAULT NULL,
        `channel_id` VARCHAR(30) DEFAULT NULL COMMENT 'Created ticket channel ID',
        `channel_name` VARCHAR(100) DEFAULT NULL,
        `status` ENUM('open','closed') DEFAULT 'open',
        `closed_by` VARCHAR(100) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `closed_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_panel_id` (`panel_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `ticket_transcripts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `ticket_id` INT(11) NOT NULL,
        `messages` LONGTEXT NOT NULL COMMENT 'JSON array of messages',
        `message_count` INT(11) DEFAULT 0,
        `saved_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_ticket_id` (`ticket_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Tables may already exist
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$api = new BotAPI();

switch ($action) {

    // ========== PANEL CRUD ==========
    case 'list_panels':
        $stmt = $pdo->query("SELECT p.*, 
            (SELECT COUNT(*) FROM tickets t WHERE t.panel_id = p.id AND t.status = 'open') as open_tickets,
            (SELECT COUNT(*) FROM tickets t WHERE t.panel_id = p.id) as total_tickets
            FROM ticket_panels p ORDER BY p.created_at DESC");
        $panels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'panels' => $panels]);
        break;

    case 'get_panel':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM ticket_panels WHERE id = ?");
        $stmt->execute([$id]);
        $panel = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($panel) {
            echo json_encode(['success' => true, 'panel' => $panel]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Panel not found']);
        }
        break;

    case 'save_panel':
        $id = $_POST['id'] ?? null;
        $data = [
            'title' => $_POST['title'] ?? 'Support Ticket',
            'description' => $_POST['description'] ?? '',
            'channel_id' => $_POST['channel_id'] ?? '',
            'category_id' => $_POST['category_id'] ?? '',
            'support_role_id' => $_POST['support_role_id'] ?? '',
            'button_text' => $_POST['button_text'] ?? 'Open Ticket',
            'button_color' => $_POST['button_color'] ?? 'green',
            'button_emoji' => $_POST['button_emoji'] ?? '🎫',
            'embed_color' => $_POST['embed_color'] ?? '#5865F2',
            'welcome_message' => $_POST['welcome_message'] ?? '',
            'max_tickets' => intval($_POST['max_tickets'] ?? 1),
        ];

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE ticket_panels SET title=?, description=?, channel_id=?, category_id=?, support_role_id=?, button_text=?, button_color=?, button_emoji=?, embed_color=?, welcome_message=?, max_tickets=? WHERE id=?");
            $stmt->execute([
                $data['title'], $data['description'], $data['channel_id'],
                $data['category_id'], $data['support_role_id'],
                $data['button_text'], $data['button_color'], $data['button_emoji'],
                $data['embed_color'], $data['welcome_message'], $data['max_tickets'],
                $id
            ]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO ticket_panels (title, description, channel_id, category_id, support_role_id, button_text, button_color, button_emoji, embed_color, welcome_message, max_tickets) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $data['title'], $data['description'], $data['channel_id'],
                $data['category_id'], $data['support_role_id'],
                $data['button_text'], $data['button_color'], $data['button_emoji'],
                $data['embed_color'], $data['welcome_message'], $data['max_tickets']
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        break;

    case 'delete_panel':
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM ticket_panels WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'deploy_panel':
        // Send the panel embed to the configured Discord channel
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM ticket_panels WHERE id = ?");
        $stmt->execute([$id]);
        $panel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$panel) {
            echo json_encode(['success' => false, 'error' => 'Panel not found']);
            break;
        }

        if (empty($panel['channel_id'])) {
            echo json_encode(['success' => false, 'error' => 'No channel configured']);
            break;
        }

        // Send panel via bot API
        $result = $api->request('/tickets/panel/send', [
            'panel_id' => $panel['id'],
            'channel_id' => $panel['channel_id'],
            'title' => $panel['title'],
            'description' => $panel['description'],
            'button_text' => $panel['button_text'],
            'button_color' => $panel['button_color'],
            'button_emoji' => $panel['button_emoji'],
            'embed_color' => $panel['embed_color'],
        ]);

        if (!empty($result['success']) && !empty($result['data']['message_id'])) {
            // Save the message ID
            $upd = $pdo->prepare("UPDATE ticket_panels SET message_id = ? WHERE id = ?");
            $upd->execute([$result['data']['message_id'], $id]);
            echo json_encode(['success' => true, 'message_id' => $result['data']['message_id']]);
        } else {
            $err = $result['error'] ?? $result['data']['error'] ?? 'Unknown error';
            echo json_encode(['success' => false, 'error' => $err]);
        }
        break;

    // ========== TICKET LIST ==========
    case 'list_tickets':
        $panel_id = $_GET['panel_id'] ?? null;
        $status = $_GET['status'] ?? null;

        $sql = "SELECT t.*, p.title as panel_title FROM tickets t JOIN ticket_panels p ON t.panel_id = p.id WHERE 1=1";
        $params = [];

        if ($panel_id) {
            $sql .= " AND t.panel_id = ?";
            $params[] = $panel_id;
        }
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY t.created_at DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'tickets' => $tickets]);
        break;

    case 'close_ticket':
        $ticket_id = $_POST['ticket_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            echo json_encode(['success' => false, 'error' => 'Ticket not found']);
            break;
        }

        // Close via bot (delete/archive channel)
        if (!empty($ticket['channel_id'])) {
            $api->request('/tickets/close', [
                'channel_id' => $ticket['channel_id'],
                'ticket_id' => $ticket['id']
            ]);
        }

        // Update DB
        $currentUser = getUser();
        $closedBy = $currentUser ? ($currentUser['personaname'] ?? $currentUser['username'] ?? 'Admin') : 'Admin';
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed', closed_by = ?, closed_at = NOW() WHERE id = ?");
        $stmt->execute([$closedBy, $ticket_id]);

        echo json_encode(['success' => true]);
        break;

    case 'get_categories':
        // Get Discord channel categories via bot
        $result = $api->request('/categories', [], 'GET');
        if (!empty($result['success'])) {
            echo json_encode(['success' => true, 'categories' => $result['data']['categories'] ?? []]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed']);
        }
        break;

    // Called by the bot when a user clicks the Open Ticket button
    case 'save_ticket':
        $panel_id = $_POST['panel_id'] ?? 0;
        $user_id = $_POST['user_id'] ?? '';
        $user_name = $_POST['user_name'] ?? '';
        $channel_id = $_POST['channel_id'] ?? '';
        $channel_name = $_POST['channel_name'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO tickets (panel_id, user_id, user_name, channel_id, channel_name) VALUES (?,?,?,?,?)");
        $stmt->execute([$panel_id, $user_id, $user_name, $channel_id, $channel_name]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    // Called by the bot when a user clicks the Close Ticket button
    case 'close_ticket_by_channel':
        $channel_id = $_POST['channel_id'] ?? '';
        $closed_by = $_POST['closed_by'] ?? 'Unknown';

        // Look up ticket_id first (needed for transcript saving)
        $lookupStmt = $pdo->prepare("SELECT id FROM tickets WHERE channel_id = ? AND status = 'open' LIMIT 1");
        $lookupStmt->execute([$channel_id]);
        $ticketRow = $lookupStmt->fetch(PDO::FETCH_ASSOC);
        $ticket_id = $ticketRow ? $ticketRow['id'] : null;

        $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed', closed_by = ?, closed_at = NOW() WHERE channel_id = ? AND status = 'open'");
        $stmt->execute([$closed_by, $channel_id]);
        echo json_encode(['success' => true, 'affected' => $stmt->rowCount(), 'ticket_id' => $ticket_id]);
        break;

    // ========== TRANSCRIPT ==========
    case 'save_transcript':
        $ticket_id = $_POST['ticket_id'] ?? 0;
        $messages = $_POST['messages'] ?? '[]';
        $message_count = $_POST['message_count'] ?? 0;

        if (!$ticket_id) {
            echo json_encode(['success' => false, 'error' => 'Missing ticket_id']);
            break;
        }

        // Upsert: replace if already exists
        $stmt = $pdo->prepare("INSERT INTO ticket_transcripts (ticket_id, messages, message_count) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE messages = VALUES(messages), message_count = VALUES(message_count), saved_at = NOW()");
        $stmt->execute([$ticket_id, $messages, $message_count]);
        echo json_encode(['success' => true]);
        break;

    case 'get_transcript':
        $ticket_id = $_GET['ticket_id'] ?? 0;
        if (!$ticket_id) {
            echo json_encode(['success' => false, 'error' => 'Missing ticket_id']);
            break;
        }

        $stmt = $pdo->prepare("SELECT t.*, tk.user_name, tk.channel_name, tk.created_at as ticket_created, tk.closed_at as ticket_closed, tk.closed_by, p.title as panel_title FROM ticket_transcripts t JOIN tickets tk ON t.ticket_id = tk.id LEFT JOIN ticket_panels p ON tk.panel_id = p.id WHERE t.ticket_id = ?");
        $stmt->execute([$ticket_id]);
        $transcript = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transcript) {
            echo json_encode(['success' => true, 'transcript' => $transcript]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No transcript found for this ticket']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}

