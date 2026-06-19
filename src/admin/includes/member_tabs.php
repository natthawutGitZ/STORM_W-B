<?php
// Use REQUEST_URI to detect current page since central router makes PHP_SELF always /index.php
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'members';
?>
<div class="dashboard-container" style="padding-top: 10px; padding-bottom: 0;">

    <div style="margin: 0 0 20px 0; border-bottom: 1px solid #333; display: flex; gap: 10px; overflow-x: auto; white-space: nowrap; padding-bottom: 5px;"
        class="member-nav-tabs">
        <a href="member_management?tab=members"
            class="tab-link <?php echo $active_tab === 'members' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'members' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-users"></i> Members
        </a>
        <a href="member_management?tab=units" class="tab-link <?php echo $active_tab === 'units' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'units' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-shield-alt"></i> Units
        </a>

        <a href="member_management?tab=ranks" class="tab-link <?php echo $active_tab === 'ranks' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'ranks' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-layer-group"></i> Ranks
        </a>
        <a href="member_management?tab=awards" class="tab-link <?php echo $active_tab === 'awards' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'awards' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-trophy"></i> Awards
        </a>
        <a href="member_management?tab=qualifications"
            class="tab-link <?php echo $active_tab === 'qualifications' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'qualifications' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-graduation-cap"></i> Qualifications
        </a>
        <a href="member_management?tab=positions"
            class="tab-link <?php echo $active_tab === 'positions' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'positions' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-user-tag"></i> Positions
        </a>
    </div>
</div>

<style>
    .member-nav-tabs::-webkit-scrollbar {
        height: 4px;
    }

    .member-nav-tabs::-webkit-scrollbar-track {
        background: transparent;
    }

    .member-nav-tabs::-webkit-scrollbar-thumb {
        background: rgba(197, 160, 89, 0.3);
        border-radius: 4px;
    }

    .member-nav-tabs::-webkit-scrollbar-thumb:hover {
        background: rgba(197, 160, 89, 0.6);
    }

    .tab-link:hover {
        color: #fff !important;
    }

    /* AJAX Tab Transition */
    #tab-content {
        transition: opacity 0.25s ease;
    }

    #tab-content.loading {
        opacity: 0.3;
        pointer-events: none;
    }
</style>

<!-- AJAX Tab Content Container -->
<div id="tab-content">

    <script>
        (function () {
            const tabLinks = document.querySelectorAll('.member-nav-tabs .tab-link');
            const tabContent = document.getElementById('tab-content');
            let currentTab = '<?php echo $active_tab; ?>';

            function updateActiveTab(tab) {
                tabLinks.forEach(link => {
                    const url = new URL(link.href, window.location.origin);
                    const linkTab = url.searchParams.get('tab') || 'members';
                    if (linkTab === tab) {
                        link.classList.add('active');
                        link.style.color = 'var(--accent-color)';
                        link.style.borderBottomColor = 'var(--accent-color)';
                    } else {
                        link.classList.remove('active');
                        link.style.color = '#aaa';
                        link.style.borderBottomColor = 'transparent';
                    }
                });
            }

            function loadTab(tab, pushState) {
                if (!tabContent) return;

                tabContent.classList.add('loading');
                currentTab = tab;
                updateActiveTab(tab);

                const ajaxUrl = '/admin/member_management?tab=' + encodeURIComponent(tab) + '&ajax=1';

                fetch(ajaxUrl)
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.text();
                    })
                    .then(html => {
                        tabContent.innerHTML = html;
                        tabContent.classList.remove('loading');

                        // Re-execute inline scripts from the loaded content
                        const scripts = tabContent.querySelectorAll('script');
                        scripts.forEach(oldScript => {
                            const newScript = document.createElement('script');
                            if (oldScript.src) {
                                newScript.src = oldScript.src;
                            } else {
                                newScript.textContent = oldScript.textContent;
                            }
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        });

                        if (pushState) {
                            history.pushState({ tab: tab }, '', '/admin/member_management?tab=' + tab);
                        }
                    })
                    .catch(err => {
                        console.error('Tab load error:', err);
                        // Fallback: navigate normally
                        window.location.href = '/admin/member_management?tab=' + tab;
                    });
            }

            // Intercept tab clicks
            tabLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = new URL(this.href, window.location.origin);
                    const tab = url.searchParams.get('tab') || 'members';
                    if (tab !== currentTab) {
                        loadTab(tab, true);
                    }
                });
            });

            // Handle browser back/forward
            window.addEventListener('popstate', function (e) {
                if (e.state && e.state.tab) {
                    loadTab(e.state.tab, false);
                } else {
                    const params = new URLSearchParams(window.location.search);
                    const tab = params.get('tab') || 'members';
                    loadTab(tab, false);
                }
            });

            // Set initial state
            history.replaceState({ tab: currentTab }, '', window.location.href);
        })();
    </script>