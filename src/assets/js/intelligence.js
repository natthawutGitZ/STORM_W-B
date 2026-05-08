const API='api/intelligence.php';
let s2Authed=false,editState={};

// Clock
function updateClock(){
  const d=new Date(),z=n=>String(n).padStart(2,'0');
  const el=document.getElementById('clock');
  if(el)el.innerHTML=z(d.getHours())+':'+z(d.getMinutes())+':'+z(d.getSeconds())+' ICT';
}
setInterval(updateClock,1000);updateClock();

// Tabs
document.querySelectorAll('.tab-btn,.nav-item').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const tab=btn.dataset.tab;
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));
    document.querySelectorAll('.nav-item').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));
    document.querySelectorAll('.tab-content').forEach(c=>c.classList.toggle('active',c.id==='tab-'+tab));
  });
});

// Auth
function showAuthModal(){
  if(s2Authed){s2Authed=false;const b=document.getElementById('btnAuth');b.classList.remove('authed');b.innerHTML='<i class="fas fa-lock"></i> S2 ACCESS';loadAll();return;}
  document.getElementById('authPass').value='';document.getElementById('authError').style.display='none';
  document.getElementById('authModal').classList.add('show');
  setTimeout(()=>document.getElementById('authPass').focus(),100);
}
function verifyAuth(){
  if(document.getElementById('authPass').value==='S2'){
    s2Authed=true;closeModal('authModal');
    const b=document.getElementById('btnAuth');b.classList.add('authed');b.innerHTML='<i class="fas fa-unlock"></i> S2 ACTIVE';
    loadAll();
  }else{document.getElementById('authError').style.display='block';document.getElementById('authPass').value='';document.getElementById('authPass').focus();}
}
function closeModal(id){document.getElementById(id).classList.remove('show');}

// Data loading
async function loadAll(){await Promise.all([loadOps(),loadIntel()]);}

async function loadOps(){
  const r=await fetch(API+'?action=list&type=operations'),d=await r.json();
  if(!d.success)return;
  document.getElementById('opCount').textContent=d.data.length;
  const el=document.getElementById('opList');
  const sb=document.getElementById('sidebarOpsList');
  if(!d.data.length){el.innerHTML='<div class="empty">NO ACTIVE OPERATIONS</div>';sb.innerHTML='';return;}
  sb.innerHTML=d.data.map(o=>`<div class="sop-item"><div>${esc(o.codename)}</div><div class="sop-status tag-${o.status.toLowerCase()}" style="color:inherit">${o.status}</div></div>`).join('');
  el.innerHTML=d.data.map(o=>renderItem('operations',o,`
    <div class="item-row"><span class="item-name">${esc(o.codename)}</span><span class="tag tag-${o.priority.toLowerCase()}">${o.priority}</span></div>
    <div class="item-row" style="margin-top:4px"><span class="tag tag-${o.status.toLowerCase()}">${o.status}</span></div>
    <div class="item-desc">${esc(o.brief||'No brief available')}</div>
    <div class="item-meta"><span><i class="fas fa-user-shield"></i>${esc(o.commander||'N/A')}</span><span><i class="fas fa-clock"></i>${timeAgo(o.updated_at)}</span></div>
  `)).join('');
}

async function loadIntel(){
  const r=await fetch(API+'?action=list&type=reports'),d=await r.json();
  if(!d.success)return;
  document.getElementById('intelCount').textContent=d.data.length;
  const el2=document.getElementById('intelCount2');if(el2)el2.textContent=d.data.length;
  const html=d.data.length?d.data.map(o=>renderItem('reports',o,`
    <div class="item-row"><span class="item-name">${esc(o.title)}</span><span class="tag tag-${o.classification.replace(/\s/g,'').toLowerCase()}">${o.classification}</span></div>
    <div class="item-desc">${esc(o.content||'')}</div>
    <div class="item-meta"><span><i class="fas fa-satellite-dish"></i>${esc(o.source)}</span><span><i class="fas fa-clock"></i>${timeAgo(o.created_at)}</span></div>
  `)).join(''):'<div class="empty">NO INTELLIGENCE REPORTS</div>';
  document.getElementById('intelList').innerHTML=html;
  const f=document.getElementById('intelListFull');if(f)f.innerHTML=html;
}

async function loadSorties(){
  const r=await fetch(API+'?action=list&type=sorties'),d=await r.json();
  if(!d.success)return;
  document.getElementById('sortieCount').textContent=d.data.length;
  const el=document.getElementById('sortieList');
  if(!d.data.length){el.innerHTML='<div class="empty">NO ACTIVE SORTIES</div>';return;}
  el.innerHTML=d.data.map(o=>renderItem('sorties',o,`
    <div class="item-row"><span class="item-name">${esc(o.callsign)}</span><span class="tag tag-${o.status.toLowerCase()}">${o.status}</span></div>
    <div class="item-desc">${esc(o.mission_type)} — ${esc(o.location||'Unknown AO')}</div>
    <div class="item-meta"><span><i class="fas fa-users"></i>${o.personnel} PAX</span><span><i class="fas fa-clock"></i>${timeAgo(o.updated_at)}</span></div>
  `)).join('');
}

function renderItem(type,o,inner){
  const actions=s2Authed?`<div class="item-actions"><button class="btn-icon" onclick="event.stopPropagation();openEditModal('${type}',${o.id})"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteItem('${type}',${o.id})"><i class="fas fa-trash"></i></button></div>`:'';
  return `<div class="item" ondblclick="openEditModal('${type}',${o.id})">${inner}${actions}</div>`;
}

// CRUD forms
const FORMS={
  operations:[{k:'codename',l:'Codename',t:'text'},{k:'status',l:'Status',t:'select',opts:['ACTIVE','COMPLETED','FAILED','PENDING']},{k:'priority',l:'Priority',t:'select',opts:['CRITICAL','HIGH','MEDIUM','LOW']},{k:'brief',l:'Brief',t:'textarea'},{k:'commander',l:'Commander',t:'text'}],
  reports:[{k:'title',l:'Title',t:'text'},{k:'classification',l:'Classification',t:'select',opts:['TOP SECRET','SECRET','CONFIDENTIAL','UNCLASSIFIED']},{k:'content',l:'Content',t:'textarea'},{k:'source',l:'Source',t:'select',opts:['HUMINT','SIGINT','CYBER','OSINT','GEOINT']}],
  sorties:[{k:'callsign',l:'Callsign',t:'text'},{k:'mission_type',l:'Mission Type',t:'select',opts:['RECON','STRIKE','EXTRACTION','PATROL','ESCORT']},{k:'location',l:'Location',t:'text'},{k:'status',l:'Status',t:'select',opts:['DEPLOYED','RTB','STANDBY','MIA']},{k:'personnel',l:'Personnel',t:'number'}]
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
  if(!s2Authed){showAuthModal();return;}
  editState={type,id:null};
  document.getElementById('editTitle').textContent='CREATE — '+type.toUpperCase();
  document.getElementById('editBody').innerHTML=buildForm(type,null);
  document.getElementById('editSave').onclick=()=>saveItem();
  document.getElementById('editModal').classList.add('show');
}

async function openEditModal(type,id){
  if(!s2Authed){showAuthModal();return;}
  const r=await fetch(API+'?action=list&type='+type),d=await r.json();
  const item=d.data.find(x=>x.id==id);if(!item)return;
  editState={type,id};
  document.getElementById('editTitle').textContent='EDIT — '+type.toUpperCase();
  document.getElementById('editBody').innerHTML=buildForm(type,item);
  document.getElementById('editSave').onclick=()=>saveItem();
  document.getElementById('editModal').classList.add('show');
}

async function saveItem(){
  const fields={};FORMS[editState.type].forEach(f=>{fields[f.k]=document.getElementById('f_'+f.k).value;});
  const body={password:'S2',type:editState.type,fields};
  const action=editState.id?'update':'create';
  if(editState.id)body.id=editState.id;
  const r=await fetch(API+'?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const d=await r.json();
  if(d.success){closeModal('editModal');loadAll();}else alert(d.error);
}

async function deleteItem(type,id){
  if(!confirm('⚠ CONFIRM DELETE — This action cannot be undone'))return;
  const r=await fetch(API+'?action=delete',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({password:'S2',type,id})});
  const d=await r.json();if(d.success)loadAll();else alert(d.error);
}

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function timeAgo(dt){
  const diff=Math.floor((Date.now()-new Date(dt).getTime())/1000);
  if(diff<60)return diff+'s ago';if(diff<3600)return Math.floor(diff/60)+'m ago';
  if(diff<86400)return Math.floor(diff/3600)+'h ago';return Math.floor(diff/86400)+'d ago';
}

loadAll();

// ==========================================
// INTELLIGENCE MAP & MISSION PLANNING
// ==========================================
let mapInst = null;
let playerLayer = new L.LayerGroup();
let drawLayer = new L.FeatureGroup();
let currentMap = 'colombia';
let drawControl = null;

// Mock MGRS_CRS for Arma3Map compatibility
window.Arma3Map = { Maps: {} };
window.MGRS_CRS = function(a, b, c) {
    return L.extend({}, L.CRS.Simple, {
        transformation: new L.Transformation(a, 0, -b, c)
    });
};

function armaToLatLng(x, y) {
    // In Arma CRS, X is Lng, Y is Lat. 
    // Usually mapUtils.js does map.unproject([x, y], map.getMaxZoom())
    // For standard Leaflet Simple CRS with transformation, LatLng is [y, x] but depending on signs.
    return [y, x];
}

async function loadMapScript(mapName) {
    return new Promise((resolve, reject) => {
        if (Arma3Map.Maps[mapName]) return resolve();
        const script = document.createElement('script');
        script.src = `https://jetelain.github.io/Arma3Map/maps/${mapName}.js`;
        script.onload = resolve;
        script.onerror = () => {
            console.error(`Failed to load ${mapName}.js`);
            // Fallback config if script fails (Use PLANOPS Atlas tiles for Colombia)
            Arma3Map.Maps[mapName] = {
                CRS: L.CRS.Simple,
                tilePattern: `https://plan-ops.fr/tiles/${mapName}/{z}/{x}/{y}.png`,
                maxZoom: 6, minZoom: 0, defaultZoom: 3, center: [10250, 10250], worldSize: 20480
            };
            resolve();
        };
        document.head.appendChild(script);
    });
}

async function initIntelMap() {
    await loadMapScript(currentMap);
    const config = Arma3Map.Maps[currentMap];
    
    if (mapInst) {
        mapInst.remove();
    }
    
    mapInst = L.map('intelMap', {
        crs: config.CRS || L.CRS.Simple,
        minZoom: config.minZoom || 0,
        maxZoom: config.maxZoom || 6,
        attributionControl: false
    });
    
    // Add Tiles
    let tileUrl = config.tilePattern || `https://jetelain.github.io/Arma3Map/maps/${currentMap}/{z}/{x}/{y}.png`;
    // Fix tileUrl if it starts with /maps
    if (tileUrl.startsWith('/maps')) tileUrl = 'https://jetelain.github.io/Arma3Map' + tileUrl;
    
    L.tileLayer(tileUrl, { noWrap: true, tms: tileUrl.includes('plan-ops'), bounds: config.worldSize ? [[0,0], [config.worldSize, config.worldSize]] : undefined }).addTo(mapInst);
    
    mapInst.setView(armaToLatLng(config.center[0], config.center[1]), config.defaultZoom || 3);
    
    playerLayer.addTo(mapInst);
    drawLayer.addTo(mapInst);
    
    // Setup Drawing Tools
    if (drawControl) mapInst.removeControl(drawControl);
    drawControl = new L.Control.Draw({
        edit: { featureGroup: drawLayer },
        draw: { circle: false, circlemarker: false, rectangle: false }
    });
    mapInst.addControl(drawControl);
    
    // Draw Events
    mapInst.on(L.Draw.Event.CREATED, async function(e) {
        const layer = e.layer;
        drawLayer.addLayer(layer);
        await saveDrawing(layer.toGeoJSON());
    });
    
    // Trigger initial polls
    pollPlayers();
    pollDrawings();
}

document.getElementById('mapSelector')?.addEventListener('change', async (e) => {
    currentMap = e.target.value;
    await initIntelMap();
});

// --- PLAYER TRACKING (MOCK API) ---
let playerMarkers = {};
async function pollPlayers() {
    try {
        const r = await fetch(`api/mock_players.php?map=${currentMap}`);
        const d = await r.json();
        if (d.success) {
            const currentIds = new Set(d.data.map(p => p.id));
            // Remove old
            Object.keys(playerMarkers).forEach(id => {
                if (!currentIds.has(parseInt(id))) {
                    playerLayer.removeLayer(playerMarkers[id]);
                    delete playerMarkers[id];
                }
            });
            // Update/Add new
            d.data.forEach(p => {
                const latlng = armaToLatLng(p.x, p.y);
                if (playerMarkers[p.id]) {
                    playerMarkers[p.id].setLatLng(latlng);
                } else {
                    const m = L.marker(latlng, {
                        icon: L.divIcon({ className: 'player-marker', iconSize: [12,12] })
                    });
                    m.bindTooltip(p.name, { permanent: true, direction: 'right', className: 'player-tooltip' });
                    playerMarkers[p.id] = m;
                    playerLayer.addLayer(m);
                }
            });
        }
    } catch (e) { console.error('Poll Error:', e); }
    setTimeout(pollPlayers, 2000); // 2s polling
}

// --- MISSION PLANNING SYNC ---
async function pollDrawings() {
    try {
        const r = await fetch(`api/mission_plan.php?map=${currentMap}`);
        const d = await r.json();
        if (d.success) {
            drawLayer.clearLayers(); // Simple reset for demo, in production we'd merge
            L.geoJSON(d.data, {
                onEachFeature: function(feature, layer) {
                    // Optional: bind popup with user_id
                    if(feature.properties && feature.properties.user_id) {
                        layer.bindPopup(`Drawn by: ${esc(feature.properties.user_id)}`);
                    }
                    drawLayer.addLayer(layer);
                }
            });
        }
    } catch(e) {}
    setTimeout(pollDrawings, 5000); // 5s sync poll
}

async function saveDrawing(geojson) {
    // Optionally ask for Callsign/User ID, defaulting to Anonymous
    const userId = localStorage.getItem('s2_callsign') || prompt('Enter your Callsign:') || 'Anonymous';
    localStorage.setItem('s2_callsign', userId);
    
    await fetch('api/mission_plan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save', map: currentMap, geojson, user_id: userId })
    });
}

async function clearMapDrawings() {
    if(!s2Authed) { showAuthModal(); return; }
    if(!confirm('⚠ CONFIRM CLEAR ALL DRAWINGS FOR THIS MAP?')) return;
    const r = await fetch('api/mission_plan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear', map: currentMap, password: 'S2' })
    });
    const d = await r.json();
    if(d.success) { drawLayer.clearLayers(); } else { alert(d.error); }
}

// Hook map init into tabs
document.querySelectorAll('[data-tab="sorties"]').forEach(el => {
    el.addEventListener('click', () => {
        if (!mapInst) {
            setTimeout(initIntelMap, 300);
        } else {
            setTimeout(() => mapInst.invalidateSize(), 300);
        }
    });
});
