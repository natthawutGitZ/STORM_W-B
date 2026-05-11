// =========================================================
// INTEL DOSSIER SYSTEM - JavaScript Controller
// =========================================================

const DOSSIER_API = 'api/dossier.php';
let allDossiers = [];
let activeDossierId = null;
let dossierEditId = null;

// ---- Load all dossiers from API ----
async function loadDossiers() {
    try {
        console.log('[DOSSIER] Loading from:', DOSSIER_API + '?action=list');
        const r = await fetch(DOSSIER_API + '?action=list');
        console.log('[DOSSIER] Response status:', r.status);
        const text = await r.text();
        console.log('[DOSSIER] Raw response:', text.substring(0, 300));
        let d;
        try { d = JSON.parse(text); } catch (pe) {
            console.error('[DOSSIER] JSON parse error - response was not JSON:', text.substring(0, 500));
            return;
        }
        if (!d.success) { console.warn('[DOSSIER] API returned success=false:', d.error); return; }
        allDossiers = d.data || [];
        console.log('[DOSSIER] Loaded', allDossiers.length, 'dossiers');
        
        // Auto-select primary target by default
        if (!activeDossierId && allDossiers.length > 0) {
            const primary = allDossiers.find(x => x.category === 'primary');
            activeDossierId = primary ? primary.id : allDossiers[0].id;
        }

        renderDossierCategories();
        updateDossierCounts();
        
        if (activeDossierId) {
            const found = allDossiers.find(x => x.id == activeDossierId);
            if (found) showDossierDetail(found);
        }
    } catch (e) {
        console.error('[DOSSIER] Load error:', e);
    }
}

// ---- Update counts in category headers ----
function updateDossierCounts() {
    const primary = allDossiers.filter(d => d.category === 'primary');
    const secondary = allDossiers.filter(d => d.category === 'secondary');
    const poi = allDossiers.filter(d => d.category === 'poi');

    document.getElementById('catPrimaryCount').textContent = primary.length;
    document.getElementById('catSecondaryCount').textContent = secondary.length;
    document.getElementById('catPoiCount').textContent = poi.length;

    // Update left panel stats
    const intelEl = document.getElementById('dossierIntelCount');
    if (intelEl) intelEl.textContent = allDossiers.length + ' HUMINT';
}

// ---- Render category lists ----
function renderDossierCategories() {
    const search = (document.getElementById('dossierSearch')?.value || '').toLowerCase();
    const filtered = search
        ? allDossiers.filter(d =>
            (d.callsign || '').toLowerCase().includes(search) ||
            (d.full_name || '').toLowerCase().includes(search))
        : allDossiers;

    renderCategoryList('primary', 'catPrimaryList', filtered.filter(d => d.category === 'primary'), true);
    renderCategoryList('secondary', 'catSecondaryList', filtered.filter(d => d.category === 'secondary'), false);
    renderCategoryList('poi', 'catPoiList', filtered.filter(d => d.category === 'poi'), true);
}

function renderCategoryList(category, elementId, items, gridMode) {
    const el = document.getElementById(elementId);
    if (!el) return;

    if (!items.length) {
        el.innerHTML = '<div style="color:#555;font-family:var(--mono);font-size:.65rem;padding:4px 0;">NO ASSETS</div>';
        return;
    }

    if (gridMode) {
        el.innerHTML = items.map(d => `
            <div class="asset-thumb ${activeDossierId == d.id ? 'active' : ''}" 
                 onclick="selectDossier(${d.id})" title="${escD(d.callsign || d.full_name || '')}">
                ${d.photo_url
                    ? `<img src="${escD(d.photo_url)}" alt="${escD(d.callsign)}">`
                    : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#555;font-size:1rem;"><i class="fas fa-user"></i></div>`
                }
            </div>
        `).join('');
    } else {
        el.innerHTML = items.map(d => `
            <div class="asset-card ${activeDossierId == d.id ? 'active' : ''}" onclick="selectDossier(${d.id})">
                <div class="asset-avatar">
                    ${d.photo_url
                        ? `<img src="${escD(d.photo_url)}" alt="${escD(d.callsign)}">`
                        : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#555;"><i class="fas fa-user"></i></div>`
                    }
                </div>
                <div class="asset-info">
                    <div class="asset-callsign">
                        ${escD(d.callsign || '-')}
                        ${d.status === 'CAPTURED' ? '<i class="fas fa-lock captured-icon"></i>' : ''}
                    </div>
                    <div class="asset-realname">${escD(d.full_name || '')}</div>
                </div>
                <span class="asset-tag ${statusClass(d.status)}">${escD(d.status || '-')}</span>
            </div>
        `).join('');
    }
}

function statusClass(status) {
    if (!status) return '';
    const s = status.toUpperCase();
    if (s === 'CAPTURED') return 'captured';
    if (s === 'AT LARGE') return 'at-large';
    if (s === 'KIA') return 'kia';
    if (s === 'ACTIVE') return 'active-target';
    return '';
}

// ---- Select a dossier ----
function selectDossier(id) {
    const d = allDossiers.find(x => x.id == id);
    if (!d) return;
    activeDossierId = id;
    showDossierDetail(d);
    renderDossierCategories(); // re-render to show active state
}

// ---- Show dossier detail in right panel ----
function showDossierDetail(d) {
    document.getElementById('dossierEmptyState').style.display = 'none';
    document.getElementById('dossierDetailView').style.display = 'block';

    // Header - show full name for primary targets, callsign for others
    document.getElementById('dossierCallsign').textContent = (d.category === 'primary' && d.full_name) ? d.full_name : (d.callsign || '-');
    const statusIcon = document.getElementById('dossierStatusIcon');
    if (d.status === 'CAPTURED') {
        statusIcon.innerHTML = '<i class="fas fa-lock" style="color:#00cc44;"></i>';
    } else if (d.status === 'KIA') {
        statusIcon.innerHTML = '<i class="fas fa-skull" style="color:#888;"></i>';
    } else {
        statusIcon.innerHTML = '';
    }

    // Badges
    const badges = document.getElementById('dossierBadges');
    let badgeHtml = '';
    if (d.status === 'CAPTURED') {
        badgeHtml += '<span class="dossier-badge captured"><i class="fas fa-check"></i> CAPTURED</span>';
    }
    if (d.task_directive && d.task_directive !== 'NONE') {
        badgeHtml += `<span class="dossier-badge task">TASK: ${escD(d.task_directive)}</span>`;
    }
    badgeHtml += `<button class="dossier-menu-btn" onclick="openDossierEditModal(${d.id})" title="Edit Dossier"><i class="fas fa-ellipsis-v"></i></button>`;
    badges.innerHTML = badgeHtml;

    // Photo (clickable for lightbox)
    const photoEl = document.getElementById('dossierPhoto');
    if (d.photo_url) {
        photoEl.innerHTML = `<img src="${escD(d.photo_url)}" alt="${escD(d.callsign)}"><div class="photo-scanline"></div>`;
        photoEl.onclick = function() { openIntelLightbox(d); };
    } else {
        photoEl.innerHTML = '<div class="photo-placeholder"><i class="fas fa-user-secret"></i></div>';
        photoEl.onclick = null;
    }

    // Core Data
    document.getElementById('dFullName').textContent = d.full_name || '-';
    const threatEl = document.getElementById('dThreatLevel');
    threatEl.textContent = d.threat_level || '-';
    threatEl.className = 'core-data-value' + (d.threat_level === 'HIGH' ? ' high' : '');

    document.getElementById('dLastLoi').textContent = d.last_loi || '-';
    // Force GRID REF to be N/A for all as requested
    document.getElementById('dGridRef').textContent = 'N/A';
    document.getElementById('dStatus').textContent = d.status || '-';
    document.getElementById('dCategory').textContent = (d.category || '-').toUpperCase();
    document.getElementById('dAssetTag').textContent = d.asset_tag || '-';

    const taskEl = document.getElementById('dTaskDirective');
    taskEl.textContent = d.task_directive || '-';
    taskEl.className = 'core-data-value red';

    // Intel Stats with bar animation
    function setStat(id, barId, val) {
        document.getElementById(id).textContent = val || '-';
        const bar = document.getElementById(barId);
        if (bar) {
            const pct = val ? Math.min(parseFloat(val) * 10, 100) : 0;
            bar.style.width = '0%';
            setTimeout(() => { bar.style.width = pct + '%'; }, 50);
        }
    }
    setStat('dStatMA', 'dStatMABar', d.stat_ma);
    setStat('dStatFOG', 'dStatFOGBar', d.stat_fog);
    setStat('dStatFR', 'dStatFRBar', d.stat_fr);
    setStat('dStatINT', 'dStatINTBar', d.stat_int);

    // Summary - highlight all known callsigns
    const summaryEl = document.getElementById('dSummary');
    if (d.summary) {
        let html = escD(d.summary).replace(/\n/g, '<br>');
        // Collect all callsigns from loaded dossiers
        const callsigns = allDossiers.map(x => x.callsign).filter(c => c && c.length > 2);
        callsigns.sort((a, b) => b.length - a.length); // longest first to avoid partial matches
        callsigns.forEach(cs => {
            const escaped = escD(cs).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const re = new RegExp('(' + escaped + ')', 'gi');
            html = html.replace(re, '<strong>$1</strong>');
        });
        summaryEl.innerHTML = html;
    } else {
        summaryEl.innerHTML = '<em style="color:var(--r-muted);">No summary available.</em>';
    }

    // Evidence grid - populate with all images from the same folder
    const evidenceGrid = document.getElementById('dEvidenceGrid');
    const evidenceBody = document.getElementById('dEvidenceBody');
    if (evidenceGrid) {
        evidenceGrid.innerHTML = '';
        const folder = getFolderForImage(d.photo_url);
        if (folder && INTEL_IMAGE_FOLDERS[folder]) {
            const images = INTEL_IMAGE_FOLDERS[folder];
            images.forEach((imgUrl, idx) => {
                const item = document.createElement('div');
                item.className = 'evidence-item';
                item.innerHTML = `<img src="${escD(imgUrl)}" alt="EVD-${idx+1}"><div class="ev-name">IMG ${idx+1}/${images.length}</div>`;
                item.onclick = function() {
                    openIntelLightboxFromUrl(imgUrl, (d.callsign || '') + ' // ' + folder);
                };
                evidenceGrid.appendChild(item);
            });
            if (evidenceBody) evidenceBody.classList.add('show');
        } else {
            evidenceGrid.innerHTML = '<div style="color:var(--r-muted);font-family:var(--mono);font-size:.65rem;text-align:center;padding:8px;">NO ATTACHMENTS</div>';
            if (evidenceBody) evidenceBody.classList.remove('show');
        }
    }
}

// ---- Modal: Create / Edit ----
function openDossierModal(category) {
    dossierEditId = null;
    document.getElementById('dossierModalTitle').textContent = 'CREATE ASSET DOSSIER';
    clearDossierForm();
    if (category) document.getElementById('df_category').value = category;
    document.getElementById('dossierModal').classList.add('show');
}

function openDossierEditModal(id) {
    const d = allDossiers.find(x => x.id == id);
    if (!d) return;
    dossierEditId = id;
    document.getElementById('dossierModalTitle').textContent = 'EDIT DOSSIER - ' + (d.callsign || '').toUpperCase();
    fillDossierForm(d);
    document.getElementById('dossierModal').classList.add('show');
}

function closeDossierModal() {
    document.getElementById('dossierModal').classList.remove('show');
}

function clearDossierForm() {
    const fields = ['callsign', 'full_name', 'category', 'threat_level', 'status', 'task_directive',
        'last_loi', 'grid_ref', 'asset_tag', 'photo_url', 'stat_ma', 'stat_fog', 'stat_fr', 'stat_int', 'summary'];
    fields.forEach(f => {
        const el = document.getElementById('df_' + f);
        if (el) {
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        }
    });
}

function fillDossierForm(d) {
    const map = {
        callsign: d.callsign, full_name: d.full_name, category: d.category,
        threat_level: d.threat_level, status: d.status, task_directive: d.task_directive,
        last_loi: d.last_loi, grid_ref: d.grid_ref, asset_tag: d.asset_tag,
        photo_url: d.photo_url, stat_ma: d.stat_ma, stat_fog: d.stat_fog,
        stat_fr: d.stat_fr, stat_int: d.stat_int, summary: d.summary
    };
    for (const [k, v] of Object.entries(map)) {
        const el = document.getElementById('df_' + k);
        if (el && v !== null && v !== undefined) el.value = v;
    }
}

function getDossierFormData() {
    const fields = ['callsign', 'full_name', 'category', 'threat_level', 'status', 'task_directive',
        'last_loi', 'grid_ref', 'asset_tag', 'photo_url', 'stat_ma', 'stat_fog', 'stat_fr', 'stat_int', 'summary'];
    const data = {};
    fields.forEach(f => {
        const el = document.getElementById('df_' + f);
        if (el) data[f] = el.value;
    });
    return data;
}

// ---- Save dossier ----
async function saveDossier() {
    const data = getDossierFormData();
    if (!data.callsign && !data.full_name) {
        alert('Callsign or Full Name is required.');
        return;
    }

    const body = { password: 'S2', fields: data };
    let action = 'create';
    if (dossierEditId) {
        body.id = dossierEditId;
        action = 'update';
    }

    try {
        const r = await fetch(DOSSIER_API + '?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const d = await r.json();
        if (d.success) {
            closeDossierModal();
            await loadDossiers();
            if (dossierEditId) {
                selectDossier(dossierEditId);
            } else if (d.id) {
                selectDossier(d.id);
            }
        } else {
            alert(d.error || 'Failed to save dossier');
        }
    } catch (e) {
        alert('Network error');
        console.error(e);
    }
}

// ---- Delete dossier ----
async function deleteDossier(id) {
    if (!confirm('WARNING: CONFIRM DELETE - This action cannot be undone')) return;
    try {
        const r = await fetch(DOSSIER_API + '?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: 'S2', id })
        });
        const d = await r.json();
        if (d.success) {
            if (activeDossierId == id) {
                activeDossierId = null;
                document.getElementById('dossierEmptyState').style.display = '';
                document.getElementById('dossierDetailView').style.display = 'none';
            }
            await loadDossiers();
        } else {
            alert(d.error || 'Failed to delete');
        }
    } catch (e) {
        alert('Network error');
    }
}

// ---- Search ----
document.getElementById('dossierSearch')?.addEventListener('input', function () {
    renderDossierCategories();
});

// ---- Dossier tab switching ----
document.querySelectorAll('.dossier-tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.dossier-tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

// ---- Left panel nav (Removed) ----

// ---- MRS SWITCH button (Removed) ----

// ---- Toggle left dossier list ----
document.getElementById('toggleDossierListBtn')?.addEventListener('click', function() {
    const layout = document.querySelector('.dossier-layout');
    if (layout) {
        layout.classList.toggle('list-collapsed');
    }
});

// ---- Save button ----
document.getElementById('dossierSaveBtn')?.addEventListener('click', saveDossier);

// ---- Helper ----
function escD(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ---- Load on tab switch ----
const origSwitchTab = window.switchTab;
if (typeof origSwitchTab === 'function') {
    window.switchTab = function (tab) {
        origSwitchTab(tab);
        if (tab === 'intel') loadDossiers();
    };
}

// ---- Load brief data into left panel header (same data as OPS HUB) ----
async function loadDossierBrief() {
    try {
        const r = await fetch('api/intelligence.php?action=get_brief');
        const d = await r.json();
        if (!d.success || !d.data) return;
        const b = d.data;
        const titleEl = document.getElementById('dossierOpTitle');
        if (titleEl) titleEl.textContent = b.op_name || 'OPERATION STORMSURGE';
    } catch (e) { }
}

// ---- Load operations into left panel (same data as OPS HUB sorties) ----
async function loadDossierOps() {
    try {
        const r = await fetch('api/intelligence.php?action=list&type=operations');
        const d = await r.json();
        if (!d.success) return;
        const el = document.getElementById('dossierOpsList');
        if (!el) return;
        if (!d.data.length) {
            el.innerHTML = '<div style="color:#555;font-family:var(--mono);font-size:.65rem;">NO ACTIVE SORTIES</div>';
            return;
        }
        el.innerHTML = d.data.map((o, i) => `
            <div class="dossier-op-item ${i === 0 ? 'active' : ''}">
                <div class="op-name">${escD(o.codename)}</div>
                <div class="op-meta">
                    <span class="op-status-tag">${escD(o.status)}</span>
                    <span>${escD(o.priority || 'OPERATION')}</span>
                </div>
            </div>
        `).join('');
    } catch (e) { }
}

// ---- Load intel report count into left panel stats ----
async function loadDossierIntelStats() {
    try {
        const r = await fetch('api/intelligence.php?action=list&type=reports');
        const d = await r.json();
        if (!d.success) return;
        const intelEl = document.getElementById('dossierIntelCount');
        if (intelEl) intelEl.textContent = (d.data?.length || 0) + ' HUMINT';

        const r2 = await fetch('api/intelligence.php?action=list&type=operations');
        const d2 = await r2.json();
        if (!d2.success) return;
        const taskEl = document.getElementById('dossierTaskCount');
        if (taskEl) {
            const active = (d2.data || []).filter(o => o.status === 'ACTIVE').length;
            taskEl.textContent = active + ' ACTIVE';
        }
    } catch (e) { }
}

// ---- Seed sample dossiers if DB is empty or outdated ----
async function seedDossiersIfEmpty() {
    try {
        console.log('[DOSSIER SEED] Checking if seed needed...');
        const r = await fetch(DOSSIER_API + '?action=list');
        const text = await r.text();
        console.log('[DOSSIER SEED] List response:', text.substring(0, 200));
        let d;
        try { d = JSON.parse(text); } catch (pe) {
            console.error('[DOSSIER SEED] API not returning JSON - cannot seed:', text.substring(0, 500));
            return;
        }
        // Check if data already matches the Arkerian narrative v3 (22 dossiers, no RAVEN/WARLORD)
        if (d.success && d.data && d.data.length > 0) {
            const hasCleanser = d.data.some(x => x.callsign === 'CLEANSER');
            const hasRaven = d.data.some(x => x.callsign === 'RAVEN');
            if (hasCleanser && !hasRaven && d.data.length >= 18) { console.log('[DOSSIER SEED] Already seeded with Arkerian data v3'); return; }
            // Old data exists - reset first
            console.log('[DOSSIER SEED] Old data found, resetting...');
            const rr = await fetch(DOSSIER_API + '?action=reset', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: 'S2' })
            });
            console.log('[DOSSIER SEED] Reset response:', await rr.text());
        }

        const seeds = [
    // ============ PRIMARY TARGET - HVT ============
    {
        callsign: 'SOVEREIGN',
        full_name: 'Donald III - Supreme Leader of the Arkerian Federation',
        category: 'primary',
        threat_level: 'HIGH',
        status: 'AT LARGE',
        task_directive: 'CAPTURE/KILL',
        last_loi: 'AO1 - Presidential Palace, Arkeria Capital',
        grid_ref: 'N/A',
        asset_tag: 'HVT-001',
        photo_url: 'assets/img/intel/HVT/20260508231651_1.jpg',
        stat_ma: '9.8',
        stat_fog: '8.5',
        stat_fr: '3.0',
        stat_int: '9.2',
        summary: [
            'SOVEREIGN (Donald III) seized power via military coup on 14 FEB 2026. He orchestrated the overthrow of the democratically elected government using loyalist forces.',
            'Since seizing power, SOVEREIGN conducted systematic purges resulting in 46,000-57,000 civilian casualties. Ordered the attack on the IDAP mission (200 personnel - half killed, rest captured). Ordered the live broadcast execution of 4 American nationals.',
            'When confronted with US demands: "What right does a dog have to beg mercy from the supreme?"',
            'CIA Ground Branch intercepted Arkerian-sponsored terror cell en route to US homeland.',
            '- PERSONAL PROFILE -',
            'DOB: 12 MAR 1971 (Age 55) | POB: Vostochka Province, Arkeria',
            'Blood Type: O+ | Height: 182 cm (6\'0") | Weight: 91 kg (200 lbs)',
            'Eyes: Brown | Hair: Black (full beard)',
            'Distinguishing Marks: Scar left forearm (shrapnel, 2008). Always wears woodland DPM without rank insignia.',
            'Languages: Arkerian (native), Russian (fluent), English (basic)',
            'Family: Wife Mila Arkanova (status unknown). Two sons evacuated.',
            'Education: Arkerian Military Academy (1992), Eastern Bloc War College (1998)',
            'Background: Former Colonel, 4th Mechanized Div. Dismissed 2019. Built loyalist network 2020-2025.',
            'Psych: Narcissistic, megalomaniac. Paranoid - changes location every 48-72 hrs. Always with red beret Presidential Guard.',
            'Habits: Chain smoker (Arkerian Gold). Right-handed. Favors Makarov PM sidearm.',
            'Designated HVT-001. Capture preferred for ICC prosecution; lethal force authorized.'
        ].join('\n')
    },
    // ============ SECONDARY - PERSONNEL ============
    {
        callsign: 'MARSHAL',
        full_name: 'Unknown - Troop Formation Commander',
        category: 'secondary',
        threat_level: 'MEDIUM',
        status: 'AT LARGE',
        task_directive: 'IDENTIFY',
        last_loi: 'AO1 - Port District / Staging Area',
        grid_ref: 'N/A',
        asset_tag: 'HVT-004',
        photo_url: 'assets/img/intel/HVT/20260508224910_1.jpg',
        stat_ma: '7.0',
        stat_fog: '5.0',
        stat_fr: '8.5',
        stat_int: '6.0',
        summary: [
            'Aerial reconnaissance captured a large-scale troop formation at the AO1 port district near an industrial crane and warehouse complex. Estimated 150-200 soldiers in formation.',
            'The massed troops suggest either a deployment briefing or a reinforcement staging operation. The port location indicates possible seaborne logistics or troop movement capability.',
            'The commander of this formation is designated MARSHAL. Identity unknown. The scale of the formation indicates a company-to-battalion level officer.',
            '- ASSESSMENT -',
            'Force Size: 150-200 PAX in open formation',
            'Equipment: Standard infantry small arms, combat loads',
            'Location: Port industrial zone - crane, warehouses, paved staging area',
            'Significance: Largest single troop concentration observed in AO1. Priority target for Phase 1 air strike package if negotiations fail.'
        ].join('\n')
    },
    {
        callsign: 'CLEANSER',
        full_name: 'Unknown - Rural Pacification Unit Commander',
        category: 'secondary',
        threat_level: 'HIGH',
        status: 'AT LARGE',
        task_directive: 'CAPTURE/KILL',
        last_loi: 'AO1-AO2 - Rural Sector',
        grid_ref: 'N/A',
        asset_tag: 'HVT-005',
        photo_url: 'assets/img/intel/Civil%20War/20260508224931_1.jpg',
        stat_ma: '7.5',
        stat_fog: '4.0',
        stat_fr: '9.0',
        stat_int: '5.5',
        summary: [
            'CLEANSER commands a rural pacification unit responsible for systematic killing of civilians in outlying villages. SIGINT intercepts reference his unit as "Cleanup Detail."',
            'Imagery shows CLEANSER\'s soldiers at a rural homestead where multiple civilian bodies were found - victims include men and women in civilian clothing. Weapons (AK-pattern rifles) staged next to bodies suggest the regime plants weapons to justify killings as "counter-insurgency."',
            'The mustached soldier seen standing over bodies is tentatively designated as CLEANSER based on repeat sightings.',
            '- PROFILE -',
            'Identity: UNKNOWN | Estimated Age: 40-50',
            'Distinguishing: Mustache, woodland BDU, patrol cap. Carries AK-74.',
            'Assessment: Company-level officer running death squads. War crimes suspect. CAPTURE for ICC prosecution; lethal force authorized.'
        ].join('\n')
    },
    {
        callsign: 'IRONSIDE',
        full_name: 'Unknown - Armored Section Commander',
        category: 'secondary',
        threat_level: 'MEDIUM',
        status: 'ACTIVE',
        task_directive: 'NEUTRALIZE',
        last_loi: 'AO1 - Main Airfield Complex',
        grid_ref: 'N/A',
        asset_tag: 'SEC-006',
        photo_url: 'assets/img/intel/Civil%20War/20260508230624_1.jpg',
        stat_ma: '6.5',
        stat_fog: '5.0',
        stat_fr: '8.0',
        stat_int: '4.5',
        summary: [
            'IRONSIDE commands an armored section observed operating alongside infantry in rural and urban areas. Imagery captured a T-72 variant MBT with dismounted infantry and a PRESS journalist in proximity.',
            'The tank has been observed at multiple locations - rural villages and the main airfield hangar complex. This suggests the armor is used as mobile intimidation and direct fire support for ground clearance operations.',
            'PRESS personnel observed documenting the armored patrol - assessment: state media propaganda coverage.',
            '- EQUIPMENT -',
            'Armor: T-72 variant MBT (1x confirmed, possibly 2-3x in AO1)',
            'Support: Infantry dismounts, technical vehicles',
            'Threat: Main gun (125mm), coaxial MG, reactive armor',
            'Counter: Javelin, TOW, or CAS required for engagement'
        ].join('\n')
    },
    // ============ POI - AO1 LOCATIONS ============
    {
        callsign: 'OBJ DEPOT',
        full_name: 'AO1 - Logistics Depot & Supply Building',
        category: 'poi',
        threat_level: 'MEDIUM',
        status: 'ACTIVE',
        task_directive: 'PHASE 1 TARGET',
        last_loi: 'AO1 - Central Logistics Area',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-001',
        photo_url: 'assets/img/intel/AO1/20260508225135_1.jpg',
        stat_ma: '5.0',
        stat_fog: '6.0',
        stat_fr: '7.0',
        stat_int: '6.5',
        summary: [
            'Aerial recon identified a logistics depot in AO1: multi-story concrete building surrounded by trees with 2-3 KrAZ military trucks parked in front. A smaller support structure visible to the right.',
            'The building appears to serve as a supply distribution center. Truck types are consistent with Eastern European military logistics (KrAZ-260 series).',
            'Destroying or capturing this depot during Phase 1 would degrade Arkerian resupply capability in AO1.',
            'Threat: Unknown garrison strength. Possible small arms defense only.'
        ].join('\n')
    },
    {
        callsign: 'OBJ EAGLE',
        full_name: 'AO1 - Main Airfield / Helicopter Complex',
        category: 'poi',
        threat_level: 'HIGH',
        status: 'ACTIVE',
        task_directive: 'PHASE 1 TARGET',
        last_loi: 'AO1 - Primary Airfield',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-002',
        photo_url: 'assets/img/intel/AO1/20260508225140_1.jpg',
        stat_ma: '7.0',
        stat_fog: '7.5',
        stat_fr: '8.0',
        stat_int: '8.5',
        summary: [
            'PRIMARY Phase 1 target. Aerial recon shows two large aircraft hangars with helicopters (Mi-8/Mi-24 type) visible inside and on the apron. A radar/comms array sits between the hangars. Fuel storage barrels visible.',
            'This is the main rotary-wing aviation asset for Arkerian forces in AO1. Neutralizing this facility eliminates enemy air mobility and CAS capability.',
            'SOAR/VFA-125 strike package assigned for initial suppression. ODA 142 tasked with ground verification post-strike.',
            'Threat: Possible MANPADS, AAA positions in tree line. Helicopter crews may attempt emergency scramble.'
        ].join('\n')
    },
    {
        callsign: 'OBJ TOWER',
        full_name: 'AO1 - Airfield Control Tower & Hangar',
        category: 'poi',
        threat_level: 'MEDIUM',
        status: 'ACTIVE',
        task_directive: 'PHASE 1 TARGET',
        last_loi: 'AO1 - Control Tower Sector',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-003',
        photo_url: 'assets/img/intel/AO1/20260508225146_1.jpg',
        stat_ma: '4.5',
        stat_fog: '6.0',
        stat_fr: '5.5',
        stat_int: '7.0',
        summary: [
            'AO1 airfield control tower - multi-story structure with antenna arrays on roof. Adjacent large hangar visible to the left with helicopter inside.',
            'The tower provides ATC and likely serves as a tactical C2 node for air operations. Destroying comms equipment on the roof would blind Arkerian air coordination.',
            'Assessment: Lightly defended. 5-10 personnel estimated. Secondary strike target after OBJ EAGLE.'
        ].join('\n')
    },
    {
        callsign: 'OBJ FALCON',
        full_name: 'AO1 - Radar & Communications Base',
        category: 'poi',
        threat_level: 'HIGH',
        status: 'ACTIVE',
        task_directive: 'PHASE 1 TARGET',
        last_loi: 'AO1 - Radar Dome Complex',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-004',
        photo_url: 'assets/img/intel/AO1/20260508225151_1.jpg',
        stat_ma: '6.0',
        stat_fog: '8.0',
        stat_fr: '7.5',
        stat_int: '9.0',
        summary: [
            'CRITICAL target. Large radar dome (likely P-18 or similar early warning system), communications tower, and fortified barracks compound. Multiple military vehicles including HMMWVs and APCs visible.',
            'This is the primary EW/SIGINT facility for AO1. The radar dome provides early warning coverage - destroying it will blind Arkerian air defense network.',
            'Compound is walled with T-barriers. Estimated 30-50 personnel. Multiple vehicle types suggest mixed unit composition.',
            'Priority: FIRST STRIKE target for VFA-125 SEAD mission. Must be destroyed before rotary-wing assets enter AO1 airspace.'
        ].join('\n')
    },
    {
        callsign: 'OBJ OVERWATCH',
        full_name: 'AO1 - Forward Airfield Outpost & Tent Hangars',
        category: 'poi',
        threat_level: 'MEDIUM',
        status: 'ACTIVE',
        task_directive: 'RECON',
        last_loi: 'AO1 - Secondary Airstrip',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-005',
        photo_url: 'assets/img/intel/AO1/20260508225201_1.jpg',
        stat_ma: '4.0',
        stat_fog: '5.5',
        stat_fr: '6.0',
        stat_int: '5.0',
        summary: [
            'Secondary airstrip outpost with tent/canvas hangars (likely mobile repair/maintenance facility), guard tower, and armored MRAP-type vehicle.',
            'Partially obscured by fog/haze. Location suggests a dispersal airfield - enemy may relocate aircraft here if primary airfield is struck.',
            'The guard tower provides observation over the runway approach. ODA 142 should recon this location during Phase 1 approach.'
        ].join('\n')
    },
    {
        callsign: 'OBJ GATE',
        full_name: 'AO1 - MSR Checkpoint / Destroyed Vehicles',
        category: 'poi',
        threat_level: 'LOW',
        status: 'ACTIVE',
        task_directive: 'BYPASS',
        last_loi: 'AO1 - Main Supply Route',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-006',
        photo_url: 'assets/img/intel/AO1/20260508225207_1.jpg',
        stat_ma: '3.5',
        stat_fog: '4.0',
        stat_fr: '5.0',
        stat_int: '4.0',
        summary: [
            'MSR checkpoint with heavy military truck traffic (KrAZ-series). Burned/destroyed civilian vehicles visible on roadside - evidence of checkpoint violence against civilians.',
            'Power line infrastructure and fencing suggest this is a controlled access point on the main supply route into AO1.',
            'Recommend bypass via secondary routes. If engagement is necessary, expect light infantry garrison with possible vehicle-mounted weapons.'
        ].join('\n')
    },
    {
        callsign: 'OBJ HANGAR',
        full_name: 'AO1 - Main Vehicle Depot / Armor Storage',
        category: 'poi',
        threat_level: 'HIGH',
        status: 'ACTIVE',
        task_directive: 'PHASE 1 TARGET',
        last_loi: 'AO1 - Industrial Hangar',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-007',
        photo_url: 'assets/img/intel/AO1/20260508225220_1.jpg',
        stat_ma: '6.0',
        stat_fog: '5.5',
        stat_fr: '8.5',
        stat_int: '7.0',
        summary: [
            'Large industrial hangar/warehouse housing armored vehicles. A T-72 MBT is visible inside along with technical vehicles (armed pickup, HMMWV). Single soldier on guard.',
            'This facility is the primary armor staging point in AO1. Destroying it with vehicles inside would eliminate significant enemy combat power.',
            'The corrugated metal structure is vulnerable to precision munitions. CAS or JDAM strike recommended during Phase 1.',
            'Threat: Tank crew may attempt to sortie if given warning. Speed is critical.'
        ].join('\n')
    },
    {
        callsign: 'OBJ CONVOY',
        full_name: 'AO1 - Military Supply Convoy Route',
        category: 'poi',
        threat_level: 'LOW',
        status: 'ACTIVE',
        task_directive: 'INTERDICT',
        last_loi: 'AO1 - Southern MSR',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-008',
        photo_url: 'assets/img/intel/AO1/20260508225239_1.jpg',
        stat_ma: '3.0',
        stat_fog: '4.5',
        stat_fr: '4.0',
        stat_int: '5.5',
        summary: [
            'Recon captured military supply trucks on the southern MSR moving through palm-lined road. Multiple KrAZ cargo trucks carrying supplies between AO1 facilities.',
            'Regular convoy activity on this route suggests it is the primary logistics artery for AO1. Interdicting this route would starve forward positions of ammunition and supplies.',
            'No escort vehicles observed - convoys appear lightly defended. Opportunity for ambush or air interdiction during Phase 1.'
        ].join('\n')
    },
    {
        callsign: 'OBJ BASTION',
        full_name: 'AO1 - Fortified Artillery Position',
        category: 'poi',
        threat_level: 'HIGH',
        status: 'ACTIVE',
        task_directive: 'PHASE 1 TARGET',
        last_loi: 'AO1 - Sandbagged Emplacement',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-009',
        photo_url: 'assets/img/intel/AO1/20260508225244_1.jpg',
        stat_ma: '5.5',
        stat_fog: '6.5',
        stat_fr: '9.0',
        stat_int: '7.5',
        summary: [
            'Heavily fortified position with sandbag walls, camouflage netting, and what appears to be towed artillery or heavy weapons emplacement. Position is roadside with clear fields of fire.',
            'Dense vegetation provides concealment from aerial observation. This position could interdict any ground approach along the MSR.',
            'CRITICAL: Must be suppressed before ground forces advance through AO1. Recommend pre-planned fire mission or precision strike.'
        ].join('\n')
    },
    {
        callsign: 'OBJ CITADEL',
        full_name: 'AO1 - Main Military Compound Overview',
        category: 'poi',
        threat_level: 'HIGH',
        status: 'ACTIVE',
        task_directive: 'PHASE 2 TARGET',
        last_loi: 'AO1 - Central Military Compound',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-010',
        photo_url: 'assets/img/intel/AO1/20260508225304_1.jpg',
        stat_ma: '7.0',
        stat_fog: '7.0',
        stat_fr: '8.0',
        stat_int: '9.0',
        summary: [
            'Wide-angle recon of the AO1 main military compound. Visible: barracks buildings, satellite communications dish, radar dome (OBJ FALCON), supply tents, and perimeter wall.',
            'This is the heart of Arkerian military operations in AO1. The compound houses C2, logistics, communications, and barracks for an estimated 200-400 personnel.',
            'Phase 2 objective: Once OBJ EAGLE and OBJ FALCON are neutralized, ground forces will assault this compound to seize C2 infrastructure and any intelligence materials.',
            'SOVEREIGN may use this compound as a fallback position if the Palace is threatened.'
        ].join('\n')
    },
    // ============ POI - AO2 LOCATIONS ============
    {
        callsign: 'OBJ PALACE',
        full_name: 'AO2 - Fortified HQ Compound (Aerial)',
        category: 'poi',
        threat_level: 'HIGH',
        status: 'ACTIVE',
        task_directive: 'PHASE 3 TARGET',
        last_loi: 'AO2 - Urban Center',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-011',
        photo_url: 'assets/img/intel/AO2/20260508224800_1.jpg',
        stat_ma: '7.5',
        stat_fog: '7.0',
        stat_fr: '9.0',
        stat_int: '8.5',
        summary: [
            'Aerial reconnaissance of fortified compound in AO2 urban center. Multi-story structure with rooftop defensive positions, surrounded by residential buildings. APCs and armored vehicles visible at perimeter.',
            'The compound is in a densely populated area - significant collateral damage risk. ROE restrictions apply: PID required before engagement.',
            'Assessment: This is the AO2 regional headquarters. Likely houses a senior Arkerian commander and tactical C2 for AO2 operations.',
            'Phase 3 objective for 2nd/75th Ranger assault. SOAR will provide insertion capability.'
        ].join('\n')
    },
    {
        callsign: 'OBJ SPIRE',
        full_name: 'AO2 - Communications Hub / Industrial Complex',
        category: 'poi',
        threat_level: 'MEDIUM',
        status: 'ACTIVE',
        task_directive: 'PHASE 2 TARGET',
        last_loi: 'AO2 - Eastern Industrial Sector',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-012',
        photo_url: 'assets/img/intel/AO2/20260508224808_1.jpg',
        stat_ma: '5.0',
        stat_fog: '6.0',
        stat_fr: '6.5',
        stat_int: '7.5',
        summary: [
            'Aerial view of AO2 industrial/communications complex. Multi-story building with rooftop antenna arrays and satellite equipment. Factory smokestack and industrial structures nearby.',
            'This facility links AO2 forces to central command in AO1. Neutralizing comms infrastructure isolates AO2 defenders from SOVEREIGN\'s command authority.',
            'Secondary function: industrial complex may be used for weapons maintenance. Blue-roofed warehouse and walled compound suggest storage facility.'
        ].join('\n')
    },
    {
        callsign: 'OBJ CHECKPOINT',
        full_name: 'AO2 - Fortified Road Checkpoint',
        category: 'poi',
        threat_level: 'MEDIUM',
        status: 'ACTIVE',
        task_directive: 'NEUTRALIZE',
        last_loi: 'AO2 - Northern Approach Road',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-013',
        photo_url: 'assets/img/intel/AO2/20260508224816_1.jpg',
        stat_ma: '4.0',
        stat_fog: '5.0',
        stat_fr: '7.0',
        stat_int: '5.0',
        summary: [
            'Aerial view of fortified checkpoint on dirt road approach to AO2. Two sandbagged bunker positions flanking a barrier gate. Hesco/gabion walls and overhead cover.',
            'This checkpoint controls the northern ground approach to AO2 urban area. Any ground advance from AO1 must pass through or bypass this position.',
            'Estimated garrison: 10-20 personnel with crew-served weapons. Recommend suppression by CAS or mortar fire before ground approach.'
        ].join('\n')
    },
    {
        callsign: 'OBJ INDUSTRIAL',
        full_name: 'AO2 - Industrial Zone Overview',
        category: 'poi',
        threat_level: 'MEDIUM',
        status: 'ACTIVE',
        task_directive: 'RECON',
        last_loi: 'AO2 - Western Industrial Area',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-014',
        photo_url: 'assets/img/intel/AO2/20260508224819_1.jpg',
        stat_ma: '4.5',
        stat_fog: '5.5',
        stat_fr: '5.0',
        stat_int: '6.5',
        summary: [
            'Wide aerial view of AO2 industrial zone. Power plant/factory complex with crane and smokestacks. Communications towers visible on hilltop behind the town.',
            'The industrial zone provides power and potentially weapons manufacturing capability. Civilian residential areas visible interspersed with military-use buildings.',
            'Dirt road network connects to AO2 checkpoint system. Difficult terrain for armored approach from the north.',
            'Recommend ISR coverage during Phase 2 to identify military vs civilian infrastructure.'
        ].join('\n')
    },
    {
        callsign: 'OBJ COASTAL',
        full_name: 'AO2 - Coastal Town & Road Network',
        category: 'poi',
        threat_level: 'LOW',
        status: 'ACTIVE',
        task_directive: 'RECON',
        last_loi: 'AO2 - Coastal Sector',
        grid_ref: 'N/A',
        asset_tag: 'OBJ-015',
        photo_url: 'assets/img/intel/AO2/20260508224828_1.jpg',
        stat_ma: '3.0',
        stat_fog: '4.0',
        stat_fr: '3.5',
        stat_int: '5.0',
        summary: [
            'Aerial view of AO2 coastal town. Civilian residential area with road network leading inland. Relatively flat terrain with sparse vegetation.',
            'No significant military presence observed in this sector. Possible infiltration route for special operations forces approaching AO2 from the coast.',
            'SOAR could use the coastal approach for low-level helicopter insertion, avoiding AO2 radar coverage from the inland hills.'
        ].join('\n')
    },
    // ============ POI - EVIDENCE / WAR CRIMES ============
    {
        callsign: 'EVD: VILLAGE RAID',
        full_name: 'Civil War - Rural Village Raid Documentation',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'EVIDENCE',
        last_loi: 'AO1-AO2 Border - Rural Village',
        grid_ref: 'N/A',
        asset_tag: 'EVD-001',
        photo_url: 'assets/img/intel/Civil%20War/20260508224924_1.jpg',
        stat_ma: '2.0',
        stat_fog: '3.0',
        stat_fr: '1.0',
        stat_int: '8.0',
        summary: [
            'HUMINT imagery from rural village shows Arkerian soldiers approaching a wooden structure. Bodies of civilians visible inside and around the building.',
            'The soldiers are carrying AK-pattern rifles in a patrol formation. The village appears to be a farming community with wooden structures.',
            'This image is part of a series documenting systematic rural "cleanup" operations by CLEANSER\'s unit. Evidence collected for ICC war crimes prosecution.',
            'Location matches SIGINT intercepts referencing "Village 7" clearance operation.'
        ].join('\n')
    },
    {
        callsign: 'EVD: MASSACRE SITE',
        full_name: 'Civil War - Civilian Execution Documentation',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'EVIDENCE',
        last_loi: 'AO1-AO2 Border - Rural Road',
        grid_ref: 'N/A',
        asset_tag: 'EVD-002',
        photo_url: 'assets/img/intel/Civil%20War/20260508224951_1.jpg',
        stat_ma: '2.0',
        stat_fog: '2.5',
        stat_fr: '1.0',
        stat_int: '9.0',
        summary: [
            'Three Arkerian soldiers photographed posing with bodies of executed civilians on a dirt road. Weapons (AK rifles) placed next to victims - standard regime tactic to frame civilians as insurgents.',
            'Victims: Two adult males in civilian clothing. Cause of death: gunshot wounds. Soldiers show no attempt to conceal their actions - indicating systemic impunity.',
            'This image has been transmitted to CENTCOM JAG for war crimes evidence package. Soldiers in photo are priority identification targets.'
        ].join('\n')
    },
    {
        callsign: 'EVD: PRESS TANK',
        full_name: 'Civil War - Embedded Press with Armor',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'EVIDENCE',
        last_loi: 'AO1 - Rural Patrol Route',
        grid_ref: 'N/A',
        asset_tag: 'EVD-003',
        photo_url: 'assets/img/intel/Civil%20War/20260508230624_1.jpg',
        stat_ma: '3.0',
        stat_fog: '2.0',
        stat_fr: '1.0',
        stat_int: '7.5',
        summary: [
            'A journalist wearing PRESS vest observed alongside Arkerian soldiers and a T-72 tank during a rural patrol. The journalist appears to be voluntarily embedded with military forces.',
            'Assessment: State media propagandist OR coerced journalist providing regime coverage. The same individual appears in multiple war crime scene photographs.',
            'ROE: All PRESS-identified individuals classified NON-COMBATANT. Do not engage. If encountered, detain for screening and intelligence debrief.',
            'The journalist\'s camera/footage may contain critical war crimes evidence.'
        ].join('\n')
    },
    {
        callsign: 'EVD: PRESS CHECKPOINT',
        full_name: 'Civil War - Press at Civilian Killing Site',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'EVIDENCE',
        last_loi: 'AO1 - Rural Checkpoint',
        grid_ref: 'N/A',
        asset_tag: 'EVD-004',
        photo_url: 'assets/img/intel/Civil%20War/20260508230641_1.jpg',
        stat_ma: '3.0',
        stat_fog: '2.0',
        stat_fr: '1.0',
        stat_int: '8.0',
        summary: [
            'Same PRESS journalist observed at a rural checkpoint where soldiers are actively killing civilians. Dead bodies visible on the ground. Soldiers carrying AK rifles and a red barrier gate visible.',
            'The journalist did not intervene and appeared to be documenting the event. This confirms the journalist has witnessed multiple atrocities firsthand.',
            'If captured alive, this journalist is a priority intelligence source - eyewitness to systematic killings across multiple locations.'
        ].join('\n')
    },
    {
        callsign: 'EVD: IDAP COURTYARD',
        full_name: 'Proof of IDAP Killing - Courtyard Massacre',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'EVIDENCE',
        last_loi: 'AO2 - Town Courtyard',
        grid_ref: 'N/A',
        asset_tag: 'EVD-005',
        photo_url: 'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508225038_1.jpg',
        stat_ma: '2.0',
        stat_fog: '2.0',
        stat_fr: '1.0',
        stat_int: '9.5',
        summary: [
            'CRITICAL EVIDENCE. Imagery shows Arkerian soldiers executing IDAP humanitarian workers (identifiable by orange/red vests) in a Mediterranean-style courtyard. Multiple bodies visible. IDAP vehicles in background.',
            'At least 6-8 IDAP personnel killed at this location. Soldiers show no regard for the protected status of humanitarian workers.',
            'This image was obtained by CIA assets and forms part of the casus belli evidence package presented to the UN Security Council.',
            'Location: Urban courtyard in AO2 - matches known IDAP mission coordination center.'
        ].join('\n')
    },
    {
        callsign: 'EVD: IDAP CHURCH',
        full_name: 'Proof of IDAP Killing - Church Square Massacre',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'EVIDENCE',
        last_loi: 'AO2 - Church Square',
        grid_ref: 'N/A',
        asset_tag: 'EVD-006',
        photo_url: 'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508225050_1.jpg',
        stat_ma: '2.0',
        stat_fog: '2.0',
        stat_fr: '1.0',
        stat_int: '9.5',
        summary: [
            'Second IDAP massacre site. Soldiers with IDAP worker bodies near a church (crosses visible on building). Orange-vested victims scattered on paved square. Armed soldiers standing guard.',
            'The proximity to a church suggests IDAP workers may have sought sanctuary before being killed. The deliberate targeting of humanitarian workers at a place of worship demonstrates the regime\'s total disregard for international law.',
            'Combined with EVD-005, this confirms the systematic nature of the IDAP massacre - not isolated incidents but coordinated killing operations.'
        ].join('\n')
    },
    {
        callsign: 'EVD: US EXECUTION',
        full_name: 'Proof of IDAP Killing - American National Execution (BEFORE)',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'CASUS BELLI',
        last_loi: 'Unknown - Detention Facility',
        grid_ref: 'N/A',
        asset_tag: 'EVD-007',
        photo_url: 'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508225104_1.jpg',
        stat_ma: '1.0',
        stat_fog: '1.0',
        stat_fr: '1.0',
        stat_int: '10.0',
        summary: [
            'HIGHEST CLASSIFICATION. Four American nationals kneeling in a concrete room with blood on the floor. Hands bound behind their backs. Civilian clothing - IDAP aid workers.',
            'This image was captured moments before the live broadcast execution ordered by SOVEREIGN. The broadcast was transmitted on international television, triggering worldwide condemnation.',
            'Victim identification (CLASSIFIED): 4x US citizens attached to IDAP humanitarian mission.',
            'This single event is the PRIMARY CASUS BELLI for OPERATION ARKERIAN FREEDOM. The Pentagon authorized the 3-phase military response within 72 hours of this broadcast.',
            'The detention facility location remains UNKNOWN - locating it is a priority intelligence requirement. Surviving IDAP hostages may be held at the same facility.'
        ].join('\n')
    },
    {
        callsign: 'EVD: US EXECUTION AFTERMATH',
        full_name: 'Proof of IDAP Killing - American National Execution (AFTER)',
        category: 'poi',
        threat_level: 'UNKNOWN',
        status: 'DOCUMENTED',
        task_directive: 'CASUS BELLI',
        last_loi: 'Unknown - Detention Facility',
        grid_ref: 'N/A',
        asset_tag: 'EVD-008',
        photo_url: 'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508230703_1.jpg',
        stat_ma: '1.0',
        stat_fog: '1.0',
        stat_fr: '1.0',
        stat_int: '10.0',
        summary: [
            'HIGHEST CLASSIFICATION. Aftermath of the execution. Four American nationals dead on the floor of the same concrete room. Massive blood pooling. Bodies collapsed forward.',
            'This image was extracted from the broadcast footage. The execution method and staging were designed for maximum psychological impact on the American public.',
            'Forensic analysis of the broadcast video is ongoing to determine the facility location through background audio, lighting patterns, and construction materials.',
            'All intelligence assets are tasked with locating this facility. Any IDAP survivors held at this location are designated PRECIOUS CARGO - rescue is a Phase 1 priority.'
        ].join('\n')
    }
        ];

        console.log('[DOSSIER SEED] Creating', seeds.length, 'dossiers...');
        let ok = 0, fail = 0;
        for (const s of seeds) {
            try {
                const cr = await fetch(DOSSIER_API + '?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password: 'S2', fields: s })
                });
                const ct = await cr.text();
                const cd = JSON.parse(ct);
                if (cd.success) { ok++; } else { fail++; console.warn('[DOSSIER SEED] Failed:', s.callsign, cd.error); }
            } catch (ce) {
                fail++;
                console.error('[DOSSIER SEED] Error creating', s.callsign, ':', ce);
            }
        }

        console.log('[DOSSIER SEED] Done:', ok, 'ok,', fail, 'failed');
        await loadDossiers();
    } catch (e) {
        console.error('[DOSSIER SEED] Fatal error:', e);
    }
}

// ---- Photo Lightbox ----
let lightboxGallery = [];
let lightboxIndex = 0;

// Image folder mapping for gallery browsing
const INTEL_IMAGE_FOLDERS = {
    'HVT': [
        'assets/img/intel/HVT/20260508231651_1.jpg',
        'assets/img/intel/HVT/20260508225022_1.jpg',
        'assets/img/intel/HVT/20260508224440_1.jpg',
        'assets/img/intel/HVT/20260508224743_1.jpg',
        'assets/img/intel/HVT/20260508224902_1.jpg',
        'assets/img/intel/HVT/20260508224910_1.jpg'
    ],
    'AO1': [
        'assets/img/intel/AO1/20260508225135_1.jpg',
        'assets/img/intel/AO1/20260508225140_1.jpg',
        'assets/img/intel/AO1/20260508225146_1.jpg',
        'assets/img/intel/AO1/20260508225151_1.jpg',
        'assets/img/intel/AO1/20260508225201_1.jpg',
        'assets/img/intel/AO1/20260508225207_1.jpg',
        'assets/img/intel/AO1/20260508225220_1.jpg',
        'assets/img/intel/AO1/20260508225239_1.jpg',
        'assets/img/intel/AO1/20260508225244_1.jpg',
        'assets/img/intel/AO1/20260508225304_1.jpg'
    ],
    'AO2': [
        'assets/img/intel/AO2/20260508224800_1.jpg',
        'assets/img/intel/AO2/20260508224808_1.jpg',
        'assets/img/intel/AO2/20260508224816_1.jpg',
        'assets/img/intel/AO2/20260508224819_1.jpg',
        'assets/img/intel/AO2/20260508224828_1.jpg'
    ],
    'Civil War': [
        'assets/img/intel/Civil%20War/20260508224924_1.jpg',
        'assets/img/intel/Civil%20War/20260508224931_1.jpg',
        'assets/img/intel/Civil%20War/20260508224951_1.jpg',
        'assets/img/intel/Civil%20War/20260508230624_1.jpg',
        'assets/img/intel/Civil%20War/20260508230641_1.jpg'
    ],
    'Proof of the IDAP Killing': [
        'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508225038_1.jpg',
        'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508225050_1.jpg',
        'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508225104_1.jpg',
        'assets/img/intel/Proof%20of%20the%20IDAP%20Killing/20260508230703_1.jpg'
    ]
};

function getFolderForImage(url) {
    if (!url) return null;
    const decoded = decodeURIComponent(url);
    for (const [folder, images] of Object.entries(INTEL_IMAGE_FOLDERS)) {
        const decodedImages = images.map(i => decodeURIComponent(i));
        if (decodedImages.some(i => decoded.includes(i.split('/').pop()))) return folder;
    }
    return null;
}

function openIntelLightbox(dossier) {
    const folder = getFolderForImage(dossier.photo_url);
    if (folder && INTEL_IMAGE_FOLDERS[folder]) {
        lightboxGallery = INTEL_IMAGE_FOLDERS[folder];
        const decoded = decodeURIComponent(dossier.photo_url);
        lightboxIndex = lightboxGallery.findIndex(i => decoded.includes(decodeURIComponent(i).split('/').pop()));
        if (lightboxIndex < 0) lightboxIndex = 0;
    } else {
        lightboxGallery = [dossier.photo_url];
        lightboxIndex = 0;
    }
    const label = document.getElementById('lightboxLabel');
    label.textContent = (dossier.callsign || '') + ' // ' + (folder || 'INTEL');
    updateLightboxImage();
    document.getElementById('intelLightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function openIntelLightboxFromUrl(url, label) {
    const folder = getFolderForImage(url);
    if (folder && INTEL_IMAGE_FOLDERS[folder]) {
        lightboxGallery = INTEL_IMAGE_FOLDERS[folder];
        const decoded = decodeURIComponent(url);
        lightboxIndex = lightboxGallery.findIndex(i => decoded.includes(decodeURIComponent(i).split('/').pop()));
        if (lightboxIndex < 0) lightboxIndex = 0;
    } else {
        lightboxGallery = [url];
        lightboxIndex = 0;
    }
    document.getElementById('lightboxLabel').textContent = label || 'INTEL EVIDENCE';
    updateLightboxImage();
    document.getElementById('intelLightbox').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeIntelLightbox() {
    document.getElementById('intelLightbox').classList.remove('show');
    document.body.style.overflow = '';
}

function navigateLightbox(dir) {
    lightboxIndex += dir;
    if (lightboxIndex < 0) lightboxIndex = lightboxGallery.length - 1;
    if (lightboxIndex >= lightboxGallery.length) lightboxIndex = 0;
    updateLightboxImage();
}

function updateLightboxImage() {
    const img = document.getElementById('lightboxImg');
    img.style.opacity = '0';
    setTimeout(() => {
        img.src = lightboxGallery[lightboxIndex];
        img.onload = () => { img.style.opacity = '1'; };
    }, 100);
    const counter = document.getElementById('lightboxCounter');
    counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxGallery.length;
    document.getElementById('lightboxPrev').style.display = lightboxGallery.length > 1 ? '' : 'none';
    document.getElementById('lightboxNext').style.display = lightboxGallery.length > 1 ? '' : 'none';
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('intelLightbox');
    if (!lb || !lb.classList.contains('show')) return;
    if (e.key === 'Escape') closeIntelLightbox();
    if (e.key === 'ArrowLeft') navigateLightbox(-1);
    if (e.key === 'ArrowRight') navigateLightbox(1);
});

// ---- Init ----
document.addEventListener('DOMContentLoaded', async function () {
    await seedDossiersIfEmpty();
    await loadDossiers();
    loadDossierBrief();
    loadDossierOps();
    loadDossierIntelStats();
});
