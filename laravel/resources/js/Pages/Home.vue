<template>
    <div>
        <Navbar />

        <!-- Hero Section (same as original) -->
        <div class="hero">
            <div class="hero-content">
                <h1><span id="typing-title" class="typing-text">{{ displayedText }}</span></h1>
                <p>STRATEGIC TACTICAL OPERATIONS &amp; ROLEPLAY MILSIM</p>
                <a href="/register" class="btn">JOIN US NOW!</a>
            </div>
        </div>

        <!-- FAQ Section -->
        <div id="faq" class="section faq-section" style="padding-top: 14rem;">
            <div class="split-layout">
                <div class="split-image">
                    <img src="/assets/images/logo.png" alt="SAC Logo"
                        @error="$event.target.src='https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Seal_of_the_Central_Intelligence_Agency.svg/1200px-Seal_of_the_Central_Intelligence_Agency.svg.png'" />
                </div>
                <div class="split-content">
                    <h2>FAQ</h2>
                    <div class="faq-item">
                        <h3>Who are we?</h3>
                        <p>We are ODA 142, a Special Forces detachment operating under United States Special Operations Command (USSOCOM) Group 12. Our team brings together a broad spectrum of capabilities, represented by the array of unit insignia featured in our emblem. As a MILSIM unit, we faithfully follow the principles, structure, and discipline of real-world Special Forces operations—emphasizing teamwork, precision, and mission effectiveness above all else.</p>
                    </div>
                    <div class="faq-item">
                        <h3>When do we operate?</h3>
                        <p>ODA 142 primarily conducts operations within the Asian region, focusing on time zones and operational windows that best support missions and campaigns in this area. Our activities are scheduled to provide consistent availability for players across Asia while remaining accessible to international members.</p>
                    </div>
                    <div class="faq-item">
                        <h3>What we do</h3>
                        <p>We conduct a wide range of Special Forces–style missions, including unconventional warfare, reconnaissance, foreign internal defense, and direct action. ODA 142 operates independently or with supporting units, allowing us to execute multi-phase MILSIM operations with strong realism, coordination, and immersion.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ground Branch Section -->
        <div class="branch-section text-left" style="background-image: url('/assets/images/ground_branch.jpg'); background-position: left center;">
            <div class="branch-content align-left">
                <h2>Delta Force Special Operations Unit</h2>
                <p>The 1st Special Forces Operational Detachment–Delta (1st SFOD-D), commonly known as Delta Force, is a U.S. Army special operations unit operating under the Joint Special Operations Command (JSOC). Its primary missions include counter-terrorism, hostage rescue, direct action, and special reconnaissance, with an emphasis on high-value and time-sensitive targets.</p>
            </div>
        </div>

        <!-- JTAC Section -->
        <div class="branch-section text-right" style="background-image: url('/assets/images/jtac.jpg'); background-position: right center;">
            <div class="branch-content align-right">
                <h2>JOINT TERMINAL ATTACK CONTROLLER</h2>
                <p>Joint Terminal Attack Controller (JTACs) play a critical role in mission success. They manage both ground-to-air communications and air traffic control, ensuring seamless coordination between ground forces and aircraft. Additionally, they oversee close air support missions, directing precise airstrikes to support troops on the ground. This combination of responsibilities ensures effective air-ground integration and enhances operational efficiency.</p>
            </div>
        </div>

        <!-- Air Branch Section -->
        <div class="branch-section text-left" style="background-image: url('/assets/images/air_branch.jpg'); background-position: left center;">
            <div class="branch-content align-left">
                <h2>Air Operations Special Missions Unit</h2>
                <p>The 160th Special Operations Aviation Regiment (Airborne), known as the 160th SOAR, is the U.S. Army's premier special operations aviation unit. Its highly trained aviators and crews provide precision helicopter support for special operations forces, conducting attack, assault, and reconnaissance missions. Operating primarily at night and under demanding conditions, the unit—nicknamed the "Night Stalkers" and designated as Task Force Brown within JSOC—specializes in high-speed, low-altitude, and time-sensitive operations.</p>
            </div>
        </div>

        <!-- Application Process Section -->
        <div class="section app-process-section">
            <div style="position: relative; z-index: 1;">
                <div style="text-align: center; margin-bottom: 4rem;">
                    <h2 style="font-size: 2.45rem; margin-bottom: 0; color: #ffffff; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; display: inline-block;">Application Process</h2>
                    <div style="width: 30%; height: 3px; background: var(--accent-color); margin: 1rem auto 0;"></div>
                </div>
                <div class="timeline-zigzag" style="max-width: 900px; margin: 0 auto; position: relative;">
                    <!-- Step 1 -->
                    <div style="position: relative; margin-bottom: 3rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="flex-shrink: 0; padding-top: 8px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(102, 126, 234, 0.6), 0 0 40px rgba(118, 75, 162, 0.4), 0 4px 12px rgba(0,0,0,0.3);">📄</div>
                            </div>
                            <div style="flex: 1; padding-top: 5px;">
                                <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">Submit Application</h3>
                                <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">Your application process begins by submitting a written application <a href="/register" style="color: var(--accent-color); text-decoration: none; font-weight: 600;">here</a>. This allows us to ensure that you meet the criteria for our membership.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 2 -->
                    <div style="position: relative; margin-bottom: 3rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem; flex-direction: row-reverse;">
                            <div style="flex-shrink: 0; padding-top: 8px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(240, 147, 251, 0.6), 0 0 40px rgba(245, 87, 108, 0.4), 0 4px 12px rgba(0,0,0,0.3);">💬</div>
                            </div>
                            <div style="flex: 1; padding-top: 5px; text-align: right;">
                                <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">Interview</h3>
                                <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">Once your application is accepted, you will be invited for an interview. The interview will start with questions about your application, followed by more specific inquiries to assess your suitability for the unit.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 3 -->
                    <div style="position: relative; margin-bottom: 3rem;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem;">
                            <div style="flex-shrink: 0; padding-top: 8px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(79, 172, 254, 0.6), 0 0 40px rgba(0, 242, 254, 0.4), 0 4px 12px rgba(0,0,0,0.3);">👨‍🏫</div>
                            </div>
                            <div style="flex: 1; padding-top: 5px;">
                                <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">Officer Training Course</h3>
                                <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">Upon acceptance as a Probationary Officer (PVT), you will enter our Advanced Training Program, a structured course designed to prepare you for operational duty.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Step 4 -->
                    <div style="position: relative;">
                        <div style="display: flex; align-items: flex-start; gap: 1rem; flex-direction: row-reverse;">
                            <div style="flex-shrink: 0; padding-top: 8px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(67, 233, 123, 0.6), 0 0 40px rgba(56, 249, 215, 0.4), 0 4px 12px rgba(0,0,0,0.3);">🏁</div>
                            </div>
                            <div style="flex: 1; padding-top: 5px; text-align: right;">
                                <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">Operational Period</h3>
                                <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">During the Operational Period, you will be assigned to a team and expected to apply the skills, tactical expertise, and leadership foundations gained in training.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Section -->
        <div class="section" style="text-align: center; padding: 6rem 2rem;">
            <h2 style="font-size: 2rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 2px; color: #fff;">WHERE TO FIND US</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">join our discord to get started!</p>
            <div style="display: flex; justify-content: center; gap: 2rem;">
                <a href="https://discord.gg/djtw8g9tDC" target="_blank" style="width: 50px; height: 50px; fill: var(--text-muted); transition: all 0.3s; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" style="width: 40px; height: 40px;"><path d="M524.53 69.84a1.5 1.5 0 0 0 -.76-.4C506.9 56.32 465.24 43.6 422.43 37.49a1.81 1.81 0 0 0 -1.92 .62c-2.17 3.37-9.14 16.6-12.28 23.48-50.83-6.08-100.68-6.08-150.49 0-3.22-6.94-10.21-20.19-12.36-23.48a1.84 1.84 0 0 0 -1.93-.62c-42.81 6.11-84.47 18.83-102.1 32.35a1.56 1.56 0 0 0 -.76 .4C47.85 164.68 22.34 314.73 53.77 410.79a1.81 1.81 0 0 0 1.43 .88c42.69 31.31 93.79 55.8 143.3 68.68a1.81 1.81 0 0 0 2-.73c10.2-13.91 19.09-28.4 26.82-43.4a1.8 1.8 0 0 0 -1-2.46 292.2 292.2 0 0 1 -45.25-21.6 1.85 1.85 0 0 1 -.18-3.12c3.12-2.3 6.15-4.69 9.1-7.13a1.81 1.81 0 0 1 1.9-.27c96.42 43.91 200.41 43.91 295.5 0a1.81 1.81 0 0 1 1.92 .27c3 2.43 6 4.81 9.17 7.13a1.84 1.84 0 0 1 -.19 3.12 291.5 291.5 0 0 1 -45.22 21.6 1.8 1.8 0 0 0 -1 2.46c7.76 15 16.67 29.44 26.87 43.39a1.81 1.81 0 0 0 2 .73c49.48-12.87 100.61-37.37 143.27-68.68a1.81 1.81 0 0 0 1.44-.88c38.26-116.14 13.4-265.78-51.39-339.93z"/></svg>
                </a>
                <a href="https://www.youtube.com/channel/UC-zlNj2GKY46E--L7uP4V6g" target="_blank" style="width: 50px; height: 50px; fill: var(--text-muted); transition: all 0.3s; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" style="width: 40px; height: 40px;"><path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 42.155 48.284 48.477 42.591 11.412 213.371 11.412 213.371 11.412s170.78 0 213.371-11.412c23.497-6.322 42.003-24.827 48.284-48.477 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305z"/></svg>
                </a>
                <a href="https://www.facebook.com/profile.php?id=100086319649339" target="_blank" style="width: 50px; height: 50px; fill: var(--text-muted); transition: all 0.3s; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 40px; height: 40px;"><path d="M512 256C512 114.6 397.4 0 256 0S0 114.6 0 256C0 376 82.7 476.8 194.2 504.5V334.2H141.4V256h52.8V222.3c0-87.1 39.4-127.5 125-127.5c16.2 0 44.2 3.2 55.7 6.4V172c-6-.6-16.5-1-29.6-1c-42 0-58.2 15.9-58.2 57.2V256h83.6l-14.4 78.2H287V512C googler 409.4 512 338.7 512 256z"/></svg>
                </a>
            </div>
        </div>

        <!-- Flashlight Effect Canvas -->
        <canvas ref="flashlightCanvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; mix-blend-mode: screen; overflow: hidden;"></canvas>

        <!-- Footer (same as original) -->
        <footer>
            <p>&copy; 2026 STRATEGIC TACTICAL OPERATIONS &amp; ROLEPLAY MILSIM
                <br><a href="/policies" style="margin-left: 10px;">Privacy Policy</a>
            </p>
        </footer>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import * as THREE from 'three';
import Navbar from '../Components/Navbar.vue';

// Typing animation
const fullText = "SPECIAL ACTIVITIES CENTER";
const displayedText = ref("");
const typeSpeed = 100;
const deleteSpeed = 50;
const pauseAfterType = 7500;
const pauseAfterDelete = 3000;

let typingTimeout = null;

function typeText(i) {
    if (i <= fullText.length) {
        displayedText.value = fullText.substring(0, i);
        typingTimeout = setTimeout(() => typeText(i + 1), typeSpeed);
    } else {
        typingTimeout = setTimeout(() => deleteText(fullText.length), pauseAfterType);
    }
}

function deleteText(i) {
    if (i >= 0) {
        displayedText.value = fullText.substring(0, i);
        typingTimeout = setTimeout(() => deleteText(i - 1), deleteSpeed);
    } else {
        typingTimeout = setTimeout(() => typeText(0), pauseAfterDelete);
    }
}

// Scroll reveal
function handleReveal() {
    document.querySelectorAll('.reveal, .reveal-child').forEach(el => {
        const top = el.getBoundingClientRect().top;
        if (top < window.innerHeight - 100) {
            el.classList.add('active');
        }
    });
}

// Three.js flashlight
const flashlightCanvas = ref(null);
let reqId = null;
let onMouseMove = null;
let onResize = null;

onMounted(() => {
    setTimeout(() => typeText(0), 1600);
    window.addEventListener('scroll', handleReveal);
    handleReveal();

    // Flashlight
    const canvas = flashlightCanvas.value;
    if (!canvas) return;
    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
    renderer.setPixelRatio(window.devicePixelRatio || 1);
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = 100;
    const geometry = new THREE.PlaneGeometry(window.innerWidth * 2, window.innerHeight * 2);
    const material = new THREE.MeshStandardMaterial({ color: 0x000000, roughness: 1, metalness: 0 });
    scene.add(new THREE.Mesh(geometry, material));
    const spotLight = new THREE.SpotLight(0xffe8ba, 2.5);
    spotLight.position.set(0, 0, 80);
    spotLight.angle = Math.PI / 8;
    spotLight.penumbra = 0.8;
    spotLight.decay = 2;
    spotLight.distance = 250;
    scene.add(spotLight);
    scene.add(spotLight.target);
    let targetX = 0, targetY = 0;
    onMouseMove = (e) => {
        const ndcX = ((e.clientX) / window.innerWidth) * 2 - 1;
        const ndcY = -((e.clientY) / window.innerHeight) * 2 + 1;
        const vec = new THREE.Vector3(ndcX, ndcY, 0.5);
        vec.unproject(camera);
        vec.sub(camera.position).normalize();
        const d = -camera.position.z / vec.z;
        const pos = camera.position.clone().add(vec.multiplyScalar(d));
        targetX = pos.x;
        targetY = pos.y;
    };
    onResize = () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight, false);
    };
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('resize', onResize);
    const animate = () => {
        reqId = requestAnimationFrame(animate);
        spotLight.position.x += (targetX - spotLight.position.x) * 0.1;
        spotLight.position.y += (targetY - spotLight.position.y) * 0.1;
        spotLight.target.position.set(spotLight.position.x, spotLight.position.y, 0);
        spotLight.intensity = 2.5 + Math.random() * 0.15;
        renderer.render(scene, camera);
    };
    animate();
});

onUnmounted(() => {
    if (typingTimeout) clearTimeout(typingTimeout);
    window.removeEventListener('scroll', handleReveal);
    if (onMouseMove) window.removeEventListener('mousemove', onMouseMove);
    if (onResize) window.removeEventListener('resize', onResize);
    if (reqId) cancelAnimationFrame(reqId);
});
</script>

<style scoped>
.typing-text {
    display: inline-block;
    border-right: 3px solid var(--accent-color);
    animation: blinkCursor 0.7s step-end infinite;
    white-space: nowrap;
    overflow: hidden;
    min-height: 1.2em;
    text-shadow: 0 0 10px rgba(255,255,255,0.4), 0 0 30px rgba(197,160,89,0.3), 0 0 60px rgba(197,160,89,0.15);
}
@keyframes blinkCursor {
    0%, 100% { border-right-color: var(--accent-color); }
    50% { border-right-color: transparent; }
}
</style>
