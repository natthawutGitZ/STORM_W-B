<template>
    <section class="relative h-screen flex flex-col items-center justify-center text-center px-4 overflow-hidden">
        <!-- Background effects -->
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-amber-900/20 via-[#0f0f0f] to-[#0f0f0f] z-0"></div>
        
        <div class="relative z-10 max-w-5xl mx-auto flex flex-col items-center">
            <h1 class="text-5xl md:text-7xl lg:text-8xl font-black tracking-tighter mb-4 uppercase drop-shadow-[0_0_15px_rgba(245,158,11,0.3)]">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-amber-500 to-amber-700">
                    {{ displayedText }}<span class="animate-pulse">|</span>
                </span>
                <span class="block text-3xl md:text-5xl lg:text-6xl mt-4 tracking-widest text-white">
                    Strategic Tactical Operations
                    <span class="block text-xl md:text-3xl mt-2 text-gray-400 font-light">& Roleplay Milsim</span>
                </span>
            </h1>
            
            <p class="text-lg md:text-xl text-gray-400 mb-10 max-w-2xl font-light">
                The elite task force. Precision, teamwork, and tactical superiority.
            </p>

            <div class="flex flex-wrap justify-center gap-4">
                <a href="/register" class="bg-amber-600 hover:bg-amber-500 text-white px-8 py-3 rounded-full font-bold tracking-wide transition-all duration-300 shadow-[0_0_20px_rgba(217,119,6,0.4)] hover:shadow-[0_0_30px_rgba(217,119,6,0.6)] hover:-translate-y-1">
                    JOIN US NOW!
                </a>
                <button class="border border-white/20 hover:bg-white/10 text-white px-8 py-3 rounded-full font-bold tracking-wide transition-all duration-300 backdrop-blur-sm hover:-translate-y-1">
                    VIEW ROSTER
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const fullText = "SPECIAL ACTIVITIES CENTER";
const displayedText = ref("");
const typingSpeed = 100;
const deletingSpeed = 50;
const pauseTime = 3000;
let isDeleting = false;

const typeEffect = () => {
    const currentLength = displayedText.value.length;
    
    if (!isDeleting && currentLength < fullText.length) {
        displayedText.value = fullText.substring(0, currentLength + 1);
        setTimeout(typeEffect, typingSpeed);
    } else if (isDeleting && currentLength > 0) {
        displayedText.value = fullText.substring(0, currentLength - 1);
        setTimeout(typeEffect, deletingSpeed);
    } else {
        isDeleting = !isDeleting;
        setTimeout(typeEffect, pauseTime);
    }
};

onMounted(() => {
    setTimeout(typeEffect, 1000);
});
</script>
