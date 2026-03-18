<!-- Global Modals -->

<!-- Confirmation Modal -->
<div id="globalConfirmModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
    <div style="background: linear-gradient(135deg, rgba(30, 30, 40, 0.95) 0%, rgba(20, 20, 30, 0.98) 100%); width: 90%; max-width: 400px; padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.7); transform: scale(0.9); opacity: 0; transition: all 0.3s ease;"
        id="confirmModalContent">
        <div style="font-size: 3rem; margin-bottom: 20px; color: var(--accent-color);">
            <i class="fas fa-question-circle"></i>
        </div>
        <h3 id="confirmTitle" style="color: #fff; margin-bottom: 10px; font-size: 1.5rem;">Are you sure?</h3>
        <p id="confirmMessage" style="color: #aaa; margin-bottom: 25px; line-height: 1.5;">This action cannot be undone.
        </p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button id="confirmCancelBtn"
                style="background: #333; color: #fff; border: 1px solid #555; padding: 10px 25px; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 0.9rem; transition: all 0.2s;">
                Cancel
            </button>
            <button id="confirmOkBtn"
                style="background: var(--accent-color); color: #000; border: none; padding: 10px 25px; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 0.9rem; transition: all 0.2s;">
                Confirm
            </button>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div id="globalNotificationModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
    <div style="background: linear-gradient(135deg, rgba(30, 30, 40, 0.95) 0%, rgba(20, 20, 30, 0.98) 100%); width: 90%; max-width: 400px; padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.7); transform: scale(0.9); opacity: 0; transition: all 0.3s ease;"
        id="notificationModalContent">
        <div id="notificationIcon" style="font-size: 3rem; margin-bottom: 20px;"></div>
        <h3 id="notificationTitle" style="color: #fff; margin-bottom: 10px; font-size: 1.5rem;"></h3>
        <p id="notificationMessage" style="color: #aaa; margin-bottom: 25px; line-height: 1.5;"></p>
        <button onclick="closeGlobalNotification()"
            style="background: var(--accent-color); color: #000; border: none; padding: 10px 30px; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 1rem; transition: transform 0.2s;">
            OK
        </button>
    </div>
</div>

<script>
    // Confirmation Modal Logic
    let confirmCallback = null;
    const confirmModal = document.getElementById('globalConfirmModal');
    const confirmModalContent = document.getElementById('confirmModalContent');
    const confirmTitle = document.getElementById('confirmTitle');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmOkBtn = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');

    function showConfirm(title, message, callback) {
        confirmTitle.textContent = title;
        confirmMessage.textContent = message;
        confirmCallback = callback;

        confirmModal.style.display = 'flex';
        // Trigger reflow
        void confirmModal.offsetWidth;
        confirmModalContent.style.transform = 'scale(1)';
        confirmModalContent.style.opacity = '1';
    }

    function closeConfirm() {
        confirmModalContent.style.transform = 'scale(0.9)';
        confirmModalContent.style.opacity = '0';
        setTimeout(() => {
            confirmModal.style.display = 'none';
            confirmCallback = null;
        }, 300);
    }

    confirmOkBtn.addEventListener('click', () => {
        if (confirmCallback) confirmCallback();
        closeConfirm();
    });

    confirmCancelBtn.addEventListener('click', closeConfirm);

    // Notification Modal Logic
    let notificationCallback = null;
    const notificationModal = document.getElementById('globalNotificationModal');
    const notificationModalContent = document.getElementById('notificationModalContent');
    const notificationIcon = document.getElementById('notificationIcon');
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');

    function showNotification(type, title, message, callback = null) {
        notificationModal.style.display = 'flex';
        notificationCallback = callback;

        // Trigger reflow
        void notificationModal.offsetWidth;
        notificationModalContent.style.transform = 'scale(1)';
        notificationModalContent.style.opacity = '1';

        if (type === 'success') {
            notificationIcon.innerHTML = '<i class="fas fa-check-circle" style="color: #4caf50;"></i>';
        } else if (type === 'error') {
            notificationIcon.innerHTML = '<i class="fas fa-times-circle" style="color: #f44336;"></i>';
        } else {
            notificationIcon.innerHTML = '<i class="fas fa-info-circle" style="color: #2196f3;"></i>';
        }

        notificationTitle.textContent = title;
        notificationMessage.textContent = message;
    }

    function closeGlobalNotification() {
        notificationModalContent.style.transform = 'scale(0.9)';
        notificationModalContent.style.opacity = '0';
        setTimeout(() => {
            notificationModal.style.display = 'none';
            if (notificationCallback) {
                notificationCallback();
                notificationCallback = null;
            }
        }, 300);
    }

    // Close modals on outside click
    window.onclick = function (event) {
        if (event.target == confirmModal) {
            closeConfirm();
        }
        if (event.target == notificationModal) {
            closeGlobalNotification();
        }
    }

    function confirmAction(event, title, message) {
        event.preventDefault();
        const target = event.currentTarget;

        // If it's a form submission via onsubmit
        if (target.tagName === 'FORM') {
            showConfirm(title, message, () => {
                target.submit();
            });
            return false;
        }

        // If it's a link or button
        showConfirm(title, message, () => {
            if (target.tagName === 'A') {
                window.location.href = target.href;
            } else if (target.tagName === 'BUTTON' && target.type === 'submit') {
                // If button is inside a form, submit the form
                const form = target.closest('form');
                if (form) form.submit();
            }
        });
        return false;
    }
</script>