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
</head>
<body>
<div class="scanline"></div>
<div class="crt-overlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="assets/images/logo.png" alt="STORM" onerror="this.style.display='none'">
    <span>STORMSURGE</span>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item active" data-tab="opshub"><i class="fas fa-crosshairs"></i><span>OPS HUB</span></a>
    <a class="nav-item" data-tab="intel"><i class="fas fa-file-shield"></i><span>INTEL</span></a>
    <a class="nav-item" data-tab="sorties"><i class="fas fa-jet-fighter"></i><span>SORTIES</span></a>
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
      <button class="tab-btn" data-tab="sorties">TACTICAL MAP</button>
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

  <!-- TAB: SORTIES -->
  <section class="tab-content" id="tab-sorties">
    <div class="panel full-panel">
      <div class="panel-header">
        <div class="panel-title"><i class="fas fa-jet-fighter"></i> ACTIVE SORTIES</div>
        <div class="panel-controls"><span class="badge" id="sortieCount">0</span><button class="btn-add" onclick="openCreateModal('sorties')"><i class="fas fa-plus"></i></button></div>
      </div>
      <div class="panel-body" id="sortieList"></div>
    </div>
  </section>
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
