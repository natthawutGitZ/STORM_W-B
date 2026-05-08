<?php
require_once ROOT_PATH . '/includes/db.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Intelligence | S.T.O.R.M.</title>
<link rel="icon" href="assets/images/logo.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0a;--bg2:#111;--bg3:#1a1a1a;--red:#dc143c;--red-dim:rgba(220,20,60,.15);--red-glow:rgba(220,20,60,.3);--green:#00ff41;--amber:#ff8c00;--cyan:#00d4ff;--text:#e0e0e0;--muted:#666;--border:rgba(220,20,60,.2);--font:'Rajdhani',sans-serif;--mono:'Share Tech Mono',monospace}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(220,20,60,.03) 2px,rgba(220,20,60,.03) 4px);pointer-events:none;z-index:9999}
.scanline{position:fixed;top:0;left:0;width:100%;height:4px;background:rgba(220,20,60,.1);animation:scan 6s linear infinite;pointer-events:none;z-index:9998}
@keyframes scan{0%{top:-4px}100%{top:100%}}
@keyframes flicker{0%,100%{opacity:1}50%{opacity:.97}}
@keyframes pulse{0%,100%{box-shadow:0 0 5px var(--red-glow)}50%{box-shadow:0 0 20px var(--red-glow)}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.container{max-width:1400px;margin:0 auto;padding:20px;position:relative}
/* HEADER */
.hud-header{text-align:center;padding:30px 0 20px;border-bottom:1px solid var(--border);margin-bottom:24px;position:relative}
.hud-header::after{content:'';position:absolute;bottom:-1px;left:50%;transform:translateX(-50%);width:200px;height:2px;background:var(--red);box-shadow:0 0 10px var(--red-glow)}
.hud-header h1{font-family:var(--mono);font-size:2rem;color:var(--red);text-transform:uppercase;letter-spacing:6px;text-shadow:0 0 20px var(--red-glow);animation:flicker 4s infinite}
.hud-header .sub{color:var(--muted);font-family:var(--mono);font-size:.8rem;letter-spacing:3px;margin-top:4px}
.hud-header .clock{font-family:var(--mono);color:var(--red);font-size:.85rem;margin-top:8px}
.hud-header .clock span{animation:blink 1s infinite}
/* TOP BAR */
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.status-lights{display:flex;gap:16px;font-family:var(--mono);font-size:.75rem}
.status-light{display:flex;align-items:center;gap:6px}
.status-light .dot{width:8px;height:8px;border-radius:50%;animation:pulse 2s infinite}
.dot-green{background:var(--green)}
.dot-amber{background:var(--amber)}
.dot-red{background:var(--red)}
.btn-s2{background:var(--red-dim);border:1px solid var(--red);color:var(--red);font-family:var(--mono);padding:8px 20px;border-radius:4px;cursor:pointer;font-size:.85rem;transition:all .3s;text-transform:uppercase;letter-spacing:2px}
.btn-s2:hover{background:var(--red);color:#fff;box-shadow:0 0 20px var(--red-glow)}
.btn-s2.authed{background:rgba(0,255,65,.1);border-color:var(--green);color:var(--green)}
/* GRID */
.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
@media(max-width:1024px){.grid{grid-template-columns:1fr}}
/* PANELS */
.panel{background:var(--bg2);border:1px solid var(--border);border-radius:2px;overflow:hidden;animation:fadeIn .5s ease forwards;position:relative}
.panel::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--red),transparent)}
.panel-head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:var(--bg3);border-bottom:1px solid var(--border)}
.panel-head h2{font-family:var(--mono);font-size:.9rem;color:var(--red);text-transform:uppercase;letter-spacing:3px;display:flex;align-items:center;gap:8px}
.panel-head h2 i{font-size:.8rem}
.panel-head .badge{font-family:var(--mono);font-size:.7rem;background:var(--red-dim);color:var(--red);padding:2px 8px;border-radius:2px;border:1px solid var(--border)}
.btn-add{background:none;border:1px solid var(--border);color:var(--muted);width:28px;height:28px;border-radius:2px;cursor:pointer;font-size:.75rem;transition:all .2s}
.btn-add:hover{border-color:var(--red);color:var(--red)}
.panel-body{padding:0;max-height:500px;overflow-y:auto}
.panel-body::-webkit-scrollbar{width:4px}
.panel-body::-webkit-scrollbar-thumb{background:var(--red-dim);border-radius:2px}
/* ITEMS */
.item{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:background .2s;position:relative}
.item:hover{background:rgba(220,20,60,.05)}
.item:last-child{border-bottom:none}
.item-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.item-name{font-family:var(--mono);font-size:.85rem;color:var(--text);letter-spacing:1px}
.tag{font-family:var(--mono);font-size:.65rem;padding:2px 8px;border-radius:2px;text-transform:uppercase;letter-spacing:1px;border:1px solid}
.tag-critical{color:#ff4444;border-color:#ff4444;background:rgba(255,68,68,.1)}
.tag-high{color:var(--amber);border-color:var(--amber);background:rgba(255,140,0,.1)}
.tag-medium{color:var(--cyan);border-color:var(--cyan);background:rgba(0,212,255,.1)}
.tag-low{color:var(--muted);border-color:var(--muted);background:rgba(102,102,102,.1)}
.tag-active,.tag-deployed{color:var(--green);border-color:var(--green);background:rgba(0,255,65,.1)}
.tag-completed,.tag-rtb{color:var(--cyan);border-color:var(--cyan);background:rgba(0,212,255,.1)}
.tag-pending,.tag-standby{color:var(--amber);border-color:var(--amber);background:rgba(255,140,0,.1)}
.tag-failed,.tag-mia{color:#ff4444;border-color:#ff4444;background:rgba(255,68,68,.1)}
.tag-topsecret{color:#ff4444;border-color:#ff4444;background:rgba(255,68,68,.1)}
.tag-secret{color:var(--amber);border-color:var(--amber);background:rgba(255,140,0,.1)}
.tag-confidential{color:var(--cyan);border-color:var(--cyan);background:rgba(0,212,255,.1)}
.tag-unclassified{color:var(--green);border-color:var(--green);background:rgba(0,255,65,.1)}
.item-desc{font-size:.8rem;color:var(--muted);line-height:1.4;margin-top:4px}
.item-meta{display:flex;gap:12px;margin-top:8px;font-family:var(--mono);font-size:.7rem;color:var(--muted)}
.item-meta i{color:var(--red);margin-right:3px}
.item-actions{display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);gap:6px}
.item:hover .item-actions{display:flex}
.btn-icon{background:none;border:1px solid var(--border);color:var(--muted);width:26px;height:26px;border-radius:2px;cursor:pointer;font-size:.7rem;transition:all .2s;display:flex;align-items:center;justify-content:center}
.btn-icon:hover{border-color:var(--red);color:var(--red)}
.btn-icon.del:hover{border-color:#ff4444;color:#ff4444}
/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal{background:var(--bg2);border:1px solid var(--red);border-radius:4px;width:90%;max-width:480px;position:relative}
.modal::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--red);box-shadow:0 0 10px var(--red-glow)}
.modal-head{padding:16px 20px;border-bottom:1px solid var(--border);font-family:var(--mono);color:var(--red);text-transform:uppercase;letter-spacing:2px;font-size:.9rem;display:flex;justify-content:space-between;align-items:center}
.modal-close{background:none;border:none;color:var(--muted);font-size:1.2rem;cursor:pointer}
.modal-close:hover{color:var(--red)}
.modal-body{padding:20px}
.field{margin-bottom:14px}
.field label{display:block;font-family:var(--mono);font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.field input,.field select,.field textarea{width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:10px 12px;font-family:var(--mono);font-size:.85rem;border-radius:2px;outline:none;transition:border-color .2s}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--red)}
.field textarea{min-height:70px;resize:vertical}
.field select{cursor:pointer}
.field select option{background:var(--bg2)}
.modal-foot{padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}
.btn-save{background:var(--red);border:none;color:#fff;font-family:var(--mono);padding:8px 24px;border-radius:2px;cursor:pointer;text-transform:uppercase;letter-spacing:1px;font-size:.8rem;transition:all .2s}
.btn-save:hover{box-shadow:0 0 15px var(--red-glow)}
.btn-cancel{background:none;border:1px solid var(--border);color:var(--muted);font-family:var(--mono);padding:8px 16px;border-radius:2px;cursor:pointer;text-transform:uppercase;letter-spacing:1px;font-size:.8rem}
.btn-cancel:hover{border-color:var(--red);color:var(--red)}
/* AUTH MODAL */
.auth-input{text-align:center;margin:20px 0}
.auth-input input{text-align:center;font-size:1.5rem;letter-spacing:8px;width:180px;background:var(--bg);border:1px solid var(--border);color:var(--red);padding:12px;font-family:var(--mono);border-radius:2px}
.auth-input input:focus{border-color:var(--red);box-shadow:0 0 10px var(--red-glow)}
.auth-error{color:#ff4444;font-family:var(--mono);font-size:.8rem;text-align:center;margin-top:8px;display:none}
.empty{text-align:center;color:var(--muted);font-family:var(--mono);padding:40px;font-size:.8rem}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="container">
<div class="hud-header">
<h1><i class="fas fa-satellite-dish"></i> Tactical Intelligence</h1>
<div class="sub">S.T.O.R.M. — Strategic Command Intelligence Division</div>
<div class="clock" id="clock"></div>
</div>
<div class="top-bar">
<div class="status-lights">
<div class="status-light"><div class="dot dot-green"></div>COMMS ONLINE</div>
<div class="status-light"><div class="dot dot-amber"></div>INTEL FEED</div>
<div class="status-light"><div class="dot dot-red"></div>THREAT LVL</div>
</div>
<button class="btn-s2" id="btnAuth" onclick="showAuthModal()"><i class="fas fa-lock"></i> S2 ACCESS</button>
</div>

<div class="grid">
<!-- OPERATIONS -->
<div class="panel" style="animation-delay:.1s">
<div class="panel-head">
<h2><i class="fas fa-crosshairs"></i> Operation Brief</h2>
<div style="display:flex;gap:6px;align-items:center"><span class="badge" id="opCount">0</span><button class="btn-add" onclick="openCreateModal('operations')" title="Add"><i class="fas fa-plus"></i></button></div>
</div>
<div class="panel-body" id="opList"><div class="empty">LOADING...</div></div>
</div>
<!-- INTEL -->
<div class="panel" style="animation-delay:.2s">
<div class="panel-head">
<h2><i class="fas fa-file-shield"></i> Latest Intelligence</h2>
<div style="display:flex;gap:6px;align-items:center"><span class="badge" id="intelCount">0</span><button class="btn-add" onclick="openCreateModal('reports')" title="Add"><i class="fas fa-plus"></i></button></div>
</div>
<div class="panel-body" id="intelList"><div class="empty">LOADING...</div></div>
</div>
<!-- SORTIES -->
<div class="panel" style="animation-delay:.3s">
<div class="panel-head">
<h2><i class="fas fa-jet-fighter"></i> Active Sorties</h2>
<div style="display:flex;gap:6px;align-items:center"><span class="badge" id="sortieCount">0</span><button class="btn-add" onclick="openCreateModal('sorties')" title="Add"><i class="fas fa-plus"></i></button></div>
</div>
<div class="panel-body" id="sortieList"><div class="empty">LOADING...</div></div>
</div>
</div>
</div>

<!-- AUTH MODAL -->
<div class="modal-overlay" id="authModal">
<div class="modal" style="max-width:360px">
<div class="modal-head"><span><i class="fas fa-shield-halved"></i> S2 Authorization</span><button class="modal-close" onclick="closeModal('authModal')">&times;</button></div>
<div class="modal-body">
<p style="text-align:center;color:var(--muted);font-size:.85rem;margin-bottom:6px">Enter S2 clearance code</p>
<div class="auth-input"><input type="password" id="authPass" maxlength="10" onkeydown="if(event.key==='Enter')verifyAuth()"></div>
<div class="auth-error" id="authError">ACCESS DENIED</div>
</div>
<div class="modal-foot" style="justify-content:center"><button class="btn-save" onclick="verifyAuth()"><i class="fas fa-key"></i> Authenticate</button></div>
</div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
<div class="modal">
<div class="modal-head"><span id="editTitle">Edit</span><button class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
<div class="modal-body" id="editBody"></div>
<div class="modal-foot"><button class="btn-cancel" onclick="closeModal('editModal')">Cancel</button><button class="btn-save" id="editSave">Save</button></div>
</div>
</div>

<script>
const API = 'api/intelligence.php';
let s2Authed = false;
let editState = {};

// Clock
function updateClock(){
  const d=new Date();
  const z=(n)=>String(n).padStart(2,'0');
  document.getElementById('clock').innerHTML=
    `${d.getFullYear()}-${z(d.getMonth()+1)}-${z(d.getDate())} ${z(d.getHours())}<span>:</span>${z(d.getMinutes())}<span>:</span>${z(d.getSeconds())} ICT`;
}
setInterval(updateClock,1000);updateClock();

// Auth
function showAuthModal(){
  if(s2Authed){s2Authed=false;document.getElementById('btnAuth').classList.remove('authed');document.getElementById('btnAuth').innerHTML='<i class="fas fa-lock"></i> S2 ACCESS';loadAll();return;}
  document.getElementById('authPass').value='';
  document.getElementById('authError').style.display='none';
  document.getElementById('authModal').classList.add('show');
  setTimeout(()=>document.getElementById('authPass').focus(),100);
}
function verifyAuth(){
  const p=document.getElementById('authPass').value;
  if(p==='S2'){s2Authed=true;closeModal('authModal');document.getElementById('btnAuth').classList.add('authed');document.getElementById('btnAuth').innerHTML='<i class="fas fa-unlock"></i> S2 ACTIVE';loadAll();}
  else{document.getElementById('authError').style.display='block';document.getElementById('authPass').value='';document.getElementById('authPass').focus();}
}
function closeModal(id){document.getElementById(id).classList.remove('show');}

// Load data
async function loadAll(){await Promise.all([loadOps(),loadIntel(),loadSorties()]);}

async function loadOps(){
  const r=await fetch(`${API}?action=list&type=operations`);const d=await r.json();
  if(!d.success)return;
  document.getElementById('opCount').textContent=d.data.length;
  const el=document.getElementById('opList');
  if(!d.data.length){el.innerHTML='<div class="empty">NO OPERATIONS</div>';return;}
  el.innerHTML=d.data.map(o=>`<div class="item" ondblclick="openEditModal('operations',${o.id})">
    <div class="item-top"><span class="item-name">${esc(o.codename)}</span><span class="tag tag-${o.priority.toLowerCase()}">${o.priority}</span></div>
    <div class="item-top" style="margin-bottom:0"><span class="tag tag-${o.status.toLowerCase()}">${o.status}</span></div>
    <div class="item-desc">${esc(o.brief||'')}</div>
    <div class="item-meta"><span><i class="fas fa-user"></i>${esc(o.commander||'N/A')}</span><span><i class="fas fa-clock"></i>${timeAgo(o.updated_at)}</span></div>
    ${s2Authed?`<div class="item-actions"><button class="btn-icon" onclick="event.stopPropagation();openEditModal('operations',${o.id})"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteItem('operations',${o.id})"><i class="fas fa-trash"></i></button></div>`:''}
  </div>`).join('');
}

async function loadIntel(){
  const r=await fetch(`${API}?action=list&type=reports`);const d=await r.json();
  if(!d.success)return;
  document.getElementById('intelCount').textContent=d.data.length;
  const el=document.getElementById('intelList');
  if(!d.data.length){el.innerHTML='<div class="empty">NO INTEL</div>';return;}
  el.innerHTML=d.data.map(o=>`<div class="item" ondblclick="openEditModal('reports',${o.id})">
    <div class="item-top"><span class="item-name">${esc(o.title)}</span><span class="tag tag-${o.classification.replace(/\s/g,'').toLowerCase()}">${o.classification}</span></div>
    <div class="item-desc">${esc(o.content||'')}</div>
    <div class="item-meta"><span><i class="fas fa-satellite-dish"></i>${esc(o.source)}</span><span><i class="fas fa-clock"></i>${timeAgo(o.created_at)}</span></div>
    ${s2Authed?`<div class="item-actions"><button class="btn-icon" onclick="event.stopPropagation();openEditModal('reports',${o.id})"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteItem('reports',${o.id})"><i class="fas fa-trash"></i></button></div>`:''}
  </div>`).join('');
}

async function loadSorties(){
  const r=await fetch(`${API}?action=list&type=sorties`);const d=await r.json();
  if(!d.success)return;
  document.getElementById('sortieCount').textContent=d.data.length;
  const el=document.getElementById('sortieList');
  if(!d.data.length){el.innerHTML='<div class="empty">NO SORTIES</div>';return;}
  el.innerHTML=d.data.map(o=>`<div class="item" ondblclick="openEditModal('sorties',${o.id})">
    <div class="item-top"><span class="item-name">${esc(o.callsign)}</span><span class="tag tag-${o.status.toLowerCase()}">${o.status}</span></div>
    <div class="item-desc">${esc(o.mission_type)} — ${esc(o.location||'Unknown')}</div>
    <div class="item-meta"><span><i class="fas fa-users"></i>${o.personnel} PAX</span><span><i class="fas fa-clock"></i>${timeAgo(o.updated_at)}</span></div>
    ${s2Authed?`<div class="item-actions"><button class="btn-icon" onclick="event.stopPropagation();openEditModal('sorties',${o.id})"><i class="fas fa-pen"></i></button><button class="btn-icon del" onclick="event.stopPropagation();deleteItem('sorties',${o.id})"><i class="fas fa-trash"></i></button></div>`:''}
  </div>`).join('');
}

// Forms
const FORMS={
  operations:[
    {k:'codename',l:'Codename',t:'text'},
    {k:'status',l:'Status',t:'select',opts:['ACTIVE','COMPLETED','FAILED','PENDING']},
    {k:'priority',l:'Priority',t:'select',opts:['CRITICAL','HIGH','MEDIUM','LOW']},
    {k:'brief',l:'Brief',t:'textarea'},
    {k:'commander',l:'Commander',t:'text'}
  ],
  reports:[
    {k:'title',l:'Title',t:'text'},
    {k:'classification',l:'Classification',t:'select',opts:['TOP SECRET','SECRET','CONFIDENTIAL','UNCLASSIFIED']},
    {k:'content',l:'Content',t:'textarea'},
    {k:'source',l:'Source',t:'select',opts:['HUMINT','SIGINT','CYBER','OSINT','GEOINT']}
  ],
  sorties:[
    {k:'callsign',l:'Callsign',t:'text'},
    {k:'mission_type',l:'Mission Type',t:'select',opts:['RECON','STRIKE','EXTRACTION','PATROL','ESCORT']},
    {k:'location',l:'Location',t:'text'},
    {k:'status',l:'Status',t:'select',opts:['DEPLOYED','RTB','STANDBY','MIA']},
    {k:'personnel',l:'Personnel',t:'number'}
  ]
};

function buildForm(type,data){
  return FORMS[type].map(f=>{
    const v=data?esc(String(data[f.k]||'')):'';
    if(f.t==='select') return `<div class="field"><label>${f.l}</label><select id="f_${f.k}">${f.opts.map(o=>`<option value="${o}"${v===o?' selected':''}>${o}</option>`).join('')}</select></div>`;
    if(f.t==='textarea') return `<div class="field"><label>${f.l}</label><textarea id="f_${f.k}">${v}</textarea></div>`;
    return `<div class="field"><label>${f.l}</label><input type="${f.t}" id="f_${f.k}" value="${v}"></div>`;
  }).join('');
}

function getFormData(type){
  const d={};FORMS[type].forEach(f=>{d[f.k]=document.getElementById('f_'+f.k).value;});return d;
}

function openCreateModal(type){
  if(!s2Authed){showAuthModal();return;}
  editState={type,id:null};
  document.getElementById('editTitle').textContent='CREATE '+type.toUpperCase();
  document.getElementById('editBody').innerHTML=buildForm(type,null);
  document.getElementById('editSave').onclick=()=>saveItem();
  document.getElementById('editModal').classList.add('show');
}

async function openEditModal(type,id){
  if(!s2Authed){showAuthModal();return;}
  const r=await fetch(`${API}?action=list&type=${type}`);const d=await r.json();
  const item=d.data.find(x=>x.id==id);if(!item)return;
  editState={type,id};
  document.getElementById('editTitle').textContent='EDIT — '+type.toUpperCase();
  document.getElementById('editBody').innerHTML=buildForm(type,item);
  document.getElementById('editSave').onclick=()=>saveItem();
  document.getElementById('editModal').classList.add('show');
}

async function saveItem(){
  const body={password:'S2',type:editState.type,fields:getFormData(editState.type)};
  const action=editState.id?'update':'create';
  if(editState.id)body.id=editState.id;
  const r=await fetch(`${API}?action=${action}`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const d=await r.json();
  if(d.success){closeModal('editModal');loadAll();}else{alert(d.error);}
}

async function deleteItem(type,id){
  if(!confirm('CONFIRM DELETE?'))return;
  const r=await fetch(`${API}?action=delete`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({password:'S2',type,id})});
  const d=await r.json();if(d.success)loadAll();else alert(d.error);
}

// Utils
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function timeAgo(dt){
  const diff=Math.floor((Date.now()-new Date(dt).getTime())/1000);
  if(diff<60)return diff+'s ago';if(diff<3600)return Math.floor(diff/60)+'m ago';
  if(diff<86400)return Math.floor(diff/3600)+'h ago';return Math.floor(diff/86400)+'d ago';
}

loadAll();
</script>
</body>
</html>
