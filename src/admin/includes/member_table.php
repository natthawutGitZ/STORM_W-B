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

                <div style="display: flex; align-items: center; gap: 15px;">


                    <!-- Member Count Badge -->
                    <span
                        style="background: rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; color: #fff;">
                        <?php echo isset($total_members) ? $total_members : count($members); ?> Members
                    </span>
                </div>
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
                                            <span class="name">
                                                <?php
                                                $mbrRank = $member['rank'] ?? '';
                                                $abbr = isset($rank_abbrs[$mbrRank]) ? $rank_abbrs[$mbrRank] . '. ' : '';
                                                echo htmlspecialchars($abbr . $member['personaname']);
                                                ?>
                                            </span>
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
                                            data-positions="<?php echo htmlspecialchars($member['position_ids'] ?? ''); ?>"
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

            <!-- Bottom Pagination Controls -->
            <?php if (isset($total_pages) && $total_pages > 1):
                // Ensure proper types for pagination
                $pg_current = isset($current_page) ? (int) $current_page : 1;
                $pg_total = (int) $total_pages;
                ?>
                <div class="bottom-pagination"
                    style="display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.05);">
                    <?php
                    // Build base URL for pagination
                    $base_params = ['tab' => 'members'];
                    if (!empty($_GET['search']))
                        $base_params['search'] = $_GET['search'];
                    if (!empty($_GET['status']))
                        $base_params['status'] = $_GET['status'];
                    if (!empty($_GET['role']))
                        $base_params['role'] = $_GET['role'];
                    ?>

                    <!-- Previous -->
                    <?php if ($pg_current > 1):
                        $prev_params = array_merge($base_params, ['page' => $pg_current - 1]);
                        ?>
                        <a href="?<?php echo http_build_query($prev_params); ?>" class="page-nav-btn" title="Previous">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $pg_total; $i++):
                        $page_params = array_merge($base_params, ['page' => $i]);
                        ?>
                        <a href="?<?php echo http_build_query($page_params); ?>"
                            class="page-num-btn <?php echo $i === $pg_current ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next -->
                    <?php if ($pg_current < $pg_total):
                        $next_params = array_merge($base_params, ['page' => $pg_current + 1]);
                        ?>
                        <a href="?<?php echo http_build_query($next_params); ?>" class="page-nav-btn" title="Next">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Header Pagination Styles */
    .page-nav-btn,
    .page-num-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        padding: 0 8px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 6px;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .page-nav-btn:hover,
    .page-num-btn:hover {
        background: rgba(197, 160, 89, 0.2);
        border-color: rgba(197, 160, 89, 0.5);
        color: var(--accent-color, #c5a059);
    }

    .page-num-btn.active {
        background: linear-gradient(135deg, var(--accent-color, #c5a059) 0%, #b8944d 100%);
        border-color: var(--accent-color, #c5a059);
        color: #0a0a0c;
        font-weight: 600;
    }

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

    .edit-member-modal {
        max-width: 620px;
        padding: 30px;
        background: rgba(40, 40, 45, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
    }

    .edit-member-modal select {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23c5a059' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 36px;
        cursor: pointer;
    }

    .edit-member-modal select:hover {
        border-color: rgba(197, 160, 89, 0.4);
    }

    .edit-member-modal select option {
        background: #1a1a1e;
        color: #f0f0f0;
        padding: 10px;
    }

    .edit-modal-avatar-section {
        text-align: center;
        margin-bottom: 24px;
    }

    .edit-modal-avatar-ring {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        border: 2px solid var(--accent-color);
        padding: 3px;
        margin: 0 auto;
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.3), rgba(197, 160, 89, 0.1));
        box-shadow: 0 0 20px rgba(197, 160, 89, 0.15);
    }

    .edit-modal-avatar-ring img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .edit-modal-display-name {
        display: block;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-color);
        margin-top: 10px;
        letter-spacing: 0.5px;
    }

    .edit-modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    .edit-modal-grid .form-group {
        margin-bottom: 0;
    }

    .edit-modal-grid .form-group label i {
        margin-right: 6px;
        color: var(--accent-color);
        font-size: 0.8rem;
    }

    .edit-tags-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        max-height: 140px;
        overflow-y: auto;
    }

    .edit-position-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: var(--text-muted);
        transition: all 0.25s ease;
        user-select: none;
    }

    .edit-position-pill:hover {
        background: rgba(197, 160, 89, 0.1);
        border-color: rgba(197, 160, 89, 0.3);
        color: var(--text-color);
    }

    .edit-position-pill.active {
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.25), rgba(197, 160, 89, 0.15));
        border-color: var(--accent-color);
        color: var(--accent-color);
        box-shadow: 0 0 12px rgba(197, 160, 89, 0.1);
    }

    .edit-position-pill input[type="checkbox"] {
        display: none;
    }

    .edit-position-pill-text {
        margin: 0;
    }

    .edit-modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .edit-modal-btn-primary {
        flex: 1;
        background: linear-gradient(135deg, var(--accent-color), #b8944d);
        color: #0a0a0c;
        padding: 12px 20px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .edit-modal-btn-primary:hover {
        box-shadow: 0 4px 20px rgba(197, 160, 89, 0.3);
        transform: translateY(-1px);
    }

    .edit-modal-btn-secondary {
        flex: 1;
        background: transparent;
        color: var(--text-muted);
        padding: 12px 20px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .edit-modal-btn-secondary:hover {
        border-color: var(--accent-color);
        color: var(--accent-color);
        background: rgba(197, 160, 89, 0.05);
    }

    @media (max-width: 640px) {
        .edit-modal-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .edit-member-modal {
            padding: 20px;
            margin: 10px;
        }
    }
</style>

<!-- Edit Member Modal -->
<div id="editMemberModal" class="modal-overlay">
    <div class="glass-panel modal-content edit-member-modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit Member</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>

        <div class="edit-modal-avatar-section">
            <div class="edit-modal-avatar-ring">
                <img id="editModalAvatar" src="" alt="Avatar">
            </div>
            <span class="edit-modal-display-name" id="editModalDisplayName"></span>
        </div>

        <form action="" method="POST" class="modern-form">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="edit-modal-grid">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" id="editUsername" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-id-badge"></i> Display Name</label>
                    <input type="text" name="personaname" id="editPersonaName" required>
                </div>
            </div>

            <div class="edit-modal-grid">
                <div class="form-group">
                    <label><i class="fas fa-medal"></i> Rank</label>
                    <select name="rank" id="editRank">
                        <option value="">-- Select Rank --</option>
                        <?php
                        if (!empty($all_ranks)) {
                            foreach ($all_ranks as $r) {
                                echo "<option value='" . htmlspecialchars($r, ENT_QUOTES) . "'>" . htmlspecialchars($r) . "</option>";
                            }
                        } else {
                            // Fallback if DB is empty
                            $fallbackRanks = [
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
                            foreach ($fallbackRanks as $r) {
                                echo "<option value='$r'>$r</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-broadcast-tower"></i> Callsign</label>
                    <input type="text" name="position" id="editPosition" placeholder="e.g. Grizzly-1">
                </div>
            </div>

            <div class="edit-modal-grid">
                <div class="form-group">
                    <label><i class="fas fa-signal"></i> Status</label>
                    <select name="status" id="editStatus">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="LOA">LOA</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-shield-alt"></i> Role</label>
                    <select name="role" id="editRole">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-shield-alt"></i> Positions</label>
                <div class="edit-tags-container" id="editPositionsContainer">
                    <?php
                    if (isset($all_positions)) {
                        foreach ($all_positions as $pos): ?>
                            <div class="edit-position-pill" data-position-id="<?php echo $pos['id']; ?>">
                                <input type="checkbox" name="positions[]" value="<?php echo $pos['id']; ?>">
                                <span class="edit-position-pill-text"><?php echo htmlspecialchars($pos['name']); ?></span>
                            </div>
                        <?php endforeach;
                    } ?>
                </div>
            </div>

            <div class="edit-modal-actions">
                <button type="submit" name="update_user" class="btn edit-modal-btn-primary">
                    <i class="fas fa-save"></i> Update Member
                </button>
                <button type="button" id="editResumeBtn" onclick="" class="btn edit-modal-btn-secondary">
                    <i class="fas fa-file-alt"></i> View Resume
                </button>
            </div>
        </form>
    </div>
</div>

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
        const posAttr = btn.getAttribute('data-positions');
        const positionIds = posAttr ? posAttr.split(',') : [];

        // Populate fields
        document.getElementById('editUserId').value = id;
        document.getElementById('editUsername').value = username;
        document.getElementById('editPersonaName').value = personaname;
        document.getElementById('editRank').value = rank;
        document.getElementById('editPosition').value = position;
        document.getElementById('editStatus').value = status;
        document.getElementById('editRole').value = role;
        document.getElementById('editModalAvatar').src = avatar;
        document.getElementById('editModalDisplayName').textContent = personaname;

        // Populate Positions (Checkbox pills)
        const positionContainer = document.getElementById('editPositionsContainer');
        if (positionContainer) {
            positionContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = positionIds.includes(cb.value);
                const pill = cb.closest('.edit-position-pill');
                if (pill) pill.classList.toggle('active', cb.checked);
            });
        }

        // Set Resume Button Action (opens View mode first)
        const resumeBtn = document.getElementById('editResumeBtn');
        if (typeof loadResumeData === 'function') {
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

    // Position pill click handler
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.edit-position-pill').forEach(pill => {
            pill.addEventListener('click', function (e) {
                const cb = this.querySelector('input[type="checkbox"]');
                if (e.target !== cb) {
                    cb.checked = !cb.checked;
                }
                this.classList.toggle('active', cb.checked);
            });
        });
    });

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
                document.getElementById('deleteForm_' + userId).submit();
            }
        });
    }
</script>