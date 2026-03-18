<?php
session_start();
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Unit Structure');

$pageTitle = 'Unit Structure';
include ROOT_PATH . '/includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
<style>
    /* Premium Military Theme - Public View */
    :root {
        --bg-dark: #0f1115;
        --accent-gold: #c5a059;
        --text-main: #ffffff;
        --node-width: 240px;
        --avatar-size: 70px;
        --font-main: 'Kanit', sans-serif;
    }

    body {
        margin: 0;
        overflow: hidden;
        /* Lock scroll for canvas */
        font-family: var(--font-main);
        background: #000;
    }

    /* Layout */
    .builder-layout {
        display: block;
        position: relative;
        padding: 0;
        /* Adjust for public header height if distinct, roughly 80px seems standard */
        height: calc(100vh - 80px);
        overflow: hidden;
        background: #000000;
    }

    /* Infinite Canvas */
    .chart-panel-container {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
        background-color: #000000;
        background-image: radial-gradient(rgba(197, 160, 89, 0.25) 1px, transparent 1px);
        background-size: 30px 30px;
        cursor: grab;
    }

    .chart-panel-container:active {
        cursor: grabbing;
    }

    /* Wrapper for Pan/Zoom */
    #chartWrapper {
        position: absolute;
        top: 0;
        left: 0;
        width: 0;
        height: 0;
        transform-origin: 0 0;
    }

    /* Nodes */
    .org-node {
        position: absolute;
        width: var(--node-width);
        background: linear-gradient(145deg, rgba(30, 34, 42, 0.75) 0%, rgba(20, 23, 28, 0.85) 100%);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(197, 160, 89, 0.25);
        border-radius: 16px;
        padding: 22px;
        text-align: center;
        /* cursor: grab; Allow local drag for inspection? Yes. */
        transition: box-shadow 0.3s ease, border-color 0.3s ease, transform 0.3s ease;
    }

    .org-node::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(197, 160, 89, 0.4), transparent);
        border-radius: 16px 16px 0 0;
    }

    .org-node:hover {
        border-color: rgba(197, 160, 89, 0.5);
        transform: translateY(-3px);
        box-shadow:
            0 20px 60px rgba(0, 0, 0, 0.7),
            0 0 35px rgba(197, 160, 89, 0.15),
            0 0 0 1px rgba(255, 255, 255, 0.05) inset;
        z-index: 20;
    }

    .org-node.dragging {
        opacity: 0.9;
        cursor: grabbing;
        z-index: 100;
        transform: scale(1.02);
    }

    .node-avatar {
        width: var(--avatar-size);
        height: var(--avatar-size);
        border-radius: 50%;
        margin: 0 auto 12px;
        border: 3px solid var(--accent-gold);
        overflow: hidden;
        background: #000;
        box-shadow:
            0 4px 15px rgba(0, 0, 0, 0.5),
            0 0 20px rgba(197, 160, 89, 0.15);
        transition: all 0.3s ease;
    }

    .node-avatar.clickable {
        cursor: pointer;
        pointer-events: auto;
    }

    .node-avatar.clickable:hover {
        transform: scale(1.1);
        box-shadow:
            0 8px 25px rgba(0, 0, 0, 0.7),
            0 0 40px rgba(197, 160, 89, 0.4);
        border-color: #fff;
    }

    .node-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .node-rank {
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--accent-gold);
        margin-bottom: 6px;
        text-shadow: 0 0 10px rgba(197, 160, 89, 0.3);
    }

    .node-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
        pointer-events: none;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .node-position {
        font-size: 0.82rem;
        color: rgba(255, 255, 255, 0.6);
        font-style: italic;
        pointer-events: none;
    }

    /* SVG Layer for Lines */
    #connectionsLayer {
        position: absolute;
        top: 0;
        left: 0;
        width: 1px;
        height: 1px;
        overflow: visible;
        pointer-events: none;
        z-index: 1;
    }

    /* SVG Paths */
    .connector-path {
        fill: none;
        stroke: var(--accent-gold);
        stroke-width: 2px;
        opacity: 0.7;
        pointer-events: none;
        /* Read only */
        filter: drop-shadow(0 0 4px rgba(197, 160, 89, 0.4));
    }

    /* Controls */
    .canvas-controls {
        position: fixed;
        bottom: 40px;
        left: 40px;
        display: flex;
        gap: 8px;
        z-index: 500;
        padding: 12px;
        background: linear-gradient(135deg, rgba(20, 20, 25, 0.85) 0%, rgba(15, 15, 20, 0.9) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 16px;
        border: 1px solid rgba(197, 160, 89, 0.2);
        box-shadow:
            0 8px 32px rgba(0, 0, 0, 0.6),
            0 0 20px rgba(197, 160, 89, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.05);
    }

    .ctrl-btn {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        border: 1px solid rgba(197, 160, 89, 0.3);
        background: linear-gradient(135deg, rgba(30, 30, 35, 0.9) 0%, rgba(20, 20, 25, 0.95) 100%);
        color: rgba(255, 255, 255, 0.8);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .ctrl-btn:hover {
        background: linear-gradient(135deg, var(--accent-gold) 0%, #a88942 100%);
        color: #000;
        border-color: var(--accent-gold);
        transform: translateY(-2px);
    }
</style>

<div class="builder-layout">
    <div class="chart-panel-container" id="chartContainer">
        <!-- Transforms apply to this wrapper -->
        <div id="chartWrapper">
            <!-- SVG Layer -->
            <svg id="connectionsLayer"></svg>
            <!-- Nodes Container -->
            <div id="nodesLayer"></div>
        </div>

        <div class="canvas-controls">
            <button class="ctrl-btn" onclick="zoomIn()"><i class="fas fa-plus"></i></button>
            <button class="ctrl-btn" onclick="zoomOut()"><i class="fas fa-minus"></i></button>
            <button class="ctrl-btn" onclick="resetView()"><i class="fas fa-compress-arrows-alt"></i></button>
        </div>
    </div>
</div>

<script>
    const API_URL = 'api/chain.php';

    // State
    let data = [];

    // Viewport
    let panX = 0, panY = 0;
    let scale = 1;
    let isPanning = false;
    let startPanX = 0, startPanY = 0;

    // Dragging Nodes (Local Visual Only - No Save)
    let isDraggingNode = false;
    let draggedNodeId = null;
    let dragOffsetX = 0, dragOffsetY = 0;

    // DOM
    const container = document.getElementById('chartContainer');
    const wrapper = document.getElementById('chartWrapper');
    const svgLayer = document.getElementById('connectionsLayer');
    const nodesLayer = document.getElementById('nodesLayer');

    document.addEventListener('DOMContentLoaded', () => {
        initCanvas();
        loadData();
    });

    /* --- Canvas Interactions --- */
    function initCanvas() {
        // Pan
        container.addEventListener('mousedown', (e) => {
            if (e.target === container || e.target === wrapper || e.target === svgLayer) {
                isPanning = true;
                startPanX = e.clientX - panX;
                startPanY = e.clientY - panY;
                container.style.cursor = 'grabbing';
            }
        });

        window.addEventListener('mousemove', (e) => {
            if (isPanning) {
                e.preventDefault();
                panX = e.clientX - startPanX;
                panY = e.clientY - startPanY;
                updateTransform();
            }
            if (isDraggingNode && draggedNodeId) {
                e.preventDefault();
                // Calc new pos in world coordinates
                const containerRect = container.getBoundingClientRect();
                const mouseX = (e.clientX - containerRect.left - panX) / scale;
                const mouseY = (e.clientY - containerRect.top - panY) / scale;

                const x = mouseX - dragOffsetX;
                const y = mouseY - dragOffsetY;

                updateNodeVisual(draggedNodeId, x, y);
                renderConnections(); // Live update lines
            }
        });

        window.addEventListener('mouseup', () => {
            if (isPanning) {
                isPanning = false;
                container.style.cursor = 'grab';
            }
            if (isDraggingNode) {
                isDraggingNode = false;
                draggedNodeId = null;
                // No save call here
            }
        });

        // Zoom
        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomStep = 0.1;
            const delta = -Math.sign(e.deltaY);
            let newScale = scale + (delta * zoomStep);
            newScale = Math.min(Math.max(0.1, newScale), 5); // Limits

            // Zoom towards mouse
            const wx = (e.clientX - panX) / scale;
            const wy = (e.clientY - panY) / scale;

            scale = newScale;

            panX = e.clientX - (scale * wx);
            panY = e.clientY - (scale * wy);

            updateTransform();
        });
    }

    function updateTransform() {
        wrapper.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
    }

    /* --- Data Handling --- */
    async function loadData() {
        try {
            const res = await fetch(`${API_URL}?action=tree`).then(r => r.json());
            if (res.success) {
                data = res.data;
                renderNodes();
                renderConnections();
            }
        } catch (e) {
            console.error("Failed to load chain data", e);
        }
    }

    function renderNodes() {
        nodesLayer.innerHTML = '';
        data.forEach(node => {
            const el = document.createElement('div');
            el.className = 'org-node';
            el.dataset.id = node.id;

            // Coordinates
            let x = node.coc_x !== null ? parseInt(node.coc_x) : 0;
            let y = node.coc_y !== null ? parseInt(node.coc_y) : 0;
            // Store for local dragging
            node.currentX = x;
            node.currentY = y;

            el.style.left = x + 'px';
            el.style.top = y + 'px';

            const isCustom = node.card_type === 'custom' || parseInt(node.id) < 0;
            el.innerHTML = `
                <div class="node-avatar clickable" onclick="event.stopPropagation(); showResume(${node.id}, '${node.card_type || 'user'}')">
                    <img src="${node.image_url || 'assets/images/default_avatar.png'}" onerror="this.src='assets/images/default_avatar.png'">
                </div>
                <div class="node-rank">${node.rank || ''}</div>
                <div class="node-name">${node.name}</div>
                <div class="node-position">${node.position || ''}</div>
            `;


            nodesLayer.appendChild(el);
        });
    }

    function updateNodeVisual(id, x, y) {
        // Snap grid
        x = Math.round(x / 10) * 10;
        y = Math.round(y / 10) * 10;

        const node = data.find(n => n.id == id);
        if (node) {
            node.currentX = x;
            node.currentY = y;
            // Update node.coc_x/y so renderConnections knows? 
            // In renderConnections we'll use currentX/Y if available

            const el = nodesLayer.querySelector(`.org-node[data-id="${id}"]`);
            if (el) {
                el.style.left = x + 'px';
                el.style.top = y + 'px';
            }
        }
    }

    /* --- Connections (SVG) --- */
    function renderConnections() {
        svgLayer.innerHTML = '';

        data.forEach(node => {
            if (node.parent_id) {
                const parent = data.find(n => n.id == node.parent_id);
                if (parent) {
                    drawOrthogonalPath(parent, node);
                }
            }
        });
    }

    function drawOrthogonalPath(parent, child) {
        // Use coordinates (prefer current dragged pos)
        const pX = parent.currentX !== undefined ? parent.currentX : (parent.coc_x || 0);
        const pY = parent.currentY !== undefined ? parent.currentY : (parent.coc_y || 0);
        const cX = child.currentX !== undefined ? child.currentX : (child.coc_x || 0);
        const cY = child.currentY !== undefined ? child.currentY : (child.coc_y || 0);

        // Assumption: Nodes are fixed size roughly
        const W = 240;
        // Approximation for height or get exact from DOM? DOM is expensive in loop.
        // Let's assume standard height ~180px for calculations
        const pH = 180;
        const cH = 180;

        // Calculate centers
        const pCenterX = pX + W / 2;
        const pCenterY = pY + pH / 2;
        const cCenterX = cX + W / 2;
        const cCenterY = cY + cH / 2;

        let startX, startY, endX, endY;
        let routeType;

        // Determine layout
        const dx = cCenterX - pCenterX;
        const dy = cCenterY - pCenterY;

        if (cY > pY + pH - 20) {
            // Child is BELOW
            routeType = 'vertical';
            startX = pCenterX;
            startY = pY + pH;
            endX = cCenterX;
            endY = cY;
        } else if (cY + cH < pY + 20) {
            // Child is ABOVE
            routeType = 'vertical';
            startX = pCenterX;
            startY = pY;
            endX = cCenterX;
            endY = cY + cH;
        } else {
            // Side
            routeType = 'horizontal';
            if (dx > 0) {
                // Right
                startX = pX + W;
                startY = pCenterY;
                endX = cX;
                endY = cCenterY;
            } else {
                // Left
                startX = pX;
                startY = pCenterY;
                endX = cX + W;
                endY = cCenterY;
            }
        }

        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        path.setAttribute("class", "connector-path");

        let d = '';

        if (routeType === 'vertical') {
            const midY = (startY + endY) / 2;
            if (Math.abs(startX - endX) < 3) {
                d = `M ${startX} ${startY} L ${endX} ${endY}`;
            } else {
                d = `M ${startX} ${startY} L ${startX} ${midY} L ${endX} ${midY} L ${endX} ${endY}`;
            }
        } else {
            const midX = (startX + endX) / 2;
            if (Math.abs(startY - endY) < 3) {
                d = `M ${startX} ${startY} L ${endX} ${endY}`;
            } else {
                d = `M ${startX} ${startY} L ${midX} ${startY} L ${midX} ${endY} L ${endX} ${endY}`;
            }
        }

        path.setAttribute("d", d);
        svgLayer.appendChild(path);
    }

    /* --- Utils --- */
    window.zoomIn = () => { scale += 0.2; updateTransform(); };
    window.zoomOut = () => { scale = Math.max(0.1, scale - 0.2); updateTransform(); };
    window.resetView = () => { scale = 1; panX = 0; panY = 0; updateTransform(); };

    // Resume Popup
    function showResume(nodeId, cardType) {
        if (cardType === 'custom') {
            alert('Custom cards ไม่มี Resume');
            return;
        }
        // Use existing resume modal from resume_modal.php
        if (typeof loadResumeData === 'function') {
            loadResumeData(nodeId);
        } else {
            showClassifiedModal();
        }
    }

    function showClassifiedModal() {
        document.getElementById('classifiedModal').style.display = 'flex';
    }

    function closeClassifiedModal() {
        document.getElementById('classifiedModal').style.display = 'none';
    }
</script>

<!-- Classified Access Modal for non-logged in users -->
<style>
    .classified-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.92);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(15px);
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .classified-box {
        text-align: center;
        padding: 50px 60px;
        max-width: 450px;
        background: linear-gradient(145deg, rgba(25, 28, 35, 0.9) 0%, rgba(15, 17, 21, 0.95) 100%);
        border: 1px solid rgba(197, 160, 89, 0.25);
        border-radius: 20px;
        box-shadow:
            0 25px 80px rgba(0, 0, 0, 0.8),
            0 0 60px rgba(197, 160, 89, 0.1),
            inset 0 1px 0 rgba(255, 255, 255, 0.05);
        animation: slideUp 0.4s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .classified-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 25px;
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.15) 0%, rgba(197, 160, 89, 0.05) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(197, 160, 89, 0.3);
        box-shadow: 0 0 40px rgba(197, 160, 89, 0.2);
    }

    .classified-icon i {
        font-size: 2.2rem;
        color: var(--accent-gold, #c5a059);
        text-shadow: 0 0 20px rgba(197, 160, 89, 0.5);
    }

    .classified-title {
        color: #fff;
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 12px;
        letter-spacing: 3px;
        text-transform: uppercase;
    }

    .classified-subtitle {
        color: rgba(255, 255, 255, 0.5);
        margin-bottom: 35px;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    .classified-btn {
        display: inline-block;
        padding: 14px 50px;
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.15) 0%, rgba(197, 160, 89, 0.05) 100%);
        border: 2px solid var(--accent-gold, #c5a059);
        color: var(--accent-gold, #c5a059);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        border-radius: 8px;
    }

    .classified-btn:hover {
        background: linear-gradient(135deg, var(--accent-gold, #c5a059) 0%, #a88942 100%);
        color: #000;
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(197, 160, 89, 0.3);
    }

    .classified-close {
        display: block;
        margin: 25px auto 0;
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.3);
        cursor: pointer;
        font-size: 0.8rem;
        transition: color 0.2s;
    }

    .classified-close:hover {
        color: rgba(255, 255, 255, 0.6);
    }
</style>
<div id="classifiedModal" class="classified-overlay">
    <div class="classified-box">
        <div class="classified-icon">
            <i class="fas fa-lock"></i>
        </div>
        <h2 class="classified-title">Classified Access Required</h2>
        <p class="classified-subtitle">You must be a registered operative to view this section.</p>
        <a href="login.php" class="classified-btn">Authenticate</a>
        <button onclick="closeClassifiedModal()" class="classified-close">Close</button>
    </div>
</div>

<?php if (isLoggedIn()): ?>
    <?php include ROOT_PATH . '/includes/resume_modal.php'; ?>
<?php endif; ?>
