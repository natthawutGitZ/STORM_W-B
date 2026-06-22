<?php
$req_uri = $_SERVER['REQUEST_URI'];
$inAdminContext = strpos($req_uri, '/admin/') !== false;
$active_path = trim(parse_url($req_uri, PHP_URL_PATH), '/');

function isPillNavActive($path, $active_path) {
    if ($path === '' && ($active_path === '' || $active_path === 'index')) return true;
    if ($path !== '' && strpos($active_path, $path) === 0) return true;
    return false;
}

$user = function_exists('getUser') ? getUser() : null;
$is_logged_in = function_exists('isLoggedIn') ? isLoggedIn() : false;
$is_admin = function_exists('isAdmin') ? isAdmin() : false;
?>

<!-- Pill Navbar Styles and Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: {
            preflight: false,
        },
        theme: {
            extend: {
                colors: {
                    gold: {
                        DEFAULT: '#c5a059',
                        dark: '#a68545',
                        light: '#d4b77c',
                    }
                }
            }
        }
    }
</script>
<style>
    /* Scoped custom styles for the pill navbar to avoid conflicts */
    .pill-glass-panel { 
        background: rgba(10, 10, 10, 0.85); 
        backdrop-filter: blur(16px); 
        -webkit-backdrop-filter: blur(16px); 
        border-bottom: 1px solid rgba(197, 160, 89, 0.2); 
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    }
    
    .pill-glass-dropdown { 
        background: rgba(10, 10, 10, 0.95); 
        backdrop-filter: blur(24px); 
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(197, 160, 89, 0.2); 
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.7);
        border-radius: 0px !important;
    }

    .pill-nav-item {
        font-family: 'Inter', sans-serif;
        font-size: 12px !important;
        font-weight: 700 !important;
        text-transform: uppercase;
        letter-spacing: 0.22em !important;
        color: rgba(203, 213, 225, 0.85) !important;
        padding: 10px 20px !important;
        border: 1px solid transparent;
        border-radius: 0px !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        text-decoration: none !important;
        background: transparent;
        cursor: pointer;
    }

    .pill-nav-item:hover, .pill-nav-item.active { 
        border-color: rgba(197, 160, 89, 0.45) !important;
        background: rgba(197, 160, 89, 0.05) !important;
        color: #c5a059 !important;
        text-shadow: 0 0 8px rgba(197, 160, 89, 0.3);
    }
    
    .pill-navbar-container {
        transition: padding 0.4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.4s ease, border-color 0.4s ease, background-color 0.4s ease;
        font-family: 'Inter', sans-serif;
    }
    .nav-scrolled .pill-navbar-container {
        padding-top: 10px !important;
        padding-bottom: 10px !important;
        background-color: rgba(10, 10, 10, 0.92) !important;
        border-bottom-color: rgba(197, 160, 89, 0.3) !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8) !important;
    }
    @media (max-width: 767px) {
        .pill-navbar-container { border-radius: 0; }
        .pill-navbar-container.menu-open { border-radius: 0; }
    }
    
    /* Scroll Collapse Container */
    .scroll-collapse-container {
        max-height: 100px;
        opacity: 1;
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), 
                    opacity 0.3s ease, 
                    transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        transform-origin: top;
        overflow: hidden;
    }
    .nav-scrolled .scroll-collapse-container {
        max-height: 0 !important;
        opacity: 0 !important;
        transform: translateY(-100%);
        pointer-events: none;
    }

    /* Color Tones and Dividers */
    .status-info-bar {
        background-color: #13160e !important;
        border-bottom: 1px solid rgba(197, 160, 89, 0.12) !important;
        color: #8c9675 !important;
    }
    .clock-bar {
        background-color: #090b07 !important;
        border-bottom: 1px solid rgba(197, 160, 89, 0.18) !important;
    }
    
    /* Mobile menu transition and styles */
    #mobile-menu {
        transition: max-height 0.35s ease-in-out, opacity 0.3s ease-in-out;
        max-height: 0;
        opacity: 0;
        overflow: hidden;
    }
    #mobile-menu.open {
        max-height: 80vh;
        opacity: 1;
        overflow-y: auto;
    }
    #mobile-menu a {
        font-family: 'Inter', sans-serif;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.1em;
        border: 1px solid transparent;
        border-radius: 0px;
        transition: all 0.2s ease;
    }
    #mobile-menu a:hover {
        border-color: rgba(197, 160, 89, 0.3);
        background: rgba(197, 160, 89, 0.05);
        color: #c5a059 !important;
    }
</style>

<nav id="main-navigation" class="fixed top-0 left-0 w-full z-50 m-0 p-0 text-left flex flex-col" style="font-family: 'Inter', sans-serif;">
    <!-- Scrollable Top Header Wrapper -->
    <div class="scroll-collapse-container hidden xl:block">
        <!-- Status Info Bar -->
        <div class="status-info-bar w-full py-1.5 px-4 flex justify-center items-center gap-4 text-[9px] tracking-[0.32em] uppercase font-mono">
            // UNCLASSIFIED // PUBLIC RELEASE // S.T.O.R.M. // OPS NET //
        </div>
        <!-- Clock Bar -->
        <div class="clock-bar w-full py-2 px-4 flex justify-center items-center gap-6 text-[10px] tracking-widest uppercase text-gold/60 font-mono">
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">LOCAL</span> <span id="clock-local" class="text-slate-200 font-bold">00:00:00</span></div>
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">ZULU</span> <span id="clock-zulu" class="text-slate-200 font-bold">00:00:00Z</span></div>
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">EST</span> <span id="clock-est" class="text-slate-200 font-bold">00:00:00</span></div>
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">CET</span> <span id="clock-cet" class="text-slate-200 font-bold">00:00:00</span></div>
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">AU</span> <span id="clock-au" class="text-slate-200 font-bold">00:00:00</span></div>
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">MST</span> <span id="clock-mst" class="text-slate-200 font-bold">00:00:00</span></div>
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">CST</span> <span id="clock-cst" class="text-slate-200 font-bold">00:00:00</span></div>
            <div class="flex items-center gap-1.5"><span class="text-[#7a8267]">GMT</span> <span id="clock-gmt" class="text-slate-200 font-bold">00:00:00</span></div>
        </div>
    </div>
    
    <script>
        function updateClocks() {
            const now = new Date();
            const formatTime = (date, timeZone) => {
                try {
                    return new Intl.DateTimeFormat('en-GB', {
                        hour: '2-digit', minute: '2-digit', second: '2-digit',
                        timeZone: timeZone, hour12: false
                    }).format(date);
                } catch (e) { return "00:00:00"; }
            };
            const formatLocal = (date) => {
                return date.getHours().toString().padStart(2, '0') + ':' + 
                       date.getMinutes().toString().padStart(2, '0') + ':' + 
                       date.getSeconds().toString().padStart(2, '0');
            };
            if(document.getElementById('clock-local')) {
                document.getElementById('clock-local').textContent = formatLocal(now);
                document.getElementById('clock-zulu').textContent = formatTime(now, 'UTC') + 'Z';
                document.getElementById('clock-est').textContent = formatTime(now, 'America/New_York');
                document.getElementById('clock-cet').textContent = formatTime(now, 'Europe/Paris');
                document.getElementById('clock-au').textContent = formatTime(now, 'Australia/Sydney');
                document.getElementById('clock-mst').textContent = formatTime(now, 'America/Denver');
                document.getElementById('clock-cst').textContent = formatTime(now, 'America/Chicago');
                document.getElementById('clock-gmt').textContent = formatTime(now, 'Europe/London');
            }
        }
        setInterval(updateClocks, 1000);
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', updateClocks); } else { updateClocks(); }
    </script>

    <div class="pill-navbar-container pill-glass-panel px-4 py-4 md:py-5 md:px-8 flex flex-wrap items-center justify-between relative border-x-0 border-t-0" style="border-radius: 0;">
        
        <!-- Logo -->
        <a href="/" class="flex items-center gap-4 cursor-pointer group px-3 no-underline" style="text-decoration: none;">
            <div class="w-12 h-12 rounded-full bg-[#0d0d0c] border border-gold/45 flex items-center justify-center text-gold font-bold shadow-lg shadow-gold/15 group-hover:border-gold group-hover:shadow-[0_0_18px_rgba(197,160,89,0.35)] transition-all duration-300">
                <i class="fas fa-bolt text-[20px]"></i>
            </div>
            <div class="flex flex-col text-left justify-center">
                <span class="text-white font-bold text-[17px] md:text-[19px] tracking-[0.28em] group-hover:text-gold transition-colors leading-none uppercase font-mono">S.T.O.R.M.</span>
                <span class="text-[9px] md:text-[10px] tracking-[0.08em] text-[#7a826c]/80 group-hover:text-[#949c83] transition-colors uppercase mt-2 leading-none font-sans font-semibold">STRATEGIC TACTICAL OPERATIONS / EST. 2024</span>
            </div>
        </a>

        <!-- Center Nav Desktop -->
        <div class="hidden md:flex items-center justify-center gap-0.5 lg:gap-1 flex-1">
            <?php if ($is_admin): ?>
                
                <!-- Admin: MEMBERS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/dashboard') !== false || strpos($active_path, 'admin/member_') !== false || strpos($active_path, 'admin/Unit_') !== false ? 'active' : ''; ?> gap-1.5">
                        <i class="fas fa-users-cog text-gold/80"></i> Members <i class="fas fa-chevron-down text-[8px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-none p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase font-mono">Manage Users</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/dashboard" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-chart-line text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Dashboard</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">System overview and stats</div>
                                    </div>
                                </a>
                                <a href="/admin/member_management" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-users text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Member Management</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Add, edit, or remove users</div>
                                    </div>
                                </a>
                                <a href="/admin/Unit_Structure" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-sitemap text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Unit Structure</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Manage organizational roles</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: MEDIA -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/manage_media') !== false || strpos($active_path, 'admin/manage_categories') !== false ? 'active' : ''; ?> gap-1.5">
                        <i class="fas fa-photo-video text-gold/80"></i> Media <i class="fas fa-chevron-down text-[8px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-none p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase font-mono">Assets</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/manage_media" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-images text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Manage Gallery</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Upload and view site media</div>
                                    </div>
                                </a>
                                <a href="/admin/manage_categories_tags" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-tags text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Manage Tags</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Organize media categories</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: APPLICATIONS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/applications') !== false ? 'active' : ''; ?> gap-1.5">
                        <i class="fas fa-file-signature text-gold/80"></i> Applications <i class="fas fa-chevron-down text-[8px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-none p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase font-mono">Forms & Data</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/applications" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-list-alt text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Application List</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Review user submissions</div>
                                    </div>
                                </a>
                                <a href="/admin/applications?view=forms" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-wpforms text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Custom Forms</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Create and edit templates</div>
                                    </div>
                                </a>
                                <a href="/admin/applications?view=summary" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-chart-pie text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Summary</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">View application analytics</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: EVENTS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/manage_operations') !== false || strpos($active_path, 'campaigns') !== false ? 'active' : ''; ?> gap-1.5">
                        <i class="fas fa-calendar-alt text-gold/80"></i> Events <i class="fas fa-chevron-down text-[8px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-none p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase font-mono">Activities</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/manage_operations" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-calendar-check text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Operations</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Manage active operations</div>
                                    </div>
                                </a>
                                <a href="/campaigns" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-globe-americas text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Campaigns</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Oversee system campaigns</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: BOT CONTROLS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/bot_controls') !== false ? 'active' : ''; ?> gap-1.5">
                        <i class="fab fa-discord text-gold/80"></i> Bot Controls <i class="fas fa-chevron-down text-[8px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover (Wide Mega Menu) -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[580px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-none p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase font-mono">Discord Management</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- 2-Column Grid Items -->
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <a href="/admin/bot_controls?tab=embed" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-pen-fancy text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Embed Builder</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px] font-sans">Create custom embed messages</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=bot" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-robot text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Bot Settings</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px] font-sans">Configure core bot behaviors</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=welcome" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-volume-up text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Welcome Voice</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px] font-sans">Manage auto-voice channels</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=permissions" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-user-shield text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Permissions</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px] font-sans">Control role access rights</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=tickets" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0 col-span-1">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-ticket-alt text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Tickets</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px] font-sans">Manage support system</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: OTHER -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/admin_donate') !== false ? 'active' : ''; ?> gap-1.5">
                        <i class="fas fa-star text-gold/80"></i> Other <i class="fas fa-chevron-down text-[8px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-none p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase font-mono">Miscellaneous</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/admin_donate" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-donate text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Donate Manager</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans">Manage donation goals and links</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Public Nav Items -->
                <a href="/" class="pill-nav-item <?php echo isPillNavActive('', $active_path) ? 'active' : ''; ?>">Home</a>
                
                <a href="/media" class="pill-nav-item <?php echo isPillNavActive('media', $active_path) ? 'active' : ''; ?>">Media</a>
                
                <a href="/register" class="pill-nav-item <?php echo isPillNavActive('register', $active_path) ? 'active' : ''; ?>">Create Application</a>

                <!-- Standard Events Dropdown -->
                <div class="relative group px-1 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/manage_operations') !== false || strpos($active_path, 'campaigns') !== false ? 'active' : ''; ?> gap-1.5">
                        Events <i class="fas fa-chevron-down text-[8px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-none p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase font-mono">Public Events</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/manage_operations" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0" style="text-decoration:none;">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-calendar-check text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Operations</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans font-normal">View active operations</div>
                                    </div>
                                </a>
                                <a href="/campaigns" class="flex items-start gap-3 p-2.5 rounded-none border border-transparent hover:border-gold/20 hover:bg-gold/5 transition-colors group/item no-underline mx-0" style="text-decoration:none;">
                                    <div class="w-10 h-10 rounded-none bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-globe-americas text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1 font-mono uppercase tracking-wider">Campaigns</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none font-sans font-normal font-sans">View current campaigns</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Side (User/Auth) -->
        <div class="flex items-center gap-3 px-1 lg:px-2 ml-auto md:ml-0">
            <?php if ($is_logged_in && $user): ?>
                <div class="hidden sm:flex items-center gap-3 border-r border-gold/20 pr-4 mr-1">
                    <div class="text-right">
                        <div class="text-[12px] font-bold text-white tracking-widest uppercase font-mono leading-none mb-1"><?php echo htmlspecialchars($user['personaname']); ?></div>
                        <div class="text-[9px] text-gold uppercase tracking-[0.22em] font-mono leading-none opacity-85"><?php echo htmlspecialchars($user['role'] ?? 'User'); ?></div>
                    </div>
                    <a href="/profile" class="w-10 h-10 rounded-none border border-gold/45 overflow-hidden cursor-pointer hover:border-gold hover:shadow-[0_0_12px_rgba(197,160,89,0.3)] transition-all duration-300 inline-block shrink-0">
                        <img src="<?php echo get_avatar($user['avatar'] ?? null); ?>" alt="User Avatar" class="w-full h-full object-cover m-0 p-0 block" />
                    </a>
                </div>
                
                <a href="/logout" class="group border border-gold/45 hover:border-gold text-white font-mono text-[11px] tracking-[0.2em] font-bold px-6 py-2.5 hover:bg-gold/5 transition-all flex items-center gap-2.5 cursor-pointer uppercase no-underline rounded-none" style="text-decoration: none;">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#b93a3a] shrink-0"></span>
                    LOGOUT
                </a>
            <?php else: ?>
                <!-- Outlined SIGN IN button with red dot -->
                <button onclick="openLoginModal(); return false;" class="group border border-gold/45 hover:border-gold text-white font-mono text-[11px] tracking-[0.2em] font-bold px-6 py-2.5 hover:bg-gold/5 transition-all flex items-center gap-2.5 cursor-pointer uppercase rounded-none bg-transparent">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#b93a3a] shrink-0"></span>
                    SIGN IN
                </button>
                
                <!-- Solid ENLIST NOW button with dark dot -->
                <a href="/register" class="group bg-gold border border-gold hover:bg-[#d4b77c] hover:border-[#d4b77c] text-neutral-950 font-mono text-[11px] tracking-[0.2em] font-bold px-6 py-2.5 transition-all flex items-center gap-2.5 cursor-pointer uppercase no-underline rounded-none" style="text-decoration: none;">
                    <span class="w-1.5 h-1.5 rounded-full bg-neutral-950 shrink-0"></span>
                    ENLIST NOW
                </a>
            <?php endif; ?>

            <!-- Mobile Menu Toggle -->
            <button id="mobile-menu-btn" class="md:hidden p-2 text-gold hover:text-white bg-transparent rounded-none border border-gold/20 focus:outline-none hover:bg-gold/20 hover:border-gold transition-all ml-1 cursor-pointer">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="w-full md:hidden flex flex-col mt-2 basis-full">
            <div class="flex flex-col gap-1 py-3 border-t border-gold/20">
                
                <?php if ($is_admin): ?>
                    <!-- Admin Mobile Categories -->
                    <div class="text-[10px] font-bold text-gold/50 uppercase px-4 py-1 mt-1">Admin - Members</div>
                    <a href="/admin/dashboard" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-chart-line w-5 text-gold/60"></i> Dashboard</a>
                    <a href="/admin/member_management" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-users w-5 text-gold/60"></i> Member Management</a>
                    <a href="/admin/Unit_Structure" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-sitemap w-5 text-gold/60"></i> Unit Structure</a>

                    <div class="text-[10px] font-bold text-gold/50 uppercase px-4 py-1 mt-2">Admin - Media</div>
                    <a href="/admin/manage_media" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-images w-5 text-gold/60"></i> Manage Gallery</a>
                    <a href="/admin/manage_categories_tags" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-tags w-5 text-gold/60"></i> Manage Tags</a>

                    <div class="text-[10px] font-bold text-gold/50 uppercase px-4 py-1 mt-2">Admin - Applications</div>
                    <a href="/admin/applications" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-list-alt w-5 text-gold/60"></i> Application List</a>
                    <a href="/admin/applications?view=forms" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-wpforms w-5 text-gold/60"></i> Custom Forms</a>
                    <a href="/admin/applications?view=summary" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-chart-pie w-5 text-gold/60"></i> Summary</a>

                    <div class="text-[10px] font-bold text-gold/50 uppercase px-4 py-1 mt-2">Admin - Events</div>
                    <a href="/admin/manage_operations" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-calendar-check w-5 text-gold/60"></i> Operations Manager</a>
                    <a href="/campaigns" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-globe-americas w-5 text-gold/60"></i> Campaigns</a>

                    <div class="text-[10px] font-bold text-gold/50 uppercase px-4 py-1 mt-2">Admin - Bot Controls</div>
                    <a href="/admin/bot_controls?tab=embed" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-pen-fancy w-5 text-gold/60"></i> Embed Builder</a>
                    <a href="/admin/bot_controls?tab=bot" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-robot w-5 text-gold/60"></i> Bot Settings</a>
                    <a href="/admin/bot_controls?tab=welcome" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-volume-up w-5 text-gold/60"></i> Welcome Voice</a>
                    <a href="/admin/bot_controls?tab=permissions" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-user-shield w-5 text-gold/60"></i> Permissions</a>
                    <a href="/admin/bot_controls?tab=tickets" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-ticket-alt w-5 text-gold/60"></i> Tickets</a>

                    <div class="text-[10px] font-bold text-gold/50 uppercase px-4 py-1 mt-2">Admin - Misc</div>
                    <a href="/admin/admin_donate" class="px-6 py-1.5 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors"><i class="fas fa-donate w-5 text-gold/60"></i> Donate Manager</a>

                <?php else: ?>
                    <!-- Public Mobile Categories -->
                    <a href="/" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-gold hover:bg-gold/10 rounded-lg no-underline uppercase tracking-wider font-bold" style="text-decoration:none;">Home</a>
                    <a href="/media" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-gold hover:bg-gold/10 rounded-lg no-underline uppercase tracking-wider font-bold" style="text-decoration:none;">Media</a>
                    <a href="/register" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-gold hover:bg-gold/10 rounded-lg no-underline uppercase tracking-wider font-bold" style="text-decoration:none;">Create Application</a>
                    
                    <div class="text-xs font-bold text-gold/70 uppercase px-4 py-2 mt-2">Events</div>
                    <a href="/admin/manage_operations" class="px-8 py-2 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors" style="text-decoration:none;"><i class="fas fa-calendar-check mr-2 text-gold/60"></i> Operations</a>
                    <a href="/campaigns" class="px-8 py-2 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors" style="text-decoration:none;"><i class="fas fa-globe-americas mr-2 text-gold/60"></i> Campaigns</a>
                <?php endif; ?>
                
                <?php if ($is_logged_in && $user): ?>
                <div class="border-t border-gold/20 mt-3 pt-3 pb-1 px-4">
                    <a href="/profile" class="py-2 text-sm font-medium text-slate-300 hover:text-gold rounded-lg no-underline flex items-center gap-3 transition-colors" style="text-decoration:none;">
                        <img src="<?php echo get_avatar($user['avatar'] ?? null); ?>" class="w-8 h-8 rounded-none object-cover inline-block border border-gold/40 shadow-lg" />
                        <?php echo htmlspecialchars($user['personaname']); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        const container = document.querySelector('.pill-navbar-container');
        if (btn && menu) {
            btn.addEventListener('click', () => {
                menu.classList.toggle('open');
                if (container) container.classList.toggle('menu-open');
            });
        }

        // Scroll collapse behavior
        const nav = document.getElementById('main-navigation');
        if (nav) {
            const handleScroll = () => {
                if (window.scrollY > 30) {
                    nav.classList.add('nav-scrolled');
                } else {
                    nav.classList.remove('nav-scrolled');
                }
            };
            window.addEventListener('scroll', handleScroll);
            handleScroll(); // Initial check
        }
    });
</script>
