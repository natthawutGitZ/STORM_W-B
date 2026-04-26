<template>
    <nav class="fixed top-4 left-1/2 -translate-x-1/2 bg-[#1a1a20]/90 backdrop-blur-md border border-white/10 rounded-full px-6 py-3 flex items-center gap-6 z-50 shadow-2xl transition-all duration-300">
        <div class="text-xl font-bold text-white tracking-widest uppercase cursor-pointer" @click="navigate('/')">
            STORM
        </div>
        
        <div class="h-6 w-px bg-white/20"></div>

        <div class="flex items-center gap-4">
            <button @click="navigate('/')" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Home</button>
            <button @click="navigate('/ranks')" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Ranks</button>
            <button @click="navigate('/awards')" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Awards</button>
            <button @click="navigate('/campaigns')" class="text-gray-300 hover:text-white transition-colors text-sm font-medium">Campaigns</button>
        </div>

        <div class="h-6 w-px bg-white/20"></div>

        <div class="flex items-center gap-4">
            <button @click="loginWithGoogle" class="bg-amber-500 hover:bg-amber-400 text-black px-4 py-1.5 rounded-full text-sm font-semibold transition-colors flex items-center gap-2">
                <i class="fa-brands fa-google"></i> Login
            </button>
        </div>
    </nav>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const navigate = (path) => {
    router.visit(path)
}

const loginWithGoogle = () => {
    window.location.href = '/laravel-api/auth/google'
}
</script>
