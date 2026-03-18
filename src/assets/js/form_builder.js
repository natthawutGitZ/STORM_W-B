// assets/js/form_builder.js

// utility
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Enhanced Modern Form Builder
document.addEventListener('DOMContentLoaded', () => {
    // Initial Load from Global Variable injected by PHP
    if (typeof questions !== 'undefined' && questions.length > 0) {
        questions.forEach(q => renderQuestion(q));
    } else {
        // If empty or not set, maybe fetch? 
        // But for now we rely on PHP injection or empty state.
        if (typeof questions !== 'undefined' && questions.length === 0) {
            document.getElementById('emptyState').style.display = 'block';
        }
    }

    const saveMeta = debounce(async () => {
        const title = document.getElementById('formTitleInput').value;
        const desc = document.getElementById('formDescInput').value;
        const webhookStaffEl = document.getElementById('formWebhookStaff');
        const webhookWelcomeEl = document.getElementById('formWebhookWelcome');
        const webhookPublicEl = document.getElementById('formWebhookPublic');

        const webhookStaff = webhookStaffEl ? webhookStaffEl.value : '';
        const webhookWelcome = webhookWelcomeEl ? webhookWelcomeEl.value : '';
        const webhookPublic = webhookPublicEl ? webhookPublicEl.value : '';

        const giveRoleEl = document.getElementById('formGiveRoleId');
        const giveRoleId = giveRoleEl ? giveRoleEl.value : '';

        const formData = new FormData();
        formData.append('action', 'update_form_meta');
        formData.append('id', window.formId);
        formData.append('title', title);
        formData.append('description', desc);
        formData.append('webhook_staff', webhookStaff);
        formData.append('webhook_welcome', webhookWelcome);
        formData.append('webhook_public', webhookPublic);
        formData.append('give_role_id', giveRoleId);

        await fetch('form_actions.php', {
            method: 'POST',
            body: formData
        });
        console.log('Meta saved');
    }, 1000);

    document.getElementById('formTitleInput').addEventListener('input', saveMeta);
    document.getElementById('formDescInput').addEventListener('input', saveMeta);

    // Only add listeners if webhook fields exist (Main Form only)
    const webhookStaffEl = document.getElementById('formWebhookStaff');
    const webhookWelcomeEl = document.getElementById('formWebhookWelcome');
    const webhookPublicEl = document.getElementById('formWebhookPublic');

    if (webhookStaffEl) webhookStaffEl.addEventListener('input', saveMeta);
    if (webhookWelcomeEl) webhookWelcomeEl.addEventListener('input', saveMeta);
    if (webhookPublicEl) webhookPublicEl.addEventListener('input', saveMeta);

    const giveRoleEl = document.getElementById('formGiveRoleId');
    if (giveRoleEl) giveRoleEl.addEventListener('change', saveMeta);

    // Banner Preview Logic
    document.getElementById('formBannerInput').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('bannerPreview').src = e.target.result;
                document.getElementById('bannerPreviewContainer').style.display = 'block';
                document.getElementById('bannerPlaceholder').style.display = 'none';
            }
            reader.readAsDataURL(file);
        }
    });

    // Initialize Container Drag
    const container = document.getElementById('questionsContainer');
    if (container) {
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            if (afterElement == null) {
                container.appendChild(draggedItem);
            } else {
                container.insertBefore(draggedItem, afterElement);
            }
        });
    }

    // Save Form Button Logic
    document.getElementById('saveFormBtn').addEventListener('click', async () => {
        const btn = document.getElementById('saveFormBtn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
        btn.disabled = true;

        // Force save meta
        const title = document.getElementById('formTitleInput').value;
        const desc = document.getElementById('formDescInput').value;
        const webhookStaffEl = document.getElementById('formWebhookStaff');
        const webhookWelcomeEl = document.getElementById('formWebhookWelcome');
        const webhookPublicEl = document.getElementById('formWebhookPublic');

        const webhookStaff = webhookStaffEl ? webhookStaffEl.value : '';
        const webhookWelcome = webhookWelcomeEl ? webhookWelcomeEl.value : '';
        const webhookPublic = webhookPublicEl ? webhookPublicEl.value : '';
        const bannerInput = document.getElementById('formBannerInput');

        const giveRoleEl = document.getElementById('formGiveRoleId');
        const giveRoleId = giveRoleEl ? giveRoleEl.value : '';

        const formData = new FormData();
        formData.append('action', 'update_form_meta');
        formData.append('id', window.formId);
        formData.append('title', title);
        formData.append('description', desc);
        formData.append('webhook_staff', webhookStaff);
        formData.append('webhook_welcome', webhookWelcome);
        formData.append('webhook_public', webhookPublic);
        formData.append('give_role_id', giveRoleId);

        if (bannerInput.files.length > 0) {
            formData.append('banner_image', bannerInput.files[0]);
        }

        try {
            await fetch('form_actions.php', {
                method: 'POST',
                body: formData
            });

            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: 'Form saved successfully.',
                timer: 1500,
                showConfirmButton: false,
                background: '#151515',
                color: '#fff'
            });
        } catch (e) {
            Swal.fire('Error', 'Failed to save form', 'error');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
});

// Exposed Global Function for Sidebar
// Exposed Global Function for Sidebar
window.addQuestion = async function (type) {
    console.log('addQuestion called with type:', type);

    if (!window.formId) {
        console.error('formId is missing');
        Swal.fire('Error', 'Form ID is missing. Please reload the page.', 'error');
        return;
    }

    try {
        // Hide empty state
        const emptyState = document.getElementById('emptyState');
        if (emptyState) emptyState.style.display = 'none';

        // Create question in DB
        const res = await fetch('form_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add_question&form_id=${window.formId}&type=${type}`
        });

        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid JSON');
        }

        if (data.success) {
            console.log('Question added, rendering...');
            // Render
            const newQ = {
                id: data.id,
                question_text: '',
                question_type: type,
                is_required: 0,
                options: '["Option 1", "Option 2"]', // Default options
                allow_other: 0
            };
            renderQuestion(newQ, true);

            // Scroll to bottom
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        } else {
            console.error('Server error:', data.message);
            Swal.fire('Error', data.message || 'Could not add question', 'error');
        }
    } catch (err) {
        console.error('addQuestion error:', err);
        Swal.fire('Error', 'An unexpected error occurred: ' + err.message, 'error');
    }
};

// Removed loadFormData() as we use PHP injection now.

function renderQuestion(q, animate = false) {
    const tmpl = document.getElementById('questionTemplate');
    const clone = tmpl.content.cloneNode(true);
    const item = clone.querySelector('.question-item');

    item.dataset.id = q.id;
    if (animate) item.classList.add('animate-slide-in');

    const titleInput = item.querySelector('.question-text-input');
    const descInput = item.querySelector('.section-desc-input'); // Select from template

    const typeSelect = item.querySelector('.question-type-select');
    const requiredCheck = item.querySelector('.required-check');
    const deleteBtn = item.querySelector('.delete');
    const optionArea = item.querySelector('.option-area');
    const addOptionBtn = item.querySelector('.add-option-btn');
    const optionList = item.querySelector('.option-list');

    titleInput.value = q.question_text;
    descInput.value = q.description || ''; // Load description
    typeSelect.value = q.question_type;
    requiredCheck.checked = q.is_required == 1;

    // Parse options if string (from DB)
    let options = typeof q.options === 'string' ? JSON.parse(q.options || '[]') : (q.options || []);

    // Show/Hide Option Area
    const showOptions = ['radio', 'checkbox', 'select'].includes(q.question_type);
    optionArea.style.display = showOptions ? 'block' : 'none';

    // Hide the static Add Option button from template, as we render it dynamically inside optionList
    const staticAddBtn = optionArea.querySelector('.add-option-btn');
    if (staticAddBtn && staticAddBtn.parentNode === optionArea) {
        staticAddBtn.style.display = 'none';
    }

    // Render Options
    // Render Options

    // Render Options
    function renderOptions() {
        optionList.innerHTML = '';

        // Render Standard Options
        options.forEach((opt, idx) => {
            const div = document.createElement('div');
            div.className = 'option-item';
            div.innerHTML = `
                <i class="far ${q.question_type === 'checkbox' ? 'fa-square' : 'fa-circle'} option-marker"></i>
                <input type="text" class="option-input" value="${opt}" placeholder="Option ${idx + 1}">
                <i class="fas fa-times text-muted cursor-pointer remove-opt" style="cursor:pointer;"></i>
            `;

            const input = div.querySelector('.option-input');

            // Update option text
            input.addEventListener('input', (e) => {
                options[idx] = e.target.value;
                saveQuestion(q.id, item);
            });

            // Enter key to add new option
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    // Add new option after this one
                    options.splice(idx + 1, 0, `Option ${options.length + 1}`);
                    renderOptions(); // Re-render
                    saveQuestion(q.id, item);

                    // Focus the newly created option (next index)
                    setTimeout(() => {
                        const inputs = optionList.querySelectorAll('.option-input');
                        if (inputs[idx + 1]) {
                            inputs[idx + 1].focus();
                            inputs[idx + 1].select();
                        }
                    }, 0);
                }
            });

            // Remove option
            div.querySelector('.remove-opt').addEventListener('click', () => {
                options.splice(idx, 1);
                renderOptions();
                saveQuestion(q.id, item);
            });

            optionList.appendChild(div);
        });

        // Render "Other" Option if enabled
        if (q.allow_other == 1) { // Weak comparison to handle string/int "1" or 1
            const div = document.createElement('div');
            div.className = 'option-item other-option-item';
            div.innerHTML = `
                <i class="far ${q.question_type === 'checkbox' ? 'fa-square' : 'fa-circle'} option-marker text-muted"></i>
                <span style="flex:1; padding:10px 12px; font-style:italic; color:var(--text-muted); border:1px dashed rgba(255,255,255,0.1); border-radius:4px;">Other...</span>
                <i class="fas fa-times text-muted cursor-pointer remove-other" style="cursor:pointer;"></i>
            `;

            div.querySelector('.remove-other').addEventListener('click', () => {
                q.allow_other = 0;
                renderOptions();
                saveQuestion(q.id, item);
            });

            optionList.appendChild(div);
        }

        // Add "Add Option" / "Add Other" buttons
        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'd-flex align-items-center gap-3 mt-2';

        const addOptBtn = document.createElement('div');
        addOptBtn.className = 'add-option-btn';
        addOptBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Add Option';
        addOptBtn.onclick = () => {
            options.push(`Option ${options.length + 1}`);
            renderOptions();
            saveQuestion(q.id, item);
        };

        controlsDiv.appendChild(addOptBtn);

        if (q.allow_other != 1) {
            const addOtherBtn = document.createElement('div');
            addOtherBtn.className = 'add-option-btn text-muted';
            addOtherBtn.innerHTML = 'or <span style="text-decoration:underline; cursor:pointer;" class="ms-1">add "Other"</span>';
            addOtherBtn.querySelector('span').onclick = () => {
                q.allow_other = 1;
                renderOptions();
                saveQuestion(q.id, item);
            };
            controlsDiv.appendChild(addOtherBtn);
        }

        optionList.appendChild(controlsDiv);
    }
    renderOptions();

    // SECTION HANDLING
    if (q.question_type === 'section') {
        item.classList.add('section-item');
        item.style.borderTop = '4px solid var(--accent)';
        item.style.background = 'rgba(197, 160, 89, 0.05)';
        titleInput.placeholder = "Section Title (e.g. Personal Information)";
        titleInput.style.fontSize = '1.5rem';
        titleInput.style.fontWeight = 'bold';

        // Show description input for sections
        const dInput = item.querySelector('.section-desc-input');
        if (dInput) dInput.style.display = 'block';

        // Hide irrelevant controls
        requiredCheck.parentElement.style.display = 'none'; // Hide required toggle
        item.querySelector('.question-type-select').disabled = true; // Lock type
    }

    // DISCORD USER HANDLING
    const discordRoleArea = item.querySelector('.discord-role-area');
    if (q.question_type === 'discord_user') {
        discordRoleArea.style.display = 'block';
        loadDiscordRolesForQuestion(item, q);
    } else {
        discordRoleArea.style.display = 'none';
    }

    // Auto-resize Question Title
    titleInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    // Trigger once to set initial height
    setTimeout(() => { titleInput.style.height = 'auto'; titleInput.style.height = titleInput.scrollHeight + 'px'; }, 0);

    // Event Listeners
    // Event Listeners (Removed manual addOptionBtn listener as it's now inside renderOptions)

    titleInput.addEventListener('input', debounce(() => saveQuestion(q.id, item), 800));
    item.querySelector('.section-desc-input').addEventListener('input', debounce(() => saveQuestion(q.id, item), 800));

    typeSelect.addEventListener('change', (e) => {
        const newType = e.target.value;
        const show = ['radio', 'checkbox', 'select'].includes(newType);
        optionArea.style.display = show ? 'block' : 'none';

        // Toggle Discord Role Area
        if (discordRoleArea) {
            if (newType === 'discord_user') {
                discordRoleArea.style.display = 'block';
                loadDiscordRolesForQuestion(item, q);
            } else {
                discordRoleArea.style.display = 'none';
            }
        }

        // Update markers
        item.querySelectorAll('.option-marker').forEach(i => {
            i.className = `far ${newType === 'checkbox' ? 'fa-square' : 'fa-circle'} option-marker`;
        });

        q.question_type = newType; // Update local state for subsequent renders if needed
        saveQuestion(q.id, item);
    });

    requiredCheck.addEventListener('change', () => saveQuestion(q.id, item));

    deleteBtn.addEventListener('click', async () => {
        Swal.fire({
            title: 'Delete Question?',
            text: "This cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            background: '#151515',
            color: '#fff'
        }).then(async (result) => {
            if (result.isConfirmed) {
                await fetch('form_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_question&id=${q.id}`
                });
                // Animate out
                item.style.transform = 'translateX(50px)';
                item.style.opacity = '0';
                setTimeout(() => {
                    item.remove();
                    // Check if empty
                    if (document.getElementById('questionsContainer').children.length === 0) {
                        document.getElementById('emptyState').style.display = 'block';
                    }
                }, 300);
            }
        });
    });

    document.getElementById('questionsContainer').appendChild(item);

    // Setup Drag Events
    setupDragEvents(item);
}

// Drag & Drop Logic
let draggedItem = null;

function setupDragEvents(item) {
    item.addEventListener('dragstart', (e) => {
        draggedItem = item;
        e.dataTransfer.effectAllowed = 'move';
        item.classList.add('dragging');
        setTimeout(() => item.style.opacity = '0.5', 0);
    });

    item.addEventListener('dragend', () => {
        draggedItem = null;
        item.classList.remove('dragging');
        item.style.opacity = '1';
        saveOrder();
    });

    // We attach dragover/drop to the container, not individual items (bubbles up)
}

// Initialize Container Drag (Moved to DOMContentLoaded)

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.question-item:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

async function saveOrder() {
    const items = document.querySelectorAll('.question-item');
    const order = Array.from(items).map(item => item.dataset.id);

    // Debounce or immediate? Immediate is fine for drop.
    try {
        await fetch('form_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_order&order=${JSON.stringify(order)}`
        });
        console.log('Order saved');
    } catch (e) {
        console.error('Failed to save order', e);
    }
}

async function saveQuestion(id, el) {
    const text = el.querySelector('.question-text-input').value;
    const desc = el.querySelector('.section-desc-input').value; // Get description
    const type = el.querySelector('.question-type-select').value;
    const required = el.querySelector('.required-check').checked ? 1 : 0;


    // CHECK IF "OTHER" is enabled
    // We check our data object attached to the element or check DOM. 
    // Wait, the 'q' object in renderQuestion scope is nice but saveQuestion is separate.
    // The renderOptions updates 'q.allow_other' locally but we need to persist it.
    // However, saveQuestion is called FROM renderOptions, but saveQuestion reconstructs state from DOM?
    // Let's rely on checking if .other-option-item exists in DOM, which matches visual state.
    const hasOther = el.querySelector('.other-option-item') !== null ? 1 : 0;

    let options;

    // For discord_user type, collect selected roles from checkboxes
    if (type === 'discord_user') {
        const roleCheckboxes = el.querySelectorAll('.discord-role-list input[type="checkbox"]:checked');
        options = Array.from(roleCheckboxes).map(cb => cb.value);
    } else {
        const optionInputs = el.querySelectorAll('.option-input:not([disabled])'); // Exclude disabled other
        options = Array.from(optionInputs).map(i => i.value);
    }

    await fetch('form_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_question&id=${id}&question_text=${encodeURIComponent(text)}&description=${encodeURIComponent(desc)}&question_type=${type}&is_required=${required}&allow_other=${hasOther}&options=${encodeURIComponent(JSON.stringify(options))}`
    });
    console.log('Question saved');
}

async function testWebhook(type) {
    let url = '';
    if (type === 'staff') {
        url = document.getElementById('formWebhookStaff').value.trim();
    } else if (type === 'welcome') {
        url = document.getElementById('formWebhookWelcome').value.trim();
    } else if (type === 'public') {
        url = document.getElementById('formWebhookPublic').value.trim();
    } else {
        // Fallback or error
        console.error('Unknown webhook type');
        return;
    }

    if (!url) {
        Swal.fire('Error', 'Please enter a Webhook URL first.', 'error');
        return;
    }

    // Find the button that calls testWebhook()
    const btn = document.querySelector(`button[onclick="testWebhook('${type}')"]`);
    const originalText = btn ? btn.innerHTML : '<i class="fas fa-paper-plane"></i>';

    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
    }

    try {
        const res = await fetch('form_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=test_webhook&webhook_url=${encodeURIComponent(url)}`
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: 'Test message sent. Check your Discord channel.',
                icon: 'success',
                background: '#151515',
                color: '#fff'
            });
        } else {
            Swal.fire({
                title: 'Failed',
                text: data.message || 'Unknown error',
                icon: 'error',
                background: '#151515',
                color: '#fff'
            });
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Network or server error.', 'error');
    } finally {
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
}

// Cache for Discord roles
let discordRolesCache = null;

// Load Discord roles for a specific question
async function loadDiscordRolesForQuestion(item, q) {
    const roleList = item.querySelector('.discord-role-list');
    if (!roleList) return;

    // Parse currently selected roles from options (for discord_user type, options contains the selected roles)
    let selectedRoles = [];
    if (q.question_type === 'discord_user' && q.options) {
        try {
            const parsed = typeof q.options === 'string' ? JSON.parse(q.options) : q.options;
            // Check if it's the role format (array of role names)
            if (Array.isArray(parsed) && parsed.length > 0 && typeof parsed[0] === 'string') {
                // Filter out default options like "Option 1", "Option 2"
                selectedRoles = parsed.filter(r => !r.startsWith('Option '));
            }
        } catch (e) {
            console.log('Could not parse options:', e);
        }
    }

    try {
        // Check cache first
        if (!discordRolesCache) {
            const response = await fetch('/api/bot_proxy.php?action=get_roles');

            const data = await response.json();
            if (data.success && data.roles) {
                discordRolesCache = data.roles;
            } else {
                throw new Error(data.error || 'Failed to load roles');
            }
        }

        // Render roles as checkboxes
        roleList.innerHTML = discordRolesCache.map(role => {
            const isChecked = selectedRoles.includes(role.name) ? 'checked' : '';
            return `
                <label class="discord-role-checkbox" style="display: flex; align-items: center; padding: 8px 12px; cursor: pointer; border-radius: 6px; margin-bottom: 4px; transition: background 0.2s; background: ${isChecked ? 'rgba(88, 101, 242, 0.15)' : 'transparent'};"
                       onmouseover="this.style.background='rgba(255,255,255,0.05)'" 
                       onmouseout="this.style.background='${isChecked ? 'rgba(88, 101, 242, 0.15)' : 'transparent'}'">
                    <input type="checkbox" value="${role.name}" ${isChecked}
                           style="width: 16px; height: 16px; margin-right: 10px; accent-color: #5865F2;">
                    <span class="discord-role-badge" style="
                        display: inline-flex;
                        align-items: center;
                        padding: 4px 10px;
                        border-radius: 12px;
                        font-size: 0.85rem;
                        font-weight: 500;
                        background: ${role.color !== '#99aab5' ? role.color + '20' : 'rgba(255,255,255,0.1)'};
                        color: ${role.color !== '#99aab5' ? role.color : '#fff'};
                        border: 1px solid ${role.color !== '#99aab5' ? role.color + '40' : 'rgba(255,255,255,0.2)'};
                    ">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: ${role.color}; margin-right: 6px;"></span>
                        ${role.name}
                    </span>
                </label>
            `;
        }).join('');

        // Add change listeners to save when checkboxes change
        roleList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Update background
                const label = checkbox.closest('label');
                if (label) {
                    label.style.background = checkbox.checked ? 'rgba(88, 101, 242, 0.15)' : 'transparent';
                }
                saveQuestion(q.id, item);
            });
        });

    } catch (error) {
        console.error('Error loading Discord roles:', error);
        roleList.innerHTML = `
            <div style="text-align: center; padding: 20px; color: #ef4444;">
                <i class="fas fa-exclamation-triangle"></i> Failed to load roles<br>
                <small style="color: var(--text-muted);">${error.message}</small>
            </div>
        `;
    }
}
