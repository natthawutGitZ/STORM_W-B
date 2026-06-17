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
        background: rgba(5, 5, 5, 0.45); 
        backdrop-filter: blur(20px); 
        -webkit-backdrop-filter: blur(20px); 
        border: 1px solid rgba(197, 160, 89, 0.25); 
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(197, 160, 89, 0.08);
    }
    
    .pill-glass-dropdown { 
        background: rgba(5, 5, 5, 0.65); 
        backdrop-filter: blur(32px); 
        -webkit-backdrop-filter: blur(32px);
        border: 1px solid rgba(197, 160, 89, 0.15); 
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.7), inset 0 0 15px rgba(197, 160, 89, 0.05);
    }

    .pill-nav-item::after { 
        content: ''; 
        position: absolute; 
        bottom: -2px; 
        left: 50%; 
        width: 0; 
        height: 2px; 
        background: #c5a059; /* gold */
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        transform: translateX(-50%); 
        border-radius: 2px;
        box-shadow: 0 0 8px rgba(197, 160, 89, 0.6);
    }

    .pill-nav-item:hover::after, .pill-nav-item.active::after { 
        width: calc(100% - 1.5rem); 
    }
    
    .pill-navbar-container {
        transition: box-shadow 0.4s ease, border-color 0.4s ease;
        font-family: 'Inter', sans-serif;
    }
    .pill-navbar-container:hover {
        box-shadow: 0 0 25px rgba(197, 160, 89, 0.15);
        border-color: rgba(197, 160, 89, 0.5);
    }
    @media (max-width: 767px) {
        .pill-navbar-container { border-radius: 1rem; }
        .pill-navbar-container.menu-open { border-radius: 1rem; }
    }
    
    /* Mobile menu transition */
    #mobile-menu {
        transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
        max-height: 0;
        opacity: 0;
        overflow: hidden;
    }
    #mobile-menu.open {
        max-height: 500px;
        opacity: 1;
    }
</style>

<nav class="fixed top-6 left-1/2 -translate-x-1/2 w-[98%] md:w-[95%] max-w-[1400px] z-50 m-0 p-0 text-left" style="font-family: 'Inter', sans-serif;">
    <div class="pill-navbar-container pill-glass-panel rounded-full px-3 py-2 flex flex-wrap items-center justify-between relative">
        
        <!-- Logo -->
        <a href="/" class="flex items-center gap-3 cursor-pointer group px-3 no-underline" style="text-decoration: none;">
            <div class="w-8 h-8 rounded-full bg-neutral-900 border border-gold/40 flex items-center justify-center text-gold font-bold shadow-lg shadow-gold/10 group-hover:border-gold group-hover:scale-110 group-hover:shadow-[0_0_15px_rgba(197,160,89,0.3)] transition-all duration-300">
                <i class="fas fa-bolt text-sm"></i>
            </div>
            <span class="text-white font-bold tracking-tight text-lg group-hover:text-gold transition-colors">STORM</span>
        </a>

        <!-- Center Nav Desktop -->
        <div class="hidden md:flex items-center justify-center gap-0.5 lg:gap-1 flex-1">
            <?php if ($is_admin): ?>
                
                <!-- Admin: MEMBERS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/dashboard') !== false || strpos($active_path, 'admin/member_') !== false || strpos($active_path, 'admin/Unit_') !== false ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center gap-1.5 text-[12px] lg:text-[13px] font-bold hover:text-gold px-2.5 lg:px-3 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                        <i class="fas fa-users-cog text-gold/80"></i> Members <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Manage Users</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/dashboard" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-chart-line text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Dashboard</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">System overview and stats</div>
                                    </div>
                                </a>
                                <a href="/admin/member_management" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-users text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Member Management</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Add, edit, or remove users</div>
                                    </div>
                                </a>
                                <a href="/admin/Unit_Structure" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-sitemap text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Unit Structure</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Manage organizational roles</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: MEDIA -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/manage_media') !== false || strpos($active_path, 'admin/manage_categories') !== false ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center gap-1.5 text-[12px] lg:text-[13px] font-bold hover:text-gold px-2.5 lg:px-3 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                        <i class="fas fa-photo-video text-gold/80"></i> Media <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Assets</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/manage_media" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-images text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Manage Gallery</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Upload and view site media</div>
                                    </div>
                                </a>
                                <a href="/admin/manage_categories_tags" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-tags text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Manage Tags</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Organize media categories</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: APPLICATIONS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/applications') !== false ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center gap-1.5 text-[12px] lg:text-[13px] font-bold hover:text-gold px-2.5 lg:px-3 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                        <i class="fas fa-file-signature text-gold/80"></i> Applications <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Forms & Data</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/applications" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-list-alt text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Application List</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Review user submissions</div>
                                    </div>
                                </a>
                                <a href="/admin/applications?view=forms" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-wpforms text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Custom Forms</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Create and edit templates</div>
                                    </div>
                                </a>
                                <a href="/admin/applications?view=summary" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-chart-pie text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Summary</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">View application analytics</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: EVENTS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/manage_operations') !== false || strpos($active_path, 'campaigns') !== false ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center gap-1.5 text-[12px] lg:text-[13px] font-bold hover:text-gold px-2.5 lg:px-3 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                        <i class="fas fa-calendar-alt text-gold/80"></i> Events <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Activities</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/manage_operations" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-calendar-check text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Operations</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Manage active operations</div>
                                    </div>
                                </a>
                                <a href="/campaigns" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-globe-americas text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Campaigns</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Oversee system campaigns</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: BOT CONTROLS -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/bot_controls') !== false ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center gap-1.5 text-[12px] lg:text-[13px] font-bold hover:text-gold px-2.5 lg:px-3 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                        <i class="fab fa-discord text-gold/80"></i> Bot Controls <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover (Wide Mega Menu) -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[580px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Discord Management</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- 2-Column Grid Items -->
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <a href="/admin/bot_controls?tab=embed" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-pen-fancy text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Embed Builder</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px]">Create custom embed messages</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=bot" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-robot text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Bot Settings</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px]">Configure core bot behaviors</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=welcome" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-volume-up text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Welcome Voice</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px]">Manage auto-voice channels</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=permissions" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-user-shield text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Permissions</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px]">Control role access rights</div>
                                    </div>
                                </a>
                                <a href="/admin/bot_controls?tab=tickets" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0 col-span-1">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-ticket-alt text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Tickets</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none truncate max-w-[180px]">Manage support system</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin: OTHER -->
                <div class="relative group px-0.5 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/admin_donate') !== false ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center gap-1.5 text-[12px] lg:text-[13px] font-bold hover:text-gold px-2.5 lg:px-3 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                        <i class="fas fa-star text-gold/80"></i> Other <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Miscellaneous</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/admin_donate" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-donate text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Donate Manager</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">Manage donation goals and links</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Public Nav Items -->
                <a href="/" class="pill-nav-item <?php echo isPillNavActive('', $active_path) ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 no-underline tracking-wider uppercase" style="text-decoration:none;">Home</a>
                
                <a href="/media" class="pill-nav-item <?php echo isPillNavActive('media', $active_path) ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 no-underline tracking-wider uppercase" style="text-decoration:none;">Media</a>
                
                <a href="/register" class="pill-nav-item <?php echo isPillNavActive('register', $active_path) ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 no-underline tracking-wider uppercase" style="text-decoration:none;">Create Application</a>

                <!-- Standard Events Dropdown -->
                <div class="relative group px-1 flex-shrink-0">
                    <button class="pill-nav-item <?php echo strpos($active_path, 'admin/manage_operations') !== false || strpos($active_path, 'campaigns') !== false ? 'active text-gold bg-gold/10' : 'text-slate-300'; ?> relative flex items-center gap-1.5 text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                        Events <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                    </button>
                    <!-- Invisible Bridge for Hover -->
                    <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                        <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                            <!-- Header Bar -->
                            <div class="flex items-center justify-center mb-3 mt-1 px-2">
                                <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                                <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Public Events</span>
                                <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                            </div>
                            <!-- Items -->
                            <div class="flex flex-col gap-1">
                                <a href="/admin/manage_operations" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0" style="text-decoration:none;">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-calendar-check text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Operations</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">View active operations</div>
                                    </div>
                                </a>
                                <a href="/campaigns" class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-gold/10 transition-colors group/item no-underline mx-0" style="text-decoration:none;">
                                    <div class="w-10 h-10 rounded-lg bg-black/40 border border-gold/10 flex items-center justify-center text-gold/70 group-hover/item:text-gold group-hover/item:border-gold/30 transition-all flex-shrink-0 shadow-inner">
                                        <i class="fas fa-globe-americas text-[15px]"></i>
                                    </div>
                                    <div class="flex flex-col justify-center h-10">
                                        <div class="text-[13px] font-bold text-slate-200 group-hover/item:text-gold transition-colors leading-none mb-1">Campaigns</div>
                                        <div class="text-[11px] text-slate-500 group-hover/item:text-slate-400 leading-none">View current campaigns</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Side (User/Auth) -->
        <div class="flex items-center gap-2 px-1 lg:px-2 ml-auto md:ml-0">
            <?php if ($is_logged_in && $user): ?>
                <div class="hidden sm:flex items-center gap-3 border-r border-gold/20 pr-4 mr-2">
                    <div class="text-right">
                        <div class="text-sm font-semibold text-white tracking-tight" style="line-height:1.2; m-0 p-0"><?php echo htmlspecialchars($user['personaname']); ?></div>
                        <div class="text-[10px] text-gold uppercase tracking-widest font-medium opacity-80" style="line-height:1; m-0 p-0"><?php echo htmlspecialchars($user['role'] ?? 'User'); ?></div>
                    </div>
                    <a href="/profile" class="w-9 h-9 rounded-full border-2 border-gold/40 overflow-hidden cursor-pointer hover:border-gold hover:shadow-[0_0_15px_rgba(197,160,89,0.6)] transition-all duration-300 inline-block shrink-0">
                        <img src="<?php echo get_avatar($user['avatar'] ?? null); ?>" alt="User Avatar" class="w-full h-full object-cover m-0 p-0 block" />
                    </a>
                </div>
                
                <a href="/logout" class="group flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-neutral-800 to-neutral-900 border border-gold/30 text-gold text-sm font-semibold rounded-full hover:border-gold transition-all shadow-lg hover:shadow-[0_0_15px_rgba(197,160,89,0.4)] focus:outline-none no-underline" style="text-decoration:none;">
                    <i class="fas fa-sign-out-alt group-hover:scale-105 transition-transform"></i>
                    <span class="hidden md:block">Logout</span>
                </a>
            <?php else: ?>
                <button onclick="openLoginModal(); return false;" class="group flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-neutral-800 to-neutral-900 border border-gold/30 text-gold text-sm font-semibold rounded-full hover:border-gold transition-all shadow-lg hover:shadow-[0_0_15px_rgba(197,160,89,0.4)] focus:outline-none cursor-pointer">
                    <i class="fas fa-sign-in-alt group-hover:scale-110 transition-transform"></i> Members Area
                </button>
            <?php endif; ?>

            <!-- Mobile Menu Toggle -->
            <button id="mobile-menu-btn" class="md:hidden p-2 text-gold hover:text-white bg-transparent rounded-full border border-gold/20 focus:outline-none hover:bg-gold/20 hover:border-gold transition-all ml-1 cursor-pointer">
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
                        <img src="<?php echo get_avatar($user['avatar'] ?? null); ?>" class="w-8 h-8 rounded-full object-cover inline-block border border-gold/40 shadow-lg" />
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
    });
</script>
