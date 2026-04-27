<template>
<!-- Pill Navbar Styles -->
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
            <!-- Public Nav Items -->
            <a href="/" class="pill-nav-item active text-gold bg-gold/10 relative flex items-center text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 no-underline tracking-wider uppercase" style="text-decoration:none;">Home</a>
            
            <a href="/media" class="pill-nav-item text-slate-300 relative flex items-center text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 no-underline tracking-wider uppercase" style="text-decoration:none;">Media</a>
            
            <a href="/register" class="pill-nav-item text-slate-300 relative flex items-center text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 no-underline tracking-wider uppercase" style="text-decoration:none;">Create Application</a>

            <!-- Events Dropdown -->
            <div class="relative group px-1 flex-shrink-0">
                <button class="pill-nav-item text-slate-300 relative flex items-center gap-1.5 text-[13px] font-bold hover:text-gold px-4 py-2 rounded-full hover:bg-gold/10 transition-all duration-200 focus:outline-none bg-transparent border-none cursor-pointer uppercase tracking-wider">
                    Events <i class="fas fa-chevron-down text-[9px] ml-0.5 opacity-70 group-hover:rotate-180 transition-transform duration-300"></i>
                </button>
                <!-- Invisible Bridge for Hover -->
                <div class="absolute top-[100%] left-1/2 -translate-x-1/2 w-[340px] pt-4 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-300 ease-out z-50">
                    <div class="pill-glass-dropdown rounded-2xl p-3 shadow-2xl relative overflow-hidden flex flex-col border border-gold/20 translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
                        <div class="flex items-center justify-center mb-3 mt-1 px-2">
                            <div class="h-[1px] bg-gradient-to-r from-transparent to-gold/20 flex-1"></div>
                            <span class="px-3 text-[9px] font-bold tracking-widest text-gold/50 uppercase">Public Events</span>
                            <div class="h-[1px] bg-gradient-to-l from-transparent to-gold/20 flex-1"></div>
                        </div>
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
        </div>

        <!-- Right Side (Auth) -->
        <div class="flex items-center gap-2 px-1 lg:px-2 ml-auto md:ml-0">
            <button @click="openLogin" class="group flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-neutral-800 to-neutral-900 border border-gold/30 text-gold text-sm font-semibold rounded-full hover:border-gold transition-all shadow-lg hover:shadow-[0_0_15px_rgba(197,160,89,0.4)] focus:outline-none cursor-pointer">
                <i class="fas fa-sign-in-alt group-hover:scale-110 transition-transform"></i> Members Area
            </button>

            <!-- Mobile Menu Toggle -->
            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gold hover:text-white bg-transparent rounded-full border border-gold/20 focus:outline-none hover:bg-gold/20 hover:border-gold transition-all ml-1 cursor-pointer">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div :class="['w-full md:hidden flex flex-col mt-2 basis-full', mobileOpen ? 'mobile-open' : 'mobile-closed']">
            <div class="flex flex-col gap-1 py-3 border-t border-gold/20">
                <a href="/" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-gold hover:bg-gold/10 rounded-lg no-underline uppercase tracking-wider font-bold" style="text-decoration:none;">Home</a>
                <a href="/media" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-gold hover:bg-gold/10 rounded-lg no-underline uppercase tracking-wider font-bold" style="text-decoration:none;">Media</a>
                <a href="/register" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-gold hover:bg-gold/10 rounded-lg no-underline uppercase tracking-wider font-bold" style="text-decoration:none;">Create Application</a>
                <div class="text-xs font-bold text-gold/70 uppercase px-4 py-2 mt-2">Events</div>
                <a href="/admin/manage_operations" class="px-8 py-2 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors" style="text-decoration:none;"><i class="fas fa-calendar-check mr-2 text-gold/60"></i> Operations</a>
                <a href="/campaigns" class="px-8 py-2 text-sm font-medium text-slate-400 hover:text-gold hover:bg-gold/10 rounded-lg no-underline transition-colors" style="text-decoration:none;"><i class="fas fa-globe-americas mr-2 text-gold/60"></i> Campaigns</a>
            </div>
        </div>
    </div>
</nav>
</template>

<script setup>
import { ref } from 'vue';

const mobileOpen = ref(false);

const openLogin = () => {
    window.location.href = '/laravel-api/auth/google';
};
</script>

<style scoped>
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
    content: ''; position: absolute; bottom: -2px; left: 50%; width: 0; height: 2px;
    background: #c5a059; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    transform: translateX(-50%); border-radius: 2px; box-shadow: 0 0 8px rgba(197, 160, 89, 0.6);
}
.pill-nav-item:hover::after, .pill-nav-item.active::after { width: calc(100% - 1.5rem); }
.pill-navbar-container { transition: box-shadow 0.4s ease, border-color 0.4s ease; font-family: 'Inter', sans-serif; }
.pill-navbar-container:hover { box-shadow: 0 0 25px rgba(197, 160, 89, 0.15); border-color: rgba(197, 160, 89, 0.5); }
.mobile-closed { max-height: 0; opacity: 0; overflow: hidden; transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; }
.mobile-open { max-height: 500px; opacity: 1; transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; }
</style>
