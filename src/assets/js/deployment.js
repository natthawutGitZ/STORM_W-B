// =========================================================
// PERSONNEL DEPLOYMENT STATUS — JavaScript Controller
// =========================================================

const DEPLOY_API = 'api/deployment.php';
let deployData = [];
let deployRefreshTimer = null;
let deployIsEditMode = false;

// ---- Load deployment data ----
async function loadDeployment() {
    try {
        const r = await fetch(DEPLOY_API + '?action=list');
        const d = await r.json();
        if (!d.success) { console.warn('[DEPLOY] Load failed:', d.error); return; }
        deployData = d.data || [];
        renderDeployment();
        renderSidebarDeployment();
    } catch (e) {
        console.error('[DEPLOY] Load error:', e);
    }
}

// ---- Render all unit cards ----
function renderDeployment() {
    const container = document.getElementById('deployUnitList');
    if (!container) return;

    let totalSlots = 0, totalFilled = 0;
    deployData.forEach(u => {
        totalSlots += u.slots.length;
        totalFilled += u.slots.filter(s => s.player_name).length;
    });

    const deployedEl = document.getElementById('deployFilledCount');
    const totalEl = document.getElementById('deployTotalCount');
    if (deployedEl) deployedEl.textContent = totalFilled;
    if (totalEl) totalEl.textContent = totalSlots;

    container.innerHTML = deployData.map(unit => {
        const filled = unit.slots.filter(s => s.player_name).length;
        const total = unit.slots.length;
        const pct = total > 0 ? (filled / total * 100) : 0;
        const isExpanded = getUnitExpanded(unit.unit_name);

        let unitHeaderActions = '';
        if (deployIsEditMode) {
            unitHeaderActions = `
                <button class="unit-edit-btn" onclick="event.stopPropagation(); editUnit('${escDep(unit.unit_name)}', '${escDep(unit.unit_type)}')"><i class="fas fa-pencil"></i></button>
                <button class="unit-del-btn" onclick="event.stopPropagation(); deleteUnit('${escDep(unit.unit_name)}')"><i class="fas fa-trash"></i></button>
            `;
        }

        let slotsHtml = unit.slots.map((slot, idx) => renderSlotRow(unit, slot, idx)).join('');
        
        let addRoleBtn = '';
        if (deployIsEditMode) {
            addRoleBtn = `
                <div class="slot-row add-role-row">
                    <button class="add-role-btn" onclick="addRole('${escDep(unit.unit_name)}')"><i class="fas fa-plus"></i> ADD ROLE</button>
                </div>
            `;
        }

        return `
        <div class="unit-card ${isExpanded ? 'expanded' : ''}" data-unit="${escDep(unit.unit_name)}">
            <div class="unit-header" onclick="toggleUnit('${escDep(unit.unit_name)}')">
                <div class="unit-header-left">
                    <i class="fas fa-chevron-right unit-chevron"></i>
                    <span class="unit-type-badge ${escDep(unit.unit_type)}">${escDep(unit.unit_type)}</span>
                    <span class="unit-name">${escDep(unit.unit_name)}</span>
                </div>
                <div class="unit-header-right">
                    ${unitHeaderActions}
                    <span class="unit-count"><span class="filled">${filled}</span> / ${total}</span>
                    <div class="unit-progress-bar"><div class="unit-progress-fill" style="width:${pct}%"></div></div>
                </div>
            </div>
            <div class="unit-slots">
                ${slotsHtml}
                ${addRoleBtn}
            </div>
        </div>`;
    }).join('');

    if (deployIsEditMode) {
        container.innerHTML += `
            <button class="add-unit-btn" onclick="addUnit()"><i class="fas fa-plus"></i> ADD NEW UNIT</button>
        `;
    }

    renderHeaderButtons();
}

function renderHeaderButtons() {
    const box = document.querySelector('.deploy-stats-box');
    if (!box) return;
    
    // Remove old action buttons
    box.querySelectorAll('.deploy-action-btn, .deploy-btn-reset').forEach(e => e.remove());

    if (deployIsEditMode) {
        box.innerHTML += `
            <button class="deploy-action-btn deploy-btn-reset" onclick="resetDeployment()" title="Clear all assignments">
                <i class="fas fa-rotate-left"></i> RESET ALL
            </button>
            <button class="deploy-action-btn deploy-btn-exit" onclick="toggleEditMode()" title="Exit Edit Mode">
                <i class="fas fa-check"></i> DONE EDITING
            </button>
        `;
    } else {
        box.innerHTML += `
            <button class="deploy-action-btn deploy-btn-edit" onclick="toggleEditMode()" title="Edit Roster Structure">
                <i class="fas fa-pen-to-square"></i> EDIT ROSTER
            </button>
        `;
    }
}

// ---- Render a single slot row ----
function renderSlotRow(unit, slot, idx) {
    const isFilled = !!slot.player_name;
    
    let actions = '';
    if (deployIsEditMode) {
        actions = `
            <button class="slot-action edit-role-btn" onclick="editRole(${slot.id}, '${escDep(slot.role_name)}')"><i class="fas fa-pencil"></i></button>
            <button class="slot-action del-role-btn" onclick="deleteRole(${slot.id})"><i class="fas fa-trash"></i></button>
        `;
    } else {
        actions = isFilled
            ? `<button class="slot-action withdraw-btn" onclick="withdrawSlot(${slot.id})" title="Withdraw"><i class="fas fa-times"></i></button>`
            : `<button class="slot-action signup-btn" onclick="openSignupModal(${slot.id},'${escDep(slot.role_name)}','${escDep(unit.unit_name)}')" title="Sign Up"><i class="fas fa-plus"></i></button>`;
    }

    return `
    <div class="slot-row">
        <span class="slot-index">${String(idx + 1).padStart(2, '0')}</span>
        <span class="slot-role">${escDep(slot.role_name)}</span>
        <span class="slot-player ${isFilled ? 'filled' : 'vacant'}">
            ${isFilled ? escDep(slot.player_name) : '── VACANT ──'}
        </span>
        ${actions}
    </div>`;
}

// ---- Toggle Edit Mode ----
function toggleEditMode() {
    if (deployIsEditMode) {
        deployIsEditMode = false;
        renderDeployment();
        startDeployRefresh();
    } else {
        if (typeof requireAuth !== 'function') { alert('Auth system not loaded'); return; }
        requireAuth(function() {
            deployIsEditMode = true;
            stopDeployRefresh(); // Stop auto-refresh while editing to prevent UI jumps
            renderDeployment();
        });
    }
}

// ---- Expand / Collapse unit ----
function toggleUnit(unitName) {
    const card = document.querySelector(`.unit-card[data-unit="${unitName}"]`);
    if (!card) return;
    card.classList.toggle('expanded');
    const expanded = card.classList.contains('expanded');
    const state = JSON.parse(sessionStorage.getItem('deploy_expanded') || '{}');
    state[unitName] = expanded;
    sessionStorage.setItem('deploy_expanded', JSON.stringify(state));
}

function getUnitExpanded(unitName) {
    const state = JSON.parse(sessionStorage.getItem('deploy_expanded') || '{}');
    return !!state[unitName];
}

// ---- Signup Modal ----
let signupSlotId = null;

function openSignupModal(slotId, roleName, unitName) {
    signupSlotId = slotId;
    document.getElementById('signupRoleName').textContent = roleName;
    document.getElementById('signupUnitName').textContent = unitName;
    document.getElementById('signupPlayerInput').value = '';
    document.getElementById('signupModal').classList.add('show');
    setTimeout(() => document.getElementById('signupPlayerInput').focus(), 200);
}

function closeSignupModal() {
    document.getElementById('signupModal').classList.remove('show');
    signupSlotId = null;
}

async function confirmSignup() {
    const name = document.getElementById('signupPlayerInput').value.trim();
    if (!name) { document.getElementById('signupPlayerInput').style.borderColor = '#ff4c66'; return; }
    if (!signupSlotId) return;

    try {
        const r = await fetch(DEPLOY_API + '?action=signup', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slot_id: signupSlotId, player_name: name })
        });
        const d = await r.json();
        if (d.success) {
            closeSignupModal();
            await loadDeployment();
        } else { alert(d.error || 'Failed to sign up'); }
    } catch (e) { alert('Network error'); console.error(e); }
}

async function withdrawSlot(slotId) {
    if (!confirm('⚠ CONFIRM WITHDRAWAL — Remove assignment from this slot?')) return;
    try {
        const r = await fetch(DEPLOY_API + '?action=withdraw', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slot_id: slotId })
        });
        const d = await r.json();
        if (d.success) { await loadDeployment(); }
        else { alert(d.error || 'Failed to withdraw'); }
    } catch (e) { alert('Network error'); }
}

// =========================================================
// S2 EDIT ACTIONS (Units & Roles)
// =========================================================

async function _deployApiCall(action, payload) {
    payload.password = getAuthPass();
    try {
        const r = await fetch(DEPLOY_API + '?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const d = await r.json();
        if (d.success) { await loadDeployment(); return true; }
        else { alert(d.error || 'Operation failed'); return false; }
    } catch (e) { alert('Network error'); return false; }
}

async function resetDeployment() {
    if (!confirm('⚠ CONFIRM RESET — Clear ALL personnel assignments?')) return;
    await _deployApiCall('reset', {});
}

async function addUnit() {
    const name = prompt('Enter NEW Unit Name (e.g. Troop C):');
    if (!name) return;
    const type = prompt('Enter Unit Type Badge (e.g. SFOD-D, ODA, RANGER, AIR):') || 'OTHER';
    await _deployApiCall('add_unit', { unit_name: name, unit_type: type.toUpperCase() });
}

async function editUnit(oldName, oldType) {
    const newName = prompt('Edit Unit Name:', oldName);
    if (!newName) return;
    const newType = prompt('Edit Unit Type Badge:', oldType) || 'OTHER';
    await _deployApiCall('edit_unit', { old_unit_name: oldName, new_unit_name: newName, new_unit_type: newType.toUpperCase() });
}

async function deleteUnit(name) {
    if (!confirm(`⚠ CONFIRM DELETION — Delete unit '${name}' and ALL its roles?`)) return;
    await _deployApiCall('delete_unit', { unit_name: name });
}

async function addRole(unitName) {
    const roleName = prompt(`Enter NEW Role for ${unitName}:`);
    if (!roleName) return;
    
    // Automatically expand the unit since we added a role
    const state = JSON.parse(sessionStorage.getItem('deploy_expanded') || '{}');
    state[unitName] = true;
    sessionStorage.setItem('deploy_expanded', JSON.stringify(state));

    await _deployApiCall('add_role', { unit_name: unitName, role_name: roleName });
}

async function editRole(slotId, oldRole) {
    const newRole = prompt('Edit Role Name:', oldRole);
    if (!newRole || newRole === oldRole) return;
    await _deployApiCall('edit_role', { slot_id: slotId, role_name: newRole });
}

async function deleteRole(slotId) {
    if (!confirm('⚠ Delete this role?')) return;
    await _deployApiCall('delete_role', { slot_id: slotId });
}

// ---- Sidebar Summary ----
function renderSidebarDeployment() {
    // Intentionally empty: sidebar detailed list was replaced by a single button.
}

// ---- Auto-refresh ----
function startDeployRefresh() {
    if (deployRefreshTimer || deployIsEditMode) return;
    deployRefreshTimer = setInterval(loadDeployment, 5000);
}
function stopDeployRefresh() {
    if (deployRefreshTimer) { clearInterval(deployRefreshTimer); deployRefreshTimer = null; }
}

// ---- Helper ----
function escDep(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    const inp = document.getElementById('signupPlayerInput');
    if (inp) inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') confirmSignup();
    });
    if (inp) inp.addEventListener('input', function() {
        this.style.borderColor = '#333';
    });
});

const origSwitchTabDeploy = window.switchTab;
if (typeof origSwitchTabDeploy === 'function') {
    window.switchTab = function(tab) {
        origSwitchTabDeploy(tab);
        if (tab === 'unit') {
            loadDeployment();
            startDeployRefresh();
        } else {
            stopDeployRefresh();
        }
    };
}

document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'unit') {
        if (typeof switchTab === 'function') switchTab('unit');
    }
    loadDeployment();
});
