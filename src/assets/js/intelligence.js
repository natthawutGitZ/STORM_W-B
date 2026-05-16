const API='api/intelligence.php';
let editState={};

// ---- S2 AUTHORIZATION GATE ----
let _authPass = sessionStorage.getItem('s2_auth') || '';
let _authCallback = null;

function getAuthPass() { return _authPass; }
function isAuthed() { return !!_authPass; }

function requireAuth(callback) {
    if (isAuthed()) { callback(); return; }
    _authCallback = callback;
    document.getElementById('authPassInput').value = '';
    document.getElementById('authError').style.display = 'none';
    document.getElementById('authModal').classList.add('show');
    setTimeout(() => document.getElementById('authPassInput').focus(), 200);
}

function submitAuth() {
    const pass = document.getElementById('authPassInput').value;
    if (!pass) return;
    // Validate by making a test request to the API
    fetch(API + '?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ password: pass, type: 'operations', fields: {} })
    }).then(r => {
        if (r.status === 403) {
            document.getElementById('authError').style.display = 'block';
            document.getElementById('authPassInput').value = '';
            document.getElementById('authPassInput').style.borderColor = '#ff4c66';
            setTimeout(() => { document.getElementById('authPassInput').style.borderColor = '#333'; }, 1500);
            return;
        }
        // Auth success — store password
        _authPass = pass;
        sessionStorage.setItem('s2_auth', pass);
        closeModal('authModal');
        if (_authCallback) { _authCallback(); _authCallback = null; }
    }).catch(() => {
        document.getElementById('authError').style.display = 'block';
    });
}

// Enter key support for auth modal
document.addEventListener('DOMContentLoaded', function() {
    var inp = document.getElementById('authPassInput');
    if (inp) inp.addEventListener('keydown', function(e) { if (e.key === 'Enter') submitAuth(); });

    // Auto-collapse sidebar on mobile
    if (window.innerWidth <= 768) {
        document.body.classList.add('sidebar-collapsed');
    }

    // Close sidebar when nav-item is clicked on mobile
    document.querySelectorAll('.nav-item').forEach(function(item) {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                document.body.classList.add('sidebar-collapsed');
            }
        });
    });
});

// Clock
function updateClock(){
  const d=new Date(),z=n=>String(n).padStart(2,'0');
  const el=document.getElementById('clock');
  if(el)el.innerHTML=z(d.getHours())+':'+z(d.getMinutes())+':'+z(d.getSeconds())+' ICT';
}
setInterval(updateClock,1000);updateClock();

// UI Sound Effects (Web Audio API)
const AudioCtx = window.AudioContext || window.webkitAudioContext;
let audioCtx = null;
function playClick(){
  if(!audioCtx) audioCtx = new AudioCtx();
  const o=audioCtx.createOscillator(),g=audioCtx.createGain();
  o.connect(g);g.connect(audioCtx.destination);
  o.type='sine';o.frequency.setValueAtTime(1200,audioCtx.currentTime);
  g.gain.setValueAtTime(0.08,audioCtx.currentTime);
  g.gain.exponentialRampToValueAtTime(0.001,audioCtx.currentTime+0.08);
  o.start();o.stop(audioCtx.currentTime+0.08);
}
function playHover(){
  if(!audioCtx) audioCtx = new AudioCtx();
  const o=audioCtx.createOscillator(),g=audioCtx.createGain();
  o.connect(g);g.connect(audioCtx.destination);
  o.type='sine';o.frequency.setValueAtTime(800,audioCtx.currentTime);
  g.gain.setValueAtTime(0.03,audioCtx.currentTime);
  g.gain.exponentialRampToValueAtTime(0.001,audioCtx.currentTime+0.04);
  o.start();o.stop(audioCtx.currentTime+0.04);
}
document.querySelectorAll('.tab-btn,.nav-item,.map-tool-btn,.btn-s2,.layers-action-btn').forEach(el=>{
  el.addEventListener('click',playClick);
  el.addEventListener('mouseenter',playHover);
});

// Tabs (with persistence)
function switchTab(tab){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));
  document.querySelectorAll('.nav-item').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));
  document.querySelectorAll('.tab-content').forEach(c=>c.classList.toggle('active',c.id==='tab-'+tab));
  localStorage.setItem('intel_active_tab',tab);
}
document.querySelectorAll('.tab-btn,.nav-item').forEach(btn=>{
  btn.addEventListener('click',()=>switchTab(btn.dataset.tab));
});
// Restore tab on page load — URL param takes priority
(function(){
  const urlParams = new URLSearchParams(window.location.search);
  const urlTab = urlParams.get('tab');
  if (urlTab) { switchTab(urlTab); return; }
  const saved=localStorage.getItem('intel_active_tab');
  if(saved) switchTab(saved);
})();

// PDF Viewer Logic (PDF.js)
let pdfDoc = null,
    pageNum = 1,
    pageRendering = false,
    pageNumPending = null,
    pdfCanvas = null,
    pdfCtx = null;

// The workerSrc property shall be specified.
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

function renderPage(num, direction) {
    pageRendering = true;
    
    // Setup animation
    if (pdfCanvas && direction) {
        pdfCanvas.classList.remove('pdf-anim-next', 'pdf-anim-prev');
        void pdfCanvas.offsetWidth; // Trigger reflow
        pdfCanvas.classList.add(direction === 'next' ? 'pdf-anim-next' : 'pdf-anim-prev');
    }

    // Fetch page
    pdfDoc.getPage(num).then(function(page) {
        const container = document.getElementById('pdfCanvasWrap');
        if (!container) return;

        const containerW = container.clientWidth - 20;  // slight padding
        const containerH = container.clientHeight - 20;

        // Calculate scale to fit BOTH width and height (no scrollbar)
        const unscaledViewport = page.getViewport({ scale: 1 });
        const scaleW = containerW / unscaledViewport.width;
        const scaleH = containerH / unscaledViewport.height;
        const fitScale = Math.min(scaleW, scaleH);

        const viewport = page.getViewport({ scale: fitScale });

        pdfCanvas.width = viewport.width;
        pdfCanvas.height = viewport.height;

        const renderContext = {
            canvasContext: pdfCtx,
            viewport: viewport
        };
        const renderTask = page.render(renderContext);

        renderTask.promise.then(function() {
            pageRendering = false;
            if (pageNumPending !== null) {
                const pendingDir = pageNumPending > num ? 'next' : 'prev';
                const pending = pageNumPending;
                pageNumPending = null;
                renderPage(pending, pendingDir);
            }
        });
    });

    // Update page counters
    document.getElementById('pdfPageNum').textContent = num;
    document.getElementById('pdfPrev').disabled = num <= 1;
    document.getElementById('pdfNext').disabled = num >= pdfDoc.numPages;
}

function queueRenderPage(num, direction) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num, direction);
    }
}

function prevPage() {
    if (!pdfDoc || pageNum <= 1) return;
    pageNum--;
    queueRenderPage(pageNum, 'prev');
}

function nextPage() {
    if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
    pageNum++;
    queueRenderPage(pageNum, 'next');
}

// Load the PDF once DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    pdfCanvas = document.getElementById('pdfRenderCanvas');
    if (pdfCanvas) {
        pdfCtx = pdfCanvas.getContext('2d');
        const url = 'assets/Role/Joint OPS plan [ Edit-t ].pdf';
        
        pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            document.getElementById('pdfPageCount').textContent = pdfDoc.numPages;
            renderPage(pageNum, null);
        }).catch(function(err) {
            console.error('Error loading PDF:', err);
        });
    }

    // Re-render on window resize so it always fits
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (pdfDoc) renderPage(pageNum, null);
        }, 200);
    });

    // Arrow key navigation (Left/Right)
    document.addEventListener('keydown', (e) => {
        // Only when DOCUMENT tab is active
        const docTab = document.getElementById('tab-document');
        if (!docTab || !docTab.classList.contains('active')) return;
        // Don't capture if user is typing in an input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            nextPage();
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            prevPage();
        }
    });

    // Mouse scroll navigation (scroll up = prev, scroll down = next)
    const canvasWrap = document.getElementById('pdfCanvasWrap');
    if (canvasWrap) {
        let scrollCooldown = false;
        canvasWrap.addEventListener('wheel', (e) => {
            e.preventDefault();
            if (scrollCooldown) return;
            scrollCooldown = true;
            
            if (e.deltaY > 0) {
                nextPage();
            } else if (e.deltaY < 0) {
                prevPage();
            }
            
            // Cooldown to prevent rapid-fire page changes
            setTimeout(() => { scrollCooldown = false; }, 350);
        }, { passive: false });

        // Touch swipe support for mobile (swipe left = next, swipe right = prev)
        let touchStartX = 0;
        let touchStartY = 0;
        canvasWrap.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        canvasWrap.addEventListener('touchend', (e) => {
            const deltaX = e.changedTouches[0].screenX - touchStartX;
            const deltaY = e.changedTouches[0].screenY - touchStartY;
            // Only trigger if horizontal swipe is dominant and > 50px
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                if (deltaX < 0) {
                    nextPage(); // swipe left = next
                } else {
                    prevPage(); // swipe right = prev
                }
            }
        }, { passive: true });
    }
});

function togglePdfFullscreen() {
    const container = document.getElementById('pdfViewerContainer');
    if (!document.fullscreenElement) {
        if (container.requestFullscreen) {
            container.requestFullscreen();
        } else if (container.webkitRequestFullscreen) { /* Safari */
            container.webkitRequestFullscreen();
        } else if (container.msRequestFullscreen) { /* IE11 */
            container.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) { /* Safari */
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { /* IE11 */
            document.msExitFullscreen();
        }
    }
}

let pdfLangEn = true;
function togglePdfLanguage() {
    pdfLangEn = !pdfLangEn;
    document.getElementById('btnPdfLang').innerHTML = pdfLangEn ? '<i class="fas fa-language"></i> EN' : '<i class="fas fa-language"></i> TH';
    // If you had a Thai PDF, you could load it here:
    // const newUrl = pdfLangEn ? 'assets/Role/Joint OPS plan [ Edit-t ].pdf' : 'assets/Role/Joint OPS plan TH.pdf';
    // pdfjsLib.getDocument(newUrl).promise.then(pdf => { pdfDoc = pdf; renderPage(1); });
}

function closeModal(id){document.getElementById(id).classList.remove('show');}

// Data loading
async function loadAll(){await Promise.all([loadOps(),loadIntel(),loadBrief()]);}

async function loadOps(){
  const r=await fetch(API+'?action=list&type=operations'),d=await r.json();
  if(!d.success)return;
  document.getElementById('opCount').textContent=d.data.length;
  const sortiesList=document.getElementById('briefSortiesList');
  if(!d.data.length){
    sortiesList.innerHTML='<div style="color:#666;font-family:var(--mono);font-size:.75rem;padding:12px;">NO ACTIVE SORTIES</div>';
    return;
  }
  sortiesList.innerHTML=d.data.map(o=>`
    <div class="brief-sortie-row" ondblclick="openEditModal('operations',${o.id})">
      <div class="brief-sortie-name"><i class="fas fa-chevron-right"></i> ${esc(o.codename)}</div>
      <div class="brief-sortie-right">
        <span class="brief-sortie-status">${esc(o.status)}</span>
        <button class="brief-sortie-btn" onclick="event.stopPropagation();openEditModal('operations',${o.id})">OPEN SORTIE <i class="fas fa-arrow-right"></i></button>
      </div>
    </div>
  `).join('');
}

async function loadIntel(){
  const r=await fetch(API+'?action=list&type=reports'),d=await r.json();
  if(!d.success)return;
  document.getElementById('intelCount').textContent=d.data.length;
  const el2=document.getElementById('intelCount2');if(el2)el2.textContent=d.data.length;
  // Intel cards for brief
  const cardsEl=document.getElementById('briefIntelCards');
  if(cardsEl){
    cardsEl.innerHTML=d.data.length?d.data.slice(0,6).map(o=>`
      <div class="brief-intel-card" ondblclick="openEditModal('reports',${o.id})">
        <div class="brief-intel-card-id">ID: ${String(o.id).padStart(7,'0')}</div>
        <div class="brief-intel-card-title">${esc(o.title)}</div>
        <div class="brief-intel-card-desc">${esc(o.content||'')}</div>
        <div class="brief-intel-card-class">${esc(o.classification)}</div>
      </div>
    `).join(''):'<div style="color:#666;font-family:var(--mono);font-size:.75rem;">NO INTELLIGENCE REPORTS</div>';
  }
  // Full intel list for INTEL tab
  const html=d.data.length?d.data.map(o=>renderItem('reports',o,`
    <div class="item-row"><span class="item-name">${esc(o.title)}</span><span class="tag tag-${o.classification.replace(/\s/g,'').toLowerCase()}">${o.classification}</span></div>
    <div class="item-desc">${esc(o.content||'')}</div>
    <div class="item-meta"><span><i class="fas fa-satellite-dish"></i>${esc(o.source)}</span><span><i class="fas fa-clock"></i>${timeAgo(o.created_at)}</span></div>
  `)).join(''):'<div class="empty">NO INTELLIGENCE REPORTS</div>';
  const f=document.getElementById('intelListFull');if(f)f.innerHTML=html;
}

function renderItem(type,o,inner){
  const actions=`<div class="item-actions"><button class="btn-icon" onclick="event.stopPropagation();openEditModal('${type}',${o.id})"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteItem('${type}',${o.id})"><i class="fas fa-trash"></i></button></div>`;
  return `<div class="item" ondblclick="openEditModal('${type}',${o.id})">${inner}${actions}</div>`;
}

// ---- OPERATION BRIEF (OPORD) ----
async function loadBrief(){
  try{
    const r=await fetch(API+'?action=get_brief');
    const d=await r.json();
    if(d.success && d.data){
      if(d.data.op_name) document.getElementById('briefOpName').textContent=d.data.op_name;
      if(d.data.classification) document.getElementById('briefClassTag').textContent=d.data.classification;
      if(d.data.status) document.getElementById('briefStatus').textContent=d.data.status;
      if(d.data.ao_location) document.getElementById('briefAO').textContent=d.data.ao_location;
      if(d.data.team) document.getElementById('briefTeam').textContent=d.data.team;
      if(d.data.start_date) document.getElementById('briefStartDate').textContent=d.data.start_date;
      if(d.data.opord) document.getElementById('briefOpordContent').innerHTML='<p>'+d.data.opord.replace(/\n/g,'</p><p>')+'</p>';
      if(d.data.doc_title) document.getElementById('briefDocTitle').textContent=d.data.doc_title;
    }
  }catch(e){}
}

function toggleBriefEdit(){
  requireAuth(function() {
    const editor=document.getElementById('briefOpordEditor');
    const content=document.getElementById('briefOpordContent');
    if(editor.style.display==='none'){
      editor.style.display='block';
      content.style.display='none';
      document.getElementById('briefOpordText').value=content.innerText.trim()==='No operation order loaded. Click EDIT OPORD to add briefing content.'?'':content.innerText;
    }else{
      cancelBriefEdit();
    }
  });
}

function cancelBriefEdit(){
  document.getElementById('briefOpordEditor').style.display='none';
  document.getElementById('briefOpordContent').style.display='block';
}

async function saveBrief(){
  const opord=document.getElementById('briefOpordText').value;
  try{
    await fetch(API+'?action=save_brief',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({opord,password:getAuthPass()})});
    document.getElementById('briefOpordContent').innerHTML=opord?'<p>'+esc(opord).replace(/\n/g,'</p><p>')+'</p>':'<p class="brief-placeholder">No operation order loaded.</p>';
    cancelBriefEdit();
  }catch(e){stormAlert('Failed to save', 'error');}
}

function exportBrief(){
  const data={
    doc_title:document.getElementById('briefDocTitle').textContent,
    op_name:document.getElementById('briefOpName').textContent,
    classification:document.getElementById('briefClassTag').textContent,
    status:document.getElementById('briefStatus').textContent,
    ao_location:document.getElementById('briefAO').textContent,
    team:document.getElementById('briefTeam').textContent,
    start_date:document.getElementById('briefStartDate').textContent,
    opord:document.getElementById('briefOpordContent').innerText
  };
  const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
  const a=document.createElement('a');a.href=URL.createObjectURL(blob);
  a.download='opord_'+Date.now()+'.json';a.click();
}

// CRUD forms
const FORMS={
  operations:[{k:'codename',l:'Codename',t:'text'},{k:'status',l:'Status',t:'select',opts:['ACTIVE','COMPLETED','FAILED','PENDING']},{k:'priority',l:'Priority',t:'select',opts:['CRITICAL','HIGH','MEDIUM','LOW']},{k:'brief',l:'Brief',t:'textarea'},{k:'commander',l:'Commander',t:'text'}],
  reports:[{k:'title',l:'Title',t:'text'},{k:'classification',l:'Classification',t:'select',opts:['TOP SECRET','SECRET','CONFIDENTIAL','UNCLASSIFIED']},{k:'content',l:'Content',t:'textarea'},{k:'source',l:'Source',t:'select',opts:['HUMINT','SIGINT','CYBER','OSINT','GEOINT']}]
};

function buildForm(type,data){
  return FORMS[type].map(f=>{
    const v=data?esc(String(data[f.k]||'')):'';
    if(f.t==='select')return `<div class="field"><label>${f.l}</label><select id="f_${f.k}">${f.opts.map(o=>`<option value="${o}"${v===o?' selected':''}>${o}</option>`).join('')}</select></div>`;
    if(f.t==='textarea')return `<div class="field"><label>${f.l}</label><textarea id="f_${f.k}">${v}</textarea></div>`;
    return `<div class="field"><label>${f.l}</label><input type="${f.t}" id="f_${f.k}" value="${v}"></div>`;
  }).join('');
}

function openCreateModal(type){
  requireAuth(function() {
    editState={type,id:null};
    document.getElementById('editTitle').textContent='CREATE — '+type.toUpperCase();
    document.getElementById('editBody').innerHTML=buildForm(type,null);
    document.getElementById('editSave').onclick=()=>saveItem();
    document.getElementById('editModal').classList.add('show');
  });
}

async function openEditModal(type,id){
  requireAuth(async function() {
    const r=await fetch(API+'?action=list&type='+type),d=await r.json();
    const item=d.data.find(x=>x.id==id);if(!item)return;
    editState={type,id};
    document.getElementById('editTitle').textContent='EDIT — '+type.toUpperCase();
    document.getElementById('editBody').innerHTML=buildForm(type,item);
    document.getElementById('editSave').onclick=()=>saveItem();
    document.getElementById('editModal').classList.add('show');
  });
}

async function saveItem(){
  const fields={};FORMS[editState.type].forEach(f=>{fields[f.k]=document.getElementById('f_'+f.k).value;});
  const body={password:getAuthPass(),type:editState.type,fields};
  const action=editState.id?'update':'create';
  if(editState.id)body.id=editState.id;
  const r=await fetch(API+'?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const d=await r.json();
  if(d.success){closeModal('editModal');loadAll();}else{ if(d.error&&d.error.includes('ACCESS DENIED')){_authPass='';sessionStorage.removeItem('s2_auth');stormAlert('Session expired. Please re-authenticate.', 'error');}else stormAlert(d.error, 'error');}
}

async function deleteItem(type,id){
  requireAuth(async function() {
    if(!await stormConfirm('CONFIRM DELETE — This action cannot be undone'))return;
    const r=await fetch(API+'?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({password:getAuthPass(),type,id})});
    const d=await r.json();if(d.success)loadAll();else{ if(d.error&&d.error.includes('ACCESS DENIED')){_authPass='';sessionStorage.removeItem('s2_auth');stormAlert('Session expired.', 'error');}else stormAlert(d.error, 'error');}
  });
}

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function timeAgo(dt){
  const diff=Math.floor((Date.now()-new Date(dt).getTime())/1000);
  if(diff<60)return diff+'s ago';if(diff<3600)return Math.floor(diff/60)+'m ago';
  if(diff<86400)return Math.floor(diff/3600)+'h ago';return Math.floor(diff/86400)+'d ago';
}

loadAll();

// ==========================================
// INTELLIGENCE MAP & MISSION PLANNING (ARMA3TACMAP PORT)
// ==========================================
let mapInst = null;
let drawLayer = null;
let currentMap = 'colombia';
let gridLayer = null;
let drawPollTimer = null;
let currentArea = null;

// ---- REAL-TIME MULTIPLAYER ----
const RT_COLORS = ['#00ff41','#00e5ff','#ff4c66','#ffaa00','#aa00ff','#ffff00','#ff6600','#00cc44','#ff00aa','#66ccff'];
let rtUserId = localStorage.getItem('map_user_id');
let rtDisplayName = localStorage.getItem('map_display_name') || '';
let rtUserColor = localStorage.getItem('map_user_color') || '';
let rtCursorPos = null;
let rtPresenceTimer = null;
let rtCursorLayer = null;
let rtCursorMarkers = {};
let rtHeartbeatTimer = null;

if (!rtUserId) {
    rtUserId = 'user_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
    localStorage.setItem('map_user_id', rtUserId);
}
if (!rtDisplayName) {
    rtDisplayName = 'Operator-' + rtUserId.substr(-4).toUpperCase();
    localStorage.setItem('map_display_name', rtDisplayName);
}
if (!rtUserColor) {
    rtUserColor = RT_COLORS[Math.floor(Math.random() * RT_COLORS.length)];
    localStorage.setItem('map_user_color', rtUserColor);
}

// ---- PLANOPS MAP CONFIG ----
// Uses MGRS_CRS from mapUtils.js (loaded from PLANOPS CDN)
const MAP_CONFIG = {
    tileUrl: 'https://atlas.plan-ops.fr/data/1/maps/118/118/{z}/{x}/{y}.webp',
    tileSize: 323, maxZoom: 6, minZoom: 0, defaultZoom: 2,
    factorX: 0.01575, factorY: 0.01575, worldSize: 20480, center: [10250, 10250]
};

const ArmaCRS = MGRS_CRS(MAP_CONFIG.factorX, MAP_CONFIG.factorY, MAP_CONFIG.tileSize);

function armaToLatLng(x, y) { return [y, x]; }
function latLngToArma(latlng) { return { x: Math.round(latlng.lng), y: Math.round(latlng.lat) }; }

// ---- CUSTOM UI STATE ----
let currentDrawAction = null;
let clickPosition = null;

function setTool(toolName, btnEl) {
    currentDrawAction = toolName;
    document.querySelectorAll('.map-tool-btn').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    
    // Clear pending drawings
    if (currentLine) { currentLine.remove(); currentLine = null; }
    if (currentMeasure) { currentMeasure.remove(); currentMeasure = null; }
    if (currentMission) { currentMission.remove(); currentMission = null; }
    if (currentArea) { currentArea.remove(); currentArea = null; }
    missionSelection = null;
    
    if (toolName === 'select') { mapInst.dragging.disable(); } 
    else { mapInst.dragging.enable(); }
    
    document.getElementById('intelMap').style.cursor = 
        (toolName === 'pan') ? '' : 'crosshair';
    
    var colorPicker = document.getElementById('toolbarColorPicker');
    if (colorPicker) colorPicker.style.display = (toolName === 'line' || toolName === 'measure') ? 'flex' : 'none';
    
    if (toolName === 'mission') {
        missionSelection = null;
        openMapModal('modalMissionSelector');
    }
}

// ---- ARMA3TACMAP PORTED LOGIC ----
let allMarkers = {};
let currentLine = null;
let currentMeasure = null;
let currentMission = null;
let missionSelection = null;
let modalMarkerId = null;
let modalMarkerData = null;

const backend = {
    addMarker: function(layerId, markerData) {
        markerData.id = Date.now().toString() + Math.floor(Math.random()*1000);
        fetch('api/mission_plan.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'save_marker', map:currentMap, user_id:'S2', data: markerData }) });
        addOrUpdateMarker(mapInst, allMarkers, { id: markerData.id, data: markerData }, true, backend, {}, { group: drawLayer });
    },
    removeMarker: function(markerId) {
        fetch('api/mission_plan.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_marker', map:currentMap, password:'S2', id: markerId }) });
        if (allMarkers[markerId]) {
            if(allMarkers[markerId].labels) allMarkers[markerId].labels.forEach(l=>l.remove());
            drawLayer.removeLayer(allMarkers[markerId]);
            delete allMarkers[markerId];
        }
    },
    updateMarkerToLayer: function(markerId, layerId, markerData) {
        fetch('api/mission_plan.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'save_marker', map:currentMap, user_id:'S2', data: markerData }) });
        addOrUpdateMarker(mapInst, allMarkers, { id: markerId, data: markerData }, true, backend, {}, { group: drawLayer });
    },
    moveMarker: function(markerId, markerData) {
        this.updateMarkerToLayer(markerId, null, markerData);
    }
};

function colorToCss(color) {
    const map = {
        "colorblack": "#000000", "colorgrey": "#7f7f7f", "colorred": "#e50000",
        "colorbrown": "#7f3f00", "colororange": "#d86600", "coloryellow": "#d8d800",
        "colorkhaki": "#7f9966", "colorgreen": "#00cc00", "colorblue": "#0000ff",
        "colorpink": "#ff4c66", "colorwhite": "#ffffff", "colorunknown": "#b29900",
        "colorblufor": "#004c99", "coloropfor": "#7f0000", "colorindependent": "#007f00",
        "colorcivilian": "#66007f"
    };
    if (color && color.startsWith('#')) return color;
    return map[(color || '').toLowerCase()] || '#000000';
}

function posToPoints(pos) {
    var points = [];
    for (var i = 0; i < pos.length; i += 2) { points.push([pos[i], pos[i + 1]]); }
    return points;
}

function computeDistanceAndShowTooltip(map, marker, posList, isEdit, markerId, markerData) {
    function toLL(p) { return p.lat !== undefined ? p : L.latLng(p[0], p[1]); }
    var p0 = toLL(posList[0]);
    var p1 = toLL(posList[posList.length - 1]);
    var distance = map.distance(p0, p1).toFixed();
    var dx = p1.lat - p0.lat;
    var dy = p1.lng - p0.lng;
    var heading = Math.round(Math.atan2(dy, dx) * 3200 / Math.PI);
    if (heading < 0) heading = 6400 + heading;
    var distNum = Number(distance);
    var miles = (distNum * 0.000621371).toFixed(3);
    var formatedDistance = '<div class="measure-line"><i class="fas fa-arrows-left-right"></i> <span>' + distNum.toLocaleString() + ' m</span></div>' +
        '<div class="measure-line"><i class="fas fa-arrows-left-right"></i> <span>' + miles + ' mil</span></div>';
    if (marker.getTooltip()) {
        marker.unbindTooltip();
    }
    marker.bindTooltip(formatedDistance, { direction: 'center', permanent: true, interactive: isEdit, opacity: 1, className: 'measure-tooltip' });
}

function missionLabels(marker, result, target) {
    if (!marker.labels) {
        marker.labels = result.labels.map(function (label, i) {
            return L.marker(result.labelsPoints[i], {
                icon: new L.DivIcon({ className: 'mission-text', html: '<div style="background:rgba(255,255,255,0.7);padding:2px;border:1px solid #000;color:#000;border-radius:4px;font-size:10px;font-weight:bold;">'+label+'</div>', iconAnchor: [8, 8], iconSize: [16, 16] }),
                interactive: marker.options.interactive
            }).addTo(target).on('click', function (ev) {
                ev.sourceTarget = marker; ev.target = marker;
                marker.fire('click', ev);
            });
        });
        marker.on('remove', function (ev) { ev.target.labels.forEach(function (l) { l.remove(); }); });
    } else {
        for (var i = 0; i < result.labelsPoints.length; ++i) {
            marker.labels[i].setLatLng(result.labelsPoints[i]);
        }
    }
}

function generateMission(mission, points, size) {
    var def = typeof MilMissions !== 'undefined' ? MilMissions.missions[mission] : null;
    if (def) {
        var result = { labels: def.labels};
        var sizeMeters = 1000;
        switch (String(size)) { case '12': sizeMeters = 25; break; case '13': sizeMeters = 50; break; case '14': sizeMeters = 250; break; }
        if (def.points > 1 && points.length < 2) {
            result.lines = points;
            if (def.labels) result.labelsPoints = [points[0], points[0]];
            return result;
        }
        if (def.points == 4 && points.length < 4) points = MilMissions.complete4Points(points, sizeMeters);
        result.lines = def.generate(points, sizeMeters);
        if (def.labels) result.labelsPoints = def.generateLabels(points, sizeMeters, result.lines);
        return result;
    }
    return {lines:[]};
}

function updateMarkerHandler(e, map, backend) {
    var marker = e.target;
    if (!marker.options.interactive) return;
    modalMarkerId = marker.options.markerId;
    modalMarkerData = marker.options.markerData;
    
    if (modalMarkerData.type === 'mil') {
        document.getElementById('natoDeleteBtn').style.display = 'block';
        document.getElementById('natoInsertBtn').innerText = 'Update';
        // ---- Pre-populate NATO form from existing marker data ----
        var sidc = modalMarkerData.symbol || '';
        if (sidc.length >= 20) {
            // Parse SIDC: 10(ver) 0(sid1) identity(1) symbolSet(2) status(1) hqtf(1) echelon(2) entity(6) mod1(2) mod2(2)
            var identity  = sidc.charAt(3);
            var symbolSet = sidc.substring(4, 6);
            var status    = sidc.charAt(6);
            var hqtf      = sidc.charAt(7);
            var echelon   = sidc.substring(8, 10);
            var entity    = sidc.substring(10, 16);
            var mod1      = sidc.substring(16, 18);
            var mod2      = sidc.substring(18, 20);

            // Reverse lookup: identity → affiliation key
            var affReverse = { '0':'pending','1':'unknown','2':'assumedFriend','3':'friend','4':'neutral','5':'suspect','6':'hostile' };
            var affKey = affReverse[identity] || 'friend';
            document.querySelectorAll('.aff-box-btn[data-aff]').forEach(function(b) { b.classList.remove('active'); });
            var affBtn = document.querySelector('.aff-box-btn[data-aff="' + affKey + '"]');
            if (affBtn) affBtn.classList.add('active');

            // Symbol set → triggers entity/mod dropdown repopulation
            var ssEl = document.getElementById('natoSymbolSet');
            if (ssEl) { ssEl.value = symbolSet; }
            // Repopulate entity/mod1/mod2 dropdowns for this symbol set
            var ssVal = ssEl ? ssEl.value : '10';
            populateSelect('natoSymbolType', NATO_ENTITIES[ssVal] || NATO_ENTITIES['_default']);
            populateSelect('natoMod1', NATO_MOD1[ssVal] || NATO_MOD1['_default']);
            populateSelect('natoMod2', NATO_MOD2[ssVal] || NATO_MOD2['_default']);

            // Set entity, status, echelon, mod1, mod2
            var ntEl = document.getElementById('natoSymbolType');
            if (ntEl && ntEl.querySelector('option[value="' + entity + '"]')) ntEl.value = entity;
            var stEl = document.getElementById('natoStatus');
            if (stEl) stEl.value = status;
            var ecEl = document.getElementById('natoEchelon');
            if (ecEl) ecEl.value = echelon;
            var m1El = document.getElementById('natoMod1');
            if (m1El && m1El.querySelector('option[value="' + mod1 + '"]')) m1El.value = mod1;
            var m2El = document.getElementById('natoMod2');
            if (m2El && m2El.querySelector('option[value="' + mod2 + '"]')) m2El.value = mod2;

            // HQ/TF/Dummy toggles (bitfield: 1=Dummy, 2=HQ, 4=TF)
            var hqtfNum = parseInt(hqtf) || 0;
            document.querySelectorAll('.aff-box-btn[data-hqtf]').forEach(function(b) {
                var bit = parseInt(b.dataset.hqtf) || 0;
                if (hqtfNum & bit) b.classList.add('active');
                else b.classList.remove('active');
            });
        }
        // Config fields (text inputs)
        var cfg = modalMarkerData.config || {};
        document.getElementById('natoDesignation').value = cfg.uniqueDesignation || '';
        document.getElementById('natoAdditional').value = cfg.additionalInformation || '';
        if (document.getElementById('natoHigherFormation'))
            document.getElementById('natoHigherFormation').value = cfg.higherFormation || '';
        if (document.getElementById('natoDirection'))
            document.getElementById('natoDirection').value = (cfg.direction !== undefined && cfg.direction !== null) ? Math.round(cfg.direction * 6400 / 360) : '';
        if (document.getElementById('natoReinforced'))
            document.getElementById('natoReinforced').value = cfg.reinforcedReduced || '';
        // Scale
        var scaleVal = modalMarkerData.scale ? Math.round(modalMarkerData.scale * 100) : 100;
        document.getElementById('natoScale').value = scaleVal;

        // Rebuild icon dropdowns and update preview
        updateAffiliationIcons();
        if (typeof buildAllIconDropdowns === 'function') buildAllIconDropdowns();
        updateNatoPreview();
        openMapModal('modalNatoSymbol');
    } else if (modalMarkerData.type === 'line') {
        document.getElementById('lineDeleteBtn').style.display = 'block';
        document.getElementById('lineSaveBtn').innerText = 'Update';
        openMapModal('modalLineProps');
    } else if (modalMarkerData.type === 'mission') {
        // Just delete for now to recreate
        stormConfirm('Delete this mission?').then(function(ok) {
            if(ok) backend.removeMarker(modalMarkerId);
        });
    } else if (modalMarkerData.type === 'note') {
        openMapModal('modalNote');
        document.getElementById('noteDeleteBtn').style.display = 'block';
        if(tinymce.get('noteContent')) tinymce.get('noteContent').setContent(modalMarkerData.config.content || '');
    } else if (modalMarkerData.type === 'measure') {
        openMapModal('modalMeasure');
        document.getElementById('measureDeleteBtn').style.display = 'block';
    } else if (modalMarkerData.type === 'basic') {
        document.getElementById('basicShape').value = modalMarkerData.symbol || 'mil_dot';
        var colorBtns = document.querySelectorAll('#modalBasicSymbol .color-btn');
        colorBtns.forEach(function(b) { b.classList.remove('active'); if (b.dataset.color === colorToCss(modalMarkerData.config.color)) b.classList.add('active'); });
        document.getElementById('basicLabel').value = modalMarkerData.config.label || '';
        document.getElementById('basicScale').value = Math.round((modalMarkerData.scale || 1) * 100);
        document.getElementById('basicDeleteBtn').style.display = 'block';
        document.getElementById('basicInsertBtn').innerText = 'Update';
        openMapModal('modalBasicSymbol');
    }
}

const BASIC_SYMBOL_SVG = {
    'mil_dot':       '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="{c}"/></svg>',
    'mil_circle':    '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="{c}" stroke-width="2.5"/></svg>',
    'mil_cross':     '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><line x1="4" y1="4" x2="20" y2="20" stroke="{c}" stroke-width="3"/><line x1="20" y1="4" x2="4" y2="20" stroke="{c}" stroke-width="3"/></svg>',
    'mil_square':    '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" fill="none" stroke="{c}" stroke-width="2.5"/></svg>',
    'mil_triangle':  '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><polygon points="12,2 22,22 2,22" fill="{c}"/></svg>',
    'mil_diamond':   '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><polygon points="12,2 22,12 12,22 2,12" fill="{c}"/></svg>',
    'mil_arrow':     '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><path d="M12 2L20 22L12 16L4 22Z" fill="{c}"/></svg>',
    'mil_objective': '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="{c}" stroke-width="2"/><line x1="12" y1="3" x2="12" y2="21" stroke="{c}" stroke-width="2"/><line x1="3" y1="12" x2="21" y2="12" stroke="{c}" stroke-width="2"/></svg>',
    'mil_pickup':    '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><polygon points="12,2 22,18 2,18" fill="none" stroke="{c}" stroke-width="2.5"/><circle cx="12" cy="11" r="3" fill="{c}"/></svg>',
    'mil_start':     '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="{c}" stroke-width="2.5"/><circle cx="12" cy="12" r="3" fill="{c}"/></svg>',
    'mil_end':       '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="{c}" stroke-width="2.5"/><line x1="5" y1="5" x2="19" y2="19" stroke="{c}" stroke-width="2"/><line x1="19" y1="5" x2="5" y2="19" stroke="{c}" stroke-width="2"/></svg>',
    'mil_unknown':   '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="{c}" stroke-width="2"/><text x="12" y="17" text-anchor="middle" fill="{c}" font-size="16" font-weight="bold">?</text></svg>',
    'mil_warning':   '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><polygon points="12,2 22,22 2,22" fill="none" stroke="{c}" stroke-width="2.5"/><text x="12" y="19" text-anchor="middle" fill="{c}" font-size="14" font-weight="bold">!</text></svg>',
    'mil_flag':      '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><line x1="5" y1="2" x2="5" y2="22" stroke="{c}" stroke-width="2"/><polygon points="5,2 20,7 5,12" fill="{c}"/></svg>',
    'mil_destroy':   '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="{c}" stroke-width="2"/><line x1="5" y1="5" x2="19" y2="19" stroke="{c}" stroke-width="3"/><line x1="19" y1="5" x2="5" y2="19" stroke="{c}" stroke-width="3"/></svg>',
    'mil_join':      '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="{c}" stroke-width="2"/><line x1="7" y1="12" x2="17" y2="12" stroke="{c}" stroke-width="2.5"/><line x1="12" y1="7" x2="12" y2="17" stroke="{c}" stroke-width="2.5"/></svg>',
    'mil_marker':    '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z" fill="{c}"/></svg>',
    'hd_ambush':     '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><path d="M4 4L12 20L20 4" fill="none" stroke="{c}" stroke-width="3" stroke-linejoin="round"/><line x1="12" y1="20" x2="12" y2="12" stroke="{c}" stroke-width="3"/></svg>',
    'hd_destroy':    '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><line x1="4" y1="4" x2="20" y2="20" stroke="{c}" stroke-width="3.5"/><line x1="20" y1="4" x2="4" y2="20" stroke="{c}" stroke-width="3.5"/></svg>',
    'hd_flag':       '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><line x1="5" y1="2" x2="5" y2="22" stroke="{c}" stroke-width="2.5"/><polygon points="5,2 20,7 5,12" fill="{c}" opacity="0.7"/></svg>',
    'hd_start':      '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" fill="none" stroke="{c}" stroke-width="3"/><circle cx="12" cy="12" r="3" fill="{c}"/></svg>',
    'hd_end':        '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" fill="none" stroke="{c}" stroke-width="3"/><line x1="4" y1="4" x2="20" y2="20" stroke="{c}" stroke-width="2"/><line x1="20" y1="4" x2="4" y2="20" stroke="{c}" stroke-width="2"/></svg>',
    'hd_objective':  '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8" fill="none" stroke="{c}" stroke-width="3"/><line x1="12" y1="4" x2="12" y2="20" stroke="{c}" stroke-width="2"/><line x1="4" y1="12" x2="20" y2="12" stroke="{c}" stroke-width="2"/></svg>',
    'hd_pickup':     '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><polygon points="12,4 20,18 4,18" fill="none" stroke="{c}" stroke-width="3"/></svg>',
    'hd_warning':    '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><polygon points="12,3 22,21 2,21" fill="none" stroke="{c}" stroke-width="2.5"/><line x1="12" y1="10" x2="12" y2="15" stroke="{c}" stroke-width="2.5"/><circle cx="12" cy="18" r="1.2" fill="{c}"/></svg>',
    'hd_unknown':    '<svg width="{s}" height="{s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="{c}" stroke-width="2.5"/><text x="12" y="17" text-anchor="middle" fill="{c}" font-size="16" font-weight="bold">?</text></svg>'
};

function getBasicSymbolSVG(symbol, color, size) {
    var tmpl = BASIC_SYMBOL_SVG[symbol] || BASIC_SYMBOL_SVG['mil_dot'];
    return tmpl.replace(/\{s\}/g, size).replace(/\{c\}/g, color);
}

function addOrUpdateMarker(map, markers, marker, canEdit, backend, opacity, layer) {
    var markerId = marker.id;
    var markerData = marker.data;
    var existing = markers[markerId];

    if (markerData.type == 'line') {
        var posList = posToPoints(markerData.pos);
        var color = colorToCss(markerData.config.color);
        var isArea = markerData.symbol === 'area' || markerData.config.fill;
        if (existing) {
            existing.setLatLngs(posList); existing.setStyle({ color: color, weight: markerData.config.weight||3 });
            existing.options.markerData = markerData;
        } else {
            var mapMarker;
            if (isArea) {
                mapMarker = L.polygon(posList, { color: color, weight: markerData.config.weight||3, fillOpacity: 0.12, interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
            } else {
                mapMarker = L.polyline(posList, { color: color, weight: markerData.config.weight||3, interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
            }
            if (canEdit) mapMarker.on('click', e => updateMarkerHandler(e,map,backend));
            markers[markerId] = existing = mapMarker;
        }
    } else if (markerData.type == 'measure') {
        var posList = posToPoints(markerData.pos);
        if (existing) {
            existing.setLatLngs(posList);
            computeDistanceAndShowTooltip(map, existing, posList, canEdit, markerId, markerData);
            existing.options.markerData = markerData;
        } else {
            var mColor = (markerData.config && markerData.config.color) ? colorToCss(markerData.config.color) : '#ffff00';
            var mapMarker = L.polyline(posList, { color: mColor, weight: 2, dashArray: '4,4', interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
            computeDistanceAndShowTooltip(map, mapMarker, posList, canEdit, markerId, markerData);
            if (canEdit) mapMarker.on('click', e => updateMarkerHandler(e, map, backend));
            markers[markerId] = existing = mapMarker;
        }
    } else if (markerData.type == 'mission') {
        var posList = posToPoints(markerData.pos);
        var color = colorToCss(markerData.config.color);
        var result = generateMission(markerData.symbol, posList, markerData.config.size || '13');
        if (existing) {
            existing.setLatLngs(result.lines); existing.setStyle({ color: color });
            existing.options.markerData = markerData;
        } else {
            var mapMarker = L.polyline(result.lines, { smoothFactor: 0.5, color: color, weight: 3, interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
            markers[markerId] = existing = mapMarker;
            if (canEdit) mapMarker.on('click', e => updateMarkerHandler(e, map, backend));
        }
        if (result.labels) missionLabels(existing, result, layer.group);
    } else if (markerData.type == 'note') {
        if (existing) {
            existing.setLatLng(markerData.pos);
            existing.setTooltipContent(markerData.config.content);
            existing.options.direction = markerData.config.position || 'center';
            existing.options.markerData = markerData;
        } else {
            var options = { content: markerData.config.content, direction: markerData.config.position||'center', permanent: true, interactive: canEdit, markerId: markerId, markerData: markerData, className: 'stickyNote' };
            var mapMarker = L.marker(markerData.pos, { opacity:0, interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
            mapMarker.bindTooltip(markerData.config.content, { permanent: true, direction: markerData.config.position||'center', className: 'stickyNote'});
            if (canEdit) mapMarker.on('click', e => updateMarkerHandler(e, map, backend));
            markers[markerId] = existing = mapMarker;
        }
    } else { // 'mil' or 'basic'
        var size = 32;
        if (markerData.scale) size = Number(markerData.scale) * size;
        var icon;
        if (markerData.type == 'mil') {
            var symbolConfig = Object.assign({ size: size }, markerData.config);
            var sym = new ms.Symbol(markerData.symbol, symbolConfig);
            icon = L.divIcon({ className: 'nato-icon', html: sym.asSVG(), iconSize: [sym.width, sym.height], iconAnchor: [sym.getAnchor().x, sym.getAnchor().y] });
        } else {
            icon = L.divIcon({ className: 'basic-symbol-icon', html: getBasicSymbolSVG(markerData.symbol, colorToCss(markerData.config.color), size), iconSize: [size, size], iconAnchor: [size/2, size/2] });
        }
        
        if (existing) {
            existing.setIcon(icon); existing.setLatLng(markerData.pos); existing.options.markerData = markerData;
            if (markerData.type === 'basic' && markerData.config && markerData.config.label) {
                if (existing.getTooltip()) existing.setTooltipContent(markerData.config.label);
                else existing.bindTooltip(markerData.config.label, { permanent: true, direction: 'right', className: 'marker-text' });
            } else if (markerData.type === 'basic' && existing.getTooltip()) {
                existing.unbindTooltip();
            }
        } else {
            var mapMarker = L.marker(markerData.pos, { icon: icon, draggable: canEdit, interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
            if (markerData.config && markerData.config.label) {
                 mapMarker.bindTooltip(markerData.config.label, { permanent: true, direction: 'right', className: 'marker-text' });
            }
            if (canEdit) {
                mapMarker.on('click', e => updateMarkerHandler(e, map, backend));
                mapMarker.on('dragend', function (e) {
                    var marker = e.target;
                    marker.options.markerData.pos = [marker.getLatLng().lat, marker.getLatLng().lng];
                    backend.moveMarker(marker.options.markerId, marker.options.markerData);
                });
            }
            markers[markerId] = existing = mapMarker;
        }
    }
}

// Cancel pending line/area draw
function cancelLineDraw() {
    if (currentLine) { currentLine.remove(); currentLine = null; }
    if (currentArea) { currentArea.remove(); currentArea = null; }
    closeMapModal('modalLineProps');
    setTool('pan', document.getElementById('toolPan'));
}

// Map Click Logic for drawing
function onMapClick(e) {
    var latlng = e.latlng;
    var tool = currentDrawAction;
    if (!tool || tool === 'pan') return;
    
    if (tool === 'line') {
        var point = [latlng.lat, latlng.lng];
        var append = e.originalEvent.ctrlKey || e.originalEvent.shiftKey;
        var toolColorBtn = document.querySelector('#toolbarColorPicker .tool-color-btn.active');
        var lineColor = toolColorBtn ? toolColorBtn.dataset.color : '#000000';
        if (!currentLine) {
            currentLine = L.polyline([point, point], { color: lineColor, weight: 3, interactive: false }).addTo(mapInst);
        } else if (append) {
            var data = currentLine.getLatLngs();
            data[data.length - 1] = L.latLng(point[0], point[1]);
            data.push(L.latLng(point[0], point[1]));
            currentLine.setLatLngs(data);
        } else {
            var data = currentLine.getLatLngs();
            data[data.length - 1] = L.latLng(point[0], point[1]);
            currentLine.remove(); currentLine = null;
            backend.addMarker(null, { type: 'line', symbol: 'line', config: { color: lineColor }, pos: data.map(function (p) { var ll = L.latLng(p); return [ll.lat, ll.lng]; }).flat() });
        }
    } else if (tool === 'measure') {
        var point = [latlng.lat, latlng.lng];
        var toolColorBtn = document.querySelector('#toolbarColorPicker .tool-color-btn.active');
        var measureColor = toolColorBtn ? toolColorBtn.dataset.color : '#ffff00';
        if (!currentMeasure) {
            currentMeasure = L.polyline([point, point], { color: measureColor, weight: 2, dashArray: '4', interactive: false }).addTo(mapInst);
        } else {
            var data = currentMeasure.getLatLngs();
            currentMeasure.remove(); currentMeasure = null;
            backend.addMarker(null, { type: 'measure', symbol: 'measure', config: { color: measureColor }, pos: data.map(function (p) { var ll = L.latLng(p); return [ll.lat, ll.lng]; }).flat() });
            setTool('pan', document.getElementById('toolPan'));
        }
    } else if (tool === 'mission') {
        if (!missionSelection) return;
        if (!missionSelection.points) {
            missionSelection.points = [[latlng.lat, latlng.lng]];
        } else {
            missionSelection.points.push([latlng.lat, latlng.lng]);
        }
        var def = MilMissions.missions[missionSelection.mission];
        if (def && def.points == missionSelection.points.length) {
            if (currentMission) { currentMission.remove(); currentMission = null; }
            backend.addMarker(null, {
                type: 'mission', symbol: missionSelection.mission,
                config: { size: missionSelection.size, color: missionSelection.color },
                pos: missionSelection.points.flat()
            });
            missionSelection = null;
            setTool('pan', document.getElementById('toolPan'));
        } else {
            var result = generateMission(missionSelection.mission, missionSelection.points, missionSelection.size);
            if (!currentMission) {
                currentMission = L.polyline(result.lines, { smoothFactor: 0.5, color: missionSelection.color, weight: 3, interactive: false }).addTo(mapInst);
            } else { currentMission.setLatLngs(result.lines); }
            if (result.labels) missionLabels(currentMission, result, mapInst);
        }
    } else if (tool === 'note') {
        clickPosition = [latlng.lat, latlng.lng];
        modalMarkerId = null;
        document.getElementById('noteDeleteBtn').style.display = 'none';
        openMapModal('modalNote');
        if(tinymce.get('noteContent')) tinymce.get('noteContent').setContent('');
        setTool('pan', document.getElementById('toolPan'));
    } else if (tool === 'natoSymbol') {
        clickPosition = [latlng.lat, latlng.lng];
        modalMarkerId = null;
        document.getElementById('natoInsertBtn').innerText = 'Insert';
        document.getElementById('natoDeleteBtn').style.display = 'none';
        var a = latLngToArma(latlng);
        var grid = String(Math.floor(a.x / 10)).padStart(4, '0') + ' - ' + String(Math.floor(a.y / 10)).padStart(4, '0');
        document.getElementById('natoModalTitle').innerText = grid + ' : NATO APP-6 (D) Symbol';
        openMapModal('modalNatoSymbol');
        setTool('pan', document.getElementById('toolPan'));
    } else if (tool === 'basicSymbol') {
        clickPosition = [latlng.lat, latlng.lng];
        modalMarkerId = null;
        document.getElementById('basicDeleteBtn').style.display = 'none';
        document.getElementById('basicInsertBtn').innerText = 'Insert';
        openMapModal('modalBasicSymbol');
        setTool('pan', document.getElementById('toolPan'));
    } else if (tool === 'area') {
        var point = [latlng.lat, latlng.lng];
        if (!currentArea) {
            currentArea = L.polygon([point, point, point], { color: '#0066ff', weight: 3, fillOpacity: 0.12, interactive: false }).addTo(mapInst);
        } else if (e.originalEvent.shiftKey) {
            var data = currentArea.getLatLngs()[0];
            data[data.length - 1] = L.latLng(point[0], point[1]);
            data.push(L.latLng(point[0], point[1]));
            currentArea.setLatLngs([data]);
        } else {
            var data = currentArea.getLatLngs()[0];
            data[data.length - 1] = L.latLng(point[0], point[1]);
            var posFlat = data.map(function(p) { return [p.lat, p.lng]; }).flat();
            currentArea.remove(); currentArea = null;
            var color = document.querySelector('#lineColorPicker .active') ? document.querySelector('#lineColorPicker .active').dataset.color : '#0066ff';
            backend.addMarker(null, { type: 'line', symbol: 'area', config: { color: color, fill: true }, pos: posFlat });
            setTool('pan', document.getElementById('toolPan'));
        }
    } else if (tool === 'point') {
        clickPosition = [latlng.lat, latlng.lng];
        backend.addMarker(null, { type: 'basic', symbol: 'mil_dot', config: { color: '#ff0000', label: '' }, scale: 1, pos: clickPosition });
    } else if (tool === 'flag') {
        clickPosition = [latlng.lat, latlng.lng];
        var label = prompt('Enter label text:');
        if (label) {
            backend.addMarker(null, { type: 'basic', symbol: 'mil_dot', config: { color: '#ffff00', label: label }, scale: 0.8, pos: clickPosition });
        }
    } else if (tool === 'select') {
        // Select mode: do nothing on empty map click, markers handle their own click
    }
}

// Map Mousemove for rubberbanding
function onMapMouseMove(e) {
    var latlng = e.latlng;
    if (currentLine) {
        var data = currentLine.getLatLngs();
        data[data.length - 1] = L.latLng(latlng.lat, latlng.lng);
        currentLine.setLatLngs(data);
    }
    if (currentArea) {
        var data = currentArea.getLatLngs()[0];
        data[data.length - 1] = L.latLng(latlng.lat, latlng.lng);
        currentArea.setLatLngs([data]);
    }
    if (currentMeasure) {
        var data = currentMeasure.getLatLngs();
        data[data.length - 1] = L.latLng(latlng.lat, latlng.lng);
        currentMeasure.setLatLngs(data);
        computeDistanceAndShowTooltip(mapInst, currentMeasure, data, false);
    }
    if (currentMission && missionSelection && missionSelection.points) {
        var pts = missionSelection.points.slice();
        pts.push([latlng.lat, latlng.lng]);
        var result = generateMission(missionSelection.mission, pts, missionSelection.size);
        currentMission.setLatLngs(result.lines);
    }
}

// ---- INIT MAP ----
async function initIntelMap() {
    if (mapInst) { mapInst.remove(); mapInst = null; }
    if (drawPollTimer) clearTimeout(drawPollTimer);
    drawLayer = L.featureGroup();

    var bounds = [[0, 0], [MAP_CONFIG.worldSize, MAP_CONFIG.worldSize]];

    mapInst = L.map('intelMap', {
        crs: ArmaCRS, minZoom: MAP_CONFIG.minZoom, maxZoom: MAP_CONFIG.maxZoom + 1,
        attributionControl: false, zoomControl: false,
        doubleClickZoom: false, zoomSnap: 0.2, zoomDelta: 0.2,
        maxBounds: bounds, maxBoundsViscosity: 1.0
    });
    
    // Create a custom pane for the white background so it sits behind the tiles (tilePane z-index is 200)
    mapInst.createPane('bgPane');
    mapInst.getPane('bgPane').style.zIndex = 100;
    L.rectangle(bounds, { color: 'none', fillColor: '#fff', fillOpacity: 1, interactive: false, pane: 'bgPane' }).addTo(mapInst);

    L.tileLayer(MAP_CONFIG.tileUrl, { tileSize: MAP_CONFIG.tileSize, noWrap: true, bounds: bounds, maxNativeZoom: MAP_CONFIG.maxZoom }).addTo(mapInst);
    mapInst.fitBounds(bounds);
    
    drawLayer.addTo(mapInst);

    // PLANOPS graticule (replaces custom grid)
    // Make grid lines transparent (weight: 0) but keep the grid labels (fontColor)
    var graticuleZooms = [];
    if (MAP_CONFIG.maxZoom > 4) {
        graticuleZooms.push({ start: 0, end: MAP_CONFIG.maxZoom - 4, interval: 10000 });
        graticuleZooms.push({ start: MAP_CONFIG.maxZoom - 4, end: 10, interval: 1000 });
    } else {
        graticuleZooms.push({ start: 0, end: 10, interval: 1000 });
    }
    gridLayer = L.latlngGraticule({ weight: 0, color: 'transparent', fontColor: '#444', zoomInterval: graticuleZooms }).addTo(mapInst);

    // PLANOPS coordinate display (bottom-right)
    L.control.gridMousePosition({ precision: 4 }).addTo(mapInst);
    L.control.scale({ maxWidth: 200, imperial: false }).addTo(mapInst);

    mapInst.on('click', onMapClick);
    mapInst.on('mousemove', onMapMouseMove);
    var isPointing = false;
    var pointingMarker = null;
    mapInst.on('mousedown', function(e) {
        if (currentDrawAction === 'select') {
            isPointing = true;
            if (!pointingMarker) {
                pointingMarker = L.circleMarker(e.latlng, { radius: 12, color: '#ff0000', weight: 3, fillColor: '#ff0000', fillOpacity: 0.3, interactive: false, className: 'pointing-pulse' }).addTo(mapInst);
            } else {
                pointingMarker.setLatLng(e.latlng);
            }
        }
    });
    mapInst.on('mousemove', function(e) {
        if (isPointing && pointingMarker) {
            pointingMarker.setLatLng(e.latlng);
        }
    });
    mapInst.on('mouseup mouseout', function(e) {
        if (isPointing) {
            isPointing = false;
            if (pointingMarker) { pointingMarker.remove(); pointingMarker = null; }
        }
    });
    mapInst.on('contextmenu', function(e) {
        if (currentLine) {
            var data = currentLine.getLatLngs().slice(0, -1);
            if (data.length > 1) { currentLine.setLatLngs(data); }
            else { currentLine.remove(); currentLine = null; }
        }
        if (currentArea) { currentArea.remove(); currentArea = null; }
        if (currentMeasure) { currentMeasure.remove(); currentMeasure = null; }
    });
    document.querySelectorAll('.map-tool-btn[data-tool]').forEach(btn => {
        btn.addEventListener('click', function() { setTool(this.dataset.tool, this); });
    });
    document.getElementById('toolZoomIn').onclick = () => mapInst.zoomIn();
    document.getElementById('toolZoomOut').onclick = () => mapInst.zoomOut();
    
    document.querySelectorAll('.tool-color-btn').forEach(btn => {
        btn.onclick = function() {
            document.querySelectorAll('.tool-color-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        };
    });
    document.getElementById('toolFullscreen').onclick = () => {
        var mapEl = document.querySelector('.map-editor-wrap');
        if (!document.fullscreenElement) { if(mapEl.requestFullscreen) mapEl.requestFullscreen(); } 
        else { if(document.exitFullscreen) document.exitFullscreen(); }
    };

    // Layers panel toggle
    document.getElementById('toolLayers').onclick = () => {
        var panel = document.getElementById('layersPanel');
        if (panel) panel.classList.toggle('show');
    };

    // Export to JSON
    document.getElementById('toolExport').onclick = () => {
        var exportData = { map: currentMap, timestamp: new Date().toISOString(), markers: [] };
        Object.keys(allMarkers).forEach(id => {
            var m = allMarkers[id];
            if (m.options && m.options.markerData) {
                exportData.markers.push({ id: id, data: m.options.markerData });
            }
        });
        var blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'intel_map_export_' + Date.now() + '.json';
        a.click(); URL.revokeObjectURL(url);
    };

    // Layers panel: toggle layer visibility
    document.getElementById('layerDrawings').onclick = function() {
        this.classList.toggle('active');
        var icon = this.querySelector('.layer-vis i');
        if (this.classList.contains('active')) {
            mapInst.addLayer(drawLayer); icon.className = 'fas fa-eye';
        } else {
            mapInst.removeLayer(drawLayer); icon.className = 'fas fa-eye-slash';
        }
    };
    document.getElementById('layerGrid').onclick = function() {
        this.classList.toggle('active');
        var icon = this.querySelector('.layer-vis i');
        if (this.classList.contains('active')) {
            if (gridLayer) mapInst.addLayer(gridLayer); icon.className = 'fas fa-eye';
        } else {
            if (gridLayer) mapInst.removeLayer(gridLayer); icon.className = 'fas fa-eye-slash';
        }
    };

    // Clear All markers
    document.getElementById('btnClearAll').onclick = function() {
        stormConfirm('CLEAR ALL MARKERS? This cannot be undone.').then(function(ok) {
        if (!ok) return;
        fetch('api/mission_plan.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'clear', map:currentMap, password:'S2' }) })
        .then(r => r.json()).then(d => {
            if (d.success) {
                Object.keys(allMarkers).forEach(id => {
                    if(allMarkers[id].labels) allMarkers[id].labels.forEach(l=>l.remove());
                    drawLayer.removeLayer(allMarkers[id]);
                });
                allMarkers = {};
            }
        });
        });
    };

    // Import JSON markers
    document.getElementById('btnImportMarkers').onclick = function() {
        var input = document.createElement('input');
        input.type = 'file'; input.accept = '.json';
        input.onchange = function(ev) {
            var file = ev.target.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var data = JSON.parse(e.target.result);
                    if (data.markers && Array.isArray(data.markers)) {
                        data.markers.forEach(function(m) {
                            backend.addMarker(null, m.data);
                        });
                    }
                } catch(err) { stormAlert('Invalid JSON file', 'error'); }
            };
            reader.readAsText(file);
        };
        input.click();
    };

    pollPlayers();
    pollDrawings();
    
    // ---- Real-Time Cursor Layer ----
    rtCursorLayer = L.layerGroup().addTo(mapInst);
    
    // Track mouse position on map
    mapInst.on('mousemove', function(e) {
        rtCursorPos = { lat: e.latlng.lat, lng: e.latlng.lng };
    });
    mapInst.on('mouseout', function() {
        rtCursorPos = null;
    });
    
    // Start real-time presence system
    startRealTimePresence();
    
    // Init TinyMCE for notes
    tinymce.init({
        selector: '#noteContent',
        menubar: false,
        toolbar: 'bold italic forecolor | alignleft aligncenter alignright',
        height: 200, skin: 'oxide-dark', content_css: 'dark'
    });
}

// ---- MODAL LOGIC ----
function openMapModal(id) { document.getElementById(id).classList.add('show'); }
function closeMapModal(id) { 
    document.getElementById(id).classList.remove('show'); 
}

// --- SIDC Generation & Preview ---
const NATO_AFF = { pending:'0', unknown:'1', assumedFriend:'2', friend:'3', neutral:'4', suspect:'5', hostile:'6' };

// Entity data per symbol set — exact codes from milsymbol source (matches PLANOPS Maps)
const NATO_ENTITIES = {
    '10': [ // Land Unit
        {v:'110000',t:'Command and Control'},
        {v:'110100',t:'Broadcast Transmitter Antenna'},
        {v:'110200',t:'Civil Affairs'},
        {v:'110300',t:'Civil-Military Cooperation'},
        {v:'110400',t:'Information Operations'},
        {v:'110500',t:'Liaison'},
        {v:'110600',t:'MISO (PSYOP)'},
        {v:'110700',t:'Radio'},
        {v:'110800',t:'Radio Relay'},
        {v:'110900',t:'Radio Teletype Centre'},
        {v:'111000',t:'Signal'},
        {v:'111100',t:'Satellite'},
        {v:'111200',t:'Video Imagery'},
        {v:'111300',t:'Space'},
        {v:'111400',t:'Special Troops'},
        {v:'120100',t:'Air Assault'},
        {v:'120200',t:'Air Traffic Services'},
        {v:'120300',t:'Amphibious'},
        {v:'120400',t:'Anti-Armor / Anti-Tank'},
        {v:'120500',t:'Armour'},
        {v:'120501',t:'Armour, Reconnaissance'},
        {v:'120502',t:'Armour, Amphibious'},
        {v:'120600',t:'Aviation, Rotary Wing'},
        {v:'120601',t:'Aviation, Rotary Wing Recon'},
        {v:'120700',t:'Aviation, Composite'},
        {v:'120800',t:'Aviation, Fixed Wing'},
        {v:'120801',t:'Aviation, Fixed Wing Recon'},
        {v:'120900',t:'Combat'},
        {v:'121000',t:'Combined Arms'},
        {v:'121100',t:'Infantry'},
        {v:'121101',t:'Infantry, Amphibious'},
        {v:'121102',t:'Infantry, Armoured'},
        {v:'121103',t:'Infantry, Mechanized'},
        {v:'121104',t:'Infantry, Motorized'},
        {v:'121106',t:'Main Gun System'},
        {v:'121200',t:'Observer / Observation'},
        {v:'121300',t:'Reconnaissance'},
        {v:'121301',t:'Reconnaissance, Surveillance'},
        {v:'121302',t:'Reconnaissance, Amphibious'},
        {v:'121303',t:'Reconnaissance, Motorized'},
        {v:'121400',t:'Sea-Air-Land (SEAL)'},
        {v:'121500',t:'Sniper'},
        {v:'121600',t:'Surveillance'},
        {v:'121700',t:'Special Forces'},
        {v:'121800',t:'Special Operations Forces'},
        {v:'121900',t:'Unmanned Systems'},
        {v:'122000',t:'Ranger'},
        {v:'130100',t:'Air Defence'},
        {v:'130101',t:'Air Defence, Gun'},
        {v:'130102',t:'Air Defence, Missile'},
        {v:'130200',t:'Field Arty Aerial Obs'},
        {v:'130300',t:'Field Artillery'},
        {v:'130301',t:'Field Artillery, Self-Propelled'},
        {v:'130302',t:'Field Arty, Target Acquisition'},
        {v:'130400',t:'Field Artillery Observer'},
        {v:'130500',t:'Joint Fire Support'},
        {v:'130600',t:'Meteorological'},
        {v:'130700',t:'Missile'},
        {v:'130800',t:'Mortar'},
        {v:'130900',t:'Survey'},
        {v:'140100',t:'CBRN'},
        {v:'140200',t:'Combat Support (Manoeuvre Enhancement)'},
        {v:'140300',t:'Criminal Investigation'},
        {v:'140400',t:'Diver'},
        {v:'140500',t:'Dog'},
        {v:'140600',t:'Drilling'},
        {v:'140700',t:'Engineer'},
        {v:'140701',t:'Engineer, Mechanized'},
        {v:'140702',t:'Engineer, Motorized'},
        {v:'140800',t:'Explosive Ordnance Disposal'},
        {v:'140900',t:'Field Camp Construction'},
        {v:'141000',t:'Fire Protection'},
        {v:'141100',t:'Geospatial Support'},
        {v:'141200',t:'Military Police'},
        {v:'141300',t:'Mine'},
        {v:'141400',t:'Mine Clearing'},
        {v:'141500',t:'Mine Launching'},
        {v:'141600',t:'Mine Laying'},
        {v:'141700',t:'Security'},
        {v:'141800',t:'Search and Rescue'},
        {v:'141900',t:'Security Police (Air)'},
        {v:'142000',t:'Shore Patrol'},
        {v:'142100',t:'Topographic'},
        {v:'142200',t:'Air and Missile Defense'},
        {v:'150100',t:'Analysis'},
        {v:'150200',t:'Counter-Intelligence'},
        {v:'150300',t:'Direction Finding'},
        {v:'150400',t:'Electronic Ranging'},
        {v:'150500',t:'Electronic Warfare'},
        {v:'150600',t:'Intercept'},
        {v:'150700',t:'Interrogation'},
        {v:'150800',t:'Jamming'},
        {v:'150900',t:'Joint Intelligence Centre'},
        {v:'151000',t:'Military Intelligence'},
        {v:'151100',t:'Search'},
        {v:'151200',t:'Sensor'},
        {v:'160000',t:'Sustainment'},
        {v:'160100',t:'Administrative'},
        {v:'160200',t:'All Class Supply'},
        {v:'160400',t:'Ammunition'},
        {v:'160500',t:'Band'},
        {v:'160600',t:'Combat Service Support'},
        {v:'160700',t:'Finance'},
        {v:'160800',t:'Judge Advocate General'},
        {v:'160900',t:'Labour'},
        {v:'161000',t:'Laundry/Bath'},
        {v:'161100',t:'Maintenance'},
        {v:'161200',t:'Materiel'},
        {v:'161300',t:'Medical'},
        {v:'161400',t:'Medical Treatment Facility'},
        {v:'161500',t:'Morale, Welfare, Recreation'},
        {v:'161600',t:'Mortuary Affairs'},
        {v:'162300',t:'Ordnance'},
        {v:'162400',t:'Personnel Services'},
        {v:'162500',t:'Petroleum, Oil, Lubricants'},
        {v:'162600',t:'Pipeline'},
        {v:'162700',t:'Postal'},
        {v:'162800',t:'Public Affairs'},
        {v:'162900',t:'Quartermaster'},
        {v:'163000',t:'Railhead'},
        {v:'163100',t:'Religious Support'},
        {v:'163200',t:'Replacement Holding Unit'},
        {v:'163400',t:'Supply'},
        {v:'163600',t:'Transportation'},
        {v:'164700',t:'Water'},
        {v:'164800',t:'Water Purification'},
        {v:'170100',t:'Naval'},
        {v:'180100',t:'Allied Command Europe Rapid Reaction Corps (ARRC)'},
        {v:'180200',t:'Allied Command Operations'},
        {v:'180300',t:'ISAF'},
        {v:'180400',t:'Multinational'},
        {v:'190000',t:'Emergency Operation'},
        {v:'200000',t:'Law Enforcement'},
        {v:'200700',t:'Law Enforcement Unit'},
        {v:'210000',t:'Cyber'}
    ],
    '01': [ // Air
        {v:'110100',t:'Fixed-Wing'},
        {v:'110200',t:'Military Rotary Wing'},
        {v:'110300',t:'UAV'},
        {v:'110400',t:'VT-UAV'},
        {v:'110500',t:'Military Balloon'},
        {v:'110600',t:'Military Airship'},
        {v:'110700',t:'Tethered Lighter Than Air'},
        {v:'120100',t:'Civilian Fixed-Wing'},
        {v:'120200',t:'Civilian Rotary Wing'},
        {v:'120300',t:'Civilian UAV'},
        {v:'120400',t:'Civilian Balloon'},
        {v:'120500',t:'Civilian Airship'},
        {v:'120600',t:'Civilian Tethered LTA'},
        {v:'130100',t:'Bomb'},
        {v:'130200',t:'Underwater Decoy'},
        {v:'140000',t:'Manual Track'}
    ],
    '02': [ // Air Missile
        {v:'110000',t:'Missile'}
    ],
    '05': [ // Space
        {v:'110100',t:'Military'},
        {v:'110200',t:'Civilian'}
    ],
    '11': [ // Land civilian unit/Organization
        {v:'110000',t:'Civilian'},
        {v:'110100',t:'Enterprise/Business'},
        {v:'110200',t:'Government Organization'},
        {v:'110300',t:'Non-Government Organization (NGO)'}
    ],
    '15': [ // Land Equipment
        {v:'110100',t:'Weapon'},
        {v:'110101',t:'Rifle/Automatic Weapon'},
        {v:'110102',t:'Machine Gun'},
        {v:'110103',t:'Grenade Launcher'},
        {v:'110200',t:'Anti-Tank Gun'},
        {v:'110300',t:'Direct Fire Gun'},
        {v:'110400',t:'Recoilless Gun'},
        {v:'110500',t:'Anti-Tank Missile Launcher'},
        {v:'110600',t:'Anti-Tank Rocket Launcher'},
        {v:'110700',t:'Howitzer'},
        {v:'110800',t:'Missile Launcher'},
        {v:'110900',t:'Mortar'},
        {v:'111000',t:'Single Rocket Launcher'},
        {v:'111100',t:'Multiple Rocket Launcher'},
        {v:'111200',t:'Anti-Tank Heavy'},
        {v:'111300',t:'Air Defence Gun'},
        {v:'111400',t:'Air Defence Missile Launcher'},
        {v:'120000',t:'Vehicle'},
        {v:'120100',t:'Armoured Fighting Vehicle'},
        {v:'120101',t:'Armoured Fighting Vehicle (Command)'},
        {v:'120200',t:'Tank'},
        {v:'120300',t:'Light Armoured Vehicle'},
        {v:'120400',t:'Armoured Personnel Carrier'},
        {v:'120500',t:'Infantry Fighting Vehicle'},
        {v:'120600',t:'Armoured Utility Vehicle'},
        {v:'120700',t:'Engineering Vehicle'},
        {v:'130000',t:'Utility Vehicle'},
        {v:'130100',t:'Bus'},
        {v:'130200',t:'Semi-Trailer Truck'},
        {v:'130300',t:'Tow Truck'},
        {v:'130400',t:'Ambulance'},
        {v:'140000',t:'Train / Locomotive'},
        {v:'150000',t:'Sensor'},
        {v:'150100',t:'Radar'},
        {v:'150200',t:'JTIDS/MIDS'},
        {v:'160000',t:'Missile'},
        {v:'170000',t:'Civilian Vehicle'}
    ],
    '20': [ // Land Installation
        {v:'110100',t:'Aircraft Production & Assembly'},
        {v:'110200',t:'Ammunition'},
        {v:'110300',t:'Ammunition & Supply'},
        {v:'110400',t:'Tank/Armour'},
        {v:'110500',t:'Black List Location'},
        {v:'110600',t:'CBRN'},
        {v:'110700',t:'Engineer/Dozer'},
        {v:'110701',t:'Bridge'},
        {v:'110800',t:'Equipment Manufacture'},
        {v:'110900',t:'Government'},
        {v:'111000',t:'Gray List Location'},
        {v:'111100',t:'Mass Grave Location'}
    ],
    '27': [ // Dismounted individual
        {v:'110100',t:'Personnel'},
        {v:'110200',t:'Leader'},
        {v:'110300',t:'Sniper'},
        {v:'110400',t:'Scout'}
    ],
    '30': [ // Sea Surface
        {v:'120000',t:'Combatant'},
        {v:'120100',t:'Carrier'},
        {v:'120200',t:'Surface Combatant, Line'},
        {v:'120300',t:'Amphibious Warfare Ship'},
        {v:'130000',t:'Noncombatant'},
        {v:'130100',t:'Auxiliary Ship'}
    ],
    '35': [ // Sea Subsurface
        {v:'110100',t:'Submarine'},
        {v:'110101',t:'SSN (Nuclear Attack)'},
        {v:'110102',t:'SSBN (Ballistic Missile)'},
        {v:'110103',t:'SSGN (Guided Missile)'},
        {v:'120000',t:'UUV/Unmanned Underwater Vehicle'},
        {v:'130000',t:'Diver'}
    ],
    '36': [ // Mine Warfare
        {v:'110100',t:'Mine'},
        {v:'110200',t:'Mine — Moored'},
        {v:'110300',t:'Mine — Floating'},
        {v:'110400',t:'Mine — Bottom'},
        {v:'110500',t:'Mine — Rising'},
        {v:'120000',t:'Mine Countermeasure Vessel'}
    ],
    '40': [ // Activity/Event
        {v:'110100',t:'Criminal Activity Incident'},
        {v:'110200',t:'Bomb'}
    ],
    '_default': [{v:'110000',t:'Default'}]
};

// Modifier 1 — exact codes from milsymbol landunit.js
const NATO_MOD1 = {
    '10': [
        {v:'00',t:'Unspecified'},
        {v:'01',t:'Airmobile / Air Assault'},
        {v:'02',t:'Area'},
        {v:'03',t:'Attack'},
        {v:'04',t:'Biological'},
        {v:'05',t:'Border'},
        {v:'06',t:'Bridging'},
        {v:'07',t:'Chemical'},
        {v:'08',t:'Close Protection'},
        {v:'09',t:'Combat'},
        {v:'10',t:'Command and Control'},
        {v:'11',t:'Communications Contingency'},
        {v:'12',t:'Construction'},
        {v:'13',t:'Cross Cultural Communication'},
        {v:'14',t:'Crowd and Riot Control'},
        {v:'15',t:'Decontamination'},
        {v:'16',t:'Detention'},
        {v:'17',t:'Direct Communications'},
        {v:'18',t:'Diving'},
        {v:'19',t:'Division'},
        {v:'20',t:'Dog'},
        {v:'21',t:'Drilling'},
        {v:'22',t:'Electro-Optical'},
        {v:'23',t:'Enhanced'},
        {v:'24',t:'Explosive Ordnance Disposal'},
        {v:'25',t:'Fire Direction Centre'},
        {v:'26',t:'Force'},
        {v:'27',t:'Forward'},
        {v:'29',t:'Landing Support'},
        {v:'31',t:'Maintenance'},
        {v:'32',t:'Meteorological'},
        {v:'33',t:'Mine Countermeasure'},
        {v:'34',t:'Missile'},
        {v:'35',t:'Mobile Advisor and Support'},
        {v:'37',t:'Mobility Support'},
        {v:'38',t:'Movement Control Centre'},
        {v:'39',t:'Multinational'},
        {v:'41',t:'Multiple Rocket Launcher'},
        {v:'42',t:'NATO Medical Role 1'},
        {v:'43',t:'NATO Medical Role 2'},
        {v:'44',t:'NATO Medical Role 3'},
        {v:'45',t:'NATO Medical Role 4'},
        {v:'46',t:'Naval'},
        {v:'48',t:'Nuclear'},
        {v:'49',t:'Operations'},
        {v:'50',t:'Radar'},
        {v:'52',t:'Radiological'},
        {v:'53',t:'Search and Rescue'},
        {v:'54',t:'Security'},
        {v:'55',t:'Sensor'},
        {v:'57',t:'Signals Intelligence'},
        {v:'59',t:'Single Rocket Launcher'},
        {v:'60',t:'Smoke'},
        {v:'61',t:'Sniper'},
        {v:'62',t:'Sound Ranging'},
        {v:'63',t:'Special Operations Forces (SOF)'},
        {v:'64',t:'Special Weapons and Tactics'},
        {v:'65',t:'Survey'},
        {v:'66',t:'Tactical Exploitation'},
        {v:'67',t:'Target Acquisition'},
        {v:'68',t:'Topographic'},
        {v:'69',t:'Utility'},
        {v:'70',t:'Video Imagery'},
        {v:'75',t:'MEDEVAC'},
        {v:'76',t:'Ranger'},
        {v:'77',t:'Support'},
        {v:'78',t:'Aviation'},
        {v:'79',t:'Route, Recon, and Clearance'},
        {v:'80',t:'Tilt-Rotor'},
        {v:'84',t:'Assault'},
        {v:'85',t:'Weapons'},
        {v:'93',t:'Independent Command'},
        {v:'97',t:'Brigade'},
        {v:'98',t:'Headquarters Element'}
    ],
    '_default': [{v:'00',t:'Unspecified'}]
};

// Modifier 2 — exact codes from milsymbol landunit.js
const NATO_MOD2 = {
    '10': [
        {v:'00',t:'Unspecified'},
        {v:'01',t:'Airborne'},
        {v:'02',t:'Arctic'},
        {v:'03',t:'Battle Damage Repair'},
        {v:'04',t:'Bicycle Equipped'},
        {v:'05',t:'Casualty Staging'},
        {v:'06',t:'Clearing'},
        {v:'07',t:'Close Range'},
        {v:'08',t:'Control'},
        {v:'09',t:'Decontamination'},
        {v:'10',t:'Demolition'},
        {v:'11',t:'Dental'},
        {v:'12',t:'Digital'},
        {v:'14',t:'Equipment'},
        {v:'15',t:'Heavy'},
        {v:'16',t:'High Altitude'},
        {v:'17',t:'Intermodal'},
        {v:'18',t:'Intensive Care'},
        {v:'19',t:'Light'},
        {v:'20',t:'Laboratory'},
        {v:'21',t:'Launcher'},
        {v:'22',t:'Long Range'},
        {v:'23',t:'Low Altitude'},
        {v:'24',t:'Medium'},
        {v:'25',t:'Medium Altitude'},
        {v:'26',t:'Medium Range'},
        {v:'27',t:'Mountain'},
        {v:'29',t:'Multi-Channel'},
        {v:'31',t:'Pack Animal'},
        {v:'34',t:'Psychological'},
        {v:'36',t:'Railroad'},
        {v:'40',t:'Riverine'},
        {v:'42',t:'Ski'},
        {v:'43',t:'Short Range'},
        {v:'44',t:'Strategic'},
        {v:'45',t:'Support'},
        {v:'46',t:'Tactical'},
        {v:'47',t:'Towed'},
        {v:'48',t:'Troop'},
        {v:'49',t:'V/STOL'},
        {v:'50',t:'Veterinary'},
        {v:'51',t:'Wheeled'},
        {v:'54',t:'Attack'},
        {v:'55',t:'Refuel'},
        {v:'56',t:'Utility'},
        {v:'57',t:'Combat Search and Rescue'},
        {v:'58',t:'Guerilla'},
        {v:'59',t:'Air Assault'},
        {v:'60',t:'Amphibious'},
        {v:'61',t:'Very Heavy'},
        {v:'74',t:'Composite'},
        {v:'76',t:'Light and Medium'},
        {v:'77',t:'Self-Propelled'},
        {v:'89',t:'Air Defence'}
    ],
    '_default': [{v:'00',t:'Unspecified'}]
};

// Populate a <select> element from an array of {v,t}
function populateSelect(selId, items, keepValue) {
    var sel = document.getElementById(selId);
    if (!sel) return;
    var prev = keepValue ? sel.value : null;
    sel.innerHTML = '';
    items.forEach(function(item) {
        var opt = document.createElement('option');
        opt.value = item.v; opt.textContent = item.t;
        sel.appendChild(opt);
    });
    if (prev && sel.querySelector('option[value="'+prev+'"]')) sel.value = prev;
}

// When symbol set changes, repopulate entity, mod1, mod2 dropdowns
function onSymbolSetChange() {
    var ss = document.getElementById('natoSymbolSet').value;
    populateSelect('natoSymbolType', NATO_ENTITIES[ss] || NATO_ENTITIES['_default']);
    populateSelect('natoMod1', NATO_MOD1[ss] || NATO_MOD1['_default']);
    populateSelect('natoMod2', NATO_MOD2[ss] || NATO_MOD2['_default']);
    // Rebuild icon dropdowns for repopulated selects
    if (typeof buildAllIconDropdowns === 'function') buildAllIconDropdowns();
    updateNatoPreview();
}

function getHqTfDummyValue() {
    var v = 0;
    document.querySelectorAll('.aff-box-btn[data-hqtf].active').forEach(function(b) {
        v += parseInt(b.dataset.hqtf) || 0;
    });
    return String(v);
}

function generateSIDC() {
    var affBtn = document.querySelector('.aff-box-btn[data-aff].active');
    var affKey = affBtn ? affBtn.dataset.aff : 'friend';
    var symbolSet = document.getElementById('natoSymbolSet').value;
    var entity = document.getElementById('natoSymbolType').value;
    var status = document.getElementById('natoStatus').value;
    var hqtf = getHqTfDummyValue();
    var echelon = document.getElementById('natoEchelon').value;
    var mod1 = document.getElementById('natoMod1').value;
    var mod2 = document.getElementById('natoMod2').value;
    var identity = NATO_AFF[affKey] || '3';
    return '10' + '0' + identity + symbolSet + status + hqtf + echelon + entity + mod1 + mod2;
}

function updateNatoPreview() {
    var sidc = generateSIDC();
    var desig = document.getElementById('natoDesignation').value;
    var info = document.getElementById('natoAdditional').value;
    var hf = document.getElementById('natoHigherFormation') ? document.getElementById('natoHigherFormation').value : '';
    var dirMil = document.getElementById('natoDirection') ? parseInt(document.getElementById('natoDirection').value) : undefined;
    var reinf = document.getElementById('natoReinforced') ? document.getElementById('natoReinforced').value : '';
    var scale = parseInt(document.getElementById('natoScale').value) || 100;
    
    document.getElementById('sidcCode').value = sidc;

    if (typeof ms !== 'undefined') {
        var opts = { size: scale * 0.3 };
        if (desig) opts.uniqueDesignation = desig;
        if (info) opts.additionalInformation = info;
        if (hf) opts.higherFormation = hf;
        if (dirMil && !isNaN(dirMil)) opts.direction = Math.round(dirMil * 360 / 6400);
        if (reinf) opts.reinforcedReduced = reinf;
        var sym = new ms.Symbol(sidc, opts);
        document.getElementById('natoPreview').innerHTML = '';
        document.getElementById('natoPreview').appendChild(sym.asDOM());
    }
    if (typeof refreshAllIconDropdowns === 'function') refreshAllIconDropdowns();
}

// Init dropdowns on page load
document.getElementById('natoSymbolSet').addEventListener('change', onSymbolSetChange);
['natoSymbolType', 'natoStatus', 'natoEchelon', 'natoMod1', 'natoMod2',
 'natoDesignation', 'natoAdditional', 'natoHigherFormation', 'natoDirection',
 'natoReinforced', 'natoScale'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', updateNatoPreview);
        el.addEventListener('change', updateNatoPreview);
    }
});
// Affiliation buttons (radio — exclusive)
document.querySelectorAll('.aff-box-btn[data-aff]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.aff-box-btn[data-aff]').forEach(b=>b.classList.remove('active'));
        this.classList.add('active');
        updateAffiliationIcons();
        updateNatoPreview();
    });
});
// HQ/TF/Dummy buttons (toggle — multiple selectable)
document.querySelectorAll('.aff-box-btn[data-hqtf]').forEach(btn => {
    btn.addEventListener('click', function() {
        this.classList.toggle('active');
        updateNatoPreview();
    });
});

// Render affiliation SVG icons dynamically using milsymbol
function updateAffiliationIcons() {
    if (typeof ms === 'undefined') return;
    var activeAffBtn = document.querySelector('.aff-box-btn[data-aff].active');
    var activeAff = activeAffBtn ? (NATO_AFF[activeAffBtn.dataset.aff] || '3') : '3';
    
    // Main affiliations (render only the frame)
    document.querySelectorAll('.aff-box-btn[data-aff]').forEach(function(btn) {
        var ident = NATO_AFF[btn.dataset.aff] || '3';
        // 10(version) 0(sid1) ident(sid2) 10(set) 0(status) 0(hqtf) 00(ech) 000000(entity) 00(mod1) 00(mod2)
        var sidc = '10' + '0' + ident + '10' + '0' + '0' + '00' + '000000' + '00' + '00';
        try {
            btn.querySelector('.aff-icon').innerHTML = new ms.Symbol(sidc, { size: 24, icon: false }).asSVG();
        } catch(e){}
    });
    // HQ/TF/Dummy modifiers (render frame using active affiliation + specific modifier)
    document.querySelectorAll('.aff-box-btn[data-hqtf]').forEach(function(btn) {
        var hqtf = btn.dataset.hqtf;
        // 10(version) 0(sid1) activeAff(sid2) 10(set) 0(status) hqtf 00(ech) 000000(entity) 00(mod1) 00(mod2)
        var sidc = '10' + '0' + activeAff + '10' + '0' + hqtf + '00' + '000000' + '00' + '00';
        try {
            btn.querySelector('.aff-icon').innerHTML = new ms.Symbol(sidc, { size: 24, icon: false }).asSVG();
        } catch(e){}
    });
}
updateAffiliationIcons();

// Initialize entity lists for default symbol set
onSymbolSetChange();

// Reusable icon dropdown builder for all NATO selects (PLANOPS style)
// type: 'entity' | 'status' | 'mod1' | 'mod2' | 'echelon'
function buildIconDropdown(selId, type) {
    if (typeof ms === 'undefined') return;
    var sel = document.getElementById(selId);
    if (!sel) return;

    // Destroy old wrapper if exists (fixes duplicate bug)
    var oldWrap = sel.closest('.nato-icon-dropdown-wrap');
    if (oldWrap) {
        var parent = oldWrap.parentNode;
        parent.insertBefore(sel, oldWrap);
        oldWrap.remove();
    }
    sel.style.display = 'none';

    var wrap = document.createElement('div');
    wrap.className = 'nato-icon-dropdown-wrap';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);

    var display = document.createElement('div');
    display.className = 'nato-icon-dropdown-display nato-select';
    display.style.cursor = 'pointer';
    display.style.display = 'flex';
    display.style.alignItems = 'center';
    display.style.gap = '6px';
    wrap.appendChild(display);

    var list = document.createElement('div');
    list.className = 'nato-icon-dropdown-list';
    list.style.display = 'none';
    wrap.appendChild(list);

    var searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search...';
    searchInput.className = 'nato-input';
    searchInput.style.cssText = 'margin:4px;width:calc(100% - 8px);box-sizing:border-box;';

    function renderSidc(val) {
        try {
            var affBtn = document.querySelector('.aff-box-btn[data-aff].active');
            var ident = affBtn ? (NATO_AFF[affBtn.dataset.aff] || '3') : '3';
            var ss = (type === 'symbolset') ? val : document.getElementById('natoSymbolSet').value;
            var entityVal = (type === 'symbolset') ? '000000' : ((type === 'entity') ? val : document.getElementById('natoSymbolType').value);
            var statusVal = (type === 'symbolset') ? '0' : ((type === 'status') ? val : document.getElementById('natoStatus').value);
            var echelonVal = (type === 'symbolset') ? '00' : ((type === 'echelon') ? val : document.getElementById('natoEchelon').value);
            var mod1Val = (type === 'symbolset') ? '00' : ((type === 'mod1') ? val : document.getElementById('natoMod1').value);
            var mod2Val = (type === 'symbolset') ? '00' : ((type === 'mod2') ? val : document.getElementById('natoMod2').value);
            var hqtf = (type === 'symbolset') ? '0' : getHqTfDummyValue();
            
            // If we are just previewing symbol sets, render only the frame
            var sidc = '100' + ident + ss + statusVal + hqtf + echelonVal + entityVal + mod1Val + mod2Val;
            var opts = { size: 22 };
            if (type === 'symbolset') opts.icon = false; // Only show frame for Symbol Set dropdown
            return new ms.Symbol(sidc, opts).asSVG();
        } catch(e) { return ''; }
    }

    function buildList(filter) {
        list.innerHTML = '';
        list.appendChild(searchInput);
        Array.from(sel.options).forEach(function(opt) {
            if (filter && opt.text.toLowerCase().indexOf(filter.toLowerCase()) < 0) return;
            var item = document.createElement('div');
            item.className = 'nato-icon-dropdown-item';
            item.innerHTML = renderSidc(opt.value) + '<span>' + opt.text + '</span>';
            item.dataset.value = opt.value;
            item.onclick = function() {
                sel.value = this.dataset.value;
                refreshDisplay();
                list.style.display = 'none';
                sel.dispatchEvent(new Event('change'));
            };
            list.appendChild(item);
        });
    }

    function refreshDisplay() {
        var opt = sel.options[sel.selectedIndex];
        if (opt) display.innerHTML = renderSidc(opt.value) + '<span>' + opt.text + '</span>';
    }

    display.onclick = function(e) {
        e.stopPropagation();
        // Close all other open dropdowns
        document.querySelectorAll('.nato-icon-dropdown-list').forEach(function(l) { if (l !== list) l.style.display = 'none'; });
        var isOpen = list.style.display !== 'none';
        list.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) { buildList(''); searchInput.value = ''; searchInput.focus(); }
    };
    searchInput.oninput = function() { buildList(this.value); };
    searchInput.onclick = function(e) { e.stopPropagation(); };

    refreshDisplay();
    sel._iconDropdown = { refreshDisplay: refreshDisplay };
}
// Close all dropdowns on outside click
document.addEventListener('click', function() { document.querySelectorAll('.nato-icon-dropdown-list').forEach(function(l) { l.style.display = 'none'; }); });

// Build all icon dropdowns
function buildAllIconDropdowns() {
    buildIconDropdown('natoSymbolSet', 'symbolset');
    buildIconDropdown('natoSymbolType', 'entity');
    buildIconDropdown('natoStatus', 'status');
    buildIconDropdown('natoMod1', 'mod1');
    buildIconDropdown('natoMod2', 'mod2');
    buildIconDropdown('natoEchelon', 'echelon');
}
buildAllIconDropdowns();

// Refresh all icon dropdown displays
function refreshAllIconDropdowns() {
    ['natoSymbolSet','natoSymbolType','natoStatus','natoMod1','natoMod2','natoEchelon'].forEach(function(id) {
        var sel = document.getElementById(id);
        if (sel && sel._iconDropdown) sel._iconDropdown.refreshDisplay();
    });
}

// Copy code button
document.getElementById('natoCopyCodeBtn').onclick = function() {
    var code = document.getElementById('sidcCode').value;
    navigator.clipboard.writeText(code).then(function() {
        var btn = document.getElementById('natoCopyCodeBtn');
        btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy code'; }, 1500);
    });
};
// Copy image button
document.getElementById('natoCopyImageBtn').onclick = function() {
    var svgEl = document.querySelector('#natoPreview svg');
    if (!svgEl) return;
    var svgData = new XMLSerializer().serializeToString(svgEl);
    var canvas = document.createElement('canvas');
    var img = new Image();
    img.onload = function() {
        canvas.width = img.width; canvas.height = img.height;
        canvas.getContext('2d').drawImage(img, 0, 0);
        canvas.toBlob(function(blob) {
            navigator.clipboard.write([new ClipboardItem({'image/png': blob})]);
        });
    };
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
};
// All symbols link
document.getElementById('natoAllSymbolsLink').onclick = function(e) {
    e.preventDefault();
    window.open('https://maps.plan-ops.fr/Symbols/All', '_blank');
};

// MODAL SAVES
document.getElementById('natoDeleteBtn').onclick = function() {
    if(modalMarkerId) backend.removeMarker(modalMarkerId);
    closeMapModal('modalNatoSymbol');
};
document.getElementById('natoInsertBtn').onclick = function() {
    var sidc = generateSIDC();
    var desig = document.getElementById('natoDesignation').value;
    var info = document.getElementById('natoAdditional').value;
    var hf = document.getElementById('natoHigherFormation') ? document.getElementById('natoHigherFormation').value : '';
    var dirMil = document.getElementById('natoDirection') ? parseInt(document.getElementById('natoDirection').value) : undefined;
    var reinf = document.getElementById('natoReinforced') ? document.getElementById('natoReinforced').value : '';
    var scale = parseInt(document.getElementById('natoScale').value) || 100;
    var config = { uniqueDesignation: desig, additionalInformation: info };
    if (hf) config.higherFormation = hf;
    if (dirMil && !isNaN(dirMil)) config.direction = Math.round(dirMil * 360 / 6400);
    if (reinf) config.reinforcedReduced = reinf;

    if(modalMarkerId) {
        modalMarkerData.symbol = sidc; modalMarkerData.config = config; modalMarkerData.scale = scale/100;
        backend.updateMarkerToLayer(modalMarkerId, null, modalMarkerData);
    } else {
        backend.addMarker(null, { type: 'mil', symbol: sidc, config: config, scale: scale/100, pos: clickPosition });
    }
    closeMapModal('modalNatoSymbol');
};

document.getElementById('basicDeleteBtn').onclick = function() {
    if(modalMarkerId) backend.removeMarker(modalMarkerId);
    closeMapModal('modalBasicSymbol');
};
document.getElementById('basicInsertBtn').onclick = function() {
    var shape = document.getElementById('basicShape').value;
    var color = document.querySelector('#modalBasicSymbol .color-btn.active').dataset.color;
    var text = document.getElementById('basicLabel').value;
    var scale = parseInt(document.getElementById('basicScale').value) || 100;

    if(modalMarkerId) {
        modalMarkerData.symbol = shape; modalMarkerData.config.color = color; modalMarkerData.config.label = text; modalMarkerData.scale = scale/100;
        backend.updateMarkerToLayer(modalMarkerId, null, modalMarkerData);
    } else {
        backend.addMarker(null, { type: 'basic', symbol: shape, config: {color: color, label: text}, scale: scale/100, pos: clickPosition });
    }
    closeMapModal('modalBasicSymbol');
};

document.getElementById('lineSaveBtn').onclick = function() {
    var color = document.querySelector('#modalLineProps .color-btn.active').dataset.color;
    var weight = document.getElementById('lineWeight').value;
    if(modalMarkerId) {
        modalMarkerData.config.color = color; modalMarkerData.config.weight = weight;
        backend.updateMarkerToLayer(modalMarkerId, null, modalMarkerData);
    }
    closeMapModal('modalLineProps');
};
document.getElementById('lineDeleteBtn').onclick = function() {
    if(modalMarkerId) backend.removeMarker(modalMarkerId);
    closeMapModal('modalLineProps');
};

document.querySelectorAll('.mission-btn').forEach(btn => {
    btn.onclick = function() {
        var mission = this.dataset.mission;
        var size = document.getElementById('missionSize').value;
        var color = document.querySelector('#missionColorPicker .color-btn.active').dataset.color || '#000000';
        missionSelection = { mission: mission, size: size, color: color, points: null };
        closeMapModal('modalMissionSelector');
        // Now click on map to add points
    }
});

document.getElementById('noteSaveBtn').onclick = function() {
    var content = tinymce.get('noteContent').getContent();
    var pos = document.getElementById('notePosition').value;
    if(modalMarkerId) {
        modalMarkerData.config.content = content; modalMarkerData.config.position = pos;
        backend.updateMarkerToLayer(modalMarkerId, null, modalMarkerData);
    } else {
        backend.addMarker(null, { type: 'note', symbol: 'note', config: {content: content, position: pos}, pos: clickPosition });
    }
    closeMapModal('modalNote');
};
document.getElementById('noteDeleteBtn').onclick = function() {
    if(modalMarkerId) backend.removeMarker(modalMarkerId);
    closeMapModal('modalNote');
};
document.getElementById('measureDeleteBtn').onclick = function() {
    if(modalMarkerId) backend.removeMarker(modalMarkerId);
    closeMapModal('modalMeasure');
};

// Selection Helpers
document.querySelectorAll('.color-btn').forEach(btn => {
    btn.onclick = function() { this.parentElement.querySelectorAll('.color-btn').forEach(b=>b.classList.remove('active')); this.classList.add('active'); }
});

// Search
document.getElementById('toolSearch').onclick = () => openMapModal('modalSearch');
document.getElementById('searchGoBtn').onclick = () => {
    var x = parseInt(document.getElementById('searchX').value);
    var y = parseInt(document.getElementById('searchY').value);
    if (!isNaN(x) && !isNaN(y)) { mapInst.setView(armaToLatLng(x, y), 5); closeMapModal('modalSearch'); }
};

// --- POLLERS ---
async function pollPlayers() {
    try {
        var r = await fetch('api/mock_players.php?map='+currentMap);
        var d = await r.json();
        if (d.success) {
            var currentIds = new Set(d.data.map(function(p){ return p.id; }));
            Object.keys(playerMarkers).forEach(function(id) {
                if (!currentIds.has(parseInt(id))) { playerLayer.removeLayer(playerMarkers[id]); delete playerMarkers[id]; }
            });
            d.data.forEach(function(p) {
                var latlng = armaToLatLng(p.x, p.y);
                if (playerMarkers[p.id]) {
                    playerMarkers[p.id].setLatLng(latlng);
                } else {
                    var m = L.marker(latlng, { icon: L.divIcon({ className:'player-marker', iconSize:[12,12] }) });
                    playerMarkers[p.id] = m;
                    playerLayer.addLayer(m);
                }
            });
        }
    } catch(e) { }
    playerPollTimer = setTimeout(pollPlayers, 2000);
}

async function pollDrawings() {
    try {
        var r = await fetch('api/mission_plan.php?map='+currentMap);
        var d = await r.json();
        if (d.success && d.markers) {
            var currentIds = new Set(d.markers.map(function(m){ return m.id; }));
            Object.keys(allMarkers).forEach(function(id) {
                if (!currentIds.has(id)) {
                    if(allMarkers[id].labels) allMarkers[id].labels.forEach(l=>l.remove());
                    drawLayer.removeLayer(allMarkers[id]); delete allMarkers[id]; 
                }
            });
            d.markers.forEach(function(m) {
                addOrUpdateMarker(mapInst, allMarkers, { id: m.id, data: m }, true, backend, {}, { group: drawLayer });
            });
        }
    } catch(e) {}
    drawPollTimer = setTimeout(pollDrawings, 2000);
}

// ---- REAL-TIME PRESENCE SYSTEM ----
function startRealTimePresence() {
    sendHeartbeat();
    pollPresence();
}

async function sendHeartbeat() {
    try {
        await fetch('api/map_presence.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'heartbeat',
                user_id: rtUserId,
                display_name: rtDisplayName,
                user_color: rtUserColor,
                map: currentMap,
                cursor_lat: rtCursorPos ? rtCursorPos.lat : null,
                cursor_lng: rtCursorPos ? rtCursorPos.lng : null
            })
        });
    } catch (e) { }
    rtHeartbeatTimer = setTimeout(sendHeartbeat, 2000);
}

async function pollPresence() {
    try {
        var r = await fetch('api/map_presence.php?map=' + currentMap);
        var d = await r.json();
        if (d.success && d.users) {
            var activeIds = new Set();
            var onlineCount = 0;
            
            d.users.forEach(function(u) {
                activeIds.add(u.user_id);
                onlineCount++;
                
                // Skip self cursor
                if (u.user_id === rtUserId) return;
                
                if (u.cursor_lat != null && u.cursor_lng != null && rtCursorLayer) {
                    var latlng = L.latLng(u.cursor_lat, u.cursor_lng);
                    if (rtCursorMarkers[u.user_id]) {
                        rtCursorMarkers[u.user_id].setLatLng(latlng);
                        // Update tooltip
                        rtCursorMarkers[u.user_id].setTooltipContent(u.display_name);
                    } else {
                        var cursorIcon = L.divIcon({
                            className: 'rt-cursor-marker',
                            html: '<div class="rt-cursor-dot" style="background:' + u.user_color + ';box-shadow:0 0 8px ' + u.user_color + '"></div>' +
                                  '<div class="rt-cursor-label" style="color:' + u.user_color + '">' + u.display_name + '</div>',
                            iconSize: [0, 0],
                            iconAnchor: [0, 0]
                        });
                        var marker = L.marker(latlng, { icon: cursorIcon, interactive: false, zIndexOffset: 9000 });
                        marker.bindTooltip(u.display_name, { permanent: false, direction: 'right', offset: [12, 0], className: 'rt-cursor-tooltip' });
                        rtCursorLayer.addLayer(marker);
                        rtCursorMarkers[u.user_id] = marker;
                    }
                } else if (rtCursorMarkers[u.user_id]) {
                    // User has no cursor position — remove marker
                    rtCursorLayer.removeLayer(rtCursorMarkers[u.user_id]);
                    delete rtCursorMarkers[u.user_id];
                }
            });
            
            // Remove cursors for users who left
            Object.keys(rtCursorMarkers).forEach(function(uid) {
                if (!activeIds.has(uid)) {
                    rtCursorLayer.removeLayer(rtCursorMarkers[uid]);
                    delete rtCursorMarkers[uid];
                }
            });
            
            // Update connected users indicator
            updateOnlineIndicator(onlineCount, d.users);
        }
    } catch (e) { }
    rtPresenceTimer = setTimeout(pollPresence, 2000);
}

function updateOnlineIndicator(count, users) {
    var el = document.getElementById('rtOnlineCount');
    if (el) el.textContent = count;
    var dot = document.getElementById('rtOnlineDot');
    if (dot) dot.style.background = count > 1 ? '#00ff41' : '#ffaa00';
    
    // Update user list panel
    var listEl = document.getElementById('rtUserList');
    if (listEl) {
        listEl.innerHTML = users.map(function(u) {
            var isSelf = u.user_id === rtUserId;
            return '<div class="rt-user-item' + (isSelf ? ' self' : '') + '">' +
                   '<span class="rt-user-dot" style="background:' + u.user_color + '"></span>' +
                   '<span class="rt-user-name">' + (u.display_name || 'Unknown') + (isSelf ? ' (You)' : '') + '</span>' +
                   '</div>';
        }).join('');
    }
}

// Send leave signal when user closes tab
window.addEventListener('beforeunload', function() {
    navigator.sendBeacon('api/map_presence.php', JSON.stringify({
        action: 'leave',
        user_id: rtUserId,
        map: currentMap
    }));
});

// Hook map init
document.querySelectorAll('[data-tab="sorties"]').forEach(function(el) {
    el.addEventListener('click', function() {
        if (!mapInst) { setTimeout(initIntelMap, 300); }
        else { setTimeout(function(){ mapInst.invalidateSize(); }, 300); }
    });
});
// Auto-init map if restored tab is sorties
if(localStorage.getItem('intel_active_tab')==='sorties'){
    setTimeout(initIntelMap, 400);
}

// ==========================================
// DOCUMENT VIEWER LOGIC
// ==========================================
let currentPdfLang = 'EN';
const pdfPaths = {
    'EN': 'assets/Role/Joint%20OPS%20plan%20[%20Edit-t%20].pdf',
    'TH': 'assets/Role/Joint%20OPS%20plan%20[%20Edit-t%20]_TH.pdf'
};

function togglePdfLanguage() {
    currentPdfLang = currentPdfLang === 'EN' ? 'TH' : 'EN';
    const btn = document.getElementById('btnPdfLang');
    if (btn) btn.innerHTML = `<i class="fas fa-language"></i> ${currentPdfLang}`;
    
    const iframe = document.getElementById('documentPdfViewer');
    if (iframe) {
        iframe.src = pdfPaths[currentPdfLang] + '#toolbar=0&navpanes=0&scrollbar=0&view=FitH';
    }
}

function togglePdfFullscreen() {
    const container = document.getElementById('pdfViewerContainer');
    if (!container) return;
    
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
        if (container.requestFullscreen) {
            container.requestFullscreen();
        } else if (container.webkitRequestFullscreen) {
            container.webkitRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    }
}
