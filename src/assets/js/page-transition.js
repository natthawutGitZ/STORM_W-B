/**
 * Three.js Page Transition Effect
 * Cinematic curtain-slide transition inspired by premium creative sites
 * Two dark panels slide in from top & bottom to cover the screen,
 * a gold accent line sweeps across the seam, then panels slide apart to reveal the new page.
 */
(function () {
    'use strict';

    if (typeof THREE === 'undefined') return;

    // --- Configuration ---
    const COVER_DURATION  = 0.7;  // Time for panels to close (seconds)
    const REVEAL_DURATION = 0.8;  // Time for panels to open (seconds)
    const HOLD_DURATION   = 0.15; // Pause between close & navigate
    const EASE_IN  = (t) => t * t * t; // cubic ease-in
    const EASE_OUT = (t) => 1 - Math.pow(1 - t, 3); // cubic ease-out

    // --- Vertex shader ---
    const vertexShader = `
        varying vec2 vUv;
        void main() {
            vUv = uv;
            gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
        }
    `;

    // --- Fragment shader: two sliding panels + gold accent line ---
    const fragmentShader = `
        uniform float uProgress;   // 0 = open, 1 = closed
        uniform float uDirection;  // 1.0 = closing, -1.0 = opening
        uniform vec2  uResolution;
        varying vec2 vUv;

        void main() {
            vec2 uv = vUv;

            // Panel backgrounds — dark (#0a0a0a)
            vec3 panelColor = vec3(0.04, 0.04, 0.04);

            // Gold accent (#c5a059)
            vec3 accentColor = vec3(0.773, 0.627, 0.349);

            // --- Top panel: slides down from top ---
            // When progress=0 the top edge is at y=1.0 (hidden above)
            // When progress=1 the top edge reaches y=0.5 (center)
            float topEdge = 1.0 - uProgress * 0.5;
            float topPanel = smoothstep(topEdge + 0.003, topEdge - 0.003, uv.y);

            // --- Bottom panel: slides up from bottom ---
            float botEdge = uProgress * 0.5;
            float botPanel = smoothstep(botEdge - 0.003, botEdge + 0.003, uv.y);

            // Combined panel alpha
            float panelAlpha = max(topPanel, botPanel);

            // --- Gold accent line at the seam between the two panels ---
            float seamY = 0.5;
            float lineThickness = 0.003;
            float lineGlow = 0.012;

            // Line visibility: appears when panels are nearly closed
            float lineVisibility = smoothstep(0.6, 0.9, uProgress);

            float lineDist = abs(uv.y - seamY);
            float lineCore = smoothstep(lineThickness, 0.0, lineDist) * lineVisibility;
            float lineBloom = smoothstep(lineGlow, 0.0, lineDist) * lineVisibility * 0.4;

            // --- Compose final color ---
            vec3 color = panelColor;
            // Mix in accent on the line
            color = mix(color, accentColor, lineCore);
            // Add subtle bloom glow
            color += accentColor * lineBloom;

            float alpha = max(panelAlpha, (lineCore + lineBloom) * 0.5);
            alpha = clamp(alpha, 0.0, 1.0);

            gl_FragColor = vec4(color, alpha);
        }
    `;

    // --- State ---
    let canvas, renderer, scene, camera, material;
    let isAnimating = false;
    let phase = 'idle'; // 'cover', 'hold', 'navigate', 'reveal'
    let phaseStart = 0;
    let targetUrl = null;

    // --- Init ---
    function init() {
        canvas = document.createElement('canvas');
        canvas.id = 'page-transition-canvas';
        canvas.style.cssText =
            'position:fixed;top:0;left:0;width:100%;height:100%;' +
            'pointer-events:none;z-index:99999;opacity:1;';
        document.body.appendChild(canvas);

        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: false });
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

        scene = new THREE.Scene();
        camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);

        const geometry = new THREE.PlaneGeometry(2, 2);
        material = new THREE.ShaderMaterial({
            vertexShader,
            fragmentShader,
            uniforms: {
                uProgress:   { value: 0.0 },
                uDirection:  { value: 1.0 },
                uResolution: { value: new THREE.Vector2(canvas.clientWidth, canvas.clientHeight) }
            },
            transparent: true,
            depthTest: false
        });
        scene.add(new THREE.Mesh(geometry, material));

        window.addEventListener('resize', onResize);
        document.addEventListener('click', onLinkClick, true);

        // Entry reveal
        playReveal();
    }

    function onResize() {
        if (!renderer || !canvas) return;
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        material.uniforms.uResolution.value.set(canvas.clientWidth, canvas.clientHeight);
    }

    // --- Should we intercept this link? ---
    function shouldIntercept(el) {
        const link = el.closest('a');
        if (!link) return null;
        const href = link.getAttribute('href');
        if (!href) return null;
        if (href === '' || href === '#' || href.startsWith('#') ||
            href.startsWith('javascript:') || href.startsWith('mailto:') ||
            href.startsWith('tel:')) return null;
        if (link.target === '_blank') return null;
        if (link.hostname && link.hostname !== window.location.hostname) return null;
        if (link.hasAttribute('download')) return null;
        if (link.closest('.modal') || link.closest('.login-modal') || link.closest('.register-modal')) return null;
        if (link.getAttribute('data-bs-toggle') || link.getAttribute('data-toggle')) return null;
        const oc = link.getAttribute('onclick');
        if (oc && (oc.includes('modal') || oc.includes('Modal'))) return null;
        return href;
    }

    function onLinkClick(e) {
        if (isAnimating) { e.preventDefault(); return; }
        const href = shouldIntercept(e.target);
        if (!href) return;
        e.preventDefault();
        navigateWithTransition(href);
    }

    // --- Cover → navigate ---
    function navigateWithTransition(url) {
        targetUrl = url;
        isAnimating = true;
        phase = 'cover';
        phaseStart = performance.now();
        material.uniforms.uDirection.value = 1.0;
        canvas.style.pointerEvents = 'all';
        document.body.classList.add('page-transitioning');
        requestAnimationFrame(animate);
    }

    // --- Reveal on page load ---
    function playReveal() {
        isAnimating = true;
        phase = 'reveal';
        material.uniforms.uProgress.value = 1.0;
        material.uniforms.uDirection.value = -1.0;
        renderer.render(scene, camera); // Show covered state immediately

        setTimeout(function () {
            phaseStart = performance.now();
            requestAnimationFrame(animate);
        }, 200);
    }

    // --- Animation loop ---
    function animate(now) {
        if (!isAnimating) return;
        const elapsed = (now - phaseStart) / 1000;

        if (phase === 'cover') {
            const t = Math.min(elapsed / COVER_DURATION, 1);
            material.uniforms.uProgress.value = EASE_IN(t);
            renderer.render(scene, camera);
            if (t < 1) {
                requestAnimationFrame(animate);
            } else {
                // Hold briefly with panels closed, then navigate
                phase = 'hold';
                phaseStart = performance.now();
                requestAnimationFrame(animate);
            }
        }
        else if (phase === 'hold') {
            const t = elapsed;
            renderer.render(scene, camera); // Keep rendering the closed state
            if (t < HOLD_DURATION) {
                requestAnimationFrame(animate);
            } else {
                // Navigate
                window.location.href = targetUrl;
            }
        }
        else if (phase === 'reveal') {
            const t = Math.min(elapsed / REVEAL_DURATION, 1);
            material.uniforms.uProgress.value = 1.0 - EASE_OUT(t);
            renderer.render(scene, camera);
            if (t < 1) {
                requestAnimationFrame(animate);
            } else {
                // Done
                material.uniforms.uProgress.value = 0.0;
                renderer.render(scene, camera);
                canvas.style.pointerEvents = 'none';
                document.body.classList.remove('page-transitioning');
                isAnimating = false;
                phase = 'idle';
            }
        }
    }

    // --- Bootstrap ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
