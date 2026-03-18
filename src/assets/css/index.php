<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Home');

// Redirect logged-in users
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard');
    } else {
        redirect('profile');
    }
}

include ROOT_PATH . '/includes/header.php';
?>

<style>
    /* Typing Animation */
    .typing-text {
        display: inline-block;
        border-right: 3px solid var(--accent-color);
        animation: blinkCursor 0.7s step-end infinite;
        white-space: nowrap;
        overflow: hidden;
        min-height: 1.2em;
        text-shadow: 0 0 10px rgba(255, 255, 255, 0.4),
            0 0 30px rgba(197, 160, 89, 0.3),
            0 0 60px rgba(197, 160, 89, 0.15);
    }

    .typing-text.done {
        border-right-color: transparent;
        animation: none;
    }

    @keyframes blinkCursor {

        0%,
        100% {
            border-right-color: var(--accent-color);
        }

        50% {
            border-right-color: transparent;
        }
    }

    /* Branch Sections Enhanced Styling */
    .branch-section {
        position: relative;
        padding: 5rem 2rem;
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        min-height: 40vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: visible;
        z-index: 1;
    }

    .branch-section::before {
        content: '';
        position: absolute;
        top: -10%;
        width: 100%;
        height: 120%;
        background: linear-gradient(to right, rgba(0, 0, 0, 0.95), transparent);
        z-index: -1;
        pointer-events: none;
    }

    .branch-section.text-right::before {
        right: 0;
        left: auto;
        background: linear-gradient(to left, rgba(0, 0, 0, 0.95), transparent);
    }

    .branch-section.text-left::before {
        left: 0;
        right: auto;
        background: linear-gradient(to right, rgba(0, 0, 0, 0.95), transparent);
    }

    .branch-content {
        position: relative;
        z-index: 2;
        max-width: 60%;
        padding: 0;
        background: none;
        border: none;
    }

    .branch-content h2 {
        font-size: 2.2rem;
        margin-bottom: 1.5rem;
        color: #ffffff;
        font-weight: 800;
        letter-spacing: 3px;
        text-transform: uppercase;
        line-height: 1.2;
        text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.8);
    }

    .branch-content p {
        font-size: 1rem;
        line-height: 1.8;
        color: #f0f0f0;
        text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.7);
    }

    .branch-content.align-left {
        margin-left: 5%;
        margin-right: auto;
        text-align: left;
    }

    .branch-content.align-right {
        margin-left: auto;
        margin-right: 2%;
        text-align: left;
    }

    .app-process-section {
        background: #0a0a0a;
        padding: 6rem 15vw !important;
        width: 130vw !important;
        max-width: 130vw !important;
        position: relative;
        left: 50%;
        margin-left: -65vw !important;
        box-sizing: border-box;
        overflow: hidden;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .branch-section::before {
            width: 100%;
            height: 100%;
            top: 0;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.9));
        }

        .branch-section.text-left::before,
        .branch-section.text-right::before {
            left: 0;
            right: auto;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.9));
        }

        .branch-content {
            max-width: 100%;
            padding: 2rem 1rem;
            margin: 0 auto !important;
            text-align: center !important;
        }

        .branch-content h2 {
            font-size: 1.5rem;
            line-height: 1.3;
            word-wrap: break-word;
            hyphens: auto;
        }

        .branch-content p {
            font-size: 0.95rem;
            text-align: left;
        }

        .branch-section {
            min-height: auto;
            padding: 4rem 1rem !important;
            background-attachment: scroll;
            width: 100vw !important;
            max-width: 100vw !important;
            left: 50% !important;
            margin-left: -50vw !important;
        }

        .app-process-section {
            padding: 4rem 1rem !important;
            width: 100vw !important;
            max-width: 100vw !important;
            left: 50% !important;
            margin-left: -50vw !important;
        }

        /* Timeline Mobile Adjustments */
        .timeline-zigzag>div>div {
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
        }

        .timeline-zigzag>div>div>div:first-child {
            padding-top: 0 !important;
            margin-bottom: 1.5rem;
        }

        .timeline-zigzag>div>div>div:last-child {
            text-align: center !important;
            padding-top: 0 !important;
            width: 100%;
        }
    }
</style>

<!-- Hero Section -->
<div class="hero">
    <div class="hero-content">
        <h1><span id="typing-title" class="typing-text"></span></h1>
        <p>STRATEGIC TACTICAL OPERATIONS & ROLEPLAY MILSIM</p>
        <a href="register" class="btn">JOIN US NOW!</a>
    </div>
</div>

<script>
    (function () {
        const text = 'SPECIAL ACTIVITIES CENTER';
        const el = document.getElementById('typing-title');
        const typeSpeed = 100;
        const deleteSpeed = 50;
        const pauseAfterType = 7500;
        const pauseAfterDelete = 3000;

        function typeText(i) {
            if (i <= text.length) {
                el.textContent = text.substring(0, i);
                setTimeout(() => typeText(i + 1), typeSpeed);
            } else {
                // Pause then start deleting
                setTimeout(() => deleteText(text.length), pauseAfterType);
            }
        }

        function deleteText(i) {
            if (i >= 0) {
                el.textContent = text.substring(0, i);
                setTimeout(() => deleteText(i - 1), deleteSpeed);
            } else {
                // Pause then start typing again
                setTimeout(() => typeText(0), pauseAfterDelete);
            }
        }

        // Start after hero animation
        setTimeout(() => typeText(0), 1600);
    })();
</script>

<!-- FAQ Section -->
<div id="faq" class="section faq-section reveal reveal-left" style="padding-top: 14rem;">
    <div class="split-layout">
        <div class="split-image reveal-child">
            <img src="assets/images/logo.png" alt="SAC Logo"
                onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/2/25/Seal_of_the_Central_Intelligence_Agency.svg/1200px-Seal_of_the_Central_Intelligence_Agency.svg.png'">
        </div>
        <div class="split-content">
            <h2>FAQ</h2>

            <div class="faq-item reveal-child">
                <h3>Who are we?</h3>
                <p>We are ODA 142, a Special Forces detachment operating under United States Special Operations Command
                    (USSOCOM) Group 12. Our team brings together a broad spectrum of capabilities, represented by the
                    array of unit insignia featured in our emblem. As a MILSIM unit, we faithfully follow the
                    principles, structure, and discipline of real-world Special Forces operations—emphasizing teamwork,
                    precision, and mission effectiveness above all else.</p>
            </div>

            <div class="faq-item reveal-child">
                <h3>When do we operate?</h3>
                <p>ODA 142 primarily conducts operations within the Asian region, focusing on time zones and operational
                    windows that best support missions and campaigns in this area. Our activities are scheduled to
                    provide consistent availability for players across Asia while remaining accessible to international
                    members.</p>
            </div>

            <div class="faq-item reveal-child">
                <h3>What we do</h3>
                <p>We conduct a wide range of Special Forces–style missions, including unconventional warfare,
                    reconnaissance, foreign internal defense, and direct action. ODA 142 operates independently or with
                    supporting units, allowing us to execute multi-phase MILSIM operations with strong realism,
                    coordination, and immersion.</p>
            </div>
        </div>
    </div>
</div>

<!-- Ground Branch Section -->
<div class="branch-section text-left reveal reveal-left"
    style="background-image: url('assets/images/ground_branch.jpg'); background-position: left center;">
    <div class="branch-content align-left">
        <h2>Delta Force Special Operations Unit</h2>
        <p>The 1st Special Forces Operational Detachment–Delta (1st SFOD-D), commonly known as Delta Force, is a U.S.
            Army special operations unit operating under the Joint Special Operations Command (JSOC). Its primary
            missions include counter-terrorism, hostage rescue, direct action, and special reconnaissance, with an
            emphasis on high-value and time-sensitive targets.</p>
    </div>
</div>

<!-- JTAC Section -->
<div class="branch-section text-right reveal reveal-right"
    style="background-image: url('assets/images/jtac.jpg'); background-position: right center;">
    <div class="branch-content align-right">
        <h2>JOINT TERMINAL ATTACK CONTROLLER</h2>
        <p>Joint Terminal Attack Controller (JTACs) play a critical role in mission success. They manage both
            ground-to-air communications and air traffic control, ensuring seamless coordination between ground forces
            and aircraft. Additionally, they oversee close air support missions, directing precise airstrikes to support
            troops on the ground. This combination of responsibilities ensures effective air-ground integration and
            enhances operational efficiency.</p>
    </div>
</div>

<!-- Air Branch Section -->
<div class="branch-section text-left reveal reveal-left"
    style="background-image: url('assets/images/air_branch.jpg'); background-position: left center;">
    <div class="branch-content align-left">
        <h2>Air Operations Special Missions Unit</h2>
        <p>The 160th Special Operations Aviation Regiment (Airborne), known as the 160th SOAR, is the U.S. Army’s
            premier
            special operations aviation unit. Its highly trained aviators and crews provide precision helicopter support
            for
            special operations forces, conducting attack, assault, and reconnaissance missions. Operating primarily at
            night
            and under demanding conditions, the unit—nicknamed the “Night Stalkers” and designated as Task Force Brown
            within
            JSOC—specializes in high-speed, low-altitude, and time-sensitive operations.</p>
    </div>
</div>

<!-- Application Process Section -->
<div class="section app-process-section reveal reveal-scale">
    <div style="position: relative; z-index: 1;">
        <!-- Title with underline -->
        <div style="text-align: center; margin-bottom: 4rem;">
            <h2
                style="font-size: 2.45rem; margin-bottom: 0; color: #ffffff; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; display: inline-block;">
                Application Process</h2>
            <div style="width: 30%; height: 3px; background: var(--accent-color); margin: 1rem auto 0;"></div>
        </div>

        <div class="timeline-zigzag" style="max-width: 900px; margin: 0 auto; position: relative;">
            <!-- Step 1 - Left side -->
            <div style="position: relative; margin-bottom: 3rem;">
                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <!-- Icon -->
                    <div style="flex-shrink: 0; padding-top: 8px;">
                        <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(102, 126, 234, 0.6), 0 0 40px rgba(118, 75, 162, 0.4), 0 4px 12px rgba(0,0,0,0.3); transition: transform 0.3s ease, box-shadow 0.3s ease;"
                            onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 0 30px rgba(102, 126, 234, 0.8), 0 0 60px rgba(118, 75, 162, 0.6), 0 6px 16px rgba(0,0,0,0.4)';"
                            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 0 20px rgba(102, 126, 234, 0.6), 0 0 40px rgba(118, 75, 162, 0.4), 0 4px 12px rgba(0,0,0,0.3)';">
                            📄</div>
                    </div>
                    <!-- Content -->
                    <div style="flex: 1; padding-top: 5px;">
                        <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">Submit
                            Application</h3>
                        <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">Your application
                            process begins by submitting a written application <a href="register"
                                style="color: var(--accent-color); text-decoration: none; font-weight: 600;">here</a>.
                            This allows us to ensure that you meet the criteria for our membership.</p>
                    </div>
                </div>
            </div>

            <!-- Step 2 - Right side -->
            <div style="position: relative; margin-bottom: 3rem;">
                <div style="display: flex; align-items: flex-start; gap: 1rem; flex-direction: row-reverse;">
                    <!-- Icon -->
                    <div style="flex-shrink: 0; padding-top: 8px;">
                        <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(240, 147, 251, 0.6), 0 0 40px rgba(245, 87, 108, 0.4), 0 4px 12px rgba(0,0,0,0.3); transition: transform 0.3s ease, box-shadow 0.3s ease;"
                            onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 0 30px rgba(240, 147, 251, 0.8), 0 0 60px rgba(245, 87, 108, 0.6), 0 6px 16px rgba(0,0,0,0.4)';"
                            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 0 20px rgba(240, 147, 251, 0.6), 0 0 40px rgba(245, 87, 108, 0.4), 0 4px 12px rgba(0,0,0,0.3)';">
                            💬</div>
                    </div>
                    <!-- Content -->
                    <div style="flex: 1; padding-top: 5px; text-align: right;">
                        <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">
                            Interview</h3>
                        <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">Once your application
                            is accepted, you will be invited for an interview. The interview will start with questions
                            about your application, followed by more specific inquiries to assess your suitability for
                            the unit.</p>
                    </div>
                </div>
            </div>

            <!-- Step 3 - Left side -->
            <div style="position: relative; margin-bottom: 3rem;">
                <div style="display: flex; align-items: flex-start; gap: 1rem;">
                    <!-- Icon -->
                    <div style="flex-shrink: 0; padding-top: 8px;">
                        <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(79, 172, 254, 0.6), 0 0 40px rgba(0, 242, 254, 0.4), 0 4px 12px rgba(0,0,0,0.3); transition: transform 0.3s ease, box-shadow 0.3s ease;"
                            onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 0 30px rgba(79, 172, 254, 0.8), 0 0 60px rgba(0, 242, 254, 0.6), 0 6px 16px rgba(0,0,0,0.4)';"
                            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 0 20px rgba(79, 172, 254, 0.6), 0 0 40px rgba(0, 242, 254, 0.4), 0 4px 12px rgba(0,0,0,0.3)';">
                            👨‍🏫</div>
                    </div>
                    <!-- Content -->
                    <div style="flex: 1; padding-top: 5px;">
                        <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">Officer
                            Training Course</h3>
                        <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">Upon acceptance as a
                            Probationary Officer (PVT), you will enter our Advanced Training Program, a structured
                            course designed to prepare you for operational duty. The program consists of multiple phases
                            that develop core competencies, tactical proficiency, and leadership fundamentals required
                            for service within our unit.</p>
                    </div>
                </div>
            </div>

            <!-- Step 4 - Right side -->
            <div style="position: relative;">
                <div style="display: flex; align-items: flex-start; gap: 1rem; flex-direction: row-reverse;">
                    <!-- Icon -->
                    <div style="flex-shrink: 0; padding-top: 8px;">
                        <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 0 20px rgba(67, 233, 123, 0.6), 0 0 40px rgba(56, 249, 215, 0.4), 0 4px 12px rgba(0,0,0,0.3); transition: transform 0.3s ease, box-shadow 0.3s ease;"
                            onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 0 30px rgba(67, 233, 123, 0.8), 0 0 60px rgba(56, 249, 215, 0.6), 0 6px 16px rgba(0,0,0,0.4)';"
                            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 0 20px rgba(67, 233, 123, 0.6), 0 0 40px rgba(56, 249, 215, 0.4), 0 4px 12px rgba(0,0,0,0.3)';">
                            🏁</div>
                    </div>
                    <!-- Content -->
                    <div style="flex: 1; padding-top: 5px; text-align: right;">
                        <h3 style="font-size: 1.4rem; color: #ffffff; margin-bottom: 0.5rem; font-weight: 700;">
                            Operational Period</h3>
                        <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem; margin: 0;">During the Operational
                            Period, you will be assigned to a team and expected to apply the skills, tactical expertise,
                            and leadership foundations gained in training. You will adapt these competencies to real
                            operational duties within our unit, while your performance, decision-making, and
                            effectiveness in the field are continuously evaluated.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Social Section -->
<div class="social-section reveal reveal-scale">
    <div class="social-content">
        <h2>WHERE TO FIND US</h2>
        <p>join our disord to get started!</p>
        <div class="social-links">
            <!-- Discord -->
            <a href="https://discord.gg/djtw8g9tDC" class="social-icon" target="_blank" aria-label="Discord">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                    <!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                    <path
                        d="M524.53 69.84a1.5 1.5 0 0 0 -.76-.4C506.9 56.32 465.24 43.6 422.43 37.49a1.81 1.81 0 0 0 -1.92 .62c-2.17 3.37-9.14 16.6-12.28 23.48-50.83-6.08-100.68-6.08-150.49 0-3.22-6.94-10.21-20.19-12.36-23.48a1.84 1.84 0 0 0 -1.93-.62c-42.81 6.11-84.47 18.83-102.1 32.35a1.56 1.56 0 0 0 -.76 .4C47.85 164.68 22.34 314.73 53.77 410.79a1.81 1.81 0 0 0 1.43 .88c42.69 31.31 93.79 55.8 143.3 68.68a1.81 1.81 0 0 0 2-.73c10.2-13.91 19.09-28.4 26.82-43.4a1.8 1.8 0 0 0 -1-2.46 292.2 292.2 0 0 1 -45.25-21.6 1.85 1.85 0 0 1 -.18-3.12c3.12-2.3 6.15-4.69 9.1-7.13a1.81 1.81 0 0 1 1.9-.27c96.42 43.91 200.41 43.91 295.5 0a1.81 1.81 0 0 1 1.92 .27c3 2.43 6 4.81 9.17 7.13a1.84 1.84 0 0 1 -.19 3.12 291.5 291.5 0 0 1 -45.22 21.6 1.8 1.8 0 0 0 -1 2.46c7.76 15 16.67 29.44 26.87 43.39a1.81 1.81 0 0 0 2 .73c49.48-12.87 100.61-37.37 143.27-68.68a1.81 1.81 0 0 0 1.44-.88c38.26-116.14 13.4-265.78-51.39-339.93zm-288.48 271.74c-35.36 0-64.22-32.47-64.22-72.34 0-39.88 28.57-72.35 64.22-72.35 35.93 0 64.59 32.78 63.78 72.35 0 39.87-28.15 72.34-63.78 72.34zm160.06 0c-35.36 0-64.22-32.47-64.22-72.34 0-39.88 28.57-72.35 64.22-72.35 35.93 0 64.59 32.78 63.78 72.35 0 39.87-28.15 72.34-63.78 72.34z" />
                </svg>
            </a>
            <!-- YouTube -->
            <a href="https://www.youtube.com/channel/UC-zlNj2GKY46E--L7uP4V6g" class="social-icon" target="_blank"
                aria-label="YouTube">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512">
                    <!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                    <path
                        d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 42.155 48.284 48.477 42.591 11.412 213.371 11.412 213.371 11.412s170.78 0 213.371-11.412c23.497-6.322 42.003-24.827 48.284-48.477 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z" />
                </svg>
            </a>
            <!-- Facebook -->
            <a href="https://www.facebook.com/profile.php?id=100086319649339" class="social-icon" target="_blank"
                aria-label="Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                    <path
                        d="M352 256c0 22.2-1.2 43.6-3.3 64H163.3c-2.2-20.4-3.3-41.8-3.3-64s1.2-43.6 3.3-64H348.7c2.2 20.4 3.3 41.8 3.3 64zm28.8-64H503.9c5.3 20.5 8.1 41.9 8.1 64s-2.8 43.5-8.1 64H380.8c2.1-20.6 3.2-42 3.2-64s-1.1-43.4-3.2-64zm112.6-32H376.7c-10-63.9-29.8-117.4-55.3-151.6c78.3 20.7 142 77.5 171.9 151.6zm-149.1 0H167.7c6.1-36.4 15.5-68.6 27-94.7c10.5-23.6 22.2-40.7 33.5-51.5C239.4 3.2 248.7 0 256 0s16.6 3.2 27.8 13.8c11.3 10.8 23 27.9 33.5 51.5c11.6 26 20.9 58.2 27 94.7zm-209 0H18.6c30-74.1 93.6-130.9 172-151.6c-25.5 34.2-45.2 87.7-55.3 151.6zM8.1 192H131.2c-2.1 20.6-3.2 42-3.2 64s1.1 43.4 3.2 64H8.1C2.8 299.5 0 278.1 0 256s2.8-43.5 8.1-64zM194.7 446.6c-11.6-26-20.9-58.2-27-94.6H344.3c-6.1 36.4-15.5 68.6-27 94.6c-10.5 23.6-22.2 40.7-33.5 51.5C239.4 508.8 263.3 512 256 512s-16.6-3.2-27.8-13.8c-11.3-10.8-23-27.9-33.5-51.5zM478.7 352H376.7c10 63.9 29.8 117.4 55.3 151.6c-78.3-20.7-142-77.5-171.9-151.6zm-344.4 0H18.6c29.9 74.1 93.6 130.9 171.9 151.6c-25.5-34.2-45.2-87.7-55.3-151.6z" />
                </svg>
            </a>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
