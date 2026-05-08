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

const ArmaCRS = L.extend({}, L.CRS.Simple, {
    transformation: new L.Transformation(COLOMBIA_CONFIG.factorX, 0, -COLOMBIA_CONFIG.factorY, COLOMBIA_CONFIG.tileSize)
});

function armaToLatLng(x, y) { return [y, x]; }
function latLngToArma(latlng) { return { x: Math.round(latlng.lng), y: Math.round(latlng.lat) }; }

// ---- GRID LINES ----
function createGrid(map) {
    if (gridLayer) map.removeLayer(gridLayer);
    gridLayer = L.layerGroup();
    var step = 1000, ws = COLOMBIA_CONFIG.worldSize;
    var lineStyle = { color: 'rgba(0,0,0,0.15)', weight: 1, dashArray: '4,4' };
    
    for (var i = 0; i <= ws; i += step) {
        L.polyline([armaToLatLng(i, 0), armaToLatLng(i, ws)], lineStyle).addTo(gridLayer);
        L.polyline([armaToLatLng(0, i), armaToLatLng(ws, i)], lineStyle).addTo(gridLayer);
    }
    gridLayer.addTo(map);
}

// ---- GRID EDGE LABELS (PLANOPS Style) ----
function updateGridEdgeLabels() {
    if (!mapInst) return;
    var bounds = mapInst.getBounds();
    var nw = latLngToArma(bounds.getNorthWest());
    var se = latLngToArma(bounds.getSouthEast());
    var topEl = document.getElementById('gridEdgeTop');
    var leftEl = document.getElementById('gridEdgeLeft');
    if (!topEl || !leftEl) return;
    
    topEl.innerHTML = ''; leftEl.innerHTML = '';
    var step = 1000;
    var startX = Math.floor(nw.x / step) * step;
    var endX = Math.ceil(se.x / step) * step;
    var startY = Math.floor(nw.y / step) * step;
    var endY = Math.ceil(se.y / step) * step;

    for (var x = startX; x <= endX; x += step) {
        var ll = armaToLatLng(x, nw.y);
        var p = mapInst.latLngToContainerPoint(ll);
        if (p.x >= 0 && p.x <= mapInst.getSize().x) {
            var el = document.createElement('div');
            el.className = 'grid-edge-label';
            el.style.left = p.x + 'px';
            el.style.bottom = '2px';
            el.style.transform = 'translateX(-50%)';
            el.innerText = String(Math.round(x/100)).padStart(2, '0');
            topEl.appendChild(el);
        }
    }
    for (var y = startY; y <= endY; y += step) {
        var ll = armaToLatLng(nw.x, y);
        var p = mapInst.latLngToContainerPoint(ll);
        if (p.y >= 0 && p.y <= mapInst.getSize().y) {
            var el = document.createElement('div');
            el.className = 'grid-edge-label';
            el.style.top = p.y + 'px';
            el.innerText = String(Math.round(y/100)).padStart(2, '0');
            leftEl.appendChild(el);
        }
    }
}

// ---- CUSTOM UI STATE ----
let currentDrawAction = null;
let activeToolBtn = null;
let pendingLayer = null;

function setTool(toolName, btnEl) {
    if (currentDrawAction) { currentDrawAction.disable(); currentDrawAction = null; }
    document.querySelectorAll('.map-tool-btn').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    
    if (toolName === 'pan') {
        // Default Leaflet behavior
    } else if (toolName === 'line') {
        currentDrawAction = new L.Draw.Polyline(mapInst, { shapeOptions: { color:'#000000', weight:3 }});
        currentDrawAction.enable();
    } else if (toolName === 'area') {
        currentDrawAction = new L.Draw.Polygon(mapInst, { shapeOptions: { color:'#000000', weight:2, fillOpacity:0.2 }});
        currentDrawAction.enable();
    } else if (toolName === 'natoSymbol') {
        currentDrawAction = new L.Draw.Marker(mapInst, { icon: L.divIcon({className:'nato-icon', html:'<i class="fas fa-vector-square" style="color:#0066ff;font-size:24px;"></i>'}) });
        currentDrawAction.enable();
    } else if (toolName === 'basicSymbol') {
        currentDrawAction = new L.Draw.Marker(mapInst, { icon: L.divIcon({className:'basic-symbol-icon', html:'<i class="fas fa-circle" style="color:#000;font-size:16px;"></i>'}) });
        currentDrawAction.enable();
    } else if (toolName === 'point') {
        currentDrawAction = new L.Draw.Marker(mapInst);
        currentDrawAction.enable();
    } else if (toolName === 'flag') {
        currentDrawAction = new L.Draw.Marker(mapInst, { icon: L.divIcon({className:'basic-symbol-icon', html:'<i class="fas fa-flag" style="color:#ff0000;font-size:20px;"></i>'}) });
        currentDrawAction.enable();
    }
}

// ---- INIT MAP ----
async function initIntelMap() {
    if (mapInst) { mapInst.remove(); mapInst = null; }
    if (playerPollTimer) clearTimeout(playerPollTimer);
    if (drawPollTimer) clearTimeout(drawPollTimer);
    playerLayer = L.layerGroup();
    drawLayer = L.featureGroup();

    mapInst = L.map('intelMap', {
        crs: ArmaCRS, minZoom: COLOMBIA_CONFIG.minZoom, maxZoom: COLOMBIA_CONFIG.maxZoom,
        attributionControl: false, zoomControl: false
    });
    
    var bounds = L.latLngBounds(armaToLatLng(0,0), armaToLatLng(COLOMBIA_CONFIG.worldSize, COLOMBIA_CONFIG.worldSize));
    mapInst.setMaxBounds(bounds);
    L.tileLayer(COLOMBIA_CONFIG.tileUrl, { tileSize: COLOMBIA_CONFIG.tileSize, noWrap: true, bounds: bounds, maxZoom: COLOMBIA_CONFIG.maxZoom }).addTo(mapInst);
    mapInst.setView(armaToLatLng(COLOMBIA_CONFIG.center[0], COLOMBIA_CONFIG.center[1]), COLOMBIA_CONFIG.defaultZoom);
    
    playerLayer.addTo(mapInst);
    drawLayer.addTo(mapInst);
    createGrid(mapInst);

    // Coord display update
    mapInst.on('mousemove', function(e) {
        var a = latLngToArma(e.latlng);
        if (a.x >= 0 && a.y >= 0 && a.x <= COLOMBIA_CONFIG.worldSize && a.y <= COLOMBIA_CONFIG.worldSize) {
            document.getElementById('mapCoordDisplay').innerHTML = String(Math.floor(a.x / 10)).padStart(4, '0') + ' - ' + String(Math.floor(a.y / 10)).padStart(4, '0');
        }
    });

    mapInst.on('move', updateGridEdgeLabels);
    mapInst.on('zoom', updateGridEdgeLabels);
    updateGridEdgeLabels();

    // Toolbar Listeners
    document.querySelectorAll('.map-tool-btn[data-tool]').forEach(btn => {
        btn.addEventListener('click', function() { setTool(this.dataset.tool, this); });
    });
    document.getElementById('toolZoomIn').onclick = () => mapInst.zoomIn();
    document.getElementById('toolZoomOut').onclick = () => mapInst.zoomOut();
    document.getElementById('toolFullscreen').onclick = () => {
        var mapEl = document.querySelector('.map-editor-wrap');
        if (!document.fullscreenElement) { if(mapEl.requestFullscreen) mapEl.requestFullscreen(); } 
        else { if(document.exitFullscreen) document.exitFullscreen(); }
    };

    // Draw Created
    mapInst.on(L.Draw.Event.CREATED, async function(e) {
        var layer = e.layer;
        layer.feature = layer.feature || { type: 'Feature', properties: {}, geometry: {} };
        
        var tool = document.querySelector('.map-tool-btn.active').dataset.tool;
        layer.feature.properties.toolType = tool;

        if (tool === 'natoSymbol') {
            pendingLayer = layer;
            openMapModal('modalNatoSymbol');
        } else if (tool === 'basicSymbol') {
            pendingLayer = layer;
            openMapModal('modalBasicSymbol');
        } else if (tool === 'line' || tool === 'area') {
            pendingLayer = layer;
            openMapModal('modalLineProps');
        } else {
            // point, flag
            var text = prompt('Enter label:');
            if (text) {
                layer.feature.properties.text = text;
                layer.bindTooltip(text, { permanent: true, direction: 'right', className: 'marker-text' });
            }
            finalizeLayer(layer);
        }
        
        // Reset tool to pan after draw (PLANOPS style)
        setTool('pan', document.getElementById('toolPan'));
    });

    pollPlayers();
    pollDrawings();
}

// ---- MODAL LOGIC ----
function openMapModal(id) { document.getElementById(id).classList.add('show'); }
function closeMapModal(id) { 
    document.getElementById(id).classList.remove('show'); 
    if (pendingLayer && id !== 'modalSearch') {
        // If cancelled, don't add to map
        pendingLayer = null;
    }
}
function cancelLineDraw() { closeMapModal('modalLineProps'); }

function finalizeLayer(layer) {
    if (layer.dragging && layer.setLatLng) {
        layer.dragging.enable();
        layer.on('dragend', function() { clearAndResaveAll(); });
    }
    drawLayer.addLayer(layer);
    saveDrawing(layer.toGeoJSON());
    pendingLayer = null;
}

// Bind Modal Save Buttons
document.getElementById('natoInsertBtn').onclick = function() {
    if(!pendingLayer) return;
    pendingLayer.feature.properties.affiliation = document.querySelector('.aff-btn.active').dataset.aff;
    pendingLayer.feature.properties.symbolType = document.getElementById('natoSymbolType').value;
    pendingLayer.feature.properties.text = document.getElementById('natoDesignation').value;
    
    // Apply styling based on selection (mocked visual for now)
    var color = document.querySelector('.aff-btn.active').style.getPropertyValue('--aff-color') || '#000';
    pendingLayer.setIcon(L.divIcon({className:'nato-icon', html:`<div style="background:${color};border:2px solid #000;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:bold;">${pendingLayer.feature.properties.text || 'X'}</div>`}));
    
    finalizeLayer(pendingLayer);
    closeMapModal('modalNatoSymbol');
};

document.getElementById('basicInsertBtn').onclick = function() {
    if(!pendingLayer) return;
    var shape = document.getElementById('basicShape').value;
    var color = document.querySelector('#modalBasicSymbol .color-btn.active').dataset.color;
    var text = document.getElementById('basicLabel').value;
    
    pendingLayer.feature.properties.color = color;
    pendingLayer.feature.properties.shape = shape;
    pendingLayer.feature.properties.text = text;
    
    var shapeHtml = '<i class="fas fa-circle" style="color:'+color+';font-size:16px;"></i>';
    if(shape==='mil_square') shapeHtml = '<i class="fas fa-square" style="color:'+color+';font-size:16px;"></i>';
    
    pendingLayer.setIcon(L.divIcon({className:'basic-symbol-icon', html:shapeHtml}));
    if(text) pendingLayer.bindTooltip(text, { permanent: true, direction: 'right', className: 'marker-text' });
    
    finalizeLayer(pendingLayer);
    closeMapModal('modalBasicSymbol');
};

document.getElementById('lineSaveBtn').onclick = function() {
    if(!pendingLayer) return;
    var color = document.querySelector('#modalLineProps .color-btn.active').dataset.color;
    var weight = document.getElementById('lineWeight').value;
    pendingLayer.setStyle({ color: color, weight: weight });
    pendingLayer.feature.properties.color = color;
    finalizeLayer(pendingLayer);
    closeMapModal('modalLineProps');
};

// Selection Helpers
document.querySelectorAll('.aff-btn').forEach(btn => {
    btn.onclick = function() { document.querySelectorAll('.aff-btn').forEach(b=>b.classList.remove('active')); this.classList.add('active'); }
});
document.querySelectorAll('.color-btn').forEach(btn => {
    btn.onclick = function() { this.parentElement.querySelectorAll('.color-btn').forEach(b=>b.classList.remove('active')); this.classList.add('active'); }
});

// Search
document.getElementById('toolSearch').onclick = () => openMapModal('modalSearch');
document.getElementById('searchGoBtn').onclick = () => {
    var x = parseInt(document.getElementById('searchX').value);
    var y = parseInt(document.getElementById('searchY').value);
    if (!isNaN(x) && !isNaN(y)) {
        mapInst.setView(armaToLatLng(x, y), 5);
        closeMapModal('modalSearch');
    }
};

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
    } catch(e) { }
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
                L.geoJSON(feature, {
                    style: function(f) { return { color: f.properties.color || '#000', weight: 3 }; },
                    pointToLayer: function(f, latlng) {
                        var tool = f.properties.toolType;
                        if (tool === 'natoSymbol') {
                            var c = f.properties.color || '#80e0ff';
                            return L.marker(latlng, {icon: L.divIcon({className:'nato-icon', html:`<div style="background:${c};border:2px solid #000;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:bold;">${f.properties.text || 'X'}</div>`})});
                        } else if (tool === 'basicSymbol') {
                            var cc = f.properties.color || '#000';
                            return L.marker(latlng, {icon: L.divIcon({className:'basic-symbol-icon', html:`<i class="fas fa-circle" style="color:${cc};font-size:16px;"></i>`})});
                        } else {
                            return L.marker(latlng);
                        }
                    },
                    onEachFeature: function(f, l) {
                        if (f.geometry.type === 'Point' && l.dragging) {
                            l.dragging.enable();
                            l.on('dragend', function() { clearAndResaveAll(); });
                        }
                        if (f.properties && f.properties.text && f.properties.toolType !== 'natoSymbol') {
                            l.bindTooltip(f.properties.text, { permanent: true, direction: 'right', className: 'marker-text' });
                        }
                        drawLayer.addLayer(l);
                    }
                });
            });
        }
    } catch(e) {}
    drawPollTimer = setTimeout(pollDrawings, 5000);
}

async function saveDrawing(geojson) {
    await fetch('api/mission_plan.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'save', map:currentMap, geojson:geojson, user_id:'S2' }) });
}

async function clearAndResaveAll() {
    await fetch('api/mission_plan.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'clear', map:currentMap, password:'S2' }) });
    var layers = []; drawLayer.eachLayer(l => layers.push(l));
    for (var i = 0; i < layers.length; i++) {
        var gj = layers[i].feature || layers[i].toGeoJSON();
        if (gj) await saveDrawing(gj);
    }
}

// Hook map init
document.querySelectorAll('[data-tab="sorties"]').forEach(function(el) {
    el.addEventListener('click', function() {
        if (!mapInst) { setTimeout(initIntelMap, 300); }
        else { setTimeout(function(){ mapInst.invalidateSize(); updateGridEdgeLabels(); }, 300); }
    });
});

