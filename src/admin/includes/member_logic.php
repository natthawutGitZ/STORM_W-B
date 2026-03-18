<?php
// Handle Delete User
if (isset($_POST['delete_user'])) {
    $delete_id = $_POST['user_id'];
    // Prevent deleting self
    if ($delete_id == $_SESSION['user']['id']) {
        $error = "You cannot delete your own account.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            $message = "User deleted successfully.";

            // Log admin action
            if (function_exists('logAdminAction')) {
                logAdminAction($pdo, 'delete_member', 'member', $delete_id);
            }
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Handle Update User
if (isset($_POST['update_user'])) {
    $id = $_POST['user_id'];
    $username = sanitize($_POST['username']);
    $personaname = sanitize($_POST['personaname']);
    $rank = sanitize($_POST['rank']);
    $position = sanitize($_POST['position']);
    $status = sanitize($_POST['status']);
    $role = sanitize($_POST['role']);

    try {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, personaname = ?, `rank` = ?, position = ?, status = ?, `role` = ? WHERE id = ?");
        $stmt->execute([$username, $personaname, $rank, $position, $status, $role, $id]);

        // Update Positions
        $stmt = $pdo->prepare("DELETE FROM user_positions WHERE user_id = ?");
        $stmt->execute([$id]);

        if (isset($_POST['positions']) && is_array($_POST['positions'])) {
            $stmt = $pdo->prepare("INSERT INTO user_positions (user_id, position_id, date_assigned) VALUES (?, ?, CURDATE())");
            foreach ($_POST['positions'] as $position_id) {
                $stmt->execute([$id, $position_id]);
            }
        }

        $message = "User updated successfully.";

        // Log admin action
        if (function_exists('logAdminAction')) {
            logAdminAction($pdo, 'edit_member', 'member', $id, [
                'username' => $username,
                'rank' => $rank,
                'role' => $role
            ]);
        }
    } catch (PDOException $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Handle search and filter for Members
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';

// Pagination settings
$per_page = 5;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Build base WHERE clause for counting and fetching
$where_sql = "WHERE 1=1";
$params = [];

if ($search) {
    $where_sql .= " AND (u.username LIKE ? OR u.personaname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_sql .= " AND u.status = ?";
    $params[] = $status_filter;
}

if ($role_filter) {
    $where_sql .= " AND u.role = ?";
    $params[] = $role_filter;
}

// Count total members for pagination
$count_sql = "SELECT COUNT(DISTINCT u.id) as total FROM users u $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_members = $count_stmt->fetch()['total'];
$total_pages = ceil($total_members / $per_page);
$offset = ($current_page - 1) * $per_page;

// Build query with filters and pagination
$sql = "SELECT u.*, GROUP_CONCAT(up.position_id) as position_ids 
        FROM users u 
        LEFT JOIN user_positions up ON u.id = up.user_id 
        $where_sql
        GROUP BY u.id 
        ORDER BY u.id DESC
        LIMIT $per_page OFFSET $offset";

// Fetch filtered members
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Fetch all ranks
$stmt_ranks = $pdo->query("SELECT name, abbreviation FROM ranks ORDER BY COALESCE(order_index, 999) ASC");
$ranks_db = $stmt_ranks->fetchAll(PDO::FETCH_ASSOC);

$all_ranks = [];
$rank_abbrs = [];
foreach ($ranks_db as $r) {
    if ($r['name']) {
        $all_ranks[] = $r['name'];
        $rank_abbrs[$r['name']] = $r['abbreviation'];
    }
}
?>