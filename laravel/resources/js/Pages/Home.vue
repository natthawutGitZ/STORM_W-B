<template>
    <div class="min-h-screen bg-[#0f0f0f] text-gray-100 overflow-x-hidden relative">
        <Navbar />

        <!-- Hero Section -->
        <main class="relative h-screen flex flex-col items-center justify-center text-center px-4">
            <!-- Background effects -->
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-amber-900/20 via-[#0f0f0f] to-[#0f0f0f] z-0"></div>
            
            <div class="relative z-10 max-w-4xl mx-auto flex flex-col items-center">
                <h1 class="text-6xl md:text-8xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-amber-500 to-amber-700 tracking-tighter mb-6 uppercase drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">
                    Strategic Tactical
                    <span class="block text-4xl md:text-6xl mt-2 tracking-widest text-white">Operations</span>
                </h1>
                
                <p class="text-xl md:text-2xl text-gray-400 mb-10 max-w-2xl font-light">
                    The elite task force. Precision, teamwork, and tactical superiority.
                </p>

                <div class="flex gap-4">
                    <button class="bg-amber-600 hover:bg-amber-500 text-white px-8 py-3 rounded-full font-bold tracking-wide transition-all duration-300 shadow-[0_0_20px_rgba(217,119,6,0.4)] hover:shadow-[0_0_30px_rgba(217,119,6,0.6)] hover:-translate-y-1">
                        JOIN THE RANKS
                    </button>
                    <button class="border border-white/20 hover:bg-white/10 text-white px-8 py-3 rounded-full font-bold tracking-wide transition-all duration-300">
                        VIEW ROSTER
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import Navbar from '../Components/Navbar.vue'
</script>
