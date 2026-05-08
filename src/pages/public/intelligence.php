<?php
require_once ROOT_PATH . '/includes/db.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OPS HUB | Operation Stormsurge</title>
<link rel="icon" href="assets/images/logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;500;600;700&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/intelligence.css">
<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
</head>
<body>
<div class="scanline"></div>
<div class="crt-overlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <span>STORMSURGE</span>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item active" data-tab="opshub"><i class="fas fa-crosshairs"></i><span>OPS HUB</span></a>
    <a class="nav-item" data-tab="intel"><i class="fas fa-file-shield"></i><span>INTEL</span></a>
    <a class="nav-item" data-tab="sorties"><i class="fas fa-map"></i><span>INTELLIGENCE MAP</span></a>
  </nav>
  <div class="sidebar-ops" id="sidebarOps">
    <div class="sidebar-ops-title"><i class="fas fa-layer-group"></i> ACTIVE OPS</div>
    <div id="sidebarOpsList"></div>
  </div>
  <div class="sidebar-footer">
    <button class="btn-s2" id="btnAuth" onclick="showAuthModal()"><i class="fas fa-lock"></i> S2 ACCESS</button>
    <div class="sidebar-clock" id="clock"></div>
  </div>
</aside>

<!-- MAIN -->
<main class="main" id="mainContent">
  <!-- TOP BAR -->
  <header class="topbar">
    <button class="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-collapsed')"><i class="fas fa-bars"></i></button>
    <div class="topbar-tabs">
      <button class="tab-btn active" data-tab="opshub">OPS HUB</button>
      <button class="tab-btn" data-tab="intel">INTEL FEED</button>
      <button class="tab-btn" data-tab="sorties">INTELLIGENCE MAP</button>
    </div>
    <div class="topbar-right">
      <div class="threat-level"><span class="threat-label">THREAT:</span><span class="threat-val" id="threatLvl">ELEVATED</span></div>
      <div class="status-dots">
        <span class="sdot green" title="COMMS"></span>
        <span class="sdot amber" title="INTEL"></span>
        <span class="sdot red pulse" title="ALERT"></span>
      </div>
    </div>
  </header>

  <!-- TAB: OPS HUB -->
  <section class="tab-content active" id="tab-opshub">
    <div class="ops-grid">
      <!-- Operation Brief -->
      <div class="panel panel-lg">
        <div class="panel-header">
          <div class="panel-title"><i class="fas fa-crosshairs"></i> OPERATION BRIEF</div>
          <div class="panel-controls"><span class="badge" id="opCount">0</span><button class="btn-add" onclick="openCreateModal('operations')"><i class="fas fa-plus"></i></button></div>
        </div>
        <div class="panel-body" id="opList"></div>
      </div>
      <!-- Target Dossier / Latest Intel -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title"><i class="fas fa-bullseye"></i> LATEST INTELLIGENCE</div>
          <div class="panel-controls"><span class="badge" id="intelCount">0</span><button class="btn-add" onclick="openCreateModal('reports')"><i class="fas fa-plus"></i></button></div>
        </div>
        <div class="panel-body" id="intelList"></div>
      </div>
    </div>
  </section>

  <!-- TAB: INTEL -->
  <section class="tab-content" id="tab-intel">
    <div class="panel full-panel">
      <div class="panel-header">
        <div class="panel-title"><i class="fas fa-file-shield"></i> INTELLIGENCE REPORTS</div>
        <div class="panel-controls"><span class="badge" id="intelCount2">0</span><button class="btn-add" onclick="openCreateModal('reports')"><i class="fas fa-plus"></i></button></div>
      </div>
      <div class="panel-body" id="intelListFull"></div>
    </div>
  </section>

  <!-- TAB: INTELLIGENCE MAP -->
  <section class="tab-content" id="tab-sorties" style="padding:0;">
    <div class="map-editor-wrap">
      <!-- LEFT TOOLBAR -->
      <div class="map-toolbar map-toolbar-left" id="mapToolbarLeft">
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolZoomIn" title="Zoom In"><i class="fas fa-plus"></i></button>
          <button class="map-tool-btn" id="toolZoomOut" title="Zoom Out"><i class="fas fa-minus"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn active" id="toolPan" data-tool="pan" title="Pan"><i class="fas fa-hand-paper"></i></button>
          <button class="map-tool-btn" id="toolSelect" data-tool="select" title="Select"><i class="fas fa-arrow-pointer"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolNatoSymbol" data-tool="natoSymbol" title="NATO APP-6 Symbol"><i class="fas fa-vector-square"></i></button>
          <button class="map-tool-btn" id="toolBasicSymbol" data-tool="basicSymbol" title="Basic Symbol"><i class="fas fa-circle" style="color:#4488ff"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolLine" data-tool="line" title="Line"><i class="fas fa-slash"></i></button>
          <button class="map-tool-btn" id="toolArea" data-tool="area" title="Area"><i class="fas fa-draw-polygon"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolPoint" data-tool="point" title="Point"><i class="fas fa-circle-dot"></i></button>
          <button class="map-tool-btn" id="toolFlag" data-tool="flag" title="Flag / Label"><i class="fas fa-flag"></i></button>
        </div>
      </div>

      <!-- RIGHT TOOLBAR -->
      <div class="map-toolbar map-toolbar-right" id="mapToolbarRight">
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolLayers" title="Layers"><i class="fas fa-layer-group"></i></button>
          <button class="map-tool-btn" id="toolExport" title="Export"><i class="fas fa-file-export"></i></button>
          <button class="map-tool-btn" id="toolFullscreen" title="Fullscreen"><i class="fas fa-expand"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolSearch" title="Search Grid"><i class="fas fa-search"></i></button>
        </div>
      </div>

      <!-- COORD DISPLAY (top-right) -->
      <div class="map-coord-display" id="mapCoordDisplay">0000 - 0000</div>

      <!-- GRID EDGE LABELS -->
      <div class="grid-edge grid-edge-top" id="gridEdgeTop"></div>
      <div class="grid-edge grid-edge-left" id="gridEdgeLeft"></div>

      <!-- MAP -->
      <div id="intelMap" style="width:100%; height:100%; background:#000;"></div>
    </div>
  </section>

  <!-- MODAL: NATO SYMBOL -->
  <div class="map-modal-overlay" id="modalNatoSymbol">
    <div class="map-modal">
      <div class="map-modal-header">
        <span id="natoModalTitle">NATO APP-6 (D) Symbol</span>
        <button class="map-modal-close" onclick="closeMapModal('modalNatoSymbol')">&times;</button>
      </div>
      <div class="map-modal-body">
        <div class="affiliation-row" id="affiliationRow">
          <button class="aff-btn" data-aff="pending" style="--aff-color:#ffff00" title="Pending">
            <svg width="20" height="20"><circle cx="10" cy="10" r="8" fill="none" stroke="#ffff00" stroke-width="2" stroke-dasharray="3,2"/></svg>
            <span>Pending</span>
          </button>
          <button class="aff-btn" data-aff="unknown" style="--aff-color:#ffff00" title="Unknown">
            <svg width="20" height="20"><rect x="2" y="2" width="16" height="16" rx="2" fill="none" stroke="#ffff00" stroke-width="2"/></svg>
            <span>Unknown</span>
          </button>
          <button class="aff-btn" data-aff="assumedFriend" style="--aff-color:#80e0ff" title="Assumed Friend">
            <svg width="20" height="20"><rect x="2" y="2" width="16" height="16" fill="none" stroke="#80e0ff" stroke-width="2" stroke-dasharray="3,2"/></svg>
            <span>Assumed</span>
          </button>
          <button class="aff-btn active" data-aff="friend" style="--aff-color:#80e0ff" title="Friend">
            <svg width="20" height="20"><rect x="2" y="2" width="16" height="16" fill="#80e0ff" stroke="#80e0ff" stroke-width="2"/></svg>
            <span>Friend</span>
          </button>
          <button class="aff-btn" data-aff="neutral" style="--aff-color:#00ff00" title="Neutral">
            <svg width="20" height="20"><rect x="2" y="2" width="16" height="16" fill="#00ff00" stroke="#00ff00" stroke-width="2"/></svg>
            <span>Neutral</span>
          </button>
          <button class="aff-btn" data-aff="suspect" style="--aff-color:#ff0000" title="Suspect">
            <svg width="20" height="20"><polygon points="10,1 19,19 1,19" fill="none" stroke="#ff0000" stroke-width="2" stroke-dasharray="3,2"/></svg>
            <span>Suspect</span>
          </button>
          <button class="aff-btn" data-aff="hostile" style="--aff-color:#ff0000" title="Hostile">
            <svg width="20" height="20"><polygon points="10,2 19,18 1,18" fill="#ff0000" stroke="#ff0000" stroke-width="2"/></svg>
            <span>Hostile</span>
          </button>
        </div>
        <div class="form-row">
          <div class="form-col"><label>Symbol set</label><select id="natoSymbolSet"><option>Land unit</option><option>Air</option><option>Sea surface</option><option>Equipment</option></select></div>
          <div class="form-col"><label>Symbol</label><select id="natoSymbolType"><option>Unspecified</option><option>Infantry</option><option>Armor</option><option>Artillery</option><option>Reconnaissance</option><option>Engineer</option><option>Air Defense</option><option>Signal</option><option>Medical</option><option>Supply</option><option>Command and Control</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-col"><label>Status</label><select id="natoStatus"><option>Present</option><option>Planned</option><option>Anticipated</option></select></div>
          <div class="form-col"><label>Echelon</label><select id="natoEchelon"><option>Unspecified</option><option>Team</option><option>Squad</option><option>Section</option><option>Platoon</option><option>Company</option><option>Battalion</option><option>Regiment</option><option>Brigade</option><option>Division</option><option>Corps</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-col"><label>Unique Designation</label><input type="text" id="natoDesignation" placeholder="e.g. 1-2 INF"></div>
          <div class="form-col"><label>Additional Info</label><input type="text" id="natoAdditional" placeholder=""></div>
        </div>
        <div class="form-row">
          <div class="form-col"><label>Scale</label><input type="number" id="natoScale" value="100" min="10" max="500"></div>
        </div>
      </div>
      <div class="map-modal-footer">
        <button class="mbtn mbtn-cancel" onclick="closeMapModal('modalNatoSymbol')">Cancel</button>
        <button class="mbtn mbtn-insert" id="natoInsertBtn">Insert</button>
      </div>
    </div>
  </div>

  <!-- MODAL: BASIC SYMBOL -->
  <div class="map-modal-overlay" id="modalBasicSymbol">
    <div class="map-modal">
      <div class="map-modal-header">
        <span>Basic symbol</span>
        <button class="map-modal-close" onclick="closeMapModal('modalBasicSymbol')">&times;</button>
      </div>
      <div class="map-modal-body">
        <div class="form-row">
          <div class="form-col"><label>Shape</label><select id="basicShape"><option value="mil_dot">● mil_dot</option><option value="mil_circle">○ mil_circle</option><option value="mil_cross">✕ mil_cross</option><option value="mil_square">□ mil_square</option><option value="mil_triangle">△ mil_triangle</option><option value="mil_diamond">◇ mil_diamond</option></select></div>
          <div class="form-col"><label>Color</label>
            <div class="color-picker-wrap">
              <button class="color-btn active" data-color="#000000" style="background:#000;border:2px solid #fff" title="Black"></button>
              <button class="color-btn" data-color="#ff0000" style="background:#ff0000" title="Red"></button>
              <button class="color-btn" data-color="#0066ff" style="background:#0066ff" title="Blue"></button>
              <button class="color-btn" data-color="#00cc00" style="background:#00cc00" title="Green"></button>
              <button class="color-btn" data-color="#ffff00" style="background:#ffff00" title="Yellow"></button>
              <button class="color-btn" data-color="#ffffff" style="background:#fff" title="White"></button>
            </div>
          </div>
          <div class="form-col"><label>Rotation (mil)</label><input type="number" id="basicRotation" value="0"></div>
        </div>
        <div class="form-row">
          <div class="form-col" style="flex:1"><label>Label</label><input type="text" id="basicLabel" placeholder="Label text"></div>
        </div>
        <div class="form-row">
          <div class="form-col"><label>Scale</label><input type="number" id="basicScale" value="100" min="10" max="500"></div>
        </div>
      </div>
      <div class="map-modal-footer">
        <button class="mbtn mbtn-cancel" onclick="closeMapModal('modalBasicSymbol')">Cancel</button>
        <button class="mbtn mbtn-insert" id="basicInsertBtn">Insert</button>
      </div>
    </div>
  </div>

  <!-- MODAL: LINE / AREA PROPERTIES -->
  <div class="map-modal-overlay" id="modalLineProps">
    <div class="map-modal">
      <div class="map-modal-header">
        <span id="lineModalTitle">Line</span>
        <button class="map-modal-close" onclick="closeMapModal('modalLineProps')">&times;</button>
      </div>
      <div class="map-modal-body">
        <div class="form-row">
          <div class="form-col"><label>Color</label>
            <div class="color-picker-wrap" id="lineColorPicker">
              <button class="color-btn active" data-color="#000000" style="background:#000;border:2px solid #fff" title="Black"></button>
              <button class="color-btn" data-color="#ff0000" style="background:#ff0000" title="Red"></button>
              <button class="color-btn" data-color="#0066ff" style="background:#0066ff" title="Blue"></button>
              <button class="color-btn" data-color="#00cc00" style="background:#00cc00" title="Green"></button>
              <button class="color-btn" data-color="#ffff00" style="background:#ffff00" title="Yellow"></button>
              <button class="color-btn" data-color="#ffffff" style="background:#fff" title="White"></button>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-col"><label>Weight</label><input type="number" id="lineWeight" value="3" min="1" max="10"></div>
          <div class="form-col"><label>Label</label><input type="text" id="lineLabel" placeholder=""></div>
        </div>
      </div>
      <div class="map-modal-footer">
        <button class="mbtn mbtn-delete" id="lineDeleteBtn" style="display:none">Delete</button>
        <button class="mbtn mbtn-cancel" onclick="cancelLineDraw()">Cancel</button>
        <button class="mbtn mbtn-insert" id="lineSaveBtn">Save</button>
      </div>
    </div>
  </div>

  <!-- MODAL: SEARCH GRID -->
  <div class="map-modal-overlay" id="modalSearch">
    <div class="map-modal" style="max-width:340px">
      <div class="map-modal-header">
        <span>Go to Grid</span>
        <button class="map-modal-close" onclick="closeMapModal('modalSearch')">&times;</button>
      </div>
      <div class="map-modal-body">
        <div class="form-row">
          <div class="form-col"><label>X (Easting)</label><input type="number" id="searchX" placeholder="e.g. 8000"></div>
          <div class="form-col"><label>Y (Northing)</label><input type="number" id="searchY" placeholder="e.g. 6000"></div>
        </div>
      </div>
      <div class="map-modal-footer">
        <button class="mbtn mbtn-cancel" onclick="closeMapModal('modalSearch')">Cancel</button>
        <button class="mbtn mbtn-insert" id="searchGoBtn">Go</button>
      </div>
    </div>
  </div>
</main>

<!-- AUTH MODAL -->
<div class="modal-overlay" id="authModal">
<div class="modal" style="max-width:360px">
<div class="modal-head"><i class="fas fa-shield-halved"></i> S2 AUTHORIZATION<button class="modal-close" onclick="closeModal('authModal')">&times;</button></div>
<div class="modal-body" style="text-align:center">
<p style="color:var(--muted);font-size:.85rem;margin-bottom:12px">Enter S2 clearance code to unlock edit access</p>
<input type="password" id="authPass" class="auth-input" maxlength="10" onkeydown="if(event.key==='Enter')verifyAuth()" placeholder="••••">
<div class="auth-error" id="authError">⛔ ACCESS DENIED</div>
</div>
<div class="modal-foot" style="justify-content:center"><button class="btn-save" onclick="verifyAuth()"><i class="fas fa-key"></i> AUTHENTICATE</button></div>
</div></div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
<div class="modal">
<div class="modal-head"><span id="editTitle">EDIT</span><button class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
<div class="modal-body" id="editBody"></div>
<div class="modal-foot"><button class="btn-cancel" onclick="closeModal('editModal')">CANCEL</button><button class="btn-save" id="editSave">SAVE</button></div>
</div></div>

<script src="assets/js/intelligence.js"></script>
</body>
</html>
