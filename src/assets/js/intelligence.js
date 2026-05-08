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

function renderItem(type,o,inner){
  const actions=s2Authed?`<div class="item-actions"><button class="btn-icon" onclick="event.stopPropagation();openEditModal('${type}',${o.id})"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteItem('${type}',${o.id})"><i class="fas fa-trash"></i></button></div>`:'';
  return `<div class="item" ondblclick="openEditModal('${type}',${o.id})">${inner}${actions}</div>`;
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
let playerLayer = null;
let drawLayer = null;
let currentMap = 'colombia';
let drawControl = null;
let gridLayer = null;
let playerPollTimer = null;
let drawPollTimer = null;

const COLOMBIA_CONFIG = {
    tileUrl: 'https://atlas.plan-ops.fr/data/1/maps/118/118/{z}/{x}/{y}.webp',
    tileSize: 323,
    maxZoom: 6,
    minZoom: 0,
    defaultZoom: 2,
    factorX: 0.01575,
    factorY: 0.01575,
    worldSize: 20480,
    center: [10250, 10250]
};

// Proper Arma 3 CRS Transformation
const ArmaCRS = L.extend({}, L.CRS.Simple, {
    transformation: new L.Transformation(COLOMBIA_CONFIG.factorX, 0, -COLOMBIA_CONFIG.factorY, COLOMBIA_CONFIG.tileSize)
});

function armaToLatLng(x, y) {
    // With proper CRS, Lat is Y and Lng is X
    return [y, x];
}
function latLngToArma(latlng) {
    return { x: Math.round(latlng.lng), y: Math.round(latlng.lat) };
}

// ---- ARMA3 TACTICAL ICONS (SVG) ----
const TACTICAL_ICONS = [
    { id:'b_hq',     label:'BLUFOR HQ',      color:'#0066ff', svg:'<rect x="2" y="6" width="20" height="12" fill="none" stroke="currentColor" stroke-width="2"/><line x1="2" y1="6" x2="22" y2="18" stroke="currentColor" stroke-width="2"/><line x1="22" y1="6" x2="2" y2="18" stroke="currentColor" stroke-width="2"/><line x1="2" y1="18" x2="2" y2="22" stroke="currentColor" stroke-width="2"/>' },
    { id:'b_inf',    label:'BLUFOR Inf',     color:'#0066ff', svg:'<rect x="2" y="6" width="20" height="12" fill="none" stroke="currentColor" stroke-width="2"/><line x1="2" y1="6" x2="22" y2="18" stroke="currentColor" stroke-width="2"/><line x1="22" y1="6" x2="2" y2="18" stroke="currentColor" stroke-width="2"/>' },
    { id:'b_armor',  label:'BLUFOR Armor',   color:'#0066ff', svg:'<rect x="2" y="6" width="20" height="12" fill="none" stroke="currentColor" stroke-width="2"/><ellipse cx="12" cy="12" rx="6" ry="3" fill="none" stroke="currentColor" stroke-width="2"/>' },
    { id:'b_air',    label:'BLUFOR Air',     color:'#0066ff', svg:'<rect x="2" y="6" width="20" height="12" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6 18 Q 12 6 18 18" fill="none" stroke="currentColor" stroke-width="2"/>' },
    { id:'o_hq',     label:'OPFOR HQ',       color:'#ff0000', svg:'<polygon points="12,2 22,12 12,22 2,12" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/><line x1="12" y1="22" x2="12" y2="26" stroke="currentColor" stroke-width="2"/>' },
    { id:'o_inf',    label:'OPFOR Inf',      color:'#ff0000', svg:'<polygon points="12,2 22,12 12,22 2,12" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/>' },
    { id:'o_armor',  label:'OPFOR Armor',    color:'#ff0000', svg:'<polygon points="12,2 22,12 12,22 2,12" fill="none" stroke="currentColor" stroke-width="2"/><ellipse cx="12" cy="12" rx="4" ry="2" fill="none" stroke="currentColor" stroke-width="2"/>' },
    { id:'mil_destroy', label:'Destroy',     color:'#ff0000', svg:'<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/>' },
    { id:'mil_lz',   label:'LZ',             color:'#00ff00', svg:'<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><line x1="8" y1="8" x2="8" y2="16" stroke="currentColor" stroke-width="2"/><line x1="16" y1="8" x2="16" y2="16" stroke="currentColor" stroke-width="2"/><line x1="8" y1="12" x2="16" y2="12" stroke="currentColor" stroke-width="2"/>' },
    { id:'mil_marker',label:'Marker',        color:'#ffff00', svg:'<circle cx="12" cy="12" r="8" fill="currentColor"/><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>' }
];
let activeMarkerType = null;

function createTacIcon(icon, size) {
    size = size || 32;
    return L.divIcon({
        className: 'tac-icon',
        html: `<div style="width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;color:${icon.color};filter:drop-shadow(0px 0px 4px rgba(0,0,0,0.8));">
                <svg width="24" height="24" viewBox="0 0 24 24" style="overflow:visible;">${icon.svg}</svg>
               </div>`,
        iconSize: [size, size],
        iconAnchor: [size/2, size/2]
    });
}

// ---- GRID OVERLAY ----
function createGrid(map) {
    if (gridLayer) map.removeLayer(gridLayer);
    gridLayer = L.layerGroup();
    var step = 1000, ws = COLOMBIA_CONFIG.worldSize;
    var lineStyle = { color: 'rgba(220,20,60,0.15)', weight: 0.5, dashArray: '2,4' };
    for (var i = 0; i <= ws; i += step) {
        // Vertical lines (constant X)
        L.polyline([armaToLatLng(i, 0), armaToLatLng(i, ws)], lineStyle).addTo(gridLayer);
        // Horizontal lines (constant Y)
        L.polyline([armaToLatLng(0, i), armaToLatLng(ws, i)], lineStyle).addTo(gridLayer);
        
        if (i % 2000 === 0 && i !== 0) {
            // Label for X axis (placed at Y = 200)
            L.marker(armaToLatLng(i, 200), {
                icon: L.divIcon({ className:'grid-label', html: String(Math.round(i/100)).padStart(2, '0'), iconSize:[30,14] }),
                interactive: false
            }).addTo(gridLayer);
            // Label for Y axis (placed at X = 200)
            L.marker(armaToLatLng(200, i), {
                icon: L.divIcon({ className:'grid-label', html: String(Math.round(i/100)).padStart(2, '0'), iconSize:[30,14] }),
                interactive: false
            }).addTo(gridLayer);
        }
    }
    gridLayer.addTo(map);
}

// ---- MOUSE COORDINATE DISPLAY ----
L.Control.Coordinates = L.Control.extend({
    options: { position: 'bottomleft' },
    onAdd: function() {
        this._div = L.DomUtil.create('div', 'coord-display');
        this._div.innerHTML = 'GRID: ------ | ------';
        return this._div;
    },
    update: function(latlng) {
        if (!latlng) return;
        var a = latLngToArma(latlng);
        // Ensure within bounds visually
        if (a.x < 0 || a.y < 0 || a.x > COLOMBIA_CONFIG.worldSize || a.y > COLOMBIA_CONFIG.worldSize) return;
        var gx = String(Math.floor(a.x / 100)).padStart(3, '0');
        var gy = String(Math.floor(a.y / 100)).padStart(3, '0');
        var ex = String(a.x % 100).padStart(2, '0');
        var ey = String(a.y % 100).padStart(2, '0');
        this._div.innerHTML = 'GRID: <span class="coord-val">'+gx+ex+'</span> | <span class="coord-val">'+gy+ey+'</span> &nbsp; ['+a.x+', '+a.y+']';
    }
});

async function initIntelMap() {
    if (mapInst) { mapInst.remove(); mapInst = null; }
    if (playerPollTimer) clearTimeout(playerPollTimer);
    if (drawPollTimer) clearTimeout(drawPollTimer);
    playerLayer = L.layerGroup();
    drawLayer = L.featureGroup();

    mapInst = L.map('intelMap', {
        crs: ArmaCRS,
        minZoom: COLOMBIA_CONFIG.minZoom,
        maxZoom: COLOMBIA_CONFIG.maxZoom,
        attributionControl: false
    });
    
    // Bounds to prevent panning out of the map
    var bounds = L.latLngBounds(armaToLatLng(0,0), armaToLatLng(COLOMBIA_CONFIG.worldSize, COLOMBIA_CONFIG.worldSize));
    mapInst.setMaxBounds(bounds);
    
    L.tileLayer(COLOMBIA_CONFIG.tileUrl, {
        tileSize: COLOMBIA_CONFIG.tileSize,
        noWrap: true,
        bounds: bounds,
        maxZoom: COLOMBIA_CONFIG.maxZoom
    }).addTo(mapInst);
    
    mapInst.setView(armaToLatLng(COLOMBIA_CONFIG.center[0], COLOMBIA_CONFIG.center[1]), COLOMBIA_CONFIG.defaultZoom);
    playerLayer.addTo(mapInst);
    drawLayer.addTo(mapInst);
    createGrid(mapInst);

    var coordCtrl = new L.Control.Coordinates();
    coordCtrl.addTo(mapInst);
    mapInst.on('mousemove', function(e) { coordCtrl.update(e.latlng); });

    drawControl = new L.Control.Draw({
        position: 'topleft',
        edit: { featureGroup: drawLayer, remove: true, edit: true },
        draw: {
            polyline: { shapeOptions: { color:'#dc143c', weight:3 } },
            polygon: { shapeOptions: { color:'#dc143c', fillOpacity:0.15 } },
            marker: true, circle: false, circlemarker: false,
            rectangle: { shapeOptions: { color:'#ffab00', weight:2, fillOpacity:0.1 } }
        }
    });
    mapInst.addControl(drawControl);
    buildTacToolbar();

    mapInst.on(L.Draw.Event.CREATED, async function(e) {
        drawLayer.addLayer(e.layer);
        await saveDrawing(e.layer.toGeoJSON());
    });
    mapInst.on(L.Draw.Event.DELETED, async function() { await clearAndResaveAll(); });
    mapInst.on(L.Draw.Event.EDITED, async function() { await clearAndResaveAll(); });

    mapInst.on('click', function(e) {
        if (!activeMarkerType) return;
        var icon = TACTICAL_ICONS.find(function(i){ return i.id === activeMarkerType; });
        if (!icon) return;
        var marker = L.marker(e.latlng, { icon: createTacIcon(icon) });
        var ap = latLngToArma(e.latlng);
        marker.bindPopup('<b>'+icon.label+'</b><br>Grid: '+ap.x+', '+ap.y);
        marker.feature = { type:'Feature', properties:{ tacIcon: icon.id, label: icon.label }, geometry:{ type:'Point', coordinates:[e.latlng.lng, e.latlng.lat] } };
        drawLayer.addLayer(marker);
        saveDrawing(marker.feature);
        activeMarkerType = null;
        document.querySelectorAll('.tac-btn').forEach(function(b){ b.classList.remove('active'); });
    });

    pollPlayers();
    pollDrawings();
}

function buildTacToolbar() {
    var existing = document.getElementById('tacToolbar');
    if (existing) existing.remove();
    var toolbar = document.createElement('div');
    toolbar.id = 'tacToolbar';
    toolbar.className = 'tac-toolbar';
    toolbar.innerHTML = '<div class="tac-title">MARKERS</div>' +
        TACTICAL_ICONS.map(function(i){ 
            return '<button class="tac-btn" data-type="'+i.id+'" title="'+i.label+'" style="color:'+i.color+'"><svg width="18" height="18" viewBox="0 0 24 24" style="overflow:visible;">'+i.svg+'</svg></button>'; 
        }).join('');
    document.getElementById('intelMap').parentElement.appendChild(toolbar);
    toolbar.querySelectorAll('.tac-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var type = this.dataset.type;
            if (activeMarkerType === type) {
                activeMarkerType = null;
                this.classList.remove('active');
            } else {
                activeMarkerType = type;
                document.querySelectorAll('.tac-btn').forEach(function(b){ b.classList.remove('active'); });
                this.classList.add('active');
            }
        });
    });
}

// --- PLAYER TRACKING ---
let playerMarkers = {};
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
                    m.bindTooltip(p.name, { permanent:true, direction:'right', className:'player-tooltip' });
                    playerMarkers[p.id] = m;
                    playerLayer.addLayer(m);
                }
            });
        }
    } catch(e) { console.error('Poll:', e); }
    playerPollTimer = setTimeout(pollPlayers, 2000);
}

// --- MISSION PLANNING SYNC ---
async function pollDrawings() {
    try {
        var r = await fetch('api/mission_plan.php?map='+currentMap);
        var d = await r.json();
        if (d.success && d.data.features) {
            drawLayer.clearLayers();
            d.data.features.forEach(function(feature) {
                if (feature.properties && feature.properties.tacIcon) {
                    var icon = TACTICAL_ICONS.find(function(i){ return i.id === feature.properties.tacIcon; });
                    if (icon && feature.geometry.type === 'Point') {
                        var ll = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];
                        var m = L.marker(ll, { icon: createTacIcon(icon) });
                        m.bindPopup('<b>'+icon.label+'</b>');
                        m.feature = feature;
                        drawLayer.addLayer(m);
                    }
                } else {
                    L.geoJSON(feature, {
                        style: { color:'#dc143c', weight:2, fillOpacity:0.1 },
                        onEachFeature: function(f, l) {
                            if (f.properties && f.properties.user_id) l.bindPopup('Drawn by: '+esc(f.properties.user_id));
                            drawLayer.addLayer(l);
                        }
                    });
                }
            });
        }
    } catch(e) {}
    drawPollTimer = setTimeout(pollDrawings, 5000);
}

async function saveDrawing(geojson) {
    var userId = localStorage.getItem('s2_callsign') || prompt('Enter your Callsign:') || 'Anonymous';
    localStorage.setItem('s2_callsign', userId);
    await fetch('api/mission_plan.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'save', map:currentMap, geojson:geojson, user_id:userId })
    });
}

async function clearAndResaveAll() {
    await fetch('api/mission_plan.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'clear', map:currentMap, password:'S2' })
    });
    var userId = localStorage.getItem('s2_callsign') || 'Anonymous';
    var layers = [];
    drawLayer.eachLayer(function(layer) { layers.push(layer); });
    for (var i = 0; i < layers.length; i++) {
        var gj = null;
        if (layers[i].feature) gj = layers[i].feature;
        else if (layers[i].toGeoJSON) gj = layers[i].toGeoJSON();
        if (!gj) continue;
        await fetch('api/mission_plan.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ action:'save', map:currentMap, geojson:gj, user_id:userId })
        });
    }
}

async function clearMapDrawings() {
    if(!s2Authed) { showAuthModal(); return; }
    if(!confirm('⚠ CONFIRM CLEAR ALL DRAWINGS FOR THIS MAP?')) return;
    var r = await fetch('api/mission_plan.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'clear', map:currentMap, password:'S2' })
    });
    var d = await r.json();
    if(d.success) { drawLayer.clearLayers(); } else { alert(d.error); }
}

// Hook map init
document.querySelectorAll('[data-tab="sorties"]').forEach(function(el) {
    el.addEventListener('click', function() {
        if (!mapInst) { setTimeout(initIntelMap, 300); }
        else { setTimeout(function(){ mapInst.invalidateSize(); }, 300); }
    });
});
