<?php
// src/admin/applications.php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$pageTitle = "Applications Manager";
include ROOT_PATH . '/admin/includes/admin_header.php';

// View Controller
$view = $_GET['view'] ?? 'applications'; // applications, forms, form_builder, form_responses
?>

<link rel="stylesheet" href="/assets/css/forms.css">
<link rel="stylesheet" href="/assets/css/admin_media.css">

<style>
    /* Applications Page Specific Styles to Match & Enhance Admin Theme */
    .media-manager {
        max-width: 1600px;
        margin: 0 auto;
        padding: 0 2rem;
        width: 100%;
    }

    .stat-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.01) 100%);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        border-color: rgba(197, 160, 89, 0.3);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        opacity: 0.5;
    }

    .stat-icon-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    /* Table Polish */
    .table-container {
        background: var(--bg-panel);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .custom-table th {
        background: rgba(0, 0, 0, 0.3);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        color: var(--text-muted);
    }

    .nav-tabs-custom {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
        border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        padding-bottom: 0;
    }

    .nav-link-custom {
        color: rgba(255, 255, 255, 0.6);
        background: transparent;
        border: none;
        padding: 10px 20px;
        font-weight: 600;
        border-bottom: 3px solid transparent;
        transition: all 0.3s;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link-custom:hover {
        color: #fff;
    }

    .nav-link-custom.active {
        color: var(--accent);
        border-bottom-color: var(--accent);
        background: rgba(197, 160, 89, 0.05);
    }

    .custom-table tr {
        transition: background 0.2s ease;
    }

    .custom-table tr:hover {
        background: rgba(255, 255, 255, 0.02) !important;
    }

    .custom-table td {
        vertical-align: middle;
        border-color: rgba(255, 255, 255, 0.05) !important;
    }

    .builder-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 2rem;
        align-items: start;
    }

    .toolbox-panel {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        position: sticky;
        top: 20px;
    }

    .toolbox-title {
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
        font-weight: 700;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 1rem;
    }

    .tool-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .tool-card:hover {
        background: rgba(33, 150, 243, 0.1);
        border-color: rgba(33, 150, 243, 0.3);
        transform: translateX(5px);
    }

    .tool-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: var(--text-muted);
        transition: all 0.2s ease;
    }

    .tool-card:hover .tool-icon {
        background: rgba(33, 150, 243, 0.2);
        color: #2196f3;
    }

    .tool-name {
        font-weight: 500;
        color: var(--text-main);
        font-size: 0.95rem;
    }

    /* Form Canvas */
    .form-header-card {
        background: linear-gradient(135deg, rgba(20, 20, 20, 0.9) 0%, rgba(30, 30, 30, 0.9) 100%);
        border: 1px solid rgba(197, 160, 89, 0.2);
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
        /* accent border top */
        border-top: 4px solid var(--accent);
        border-radius: 16px;
        padding: 2.5rem;
        margin-bottom: 2rem;
    }

    .form-title-input {
        background: transparent;
        border: none;
        border-bottom: 1px solid transparent;
        color: var(--text-main);
        font-size: 2.5rem;
        font-weight: 700;
        width: 100%;
        margin-bottom: 1rem;
        padding: 0.5rem 0;
        transition: border-color 0.3s;
        font-family: 'Oswald', sans-serif;
        /* Assuming Oswald is available or fallback */
        letter-spacing: 1px;
    }

    .form-title-input:focus {
        outline: none;
        border-bottom-color: var(--accent);
    }

    .form-desc-input {
        background: transparent;
        border: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--text-muted);
        font-size: 1.1rem;
        width: 100%;
        padding: 0.5rem 0;
    }

    .form-desc-input:focus {
        outline: none;
        border-bottom-color: var(--accent);
        color: var(--text-main);
    }

    /* Custom Form List Card Polish */
    .form-list-card {
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        transition: all 0.3s ease;
    }

    .form-list-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border-color: rgba(197, 160, 89, 0.3);
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.02) 100%);
    }

    .question-item {
        background: rgba(30, 30, 30, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-left: 3px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.2s;
    }

    .question-item:hover {
        background: rgba(40, 40, 40, 0.9);
        border-left-color: var(--accent);
        transform: translateX(5px);
    }

    .question-item.dragging {
        opacity: 0.5;
        border: 2px dashed var(--accent);
    }

    .question-header {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1rem;
    }

    .drag-handle {
        color: rgba(255, 255, 255, 0.3);
        cursor: grab;
        padding: 0 10px;
    }

    .drag-handle:hover {
        color: #fff;
    }

    .question-text-input {
        flex: 1;
        background: transparent;
        border: none;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        padding: 0.5rem 0;
        font-size: 1.1rem;
        color: #fff;
        transition: all 0.3s;
    }

    .question-text-input:focus {
        outline: none;
        border-bottom-color: var(--accent);
        background: rgba(255, 255, 255, 0.02);
    }

    .question-type-select {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
    }

    .question-footer {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Action Buttons (Square Modern) */
    .btn-action-square {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        /* Modern Soft Square */
        background: rgba(255, 255, 255, 0.03);
        transition: all 0.2s ease;
        text-decoration: none !important;
        padding: 0;
        margin: 0;
        cursor: pointer;
        border-width: 1px;
        border-style: solid;
        flex-shrink: 0;
        /* Prevent shrinking */
    }

    .btn-action-square:hover {
        transform: translateY(-2px);
        background: rgba(255, 255, 255, 0.08);
    }

    .btn-action-square i {
        font-size: 14px;
    }

    /* Variants */
    .btn-action-square.view {
        border-color: rgba(255, 193, 7, 0.4);
        color: #ffc107;
    }

    .btn-action-square.view:hover {
        border-color: #ffc107;
        box-shadow: 0 4px 10px rgba(255, 193, 7, 0.2);
    }

    .btn-action-square.accept {
        border-color: rgba(40, 167, 69, 0.4);
        color: #28a745;
    }

    .btn-action-square.accept:hover {
        border-color: #28a745;
        box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
    }

    .btn-action-square.reject {
        border-color: rgba(220, 53, 69, 0.4);
        color: #dc3545;
    }


    .btn-action-square.reject:hover {
        border-color: #dc3545;
        box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2);
    }

    .btn-action-square.edit {
        border-color: rgba(33, 150, 243, 0.4);
        color: #2196f3;
    }

    .btn-action-square.edit:hover {
        border-color: #2196f3;
        box-shadow: 0 4px 10px rgba(33, 150, 243, 0.2);
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.75rem;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
        border: 1px solid transparent;
        transition: all 0.3s ease;
    }

    .status-badge i {
        font-size: 11px;
        margin-right: 6px;
    }

    .status-badge.accepted {
        background: rgba(76, 175, 80, 0.1);
        color: #4caf50;
        border-color: rgba(76, 175, 80, 0.2);
    }

    .status-badge.accepted:hover {
        background: rgba(76, 175, 80, 0.2);
        box-shadow: 0 0 10px rgba(76, 175, 80, 0.2);
    }

    .status-badge.rejected {
        background: rgba(244, 67, 54, 0.1);
        color: #f44336;
        border-color: rgba(244, 67, 54, 0.2);
    }

    .status-badge.rejected:hover {
        background: rgba(244, 67, 54, 0.2);
        box-shadow: 0 0 10px rgba(244, 67, 54, 0.2);
    }

    .status-badge.pending {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
        border-color: rgba(255, 193, 7, 0.2);
    }

    .status-badge.pending:hover {
        background: rgba(255, 193, 7, 0.2);
        box-shadow: 0 0 10px rgba(255, 193, 7, 0.2);
    }

    .user-info-cell {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-weight: 600;
        color: #fff;
        margin-bottom: 2px;
    }

    .user-discord {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
    }
</style>

<div class="media-manager">

    <!-- Header Section -->
    <div class="manager-header">
        <div class="manager-title">
            <h2><i class="fas fa-users-cog"></i> Applications Manager</h2>
        </div>
    </div>

    <div class="glass-panel" style="border:none; background:transparent;">
        <div class="nav-tabs-custom">
            <a href="applications.php?view=applications"
                class="nav-link-custom <?php echo $view === 'applications' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Member Applications
            </a>
            <a href="applications.php?view=summary"
                class="nav-link-custom <?php echo $view === 'summary' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Summary
            </a>
            <a href="applications.php?view=forms"
                class="nav-link-custom <?php echo $view === 'forms' ? 'active' : ''; ?>">
                <i class="fas fa-poll-h"></i> Manage Forms
            </a>
        </div>

        <!-- VIEW: MEMBER APPLICATIONS (Repurposed for Form Responses) -->
        <?php if ($view === 'applications'):
            $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
            $status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

            // Pagination settings
            $perPage = 5;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $offset = ($page - 1) * $perPage;

            // Stats from form_responses
            $stmt_stats = $pdo->query("SELECT status, COUNT(*) as count FROM form_responses GROUP BY status");
            $status_counts = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);
            $stats = [
                'total' => array_sum($status_counts),
                'pending' => $status_counts['pending'] ?? 0,
                'accepted' => $status_counts['accepted'] ?? 0,
                'rejected' => $status_counts['rejected'] ?? 0
            ];

            // Count total for pagination
            $countSql = "SELECT COUNT(*) FROM form_responses r WHERE 1=1";
            $countParams = [];
            if ($status) {
                $countSql .= " AND r.status = ?";
                $countParams[] = $status;
            }
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $totalRows = $countStmt->fetchColumn();
            $totalPages = ceil($totalRows / $perPage);

            // Main List Query with pagination
            $sql = "SELECT r.*, f.title as form_title, u.username, u.personaname 
                    FROM form_responses r 
                    JOIN forms f ON r.form_id = f.id 
                    LEFT JOIN users u ON r.user_id = u.id 
                    WHERE 1=1";
            $params = [];
            if ($search) {
                // Search is tricky with JSON answers. For now search form status
                //$sql .= " AND name LIKE ?"; 
                //$params[] = "%$search%";
            }
            if ($status) {
                $sql .= " AND r.status = ?";
                $params[] = $status;
            }
            $sql .= " ORDER BY r.submitted_at DESC LIMIT $perPage OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $applications = $stmt->fetchAll();

            // Pre-fetch Discord usernames for all applications
            $appIds = array_column($applications, 'id');
            $discordUsernames = [];
            if (!empty($appIds)) {
                $placeholders = implode(',', array_fill(0, count($appIds), '?'));
                $discordStmt = $pdo->prepare("
                    SELECT fa.response_id, fa.answer_text 
                    FROM form_answers fa 
                    JOIN form_questions fq ON fa.question_id = fq.id 
                    WHERE fa.response_id IN ($placeholders) 
                    AND fq.question_type = 'discord_user'
                ");
                $discordStmt->execute($appIds);
                while ($row = $discordStmt->fetch()) {
                    $data = @json_decode($row['answer_text'], true);
                    if ($data && isset($data['display_name'])) {
                        $discordUsernames[$row['response_id']] = [
                            'display_name' => $data['display_name'],
                            'username' => $data['username'] ?? '',
                            'avatar' => $data['avatar'] ?? ''
                        ];
                    }
                }
            }
            ?>
            <!-- Modern Stats Cards -->
            <div class="media-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                <div class="stat-card">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2">Total Apps</div>
                        <div class="display-6 fw-bold text-white"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="stat-icon-circle"
                        style="background: rgba(33, 150, 243, 0.1); color: #2196f3; box-shadow: 0 0 15px rgba(33, 150, 243, 0.2);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2">Pending</div>
                        <div class="display-6 fw-bold text-white"><?php echo $stats['pending']; ?></div>
                    </div>
                    <div class="stat-icon-circle"
                        style="background: rgba(255, 193, 7, 0.1); color: #ffc107; box-shadow: 0 0 15px rgba(255, 193, 7, 0.2);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2">Accepted</div>
                        <div class="display-6 fw-bold text-white"><?php echo $stats['accepted']; ?></div>
                    </div>
                    <div class="stat-icon-circle"
                        style="background: rgba(76, 175, 80, 0.1); color: #4caf50; box-shadow: 0 0 15px rgba(76, 175, 80, 0.2);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-2">Rejected</div>
                        <div class="display-6 fw-bold text-white"><?php echo $stats['rejected']; ?></div>
                    </div>
                    <div class="stat-icon-circle"
                        style="background: rgba(244, 67, 54, 0.1); color: #f44336; box-shadow: 0 0 15px rgba(244, 67, 54, 0.2);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-bar-container">
                <form method="GET" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="view" value="applications">

                    <div style="flex: 2; min-width: 200px;" class="modern-form">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Search applicant name..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div style="flex: 1; min-width: 150px;" class="modern-form">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending
                            </option>
                            <option value="accepted" <?php echo $status_filter == 'accepted' ? 'selected' : ''; ?>>Accepted
                            </option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn" style="height: 42px;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </form>
            </div>

            <!-- Application List -->
            <div class="glass-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
                    <h3 style="margin: 0; color: var(--accent-color);"><i class="fas fa-users"></i> Member Applications</h3>
                    <span
                        style="background: rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; color: #fff;">
                        <?php echo $totalRows; ?> Apps
                    </span>
                </div>

                <div class="modern-table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>APP#</th>
                                <th>Applicant</th>
                                <th>Form</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No applications found.</td>
                                </tr>
                            <?php else: ?>
                                <?php $rowIndex = 0; ?>
                                <?php foreach ($applications as $app): ?>
                                    <?php
                                    // เก่าสุด = #1, ใหม่สุด = #totalRows
                                    // หน้าแรก row 0: #13, row 1: #12... (newest on top)
                                    $displayNum = $totalRows - $offset - $rowIndex;
                                    $rowIndex++;
                                    ?>
                                    <tr>
                                        <td>#<?php echo $displayNum; ?></td>
                                        <td>
                                            <?php
                                            $discord = $discordUsernames[$app['id']] ?? null;
                                            if ($discord):
                                                ?>
                                                <div class="user-info-cell" style="display: flex; align-items: center; gap: 10px;">
                                                    <?php if (!empty($discord['avatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($discord['avatar']); ?>"
                                                            style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid rgba(88, 101, 242, 0.3);">
                                                    <?php endif; ?>
                                                    <div>
                                                        <span
                                                            class="user-name"><?php echo htmlspecialchars($discord['display_name']); ?></span>
                                                        <?php if (!empty($discord['username'])): ?>
                                                            <span
                                                                class="user-discord">@<?php echo htmlspecialchars($discord['username']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="user-info-cell">
                                                    <span
                                                        class="user-name"><?php echo htmlspecialchars($app['personaname'] ?? 'Guest'); ?></span>
                                                    <span
                                                        class="user-discord">@<?php echo htmlspecialchars($app['username'] ?? ''); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column;">
                                                <span
                                                    style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($app['form_title']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <i class="far fa-clock me-1 text-muted"></i>
                                            <?php echo date('M j, Y H:i', strtotime($app['submitted_at'])); ?>
                                        </td>
                                        <td>
                                            <?php
                                            // Status Logic
                                            $statusClass = 'pending';
                                            $icon = 'fa-clock';
                                            if ($app['status'] == 'accepted') {
                                                $statusClass = 'accepted';
                                                $icon = 'fa-check';
                                            } elseif ($app['status'] == 'rejected') {
                                                $statusClass = 'rejected';
                                                $icon = 'fa-times';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                                <?php echo ucfirst($app['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap; width: 1%;">
                                            <div
                                                style="display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: flex-end !important; gap: 8px !important; white-space: nowrap !important;">
                                                <!-- Edit -->
                                                <a href="applications.php?view=edit_application&id=<?php echo $app['id']; ?>"
                                                    class="btn-action-square edit" title="Edit Application">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <!-- View -->
                                                <a href="applications.php?view=response_detail&id=<?php echo $app['id']; ?>"
                                                    class="btn-action-square view" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <!-- Accept -->
                                                <button onclick="updateStatus(<?php echo $app['id']; ?>, 'accepted')"
                                                    class="btn-action-square accept" title="Accept">
                                                    <i class="fas fa-check"></i>
                                                </button>

                                                <!-- Reject -->
                                                <button onclick="updateStatus(<?php echo $app['id']; ?>, 'rejected')"
                                                    class="btn-action-square reject" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>

                                                <?php if ($app['status'] === 'accepted'): ?>
                                                    <?php if (empty($app['promoted_user_id'])): ?>
                                                        <!-- Promote -->
                                                        <button onclick="promoteToMember(<?php echo $app['id']; ?>)"
                                                            class="btn-action-square" title="Promote to Member"
                                                            style="border-color: #c5a059; color: #c5a059;">
                                                            <i class="fas fa-medal"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- View Promoted Member -->
                                                        <a href="edit_member.php?id=<?php echo $app['promoted_user_id']; ?>"
                                                            class="btn-action-square" title="View Promoted Member"
                                                            style="border-color: #c5a059; background: rgba(197, 160, 89, 0.1); color: #c5a059;">
                                                            <i class="fas fa-user-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- Delete -->
                                                <button onclick="deleteResponse(<?php echo $app['id']; ?>)"
                                                    class="btn-action-square text-danger" title="Delete Application"
                                                    style="border-color: rgba(220, 53, 69, 0.3);">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container"
                        style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <?php
                        // Build base URL with existing params
                        $baseUrl = 'applications.php?view=applications';
                        if ($status)
                            $baseUrl .= '&status=' . urlencode($status);
                        if ($search)
                            $baseUrl .= '&search=' . urlencode($search);
                        ?>

                        <!-- Previous Button -->
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $baseUrl . '&page=' . ($page - 1); ?>" class="pagination-btn"
                                style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; text-decoration: none; transition: all 0.2s;">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled"
                                style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); cursor: not-allowed;">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        if ($startPage > 1): ?>
                            <a href="<?php echo $baseUrl . '&page=1'; ?>" class="pagination-btn"
                                style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; text-decoration: none; transition: all 0.2s;">1</a>
                            <?php if ($startPage > 2): ?>
                                <span style="color: rgba(255,255,255,0.5);">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-btn active"
                                    style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #5865f2, #7289da); border: none; color: #fff; font-weight: 600; box-shadow: 0 4px 15px rgba(88, 101, 242, 0.4);">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="<?php echo $baseUrl . '&page=' . $i; ?>" class="pagination-btn"
                                    style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; text-decoration: none; transition: all 0.2s;">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span style="color: rgba(255,255,255,0.5);">...</span>
                            <?php endif; ?>
                            <a href="<?php echo $baseUrl . '&page=' . $totalPages; ?>" class="pagination-btn"
                                style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; text-decoration: none; transition: all 0.2s;"><?php echo $totalPages; ?></a>
                        <?php endif; ?>

                        <!-- Next Button -->
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo $baseUrl . '&page=' . ($page + 1); ?>" class="pagination-btn"
                                style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; text-decoration: none; transition: all 0.2s;">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled"
                                style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.3); cursor: not-allowed;">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Info -->
                        <span style="margin-left: 15px; color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>


            <script>
                function updateStatus(id, status) {
                    let swalConfig = {
                        title: 'Update Status?',
                        text: `Mark this application as ${status}?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: status === 'accepted' ? '#28a745' : '#dc3545',
                        confirmButtonText: `Yes, ${status}`,
                        background: '#151515',
                        color: '#fff'
                    };

                    if (status === 'rejected') {
                        swalConfig.input = 'textarea';
                        swalConfig.inputLabel = 'Reason for Rejection';
                        swalConfig.inputPlaceholder = 'Type your reason here...';
                        swalConfig.inputAttributes = {
                            'aria-label': 'Reason for Rejection'
                        };
                        swalConfig.text = `Please provide a reason for rejecting this application:`;
                    }

                    Swal.fire(swalConfig).then(async (result) => {
                        if (result.isConfirmed) {
                            try {
                                let bodyData = `action=update_response_status&id=${id}&status=${status}`;
                                if (status === 'rejected' && result.value) {
                                    bodyData += `&reason=${encodeURIComponent(result.value)}`;
                                }

                                const res = await fetch('form_actions.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: bodyData
                                });
                                const data = await res.json();
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success', title: 'Updated!',
                                        showConfirmButton: false, timer: 1000,
                                        background: '#151515', color: '#fff'
                                    }).then(() => location.reload());
                                } else {
                                    Swal.fire('Error', data.message, 'error');
                                }
                            } catch (e) {
                                Swal.fire('Error', 'Failed to update status', 'error');
                            }
                        }
                    });
                }

                function promoteToMember(id) {
                    Swal.fire({
                        title: 'Promote to Member?',
                        text: 'This will create or update a user account based on the application data.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#c5a059',
                        confirmButtonText: 'Yes, Promote',
                        background: '#151515',
                        color: '#fff'
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            try {
                                const res = await fetch('form_actions.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `action=promote_to_member&response_id=${id}`
                                });
                                const data = await res.json();
                                if (data.success) {
                                    let htmlContent = `User has been successfully ${data.is_new ? 'created' : 'updated'}.<br><br>`;
                                    if (data.is_new) {
                                        htmlContent += `<b>Username:</b> ${data.username}<br><b>Password:</b> ${data.password}`;
                                    }
                                    
                                    Swal.fire({
                                        icon: 'success', 
                                        title: 'Promoted!',
                                        html: htmlContent,
                                        background: '#151515', 
                                        color: '#fff'
                                    }).then(() => location.reload());
                                } else {
                                    Swal.fire('Error', data.message || 'Unknown error occurred.', 'error');
                                }
                            } catch (e) {
                                Swal.fire('Error', 'Failed to promote member', 'error');
                            }
                        }
                    });
                }
            </script>

        <?php elseif ($view === 'response_detail'):
            $rId = $_GET['id'] ?? 0;
            $rStmt = $pdo->prepare("SELECT r.*, f.title FROM form_responses r JOIN forms f ON r.form_id = f.id WHERE r.id = ?");
            $rStmt->execute([$rId]);
            $response = $rStmt->fetch();

            if (!$response) {
                echo "Response not found";
                exit;
            }

            $aStmt = $pdo->prepare("
            SELECT a.answer_text, q.question_text, q.question_type 
            FROM form_answers a 
            JOIN form_questions q ON a.question_id = q.id 
            WHERE a.response_id = ?
            ORDER BY q.sort_order
        ");
            $aStmt->execute([$rId]);
            $answers = $aStmt->fetchAll();
            ?>
            <div class="glass-panel" style="max-width: 800px; margin: 0 auto;">
                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <a href="applications.php?view=responses" class="text-muted"><i class="fas fa-arrow-left"></i> Back to
                        Responses</a>
                    <span class="text-muted">#<?php echo $response['id']; ?> •
                        <?php echo date('M d, Y', strtotime($response['submitted_at'])); ?></span>
                </div>

                <h2 class="mb-4 text-white"><?php echo htmlspecialchars($response['title']); ?> <span class="text-muted"
                        style="font-size: 0.6em;">Submission</span></h2>

                <div class="response-content">
                    <?php foreach ($answers as $ans): ?>
                        <div class="mb-4 pb-3 border-bottom border-secondary"
                            style="border-color: rgba(255,255,255,0.1) !important;">
                            <h5 class="text-accent mb-2"
                                style="color: var(--accent); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php echo nl2br(htmlspecialchars($ans['question_text'])); ?>
                            </h5>
                            <div class="text-white p-3 rounded" style="background: rgba(0,0,0,0.2);">
                                <?php
                                if ($ans['question_type'] === 'checkbox') {
                                    $vals = json_decode($ans['answer_text'], true);
                                    if (is_array($vals)) {
                                        $escaped_vals = array_map('htmlspecialchars', $vals);
                                        echo implode(', ', $escaped_vals);
                                    } else {
                                        echo htmlspecialchars($ans['answer_text']);
                                    }
                                } elseif ($ans['question_type'] === 'discord_user') {
                                    // Handle Discord user picker - JSON contains {id, display_name, username, avatar}
                                    $discordData = @json_decode($ans['answer_text'], true);
                                    if ($discordData && isset($discordData['display_name'])) {
                                        $displayName = htmlspecialchars($discordData['display_name']);
                                        $username = htmlspecialchars($discordData['username'] ?? '');
                                        $avatar = htmlspecialchars($discordData['avatar'] ?? '');
                                        $userId = htmlspecialchars($discordData['id'] ?? '');

                                        echo '<div style="display: flex; align-items: center; gap: 12px;">';
                                        if ($avatar) {
                                            echo '<img src="' . $avatar . '" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid rgba(88, 101, 242, 0.3);">';
                                        }
                                        echo '<div>';
                                        echo '<div style="font-weight: 600; color: #fff;">' . $displayName . '</div>';
                                        if ($username) {
                                            echo '<div style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">@' . $username . '</div>';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                    } else {
                                        // Fallback to raw text if not valid JSON
                                        echo nl2br(htmlspecialchars($ans['answer_text']));
                                    }
                                } else {
                                    echo nl2br(htmlspecialchars($ans['answer_text']));
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!--  VIEW: EDIT APPLICATION -->
        <?php elseif ($view === 'edit_application'):
            $rId = $_GET['id'] ?? 0;
            $rStmt = $pdo->prepare("SELECT r.*, f.title, f.id as form_id FROM form_responses r JOIN forms f ON r.form_id = f.id WHERE r.id = ?");
            $rStmt->execute([$rId]);
            $response = $rStmt->fetch();

            if (!$response) {
                echo "<div class='glass-panel text-center py-5'><h3 class='text-danger'>Application not found</h3><a href='applications.php' class='btn mt-3'>Back to Applications</a></div>";
            } else {
                // Get questions for this form
                $qStmt = $pdo->prepare("SELECT * FROM form_questions WHERE form_id = ? ORDER BY sort_order ASC");
                $qStmt->execute([$response['form_id']]);
                $questions = $qStmt->fetchAll();

                // Get current answers
                $aStmt = $pdo->prepare("SELECT question_id, answer_text FROM form_answers WHERE response_id = ?");
                $aStmt->execute([$rId]);
                $answersRaw = $aStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                ?>
                <style>
                    .edit-app-container {
                        max-width: 800px;
                        margin: 0 auto;
                    }

                    .edit-header {
                        background: linear-gradient(135deg, rgba(33, 150, 243, 0.12) 0%, rgba(33, 150, 243, 0.03) 100%);
                        border: 1px solid rgba(33, 150, 243, 0.25);
                        border-radius: 10px;
                        padding: 16px 20px;
                        margin-bottom: 20px;
                    }

                    .question-card {
                        background: rgba(255, 255, 255, 0.02);
                        border: 1px solid rgba(255, 255, 255, 0.06);
                        border-radius: 8px;
                        padding: 14px 18px;
                        margin-bottom: 10px;
                        transition: all 0.15s ease;
                    }

                    .question-card:hover {
                        border-color: rgba(255, 255, 255, 0.12);
                    }

                    .question-card:focus-within {
                        border-color: rgba(33, 150, 243, 0.4);
                        background: rgba(33, 150, 243, 0.03);
                    }

                    .question-label {
                        font-weight: 500;
                        color: rgba(255, 255, 255, 0.85);
                        font-size: 0.9rem;
                        margin-bottom: 8px;
                        line-height: 1.4;
                    }

                    .question-label .text-danger {
                        font-weight: 400;
                    }

                    .section-divider {
                        background: linear-gradient(135deg, rgba(197, 160, 89, 0.15) 0%, rgba(197, 160, 89, 0.03) 100%);
                        border-left: 3px solid var(--accent);
                        border-radius: 0 8px 8px 0;
                        padding: 12px 16px;
                        margin: 24px 0 14px 0;
                    }

                    .section-divider h4 {
                        color: var(--accent);
                        margin: 0;
                        font-weight: 600;
                        font-size: 0.95rem;
                    }

                    .section-divider p {
                        font-size: 0.8rem;
                        margin-top: 6px;
                    }

                    .edit-input {
                        background: rgba(0, 0, 0, 0.3) !important;
                        border: 1px solid rgba(255, 255, 255, 0.1) !important;
                        border-radius: 6px !important;
                        color: #fff !important;
                        padding: 10px 12px !important;
                        font-size: 0.9rem !important;
                        transition: all 0.15s ease !important;
                        width: 100%;
                    }

                    .edit-input:focus {
                        border-color: #2196f3 !important;
                        box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.12) !important;
                        outline: none !important;
                    }

                    .edit-input::placeholder {
                        color: rgba(255, 255, 255, 0.35);
                        font-size: 0.85rem;
                    }

                    textarea.edit-input {
                        min-height: 80px;
                        resize: vertical;
                    }

                    select.edit-input {
                        cursor: pointer;
                        padding-right: 32px !important;
                    }

                    select.edit-input option {
                        background: #1a1a1a;
                        color: #fff;
                        padding: 8px;
                    }

                    .discord-user-display {
                        background: rgba(88, 101, 242, 0.1);
                        border: 1px solid rgba(88, 101, 242, 0.25);
                        border-radius: 6px;
                        padding: 10px 14px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }

                    .discord-user-display i {
                        color: #5865F2;
                    }

                    .discord-user-display small {
                        margin-left: auto;
                        font-size: 0.75rem;
                    }

                    .checkbox-container {
                        background: rgba(0, 0, 0, 0.2);
                        border-radius: 6px;
                        padding: 8px 12px;
                    }

                    .checkbox-item {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 6px 8px;
                        border-radius: 4px;
                        margin-bottom: 2px;
                        font-size: 0.9rem;
                        cursor: pointer;
                    }

                    .checkbox-item:hover {
                        background: rgba(255, 255, 255, 0.03);
                    }

                    .checkbox-item input[type="checkbox"] {
                        width: 16px;
                        height: 16px;
                        cursor: pointer;
                    }

                    .save-btn {
                        background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
                        border: none;
                        padding: 12px 28px;
                        font-weight: 600;
                        font-size: 0.95rem;
                        border-radius: 10px;
                        color: #fff;
                        transition: all 0.25s ease;
                        box-shadow: 0 4px 15px rgba(76, 175, 80, 0.25);
                    }

                    .save-btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
                        background: linear-gradient(135deg, #66BB6A 0%, #43A047 100%);
                    }

                    .save-btn:active {
                        transform: translateY(0);
                        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
                    }

                    .cancel-btn {
                        background: transparent;
                        border: 2px solid rgba(255, 255, 255, 0.2);
                        padding: 10px 28px;
                        border-radius: 10px;
                        color: rgba(255, 255, 255, 0.8);
                        font-weight: 600;
                        font-size: 0.95rem;
                        transition: all 0.2s ease;
                    }

                    .cancel-btn:hover {
                        border-color: rgba(255, 255, 255, 0.4);
                        color: #fff;
                        background: rgba(255, 255, 255, 0.05);
                    }

                    .btn-container {
                        display: flex;
                        gap: 16px;
                        align-items: stretch;
                    }

                    .btn-container .save-btn,
                    .btn-container .cancel-btn {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        height: 48px;
                        box-sizing: border-box;
                    }
                </style>
                <div class="edit-app-container">
                    <!-- Header -->
                    <div class="edit-header">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <a href="applications.php?view=applications" class="text-white" style="text-decoration: none;">
                                <i class="fas fa-arrow-left me-2"></i> Back to Applications
                            </a>
                            <span class="text-muted">#<?php echo $response['id']; ?> •
                                <?php echo date('M d, Y', strtotime($response['submitted_at'])); ?></span>
                        </div>
                        <h2 class="mb-0 text-white">
                            <i class="fas fa-edit me-2" style="color: #2196f3;"></i>
                            แก้ไขใบสมัคร
                            <span class="text-muted" style="font-size: 0.6em; display: block; margin-top: 8px;">
                                <?php echo htmlspecialchars($response['title']); ?>
                            </span>
                        </h2>
                    </div>

                    <form id="editApplicationForm">
                        <input type="hidden" name="response_id" value="<?php echo $rId; ?>">

                        <?php foreach ($questions as $q):
                            $currentAnswer = $answersRaw[$q['id']] ?? '';
                            $isSection = $q['question_type'] === 'section';
                            ?>
                            <?php if ($isSection): ?>
                                <div class="section-divider">
                                    <h4><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></h4>
                                    <?php if (!empty($q['description'])): ?>
                                        <p class="text-muted small mt-2 mb-0"><?php echo nl2br(htmlspecialchars($q['description'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="question-card">
                                    <label class="question-label">
                                        <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                                        <?php if ($q['is_required']): ?><span class="text-danger ms-1">*</span><?php endif; ?>
                                    </label>

                                    <?php if ($q['question_type'] === 'text'): ?>
                                        <input type="text" class="form-control edit-input" name="answers[<?php echo $q['id']; ?>]"
                                            value="<?php echo htmlspecialchars($currentAnswer); ?>" placeholder="กรอกคำตอบ...">

                                    <?php elseif ($q['question_type'] === 'textarea'): ?>
                                        <textarea class="form-control edit-input" name="answers[<?php echo $q['id']; ?>]" rows="5"
                                            placeholder="กรอกคำตอบ..."><?php echo htmlspecialchars($currentAnswer); ?></textarea>

                                    <?php elseif ($q['question_type'] === 'date'): ?>
                                        <input type="text" class="form-control edit-input" name="answers[<?php echo $q['id']; ?>]"
                                            value="<?php echo htmlspecialchars($currentAnswer); ?>" placeholder="วัน/เดือน/ปี">

                                    <?php elseif (in_array($q['question_type'], ['radio', 'select'])):
                                        $options = json_decode($q['options'], true) ?: [];
                                        ?>
                                        <select class="form-control edit-input" name="answers[<?php echo $q['id']; ?>]">
                                            <option value="">-- เลือกคำตอบ --</option>
                                            <?php foreach ($options as $opt): ?>
                                                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $currentAnswer === $opt ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($opt); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if (!empty($currentAnswer) && !in_array($currentAnswer, $options) && strpos($currentAnswer, 'Other:') === 0): ?>
                                                <option value="<?php echo htmlspecialchars($currentAnswer); ?>" selected>
                                                    <?php echo htmlspecialchars($currentAnswer); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>

                                    <?php elseif ($q['question_type'] === 'checkbox'):
                                        $options = json_decode($q['options'], true) ?: [];
                                        $selectedOptions = json_decode($currentAnswer, true) ?: [];
                                        ?>
                                        <div class="checkbox-container">
                                            <?php foreach ($options as $opt): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" name="answers[<?php echo $q['id']; ?>][]"
                                                        value="<?php echo htmlspecialchars($opt); ?>" <?php echo in_array($opt, $selectedOptions) ? 'checked' : ''; ?>>
                                                    <span class="text-white"><?php echo htmlspecialchars($opt); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php elseif ($q['question_type'] === 'discord_user'):
                                        $discordData = @json_decode($currentAnswer, true);
                                        $displayVal = $currentAnswer;
                                        if ($discordData && isset($discordData['display_name'])) {
                                            $displayVal = $discordData['display_name'];
                                            if (!empty($discordData['username'])) {
                                                $displayVal .= ' (@' . $discordData['username'] . ')';
                                            }
                                        }
                                        ?>
                                        <div class="discord-user-display">
                                            <i class="fab fa-discord"></i>
                                            <span class="text-white"><?php echo htmlspecialchars($displayVal); ?></span>
                                            <input type="hidden" name="answers[<?php echo $q['id']; ?>]"
                                                value="<?php echo htmlspecialchars($currentAnswer); ?>">
                                            <small class="text-muted"><i class="fas fa-lock me-1"></i> ไม่สามารถแก้ไขได้</small>
                                        </div>

                                    <?php else: ?>
                                        <input type="text" class="form-control edit-input" name="answers[<?php echo $q['id']; ?>]"
                                            value="<?php echo htmlspecialchars($currentAnswer); ?>" placeholder="กรอกคำตอบ...">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="mt-4 pt-4" style="border-top: 1px solid rgba(255,255,255,0.1);">
                            <div class="btn-container">
                                <button type="submit" class="btn save-btn" id="saveEditBtn">
                                    <i class="fas fa-save me-2"></i> บันทึกการแก้ไข
                                </button>
                                <a href="applications.php?view=response_detail&id=<?php echo $rId; ?>" class="btn cancel-btn">
                                    <i class="fas fa-times me-2"></i> ยกเลิก
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <script>
                    document.getElementById('editApplicationForm').addEventListener('submit', async function (e) {
                        e.preventDefault();
                        const btn = document.getElementById('saveEditBtn');
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...';
                        btn.disabled = true;

                        try {
                            const formData = new FormData(this);
                            formData.append('action', 'update_application_answers');

                            const res = await fetch('form_actions.php', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await res.json();

                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'บันทึกสำเร็จ!',
                                    text: 'ข้อมูลถูกอัปเดตเรียบร้อยแล้ว',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    background: '#151515',
                                    color: '#fff'
                                }).then(() => {
                                    window.location.href = 'applications.php?view=response_detail&id=<?php echo $rId; ?>';
                                });
                            } else {
                                throw new Error(data.message || 'Unknown error');
                            }
                        } catch (err) {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: err.message,
                                background: '#151515',
                                color: '#fff'
                            });
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    });
                </script>
            <?php } ?>

            <!--  VIEW: CUSTOM FORMS LIST -->
        <?php elseif ($view === 'forms'): ?>
            <div class="filter-bar justify-content-between mb-4">
                <h2 style="color: var(--accent); margin: 0; font-size: 1.5rem;"><i class="fas fa-poll-h me-2"></i> Custom
                    Forms
                </h2>
                <button id="createFormBtn" class="action-btn primary">
                    <i class="fas fa-plus me-2"></i> Create New Form
                </button>
            </div>

            <div class="media-grid">
                <?php
                $stmt = $pdo->query("SELECT * FROM forms ORDER BY created_at DESC");
                $forms = $stmt->fetchAll();

                if (count($forms) > 0) {
                    foreach ($forms as $form) {
                        $responseCountStmt = $pdo->prepare("SELECT COUNT(*) FROM form_responses WHERE form_id = ?");
                        $responseCountStmt->execute([$form['id']]);
                        $count = $responseCountStmt->fetchColumn();
                        ?>
                        <div class="form-list-card d-flex flex-column h-100 p-0 overflow-hidden">
                            <div class="card-body d-flex flex-column p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h3 class="card-title text-wrap mb-0"
                                        style="font-size: 1.25rem; font-weight: 600; color: #fff;">
                                        <?php echo htmlspecialchars($form['title']); ?>
                                    </h3>
                                    <span class="badge"
                                        style="background: rgba(197, 160, 89, 0.2); color: var(--accent); border: 1px solid rgba(197, 160, 89, 0.3);"><?php echo $count; ?>
                                        Responses</span>
                                </div>

                                <p class="text-muted small mb-4 flex-grow-1"
                                    style="line-height: 1.5; opacity: 0.8; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo htmlspecialchars($form['description']); ?>
                                </p>

                                <div class="card-meta mt-auto pt-3 border-top border-light"
                                    style="border-color: rgba(255,255,255,0.05) !important;">
                                    <div class="d-flex align-items-center text-muted small">
                                        <i class="far fa-clock me-2"></i>
                                        <?php echo date('M j, Y', strtotime($form['created_at'])); ?>
                                    </div>
                                </div>

                                <div class="card-actions mt-3 d-flex gap-2">
                                    <a href="applications.php?view=form_builder&id=<?php echo $form['id']; ?>"
                                        class="action-btn btn-sm flex-fill text-center" title="Edit"><i
                                            class="fas fa-edit me-1"></i>
                                        Edit</a>

                                    <!-- Set Main App Button -->
                                    <?php if ($form['is_main_application']): ?>
                                        <button class="action-btn btn-sm text-success" disabled title="Main Application"
                                            style="border-color: rgba(76, 175, 80, 0.3); opacity: 1;">
                                            <i class="fas fa-star"></i> Main
                                        </button>
                                    <?php else: ?>
                                        <button onclick="setMainForm(<?php echo $form['id']; ?>)" class="action-btn btn-sm text-muted"
                                            title="Set as Main Application" style="border-color: rgba(255,255,255,0.1);">
                                            <i class="far fa-star"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Test Webhook Button -->
                                    <button onclick="testFormWebhook(<?php echo $form['id']; ?>, this)"
                                        class="action-btn btn-sm text-warning" title="Test Webhook"
                                        style="border-color: rgba(255, 193, 7, 0.3);">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <a href="../view_form.php?id=<?php echo $form['id']; ?>" target="_blank"
                                        class="action-btn btn-sm" title="View Public Link"><i
                                            class="fas fa-external-link-alt"></i></a>
                                    <button onclick="deleteForm(<?php echo $form['id']; ?>)" class="action-btn btn-sm text-danger"
                                        style="border-color: rgba(255, 77, 77, 0.3);"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="col-12 text-center text-muted py-5"><h4>No forms found. Create one to get started!</h4></div>';
                }
                ?>
            </div>

            <!-- Create/Delete Scripts -->
            <script>
                document.getElementById('createFormBtn').addEventListener('click', async () => {
                    const btn = document.getElementById('createFormBtn');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating...';
                    btn.disabled = true;
                    try {
                        const res = await fetch('form_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=create_form' });
                        const data = await res.json();
                        if (data.success) {
                            window.location.href = `applications.php?view=form_builder&id=${data.id}`;
                        } else {
                            Swal.fire('Error', 'Failed to create form', 'error');
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Error creating form', 'error');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                });


                function deleteForm(id) {
                    Swal.fire({
                        title: 'Delete Form?', text: "This will delete all responses.", icon: 'warning',
                        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!', background: '#151515', color: '#fff'
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            await fetch('form_actions.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=delete_form&id=${id}` });
                            location.reload();
                        }
                    })
                }

                async function testFormWebhook(id, btnElement) {
                    const btn = btnElement || event.currentTarget;
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    btn.disabled = true;

                    try {
                        const res = await fetch('form_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=simulate_application&form_id=${id}`
                        });
                        const data = await res.json();

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Simulation Sent!',
                                text: 'Check all configured Discord channels (Staff, Public, Welcome) for simulated messages.',
                                background: '#151515', color: '#fff'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: data.message || 'Unknown error',
                                background: '#151515', color: '#fff'
                            });
                        }
                    } catch (e) {
                        console.error(e);
                        Swal.fire('Error', 'Connection failed', 'error');
                    } finally {
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }
                }


                async function setMainForm(id) {
                    const btn = event.currentTarget;
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    btn.disabled = true;

                    try {
                        const res = await fetch('form_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=set_main_form&id=${id}`
                        });
                        const data = await res.json();
                        if (data.success) {
                            // Reload to update UI (stars)
                            location.reload();
                        } else {
                            Swal.fire('Error', 'Failed: ' + (data.message || 'Unknown error'), 'error');
                            btn.innerHTML = originalHtml;
                            btn.disabled = false;
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Connection failed', 'error');
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }
                }


            </script>


            <!-- VIEW: FORM BUILDER -->
        <?php elseif ($view === 'form_builder'):
            $form_id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
            $stmt->execute([$form_id]);
            $form = $stmt->fetch();
            if (!$form) {
                echo "Form not found";
                exit;
            }

            $qStmt = $pdo->prepare("SELECT * FROM form_questions WHERE form_id = ? ORDER BY sort_order ASC");
            $qStmt->execute([$form_id]);
            $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
            $form['questions'] = json_encode($questions);
            ?>

            <div class="builder-layout">
                <div class="builder-canvas">
                    <div class="banner-upload-container mb-4" style="position: relative;">
                        <input type="file" id="formBannerInput" class="form-control" accept="image/*"
                            style="display: none;">

                        <label for="formBannerInput" class="banner-upload-label" style="display: flex; flex-direction: column; align-items: center; justify-content: center;
                                       padding: 30px; border: 2px dashed rgba(255,255,255,0.1); border-radius: 12px;
                                       cursor: pointer; transition: all 0.3s ease; background: rgba(0,0,0,0.2);">

                            <div id="bannerPlaceholder" class="text-center"
                                style="<?php echo !empty($form['banner_image']) ? 'display:none;' : ''; ?>">
                                <i class="fas fa-image fa-2x mb-2 text-muted"></i>
                                <p class="mb-0 text-muted small">Click to upload banner image</p>
                            </div>

                            <div id="bannerPreviewContainer"
                                style="<?php echo !empty($form['banner_image']) ? 'display:block; width:100%;' : 'display:none; width:100%;'; ?>">
                                <img id="bannerPreview"
                                    src="<?php echo !empty($form['banner_image']) ? htmlspecialchars($form['banner_image']) : ''; ?>"
                                    alt="Banner Preview"
                                    style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
                                <p class="text-center text-muted small mt-2 mb-0">Click to change</p>
                            </div>
                        </label>

                    </div>

                    <div class="form-header-card">
                        <input type="text" id="formTitleInput" class="form-title-input" placeholder="Form Title"
                            value="<?php echo htmlspecialchars($form['title'] ?? ''); ?>">

                        <textarea id="formDescInput" class="form-control mb-4"
                            placeholder="Form Description (can use multiple lines)" rows="3"
                            style="resize: none; overflow-y: hidden; min-height: 40px; background:transparent; border:none; border-bottom:1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.7); width:100%; transition: 0.2s;"
                            oninput="this.style.height = 'auto'; this.style.height = (this.scrollHeight) + 'px';"><?php echo htmlspecialchars($form['description'] ?? ''); ?></textarea>
                    </div>
                    <div id="questionsContainer"></div>
                    <div id="emptyState" class="text-center py-5 text-muted" style="display:none;">
                        <div class="p-5" style="border: 2px dashed rgba(255,255,255,0.1); border-radius: 16px;">
                            <i class="fas fa-clipboard-list fa-3x mb-3" style="opacity:0.3;"></i>
                            <p class="mb-0">Select a tool from the toolbox to start building your form.</p>
                        </div>
                    </div>
                </div>

                <div class="toolbox-panel">
                    <h3 class="toolbox-title"><i class="fas fa-tools me-2"></i> Toolbox</h3>

                    <div class="tool-card" onclick="addQuestion('text')">
                        <div class="tool-icon"><i class="fas fa-align-left"></i></div>
                        <div class="tool-info"><span class="tool-name">Short Text</span></div>
                    </div>
                    <div class="tool-card" onclick="addQuestion('textarea')">
                        <div class="tool-icon"><i class="fas fa-align-justify"></i></div>
                        <div class="tool-info"><span class="tool-name">Long Text</span></div>
                    </div>
                    <div class="tool-card" onclick="addQuestion('radio')">
                        <div class="tool-icon"><i class="fas fa-list-ul"></i></div>
                        <div class="tool-info"><span class="tool-name">Multiple Choice</span></div>
                    </div>
                    <div class="tool-card" onclick="addQuestion('checkbox')">
                        <div class="tool-icon"><i class="fas fa-check-square"></i></div>
                        <div class="tool-info"><span class="tool-name">Checkboxes</span></div>
                    </div>
                    <div class="tool-card" onclick="addQuestion('select')">
                        <div class="tool-icon"><i class="fas fa-chevron-circle-down"></i></div>
                        <div class="tool-info"><span class="tool-name">Dropdown</span></div>
                    </div>
                    <div class="tool-card" onclick="addQuestion('date')">
                        <div class="tool-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="tool-info"><span class="tool-name">Date Picker</span></div>
                    </div>
                    <div class="tool-card" onclick="addQuestion('discord_user')">
                        <div class="tool-icon" style="color: #5865F2; background: rgba(88, 101, 242, 0.15);"><i
                                class="fab fa-discord"></i></div>
                        <div class="tool-info"><span class="tool-name">Discord User</span></div>
                    </div>

                    <div class="toolbox-title mt-4" style="margin-bottom: 1rem;">Layout</div>
                    <div class="tool-card" onclick="addQuestion('section')">
                        <div class="tool-icon" style="color: var(--accent); background: rgba(197,160,89,0.1);"><i
                                class="fas fa-columns"></i></div>
                        <div class="tool-info"><span class="tool-name">Page Section</span></div>
                    </div>

                    <div class="mt-4 pt-4 border-top border-light" style="border-color: rgba(255,255,255,0.1) !important;">

                        <?php if (($form['is_main_application'] ?? 0) == 1):
                            // Initialize Bot API to fetch channels
                            require_once ROOT_PATH . '/includes/bot_api.php';
                            $botApi = new BotAPI();
                            $channels = $botApi->getChannels();

                            // Helper function to render channel options
                            $renderChannelOptions = function ($currentValue) use ($channels) {
                                $html = '<option value="">-- Select Channel --</option>';

                                // Extract ID from Webhook URL if applicable (Legacy Data Support)
                                $compareValue = $currentValue;
                                if (strpos((string) $currentValue, 'http') !== false && preg_match('/\/webhooks\/(\d+)/', $currentValue, $matches)) {
                                    $compareValue = $matches[1];
                                }

                                foreach ($channels as $c) {
                                    $selected = ((string) $compareValue === (string) $c['id']) ? 'selected' : '';
                                    if ($c['type'] === 'text' || $c['type'] == 0) { // Text channels only
                                        $html .= '<option value="' . $c['id'] . '" ' . $selected . '># ' . htmlspecialchars($c['name']) . '</option>';
                                    }
                                }
                                return $html;
                            };
                            ?>
                            <!-- Settings / Discord (Main Form Only) -->
                            <div class="mb-3">
                                <label class="text-muted small mb-2 text-uppercase fw-bold" style="font-size: 0.75rem;"><i
                                        class="fab fa-discord me-1"></i> Staff Channel (New Application)</label>
                                <select id="formWebhookStaff" class="form-control small"
                                    style="background: rgba(0,0,0,0.3) !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; padding: 0.5rem; font-size: 0.85rem;">
                                    <?php echo $renderChannelOptions($form['webhook_url_staff'] ?? ''); ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted small mb-2 text-uppercase fw-bold" style="font-size: 0.75rem;"><i
                                        class="fab fa-discord me-1"></i> Welcome Channel (Accepted)</label>
                                <select id="formWebhookWelcome" class="form-control small"
                                    style="background: rgba(0,0,0,0.3) !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; padding: 0.5rem; font-size: 0.85rem;">
                                    <?php echo $renderChannelOptions($form['webhook_url_welcome'] ?? ''); ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="text-muted small mb-2 text-uppercase fw-bold" style="font-size: 0.75rem;"><i
                                        class="fab fa-discord me-1"></i> Public Channel (Pending Alert)</label>
                                <select id="formWebhookPublic" class="form-control small"
                                    style="background: rgba(0,0,0,0.3) !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; padding: 0.5rem; font-size: 0.85rem;">
                                    <?php echo $renderChannelOptions($form['webhook_url_public'] ?? ''); ?>
                                </select>
                            </div>

                            <?php
                            // Fetch roles for the Give Role selector
                            $roles = $botApi->getRoles();
                            $currentRoleId = $form['give_role_id'] ?? '';
                            ?>
                            <div class="mb-3">
                                <label class="text-muted small mb-2 text-uppercase fw-bold" style="font-size: 0.75rem;"><i
                                        class="fas fa-user-tag me-1" style="color: #43b581;"></i> Give Role (On Submit)</label>
                                <select id="formGiveRoleId" class="form-control small"
                                    style="background: rgba(0,0,0,0.3) !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; padding: 0.5rem; font-size: 0.85rem;">
                                    <option value="">-- No Role --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role['id']); ?>" <?php echo ($currentRoleId === $role['id']) ? 'selected' : ''; ?>
                                            style="color: <?php echo htmlspecialchars($role['color']); ?>;">
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" style="font-size: 0.7rem; display: block; margin-top: 4px;">
                                    <i class="fas fa-info-circle me-1"></i> Role จะถูกให้อัตโนมัติทันทีหลังส่งใบสมัคร
                                </small>
                            </div>
                        <?php else: ?>
                            <!-- Not Main Form - Show Info Message -->
                            <div class="alert alert-info mb-3"
                                style="background: rgba(33, 150, 243, 0.1); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 12px; padding: 1rem; color: #64b5f6;">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Webhook Settings</strong> are only available for the Main Form.
                                <a href="applications.php?view=forms" class="text-warning">Set a form as Main</a> to configure
                                webhooks.
                            </div>
                        <?php endif; ?>




                        <!-- Action Buttons - Modern Design -->
                        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05);">
                            <!-- Save Button -->
                            <button class="action-btn primary w-100" id="saveFormBtn" style="padding: 1rem 1.5rem; 
                                       font-weight: 600; 
                                       font-size: 1rem;
                                       border-radius: 12px; 
                                       background: linear-gradient(135deg, #c5a059 0%, #b8934d 100%);
                                       border: none;
                                       box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
                                       transition: all 0.3s ease;
                                       margin-bottom: 1rem;">
                                <i class="fas fa-save me-2"></i> Save Form
                            </button>

                            <!-- Done Button -->
                            <a href="applications.php?view=forms"
                                class="action-btn w-100 text-center d-block text-decoration-none" style="padding: 1rem 1.5rem; 
                                      font-weight: 600;
                                      font-size: 1rem;
                                      border-radius: 12px; 
                                      background: rgba(255,255,255,0.03); 
                                      border: 1px solid rgba(255,255,255,0.1);
                                      color: rgba(255,255,255,0.7);
                                      transition: all 0.3s ease;">
                                <i class="fas fa-check me-2"></i> Done
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                let questions = <?php echo $form['questions'] ?: '[]'; ?>;
                if (questions.length === 0) document.getElementById('emptyState').style.display = 'block';
                window.formId = <?php echo $form_id; ?>;
            </script>

            <!-- Question Template -->
            <template id="questionTemplate">
                <div class="glass-card question-item animate-slide-in" data-id="" draggable="true">
                    <div class="question-header">
                        <i class="fas fa-grip-vertical drag-handle"></i>
                        <textarea class="question-text-input" placeholder="Question" rows="1"
                            style="resize:none; overflow:hidden; min-height:38px;"></textarea>
                        <select class="question-type-select">
                            <option value="text">Short Answer</option>
                            <option value="textarea">Paragraph</option>
                            <option value="radio">Multiple Choice</option>
                            <option value="checkbox">Checkboxes</option>
                            <option value="select">Dropdown</option>
                            <option value="date">Date</option>
                            <option value="discord_user">Discord User</option>
                            <option value="section">Page Section</option>
                        </select>
                    </div>
                    <textarea class="form-control section-desc-input" placeholder="Section Description (Optional)"
                        style="display:none; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; width:100%; margin-bottom:1rem; padding: 10px; border-radius: 8px;"
                        rows="2"></textarea>
                    <div class="option-area mt-3" style="display:none;">
                        <div class="option-list"></div>
                        <div class="add-option-btn"><i class="fas fa-plus-circle"></i> Add Option</div>
                    </div>
                    <!-- Discord User Role Selector -->
                    <div class="discord-role-area mt-3" style="display:none;">
                        <div class="discord-role-header"
                            style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <i class="fab fa-discord" style="color: #5865F2; font-size: 1.2rem;"></i>
                            <span style="color: var(--text-main); font-weight: 500;">Discord User Filter Settings</span>
                        </div>
                        <div class="discord-role-info"
                            style="background: rgba(88, 101, 242, 0.1); border: 1px solid rgba(88, 101, 242, 0.2); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                            <small style="color: var(--text-muted);">
                                <i class="fas fa-info-circle" style="color: #5865F2;"></i>
                                ค่าเริ่มต้น: แสดงเฉพาะ users ที่ไม่มี role (สมาชิกใหม่)<br>
                                เลือก roles เพิ่มเติมด้านล่างเพื่อแสดง users ที่มี roles เหล่านั้นด้วย
                            </small>
                        </div>
                        <div class="discord-role-list"
                            style="max-height: 250px; overflow-y: auto; border-radius: 8px; background: rgba(0,0,0,0.3); padding: 10px;">
                            <div class="discord-roles-loading"
                                style="text-align: center; padding: 20px; color: var(--text-muted);">
                                <i class="fas fa-spinner fa-spin"></i> Loading roles...
                            </div>
                        </div>
                    </div>
                    <div class="question-footer">
                        <label class="switch"><input type="checkbox" class="required-check"> Required</label>
                        <div class="vr mx-2 bg-secondary"></div>
                        <button class="icon-btn delete"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </template>
            <script src="/assets/js/form_builder.js?v=<?php echo time(); ?>"></script>


            <!-- VIEW: SUMMARY (Google Forms Style) -->
        <?php elseif ($view === 'summary'):
            // Get all forms for selector
            $formsStmt = $pdo->query("SELECT id, title FROM forms ORDER BY created_at DESC");
            $allForms = $formsStmt->fetchAll();

            // Get selected form (default to first or from URL)
            $selectedFormId = $_GET['form_id'] ?? ($allForms[0]['id'] ?? 0);

            if ($selectedFormId) {
                // Get form details
                $formStmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
                $formStmt->execute([$selectedFormId]);
                $selectedForm = $formStmt->fetch();

                // Get questions (only choice-based questions for graphs)
                $qStmt = $pdo->prepare("SELECT * FROM form_questions WHERE form_id = ? AND question_type IN ('radio', 'select', 'checkbox') ORDER BY sort_order ASC");
                $qStmt->execute([$selectedFormId]);
                $questions = $qStmt->fetchAll();

                // Get response stats
                $statsStmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        MAX(submitted_at) as last_response,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
                    FROM form_responses WHERE form_id = ?
                ");
                $statsStmt->execute([$selectedFormId]);
                $formStats = $statsStmt->fetch();

                // Get daily responses for timeline (last 30 days)
                $timelineStmt = $pdo->prepare("
                    SELECT DATE(submitted_at) as date, COUNT(*) as count 
                    FROM form_responses 
                    WHERE form_id = ? AND submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(submitted_at) 
                    ORDER BY date ASC
                ");
                $timelineStmt->execute([$selectedFormId]);
                $timelineData = $timelineStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            }
            ?>
            <style>
                .summary-container {
                    max-width: 1400px;
                    margin: 0 auto;
                }

                .form-selector-card {
                    background: linear-gradient(135deg, rgba(88, 101, 242, 0.1) 0%, rgba(88, 101, 242, 0.02) 100%);
                    border: 1px solid rgba(88, 101, 242, 0.2);
                    border-radius: 16px;
                    padding: 1.5rem;
                    margin-bottom: 2rem;
                }

                .form-selector-card select {
                    background: rgba(0, 0, 0, 0.4);
                    border: 1px solid rgba(255, 255, 255, 0.15);
                    color: #fff;
                    padding: 12px 20px;
                    border-radius: 10px;
                    font-size: 1rem;
                    min-width: 300px;
                    cursor: pointer;
                }

                .form-selector-card select:focus {
                    border-color: #5865F2;
                    outline: none;
                    box-shadow: 0 0 0 3px rgba(88, 101, 242, 0.2);
                }

                .overview-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 1rem;
                    margin-bottom: 2rem;
                }

                .overview-card {
                    background: linear-gradient(145deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0.01) 100%);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.08);
                    border-radius: 16px;
                    padding: 1.25rem;
                    text-align: center;
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }

                .overview-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                    background: linear-gradient(90deg, var(--card-accent, #5865F2), transparent);
                }

                .overview-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
                }

                .overview-card.total {
                    --card-accent: #5865F2;
                }

                .overview-card.pending {
                    --card-accent: #F59E0B;
                }

                .overview-card.accepted {
                    --card-accent: #10B981;
                }

                .overview-card.rejected {
                    --card-accent: #EF4444;
                }

                .overview-card.date {
                    --card-accent: #C5A059;
                }

                .overview-card .card-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 0.75rem;
                    font-size: 1.1rem;
                }

                .overview-card.total .card-icon {
                    background: rgba(88, 101, 242, 0.15);
                    color: #5865F2;
                }

                .overview-card.pending .card-icon {
                    background: rgba(245, 158, 11, 0.15);
                    color: #F59E0B;
                }

                .overview-card.accepted .card-icon {
                    background: rgba(16, 185, 129, 0.15);
                    color: #10B981;
                }

                .overview-card.rejected .card-icon {
                    background: rgba(239, 68, 68, 0.15);
                    color: #EF4444;
                }

                .overview-card.date .card-icon {
                    background: rgba(197, 160, 89, 0.15);
                    color: #C5A059;
                }

                .overview-number {
                    font-size: 2rem;
                    font-weight: 700;
                    color: #fff;
                    margin-bottom: 0.25rem;
                    line-height: 1.2;
                }

                .overview-card.pending .overview-number {
                    color: #F59E0B;
                }

                .overview-card.accepted .overview-number {
                    color: #10B981;
                }

                .overview-card.rejected .overview-number {
                    color: #EF4444;
                }

                .overview-label {
                    color: rgba(255, 255, 255, 0.5);
                    font-size: 0.75rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    font-weight: 500;
                }

                .question-chart-card {
                    background: linear-gradient(145deg, rgba(255, 255, 255, 0.03) 0%, rgba(255, 255, 255, 0.01) 100%);
                    border: 1px solid rgba(255, 255, 255, 0.06);
                    border-radius: 16px;
                    padding: 1.5rem;
                    margin-bottom: 1.5rem;
                }

                .question-chart-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 1.5rem;
                    padding-bottom: 1rem;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
                }

                .question-title {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #fff;
                    margin: 0;
                    flex: 1;
                }

                .response-count-badge {
                    background: rgba(197, 160, 89, 0.15);
                    color: var(--accent);
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    font-weight: 600;
                }

                .chart-container {
                    position: relative;
                    height: 280px;
                }

                .chart-doughnut-container {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 2rem;
                    align-items: center;
                }

                .chart-legend-custom {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                }

                .legend-item {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 10px 14px;
                    background: rgba(255, 255, 255, 0.02);
                    border-radius: 10px;
                    transition: all 0.2s ease;
                }

                .legend-item:hover {
                    background: rgba(255, 255, 255, 0.05);
                }

                .legend-color {
                    width: 16px;
                    height: 16px;
                    border-radius: 4px;
                    flex-shrink: 0;
                }

                .legend-label {
                    flex: 1;
                    color: rgba(255, 255, 255, 0.85);
                    font-size: 0.9rem;
                }

                .legend-value {
                    font-weight: 600;
                    color: #fff;
                }

                .legend-percent {
                    color: rgba(255, 255, 255, 0.5);
                    font-size: 0.8rem;
                    margin-left: 6px;
                }

                .text-responses-list {
                    max-height: 300px;
                    overflow-y: auto;
                }

                .text-response-item {
                    background: rgba(0, 0, 0, 0.2);
                    border-radius: 10px;
                    padding: 12px 16px;
                    margin-bottom: 10px;
                    border-left: 3px solid rgba(197, 160, 89, 0.4);
                }

                .text-response-item:hover {
                    border-left-color: var(--accent);
                    background: rgba(0, 0, 0, 0.3);
                }

                .timeline-card {
                    background: linear-gradient(145deg, rgba(88, 101, 242, 0.05) 0%, rgba(88, 101, 242, 0.01) 100%);
                    border: 1px solid rgba(88, 101, 242, 0.15);
                    border-radius: 16px;
                    padding: 1.5rem;
                    margin-bottom: 2rem;
                }

                .no-data-message {
                    text-align: center;
                    padding: 3rem;
                    color: rgba(255, 255, 255, 0.5);
                }

                .no-data-message i {
                    font-size: 3rem;
                    margin-bottom: 1rem;
                    opacity: 0.3;
                }

                @media (max-width: 768px) {
                    .chart-doughnut-container {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

            <div class="summary-container">
                <!-- Form Selector -->
                <div class="form-selector-card">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div>
                            <i class="fas fa-chart-pie" style="font-size: 1.5rem; color: #5865F2;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="color: #fff; margin: 0 0 5px 0; font-size: 1.3rem;">Response Summary</h3>
                            <p style="color: rgba(255,255,255,0.5); margin: 0; font-size: 0.9rem;">
                                เลือกฟอร์มเพื่อดูสรุปผลลัพธ์</p>
                        </div>
                        <select id="formSelector"
                            onchange="window.location.href='applications.php?view=summary&form_id='+this.value">
                            <?php foreach ($allForms as $f): ?>
                                <option value="<?php echo $f['id']; ?>" <?php echo $f['id'] == $selectedFormId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($f['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if (!empty($selectedForm)): ?>

                    <!-- Overview Cards -->
                    <div class="overview-grid">
                        <div class="overview-card total">
                            <div class="card-icon"><i class="fas fa-file-alt"></i></div>
                            <div class="overview-number"><?php echo $formStats['total'] ?? 0; ?></div>
                            <div class="overview-label">Total Responses</div>
                        </div>
                        <div class="overview-card pending">
                            <div class="card-icon"><i class="fas fa-clock"></i></div>
                            <div class="overview-number"><?php echo $formStats['pending'] ?? 0; ?></div>
                            <div class="overview-label">Pending</div>
                        </div>
                        <div class="overview-card accepted">
                            <div class="card-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="overview-number"><?php echo $formStats['accepted'] ?? 0; ?></div>
                            <div class="overview-label">Accepted</div>
                        </div>
                        <div class="overview-card rejected">
                            <div class="card-icon"><i class="fas fa-times-circle"></i></div>
                            <div class="overview-number"><?php echo $formStats['rejected'] ?? 0; ?></div>
                            <div class="overview-label">Rejected</div>
                        </div>
                        <div class="overview-card date">
                            <div class="card-icon"><i class="fas fa-calendar"></i></div>
                            <div class="overview-number" style="font-size: 1.1rem;">
                                <?php echo $formStats['last_response'] ? date('M j, Y', strtotime($formStats['last_response'])) : '-'; ?>
                            </div>
                            <div class="overview-label">Last Response</div>
                        </div>
                    </div>

                    <!-- Response Timeline -->
                    <?php if (!empty($timelineData)): ?>
                        <div class="timeline-card">
                            <div class="question-chart-header">
                                <h4 class="question-title"><i class="fas fa-chart-line me-2" style="color: #5865F2;"></i>Response
                                    Timeline (Last 30 Days)</h4>
                            </div>
                            <div class="chart-container" style="height: 200px;">
                                <canvas id="timelineChart"></canvas>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                var ctx = document.getElementById('timelineChart');
                                if (ctx) {
                                    new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                            labels: <?php echo json_encode(array_keys($timelineData)); ?>,
                                            datasets: [{
                                                label: 'Responses',
                                                data: <?php echo json_encode(array_values($timelineData)); ?>,
                                                borderColor: '#5865F2',
                                                backgroundColor: 'rgba(88, 101, 242, 0.1)',
                                                fill: true,
                                                tension: 0.4,
                                                pointRadius: 4,
                                                pointBackgroundColor: '#5865F2'
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: { legend: { display: false } },
                                            scales: {
                                                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.5)' } },
                                                x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.5)', maxTicksLimit: 10 } }
                                            }
                                        }
                                    });
                                }
                            });
                        </script>
                    <?php endif; ?>

                    <!-- Questions Analysis -->
                    <h3 style="color: var(--accent); margin: 2rem 0 1.5rem 0; font-size: 1.2rem;">
                        <i class="fas fa-poll me-2"></i>Question Analysis
                    </h3>

                    <?php
                    $chartColors = ['#5865F2', '#C5A059', '#4CAF50', '#FF6B6B', '#9B59B6', '#3498DB', '#E67E22', '#1ABC9C', '#E91E63', '#00BCD4'];
                    $chartIndex = 0;

                    foreach ($questions as $q):
                        $aStmt = $pdo->prepare("SELECT a.answer_text FROM form_answers a JOIN form_responses r ON a.response_id = r.id WHERE a.question_id = ?");
                        $aStmt->execute([$q['id']]);
                        $answers = $aStmt->fetchAll(PDO::FETCH_COLUMN);
                        $chartId = "chart_q_" . $q['id'];
                        $totalAnswers = count($answers);
                        ?>
                        <div class="question-chart-card">
                            <div class="question-chart-header">
                                <h4 class="question-title"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></h4>
                                <span class="response-count-badge"><?php echo $totalAnswers; ?> responses</span>
                            </div>

                            <?php if ($totalAnswers == 0): ?>
                                <div class="no-data-message">
                                    <i class="fas fa-inbox"></i>
                                    <p>No responses yet</p>
                                </div>

                            <?php elseif (in_array($q['question_type'], ['radio', 'select'])):
                                // Count answers
                                $counts = [];
                                foreach ($answers as $ans) {
                                    $counts[$ans] = ($counts[$ans] ?? 0) + 1;
                                }
                                $labels = array_keys($counts);
                                $data = array_values($counts);
                                ?>
                                <div class="chart-doughnut-container">
                                    <div class="chart-container" style="max-width: 280px; margin: 0 auto;">
                                        <canvas id="<?php echo $chartId; ?>"></canvas>
                                    </div>
                                    <div class="chart-legend-custom">
                                        <?php foreach ($counts as $label => $count):
                                            $percent = round(($count / $totalAnswers) * 100, 1);
                                            $colorIndex = array_search($label, array_keys($counts)) % count($chartColors);
                                            ?>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background: <?php echo $chartColors[$colorIndex]; ?>;">
                                                </div>
                                                <span class="legend-label"><?php echo htmlspecialchars($label); ?></span>
                                                <span class="legend-value"><?php echo $count; ?><span
                                                        class="legend-percent">(<?php echo $percent; ?>%)</span></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        var ctx = document.getElementById('<?php echo $chartId; ?>');
                                        if (ctx) {
                                            new Chart(ctx, {
                                                type: 'doughnut',
                                                data: {
                                                    labels: <?php echo json_encode($labels); ?>,
                                                    datasets: [{
                                                        data: <?php echo json_encode($data); ?>,
                                                        backgroundColor: <?php echo json_encode(array_slice($chartColors, 0, count($labels))); ?>,
                                                        borderWidth: 0,
                                                        hoverOffset: 10
                                                    }]
                                                },
                                                options: {
                                                    responsive: true,
                                                    maintainAspectRatio: true,
                                                    cutout: '65%',
                                                    plugins: {
                                                        legend: { display: false },
                                                        tooltip: {
                                                            backgroundColor: 'rgba(0,0,0,0.8)',
                                                            padding: 12,
                                                            titleFont: { size: 14 },
                                                            bodyFont: { size: 13 },
                                                            callbacks: {
                                                                label: function (ctx) {
                                                                    let total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                                                    let pct = ((ctx.raw / total) * 100).toFixed(1);
                                                                    return ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            });
                                        }
                                    });
                                </script>

                            <?php elseif ($q['question_type'] === 'checkbox'):
                                // Count each checkbox option
                                $counts = [];
                                foreach ($answers as $ans) {
                                    $arr = json_decode($ans, true);
                                    if (is_array($arr)) {
                                        foreach ($arr as $val) {
                                            $counts[$val] = ($counts[$val] ?? 0) + 1;
                                        }
                                    }
                                }
                                arsort($counts); // Sort by count descending
                                $labels = array_keys($counts);
                                $data = array_values($counts);
                                ?>
                                <div class="chart-container">
                                    <canvas id="<?php echo $chartId; ?>"></canvas>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        var ctx = document.getElementById('<?php echo $chartId; ?>');
                                        if (ctx) {
                                            new Chart(ctx, {
                                                type: 'bar',
                                                data: {
                                                    labels: <?php echo json_encode($labels); ?>,
                                                    datasets: [{
                                                        label: 'Responses',
                                                        data: <?php echo json_encode($data); ?>,
                                                        backgroundColor: <?php echo json_encode(array_slice($chartColors, 0, count($labels))); ?>,
                                                        borderRadius: 8,
                                                        borderSkipped: false
                                                    }]
                                                },
                                                options: {
                                                    indexAxis: 'y',
                                                    responsive: true,
                                                    maintainAspectRatio: false,
                                                    plugins: {
                                                        legend: { display: false },
                                                        tooltip: { backgroundColor: 'rgba(0,0,0,0.8)', padding: 12 }
                                                    },
                                                    scales: {
                                                        x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.6)' } },
                                                        y: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.8)' } }
                                                    }
                                                }
                                            });
                                        }
                                    });
                                </script>

                            <?php elseif ($q['question_type'] === 'discord_user'): ?>
                                <div class="text-responses-list">
                                    <?php
                                    $displayCount = 0;
                                    foreach (array_reverse($answers) as $ans):
                                        if ($displayCount >= 10)
                                            break;
                                        $discordData = @json_decode($ans, true);
                                        if ($discordData && isset($discordData['display_name'])):
                                            $displayCount++;
                                            ?>
                                            <div class="text-response-item"
                                                style="display: flex; align-items: center; gap: 12px; border-left-color: rgba(88, 101, 242, 0.5);">
                                                <?php if (!empty($discordData['avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($discordData['avatar']); ?>"
                                                        style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid rgba(88, 101, 242, 0.3);">
                                                <?php else: ?>
                                                    <div
                                                        style="width: 32px; height: 32px; border-radius: 50%; background: rgba(88, 101, 242, 0.2); display: flex; align-items: center; justify-content: center;">
                                                        <i class="fab fa-discord" style="color: #5865F2;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 600; color: #fff;">
                                                        <?php echo htmlspecialchars($discordData['display_name']); ?>
                                                    </div>
                                                    <?php if (!empty($discordData['username'])): ?>
                                                        <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                                                            @<?php echo htmlspecialchars($discordData['username']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php
                                        endif;
                                    endforeach;
                                    if ($displayCount == 0): ?>
                                        <div class="no-data-message">
                                            <i class="fab fa-discord"></i>
                                            <p>No Discord users linked</p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($totalAnswers > 10): ?>
                                        <div style="text-align: center; padding: 10px; color: rgba(255,255,255,0.4); font-size: 0.85rem;">
                                            + <?php echo ($totalAnswers - 10); ?> more responses
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <?php else: // Text, Textarea, Date ?>
                                <div class="text-responses-list">
                                    <?php
                                    $displayCount = 0;
                                    foreach (array_reverse($answers) as $ans):
                                        if ($displayCount >= 10)
                                            break;
                                        if (empty(trim($ans)))
                                            continue;
                                        $displayCount++;
                                        ?>
                                        <div class="text-response-item">
                                            <?php echo nl2br(htmlspecialchars($ans)); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($totalAnswers > 10): ?>
                                        <div style="text-align: center; padding: 10px; color: rgba(255,255,255,0.4); font-size: 0.85rem;">
                                            + <?php echo ($totalAnswers - 10); ?> more responses
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php $chartIndex++; endforeach; ?>

                <?php else: ?>
                    <div class="no-data-message" style="padding: 5rem;">
                        <i class="fas fa-poll" style="font-size: 4rem;"></i>
                        <h3 style="color: rgba(255,255,255,0.6); margin-top: 1.5rem;">No Forms Available</h3>
                        <p>Create a form first to see summary analytics</p>
                        <a href="applications.php?view=forms" class="btn mt-3" style="background: var(--accent); color: #000;">
                            <i class="fas fa-plus me-2"></i>Create Form
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- VIEW: RESPONSES (Legacy) -->
        <?php elseif ($view === 'form_responses'):
            $form_id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM form_questions WHERE form_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$form_id]);
            $questions = $stmt->fetchAll();
            ?>
            <div class="mb-3 flex-between">
                <a href="applications.php?view=forms" class="text-muted"><i class="fas fa-arrow-left"></i> Back to Forms</a>
                <h2 class="text-white">Responses Analysis</h2>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <div class="flex-column gap-3">
                <?php foreach ($questions as $q):
                    $aStmt = $pdo->prepare("SELECT a.answer_text FROM form_answers a JOIN form_responses r ON a.response_id = r.id WHERE a.question_id = ?");
                    $aStmt->execute([$q['id']]);
                    $answers = $aStmt->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <div class="glass-card">
                        <h4 class="mb-3"><?php echo htmlspecialchars($q['question_text']); ?></h4>
                        <p class="text-muted small"><?php echo count($answers); ?> responses</p>
                        <?php if (in_array($q['question_type'], ['radio', 'select', 'checkbox'])):
                            $counts = [];
                            foreach ($answers as $ans) {
                                if ($q['question_type'] === 'checkbox') {
                                    $arr = json_decode($ans, true);
                                    if (is_array($arr))
                                        foreach ($arr as $val)
                                            $counts[$val] = ($counts[$val] ?? 0) + 1;
                                } else {
                                    $counts[$ans] = ($counts[$ans] ?? 0) + 1;
                                }
                            }
                            $labels = array_keys($counts);
                            $data = array_values($counts);
                            $chartId = "chart_" . $q['id'];
                            ?>
                            <div style="max-height: 300px;"><canvas id="<?php echo $chartId; ?>"></canvas></div>
                            <script>
                                new Chart(document.getElementById('<?php echo $chartId; ?>'), {
                                    type: 'bar',
                                    data: {
                                        labels: <?php echo json_encode($labels); ?>,
                                        datasets: [{ label: 'Votes', data: <?php echo json_encode($data); ?>, backgroundColor: '#c5a059', borderColor: '#c5a059', borderWidth: 1 }]
                                    },
                                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.1)' } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
                                });
                            </script>
                        <?php else: ?>
                            <div class="bg-dark rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                <?php if (empty($answers)):
                                    echo '<span class="text-muted">No responses.</span>';
                                else: ?>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach (array_reverse(array_slice($answers, -20)) as $ans): ?>
                                            <li class="border-bottom border-secondary py-2 text-light"><?php echo htmlspecialchars($ans); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
    <?php include ROOT_PATH . '/includes/footer.php'; ?>

    <script>
        async function deleteResponse(id) {
            Swal.fire({
                title: 'Delete Response?',
                text: "This cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!',
                background: '#151515', color: '#fff'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const res = await fetch('form_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=delete_response&id=${id}`
                        });
                        const data = await res.json();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success', title: 'Deleted',
                                showConfirmButton: false, timer: 1000,
                                background: '#151515', color: '#fff'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Connection failed', 'error');
                    }
                }
            });
        }
    </script>

