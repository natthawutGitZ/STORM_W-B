<template>
    <div class="relative py-24 flex items-center min-h-[50vh] overflow-hidden group">
        <!-- Background Image with Parallax effect -->
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 bg-cover bg-center transition-transform duration-1000 group-hover:scale-105" 
                 :style="{ backgroundImage: `url(${image})`, backgroundPosition: align === 'left' ? 'left center' : 'right center' }">
            </div>
            <!-- Gradients for smooth blending -->
            <div class="absolute inset-0 bg-black/60"></div>
            <div :class="[
                'absolute inset-0',
                align === 'left' ? 'bg-gradient-to-r from-[#0f0f0f] via-[#0f0f0f]/80 to-transparent' : 'bg-gradient-to-l from-[#0f0f0f] via-[#0f0f0f]/80 to-transparent'
            ]"></div>
            <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-[#0f0f0f] to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-[#0f0f0f] to-transparent"></div>
        </div>

        <div class="relative z-10 w-full max-w-7xl mx-auto px-6">
            <div :class="[
                'max-w-2xl',
                align === 'left' ? 'mr-auto' : 'ml-auto text-right'
            ]">
                <h2 class="text-3xl md:text-5xl font-black text-white mb-6 uppercase tracking-wider drop-shadow-[0_2px_10px_rgba(0,0,0,0.8)]">
                    {{ title }}
                </h2>
                <div :class="[
                    'h-1 bg-amber-500 mb-6 w-24',
                    align === 'left' ? 'mr-auto' : 'ml-auto'
                ]"></div>
                <p class="text-lg md:text-xl text-gray-200 leading-relaxed font-light drop-shadow-[0_1px_5px_rgba(0,0,0,0.8)] backdrop-blur-sm bg-black/10 p-4 rounded-xl border border-white/5">
                    {{ description }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },
    description: {
        type: String,
        required: true
    },
    image: {
        type: String,
        required: true
    },
    align: {
        type: String,
        default: 'left',
        validator: (value) => ['left', 'right'].includes(value)
    }
});
</script>
