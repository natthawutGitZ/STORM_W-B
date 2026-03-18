<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    redirect('/');
}

include ROOT_PATH . '/admin/includes/admin_header.php';
?>



<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
<style>
    /* Premium Military Theme - Admin */
    :root {
        --bg-dark: #0f1115;
        --bg-panel: rgba(20, 23, 28, 0.95);
        --accent-gold: #c5a059;
        --accent-gold-dim: rgba(197, 160, 89, 0.2);
        --text-main: #ffffff;
        --text-muted: #888888;
        --border-color: rgba(255, 255, 255, 0.08);
        --card-bg: linear-gradient(145deg, rgba(30, 34, 40, 0.9), rgba(20, 23, 28, 0.95));

        --node-width: 240px;
        --avatar-size: 70px;
        --line-color: var(--accent-gold);
        --line-thickness: 2px;
        --font-main: 'Kanit', sans-serif;
    }

    body {
        margin: 0;
        overflow: hidden;
        font-family: var(--font-main);
    }

    /* Layout */
    .builder-layout {
        display: block;
        position: relative;
        padding: 0;
        height: calc(125vh - 87.5px);
        /* Adjusted for zoom: 0.8 (100vh / 0.8 = 125vh, 70px / 0.8 = 87.5px) */
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
        /* Wrapper size doesn't matter, content is absolute */
        transform-origin: 0 0;
        /* transition: transform 0.05s ease-out; Removed for instant drag */
    }

    /* Nodes */
    /* Premium Org Node Cards */
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
        cursor: grab;
        box-shadow:
            0 15px 50px rgba(0, 0, 0, 0.6),
            0 0 0 1px rgba(255, 255, 255, 0.03) inset,
            0 2px 0 rgba(255, 255, 255, 0.05) inset;
        z-index: 10;
        user-select: none;
        transition: all 0.3s ease;
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
        box-shadow:
            0 30px 80px rgba(0, 0, 0, 0.8),
            0 0 50px rgba(197, 160, 89, 0.25),
            0 0 100px rgba(197, 160, 89, 0.1);
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

    .org-node:hover .node-avatar {
        box-shadow:
            0 6px 20px rgba(0, 0, 0, 0.6),
            0 0 30px rgba(197, 160, 89, 0.25);
    }

    .node-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        pointer-events: none;
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

    .node-actions {
        position: absolute;
        top: 12px;
        right: 12px;
        opacity: 0;
        transition: all 0.25s ease;
        display: flex;
        gap: 6px;
        transform: translateY(-5px);
    }

    .org-node:hover .node-actions {
        opacity: 1;
        transform: translateY(0);
    }

    .node-actions button {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1px solid rgba(197, 160, 89, 0.3);
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
        color: rgba(255, 255, 255, 0.8);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .node-actions button:hover {
        transform: scale(1.1);
    }

    .node-actions .edit-btn:hover {
        background: linear-gradient(135deg, var(--accent-gold) 0%, #a88942 100%);
        border-color: var(--accent-gold);
        color: #000;
        box-shadow: 0 0 15px rgba(197, 160, 89, 0.4);
    }

    .node-actions .remove-btn:hover {
        background: linear-gradient(135deg, #dc3545 0%, #a82835 100%);
        border-color: #dc3545;
        color: #fff;
        box-shadow: 0 0 15px rgba(220, 53, 69, 0.4);
    }

    /* Handles for linking */
    .connector-handle {
        position: absolute;
        width: 14px;
        height: 14px;
        background: linear-gradient(135deg, var(--accent-gold) 0%, #a88942 100%);
        border: 2px solid rgba(0, 0, 0, 0.6);
        border-radius: 50%;
        z-index: 999;
        cursor: crosshair;
        opacity: 0;
        transition: all 0.25s ease;
        box-shadow:
            0 2px 6px rgba(0, 0, 0, 0.4),
            0 0 0 2px rgba(197, 160, 89, 0.1);
    }

    .org-node:hover .connector-handle {
        opacity: 1;
        box-shadow:
            0 2px 8px rgba(0, 0, 0, 0.5),
            0 0 10px rgba(197, 160, 89, 0.3);
    }

    .connector-handle:hover {
        transform: scale(1.6) !important;
        box-shadow:
            0 4px 12px rgba(0, 0, 0, 0.6),
            0 0 20px rgba(197, 160, 89, 0.5);
    }

    .handle-top {
        top: -7px;
        left: 50%;
        transform: translateX(-50%);
    }

    .handle-bottom {
        bottom: -7px;
        left: 50%;
        transform: translateX(-50%);
    }

    .handle-left {
        left: -7px;
        top: 50%;
        transform: translateY(-50%);
    }

    .handle-right {
        right: -7px;
        top: 50%;
        transform: translateY(-50%);
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

    /* SVG Glow Filter Definition */
    #connectionsLayer defs {
        display: block;
    }

    /* SVG Paths */
    .connector-path {
        fill: none;
        stroke: var(--accent-gold);
        stroke-width: 2px;
        opacity: 0.7;
        transition: all 0.25s ease;
        pointer-events: stroke;
        cursor: pointer;
        filter: drop-shadow(0 0 4px rgba(197, 160, 89, 0.4));
    }

    .connector-path:hover {
        stroke-width: 4px;
        opacity: 1;
        cursor: pointer;
        stroke: #ff4444;
        filter: drop-shadow(0 0 8px rgba(255, 68, 68, 0.6));
        pointer-events: stroke;
    }

    .temp-path {
        fill: none;
        stroke: var(--accent-gold);
        stroke-width: 2.5px;
        stroke-dasharray: 8, 6;
        opacity: 0.9;
        pointer-events: none;
        filter: drop-shadow(0 0 6px rgba(197, 160, 89, 0.5));
        animation: tempPathPulse 1s ease-in-out infinite;
    }

    @keyframes tempPathPulse {

        0%,
        100% {
            opacity: 0.9;
        }

        50% {
            opacity: 0.5;
        }
    }

    /* DEBUG UI */
    #debugConsole {
        position: fixed;
        bottom: 10px;
        right: 10px;
        width: 300px;
        height: 150px;
        background: rgba(0, 0, 0, 0.8);
        border: 1px solid red;
        color: #0f0;
        font-family: monospace;
        font-size: 10px;
        overflow-y: auto;
        z-index: 9999;
        pointer-events: none;
        padding: 5px;
    }

    #versionTag {
        position: fixed;
        top: 80px;
        right: 10px;
        background: red;
        color: white;
        padding: 5px 10px;
        font-weight: bold;
        z-index: 9999;
        border-radius: 4px;
    }

    /* DEBUG UI */
    #debugConsole {
        position: fixed;
        bottom: 10px;
        right: 10px;
        width: 300px;
        height: 150px;
        background: rgba(0, 0, 0, 0.8);
        border: 1px solid red;
        color: #0f0;
        font-family: monospace;
        font-size: 10px;
        overflow-y: auto;
        z-index: 9999;
        pointer-events: none;
        padding: 5px;
    }

    #versionTag {
        position: fixed;
        top: 80px;
        right: 10px;
        background: red;
        color: white;
        padding: 5px 10px;
        font-weight: bold;
        z-index: 9999;
        border-radius: 4px;
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
        box-shadow:
            0 6px 20px rgba(197, 160, 89, 0.4),
            0 0 15px rgba(197, 160, 89, 0.2);
    }

    /* Floating Toolbox Panel */
    .toolbox-panel {
        position: fixed;
        top: 90px;
        right: 20px;
        width: 320px;
        max-height: calc(100vh - 130px);
        background: linear-gradient(180deg, rgba(20, 23, 28, 0.75) 0%, rgba(15, 18, 22, 0.85) 100%);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(197, 160, 89, 0.15);
        border-radius: 20px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        z-index: 600;
        box-shadow:
            0 20px 60px rgba(0, 0, 0, 0.7),
            0 0 40px rgba(197, 160, 89, 0.05),
            inset 0 1px 0 rgba(255, 255, 255, 0.08),
            inset 0 -1px 0 rgba(0, 0, 0, 0.2);
        animation: toolboxSlideIn 0.4s ease-out;
    }

    @keyframes toolboxSlideIn {
        from {
            opacity: 0;
            transform: translateX(30px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .toolbox-panel::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, rgba(197, 160, 89, 0.5), transparent);
        border-radius: 20px 20px 0 0;
    }

    .toolbox-title {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--accent-gold);
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(197, 160, 89, 0.2);
        padding-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .toolbox-title::before {
        content: '\f1b2';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        font-size: 0.9rem;
    }

    /* Create Custom Card Button */
    .create-custom-btn {
        width: 100%;
        padding: 12px 16px;
        margin-bottom: 15px;
        border-radius: 12px;
        border: 2px dashed rgba(197, 160, 89, 0.4);
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.1) 0%, rgba(197, 160, 89, 0.05) 100%);
        color: var(--accent-gold);
        font-family: var(--font-main);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .create-custom-btn:hover {
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.25) 0%, rgba(197, 160, 89, 0.15) 100%);
        border-color: rgba(197, 160, 89, 0.7);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(197, 160, 89, 0.2);
    }

    .create-custom-btn i {
        font-size: 1rem;
    }

    /* Custom Card Indicator */
    .org-node.custom-card {
        border-color: rgba(139, 92, 246, 0.4);
    }

    .org-node.custom-card::before {
        background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.5), transparent);
    }

    .org-node.custom-card:hover {
        border-color: rgba(139, 92, 246, 0.7);
        box-shadow:
            0 20px 60px rgba(0, 0, 0, 0.7),
            0 0 35px rgba(139, 92, 246, 0.2),
            0 0 0 1px rgba(255, 255, 255, 0.05) inset;
    }

    .custom-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        font-size: 0.6rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .toolbox-search {
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(197, 160, 89, 0.2);
        border-radius: 12px;
        padding: 12px 16px;
        color: #fff;
        margin-bottom: 18px;
        width: 100%;
        font-family: var(--font-main);
        font-size: 0.9rem;
        transition: all 0.3s ease;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .toolbox-search:focus {
        outline: none;
        border-color: rgba(197, 160, 89, 0.5);
        box-shadow:
            inset 0 2px 4px rgba(0, 0, 0, 0.2),
            0 0 15px rgba(197, 160, 89, 0.15);
    }

    .toolbox-search::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .user-list {
        flex: 1;
        overflow-y: auto;
        padding-right: 5px;
    }

    .user-list::-webkit-scrollbar {
        width: 6px;
    }

    .user-list::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
    }

    .user-list::-webkit-scrollbar-thumb {
        background: rgba(197, 160, 89, 0.3);
        border-radius: 3px;
    }

    .user-list::-webkit-scrollbar-thumb:hover {
        background: rgba(197, 160, 89, 0.5);
    }

    .user-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0.01) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: grab;
        user-select: none;
        transition: all 0.25s ease;
    }

    .user-card:hover {
        border-color: rgba(197, 160, 89, 0.4);
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.12) 0%, rgba(197, 160, 89, 0.05) 100%);
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .user-card:active {
        cursor: grabbing;
        transform: scale(0.98);
    }

    .user-card img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 2px solid rgba(197, 160, 89, 0.3);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    .user-card span {
        font-size: 0.9rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
    }

    /* Modal & Toast */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.85);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 3000;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }

    .modal {
        background: linear-gradient(145deg, rgba(30, 33, 40, 0.8) 0%, rgba(20, 23, 28, 0.9) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 32px;
        border-radius: 20px;
        border: 1px solid rgba(197, 160, 89, 0.25);
        width: 420px;
        box-shadow:
            0 25px 80px rgba(0, 0, 0, 0.7),
            0 0 60px rgba(197, 160, 89, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.08);
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-20px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .modal::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, rgba(197, 160, 89, 0.5), transparent);
        border-radius: 20px 20px 0 0;
    }

    .toast {
        background: linear-gradient(135deg, rgba(25, 28, 35, 0.9) 0%, rgba(20, 23, 28, 0.95) 100%);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        color: #fff;
        padding: 16px 24px;
        border-radius: 14px;
        position: fixed;
        bottom: 40px;
        right: 40px;
        transform: translateY(100px);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 4000;
        border-left: 4px solid var(--accent-gold);
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        border-bottom: 1px solid rgba(0, 0, 0, 0.2);
        font-family: var(--font-main);
        box-shadow:
            0 15px 50px rgba(0, 0, 0, 0.6),
            0 0 30px rgba(197, 160, 89, 0.06);
        max-width: 380px;
    }

    .toast.show {
        transform: translateY(0);
    }

    .toast.success {
        border-left-color: #4CAF50;
        box-shadow:
            0 15px 50px rgba(0, 0, 0, 0.6),
            0 0 25px rgba(76, 175, 80, 0.15);
    }

    .toast.error {
        border-left-color: #f44336;
        box-shadow:
            0 15px 50px rgba(0, 0, 0, 0.6),
            0 0 25px rgba(244, 67, 54, 0.15);
    }

    .toast.warning {
        border-left-color: #ff9800;
        box-shadow:
            0 15px 50px rgba(0, 0, 0, 0.6),
            0 0 25px rgba(255, 152, 0, 0.15);
    }

    /* Custom Confirm Modal */
    .confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.85);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 5000;
        backdrop-filter: blur(8px);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .confirm-overlay.show {
        display: flex;
        opacity: 1;
    }

    .confirm-box {
        background: rgba(30, 30, 30, 0.65);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        padding: 30px 35px;
        border-radius: 16px;
        border: 1px solid rgba(197, 160, 89, 0.3);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        max-width: 420px;
        text-align: center;
        animation: confirmSlideIn 0.3s ease-out;
    }

    @keyframes confirmSlideIn {
        from {
            transform: scale(0.9) translateY(-20px);
            opacity: 0;
        }

        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }

    .confirm-icon {
        font-size: 48px;
        margin-bottom: 15px;
        color: var(--accent-gold);
    }

    .confirm-title {
        font-size: 20px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 10px;
    }

    .confirm-message {
        font-size: 15px;
        color: #aaa;
        margin-bottom: 25px;
        line-height: 1.5;
    }

    .confirm-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .confirm-btn {
        padding: 12px 28px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-family: var(--font-main);
    }

    .confirm-btn.confirm {
        background: linear-gradient(135deg, var(--accent-gold), #a88942);
        color: #000;
    }

    .confirm-btn.confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(197, 160, 89, 0.4);
    }

    .confirm-btn.cancel {
        background: rgba(255, 255, 255, 0.1);
        color: #999;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .confirm-btn.cancel:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    /* Resume Modal */
    .resume-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.9);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 6000;
        backdrop-filter: blur(12px);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .resume-overlay.show {
        display: flex;
        opacity: 1;
    }

    .resume-modal {
        background: linear-gradient(145deg, rgba(30, 33, 40, 0.85) 0%, rgba(20, 23, 28, 0.95) 100%);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(197, 160, 89, 0.25);
        border-radius: 20px;
        width: 600px;
        max-width: 90vw;
        max-height: 85vh;
        overflow-y: auto;
        box-shadow:
            0 25px 80px rgba(0, 0, 0, 0.8),
            0 0 60px rgba(197, 160, 89, 0.1);
        animation: resumeSlideIn 0.4s ease-out;
    }

    .resume-modal::-webkit-scrollbar {
        width: 8px;
    }

    .resume-modal::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.2);
    }

    .resume-modal::-webkit-scrollbar-thumb {
        background: rgba(197, 160, 89, 0.3);
        border-radius: 4px;
    }

    @keyframes resumeSlideIn {
        from {
            transform: scale(0.9) translateY(-30px);
            opacity: 0;
        }

        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }

    .resume-header {
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.15) 0%, rgba(197, 160, 89, 0.05) 100%);
        padding: 24px;
        border-bottom: 1px solid rgba(197, 160, 89, 0.2);
        display: flex;
        align-items: center;
        gap: 20px;
        position: relative;
    }

    .resume-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, var(--accent-gold), transparent);
        border-radius: 20px 20px 0 0;
    }

    .resume-close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(0, 0, 0, 0.4);
        color: rgba(255, 255, 255, 0.6);
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .resume-close:hover {
        background: rgba(220, 53, 69, 0.3);
        border-color: #dc3545;
        color: #fff;
    }

    .resume-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 3px solid var(--accent-gold);
        overflow: hidden;
        box-shadow: 0 0 20px rgba(197, 160, 89, 0.3);
    }

    .resume-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .resume-title-section {
        flex: 1;
    }

    .resume-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 4px;
    }

    .resume-rank {
        font-size: 0.85rem;
        color: var(--accent-gold);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .resume-position {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.6);
        font-style: italic;
    }

    .resume-body {
        padding: 24px;
    }

    .resume-section {
        margin-bottom: 24px;
    }

    .resume-section:last-child {
        margin-bottom: 0;
    }

    .resume-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--accent-gold);
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(197, 160, 89, 0.2);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .resume-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .resume-info-item {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 12px;
        padding: 12px 16px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .resume-info-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .resume-info-value {
        font-size: 0.95rem;
        color: #fff;
        font-weight: 500;
    }

    .resume-qa-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .resume-qa-item {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 12px;
        padding: 14px 16px;
        border-left: 3px solid rgba(197, 160, 89, 0.4);
    }

    .resume-question {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 6px;
    }

    .resume-answer {
        font-size: 0.95rem;
        color: #fff;
        line-height: 1.5;
    }

    .resume-loading {
        text-align: center;
        padding: 40px;
        color: rgba(255, 255, 255, 0.5);
    }

    .resume-loading i {
        font-size: 2rem;
        margin-bottom: 12px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .resume-no-data {
        text-align: center;
        padding: 30px;
        color: rgba(255, 255, 255, 0.4);
    }

    /* Clickable Avatar */
    .node-avatar.clickable {
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .node-avatar.clickable:hover {
        transform: scale(1.1);
        box-shadow:
            0 8px 25px rgba(0, 0, 0, 0.7),
            0 0 40px rgba(197, 160, 89, 0.4);
        border-color: #fff;
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

    <div class="toolbox-panel">
        <h3 class="toolbox-title">คลังข้อมูล (Unassigned)</h3>
        <button class="create-custom-btn" onclick="openCreateCustomModal()">
            <i class="fas fa-plus-circle"></i> สร้าง Custom Card
        </button>
        <input type="text" class="toolbox-search" id="userSearch" placeholder="ค้นหา..." onkeyup="filterUsers()">
        <div id="userList" class="user-list"></div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="nodeModal">
    <div class="modal">
        <h3 id="modalTitle" style="color:#fff; margin-bottom:20px;">แก้ไขข้อมูล (Edit Details)</h3>
        <form id="nodeForm">
            <input type="hidden" id="nodeId" name="id">
            <input type="hidden" id="nodeCardType" name="card_type">
            <div style="margin-bottom:15px"><label style="color:#888;display:block;margin-bottom:5px">ชื่อ
                    (Name)</label><input class="toolbox-search" name="name" id="nodeName" required></div>
            <div style="margin-bottom:15px"><label style="color:#888;display:block;margin-bottom:5px">ยศ
                    (Rank)</label>
                <select class="toolbox-search" name="rank" id="nodeRank" style="width:100%;cursor:pointer;">
                    <option value="">-- เลือกยศ --</option>
                    <option value="General of the Army (GA)">General of the Army (GA)</option>
                    <option value="General (GEN)">General (GEN)</option>
                    <option value="Lieutenant General (LTG)">Lieutenant General (LTG)</option>
                    <option value="Major General (MG)">Major General (MG)</option>
                    <option value="Brigadier General (BG)">Brigadier General (BG)</option>
                    <option value="Colonel (COL)">Colonel (COL)</option>
                    <option value="Lieutenant Colonel (LTC)">Lieutenant Colonel (LTC)</option>
                    <option value="Major (MAJ)">Major (MAJ)</option>
                    <option value="Captain (CPT)">Captain (CPT)</option>
                    <option value="First Lieutenant (1LT)">First Lieutenant (1LT)</option>
                    <option value="Second Lieutenant (2LT)">Second Lieutenant (2LT)</option>
                    <option value="Chief Warrant Officer 5 (CW5)">Chief Warrant Officer 5 (CW5)</option>
                    <option value="Chief Warrant Officer 4 (CW4)">Chief Warrant Officer 4 (CW4)</option>
                    <option value="Chief Warrant Officer 3 (CW3)">Chief Warrant Officer 3 (CW3)</option>
                    <option value="Chief Warrant Officer 2 (CW2)">Chief Warrant Officer 2 (CW2)</option>
                    <option value="Warrant Officer 1 (WO1)">Warrant Officer 1 (WO1)</option>
                    <option value="Sergeant Major of the Army (SMA)">Sergeant Major of the Army (SMA)</option>
                    <option value="Command Sergeant Major (CSM)">Command Sergeant Major (CSM)</option>
                    <option value="Sergeant Major (SGM)">Sergeant Major (SGM)</option>
                    <option value="First Sergeant (1SG)">First Sergeant (1SG)</option>
                    <option value="Master Sergeant (MSG)">Master Sergeant (MSG)</option>
                    <option value="Sergeant First Class (SFC)">Sergeant First Class (SFC)</option>
                    <option value="Staff Sergeant (SSG)">Staff Sergeant (SSG)</option>
                    <option value="Sergeant (SGT)">Sergeant (SGT)</option>
                    <option value="Corporal (CPL)">Corporal (CPL)</option>
                    <option value="Specialist (SPC)">Specialist (SPC)</option>
                    <option value="Private First Class (PFC)">Private First Class (PFC)</option>
                    <option value="Private (PV2)">Private (PV2)</option>
                    <option value="Private (PV1)">Private (PV1)</option>
                    <option value="Recruit">Recruit</option>
                </select>
            </div>
            <div style="margin-bottom:15px"><label style="color:#888;display:block;margin-bottom:5px">Call
                    Sign</label><input class="toolbox-search" name="position" id="nodePosition"
                    placeholder="เช่น Nomad, Overlord, Bravo-1"></div>
            <div style="margin-bottom:15px" id="imageUrlRow"><label
                    style="color:#888;display:block;margin-bottom:5px">URL รูปภาพ
                    (Image URL)</label><input class="toolbox-search" name="image_url" id="nodeImageUrl"
                    placeholder="https://..."></div>
            <div style="display:flex;justify-content:space-between;align-items:center">
                <button type="button" class="ctrl-btn delete-custom-btn" id="deleteCustomBtn"
                    onclick="deleteCurrentCustomCard()"
                    style="display:none;background:rgba(220,53,69,0.3);border-color:#dc3545;">
                    <i class="fas fa-trash"></i> ลบ
                </button>
                <div style="margin-left:auto"><button type="submit" class="ctrl-btn"
                        style="width:auto;padding:0 20px;font-size:0.9rem">บันทึก (Save)</button></div>
            </div>
        </form>
    </div>
</div>
</div>
<div class="toast" id="toast"></div>


<!-- Custom Confirm Modal -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-question-circle"></i></div>
        <div class="confirm-title" id="confirmTitle">ยืนยันการดำเนินการ</div>
        <div class="confirm-message" id="confirmMessage">คุณแน่ใจหรือไม่?</div>
        <div class="confirm-buttons">
            <button class="confirm-btn cancel" id="confirmCancel">ยกเลิก</button>
            <button class="confirm-btn confirm" id="confirmOk">ยืนยัน</button>
        </div>
    </div>
</div>

<!-- Resume Modal -->
<div class="resume-overlay" id="resumeOverlay">
    <div class="resume-modal">
        <div class="resume-header">
            <button class="resume-close" onclick="closeResume()"><i class="fas fa-times"></i></button>
            <div class="resume-avatar"><img id="resumeAvatar" src="" alt="Avatar"></div>
            <div class="resume-title-section">
                <div class="resume-name" id="resumeName">-</div>
                <div class="resume-rank" id="resumeRank">-</div>
                <div class="resume-position" id="resumePosition">-</div>
            </div>
        </div>
        <div class="resume-body" id="resumeBody">
            <div class="resume-loading">
                <i class="fas fa-spinner"></i>
                <div>กำลังโหลด...</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Custom Dialogs
    let confirmResolve = null;

    function showConfirm(title, message, icon = 'question-circle') {
        return new Promise((resolve) => {
            confirmResolve = resolve;
            const t = document.getElementById('confirmTitle');
            const m = document.getElementById('confirmMessage');
            if (t) t.textContent = title;
            if (m) m.innerHTML = message; // Use innerHTML for formatting if needed

            const i = document.querySelector('.confirm-icon i');
            if (i) i.className = `fas fa-${icon}`;

            document.getElementById('confirmOverlay').classList.add('show');
        });
    }

    document.getElementById('confirmOk').onclick = () => {
        document.getElementById('confirmOverlay').classList.remove('show');
        if (confirmResolve) confirmResolve(true);
    };

    document.getElementById('confirmCancel').onclick = () => {
        document.getElementById('confirmOverlay').classList.remove('show');
        if (confirmResolve) confirmResolve(false);
    };

    document.getElementById('confirmOverlay').onclick = (e) => {
        if (e.target.id === 'confirmOverlay') {
            document.getElementById('confirmOverlay').classList.remove('show');
            if (confirmResolve) confirmResolve(false);
        }
    };

    // Resume Popup Functions
    function showResume(nodeId, cardType) {
        if (cardType === 'custom') {
            showToast('Custom cards ไม่มี Resume', 'warning');
            return;
        }
        // Use existing resume modal from resume_modal.php
        loadResumeData(nodeId);
    }

    function showToast(message, type = 'info') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast show ' + type;
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function logDebug(msg) {
        // const c = document.getElementById('debugConsole');
        // if(c) {
        //    c.innerHTML += `<div>${new Date().toLocaleTimeString()} ${msg}</div>`;
        //    c.scrollTop = c.scrollHeight;
        // }
        console.log(msg);
    }

    const API_URL = '/api/chain.php';

    // State
    let data = [];
    let toolbox = [];

    // Viewport
    let panX = 0, panY = 0;
    let scale = 1;
    let isPanning = false;
    let startPanX = 0, startPanY = 0;

    // Dragging Nodes
    let isDraggingNode = false;
    let draggedNodeId = null;
    let dragOffsetX = 0, dragOffsetY = 0;

    // Connecting Nodes
    let isConnecting = false;
    let connectionSourceId = null;
    let tempLine = null;

    // DOM
    const container = document.getElementById('chartContainer');
    const wrapper = document.getElementById('chartWrapper');
    const svgLayer = document.getElementById('connectionsLayer');
    const nodesLayer = document.getElementById('nodesLayer');

    document.addEventListener('DOMContentLoaded', () => {
        initCanvas();
        initConnectionLogic();
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
                // Correct for Container Position relative to Viewport
                const containerRect = container.getBoundingClientRect();
                const mouseX = (e.clientX - containerRect.left - panX) / scale;
                const mouseY = (e.clientY - containerRect.top - panY) / scale;

                const x = mouseX - dragOffsetX;
                const y = mouseY - dragOffsetY;

                updateNodePosition(draggedNodeId, x, y);
                // Also update lines immediately
                renderConnections();
            }
            if (isConnecting) {
                e.preventDefault();
                updateTempConnection(e);
            }
        });

        window.addEventListener('mouseup', async (e) => {
            if (isPanning) {
                isPanning = false;
                container.style.cursor = 'grab';
            }
            if (isDraggingNode && draggedNodeId) {
                isDraggingNode = false;
                // Save position to DB
                const node = data.find(n => n.id == draggedNodeId);
                if (node) {
                    await saveNodePosition(node.id, node.coc_x, node.coc_y);
                    renderNodes(); // Re-render to ensure clean state
                }
                draggedNodeId = null;
            }
            if (isConnecting) {
                endConnectionDrag(e);
            }
        });

        // Zoom
        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomStep = 0.1;
            const delta = -Math.sign(e.deltaY);
            let newScale = scale + (delta * zoomStep);
            newScale = Math.min(Math.max(0.1, newScale), 5);

            // Zoom towards mouse (simplification: center for now or improved logic)
            // Improved logic:
            // World point under mouse before zoom
            const wx = (e.clientX - panX) / scale;
            const wy = (e.clientY - panY) / scale;

            scale = newScale;

            // New Pan so that World point is still under mouse
            // Mouse = PanNew + scaleNew * wx
            // PanNew = Mouse - scaleNew * wx
            panX = e.clientX - (scale * wx);
            panY = e.clientY - (scale * wy);

            updateTransform();
        });

        // Drop from Toolbox
        container.addEventListener('dragover', e => e.preventDefault());
        container.addEventListener('drop', async (e) => {
            e.preventDefault();
            const id = e.dataTransfer.getData('text/plain');
            if (!id) return;

            // Add to canvas at drop location
            const wx = (e.clientX - panX) / scale;
            const wy = (e.clientY - panY) / scale;

            // Center the new node (assume width 220, height ~150?)
            const x = wx - 110;
            const y = wy - 75;

            // Add to chain (DB request)
            await saveNodePosition(id, x, y);
            await loadData(); // Reload
        });
    }

    function updateTransform() {
        wrapper.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
    }

    /* --- Data Handling --- */
    async function loadData() {
        const res = await fetch(`${API_URL}?action=tree`).then(r => r.json());
        if (res.success) {
            data = res.data; // Flat list
            renderNodes();
            renderConnections();
        }

        const res2 = await fetch(API_URL).then(r => r.json());
        if (res2.success) {
            // Filter out existing
            const existing = new Set(data.map(n => n.id));
            toolbox = res2.data.filter(u => !existing.has(u.id));
            renderToolbox();
        }
    }

    function renderNodes() {
        nodesLayer.innerHTML = '';
        data.forEach(node => {
            const el = document.createElement('div');
            const isCustom = node.card_type === 'custom' || parseInt(node.id) < 0;
            el.className = 'org-node' + (isCustom ? ' custom-card' : '');
            el.dataset.id = node.id;
            el.dataset.cardType = node.card_type || 'user';

            // If coordinates missing, assign default?
            let x = node.coc_x !== null ? parseInt(node.coc_x) : 0;
            let y = node.coc_y !== null ? parseInt(node.coc_y) : 0;
            node.coc_x = x; node.coc_y = y;

            el.style.left = x + 'px';
            el.style.top = y + 'px';

            el.innerHTML = `
                ${isCustom ? '<div class="custom-badge">Custom</div>' : ''}
                <div class="connector-handle handle-top" data-id="${node.id}" data-role="target"></div>
                <div class="connector-handle handle-bottom" data-id="${node.id}" data-role="source"></div>
                <div class="connector-handle handle-left" data-id="${node.id}" data-role="target"></div>
                <div class="connector-handle handle-right" data-id="${node.id}" data-role="source"></div>
                
                <div class="node-actions">
                    <button class="edit-btn" onclick="editNode(${node.id})"><i class="fas fa-pen"></i></button>
                    <button class="remove-btn" onclick="removeNode(${node.id})"><i class="fas fa-times"></i></button>
                </div>
                <div class="node-avatar clickable" onclick="event.stopPropagation(); showResume(${node.id}, '${node.card_type || 'user'}')"><img src="${(node.image_url && node.image_url.startsWith('assets/')) ? '/' + node.image_url : (node.image_url || '/assets/images/default_avatar.png')}" onerror="this.src='/assets/images/default_avatar.png'"></div>
                <div class="node-rank">${node.rank || ''}</div>
                <div class="node-name">${node.name}</div>
                <div class="node-position">${node.position || ''}</div>
            `;

            // Drag Start
            el.addEventListener('mousedown', (e) => {
                // If clicking handle, don't drag node
                if (e.target.classList.contains('connector-handle')) return;
                if (e.target.closest('button')) return; // Ignore button clicks

                isDraggingNode = true;
                draggedNodeId = node.id;

                // Offset inside the element
                const rect = el.getBoundingClientRect();
                dragOffsetX = (e.clientX - rect.left) / scale;
                dragOffsetY = (e.clientY - rect.top) / scale;

                e.preventDefault(); // Stop text selection
            });

            nodesLayer.appendChild(el);
        });

        // Re-init handles listeners if needed? 
        // We do it globally likely, but handles are new elements.
        // Actually best to delegate or attach here. Delegated in initConnectionLogic.
    }

    function updateNodePosition(id, x, y) {
        // Snap grid? Optional. Let's do 10px snap
        x = Math.round(x / 10) * 10;
        y = Math.round(y / 10) * 10;

        const node = data.find(n => n.id == id);
        if (node) {
            node.coc_x = x;
            node.coc_y = y;
            const el = nodesLayer.querySelector(`.org-node[data-id="${id}"]`);
            if (el) {
                el.style.left = x + 'px';
                el.style.top = y + 'px';
            }
        }
    }

    /* --- Connections (SVG) --- */
    function renderConnections() {
        svgLayer.innerHTML = ''; // Clear
        // Also remove temp line if not dragging
        if (isConnecting && tempLine) svgLayer.appendChild(tempLine);

        logDebug("Rendering Connections...");
        let count = 0;

        data.forEach(node => {
            if (node.parent_id) {
                const parent = data.find(n => n.id == node.parent_id);
                if (parent) {
                    logDebug(`Line: ${parent.id} -> ${node.id}`);
                    drawOrthogonalPath(parent, node);
                    count++;
                } else {
                    logDebug(`Orphan: ${node.id} has parent ${node.parent_id} (Not Found)`);
                }
            }
        });
        logDebug(`Total Lines: ${count}`);
    }

    function drawOrthogonalPath(parent, child) {
        // Get Elements
        const pEl = document.querySelector(`.org-node[data-id="${parent.id}"]`);
        const cEl = document.querySelector(`.org-node[data-id="${child.id}"]`);

        if (!pEl || !cEl) return;

        // Use stored coordinates (world coordinates)
        const pX = parent.coc_x;
        const pY = parent.coc_y;
        const cX = child.coc_x;
        const cY = child.coc_y;

        // Get actual dimensions
        const W = 240;
        const pH = pEl.offsetHeight || 180;
        const cH = cEl.offsetHeight || 180;

        // Calculate centers
        const pCenterX = pX + W / 2;
        const pCenterY = pY + pH / 2;
        const cCenterX = cX + W / 2;
        const cCenterY = cY + cH / 2;

        // Hierarchical Routing: 
        // For org charts, we always go Parent Bottom -> Child Top
        // UNLESS the child is to the side (then use Left/Right)

        let startX, startY, endX, endY;
        let routeType; // 'vertical' or 'horizontal'

        // Determine layout
        const dx = cCenterX - pCenterX;
        const dy = cCenterY - pCenterY;

        if (cY > pY + pH - 20) {
            // Child is BELOW parent -> Bottom to Top (standard hierarchy)
            routeType = 'vertical';
            startX = pCenterX;
            startY = pY + pH + 6; // +6px for handle offset
            endX = cCenterX;
            endY = cY - 6; // -6px for handle offset
        } else if (cY + cH < pY + 20) {
            // Child is ABOVE parent -> Top to Bottom (reversed)
            routeType = 'vertical';
            startX = pCenterX;
            startY = pY - 6;
            endX = cCenterX;
            endY = cY + cH + 6;
        } else {
            // Side by side
            routeType = 'horizontal';
            if (dx > 0) {
                // Child is to the RIGHT
                startX = pX + W + 6;
                startY = pCenterY;
                endX = cX - 6;
                endY = cCenterY;
            } else {
                // Child is to the LEFT
                startX = pX - 6;
                startY = pCenterY;
                endX = cX + W + 6;
                endY = cCenterY;
            }
        }

        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        path.setAttribute("class", "connector-path");
        path.setAttribute("data-link", `${parent.id}-${child.id}`);

        let d = '';

        if (routeType === 'vertical') {
            // Vertical routing: go down, across, down
            const midY = (startY + endY) / 2;
            if (Math.abs(startX - endX) < 3) {
                // Perfectly aligned - straight line
                d = `M ${startX} ${startY} L ${endX} ${endY}`;
            } else {
                // Step pattern: down to midY, across, down to end
                d = `M ${startX} ${startY} L ${startX} ${midY} L ${endX} ${midY} L ${endX} ${endY}`;
            }
        } else {
            // Horizontal routing: across, down/up, across
            const midX = (startX + endX) / 2;
            if (Math.abs(startY - endY) < 3) {
                // Perfectly aligned - straight line
                d = `M ${startX} ${startY} L ${endX} ${endY}`;
            } else {
                // Step pattern: across to midX, vertical, across to end
                d = `M ${startX} ${startY} L ${midX} ${startY} L ${midX} ${endY} L ${endX} ${endY}`;
            }
        }

        path.setAttribute("d", d);

        // Remove Actions
        const removeFn = async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const confirmed = await showConfirm(
                'ยุติการเชื่อมโยง (Unlink)',
                `ต้องการลบการเชื่อมโยงระหว่าง ${parent.name} และ ${child.name} หรือไม่?`,
                'unlink'
            );

            if (confirmed) {
                await setParent(child.id, null);
                showToast('ลบเส้นเชื่อมโยงเรียบร้อย', 'success');
            }
            return false;
        };

        path.onclick = removeFn;
        path.oncontextmenu = removeFn;

        // Tooltip
        const title = document.createElementNS("http://www.w3.org/2000/svg", "title");
        title.textContent = `Link: ${parent.name} -> ${child.name}\n(Click/RightClick to Remove)`;
        path.appendChild(title);

        svgLayer.appendChild(path);
    }

    /* --- Interactive Linking --- */
    function initConnectionLogic() {
        // Use Delegation for handles
        container.addEventListener('mousedown', (e) => {
            if (e.target.classList.contains('connector-handle')) {
                const handle = e.target;
                // Allow dragging from ANY handle
                e.preventDefault();
                e.stopPropagation(); // Don't trigger Pan
                startConnectionDrag(handle);
            }
        });
    }

    function startConnectionDrag(handle) {
        logDebug("Start Drag CID: " + handle.dataset.id);
        isConnecting = true;
        connectionSourceId = handle.dataset.id;

        // Create Temp Line
        tempLine = document.createElementNS("http://www.w3.org/2000/svg", "path");
        tempLine.setAttribute("class", "temp-path");
        svgLayer.appendChild(tempLine);

        // Initial point - Use Handle Center, not Node Center!
        const rect = handle.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();

        // Convert to World
        const startX = (rect.left + rect.width / 2 - containerRect.left - panX) / scale;
        const startY = (rect.top + rect.height / 2 - containerRect.top - panY) / scale;

        // Store start pos for update
        tempLine.dataset.startX = startX;
        tempLine.dataset.startY = startY;
    }

    function updateTempConnection(e) {
        if (!tempLine) return;

        const startX = parseFloat(tempLine.dataset.startX);
        const startY = parseFloat(tempLine.dataset.startY);

        // Current Mouse World Pos
        const containerRect = container.getBoundingClientRect();
        const mouseX = (e.clientX - containerRect.left - panX) / scale;
        const mouseY = (e.clientY - containerRect.top - panY) / scale;

        const d = `M ${startX} ${startY} L ${mouseX} ${mouseY}`;
        tempLine.setAttribute("d", d);
    }

    async function endConnectionDrag(e) {
        isConnecting = false;
        if (tempLine) {
            tempLine.remove();
            tempLine = null;
        }

        // Remove 'dragging' class or style

        // Check if dropped on a node
        // Use elementFromPoint. 
        // Note: handles are z-index 999. Nodes are lower.
        // We might hit a handle of the target node.
        const targetEl = document.elementFromPoint(e.clientX, e.clientY);

        let nodeEl = null;
        if (targetEl) {
            logDebug("Drop Target: " + targetEl.tagName + "." + targetEl.className);
            if (targetEl.classList.contains('connector-handle')) {
                // Dropped on a handle -> Get parent node
                nodeEl = targetEl.closest('.org-node');
            } else {
                nodeEl = targetEl.closest('.org-node');
            }
        } else {
            logDebug("Drop Null");
        }

        if (nodeEl && nodeEl.dataset.id) {
            logDebug("Found Node ID: " + nodeEl.dataset.id);
            const targetId = nodeEl.dataset.id;
            if (targetId != connectionSourceId) {
                // Connect
                logDebug("Linking " + connectionSourceId + " -> " + targetId);
                await setParent(targetId, connectionSourceId);
            }
        }

        connectionSourceId = null;
    }

    async function setParent(childId, parentId) {
        // Check circles? Client side check or DB?
        // Let's just send API
        await fetch(`${API_URL}?action=move`, {
            method: 'POST',
            body: JSON.stringify({ id: childId, parent_id: parentId }) // handled by API
        });
        loadData();
    }

    /* --- Logic --- */
    async function saveNodePosition(id, x, y) {
        await fetch(`${API_URL}?action=move`, {
            method: 'POST',
            body: JSON.stringify({ id: id, x: x, y: y }) // Parent doesn't change on drag
        });
    }

    // Toolbox Drag
    function renderToolbox(listData = toolbox) {
        const list = document.getElementById('userList');
        list.innerHTML = listData.map(u => `
            <div class="user-card" draggable="true" ondragstart="toolDragStart(event, ${u.id})">
                <img src="${(u.image_url && u.image_url.startsWith('assets/')) ? '/' + u.image_url : (u.image_url || '/assets/images/default_avatar.png')}" onerror="this.src='/assets/images/default_avatar.png'">
                <span>${u.name}</span>
            </div>
        `).join('');
    }
    window.toolDragStart = (e, id) => {
        e.dataTransfer.setData('text/plain', id);
    };

    /* --- Utils --- */
    window.zoomIn = () => { scale += 0.2; updateTransform(); };
    window.zoomOut = () => { scale = Math.max(0.1, scale - 0.2); updateTransform(); };
    window.resetView = () => { scale = 1; panX = 0; panY = 0; updateTransform(); };

    window.autoLayout = () => {
        // TBD: Simple Tree Layout Algo if mess?
        // Currently manual only as requested "Independent".
        alert("Auto-layout Logic to be implemented if needed.");
    };

    function filterUsers() {
        const q = document.getElementById('userSearch').value.toLowerCase();
        const filtered = toolbox.filter(u => u.name.toLowerCase().includes(q));
        renderToolbox(filtered);
    }

    window.removeNode = async (id) => {
        const isCustom = parseInt(id) < 0;

        if (isCustom) {
            const confirmed = await showConfirm(
                'ลบ Custom Card',
                'ต้องการลบ Custom Card นี้ถาวรหรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้',
                'trash-alt'
            );
            if (!confirmed) return;

            await fetch(`${API_URL}?action=delete_custom&id=${id}`, { method: 'POST' });
            showToast('ลบ Custom Card เรียบร้อย', 'success');
        } else {
            const confirmed = await showConfirm(
                'นำออกจากผัง (Remove)',
                'ต้องการนำบุคลากรนี้กลับไปที่คลังข้อมูล (Toolbox) หรือไม่?',
                'user-minus'
            );
            if (!confirmed) return;

            await fetch(`${API_URL}?action=removeFromChain&id=${id}`, { method: 'POST' });
            showToast('นำข้อมูลกลับไปยังคลังเรียบร้อย', 'success');
        }
        loadData();
    };

    // Open modal for creating new custom card
    window.openCreateCustomModal = () => {
        document.getElementById('modalTitle').textContent = 'สร้าง Custom Card (Create Custom)';
        document.getElementById('nodeId').value = '';
        document.getElementById('nodeCardType').value = 'custom';
        document.getElementById('nodeName').value = '';
        document.getElementById('nodeRank').value = '';
        document.getElementById('nodePosition').value = '';
        document.getElementById('nodeImageUrl').value = '';
        document.getElementById('imageUrlRow').style.display = 'block';
        document.getElementById('deleteCustomBtn').style.display = 'none';
        const modal = document.getElementById('nodeModal');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
    };

    // Open modal for editing existing node
    window.editNode = (id) => {
        const node = data.find(n => n.id == id);
        if (!node) return;

        const isCustom = node.card_type === 'custom' || parseInt(id) < 0;

        document.getElementById('modalTitle').textContent = isCustom
            ? 'แก้ไข Custom Card (Edit Custom)'
            : 'แก้ไขข้อมูล (Edit Details)';
        document.getElementById('nodeId').value = node.id;
        document.getElementById('nodeCardType').value = node.card_type || 'user';
        document.getElementById('nodeName').value = node.name || '';
        document.getElementById('nodeRank').value = node.rank || '';
        document.getElementById('nodePosition').value = node.position || '';
        document.getElementById('nodeImageUrl').value = node.image_url || '';

        // Show image URL field for all, but mainly for custom cards
        document.getElementById('imageUrlRow').style.display = 'block';

        // Show delete button only for custom cards
        document.getElementById('deleteCustomBtn').style.display = isCustom ? 'block' : 'none';

        const modal = document.getElementById('nodeModal');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
    };

    // Delete current custom card from modal
    window.deleteCurrentCustomCard = async () => {
        const id = document.getElementById('nodeId').value;
        if (!id || parseInt(id) >= 0) return;

        const confirmed = await showConfirm(
            'ลบ Custom Card',
            'ต้องการลบ Custom Card นี้ถาวรหรือไม่?',
            'trash-alt'
        );
        if (!confirmed) return;

        await fetch(`${API_URL}?action=delete_custom&id=${id}`, { method: 'POST' });
        document.getElementById('nodeModal').style.display = 'none';
        document.getElementById('nodeModal').style.opacity = '0';
        showToast('ลบ Custom Card เรียบร้อย', 'success');
        loadData();
    };

    document.getElementById('nodeModal').onclick = function (e) {
        if (e.target == this) {
            this.style.display = 'none';
            this.style.opacity = '0';
        }
    };

    document.getElementById('nodeForm').onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const formData = Object.fromEntries(fd);
        const id = formData.id;
        const cardType = formData.card_type;

        if (!id || id === '') {
            // Create new custom card
            const res = await fetch(`${API_URL}?action=create_custom`, {
                method: 'POST',
                body: JSON.stringify({
                    name: formData.name,
                    rank: formData.rank,
                    position: formData.position,
                    image_url: formData.image_url,
                    x: 100,
                    y: 100
                })
            }).then(r => r.json());

            if (res.success) {
                showToast('สร้าง Custom Card เรียบร้อย (ID: ' + res.id + ')', 'success');
            }
        } else {
            // Update existing
            await fetch(API_URL, {
                method: 'POST',
                body: JSON.stringify(formData)
            });
            showToast('บันทึกข้อมูลเรียบร้อย', 'success');
        }

        document.getElementById('nodeModal').style.display = 'none';
        loadData();
    };
</script>

<?php include ROOT_PATH . '/includes/resume_modal.php'; ?>