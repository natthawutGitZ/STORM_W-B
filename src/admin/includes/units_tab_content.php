<style>
.unit-admin-card {
    background: rgba(20, 20, 20, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 25px;
    height: 100%;
}
.unit-admin-card h3 {
    color: var(--accent-color);
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.unit-form-label {
    display: block;
    color: #aaa;
    font-size: 0.85rem;
    margin-bottom: 8px;
    font-weight: 500;
}
.unit-form-input {
    width: 100%;
    padding: 12px 15px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    border-radius: 6px;
    transition: all 0.3s;
    font-family: var(--font-main);
    box-sizing: border-box;
}
.unit-form-input:focus {
    outline: none;
    border-color: var(--accent-color);
    box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.1);
    background: rgba(0, 0, 0, 0.5);
}
.unit-item-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.unit-list-item {
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
.unit-list-item:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}
.unit-image-preview-tab {
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
.unit-action-btn {
    background: none;
    border: none;
    cursor: pointer;
    transition: color 0.2s;
    padding: 8px;
    border-radius: 4px;
    color: inherit;
}
.unit-action-btn:hover {
    background: rgba(255, 255, 255, 0.05);
}
/* Unit Edit Modal */
.unit-edit-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}
.unit-edit-overlay.active {
    display: flex;
    opacity: 1;
}
.unit-edit-modal-content {
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
.unit-edit-overlay.active .unit-edit-modal-content {
    transform: translateY(0);
}
</style>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">

    <!-- Add Unit Section -->
    <div>
        <div class="unit-admin-card">
            <h3><i class="fas fa-plus-circle"></i> Add New Unit</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="unit_action" value="create">

                <div style="margin-bottom: 20px;">
                    <label class="unit-form-label">Unit Name</label>
                    <input type="text" name="unit_name" required class="unit-form-input" placeholder="e.g., Alpha Squad">
                </div>

                <div style="margin-bottom: 20px;">
                    <label class="unit-form-label">Unit Logo</label>
                    <div style="position: relative;">
                        <input type="file" name="unit_image" accept="image/*" class="unit-form-input" style="padding-top: 10px;">
                        <div style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #aaa;">
                            <i class="fas fa-image"></i>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn" style="width: 100%; padding: 12px; display: flex; justify-content: center; align-items: center; gap: 8px;">
                    <i class="fas fa-plus"></i> Create Unit
                </button>
            </form>
        </div>
    </div>

    <!-- Existing Units Section -->
    <div>
        <div class="unit-admin-card">
            <h3><i class="fas fa-th-list"></i> Existing Units <span style="font-size: 0.8em; opacity: 0.6; margin-left: auto;"><?php echo count($units); ?></span></h3>

            <?php if (empty($units)): ?>
                <p style="color: #666; text-align: center; padding: 30px;">No units created yet.</p>
            <?php else: ?>
                <div class="unit-item-list">
                    <?php foreach ($units as $unit): ?>
                        <div class="unit-list-item">
                            <div class="unit-image-preview-tab">
                                <img src="../assets/images/units/<?php echo htmlspecialchars($unit['image_path']); ?>"
                                    onerror="this.src='../assets/images/logo.png'" alt="Unit Logo"
                                    style="width: 100%; height: 100%; object-fit: contain;">
                            </div>
                            <div style="text-align: center; padding: 10px 0;">
                                <h4 style="margin: 0 0 5px 0; color: #fff; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($unit['unit_name']); ?>
                                </h4>
                                <span style="font-size: 0.75rem; color: #666;">ID: <?php echo $unit['id']; ?></span>
                            </div>
                            <div style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; display: flex; justify-content: center; gap: 15px;">
                                <button class="unit-action-btn" style="color: #64b5f6;"
                                    onclick="editUnitTab(<?php echo $unit['id']; ?>, '<?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($unit['image_path']); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="unit-action-btn" style="color: #e57373;"
                                    onclick="confirmDeleteUnit(<?php echo $unit['id']; ?>)">
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

<!-- Edit Unit Modal -->
<div id="editUnitModalTab" class="unit-edit-overlay">
    <div class="unit-edit-modal-content">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0; font-size: 1.5rem;">Edit Unit</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="unit_action" value="update">
            <input type="hidden" name="unit_id" id="edit_unit_id_tab">

            <div style="text-align: center; margin-bottom: 20px;">
                <div class="unit-image-preview-tab" style="width: 100px; height: 100px; margin: 0 auto 15px;">
                    <img id="edit_image_preview_tab" src="" onerror="this.src='../assets/images/logo.png'"
                        style="width: 100%; height: 100%; object-fit: contain;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label class="unit-form-label">Unit Name</label>
                <input type="text" name="unit_name" id="edit_unit_name_tab" required class="unit-form-input">
            </div>

            <div style="margin-bottom: 20px;">
                <label class="unit-form-label">Change Logo (Optional)</label>
                <input type="file" name="unit_image" accept="image/*" class="unit-form-input">
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="button" onclick="closeEditUnitTab()" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #aaa;">Cancel</button>
                <button type="submit" class="btn" style="flex: 1;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteUnitFormTab" method="POST" style="display: none;">
    <input type="hidden" name="unit_action" value="delete">
    <input type="hidden" name="unit_id" id="deleteUnitIdTab">
</form>

<script>
    function editUnitTab(id, name, imagePath) {
        document.getElementById('edit_unit_id_tab').value = id;
        document.getElementById('edit_unit_name_tab').value = name;
        document.getElementById('edit_image_preview_tab').src = '../assets/images/units/' + imagePath;
        document.getElementById('editUnitModalTab').classList.add('active');
    }

    function closeEditUnitTab() {
        document.getElementById('editUnitModalTab').classList.remove('active');
    }

    function confirmDeleteUnit(id) {
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
                document.getElementById('deleteUnitIdTab').value = id;
                document.getElementById('deleteUnitFormTab').submit();
            }
        })
    }

    // Close modal on outside click
    document.getElementById('editUnitModalTab').addEventListener('click', function(event) {
        if (event.target === this) {
            closeEditUnitTab();
        }
    });
</script>
