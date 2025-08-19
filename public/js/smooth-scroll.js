/**
 * Lenis Smooth Scroll Implementation
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Configuration: duration 1.0, smoothWheel: true, initialize once only
 */

class SamFedBizSmoothScroll {
    constructor() {
        this.lenis = null;
        this.initialized = false;
        this.respectsReducedMotion = false;
        
        this.init();
    }

    init() {
        if (this.initialized) {
            console.warn('Lenis already initialized');
            return;
        }

        // Check for reduced motion preference
        this.respectsReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        if (this.respectsReducedMotion) {
            console.log('Lenis disabled due to reduced motion preference');
            return;
        }

        // Initialize after DOM and Lenis are ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupLenis());
        } else {
            this.setupLenis();
        }
    }

    setupLenis() {
        if (!window.Lenis) {
            console.error('Lenis not loaded');
            return;
        }

        try {
            // Initialize Lenis with specified configuration
            this.lenis = new Lenis({
                duration: 1.0,
                easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), // Smooth easing
                smoothWheel: true,
                wheelMultiplier: 1.0,
                smoothTouch: false, // Disable on touch devices for better performance
                normalizeWheel: true,
                infinite: false
            });

            // Integrate with GSAP if available
            if (window.gsap && window.ScrollTrigger) {
                this.lenis.on('scroll', ScrollTrigger.update);
                
                gsap.ticker.add((time) => {
                    this.lenis.raf(time * 1000);
                });
                
                gsap.ticker.lagSmoothing(0);
            } else {
                // Fallback RAF loop
                this.startRafLoop();
            }

            // Handle anchor links
            this.setupAnchorLinks();
            
            // Handle keyboard navigation
            this.setupKeyboardNavigation();

            this.initialized = true;
            console.log('✅ Lenis smooth scroll initialized');

        } catch (error) {
            console.error('Failed to initialize Lenis:', error);
        }
    }

    startRafLoop() {
        const raf = (time) => {
            this.lenis.raf(time);
            requestAnimationFrame(raf);
        };
        requestAnimationFrame(raf);
    }

    setupAnchorLinks() {
        // Handle anchor link clicks
        document.addEventListener('click', (e) => {
            const target = e.target.closest('a[href^="#"]');
            if (!target) return;

            const href = target.getAttribute('href');
            if (href === '#') return;

            e.preventDefault();
            
            const targetElement = document.querySelector(href);
            if (targetElement) {
                this.scrollTo(targetElement);
            }
        });
    }

    setupKeyboardNavigation() {
        // Ensure keyboard navigation still works smoothly
        document.addEventListener('keydown', (e) => {
            if (!this.lenis) return;

            switch (e.key) {
                case 'Home':
                    e.preventDefault();
                    this.scrollTo(0);
                    break;
                case 'End':
                    e.preventDefault();
                    this.scrollTo(document.body.scrollHeight);
                    break;
                case 'PageUp':
                    e.preventDefault();
                    this.scrollBy(-window.innerHeight * 0.8);
                    break;
                case 'PageDown':
                    e.preventDefault();
                    this.scrollBy(window.innerHeight * 0.8);
                    break;
                case 'ArrowUp':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.scrollTo(0);
                    }
                    break;
                case 'ArrowDown':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.scrollTo(document.body.scrollHeight);
                    }
                    break;
            }
        });
    }

    // Public methods for controlling scroll
    scrollTo(target, options = {}) {
        if (!this.lenis) return;

        const defaultOptions = {
            offset: 0,
            duration: 1.0,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t))
        };

        this.lenis.scrollTo(target, { ...defaultOptions, ...options });
    }

    scrollBy(delta, options = {}) {
        if (!this.lenis) return;

        const currentScroll = this.lenis.scroll;
        this.scrollTo(currentScroll + delta, options);
    }

    // Get current scroll position
    getScroll() {
        return this.lenis ? this.lenis.scroll : window.pageYOffset;
    }

    // Stop smooth scrolling
    stop() {
        if (this.lenis) {
            this.lenis.stop();
        }
    }

    // Start smooth scrolling
    start() {
        if (this.lenis) {
            this.lenis.start();
        }
    }

    // Enable/disable based on reduced motion preference
    handleReducedMotionChange(prefersReducedMotion) {
        if (prefersReducedMotion && this.lenis) {
            this.destroy();
        } else if (!prefersReducedMotion && !this.lenis) {
            this.respectsReducedMotion = false;
            this.initialized = false;
            this.setupLenis();
        }
    }

    // Cleanup
    destroy() {
        if (this.lenis) {
            this.lenis.destroy();
            this.lenis = null;
            this.initialized = false;
            console.log('Lenis destroyed');
        }
    }

    // Check if Lenis is running
    isRunning() {
        return this.lenis && this.initialized;
    }
}

// Initialize smooth scroll (singleton pattern)
let samFedBizSmoothScroll;

// Initialize when Lenis is loaded
if (window.Lenis) {
    samFedBizSmoothScroll = new SamFedBizSmoothScroll();
} else {
    // Wait for Lenis to load
    window.addEventListener('load', () => {
        if (window.Lenis) {
            samFedBizSmoothScroll = new SamFedBizSmoothScroll();
        } else {
            console.error('Lenis failed to load');
        }
    });
}

// Listen for reduced motion preference changes
if (window.matchMedia) {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    mediaQuery.addEventListener('change', () => {
        if (samFedBizSmoothScroll) {
            samFedBizSmoothScroll.handleReducedMotionChange(mediaQuery.matches);
        }
    });
}

// Export for global access
window.SamFedBizSmoothScroll = SamFedBizSmoothScroll;

// Expose scroll control methods globally
window.scrollToElement = (target, options) => {
    if (samFedBizSmoothScroll) {
        samFedBizSmoothScroll.scrollTo(target, options);
    }
};

window.scrollToTop = () => {
    if (samFedBizSmoothScroll) {
        samFedBizSmoothScroll.scrollTo(0);
    }
};

window.scrollToBottom = () => {
    if (samFedBizSmoothScroll) {
        samFedBizSmoothScroll.scrollTo(document.body.scrollHeight);
    }
};