<?php
// Determine path helper for assets (avatars)
// If in Admin Dashboard (admin/dashboard.php), paths are ../
// If in Profile (profile.php), paths are ./
$inAdminDir = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$assetPrefix = $inAdminDir ? '../' : '';
?>

<div> <!-- Member List -->
    <div class="members-section">
        <div class="glass-panel">
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
                <h3 style="margin: 0; color: var(--accent-color);"><i class="fas fa-users-cog"></i> Member
                    Management</h3>
                <span
                    style="background: rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; color: #fff;">
                    <?php echo count($members); ?> Members
                </span>
            </div>

            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Rank</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img src="<?php echo get_avatar($member['avatar']); ?>" alt="">
                                        <div class="user-info">
                                            <span
                                                class="name"><?php echo htmlspecialchars($member['personaname']); ?></span>
                                            <span
                                                class="username"><?php echo htmlspecialchars($member['username']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="color: var(--accent-color); font-weight: 500; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($member['rank'] ?? 'Recruit'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'active';
                                    if ($member['status'] === 'Inactive')
                                        $statusClass = 'inactive';
                                    if ($member['status'] === 'LOA')
                                        $statusClass = 'loa';
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php if ($statusClass == 'active')
                                            echo '<i class="fas fa-circle" style="font-size: 6px;"></i>'; ?>
                                        <?php echo htmlspecialchars($member['status']); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                                        <button type="button" class="btn" onclick="openEditModal(this)"
                                            data-id="<?php echo $member['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($member['username']); ?>"
                                            data-personaname="<?php echo htmlspecialchars($member['personaname']); ?>"
                                            data-rank="<?php echo htmlspecialchars($member['rank'] ?? 'Recruit'); ?>"
                                            data-position="<?php echo htmlspecialchars($member['position'] ?? ''); ?>"
                                            data-status="<?php echo htmlspecialchars($member['status']); ?>"
                                            data-role="<?php echo htmlspecialchars($member['role']); ?>"
                                            data-avatar="<?php echo get_avatar($member['avatar']); ?>"
                                            data-tags="<?php echo htmlspecialchars($member['tag_ids'] ?? ''); ?>"
                                            style="padding: 6px 10px; font-size: 0.8rem; min-width: auto;">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <form action="" method="POST" id="deleteForm_<?php echo $member['id']; ?>"
                                            style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                            <input type="hidden" name="delete_user" value="1">
                                            <button type="button" class="btn"
                                                onclick="confirmDelete(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['personaname'], ENT_QUOTES); ?>')"
                                                style="padding: 6px 10px; font-size: 0.8rem; min-width: auto; border-color: #f44336; color: #f44336;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<!-- Note: We ensure unique ID if this file is included multiple times (unlikely but safe) -->
<div id="editMemberModal" class="modal-overlay">
    <div class="glass-panel modal-content">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit Member</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>

        <div style="text-align: center; margin-bottom: 25px;">
            <img id="editModalAvatar" src="" alt="Avatar"
                style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-color);">
        </div>

        <form action="" method="POST" class="modern-form">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="editUsername" required>
            </div>

            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="personaname" id="editPersonaName" required>
            </div>

            <div class="form-group">
                <label>Rank</label>
                <select name="rank" id="editRank">
                    <?php
                    $ranks = [
                        'General of the Army (GA)',
                        'General (GEN)',
                        'Lieutenant General (LTG)',
                        'Major General (MG)',
                        'Brigadier General (BG)',
                        'Colonel (COL)',
                        'Lieutenant Colonel (LTC)',
                        'Major (MAJ)',
                        'Captain (CPT)',
                        'First Lieutenant (1LT)',
                        'Second Lieutenant (2LT)',
                        'Chief Warrant Officer 5 (CW5)',
                        'Chief Warrant Officer 4 (CW4)',
                        'Chief Warrant Officer 3 (CW3)',
                        'Chief Warrant Officer 2 (CW2)',
                        'Warrant Officer 1 (WO1)',
                        'Sergeant Major of the Army (SMA)',
                        'Command Sergeant Major (CSM)',
                        'Sergeant Major (SGM)',
                        'First Sergeant (1SG)',
                        'Master Sergeant (MSG)',
                        'Sergeant First Class (SFC)',
                        'Staff Sergeant (SSG)',
                        'Sergeant (SGT)',
                        'Corporal (CPL)',
                        'Specialist (SPC)',
                        'Private First Class (PFC)',
                        'Private (PV2)',
                        'Private (PV1)',
                        'Recruit'
                    ];
                    foreach ($ranks as $r) {
                        echo "<option value='$r'>$r</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Position</label>
                <input type="text" name="position" id="editPosition">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editStatus">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="LOA">LOA</option>
                </select>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" id="editRole">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tags</label>
                <select name="tags[]" id="editTags" multiple style="height: 100px;">
                    <!-- Tags need to be populated from DB in parent script, assuming $tags available -->
                    <?php
                    // Need to fetch tags if not available, OR assume parent script does it.
                    // Ideally parent script fetches $tags.
                    if (isset($tags)) {
                        foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag['id']; ?>">
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </option>
                        <?php endforeach;
                    } ?>
                </select>
                <small style="color: #666; display: block; margin-top: 5px;">Hold Ctrl/Cmd to select multiple</small>
            </div>

            <button type="submit" name="update_user" class="btn" style="width: 100%; margin-bottom: 15px;">
                Update Member
            </button>

            <button type="button" id="editResumeBtn" onclick="" class="btn"
                style="width: 100%; background: transparent; border-color: #fff; color: #fff;">
                <i class="fas fa-file-alt"></i> Edit Resume
            </button>
        </form>
    </div>
</div>

<style>
    /* Responsive Adjustments */
    @media (max-width: 900px) {
        .content-grid {
            grid-template-columns: 1fr !important;
        }

        div[style*="grid-template-columns: 1fr 2fr"] {
            grid-template-columns: 1fr !important;
        }
    }

    tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }
</style>

<script>
    function openEditModal(btn) {
        const modal = document.getElementById('editMemberModal');
        const id = btn.getAttribute('data-id');
        const username = btn.getAttribute('data-username');
        const personaname = btn.getAttribute('data-personaname');
        const rank = btn.getAttribute('data-rank');
        const position = btn.getAttribute('data-position');
        const status = btn.getAttribute('data-status');
        const role = btn.getAttribute('data-role');
        const avatar = btn.getAttribute('data-avatar');
        const tagIds = btn.getAttribute('data-tags') ? btn.getAttribute('data-tags').split(',') : [];

        // Populate fields
        document.getElementById('editUserId').value = id;
        document.getElementById('editUsername').value = username;
        document.getElementById('editPersonaName').value = personaname;
        document.getElementById('editRank').value = rank;
        document.getElementById('editPosition').value = position;
        document.getElementById('editStatus').value = status;
        document.getElementById('editRole').value = role;
        document.getElementById('editModalAvatar').src = avatar;

        // Populate Tags (Select multiple)
        const tagSelect = document.getElementById('editTags');
        if (tagSelect) {
            Array.from(tagSelect.options).forEach(option => {
                option.selected = tagIds.includes(option.value);
            });
        }

        // Set Resume Button Action
        const resumeBtn = document.getElementById('editResumeBtn');
        // Check function availability normally in admin/dashboard this works.
        // In profile.php we need to make sure openAdminResume or equivalent exists.
        // actually openAdminResume is part of resume_modal.php which is likely included.
        if (typeof openAdminResume === 'function') {
            resumeBtn.setAttribute('onclick', `openAdminResume(${id})`);
        } else if (typeof loadResumeData === 'function') {
            // Fallback for profile page if standard resume modal is used ??
            resumeBtn.setAttribute('onclick', `loadResumeData(${id})`);
        } else {
            resumeBtn.setAttribute('onclick', `alert('Resume function not found')`);
        }


        // Show Modal
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    }

    function closeEditModal() {
        const modal = document.getElementById('editMemberModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    // Close modal on outside click
    window.onclick = function (event) {
        const modal = document.getElementById('editMemberModal');
        if (event.target == modal) {
            closeEditModal();
        }
    }

    // Delete confirmation using SweetAlert2
    function confirmDelete(userId, userName) {
        Swal.fire({
            title: 'Delete User',
            html: `Are you sure you want to delete <strong>${userName}</strong>?<br><small>This action cannot be undone.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f44336',
            cancelButtonColor: '#666',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            background: 'linear-gradient(135deg, rgba(30, 30, 40, 0.95) 0%, rgba(20, 20, 30, 0.98) 100%)',
            color: '#fff',
            customClass: {
                popup: 'swal-custom-popup'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit the form
                document.getElementById('deleteForm_' + userId).submit();
            }
        });
    }
</script>