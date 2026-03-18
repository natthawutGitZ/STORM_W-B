<?php
// src/admin/applications.php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$pageTitle = "Applications Manager";
include ROOT_PATH . '/includes/admin_header.php';

// View Controller
$view = $_GET['view'] ?? 'applications'; // applications, forms, form_builder, form_responses
?>

<link rel="stylesheet" href="../assets/css/forms.css">
<link rel="stylesheet" href="../assets/css/admin_media.css">

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
            <a href="applications.php?view=forms"
                class="nav-link-custom <?php echo $view === 'forms' ? 'active' : ''; ?>">
                <i class="fas fa-poll-h"></i> Manage Forms
            </a>
        </div>

        <!-- VIEW: MEMBER APPLICATIONS (Repurposed for Form Responses) -->
        <?php if ($view === 'applications'):
            $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
            $status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

            // Stats from form_responses
            $stmt_stats = $pdo->query("SELECT status, COUNT(*) as count FROM form_responses GROUP BY status");
            $status_counts = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);
            $stats = [
                'total' => array_sum($status_counts),
                'pending' => $status_counts['pending'] ?? 0,
                'accepted' => $status_counts['accepted'] ?? 0,
                'rejected' => $status_counts['rejected'] ?? 0
            ];

            // Main List Query
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
            $sql .= " ORDER BY r.submitted_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $applications = $stmt->fetchAll();
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
                        <?php echo count($applications); ?> Apps
                    </span>
                </div>

                <div class="modern-table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
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
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td>#<?php echo $app['id']; ?></td>
                                        <td>
                                            <div class="user-info-cell">
                                                <span
                                                    class="user-name"><?php echo htmlspecialchars($app['personaname'] ?? 'Guest'); ?></span>
                                                <span
                                                    class="user-discord">@<?php echo htmlspecialchars($app['username'] ?? ''); ?></span>
                                            </div>
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
            </div>

            <script>
                function updateStatus(id, status) {
                    Swal.fire({
                        title: 'Update Status?',
                        text: `Mark this application as ${status}?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: status === 'accepted' ? '#28a745' : '#dc3545',
                        confirmButtonText: `Yes, ${status}`,
                        background: '#151515',
                        color: '#fff'
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            try {
                                const res = await fetch('form_actions.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `action=update_response_status&id=${id}&status=${status}`
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
                                    if (is_array($vals))
                                        echo implode(', ', $vals);
                                    else
                                        echo htmlspecialchars($ans['answer_text']);
                                } else {
                                    echo nl2br(htmlspecialchars($ans['answer_text']));
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

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
            <script src="../assets/js/form_builder.js?v=<?php echo time(); ?>"></script>


            <!-- VIEW: RESPONSES -->
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
                    $aStmt = $pdo->prepare("SELECT answer_text FROM form_answers WHERE question_id = ?");
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
