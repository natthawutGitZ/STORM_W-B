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
        this._div.innerHTML = 'GRID: ---- | ----';
        return this._div;
    },
    update: function(latlng) {
        if (!latlng) return;
        var a = latLngToArma(latlng);
        if (a.x < 0 || a.y < 0 || a.x > COLOMBIA_CONFIG.worldSize || a.y > COLOMBIA_CONFIG.worldSize) return;
        var gx = String(Math.floor(a.x / 10)).padStart(4, '0').slice(-4);
        var gy = String(Math.floor(a.y / 10)).padStart(4, '0').slice(-4);
        this._div.innerHTML = 'GRID: <span class="coord-val">'+gx+'</span> | <span class="coord-val">'+gy+'</span>';
    }
});

// ---- FULLSCREEN CONTROL ----
L.Control.Fullscreen = L.Control.extend({
    options: { position: 'topright' },
    onAdd: function() {
        var btn = L.DomUtil.create('button', 'leaflet-bar leaflet-control');
        btn.innerHTML = '<i class="fas fa-expand"></i>';
        btn.style.width = '32px';
        btn.style.height = '32px';
        btn.style.cursor = 'pointer';
        btn.style.backgroundColor = 'var(--bg3)';
        btn.style.color = 'var(--green)';
        btn.style.border = '1px solid var(--border)';
        btn.style.fontSize = '14px';
        btn.onclick = function(e) {
            e.stopPropagation();
            var mapEl = document.getElementById('intelMap');
            if (!document.fullscreenElement) {
                if(mapEl.requestFullscreen) mapEl.requestFullscreen();
            } else {
                if(document.exitFullscreen) document.exitFullscreen();
            }
        };
        return btn;
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
    
    mapInst.addControl(new L.Control.Fullscreen());

    drawControl = new L.Control.Draw({
        position: 'topleft',
        edit: { featureGroup: drawLayer, remove: true, edit: true },
        draw: {
            polyline: { shapeOptions: { color:'#00ff41', weight:3 } },
            polygon: { shapeOptions: { color:'#00ff41', fillOpacity:0.15 } },
            marker: true, 
            circle: false, 
            circlemarker: { shapeOptions: { color:'#00ff41', weight:2, radius:6, fillOpacity:0.8 } },
            rectangle: { shapeOptions: { color:'#00ff41', weight:2, fillOpacity:0.1 } }
        }
    });
    mapInst.addControl(drawControl);

    mapInst.on(L.Draw.Event.CREATED, async function(e) {
        var layer = e.layer;
        var isMarker = (e.layerType === 'marker' || e.layerType === 'circlemarker');
        
        layer.feature = layer.feature || { type: 'Feature', properties: {}, geometry: {} };
        if (e.layerType === 'circlemarker') layer.feature.properties.isCircleMarker = true;

        if (isMarker) {
            var text = prompt('Enter marker text (optional):');
            if (text) {
                layer.feature.properties.text = text;
                layer.bindTooltip(text, { permanent: true, direction: 'right', className: 'marker-text' });
            }
            if (layer.dragging) layer.dragging.enable();
            layer.on('dragend', function() { clearAndResaveAll(); });
            layer.on('dblclick', function(ev) {
                ev.originalEvent.stopPropagation();
                var newText = prompt('Edit marker text:', layer.feature.properties.text || '');
                if (newText !== null) {
                    layer.feature.properties.text = newText;
                    if (newText) layer.bindTooltip(newText, { permanent: true, direction: 'right', className: 'marker-text' });
                    else layer.unbindTooltip();
                    clearAndResaveAll();
                }
            });
        }
        
        drawLayer.addLayer(layer);
        await saveDrawing(layer.toGeoJSON());
    });
    mapInst.on(L.Draw.Event.DELETED, async function() { await clearAndResaveAll(); });
    mapInst.on(L.Draw.Event.EDITED, async function() { await clearAndResaveAll(); });

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
                L.geoJSON(feature, {
                    style: { color:'#00ff41', weight:2, fillOpacity:0.1 },
                    pointToLayer: function(f, latlng) {
                        return (f.properties && f.properties.isCircleMarker) ? L.circleMarker(latlng, { color:'#00ff41', weight:2, radius:6, fillOpacity:0.8 }) : L.marker(latlng);
                    },
                    onEachFeature: function(f, l) {
                        if (f.geometry.type === 'Point') {
                            if (l.dragging) {
                                l.dragging.enable();
                                l.on('dragend', function() { clearAndResaveAll(); });
                            }
                            if (f.properties && f.properties.text) {
                                l.bindTooltip(f.properties.text, { permanent: true, direction: 'right', className: 'marker-text' });
                            }
                            l.on('dblclick', function(ev) {
                                ev.originalEvent.stopPropagation();
                                var newText = prompt('Edit marker text:', f.properties.text || '');
                                if (newText !== null) {
                                    f.properties.text = newText;
                                    if (newText) l.bindTooltip(newText, { permanent: true, direction: 'right', className: 'marker-text' });
                                    else l.unbindTooltip();
                                    clearAndResaveAll();
                                }
                            });
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
    var userId = 'S2';
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
    var userId = 'S2';
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
