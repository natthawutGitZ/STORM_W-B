document.addEventListener('DOMContentLoaded', () => {
    // Mobile Menu
    const menuToggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('nav');

    if (menuToggle && nav) {
        menuToggle.addEventListener('click', () => {
            nav.classList.toggle('active');
            menuToggle.style.transform = nav.classList.contains('active') ? 'rotate(90deg)' : 'rotate(0)';
        });
    }

    // Header Scroll Effect
    const header = document.querySelector('header');
    if (header) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // ===== Modern Bidirectional Scroll Reveal =====
    const revealElements = document.querySelectorAll('.reveal');

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Add a small delay based on element position for cascade effect
                const rect = entry.boundingClientRect;
                const viewportHeight = window.innerHeight;
                const distFromCenter = Math.abs(rect.top - viewportHeight / 2) / viewportHeight;
                const extraDelay = distFromCenter * 0.1; // 0-100ms extra

                setTimeout(() => {
                    entry.target.classList.add('active');

                    // Stagger children with smooth cascade
                    const children = entry.target.querySelectorAll('.reveal-child');
                    children.forEach((child, i) => {
                        child.style.transitionDelay = (i * 0.12 + 0.15) + 's';
                        requestAnimationFrame(() => {
                            child.classList.add('active');
                        });
                    });
                }, extraDelay * 1000);
            } else {
                // Smooth exit — remove active with reset delays
                entry.target.classList.remove('active');
                const children = entry.target.querySelectorAll('.reveal-child');
                children.forEach(child => {
                    child.style.transitionDelay = '0s';
                    child.classList.remove('active');
                });
            }
        });
    }, {
        root: null,
        threshold: 0.08,
        rootMargin: "0px 0px -80px 0px"
    });

    revealElements.forEach(el => {
        revealObserver.observe(el);
    });

    // ===== Parallax Hero Background =====
    const hero = document.querySelector('.hero');
    if (hero) {
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    const scrolled = window.scrollY;
                    hero.style.backgroundPositionY = (scrolled * 0.35) + 'px';
                    // Subtle hero fade on scroll
                    const heroContent = hero.querySelector('.hero-content');
                    if (heroContent) {
                        const opacity = Math.max(0, 1 - scrolled / 600);
                        const translateY = scrolled * 0.15;
                        heroContent.style.opacity = opacity;
                        heroContent.style.transform = `translateY(${translateY}px)`;
                    }
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }

    // ===== Branch Section Parallax =====
    const branchSections = document.querySelectorAll('.branch-section');
    if (branchSections.length > 0) {
        let branchTicking = false;
        window.addEventListener('scroll', () => {
            if (!branchTicking) {
                requestAnimationFrame(() => {
                    branchSections.forEach(section => {
                        const rect = section.getBoundingClientRect();
                        const viewHeight = window.innerHeight;
                        if (rect.top < viewHeight && rect.bottom > 0) {
                            const progress = (viewHeight - rect.top) / (viewHeight + rect.height);
                            const parallaxOffset = (progress - 0.5) * 40;
                            section.style.backgroundPositionY = `calc(50% + ${parallaxOffset}px)`;
                        }
                    });
                    branchTicking = false;
                });
                branchTicking = true;
            }
        }, { passive: true });
    }

    // ===== Timeline Step Stagger Animation =====
    const timelineSteps = document.querySelectorAll('.timeline-zigzag > div');
    if (timelineSteps.length > 0) {
        const timelineObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                } else {
                    entry.target.style.opacity = '0';
                    entry.target.style.transform = 'translateY(30px)';
                }
            });
        }, {
            threshold: 0.2,
            rootMargin: "0px 0px -60px 0px"
        });

        timelineSteps.forEach((step, i) => {
            step.style.opacity = '0';
            step.style.transform = 'translateY(30px)';
            step.style.transition = `opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1) ${i * 0.15}s, 
                                     transform 0.8s cubic-bezier(0.22, 1, 0.36, 1) ${i * 0.15}s`;
            timelineObserver.observe(step);
        });
    }

    // Smooth Scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
                if (nav && nav.classList.contains('active')) {
                    nav.classList.remove('active');
                }
            }
        });
    });

    console.log('SAC Modern UI Loaded');
});
