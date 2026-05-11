// ==========================================
// INTELLIGENCE MAP & MISSION PLANNING (ARMA3TACMAP PORT)
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
    tileSize: 323, maxZoom: 6, minZoom: 0, defaultZoom: 2,
    factorX: 0.01575, factorY: 0.01575, worldSize: 20480, center: [10250, 10250]
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
            el.className = 'grid-edge-label'; el.style.left = p.x + 'px'; el.style.bottom = '2px'; el.style.transform = 'translateX(-50%)'; el.innerText = String(Math.round(x/100)).padStart(2, '0');
            topEl.appendChild(el);
        }
    }
    for (var y = startY; y <= endY; y += step) {
        var ll = armaToLatLng(nw.x, y);
        var p = mapInst.latLngToContainerPoint(ll);
        if (p.y >= 0 && p.y <= mapInst.getSize().y) {
            var el = document.createElement('div');
            el.className = 'grid-edge-label'; el.style.top = p.y + 'px'; el.innerText = String(Math.round(y/100)).padStart(2, '0');
            leftEl.appendChild(el);
        }
    }
}

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
    missionSelection = null;
    
    if (toolName === 'pan') { mapInst.dragging.enable(); } 
    else { mapInst.dragging.disable(); }
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
        "colorwhite": "#ffffff", "colorunknown": "#b29900", "colorblufor": "#004c99",
        "coloropfor": "#7f0000", "colorindependent": "#007f00"
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
    var totalDist = 0;
    for(var i=1; i<posList.length; i++) {
        var dx = posList[i][0] - posList[i-1][0];
        var dy = posList[i][1] - posList[i-1][1];
        totalDist += Math.sqrt(dx*dx + dy*dy) * 10;
    }
    marker.bindTooltip("Distance: " + Math.round(totalDist) + "m", {permanent:true});
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
        openMapModal('modalNatoSymbol'); 
        document.getElementById('natoInsertBtn').innerText = 'Update';
    } else if (modalMarkerData.type === 'line') {
        openMapModal('modalLineProps');
        document.getElementById('lineDeleteBtn').style.display = 'block';
    } else if (modalMarkerData.type === 'mission') {
        // Just delete for now to recreate
        if(confirm("Delete this mission?")) {
            backend.removeMarker(modalMarkerId);
        }
    } else if (modalMarkerData.type === 'note') {
        openMapModal('modalNote');
        document.getElementById('noteDeleteBtn').style.display = 'block';
        if(tinymce.get('noteContent')) tinymce.get('noteContent').setContent(modalMarkerData.config.content || '');
    } else if (modalMarkerData.type === 'measure') {
        openMapModal('modalMeasure');
        document.getElementById('measureDeleteBtn').style.display = 'block';
    } else if (modalMarkerData.type === 'basic') {
        openMapModal('modalBasicSymbol');
    }
}

function addOrUpdateMarker(map, markers, marker, canEdit, backend, opacity, layer) {
    var markerId = marker.id;
    var markerData = marker.data;
    var existing = markers[markerId];

    if (markerData.type == 'line') {
        var posList = posToPoints(markerData.pos);
        var color = colorToCss(markerData.config.color);
        if (existing) {
            existing.setLatLngs(posList); existing.setStyle({ color: color, weight: markerData.config.weight||3 });
            existing.options.markerData = markerData;
        } else {
            var mapMarker = L.polyline(posList, { color: color, weight: markerData.config.weight||3, interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
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
            var mapMarker = L.polyline(posList, { color: '#ffff00', weight: 2, dashArray: '4,4', interactive: canEdit, markerId: markerId, markerData: markerData }).addTo(layer.group);
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
            icon = L.divIcon({ className: 'basic-symbol-icon', html: '<i class="fas fa-' + (markerData.symbol.replace('mil_','')) + '" style="color:'+colorToCss(markerData.config.color)+';font-size:'+size+'px;"></i>' });
        }
        
        if (existing) {
            existing.setIcon(icon); existing.setLatLng(markerData.pos); existing.options.markerData = markerData;
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

// Map Click Logic for drawing
function onMapClick(e) {
    if (!s2Authed) { showAuthModal(); return; }
    var latlng = e.latlng;
    var tool = currentDrawAction;
    if (!tool || tool === 'pan') return;
    
    if (tool === 'line') {
        var point = [latlng.lat, latlng.lng];
        if (!currentLine) {
            currentLine = L.polyline([point, point], { color: '#000000', weight: 3, interactive: false }).addTo(mapInst);
        } else if (e.originalEvent.shiftKey) { // append
            var data = currentLine.getLatLngs();
            data[data.length - 1] = point;
            data.push(point);
            currentLine.setLatLngs(data);
        } else {
            var data = currentLine.getLatLngs();
            data[data.length - 1] = point;
            currentLine.remove(); currentLine = null;
            backend.addMarker(null, { type: 'line', symbol: 'line', config: { color: document.querySelector('#lineColorPicker .active') ? document.querySelector('#lineColorPicker .active').dataset.color : '#000000' }, pos: data.map(function (p) { return [p.lat, p.lng]; }).flat() });
            setTool('pan', document.getElementById('toolPan'));
        }
    } else if (tool === 'measure') {
        var point = [latlng.lat, latlng.lng];
        if (!currentMeasure) {
            currentMeasure = L.polyline([point, point], { color: '#ffff00', weight: 2, dashArray: '4', interactive: false }).addTo(mapInst);
        } else {
            var data = currentMeasure.getLatLngs();
            currentMeasure.remove(); currentMeasure = null;
            backend.addMarker(null, { type: 'measure', symbol: 'measure', config: {}, pos: data.map(function (p) { return [p.lat, p.lng]; }).flat() });
            setTool('pan', document.getElementById('toolPan'));
        }
    } else if (tool === 'mission') {
        if (!missionSelection) { openMapModal('modalMissionSelector'); return; }
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
        openMapModal('modalNote');
        if(tinymce.get('noteContent')) tinymce.get('noteContent').setContent('');
        setTool('pan', document.getElementById('toolPan'));
    } else if (tool === 'natoSymbol') {
        clickPosition = [latlng.lat, latlng.lng];
        modalMarkerId = null;
        openMapModal('modalNatoSymbol');
        setTool('pan', document.getElementById('toolPan'));
    } else if (tool === 'basicSymbol') {
        clickPosition = [latlng.lat, latlng.lng];
        modalMarkerId = null;
        openMapModal('modalBasicSymbol');
        setTool('pan', document.getElementById('toolPan'));
    }
}

// Map Mousemove for rubberbanding
function onMapMouseMove(e) {
    var latlng = e.latlng;
    if (currentLine) {
        var data = currentLine.getLatLngs();
        data[data.length - 1] = [latlng.lat, latlng.lng];
        currentLine.setLatLngs(data);
    }
    if (currentMeasure) {
        var data = currentMeasure.getLatLngs();
        data[data.length - 1] = [latlng.lat, latlng.lng];
        currentMeasure.setLatLngs(data);
    }
    if (currentMission && missionSelection && missionSelection.points) {
        var pts = missionSelection.points.slice();
        pts.push([latlng.lat, latlng.lng]);
        var result = generateMission(missionSelection.mission, pts, missionSelection.size);
        currentMission.setLatLngs(result.lines);
    }
    
    var a = latLngToArma(latlng);
    if (a.x >= 0 && a.y >= 0 && a.x <= COLOMBIA_CONFIG.worldSize && a.y <= COLOMBIA_CONFIG.worldSize) {
        document.getElementById('mapCoordDisplay').innerHTML = String(Math.floor(a.x / 10)).padStart(4, '0') + ' - ' + String(Math.floor(a.y / 10)).padStart(4, '0');
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
        attributionControl: false, zoomControl: false,
        doubleClickZoom: false // Important for drawing
    });
    
    var bounds = L.latLngBounds(armaToLatLng(0,0), armaToLatLng(COLOMBIA_CONFIG.worldSize, COLOMBIA_CONFIG.worldSize));
    mapInst.setMaxBounds(bounds);
    L.tileLayer(COLOMBIA_CONFIG.tileUrl, { tileSize: COLOMBIA_CONFIG.tileSize, noWrap: true, bounds: bounds, maxZoom: COLOMBIA_CONFIG.maxZoom }).addTo(mapInst);
    mapInst.setView(armaToLatLng(COLOMBIA_CONFIG.center[0], COLOMBIA_CONFIG.center[1]), COLOMBIA_CONFIG.defaultZoom);
    
    playerLayer.addTo(mapInst);
    drawLayer.addTo(mapInst);
    createGrid(mapInst);

    mapInst.on('click', onMapClick);
    mapInst.on('mousemove', onMapMouseMove);
    mapInst.on('move', updateGridEdgeLabels);
    mapInst.on('zoom', updateGridEdgeLabels);
    updateGridEdgeLabels();

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

    pollPlayers();
    pollDrawings();
    
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
const NATO_MAPPING = {
    aff: { pending:'0', unknown:'1', assumedFriend:'2', friend:'3', neutral:'4', suspect:'5', hostile:'6' },
    set: { 'Land unit':'10', 'Air':'01', 'Sea surface':'30', 'Equipment':'15' },
    sym: { 'Unspecified':'000000', 'Infantry':'121100', 'Armor':'120500', 'Artillery':'120600', 'Reconnaissance':'120501', 'Engineer':'120700', 'Air Defense':'120100', 'Signal':'121000', 'Medical':'121500', 'Supply':'121600', 'Command and Control':'121104' },
    status: { 'Present':'0', 'Planned':'1', 'Anticipated':'1' },
    echelon: { 'Unspecified':'00', 'Team':'11', 'Squad':'12', 'Section':'13', 'Platoon':'14', 'Company':'15', 'Battalion':'16', 'Regiment':'17', 'Brigade':'18', 'Division':'21', 'Corps':'22' }
};

function generateSIDC() {
    var affKey = document.querySelector('.aff-btn.active').dataset.aff;
    var setKey = document.getElementById('natoSymbolSet').value;
    var symKey = document.getElementById('natoSymbolType').value;
    var statusKey = document.getElementById('natoStatus').value;
    var echKey = document.getElementById('natoEchelon').value;
    
    var identity = NATO_MAPPING.aff[affKey] || '0';
    var symbolSet = NATO_MAPPING.set[setKey] || '10';
    var status = NATO_MAPPING.status[statusKey] || '0';
    var echelon = NATO_MAPPING.echelon[echKey] || '00';
    var entity = NATO_MAPPING.sym[symKey] || '000000';
    return '10' + '0' + identity + symbolSet + status + '0' + echelon + entity + '00' + '00';
}

function updateNatoPreview() {
    var sidc = generateSIDC();
    var desig = document.getElementById('natoDesignation').value;
    var info = document.getElementById('natoAdditional').value;
    var scale = parseInt(document.getElementById('natoScale').value) || 100;
    
    if (typeof ms !== 'undefined') {
        var sym = new ms.Symbol(sidc, { size: scale * 0.3, uniqueDesignation: desig, additionalInformation: info });
        document.getElementById('natoPreview').innerHTML = '';
        document.getElementById('natoPreview').appendChild(sym.asDOM());
    }
}

['natoSymbolSet', 'natoSymbolType', 'natoStatus', 'natoEchelon', 'natoDesignation', 'natoAdditional', 'natoScale'].forEach(id => {
    document.getElementById(id).addEventListener('input', updateNatoPreview);
});
document.querySelectorAll('.aff-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.aff-btn').forEach(b=>b.classList.remove('active')); this.classList.add('active');
        updateNatoPreview();
    });
});

// MODAL SAVES
document.getElementById('natoInsertBtn').onclick = function() {
    var sidc = generateSIDC();
    var desig = document.getElementById('natoDesignation').value;
    var info = document.getElementById('natoAdditional').value;
    var scale = parseInt(document.getElementById('natoScale').value) || 100;
    var config = { uniqueDesignation: desig, additionalInformation: info };

    if(modalMarkerId) {
        modalMarkerData.symbol = sidc; modalMarkerData.config = config; modalMarkerData.scale = scale/100;
        backend.updateMarkerToLayer(modalMarkerId, null, modalMarkerData);
    } else {
        backend.addMarker(null, { type: 'mil', symbol: sidc, config: config, scale: scale/100, pos: clickPosition });
    }
    closeMapModal('modalNatoSymbol');
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
                    m.bindTooltip(p.name, { permanent:true, direction:'right', className:'player-tooltip' });
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
    drawPollTimer = setTimeout(pollDrawings, 5000);
}

// Hook map init
document.querySelectorAll('[data-tab="sorties"]').forEach(function(el) {
    el.addEventListener('click', function() {
        if (!mapInst) { setTimeout(initIntelMap, 300); }
        else { setTimeout(function(){ mapInst.invalidateSize(); updateGridEdgeLabels(); }, 300); }
    });
});
