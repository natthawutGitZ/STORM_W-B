<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    redirect('../login');
}

if (!isset($_GET['id'])) {
    redirect('dashboard');
}

$user_id = $_GET['id'];
$message = '';
$error = '';

// Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('dashboard');
}

// Fetch All Tags
$stmt = $pdo->query("SELECT * FROM tags");
$all_tags = $stmt->fetchAll();

// Fetch All Ranks
$stmt = $pdo->query("SELECT * FROM ranks ORDER BY order_index ASC, name ASC");
$all_ranks = $stmt->fetchAll();

// Fetch User's Current Tags
$stmt = $pdo->prepare("SELECT tag_id FROM user_tags WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member'])) {
    $rank = sanitize($_POST['rank']);
    $status = sanitize($_POST['status']);
    $selected_tags = isset($_POST['tags']) ? $_POST['tags'] : [];

    try {
        // Update User Details
        $stmt = $pdo->prepare("UPDATE users SET `rank` = ?, status = ? WHERE id = ?");
        $stmt->execute([$rank, $status, $user_id]);

        // Update Tags
        // First, remove all existing tags for this user
        $stmt = $pdo->prepare("DELETE FROM user_tags WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Then insert selected tags
        if (!empty($selected_tags)) {
            $insert_stmt = $pdo->prepare("INSERT INTO user_tags (user_id, tag_id) VALUES (?, ?)");
            foreach ($selected_tags as $tag_id) {
                $insert_stmt->execute([$user_id, $tag_id]);
            }
        }

        $message = "Member updated successfully!";

        // Log admin action
        require_once ROOT_PATH . '/includes/admin_log.php';
        logAdminAction($pdo, 'edit_member', 'member', $user_id, [
            'member_name' => $user['personaname'],
            'rank' => $rank,
            'status' => $status
        ]);

        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT tag_id FROM user_tags WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    } catch (PDOException $e) {
        $error = "Error updating member: " . $e->getMessage();
    }
}

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<div class="admin-content">
    <div class="admin-header-actions" style="margin-bottom: 2rem;">
        <h2>Edit Member: <?php echo htmlspecialchars($user['personaname']); ?></h2>
        <a href="dashboard.php" class="btn" style="background: transparent; border: 1px solid #666; color: #ccc;">&larr;
            Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div
            style="background-color: rgba(76, 175, 80, 0.2); color: #4caf50; padding: 1rem; margin-bottom: 1rem; border: 1px solid #4caf50; border-radius: 4px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div
            style="background-color: rgba(220, 53, 69, 0.2); color: #dc3545; padding: 1rem; margin-bottom: 1rem; border: 1px solid #dc3545; border-radius: 4px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="profile-edit"
        style="max-width: 600px; margin: 0 auto; background: rgba(0,0,0,0.5); padding: 2rem; border-radius: 8px; border: 1px solid #333;">
        <form action="" method="POST">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar"
                    style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-color);">
                <p style="margin-top: 0.5rem; color: #aaa;"><?php echo htmlspecialchars($user['personaname']); ?></p>
            </div>

            <div class="form-group">
                <label for="rank">Rank:</label>
                <select id="rank" name="rank" class="form-control"
                    style="width: 100%; padding: 0.5rem; background: #222; border: 1px solid #444; color: #fff; margin-bottom: 1rem;">
                    <option value="">-- No Rank --</option>
                    <?php foreach ($all_ranks as $r): ?>
                        <option value="<?php echo htmlspecialchars($r['name']); ?>" <?php echo $user['rank'] === $r['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" class="form-control"
                    style="width: 100%; padding: 0.5rem; background: #222; border: 1px solid #444; color: #fff; margin-bottom: 1rem;">
                    <option value="Active" <?php echo $user['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Retired" <?php echo $user['status'] === 'Retired' ? 'selected' : ''; ?>>Retired
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Tags:</label>
                <div
                    style="display: flex; flex-wrap: wrap; gap: 0.5rem; background: #222; padding: 1rem; border: 1px solid #444; border-radius: 4px;">
                    <?php foreach ($all_tags as $tag): ?>
                        <label
                            style="display: flex; align-items: center; gap: 0.5rem; background: #333; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                            <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" <?php echo in_array($tag['id'], $user_tags) ? 'checked' : ''; ?>>
                            <span
                                style="color: <?php echo $tag['color']; ?>;"><?php echo htmlspecialchars($tag['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" name="update_member" class="btn" style="width: 100%; margin-top: 1rem;">Save
                Changes</button>
        </form>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>

