<template>
    <section class="py-24 relative overflow-hidden bg-black/40 border-y border-white/5">
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-5xl font-black text-white mb-4 uppercase tracking-widest">
                    Application Process
                </h2>
                <div class="w-24 h-1 bg-gradient-to-r from-amber-400 to-amber-600 mx-auto rounded-full"></div>
            </div>

            <div class="relative max-w-4xl mx-auto">
                <!-- Vertical Line -->
                <div class="absolute left-8 md:left-1/2 top-0 bottom-0 w-px bg-gradient-to-b from-transparent via-amber-500/30 to-transparent md:-translate-x-1/2"></div>

                <div class="space-y-12">
                    <!-- Step 1 -->
                    <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between group">
                        <div class="order-2 md:order-1 md:w-5/12 pl-24 md:pl-0 md:text-right md:pr-12">
                            <h3 class="text-2xl font-bold text-white mb-3">Submit Application</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">
                                Your application process begins by submitting a written application <a href="/register" class="text-amber-500 hover:text-amber-400 font-semibold transition-colors">here</a>. This allows us to ensure that you meet the criteria for our membership.
                            </p>
                        </div>
                        <div class="absolute left-0 md:left-1/2 top-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 p-1 shadow-[0_0_20px_rgba(99,102,241,0.4)] group-hover:shadow-[0_0_30px_rgba(99,102,241,0.6)] transition-shadow duration-300 z-10">
                            <div class="w-full h-full bg-[#0f0f0f] rounded-full flex items-center justify-center text-2xl">
                                📄
                            </div>
                        </div>
                        <div class="order-1 md:order-3 md:w-5/12"></div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between group">
                        <div class="order-1 md:order-1 md:w-5/12"></div>
                        <div class="absolute left-0 md:left-1/2 top-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-16 h-16 rounded-full bg-gradient-to-br from-pink-500 to-rose-500 p-1 shadow-[0_0_20px_rgba(236,72,153,0.4)] group-hover:shadow-[0_0_30px_rgba(236,72,153,0.6)] transition-shadow duration-300 z-10">
                            <div class="w-full h-full bg-[#0f0f0f] rounded-full flex items-center justify-center text-2xl">
                                💬
                            </div>
                        </div>
                        <div class="order-2 md:order-3 md:w-5/12 pl-24 md:pl-12">
                            <h3 class="text-2xl font-bold text-white mb-3">Interview</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">
                                Once your application is accepted, you will be invited for an interview. The interview will start with questions about your application, followed by more specific inquiries to assess your suitability for the unit.
                            </p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between group">
                        <div class="order-2 md:order-1 md:w-5/12 pl-24 md:pl-0 md:text-right md:pr-12">
                            <h3 class="text-2xl font-bold text-white mb-3">Officer Training Course</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">
                                Upon acceptance as a Probationary Officer (PVT), you will enter our Advanced Training Program, a structured course designed to prepare you for operational duty. The program consists of multiple phases that develop core competencies, tactical proficiency, and leadership fundamentals required for service within our unit.
                            </p>
                        </div>
                        <div class="absolute left-0 md:left-1/2 top-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-16 h-16 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500 p-1 shadow-[0_0_20px_rgba(34,211,238,0.4)] group-hover:shadow-[0_0_30px_rgba(34,211,238,0.6)] transition-shadow duration-300 z-10">
                            <div class="w-full h-full bg-[#0f0f0f] rounded-full flex items-center justify-center text-2xl">
                                👨‍🏫
                            </div>
                        </div>
                        <div class="order-1 md:order-3 md:w-5/12"></div>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between group">
                        <div class="order-1 md:order-1 md:w-5/12"></div>
                        <div class="absolute left-0 md:left-1/2 top-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 w-16 h-16 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 p-1 shadow-[0_0_20px_rgba(52,211,153,0.4)] group-hover:shadow-[0_0_30px_rgba(52,211,153,0.6)] transition-shadow duration-300 z-10">
                            <div class="w-full h-full bg-[#0f0f0f] rounded-full flex items-center justify-center text-2xl">
                                🏁
                            </div>
                        </div>
                        <div class="order-2 md:order-3 md:w-5/12 pl-24 md:pl-12">
                            <h3 class="text-2xl font-bold text-white mb-3">Operational Period</h3>
                            <p class="text-gray-400 text-sm leading-relaxed">
                                During the Operational Period, you will be assigned to a team and expected to apply the skills, tactical expertise, and leadership foundations gained in training. You will adapt these competencies to real operational duties within our unit, while your performance, decision-making, and effectiveness in the field are continuously evaluated.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
