<?php
require_once ROOT_PATH . '/admin/includes/admin_header.php';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $unit_name = $_POST['unit_name'] ?? '';

            if ($unit_name) {
                // 1. Insert Unit
                $stmt = $pdo->prepare("INSERT INTO resume_units (unit_name, image_path) VALUES (?, '')");
                $stmt->execute([$unit_name]);
                $unit_id = $pdo->lastInsertId();

                // 2. Handle Image Upload
                if (isset($_FILES['unit_image']) && $_FILES['unit_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_PATH . '/assets/images/units/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileInfo = pathinfo($_FILES['unit_image']['name']);
                    $ext = strtolower($fileInfo['extension']);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($ext, $allowed)) {
                        // Use PNG for output (supports transparency)
                        $newFileName = 'unit_' . $unit_id . '_' . time() . '.png';
                        $destPath = $uploadDir . $newFileName;

                        if (processUnitImage($_FILES['unit_image']['tmp_name'], $destPath)) {
                            // Update DB with new path
                            $stmt = $pdo->prepare("UPDATE resume_units SET image_path = ? WHERE id = ?");
                            $stmt->execute([$newFileName, $unit_id]);
                        }
                    }
                }

                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Created!',
                        text: 'New unit created successfully',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        iconColor: '#2ecc71',
                        willClose: () => {
                            const popup = Swal.getPopup();
                            popup.style.animation = 'glassFadeOut 0.5s forwards';
                        }
                    }).then(() => {
                        window.location.href = 'units.php';
                    });
                </script>";
            }
        } elseif ($action === 'update') {
            $unit_id = $_POST['unit_id'] ?? null;
            $unit_name = $_POST['unit_name'] ?? '';

            if ($unit_id && $unit_name) {
                // 1. Update Name
                $stmt = $pdo->prepare("UPDATE resume_units SET unit_name = ? WHERE id = ?");
                $stmt->execute([$unit_name, $unit_id]);

                // 2. Handle Image Upload
                if (isset($_FILES['unit_image']) && $_FILES['unit_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_PATH . '/assets/images/units/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileInfo = pathinfo($_FILES['unit_image']['name']);
                    $ext = strtolower($fileInfo['extension']);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($ext, $allowed)) {
                        // Use PNG for output
                        $newFileName = 'unit_' . $unit_id . '_' . time() . '.png';
                        $destPath = $uploadDir . $newFileName;

                        if (processUnitImage($_FILES['unit_image']['tmp_name'], $destPath)) {
                            // Update DB with new path
                            $stmt = $pdo->prepare("UPDATE resume_units SET image_path = ? WHERE id = ?");
                            $stmt->execute([$newFileName, $unit_id]);
                        }
                    }
                }

                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'Unit updated successfully',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        willClose: () => {
                            const popup = Swal.getPopup();
                            popup.style.animation = 'glassFadeOut 0.5s forwards';
                        }
                    }).then(() => {
                        window.location.href = 'units.php';
                    });
                </script>";
            }
        } elseif ($action === 'delete') {
            $unit_id = $_POST['unit_id'] ?? null;

            if ($unit_id) {
                // Get image path to delete file
                $stmt = $pdo->prepare("SELECT image_path FROM resume_units WHERE id = ?");
                $stmt->execute([$unit_id]);
                $unit = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($unit && $unit['image_path']) {
                    $imagePath = ROOT_PATH . '/assets/images/units/' . $unit['image_path'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                // Delete from DB
                $stmt = $pdo->prepare("DELETE FROM resume_units WHERE id = ?");
                $stmt->execute([$unit_id]);

                echo "<script>
                    Swal.fire({
                        iconHtml: '<i class=\"fas fa-trash-alt trash-bounce\" style=\"font-size: 4rem; color: #ff6b6b;\"></i>',
                        customClass: {
                            icon: 'no-border'
                        },
                        title: 'Deleted!',
                        text: 'Unit deleted successfully',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        willClose: () => {
                            const popup = Swal.getPopup();
                            popup.style.animation = 'glassFadeOut 0.5s forwards';
                        }
                    }).then(() => {
                        window.location.href = 'units.php';
                    });
                </script>";
            }
        }

    } catch (PDOException $e) {
        echo "<script>Swal.fire('Error', 'Database error: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch Units
$units = $pdo->query("SELECT * FROM resume_units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">

    <!-- Styles -->
    <style>
        .admin-card {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 25px;
            height: 100%;
        }

        .admin-card h3 {
            color: var(--accent-color);
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #aaa;
            font-size: 0.85rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            border-radius: 6px;
            transition: all 0.3s;
            font-family: var(--font-main);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.1);
            background: rgba(0, 0, 0, 0.5);
        }

        .item-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .list-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .list-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .unit-image-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--accent-color);
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.2s;
            padding: 8px;
            border-radius: 4px;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-content {
            background: #1a1a1a;
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            transform: translateY(20px);
            transition: transform 0.3s;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        /* SweetAlert Customization */
        div:where(.swal2-container) div:where(.swal2-popup) {
            background: rgba(20, 20, 20, 0.85) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff !important;
            border-radius: 16px;
        }

        div:where(.swal2-container) div:where(.swal2-title) {
            color: #c5a059 !important;
            /* accent-color */
        }

        div:where(.swal2-icon) {
            border-color: #c5a059 !important;
            color: #c5a059 !important;
        }

        div:where(.swal2-container) button:where(.swal2-styled).swal2-confirm {
            background-color: #ff6b6b !important;
            box-shadow: 0 0 15px rgba(255, 107, 107, 0.4);
        }

        div:where(.swal2-container) button:where(.swal2-styled).swal2-cancel {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #aaa !important;
        }

        /* Custom Animation Overrides */
        div:where(.swal2-popup) {
            animation: glassFadeIn 0.4s cubic-bezier(0.25, 1, 0.5, 1) !important;
        }

        div:where(.swal2-icon).swal2-success .swal2-success-line-tip,
        div:where(.swal2-icon).swal2-success .swal2-success-line-long {
            animation-duration: 0.3s !important;
        }

        div:where(.swal2-icon).swal2-success .swal2-success-ring {
            animation-duration: 0.3s !important;
        }

        /* Fix white background on success animation */
        div:where(.swal2-icon).swal2-success [class^='swal2-success-circular-line'] {
            background-color: transparent !important;
        }

        div:where(.swal2-icon).swal2-success .swal2-success-fix {
            background-color: transparent !important;
        }

        @keyframes glassFadeIn {
            0% {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes glassFadeOut {
            0% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }

            100% {
                opacity: 0;
                transform: scale(0.95) translateY(-10px);
            }
        }

        /* Trash Icon Animation */
        .trash-bounce {
            animation: trashBounce 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: inline-block;
        }

        @keyframes trashBounce {
            0% {
                transform: scale(0.5) rotate(0deg);
                opacity: 0;
            }

            50% {
                transform: scale(1.2) rotate(-10deg);
            }

            70% {
                transform: scale(0.9) rotate(10deg);
            }

            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        /* Remove border for custom icon */
        .swal2-icon.no-border {
            border: none !important;
        }
    </style>

    <!-- Page Header -->
    <div style="margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 style="color: var(--accent-color); margin: 0 0 10px 0; font-size: 1.8rem; letter-spacing: 1px;">
                <i class="fas fa-shield-alt"></i> Unit Management
            </h2>
            <p style="color: #aaa; margin: 0; font-size: 0.95rem;">Manage unit names and logos for resumes</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">

        <!-- Add Unit Section -->
        <div>
            <div class="admin-card">
                <h3><i class="fas fa-plus-circle"></i> Add New Unit</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">

                    <div class="form-group">
                        <label class="form-label">Unit Name</label>
                        <input type="text" name="unit_name" required class="form-input" placeholder="e.g., Alpha Squad">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Unit Logo</label>
                        <div style="position: relative;">
                            <input type="file" name="unit_image" accept="image/*" class="form-input"
                                style="padding-top: 10px;">
                            <div
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #aaa;">
                                <i class="fas fa-image"></i>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn"
                        style="width: 100%; padding: 12px; display: flex; justify-content: center; align-items: center; gap: 8px;">
                        <i class="fas fa-plus"></i> Create Unit
                    </button>
                </form>
            </div>
        </div>

        <!-- Existing Units Section -->
        <div>
            <div class="admin-card">
                <h3><i class="fas fa-th-list"></i> Existing Units <span
                        style="font-size: 0.8em; opacity: 0.6; margin-left: auto;"><?php echo count($units); ?></span>
                </h3>

                <?php if (empty($units)): ?>
                    <p style="color: #666; text-align: center; padding: 30px;">No units created yet.</p>
                <?php else: ?>
                    <div class="item-list">
                        <?php foreach ($units as $unit): ?>
                            <div class="list-item">
                                <!-- Unit Image -->
                                <div class="unit-image-preview">
                                    <img src="/assets/images/units/<?php echo htmlspecialchars($unit['image_path']); ?>"
                                        onerror="this.src='/assets/images/logo.png'" alt="Unit Logo"
                                        style="width: 100%; height: 100%; object-fit: contain;">
                                </div>

                                <!-- Unit Info -->
                                <div style="text-align: center; padding: 10px 0;">
                                    <h4 style="margin: 0 0 5px 0; color: #fff; font-size: 1.1rem;">
                                        <?php echo htmlspecialchars($unit['unit_name']); ?>
                                    </h4>
                                    <span style="font-size: 0.75rem; color: #666;">ID: <?php echo $unit['id']; ?></span>
                                </div>

                                <!-- Actions -->
                                <div
                                    style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; display: flex; justify-content: center; gap: 15px;">
                                    <button class="action-btn" style="color: #64b5f6;"
                                        onclick="editUnit(<?php echo $unit['id']; ?>, '<?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($unit['image_path']); ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="action-btn" style="color: #e57373;"
                                        onclick="confirmDelete(<?php echo $unit['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Edit Unit Modal -->
<div id="editUnitModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0; font-size: 1.5rem;">Edit Unit</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="unit_id" id="edit_unit_id">

            <div style="text-align: center; margin-bottom: 20px;">
                <div class="unit-image-preview" style="width: 100px; height: 100px; margin-bottom: 15px;">
                    <img id="edit_image_preview" src="" onerror="this.src='/assets/images/logo.png'"
                        style="width: 100%; height: 100%; object-fit: contain;">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Unit Name</label>
                <input type="text" name="unit_name" id="edit_unit_name" required class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Change Logo (Optional)</label>
                <input type="file" name="unit_image" accept="image/*" class="form-input">
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="button" onclick="closeEditUnit()" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #aaa;">Cancel</button>
                <button type="submit" class="btn" style="flex: 1;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="unit_id" id="deleteUnitId">
</form>

<script>
    function editUnit(id, name, imagePath) {
        document.getElementById('edit_unit_id').value = id;
        document.getElementById('edit_unit_name').value = name;
        document.getElementById('edit_image_preview').src = '/assets/images/units/' + imagePath;

        const modal = document.getElementById('editUnitModal');
        modal.classList.add('active');
    }

    function closeEditUnit() {
        document.getElementById('editUnitModal').classList.remove('active');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff6b6b',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteUnitId').value = id;
                document.getElementById('deleteForm').submit();
            }
        })
    }

    // Close modal on outside click
    window.onclick = function (event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
        }
    }
</script>