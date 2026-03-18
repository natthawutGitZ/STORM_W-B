/**
 * Three.js Page Transition Effect
 * Creates a cinematic shader-based wipe transition between pages
 */
(function () {
    'use strict';

    // Skip if Three.js is not loaded
    if (typeof THREE === 'undefined') return;

    const TRANSITION_DURATION = 0.9; // seconds
    const TRANSITION_EASE = (t) => t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; // easeInOutCubic

    // Vertex shader
    const vertexShader = `
        varying vec2 vUv;
        void main() {
            vUv = uv;
            gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
        }
    `;

    // Fragment shader — diagonal wipe with noise
    const fragmentShader = `
        uniform float uProgress;
        uniform vec2 uResolution;
        varying vec2 vUv;

        // Simple noise
        float hash(vec2 p) {
            return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453123);
        }

        float noise(vec2 p) {
            vec2 i = floor(p);
            vec2 f = fract(p);
            f = f * f * (3.0 - 2.0 * f);
            float a = hash(i);
            float b = hash(i + vec2(1.0, 0.0));
            float c = hash(i + vec2(0.0, 1.0));
            float d = hash(i + vec2(1.0, 1.0));
            return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
        }

        void main() {
            vec2 uv = vUv;
            
            // Diagonal progress line from top-left to bottom-right
            float diagonal = (uv.x + uv.y) * 0.5;
            
            // Add noise distortion for organic edge
            float n = noise(uv * 8.0) * 0.15;
            
            // Edge softness
            float edge = 0.08;
            
            // Calculate reveal: progress 0 = fully transparent, 1 = fully black
            float p = uProgress * (1.0 + edge * 2.0 + 0.15) - edge;
            float alpha = smoothstep(p - edge, p + edge, diagonal + n);
            
            // Dark color with slight accent tint (matching --primary-color #0a0a0a)
            vec3 color = vec3(0.04, 0.04, 0.04);
            
            gl_FragColor = vec4(color, alpha);
        }
    `;

    // State
    let canvas, renderer, scene, camera, mesh, material;
    let isAnimating = false;
    let animationProgress = 0;
    let animationDirection = 1; // 1 = cover, -1 = reveal
    let animationStart = 0;
    let targetUrl = null;
    let onComplete = null;

    function init() {
        // Create canvas
        canvas = document.createElement('canvas');
        canvas.id = 'page-transition-canvas';
        canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99999;opacity:0;';
        document.body.appendChild(canvas);

        // Renderer
        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: false });
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

        // Scene
        scene = new THREE.Scene();
        camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);

        // Fullscreen quad
        const geometry = new THREE.PlaneGeometry(2, 2);
        material = new THREE.ShaderMaterial({
            vertexShader,
            fragmentShader,
            uniforms: {
                uProgress: { value: 0.0 },
                uResolution: { value: new THREE.Vector2(canvas.clientWidth, canvas.clientHeight) }
            },
            transparent: true,
            depthTest: false
        });
        mesh = new THREE.Mesh(geometry, material);
        scene.add(mesh);

        // Resize
        window.addEventListener('resize', onResize);

        // Intercept link clicks
        document.addEventListener('click', onLinkClick, true);

        // Play entry reveal on load
        playReveal();
    }

    function onResize() {
        if (!renderer || !canvas) return;
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        material.uniforms.uResolution.value.set(canvas.clientWidth, canvas.clientHeight);
    }

    function shouldIntercept(el) {
        // Find closest <a> tag
        const link = el.closest('a');
        if (!link) return null;

        const href = link.getAttribute('href');
        if (!href) return null;

        // Skip empty, anchors, javascript:, mailto:, tel:
        if (href === '' || href === '#' || href.startsWith('#') ||
            href.startsWith('javascript:') || href.startsWith('mailto:') ||
            href.startsWith('tel:')) return null;

        // Skip external links
        if (link.target === '_blank') return null;
        if (link.hostname && link.hostname !== window.location.hostname) return null;

        // Skip if has download attribute
        if (link.hasAttribute('download')) return null;

        // Skip login/register modals (data attributes or specific classes)
        if (link.closest('.modal') || link.closest('.login-modal') || link.closest('.register-modal')) return null;
        if (link.getAttribute('data-bs-toggle') || link.getAttribute('data-toggle')) return null;
        
        // Skip if onclick handler that opens modal
        const onclickAttr = link.getAttribute('onclick');
        if (onclickAttr && (onclickAttr.includes('modal') || onclickAttr.includes('Modal'))) return null;

        return href;
    }

    function onLinkClick(e) {
        if (isAnimating) {
            e.preventDefault();
            return;
        }

        const href = shouldIntercept(e.target);
        if (!href) return;

        e.preventDefault();
        navigateWithTransition(href);
    }

    function navigateWithTransition(url) {
        targetUrl = url;
        isAnimating = true;
        animationDirection = 1; // Cover
        animationStart = performance.now();
        animationProgress = 0;
        canvas.style.opacity = '1';
        canvas.style.pointerEvents = 'all';
        document.body.classList.add('page-transitioning');

        onComplete = function () {
            window.location.href = targetUrl;
        };

        requestAnimationFrame(animate);
    }

    function playReveal() {
        isAnimating = true;
        animationDirection = -1; // Reveal
        animationProgress = 1;
        canvas.style.opacity = '1';
        canvas.style.pointerEvents = 'all';
        material.uniforms.uProgress.value = 1.0;
        renderer.render(scene, camera); // Render the initial covered state

        onComplete = function () {
            canvas.style.opacity = '0';
            canvas.style.pointerEvents = 'none';
            document.body.classList.remove('page-transitioning');
            isAnimating = false;
        };

        // Small delay to let the page layout settle, then start reveal
        setTimeout(function() {
            animationStart = performance.now();
            requestAnimationFrame(animate);
        }, 150);
    }

    function animate(time) {
        if (!isAnimating) return;

        const elapsed = (time - animationStart) / 1000;
        const t = Math.min(elapsed / TRANSITION_DURATION, 1);
        const easedT = TRANSITION_EASE(t);

        if (animationDirection === 1) {
            // Cover: 0 → 1
            animationProgress = easedT;
        } else {
            // Reveal: 1 → 0
            animationProgress = 1 - easedT;
        }

        material.uniforms.uProgress.value = animationProgress;
        renderer.render(scene, camera);

        if (t < 1) {
            requestAnimationFrame(animate);
        } else {
            isAnimating = false;
            if (onComplete) onComplete();
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
