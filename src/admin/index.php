<?php include ROOT_PATH . '/includes/admin_header.php'; ?>

<?php
// Stats
$stmt = $pdo->query("SELECT COUNT(*) FROM applications");
$totalApps = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
$pendingApps = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'accepted'");
$acceptedApps = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'rejected'");
$rejectedApps = $stmt->fetchColumn();
?>

<h2>Dashboard</h2>

<div class="grid" style="margin-bottom: 3rem;">
    <div class="card" style="text-align: center;">
        <h3 style="font-size: 3rem; margin-bottom: 0.5rem;"><?php echo $totalApps; ?></h3>
        <p>Total Applications</p>
    </div>
    <div class="card" style="text-align: center; border-color: #d4af37;">
        <h3 style="font-size: 3rem; margin-bottom: 0.5rem; color: #d4af37;"><?php echo $pendingApps; ?></h3>
        <p>Pending</p>
    </div>
    <div class="card" style="text-align: center; border-color: #28a745;">
        <h3 style="font-size: 3rem; margin-bottom: 0.5rem; color: #28a745;"><?php echo $acceptedApps; ?></h3>
        <p>Accepted</p>
    </div>
    <div class="card" style="text-align: center; border-color: #dc3545;">
        <h3 style="font-size: 3rem; margin-bottom: 0.5rem; color: #dc3545;"><?php echo $rejectedApps; ?></h3>
        <p>Rejected</p>
    </div>
</div>

<h3>Recent Applications</h3>
<div class="card" style="padding: 0; overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Age</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC LIMIT 5");
            while ($row = $stmt->fetch()):
                ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['age']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                    <td><span
                            class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
                    </td>
                    <td><a href="view_application.php?id=<?php echo $row['id']; ?>" class="btn"
                            style="padding: 0.5rem 1rem; font-size: 0.8rem;">View</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</div> <!-- End Section -->
</body>

</html>
