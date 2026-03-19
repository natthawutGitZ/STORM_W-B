/**
 * Page Transitions — Barba.js + GSAP
 * Military-themed wipe overlay transition
 */
(function () {
    'use strict';

    // ── Overlay elements ──
    const overlay = document.querySelector('.barba-overlay');
    const stripe = document.querySelector('.barba-overlay-stripe');
    if (!overlay || !stripe) return;

    // ── Routes that should NOT use SPA transitions ──
    const EXCLUDED_PREFIXES = ['admin/', 'api/', 'logout', 'login_google', 'link_steam', 'register_process', 'form_google_callback', 'upload_slip', 'donate'];

    function shouldPrevent(url) {
        try {
            const u = new URL(url, window.location.origin);
            const path = u.pathname.replace(/^\//, '').replace(/\.php$/, '');
            return EXCLUDED_PREFIXES.some(prefix => path.startsWith(prefix));
        } catch (e) {
            return true;
        }
    }

    // ── GSAP leave animation ──
    function leaveAnimation() {
        return new Promise(resolve => {
            const tl = gsap.timeline({ onComplete: resolve });

            tl.set(overlay, { display: 'block', x: '-100%' })
              .set(stripe, { opacity: 1, x: 0 })
              .to(overlay, {
                  x: '0%',
                  duration: 0.5,
                  ease: 'power3.inOut'
              })
              .to(stripe, {
                  x: '100vw',
                  opacity: 0,
                  duration: 0.4,
                  ease: 'power2.in'
              }, '-=0.3');
        });
    }

    // ── GSAP enter animation ──
    function enterAnimation(container) {
        return new Promise(resolve => {
            // Reset scroll to top
            window.scrollTo(0, 0);

            const tl = gsap.timeline({ onComplete: resolve });

            // Fade in new content with upward slide
            gsap.set(container, { opacity: 0, y: 30 });

            tl.to(overlay, {
                x: '100%',
                duration: 0.5,
                ease: 'power3.inOut'
            })
            .to(container, {
                opacity: 1,
                y: 0,
                duration: 0.5,
                ease: 'power2.out'
            }, '-=0.35')
            .set(overlay, { display: 'none', x: '-100%' })
            .set(stripe, { opacity: 1, x: 0 });
        });
    }

    // ── Re-init page scripts after transition ──
    function reinitPage(container) {
        // Re-init main.js animations
        if (typeof window.initPageAnimations === 'function') {
            window.initPageAnimations();
        }

        // Re-init Three.js flashlight if on home page
        const flashCanvas = container.querySelector('#flashlight-canvas');
        if (flashCanvas && typeof THREE !== 'undefined') {
            // The inline script in home.php will handle this via DOMContentLoaded
            // We dispatch a synthetic event
            const evt = new Event('DOMContentLoaded');
            document.dispatchEvent(evt);
        }

        // Re-run any inline <script> in the new container
        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            if (oldScript.src) {
                newScript.src = oldScript.src;
            } else {
                newScript.textContent = oldScript.textContent;
            }
            // Copy attributes
            Array.from(oldScript.attributes).forEach(attr => {
                if (attr.name !== 'src') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    // ── Init Barba ──
    barba.init({
        // Prevent transitions for excluded routes & external links
        prevent: ({ el }) => {
            if (!el || !el.href) return true;
            const url = el.href;

            // External links
            if (el.hostname !== window.location.hostname) return true;

            // Excluded routes
            if (shouldPrevent(url)) return true;

            // Hash-only links
            if (el.getAttribute('href').startsWith('#')) return true;

            // Links that open in new tab
            if (el.target === '_blank') return true;

            return false;
        },

        transitions: [{
            name: 'military-wipe',

            async leave(data) {
                await leaveAnimation();
                data.current.container.remove();
            },

            async enter(data) {
                await enterAnimation(data.next.container);
            },

            async after(data) {
                reinitPage(data.next.container);
            }
        }]
    });

    // ── Update active nav link after transition ──
    barba.hooks.after(() => {
        const currentPath = window.location.pathname.replace(/^\//, '').replace(/\.php$/, '') || 'index';
        const navLinks = document.querySelectorAll('.pill-nav-link, .nav-link, nav a');
        navLinks.forEach(link => {
            const href = (link.getAttribute('href') || '').replace(/^\//, '').replace(/\.php$/, '');
            link.classList.remove('active');
            if (href === currentPath || (currentPath === 'index' && (href === '' || href === '/'))) {
                link.classList.add('active');
            }
        });
    });

})();
