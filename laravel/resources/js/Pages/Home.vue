<template>
    <div class="min-h-screen bg-[#0f0f0f] text-gray-100 overflow-x-hidden relative selection:bg-amber-500/30">
        <!-- Flashlight Canvas Effect -->
        <canvas ref="flashlightCanvas" class="fixed inset-0 w-full h-full pointer-events-none z-0 mix-blend-screen opacity-50"></canvas>

        <!-- Navbar (From Previous Setup) -->
        <Navbar class="relative z-50" />

        <!-- Main Content -->
        <main class="relative z-10">
            <HeroSection />
            <FaqSection />
            
            <BranchSection 
                title="Delta Force Special Operations Unit"
                description="The 1st Special Forces Operational Detachment–Delta (1st SFOD-D), commonly known as Delta Force, is a U.S. Army special operations unit operating under the Joint Special Operations Command (JSOC). Its primary missions include counter-terrorism, hostage rescue, direct action, and special reconnaissance, with an emphasis on high-value and time-sensitive targets."
                image="/assets/images/ground_branch.jpg"
                align="left"
            />
            
            <BranchSection 
                title="JOINT TERMINAL ATTACK CONTROLLER"
                description="Joint Terminal Attack Controller (JTACs) play a critical role in mission success. They manage both ground-to-air communications and air traffic control, ensuring seamless coordination between ground forces and aircraft. Additionally, they oversee close air support missions, directing precise airstrikes to support troops on the ground. This combination of responsibilities ensures effective air-ground integration and enhances operational efficiency."
                image="/assets/images/jtac.jpg"
                align="right"
            />
            
            <BranchSection 
                title="Air Operations Special Missions Unit"
                description="The 160th Special Operations Aviation Regiment (Airborne), known as the 160th SOAR, is the U.S. Army's premier special operations aviation unit. Its highly trained aviators and crews provide precision helicopter support for special operations forces, conducting attack, assault, and reconnaissance missions. Operating primarily at night and under demanding conditions, the unit—nicknamed the “Night Stalkers” and designated as Task Force Brown within JSOC—specializes in high-speed, low-altitude, and time-sensitive operations."
                image="/assets/images/air_branch.jpg"
                align="left"
            />

            <ApplicationProcess />
            
            <SocialSection />
        </main>
        
        <!-- Footer -->
        <footer class="relative z-10 border-t border-white/10 bg-[#0a0a0a] py-8 text-center text-gray-500">
            <p>&copy; 2026 Strategic Tactical Operations & Roleplay Milsim. All rights reserved.</p>
        </footer>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import * as THREE from 'three';

// Components
import Navbar from '../Components/Navbar.vue';
import HeroSection from '../Components/Home/HeroSection.vue';
import FaqSection from '../Components/Home/FaqSection.vue';
import BranchSection from '../Components/Home/BranchSection.vue';
import ApplicationProcess from '../Components/Home/ApplicationProcess.vue';
import SocialSection from '../Components/Home/SocialSection.vue';

const flashlightCanvas = ref(null);
let reqAnimationFrameId = null;

onMounted(() => {
    // Implement Three.js Flashlight Effect
    if (!flashlightCanvas.value) return;

    const canvas = flashlightCanvas.value;
    const renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight, false);
    renderer.setPixelRatio(window.devicePixelRatio || 1);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = 100;

    const geometry = new THREE.PlaneGeometry(window.innerWidth * 2, window.innerHeight * 2);
    const material = new THREE.MeshStandardMaterial({ 
        color: 0x000000, 
        roughness: 1, 
        metalness: 0 
    });
    const plane = new THREE.Mesh(geometry, material);
    scene.add(plane);

    const spotLight = new THREE.SpotLight(0xffe8ba, 2.5);
    spotLight.position.set(0, 0, 80);
    spotLight.angle = Math.PI / 8;
    spotLight.penumbra = 0.8;
    spotLight.decay = 2;
    spotLight.distance = 250;
    
    scene.add(spotLight);
    scene.add(spotLight.target);

    let targetX = 0;
    let targetY = 0;
    
    const onMouseMove = (e) => {
        const ndcX = (e.clientX / window.innerWidth) * 2 - 1;
        const ndcY = -(e.clientY / window.innerHeight) * 2 + 1;
        const vec = new THREE.Vector3(ndcX, ndcY, 0.5);
        vec.unproject(camera);
        vec.sub(camera.position).normalize();
        const distance = -camera.position.z / vec.z;
        const pos = camera.position.clone().add(vec.multiplyScalar(distance));
        
        targetX = pos.x;
        targetY = pos.y;
    };

    const onResize = () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight, false);
    };

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('resize', onResize);

    const animate = () => {
        reqAnimationFrameId = requestAnimationFrame(animate);

        spotLight.position.x += (targetX - spotLight.position.x) * 0.1;
        spotLight.position.y += (targetY - spotLight.position.y) * 0.1;
        spotLight.target.position.set(spotLight.position.x, spotLight.position.y, 0);
        
        spotLight.intensity = 2.5 + Math.random() * 0.15;

        renderer.render(scene, camera);
    };
    
    animate();

    // Cleanup
    onUnmounted(() => {
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('resize', onResize);
        if (reqAnimationFrameId) {
            cancelAnimationFrame(reqAnimationFrameId);
        }
        renderer.dispose();
    });
});
</script>
