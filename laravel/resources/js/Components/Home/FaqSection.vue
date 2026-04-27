<template>
    <section class="py-24 relative z-10 px-6 max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <!-- Image Side -->
            <div class="relative group">
                <div class="absolute inset-0 bg-amber-500/20 blur-3xl rounded-full group-hover:bg-amber-500/30 transition-all duration-500"></div>
                <img src="/assets/images/logo.png" 
                     alt="SAC Logo" 
                     class="relative z-10 w-3/4 mx-auto drop-shadow-[0_0_30px_rgba(245,158,11,0.2)] transition-transform duration-500 group-hover:scale-105"
                     @error="$event.target.src='https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Seal_of_the_Central_Intelligence_Agency.svg/1200px-Seal_of_the_Central_Intelligence_Agency.svg.png'" />
            </div>

            <!-- Content Side -->
            <div class="space-y-10">
                <div>
                    <h2 class="text-4xl md:text-5xl font-bold text-white mb-2 tracking-tight">Frequently Asked</h2>
                    <h3 class="text-3xl md:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-600 uppercase tracking-widest">Questions</h3>
                </div>

                <div class="space-y-8">
                    <!-- FAQ Item -->
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-md hover:bg-white/10 transition-colors duration-300">
                        <h4 class="text-xl font-bold text-white mb-3 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-500 flex items-center justify-center text-sm">01</span>
                            Who are we?
                        </h4>
                        <p class="text-gray-400 leading-relaxed text-sm md:text-base">
                            We are ODA 142, a Special Forces detachment operating under United States Special Operations Command (USSOCOM) Group 12. Our team brings together a broad spectrum of capabilities, represented by the array of unit insignia featured in our emblem. As a MILSIM unit, we faithfully follow the principles, structure, and discipline of real-world Special Forces operations—emphasizing teamwork, precision, and mission effectiveness above all else.
                        </p>
                    </div>

                    <!-- FAQ Item -->
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-md hover:bg-white/10 transition-colors duration-300">
                        <h4 class="text-xl font-bold text-white mb-3 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-500 flex items-center justify-center text-sm">02</span>
                            When do we operate?
                        </h4>
                        <p class="text-gray-400 leading-relaxed text-sm md:text-base">
                            ODA 142 primarily conducts operations within the Asian region, focusing on time zones and operational windows that best support missions and campaigns in this area. Our activities are scheduled to provide consistent availability for players across Asia while remaining accessible to international members.
                        </p>
                    </div>

                    <!-- FAQ Item -->
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-md hover:bg-white/10 transition-colors duration-300">
                        <h4 class="text-xl font-bold text-white mb-3 flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-500 flex items-center justify-center text-sm">03</span>
                            What we do
                        </h4>
                        <p class="text-gray-400 leading-relaxed text-sm md:text-base">
                            We conduct a wide range of Special Forces–style missions, including unconventional warfare, reconnaissance, foreign internal defense, and direct action. ODA 142 operates independently or with supporting units, allowing us to execute multi-phase MILSIM operations with strong realism, coordination, and immersion.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
