// =========================================================
// PERSONNEL DEPLOYMENT STATUS — JavaScript Controller
// =========================================================

const DEPLOY_API = 'api/deployment.php';
let deployData = [];
let deployRefreshTimer = null;

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

    // Calculate totals
    let totalSlots = 0, totalFilled = 0;
    deployData.forEach(u => {
        totalSlots += u.slots.length;
        totalFilled += u.slots.filter(s => s.player_name).length;
    });

    // Update header stats
    const deployedEl = document.getElementById('deployFilledCount');
    const totalEl = document.getElementById('deployTotalCount');
    if (deployedEl) deployedEl.textContent = totalFilled;
    if (totalEl) totalEl.textContent = totalSlots;

    // Render units
    container.innerHTML = deployData.map(unit => {
        const filled = unit.slots.filter(s => s.player_name).length;
        const total = unit.slots.length;
        const pct = total > 0 ? (filled / total * 100) : 0;
        const isExpanded = getUnitExpanded(unit.unit_name);

        return `
        <div class="unit-card ${isExpanded ? 'expanded' : ''}" data-unit="${escDep(unit.unit_name)}">
            <div class="unit-header" onclick="toggleUnit('${escDep(unit.unit_name)}')">
                <div class="unit-header-left">
                    <i class="fas fa-chevron-right unit-chevron"></i>
                    <span class="unit-type-badge ${escDep(unit.unit_type)}">${escDep(unit.unit_type)}</span>
                    <span class="unit-name">${escDep(unit.unit_name)}</span>
                </div>
                <div class="unit-header-right">
                    <span class="unit-count"><span class="filled">${filled}</span> / ${total}</span>
                    <div class="unit-progress-bar"><div class="unit-progress-fill" style="width:${pct}%"></div></div>
                </div>
            </div>
            <div class="unit-slots">
                ${unit.slots.map((slot, idx) => renderSlotRow(unit, slot, idx)).join('')}
            </div>
        </div>`;
    }).join('');
}

// ---- Render a single slot row ----
function renderSlotRow(unit, slot, idx) {
    const isFilled = !!slot.player_name;
    return `
    <div class="slot-row">
        <span class="slot-index">${String(idx + 1).padStart(2, '0')}</span>
        <span class="slot-role">${escDep(slot.role_name)}</span>
        <span class="slot-player ${isFilled ? 'filled' : 'vacant'}">
            ${isFilled ? escDep(slot.player_name) : '── VACANT ──'}
        </span>
        ${isFilled
            ? `<button class="slot-action withdraw-btn" onclick="withdrawSlot(${slot.id})" title="Withdraw"><i class="fas fa-times"></i></button>`
            : `<button class="slot-action signup-btn" onclick="openSignupModal(${slot.id},'${escDep(slot.role_name)}','${escDep(unit.unit_name)}')" title="Sign Up"><i class="fas fa-plus"></i></button>`
        }
    </div>`;
}

// ---- Expand / Collapse unit ----
function toggleUnit(unitName) {
    const card = document.querySelector(`.unit-card[data-unit="${unitName}"]`);
    if (!card) return;
    card.classList.toggle('expanded');
    // Persist state
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
        } else {
            alert(d.error || 'Failed to sign up');
        }
    } catch (e) {
        alert('Network error');
        console.error(e);
    }
}

// ---- Withdraw ----
async function withdrawSlot(slotId) {
    if (!confirm('⚠ CONFIRM WITHDRAWAL — Remove assignment from this slot?')) return;
    try {
        const r = await fetch(DEPLOY_API + '?action=withdraw', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slot_id: slotId })
        });
        const d = await r.json();
        if (d.success) {
            await loadDeployment();
        } else {
            alert(d.error || 'Failed to withdraw');
        }
    } catch (e) {
        alert('Network error');
    }
}

// ---- Reset All (admin) ----
async function resetDeployment() {
    if (typeof requireAuth !== 'function') { alert('Auth system not loaded'); return; }
    requireAuth(async function() {
        if (!confirm('⚠ CONFIRM RESET — Clear ALL personnel assignments?')) return;
        try {
            const r = await fetch(DEPLOY_API + '?action=reset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: getAuthPass() })
            });
            const d = await r.json();
            if (d.success) {
                await loadDeployment();
            } else {
                alert(d.error || 'Reset failed');
            }
        } catch (e) {
            alert('Network error');
        }
    });
}

// ---- Sidebar Summary ----
function renderSidebarDeployment() {
    const el = document.getElementById('sidebarDeployList');
    if (!el) return;

    let totalSlots = 0, totalFilled = 0;

    el.innerHTML = deployData.map(u => {
        const filled = u.slots.filter(s => s.player_name).length;
        const total = u.slots.length;
        totalSlots += total;
        totalFilled += filled;
        // Shorten name for sidebar
        const shortName = u.unit_name.replace(/\s*\[.*?\]\s*/g, '').substring(0, 16);
        return `<div class="sidebar-deploy-item">
            <span class="unit-short">${escDep(shortName)}</span>
            <span class="count ${filled === 0 ? 'empty' : ''}">${filled}/${total}</span>
        </div>`;
    }).join('');

    el.innerHTML += `<div class="sidebar-deploy-total">
        <span>TOTAL</span>
        <span class="total-num">${totalFilled} / ${totalSlots}</span>
    </div>`;
}

// ---- Auto-refresh ----
function startDeployRefresh() {
    if (deployRefreshTimer) return;
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

// ---- Enter key support for signup modal ----
document.addEventListener('DOMContentLoaded', function() {
    const inp = document.getElementById('signupPlayerInput');
    if (inp) inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') confirmSignup();
    });
    // Reset border on input
    if (inp) inp.addEventListener('input', function() {
        this.style.borderColor = '#333';
    });
});

// ---- Hook into tab switch ----
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

// ---- Load on initial if unit tab is active ----
document.addEventListener('DOMContentLoaded', function() {
    // Check URL param
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'unit') {
        if (typeof switchTab === 'function') switchTab('unit');
    }
    // Load sidebar summary regardless
    loadDeployment();
});
