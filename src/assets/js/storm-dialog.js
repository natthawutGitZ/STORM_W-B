// =========================================================
// STORM DIALOG SYSTEM — Custom Themed Confirm & Alert
// Replaces native browser confirm() and alert() with
// military CRT terminal themed modals.
// =========================================================

(function () {
    'use strict';

    // ---- Create DOM structure once ----
    const overlay = document.createElement('div');
    overlay.id = 'stormDialogOverlay';
    overlay.className = 'storm-dialog-overlay';
    overlay.innerHTML = `
        <div class="storm-dialog" id="stormDialog">
            <div class="storm-dialog-scanline"></div>
            <div class="storm-dialog-header" id="stormDialogHeader">
                <span class="storm-dialog-header-icon" id="stormDialogIcon">
                    <i class="fas fa-exclamation-triangle"></i>
                </span>
                <span class="storm-dialog-header-title" id="stormDialogTitle">CONFIRM</span>
                <div class="storm-dialog-header-line"></div>
            </div>
            <div class="storm-dialog-body" id="stormDialogBody">
                <p id="stormDialogMessage"></p>
            </div>
            <div class="storm-dialog-footer" id="stormDialogFooter">
                <button class="storm-dialog-btn cancel" id="stormDialogCancel">
                    <i class="fas fa-times"></i> CANCEL
                </button>
                <button class="storm-dialog-btn confirm" id="stormDialogConfirm">
                    <i class="fas fa-check"></i> CONFIRM
                </button>
            </div>
        </div>
    `;

    // Append to body when DOM ready
    function mountDialog() {
        if (!document.getElementById('stormDialogOverlay')) {
            document.body.appendChild(overlay);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountDialog);
    } else {
        mountDialog();
    }

    // ---- Dialog types ----
    const DIALOG_TYPES = {
        confirm: {
            icon: 'fa-shield-halved',
            headerClass: 'confirm',
            title: 'CONFIRM ACTION',
            showCancel: true
        },
        warning: {
            icon: 'fa-triangle-exclamation',
            headerClass: 'warning',
            title: '⚠ WARNING',
            showCancel: true
        },
        danger: {
            icon: 'fa-skull-crossbones',
            headerClass: 'danger',
            title: '⚠ DANGER',
            showCancel: true
        },
        alert: {
            icon: 'fa-circle-info',
            headerClass: 'alert',
            title: 'NOTICE',
            showCancel: false
        },
        error: {
            icon: 'fa-circle-exclamation',
            headerClass: 'error',
            title: 'ERROR',
            showCancel: false
        },
        success: {
            icon: 'fa-circle-check',
            headerClass: 'success',
            title: 'SUCCESS',
            showCancel: false
        }
    };

    // Detect dialog type from message content
    function detectType(message) {
        const msg = (message || '').toUpperCase();
        if (msg.includes('DELETE') || msg.includes('DELETION') || msg.includes('CLEAR ALL') || msg.includes('RESET')) return 'danger';
        if (msg.includes('CONFIRM') || msg.includes('WITHDRAWAL') || msg.includes('WITHDRAW') || msg.includes('REMOVE') || msg.includes('REVOKE')) return 'warning';
        if (msg.includes('FAILED') || msg.includes('ERROR') || msg.includes('INVALID')) return 'error';
        if (msg.includes('SUCCESS') || msg.includes('SAVED') || msg.includes('COMPLETED')) return 'success';
        return 'confirm';
    }

    // Strip emoji/symbols from start of message for cleaner display
    function cleanMessage(msg) {
        return (msg || '').replace(/^[⚠️🔴🟡🟢❌✅⛔🚫]+\s*/g, '').trim();
    }

    // ---- Core show dialog function ----
    let activeResolve = null;

    function showDialog(message, type, options = {}) {
        return new Promise(function (resolve) {
            mountDialog(); // ensure mounted

            activeResolve = resolve;
            const config = DIALOG_TYPES[type] || DIALOG_TYPES.confirm;
            const dialogEl = document.getElementById('stormDialog');
            const overlayEl = document.getElementById('stormDialogOverlay');
            const iconEl = document.getElementById('stormDialogIcon');
            const titleEl = document.getElementById('stormDialogTitle');
            const msgEl = document.getElementById('stormDialogMessage');
            const cancelBtn = document.getElementById('stormDialogCancel');
            const confirmBtn = document.getElementById('stormDialogConfirm');
            const headerEl = document.getElementById('stormDialogHeader');

            // Set content
            iconEl.innerHTML = '<i class="fas ' + config.icon + '"></i>';
            titleEl.textContent = options.title || config.title;
            msgEl.textContent = cleanMessage(message);

            // Set type class
            dialogEl.className = 'storm-dialog type-' + (config.headerClass || 'confirm');
            headerEl.className = 'storm-dialog-header type-' + (config.headerClass || 'confirm');

            // Show/hide cancel button
            cancelBtn.style.display = config.showCancel ? '' : 'none';

            // Update confirm button text
            if (!config.showCancel) {
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> OK';
            } else if (type === 'danger') {
                confirmBtn.innerHTML = '<i class="fas fa-trash"></i> DELETE';
                confirmBtn.className = 'storm-dialog-btn confirm danger';
            } else {
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> CONFIRM';
                confirmBtn.className = 'storm-dialog-btn confirm';
            }

            // Show overlay
            overlayEl.classList.add('show');

            // Focus confirm button
            setTimeout(function () { confirmBtn.focus(); }, 100);

            // Handlers
            function cleanup() {
                overlayEl.classList.remove('show');
                confirmBtn.removeEventListener('click', onConfirm);
                cancelBtn.removeEventListener('click', onCancel);
                overlayEl.removeEventListener('click', onOverlayClick);
                document.removeEventListener('keydown', onKeydown);
                activeResolve = null;
            }

            function onConfirm() {
                cleanup();
                resolve(true);
            }

            function onCancel() {
                cleanup();
                resolve(false);
            }

            function onOverlayClick(e) {
                if (e.target === overlayEl) {
                    if (config.showCancel) {
                        onCancel();
                    } else {
                        onConfirm();
                    }
                }
            }

            function onKeydown(e) {
                if (e.key === 'Escape') {
                    if (config.showCancel) {
                        onCancel();
                    } else {
                        onConfirm();
                    }
                } else if (e.key === 'Enter') {
                    onConfirm();
                }
            }

            confirmBtn.addEventListener('click', onConfirm);
            cancelBtn.addEventListener('click', onCancel);
            overlayEl.addEventListener('click', onOverlayClick);
            document.addEventListener('keydown', onKeydown);
        });
    }

    // ---- Public API ----
    window.stormConfirm = function (message, type) {
        const autoType = type || detectType(message);
        return showDialog(message, autoType);
    };

    window.stormAlert = function (message, type) {
        const autoType = type || (function () {
            const msg = (message || '').toUpperCase();
            if (msg.includes('ERROR') || msg.includes('FAIL') || msg.includes('DENIED') || msg.includes('EXPIRED')) return 'error';
            if (msg.includes('SUCCESS') || msg.includes('SAVED')) return 'success';
            return 'alert';
        })();
        return showDialog(message, autoType);
    };

    // ---- Override native dialogs (opt-in) ----
    // These can be called to replace native confirm/alert globally
    window.stormDialog = {
        show: showDialog,
        confirm: window.stormConfirm,
        alert: window.stormAlert,
        TYPES: DIALOG_TYPES
    };

})();
