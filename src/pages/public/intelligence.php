<?php
require_once ROOT_PATH . '/includes/db.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Join OPS  | Operation Stormsurge</title>
<link rel="icon" href="assets/images/logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;500;600;700&family=Orbitron:wght@400;700&display=swap" rel="stylesheet">
<link href="https://db.onlinewebfonts.com/c/1a44ef13187871a0b1e1c0e4abfa563e?family=FC+Mittraphap+Rounded" rel="stylesheet">
<link rel="stylesheet" href="assets/css/intelligence.css">
<link rel="stylesheet" href="assets/css/intel-dossier.css">
<link rel="stylesheet" href="assets/css/deployment.css">
<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<!-- Milsymbol -->
<script src="https://cdn.jsdelivr.net/npm/milsymbol@2.0.0/dist/milsymbol.js"></script>
<!-- PLANOPS / Arma3TacMap Dependencies -->
<script src="https://jetelain.github.io/Arma3Map/js/mapUtils.js"></script>
<script src="https://jetelain.github.io/Arma3Map/maps/all.js"></script>
<script src="assets/js/milMissions.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.7/tinymce.min.js" referrerpolicy="origin"></script>
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
    <a class="nav-item" data-tab="unit"><i class="fas fa-users"></i><span>DEPLOYMENT</span></a>
    <a class="nav-item" data-tab="sorties"><i class="fas fa-map"></i><span>INTELLIGENCE MAP</span></a>
  </nav>
  <div class="sidebar-ops" id="sidebarOps">
    <div class="sidebar-ops-title"><i class="fas fa-users"></i> PERSONNEL DEPLOYMENT</div>
    <div id="sidebarDeployList" class="sidebar-deploy-stats"></div>
  </div>
  <div class="sidebar-footer">
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
      <button class="tab-btn" data-tab="unit">DEPLOYMENT</button>
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
    <!-- Briefing Document Header -->
    <div class="brief-header">
      <div class="brief-header-title">
        <i class="fas fa-shield-halved"></i>
        <span id="briefDocTitle">OPERATION STORMSURGE // BRIEFING DOCUMENT</span>
      </div>
      <div class="brief-header-actions">
        <button class="brief-btn" id="btnEditBrief" onclick="toggleBriefEdit()"><i class="fas fa-pen-to-square"></i> EDIT OPORD</button>
        <button class="brief-btn" id="btnExportBrief" onclick="exportBrief()"><i class="fas fa-file-export"></i> EXPORT</button>
      </div>
    </div>

    <div class="brief-body">
      <!-- SITUATION REPORT -->
      <div class="brief-section">
        <div class="brief-section-label"><span><i class="fas fa-star"></i> OPERATION BRIEF</span></div>
        <h2 class="brief-op-name" id="briefOpName">OPERATION STORMSURGE</h2>
        <span class="brief-class-tag" id="briefClassTag">CRITICAL</span>

        <div class="brief-info-grid">
          <div class="brief-info-cell">
            <div class="brief-info-label">STATUS</div>
            <div class="brief-info-value" id="briefStatus">ACTIVE</div>
          </div>
          <div class="brief-info-cell">
            <div class="brief-info-label">AO LOCATION</div>
            <div class="brief-info-value" id="briefAO">COLOMBIA</div>
          </div>
          <div class="brief-info-cell">
            <div class="brief-info-label">ASSIGNED TEAM</div>
            <div class="brief-info-value" id="briefTeam">ODA 0121</div>
          </div>
          <div class="brief-info-cell">
            <div class="brief-info-label">START DATE</div>
            <div class="brief-info-value" id="briefStartDate">—</div>
          </div>
        </div>
      </div>

      <!-- OPERATION ORDER -->
      <div class="brief-section brief-opord">
        <div class="brief-section-label"><span><i class="fas fa-scroll"></i> OPERATION ORDER // CUSTOM</span></div>
        <div class="brief-opord-content" id="briefOpordContent">
          <p class="brief-placeholder">No operation order loaded. Click EDIT OPORD to add briefing content.</p>
        </div>
        <div class="brief-opord-editor" id="briefOpordEditor" style="display:none;">
          <textarea id="briefOpordText" rows="12" placeholder="Enter OPORD content here..."></textarea>
          <div class="brief-editor-actions">
            <button class="brief-btn danger" onclick="cancelBriefEdit()">CANCEL</button>
            <button class="brief-btn save" onclick="saveBrief()">SAVE OPORD</button>
          </div>
        </div>
      </div>

      <!-- LATEST SUPPORTING INTELLIGENCE -->
      <div class="brief-section">
        <div class="brief-section-label">
          <span><i class="fas fa-satellite-dish"></i> LATEST SUPPORTING INTELLIGENCE</span>
          <span class="brief-section-actions"><span class="badge" id="intelCount">0</span><button class="brief-btn" onclick="openCreateModal('reports')"><i class="fas fa-plus"></i> ADD REPORT</button></span>
        </div>
        <div class="brief-intel-cards" id="briefIntelCards">
          <!-- Filled by JS -->
        </div>
      </div>

      <!-- ACTIVE OPERATION SORTIES -->
      <div class="brief-section">
        <div class="brief-section-label">
          <span><i class="fas fa-jet-fighter"></i> ACTIVE OPERATION SORTIES</span>
          <span class="brief-section-actions"><span class="badge" id="opCount">0</span><button class="brief-btn" onclick="openCreateModal('operations')"><i class="fas fa-plus"></i> ADD SORTIE</button></span>
        </div>
        <div class="brief-sorties-list" id="briefSortiesList">
          <!-- Filled by JS -->
        </div>
      </div>
    </div>
  </section>

  <!-- TAB: INTEL — DOSSIER SYSTEM -->
  <section class="tab-content" id="tab-intel">
    <div class="dossier-layout">

      <!-- LEFT PANEL — Operations Stats -->
      <div class="dossier-left">
        <div class="dossier-left-header">
          <div class="op-label">OPERATION</div>
          <div class="op-title" id="dossierOpTitle">OPERATION ARKERIAN FREEDOM</div>
        </div>

        <div class="dossier-stats">
          <div class="dossier-stat-row" id="toggleDossierListBtn" style="cursor: pointer;" title="Toggle Asset Dossiers List">
            <span class="stat-name"><i class="fas fa-file-shield"></i> INTEL Overall</span>
            <span class="stat-badge red" id="dossierIntelCount">0 HUMINT</span>
          </div>
          <div class="dossier-stat-row">
            <span class="stat-name"><i class="fas fa-tasks"></i> TASKING Overall</span>
            <span class="stat-badge amber" id="dossierTaskCount">0 ACTIVE</span>
          </div>
          <div class="dossier-stat-row">
            <span class="stat-name"><i class="fas fa-route"></i> MVT Overall</span>
            <span class="stat-badge green">—</span>
          </div>
          <div class="dossier-stat-row">
            <span class="stat-name"><i class="fas fa-users"></i> Operator Roster</span>
            <span class="stat-badge green">—</span>
          </div>
        </div>

        <div class="dossier-left-footer">
          <button class="ghost-btn" id="btnGhostTerminal"><i class="fas fa-terminal"></i> GHOST TERMINAL</button>
          <div class="version-tag">COMMAND OPS CONSOLE V8.9</div>
        </div>
      </div>

      <!-- ASSET DOSSIER LIST (Middle Panel) -->
      <div class="dossier-middle">
        <div class="dossier-middle-header">
          <h3>ASSET DOSSIERS</h3>
          <button class="btn-refresh" onclick="loadDossiers()" title="Refresh"><i class="fas fa-sync-alt"></i></button>
        </div>

        <div class="dossier-search">
          <i class="fas fa-search"></i>
          <input type="text" id="dossierSearch" placeholder="SEARCH ASSETS...">
        </div>

        <div class="dossier-categories" id="dossierCategories">
          <!-- PRIMARY TARGETS -->
          <div class="dossier-category" data-category="primary">
            <div class="dossier-category-header">
              <span class="cat-title">PRIMARY TARGETS (<span class="cat-count" id="catPrimaryCount">0</span>)</span>
              <div class="cat-actions">
                <button class="btn-add-asset" onclick="openDossierModal('primary')"><i class="fas fa-plus"></i></button>
              </div>
            </div>
            <div class="dossier-category-body grid-view" id="catPrimaryList">
              <!-- Populated by JS -->
            </div>
          </div>

          <!-- SECONDARY ASSETS -->
          <div class="dossier-category" data-category="secondary">
            <div class="dossier-category-header">
              <span class="cat-title">SECONDARY ASSETS (<span class="cat-count" id="catSecondaryCount">0</span>)</span>
              <div class="cat-actions">
                <button class="btn-add-asset" onclick="openDossierModal('secondary')"><i class="fas fa-plus"></i></button>
              </div>
            </div>
            <div class="dossier-category-body" id="catSecondaryList">
              <!-- Populated by JS -->
            </div>
          </div>

          <!-- PERSONS OF INTEREST -->
          <div class="dossier-category" data-category="poi">
            <div class="dossier-category-header">
              <span class="cat-title">PERSONS OF INTEREST (POI) (<span class="cat-count" id="catPoiCount">0</span>)</span>
              <div class="cat-actions">
                <button class="btn-add-asset" onclick="openDossierModal('poi')"><i class="fas fa-plus"></i></button>
              </div>
            </div>
            <div class="dossier-category-body grid-view" id="catPoiList">
              <!-- Populated by JS -->
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT PANEL — Dossier Detail View -->
      <div class="dossier-right">
        <div class="dossier-content" id="dossierDetailContent">
          <!-- Empty state -->
          <div class="dossier-empty" id="dossierEmptyState">
            <i class="fas fa-crosshairs"></i>
            <span>SELECT AN ASSET TO VIEW DOSSIER</span>
          </div>

          <!-- Dossier detail (hidden by default) -->
          <div id="dossierDetailView" style="display:none;">
            <!-- Header -->
            <div class="dossier-detail-header">
              <div>
                <div class="dossier-file-label">OFFICIAL DOSSIER FILE</div>
                <div class="dossier-callsign-title">
                  <span id="dossierCallsign">—</span>
                  <span class="status-icon" id="dossierStatusIcon"></span>
                </div>
              </div>
              <div class="dossier-header-badges" id="dossierBadges">
                <!-- Badges injected by JS -->
              </div>
            </div>

            <!-- Photo + Core Data -->
            <div class="dossier-photo-data">
              <div class="dossier-photo" id="dossierPhoto">
                <div class="photo-placeholder"><i class="fas fa-user-secret"></i></div>
              </div>
              <div class="core-data-register">
                <div class="core-data-title">CORE DATA REGISTER</div>
                <div class="core-data-grid">
                  <div class="core-data-cell"><div class="core-data-label">FULL NAME</div><div class="core-data-value" id="dFullName">—</div></div>
                  <div class="core-data-cell"><div class="core-data-label">THREAT LEVEL</div><div class="core-data-value high" id="dThreatLevel">—</div></div>
                  <div class="core-data-cell"><div class="core-data-label">LAST KNOWN LOI</div><div class="core-data-value" id="dLastLoi">—</div></div>
                  <div class="core-data-cell"><div class="core-data-label">GRID REF</div><div class="core-data-value" id="dGridRef">—</div></div>
                  <div class="core-data-cell"><div class="core-data-label">STATUS</div><div class="core-data-value" id="dStatus">—</div></div>
                  <div class="core-data-cell"><div class="core-data-label">CATEGORY</div><div class="core-data-value" id="dCategory">—</div></div>
                  <div class="core-data-cell full"><div class="core-data-label">ASSET TAG</div><div class="core-data-value" id="dAssetTag">—</div></div>
                  <div class="core-data-cell full"><div class="core-data-label">TASK DIRECTIVE</div><div class="core-data-value red" id="dTaskDirective">—</div></div>
                </div>
              </div>
            </div>

            <!-- Detailed DSR / Intel Stats -->
            <div class="intel-stats-section">
              <div class="intel-stats-title">DETAILED BSR / INTEL STATS</div>
              <div class="intel-stats-grid">
                <div class="intel-stat-cell">
                  <div class="intel-stat-label">M/A</div>
                  <div class="intel-stat-value" id="dStatMA">—</div>
                  <div class="intel-stat-bar"><div class="intel-stat-bar-fill" id="dStatMABar" style="width:0%"></div></div>
                </div>
                <div class="intel-stat-cell">
                  <div class="intel-stat-label">FOG</div>
                  <div class="intel-stat-value" id="dStatFOG">—</div>
                  <div class="intel-stat-bar"><div class="intel-stat-bar-fill" id="dStatFOGBar" style="width:0%"></div></div>
                </div>
                <div class="intel-stat-cell">
                  <div class="intel-stat-label">F/R</div>
                  <div class="intel-stat-value" id="dStatFR">—</div>
                  <div class="intel-stat-bar"><div class="intel-stat-bar-fill" id="dStatFRBar" style="width:0%"></div></div>
                </div>
                <div class="intel-stat-cell">
                  <div class="intel-stat-label">INT/BG</div>
                  <div class="intel-stat-value" id="dStatINT">—</div>
                  <div class="intel-stat-bar"><div class="intel-stat-bar-fill" id="dStatINTBar" style="width:0%"></div></div>
                </div>
              </div>
            </div>

            <!-- Target Summary & POI -->
            <div class="target-summary-section">
              <div class="target-summary-title">TARGET SUMMARY & POI</div>
              <div class="target-summary-body" id="dSummary">
                <em style="color:var(--r-muted);">No summary available.</em>
              </div>
            </div>

            <!-- Evidence & Attachments -->
            <div class="evidence-section">
              <div class="evidence-title" onclick="this.nextElementSibling.classList.toggle('show')">
                <i class="fas fa-paperclip"></i> INTEL EVIDENCE & ATTACHMENTS
              </div>
              <div class="evidence-body" id="dEvidenceBody">
                <div class="evidence-grid" id="dEvidenceGrid">
                  <!-- Populated by JS -->
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- PHOTO LIGHTBOX OVERLAY -->
  <div class="intel-lightbox-overlay" id="intelLightbox">
    <div class="intel-lightbox-close" onclick="closeIntelLightbox()">&times;</div>
    <div class="intel-lightbox-label" id="lightboxLabel"></div>
    <div class="intel-lightbox-counter" id="lightboxCounter"></div>
    <button class="intel-lightbox-nav prev" id="lightboxPrev" onclick="navigateLightbox(-1)"><i class="fas fa-chevron-left"></i></button>
    <button class="intel-lightbox-nav next" id="lightboxNext" onclick="navigateLightbox(1)"><i class="fas fa-chevron-right"></i></button>
    <div class="intel-lightbox-img-wrap">
      <img id="lightboxImg" src="" alt="">
    </div>
  </div>

  <!-- TAB: DEPLOYMENT -->
  <section class="tab-content" id="tab-unit">
    <div class="deploy-container">
      <!-- Header -->
      <div class="deploy-header">
        <div class="deploy-header-left">
          <i class="fas fa-users-gear"></i>
          <div>
            <div class="deploy-header-title">PERSONNEL DEPLOYMENT STATUS</div>
            <div class="deploy-header-subtitle">OPERATION STORMSURGE — ROLE ASSIGNMENT</div>
          </div>
        </div>
        <div class="deploy-stats-box">
          <div class="deploy-stat">
            <div class="deploy-stat-num" id="deployFilledCount">0</div>
            <div class="deploy-stat-label">DEPLOYED</div>
          </div>
          <div class="deploy-stat">
            <div class="deploy-stat-num" id="deployTotalCount">0</div>
            <div class="deploy-stat-label">TOTAL SLOTS</div>
          </div>
          <button class="deploy-btn-reset" onclick="resetDeployment()" title="Clear all assignments (Admin)">
            <i class="fas fa-rotate-left"></i> RESET ALL
          </button>
        </div>
      </div>
      <!-- Unit Cards -->
      <div id="deployUnitList"></div>
    </div>
  </section>

  <!-- MODAL: SIGNUP -->
  <div class="signup-modal-overlay" id="signupModal">
    <div class="signup-modal">
      <div class="signup-modal-header">
        <span><i class="fas fa-user-plus" style="margin-right:8px;"></i>SIGN UP</span>
        <button class="signup-modal-close" onclick="closeSignupModal()">&times;</button>
      </div>
      <div class="signup-modal-body">
        <div class="signup-modal-info">ASSIGNING TO ROLE:</div>
        <div class="signup-modal-role" id="signupRoleName"></div>
        <div class="signup-modal-unit" id="signupUnitName"></div>
        <input type="text" class="signup-input" id="signupPlayerInput" 
               placeholder="Enter your callsign / name..." 
               autocomplete="off" maxlength="50">
      </div>
      <div class="signup-modal-footer">
        <button class="signup-modal-btn cancel" onclick="closeSignupModal()">CANCEL</button>
        <button class="signup-modal-btn confirm" onclick="confirmSignup()">
          <i class="fas fa-check"></i> CONFIRM
        </button>
      </div>
    </div>
  </div>

  <!-- TAB: INTELLIGENCE MAP -->
  <section class="tab-content" id="tab-sorties" style="padding:0;">
    <div class="map-editor-wrap">
      <!-- LEFT TOOLBAR (matches PLANOPS Maps) -->
      <div class="map-toolbar map-toolbar-left" id="mapToolbarLeft">
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolZoomIn" title="Zoom In"><i class="fas fa-plus"></i></button>
          <button class="map-tool-btn" id="toolZoomOut" title="Zoom Out"><i class="fas fa-minus"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn active" id="toolPan" data-tool="pan" title="Pan"><i class="far fa-hand-paper"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolSelect" data-tool="select" title="Pointer"><i class="far fa-hand-pointer"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolNatoSymbol" data-tool="natoSymbol" title="NATO APP-6 Symbol"><img height="16" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='16' viewBox='0 0 24 16'%3E%3Crect x='1' y='1' width='22' height='14' fill='%2380d0ff' stroke='%23333' stroke-width='1.5'/%3E%3Cline x1='1' y1='1' x2='23' y2='15' stroke='%23333' stroke-width='1.5'/%3E%3Cline x1='23' y1='1' x2='1' y2='15' stroke='%23333' stroke-width='1.5'/%3E%3C/svg%3E" alt="NATO"></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolBasicSymbol" data-tool="basicSymbol" title="Basic Symbol" style="font-size:18px;line-height:32px;">&#9679;</button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolLine" data-tool="line" title="Line (Ctrl/Shift+click to add segments)" style="font-size:16px;font-weight:bold;">&#9585;</button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolMeasure" data-tool="measure" title="Measure Distance"><i class="fas fa-ruler"></i></button>
        </div>
        <div class="toolbar-group toolbar-colorpicker" id="toolbarColorPicker" style="display:none;">
          <button class="tool-color-btn active" data-color="#000000" style="background:#000" title="Black"></button>
          <button class="tool-color-btn" data-color="#ff0000" style="background:#ff0000" title="Red"></button>
          <button class="tool-color-btn" data-color="#0066ff" style="background:#0066ff" title="Blue"></button>
          <button class="tool-color-btn" data-color="#00cc44" style="background:#00cc44" title="Green"></button>
          <button class="tool-color-btn" data-color="#ffaa00" style="background:#ffaa00" title="Orange"></button>
          <button class="tool-color-btn" data-color="#aa00ff" style="background:#aa00ff" title="Purple"></button>
          <button class="tool-color-btn" data-color="#ffff00" style="background:#ff0" title="Yellow"></button>
          <button class="tool-color-btn" data-color="#00ffff" style="background:#0ff" title="Cyan"></button>
          <button class="tool-color-btn" data-color="#7f3f00" style="background:#7f3f00" title="Brown"></button>
          <button class="tool-color-btn" data-color="#ffffff" style="background:#fff;border:1px solid #999" title="White"></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolMission" data-tool="mission" title="Tactical Graphics"><i class="fas fa-plus-circle"></i></button>
        </div>
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolNote" data-tool="note" title="Sticky Note"><i class="fas fa-sticky-note"></i></button>
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
        <div class="toolbar-group">
          <button class="map-tool-btn" id="toolOnlineUsers" title="Connected Users" onclick="document.getElementById('rtUsersPanel').classList.toggle('show')">
            <span id="rtOnlineDot" class="rt-online-dot"></span>
            <span id="rtOnlineCount" style="font-size:11px;font-family:var(--mono);">0</span>
          </button>
        </div>
      </div>

      <!-- CONNECTED USERS PANEL -->
      <div class="rt-users-panel" id="rtUsersPanel">
        <div class="rt-users-header">
          <span><i class="fas fa-users"></i> CONNECTED OPERATORS</span>
          <button class="layers-panel-close" onclick="document.getElementById('rtUsersPanel').classList.remove('show')">&times;</button>
        </div>
        <div class="rt-users-body" id="rtUserList">
          <!-- Populated by JS -->
        </div>
        <div class="rt-users-footer">
          <span class="rt-status-text"><i class="fas fa-satellite-dish"></i> REAL-TIME SYNC ACTIVE</span>
        </div>
      </div>

      <!-- LAYERS PANEL -->
      <div class="layers-panel" id="layersPanel">
        <div class="layers-panel-header">
          <span><i class="fas fa-layer-group"></i> LAYERS</span>
          <button class="layers-panel-close" onclick="document.getElementById('layersPanel').classList.remove('show')">&times;</button>
        </div>
        <div class="layers-panel-body">
          <div class="layer-item active" id="layerDrawings">
            <span class="layer-vis"><i class="fas fa-eye"></i></span>
            <span class="layer-name">Drawings</span>
          </div>
          <div class="layer-item active" id="layerGrid">
            <span class="layer-vis"><i class="fas fa-eye"></i></span>
            <span class="layer-name">Grid</span>
          </div>
        </div>
        <div class="layers-panel-footer">
          <button class="layers-action-btn" id="btnImportMarkers"><i class="fas fa-file-import"></i> Import</button>
          <button class="layers-action-btn danger" id="btnClearAll"><i class="fas fa-trash"></i> Clear All</button>
        </div>
      </div>

      <!-- MAP -->
      <div id="intelMap" style="width:100%; height:100%; background:#000;"></div>
    </div>
  </section>

  <!-- MODAL: EDIT / CREATE ITEM -->
  <div class="modal-overlay" id="editModal">
    <div class="modal-box">
      <div class="modal-header">
        <span class="modal-title" id="editTitle">CREATE</span>
        <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
      </div>
      <div class="modal-body" id="editBody"></div>
      <div class="modal-footer">
        <button class="brief-btn danger" onclick="closeModal('editModal')">CANCEL</button>
        <button class="brief-btn save" id="editSave">SAVE</button>
      </div>
    </div>
  </div>

  <!-- MODAL: NATO SYMBOL -->
  <div class="map-modal-overlay" id="modalNatoSymbol">
    <div class="map-modal nato-modal">
      <div class="map-modal-header">
        <span id="natoModalTitle">NATO APP-6 (D) Symbol</span>
        <button class="map-modal-close" onclick="closeMapModal('modalNatoSymbol')">&times;</button>
      </div>
      <div class="map-modal-body">
        <div class="nato-modal-top">
            <div class="sidc-row">
                <input type="text" id="sidcCode" readonly value="10031000001100000000">
                <div class="sidc-actions">
                    <button type="button" id="natoBookmarkBtn"><i class="fas fa-star"></i> Bookmark</button>
                    <button type="button" id="natoCopyCodeBtn">Copy code</button>
                    <button type="button" id="natoCopyImageBtn">Copy image</button>
                </div>
                <div class="sidc-bookmarks-label">Bookmarks</div>
            </div>
            <div class="preview-box" id="natoPreview">
                <!-- SVG injected here -->
            </div>
            
            <div class="aff-row-container" style="display:flex; gap:10px; margin-bottom:15px;">
                <div class="aff-row" style="margin-bottom:0; flex:1;">
                    <button type="button" class="aff-box-btn" data-aff="pending">
                       <div class="aff-icon"></div>
                       <div>Pending</div>
                    </button>
                    <button type="button" class="aff-box-btn" data-aff="unknown">
                       <div class="aff-icon"></div>
                       <div>Unknown</div>
                    </button>
                    <button type="button" class="aff-box-btn" data-aff="assumedFriend">
                       <div class="aff-icon"></div>
                       <div>Assumed Friend</div>
                    </button>
                    <button type="button" class="aff-box-btn active" data-aff="friend">
                       <div class="aff-icon"></div>
                       <div>Friend</div>
                    </button>
                    <button type="button" class="aff-box-btn" data-aff="neutral">
                       <div class="aff-icon"></div>
                       <div>Neutral</div>
                    </button>
                    <button type="button" class="aff-box-btn" data-aff="suspect">
                       <div class="aff-icon"></div>
                       <div>Suspect</div>
                    </button>
                    <button type="button" class="aff-box-btn" data-aff="hostile">
                       <div class="aff-icon"></div>
                       <div>Hostile</div>
                    </button>
                </div>
                <div class="aff-row" style="margin-bottom:0;">
                    <button type="button" class="aff-box-btn" data-hqtf="1" id="btnDummy">
                       <div class="aff-icon"></div>
                       <div>Dummy</div>
                    </button>
                    <button type="button" class="aff-box-btn" data-hqtf="2" id="btnHQ">
                       <div class="aff-icon"></div>
                       <div>HQ</div>
                    </button>
                    <button type="button" class="aff-box-btn" data-hqtf="4" id="btnTF">
                       <div class="aff-icon"></div>
                       <div>TF</div>
                    </button>
                </div>
            </div>

            <div class="nato-form-grid nato-row-symbolset">
                <div><label>Symbol set</label><select id="natoSymbolSet" class="nato-select">
                    <option value="10">Land unit</option>
                    <option value="11">Land civilian unit/Organization</option>
                    <option value="15">Land equipment</option>
                    <option value="20">Land installations</option>
                    <option value="01">Air</option>
                    <option value="02">Air missile</option>
                    <option value="05">Space</option>

                    <option value="30">Sea surface</option>
                    <option value="35">Sea subsurface</option>
                    <option value="36">Mine warfare</option>
                    <option value="40">Activity/Event</option>
                    <option value="27">Dismounted individual</option>
                </select></div>
                <div class="nato-symbol-entity-col">
                    <label>Symbol <a href="#" id="natoAllSymbolsLink" class="nato-all-symbols-link">All symbols</a></label>
                    <select id="natoSymbolType" class="nato-select"><!-- Populated by JS --></select>
                </div>
            </div>
            
            <div class="nato-form-grid nato-row-status">
                <div><label>Status</label><select id="natoStatus" class="nato-select">
                    <option value="0">Present</option>
                    <option value="1">Planned / Anticipated</option>
                    <option value="2">Fully Capable</option>
                    <option value="3">Damaged</option>
                    <option value="4">Destroyed</option>
                    <option value="5">Full to Capacity</option>
                </select></div>
                <div><label>Icon Modifier 1</label><select id="natoMod1" class="nato-select"><!-- Populated by JS --></select></div>
                <div><label>Icon Modifier 2</label><select id="natoMod2" class="nato-select"><!-- Populated by JS --></select></div>
                <div><label>Echelon</label><select id="natoEchelon" class="nato-select">
                    <option value="00">Unspecified</option>
                    <option value="11">Team / Crew</option>
                    <option value="12">Squad</option>
                    <option value="13">Section</option>
                    <option value="14">Platoon / Detachment</option>
                    <option value="15">Company / Battery / Troop</option>
                    <option value="16">Battalion / Squadron</option>
                    <option value="17">Regiment / Group</option>
                    <option value="18">Brigade</option>
                    <option value="21">Division</option>
                    <option value="22">Corps / MEF</option>
                    <option value="23">Army</option>
                    <option value="24">Army Group / Front</option>
                    <option value="25">Region / Theater</option>
                    <option value="26">Command</option>
                </select></div>
            </div>

            <div class="nato-form-grid-2">
                <div><label>Additional Information</label><input type="text" id="natoAdditional" class="nato-input" placeholder=""></div>
                <div><label>Higher Formation</label><input type="text" id="natoHigherFormation" class="nato-input" placeholder=""></div>
            </div>

            <div class="nato-form-grid nato-row-desig">
                <div><label>Unique Designation</label><input type="text" id="natoDesignation" class="nato-input" placeholder=""></div>
                <div><label>Direction (mil)</label><input type="number" id="natoDirection" class="nato-input" min="0" max="6400" placeholder=""></div>
                <div><label>Reinforced</label><select id="natoReinforced" class="nato-select"><option value="">—</option><option value="(+)">(+) Reinforced</option><option value="(-)">(-) Reduced</option><option value="(±)">(±) Reinforced &amp; Reduced</option></select></div>
            </div>

            <div class="nato-form-grid nato-row-bottom">
                <div><label>Scale</label><input type="number" id="natoScale" class="nato-input" value="100" min="10" max="500"></div>
                <div><label>Layer</label><select id="natoLayer" class="nato-select">
                    <option value="base">Base layer</option>
                </select></div>
                <div class="nato-export-col"><label>Export to Metis Marker Preview</label><label class="nato-toggle"><input type="checkbox" id="natoExportMetis"><span class="nato-toggle-slider"></span></label></div>
            </div>
        </div>
      </div>
      <div class="map-modal-footer">
        <button class="mbtn mbtn-delete" id="natoDeleteBtn" style="display:none">Delete</button>
        <div class="nato-footer-right">
          <button class="mbtn mbtn-cancel" onclick="closeMapModal('modalNatoSymbol')">Cancel</button>
          <button class="mbtn mbtn-insert" id="natoInsertBtn">Insert</button>
        </div>
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
          <div class="form-col"><label>Shape</label><select id="basicShape">
            <option value="mil_dot">● mil_dot</option>
            <option value="mil_circle">○ mil_circle</option>
            <option value="mil_cross">✕ mil_cross</option>
            <option value="mil_square">□ mil_square</option>
            <option value="mil_triangle">▲ mil_triangle</option>
            <option value="mil_diamond">◆ mil_diamond</option>
            <option value="mil_arrow">➤ mil_arrow</option>
            <option value="mil_objective">⊕ mil_objective</option>
            <option value="mil_pickup">⬆ mil_pickup</option>
            <option value="mil_start">⊙ mil_start</option>
            <option value="mil_end">⊗ mil_end</option>
            <option value="mil_unknown">? mil_unknown</option>
            <option value="mil_warning">⚠ mil_warning</option>
            <option value="mil_flag">⚑ mil_flag</option>
            <option value="mil_destroy">✖ mil_destroy</option>
            <option value="mil_join">⊞ mil_join</option>
            <option value="mil_marker">📍 mil_marker</option>
            <option value="hd_ambush">↯ hd_ambush</option>
            <option value="hd_destroy">✖ hd_destroy</option>
            <option value="hd_flag">⚑ hd_flag</option>
            <option value="hd_start">⊙ hd_start</option>
            <option value="hd_end">⊗ hd_end</option>
            <option value="hd_objective">⊕ hd_objective</option>
            <option value="hd_pickup">⬆ hd_pickup</option>
            <option value="hd_warning">⚠ hd_warning</option>
            <option value="hd_unknown">? hd_unknown</option>
          </select></div>
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
      <div class="map-modal-footer" style="display:flex; justify-content:space-between; width:100%">
        <button class="mbtn mbtn-delete" id="basicDeleteBtn" style="display:none">Delete</button>
        <div style="display:flex; gap:6px;">
          <button class="mbtn mbtn-cancel" onclick="closeMapModal('modalBasicSymbol')">Cancel</button>
          <button class="mbtn mbtn-insert" id="basicInsertBtn">Insert</button>
        </div>
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

  <!-- MODAL: MISSION SELECTOR -->
  <div class="map-modal-overlay" id="modalMissionSelector">
    <div class="map-modal" style="max-width:400px; max-height:80vh; overflow-y:auto;">
      <div class="map-modal-header">
        <span>Choose mission</span>
        <button class="map-modal-close" onclick="closeMapModal('modalMissionSelector')">&times;</button>
      </div>
      <div class="map-modal-body">
        <div class="form-row">
          <div class="form-col"><label>Echelon</label>
            <select id="missionSize">
              <option value="12">● Squad</option>
              <option value="13" selected>●● Section</option>
              <option value="14">●●● Platoon</option>
            </select>
          </div>
          <div class="form-col"><label>Color</label>
            <div class="color-picker-wrap" id="missionColorPicker">
              <button class="color-btn active" data-color="#000000" style="background:#000;border:2px solid #fff" title="Black"></button>
              <button class="color-btn" data-color="#ff0000" style="background:#ff0000" title="Red"></button>
              <button class="color-btn" data-color="#0066ff" style="background:#0066ff" title="Blue"></button>
              <button class="color-btn" data-color="#00cc00" style="background:#00cc00" title="Green"></button>
              <button class="color-btn" data-color="#ffff00" style="background:#ffff00" title="Yellow"></button>
              <button class="color-btn" data-color="#ff8800" style="background:#ff8800" title="Orange"></button>
              <button class="color-btn" data-color="#7f3f00" style="background:#7f3f00" title="Brown"></button>
              <button class="color-btn" data-color="#ffffff" style="background:#fff" title="White"></button>
            </div>
          </div>
        </div>
        <hr style="border:1px solid #ddd; margin:10px 0;">
        <div id="missionList">
          <label class="mission-cat-label">AREA WITH SECTOR: 4 POINTS REQUIRED</label>
          <div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:15px;">
            <button class="mbtn mission-btn" data-mission="toSurvey"><img src="assets/img/missions/toSurvey.png" style="height:20px;margin-right:5px;">To Survey</button>
            <button class="mbtn mission-btn" data-mission="toCover"><img src="assets/img/missions/toCover.png" style="height:20px;margin-right:5px;">To Cover</button>
            <button class="mbtn mission-btn" data-mission="toSupportByFire"><img src="assets/img/missions/toSupportByFire.png" style="height:20px;margin-right:5px;">Support by Fire</button>
          </div>
          
          <label class="mission-cat-label">MOVEMENT: 2 POINTS REQUIRED</label>
          <div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:15px;">
            <button class="mbtn mission-btn" data-mission="toRecce"><img src="assets/img/missions/toRecce.png" style="height:20px;margin-right:5px;">To Recce</button>
            <button class="mbtn mission-btn" data-mission="toScout"><img src="assets/img/missions/toScout.png" style="height:20px;margin-right:5px;">To Scout</button>
            <button class="mbtn mission-btn" data-mission="toSeize"><img src="assets/img/missions/toSeize.png" style="height:20px;margin-right:5px;">To Seize (L)</button>
            <button class="mbtn mission-btn" data-mission="toSeizeR"><img src="assets/img/missions/toSeizeR.png" style="height:20px;margin-right:5px;">To Seize (R)</button>
            <button class="mbtn mission-btn" data-mission="toSupport"><img src="assets/img/missions/toSupport.png" style="height:20px;margin-right:5px;">To Support</button>
            <button class="mbtn mission-btn" data-mission="toMakeAndIdentifyContact"><img src="assets/img/missions/toMakeAndIdentifyContact.png" style="height:20px;margin-right:5px;">Make Contact</button>
          </div>

          <label class="mission-cat-label">SINGLE POINT: 1 POINT REQUIRED</label>
          <div style="display:flex; flex-wrap:wrap; gap:5px;">
            <button class="mbtn mission-btn" data-mission="toDestroy"><img src="assets/img/missions/toDestroy.png" style="height:20px;margin-right:5px;">To Destroy</button>
            <button class="mbtn mission-btn" data-mission="toDefend"><img src="assets/img/missions/toDefend.png" style="height:20px;margin-right:5px;">To Defend</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: STICKY NOTE -->
  <div class="map-modal-overlay" id="modalNote">
    <div class="map-modal" style="max-width:500px">
      <div class="map-modal-header">
        <span>Sticky Note</span>
        <button class="map-modal-close" onclick="closeMapModal('modalNote')">&times;</button>
      </div>
      <div class="map-modal-body">
        <textarea id="noteContent" style="width:100%;height:150px;background:#111;color:#fff;border:1px solid #333;padding:10px;"></textarea>
        <div class="form-row" style="margin-top:10px">
          <div class="form-col"><label>Position</label>
            <select id="notePosition">
              <option value="center">Center</option>
              <option value="right">Right</option>
              <option value="left">Left</option>
              <option value="bottom">Bottom</option>
              <option value="top">Top</option>
            </select>
          </div>
        </div>
      </div>
      <div class="map-modal-footer">
        <button class="mbtn mbtn-delete" id="noteDeleteBtn" style="display:none">Delete</button>
        <button class="mbtn mbtn-cancel" onclick="closeMapModal('modalNote')">Cancel</button>
        <button class="mbtn mbtn-insert" id="noteSaveBtn">Save</button>
      </div>
    </div>
  </div>

  <!-- MODAL: MEASURE (Simple text display) -->
  <div class="map-modal-overlay" id="modalMeasure">
    <div class="map-modal" style="max-width:300px">
      <div class="map-modal-header">
        <span>Measure Tools</span>
        <button class="map-modal-close" onclick="closeMapModal('modalMeasure')">&times;</button>
      </div>
      <div class="map-modal-body text-center">
         <p>Click on the map to start measuring distance.</p>
         <button class="mbtn mbtn-cancel" id="measureEditPointsBtn">Edit Points</button>
      </div>
      <div class="map-modal-footer">
        <button class="mbtn mbtn-delete" id="measureDeleteBtn" style="display:none">Delete</button>
        <button class="mbtn mbtn-cancel" onclick="closeMapModal('modalMeasure')">Close</button>
      </div>
    </div>
  </div>

  <!-- MODAL: CREATE/EDIT DOSSIER -->
  <div class="dossier-modal-overlay" id="dossierModal">
    <div class="dossier-modal">
      <div class="dossier-modal-header">
        <span id="dossierModalTitle">CREATE ASSET DOSSIER</span>
        <button class="dossier-modal-close" onclick="closeDossierModal()">&times;</button>
      </div>
      <div class="dossier-modal-body">
        <div class="dossier-field">
          <label>CALLSIGN / ALIAS</label>
          <input type="text" id="df_callsign" placeholder="e.g. VOLK">
        </div>
        <div class="dossier-field">
          <label>FULL NAME</label>
          <input type="text" id="df_full_name" placeholder="e.g. Viktor Volkov">
        </div>
        <div class="dossier-field">
          <label>CATEGORY</label>
          <select id="df_category">
            <option value="primary">PRIMARY TARGET</option>
            <option value="secondary">SECONDARY ASSET</option>
            <option value="poi">PERSON OF INTEREST</option>
          </select>
        </div>
        <div class="dossier-field">
          <label>THREAT LEVEL</label>
          <select id="df_threat_level">
            <option value="HIGH">HIGH</option>
            <option value="MEDIUM">MEDIUM</option>
            <option value="LOW">LOW</option>
            <option value="UNKNOWN">UNKNOWN</option>
          </select>
        </div>
        <div class="dossier-field">
          <label>STATUS</label>
          <select id="df_status">
            <option value="AT LARGE">AT LARGE</option>
            <option value="CAPTURED">CAPTURED</option>
            <option value="KIA">KIA</option>
            <option value="ACTIVE">ACTIVE</option>
            <option value="UNKNOWN">UNKNOWN</option>
          </select>
        </div>
        <div class="dossier-field">
          <label>TASK DIRECTIVE</label>
          <select id="df_task_directive">
            <option value="CAPTURE">CAPTURE</option>
            <option value="ELIMINATE">ELIMINATE</option>
            <option value="OBSERVE">OBSERVE</option>
            <option value="INTERROGATE">INTERROGATE</option>
            <option value="RECRUIT">RECRUIT</option>
            <option value="NONE">NONE</option>
          </select>
        </div>
        <div class="dossier-field">
          <label>LAST KNOWN LOCATION / LOI</label>
          <input type="text" id="df_last_loi" placeholder="e.g. DRY-006">
        </div>
        <div class="dossier-field">
          <label>GRID REFERENCE</label>
          <input type="text" id="df_grid_ref" placeholder="e.g. DRY-006">
        </div>
        <div class="dossier-field">
          <label>ASSET TAG</label>
          <input type="text" id="df_asset_tag" placeholder="e.g. MVT">
        </div>
        <div class="dossier-field">
          <label>PHOTO URL</label>
          <input type="text" id="df_photo_url" placeholder="https://...">
        </div>
        <div class="dossier-field">
          <label>INTEL STATS (M/A)</label>
          <input type="text" id="df_stat_ma" placeholder="e.g. 85">
        </div>
        <div class="dossier-field">
          <label>INTEL STATS (FOG)</label>
          <input type="text" id="df_stat_fog" placeholder="e.g. 42">
        </div>
        <div class="dossier-field">
          <label>INTEL STATS (F/R)</label>
          <input type="text" id="df_stat_fr" placeholder="e.g. INTEL SOURCE">
        </div>
        <div class="dossier-field">
          <label>INTEL STATS (INT/BG)</label>
          <input type="text" id="df_stat_int" placeholder="e.g. 77">
        </div>
        <div class="dossier-field">
          <label>TARGET SUMMARY</label>
          <textarea id="df_summary" rows="5" placeholder="Enter target summary / background intel..."></textarea>
        </div>
      </div>
      <div class="dossier-modal-footer">
        <button class="btn-dossier cancel" onclick="closeDossierModal()">CANCEL</button>
        <button class="btn-dossier save" id="dossierSaveBtn">SAVE DOSSIER</button>
      </div>
    </div>
  </div>
  <!-- MODAL: PASSWORD GATE -->
  <div class="modal-overlay" id="authModal">
    <div class="modal-box" style="max-width:380px;">
      <div class="modal-header" style="border-bottom-color:#ff4c66;">
        <span class="modal-title"><i class="fas fa-lock" style="color:#ff4c66;margin-right:8px;"></i>S2 AUTHORIZATION REQUIRED</span>
        <button class="modal-close" onclick="closeModal('authModal')">&times;</button>
      </div>
      <div class="modal-body" style="text-align:center;padding:28px 20px;">
        <div style="font-family:var(--mono);font-size:.7rem;color:#888;margin-bottom:16px;letter-spacing:1px;">
          ENTER AUTHORIZATION CODE TO PROCEED
        </div>
        <input type="password" id="authPassInput" 
               style="width:100%;padding:12px 16px;background:#0a0a0a;border:1px solid #333;color:#00ff41;font-family:var(--mono);font-size:1rem;text-align:center;letter-spacing:4px;border-radius:4px;"
               placeholder="••••••••"
               autocomplete="off">
        <div id="authError" style="color:#ff4c66;font-family:var(--mono);font-size:.7rem;margin-top:10px;display:none;">
          ⚠ ACCESS DENIED — INVALID AUTHORIZATION CODE
        </div>
        <div style="font-family:var(--mono);font-size:.6rem;color:#444;margin-top:14px;">
          <i class="fas fa-shield-halved"></i> ALL EDIT OPERATIONS REQUIRE S2 CLEARANCE
        </div>
      </div>
      <div class="modal-footer" style="justify-content:center;gap:10px;">
        <button class="brief-btn danger" onclick="closeModal('authModal')">CANCEL</button>
        <button class="brief-btn save" id="authSubmitBtn" onclick="submitAuth()">
          <i class="fas fa-key"></i> AUTHORIZE
        </button>
      </div>
    </div>
  </div>
</main>

<script src="assets/js/intelligence.js"></script>
<script src="assets/js/deployment.js"></script>
<script src="assets/js/intel-dossier.js"></script>
</body>
</html>
