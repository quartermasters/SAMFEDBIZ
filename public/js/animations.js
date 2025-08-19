/**
 * GSAP Animations for samfedbiz.com
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Implements:
 * - Reveal animations (staggered fade/slide-up, duration: 0.6, stagger: 0.08)
 * - Hover tilt (max 6°, max translate 6px, power2.out easing)
 * - Parallax effects
 */

class SamFedBizAnimations {
    constructor() {
        this.initialized = false;
        this.respectsReducedMotion = false;
        this.tiltCards = [];
        
        this.init();
    }

    init() {
        if (this.initialized) return;
        
        // Check for reduced motion preference
        this.respectsReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        // Initialize animations after DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupAnimations());
        } else {
            this.setupAnimations();
        }
        
        this.initialized = true;
    }

    setupAnimations() {
        if (!window.gsap) {
            console.error('GSAP not loaded');
            return;
        }

        // Set up reveal animations
        this.setupRevealAnimations();
        
        // Set up hover tilt effects
        this.setupHoverTilt();
        
        // Set up parallax effects
        this.setupParallax();
        
        console.log('✅ GSAP animations initialized');
    }

    setupRevealAnimations() {
        if (this.respectsReducedMotion) {
            // Just show elements without animation
            gsap.set('.reveal', { opacity: 1, y: 0 });
            return;
        }

        // Find all elements to reveal
        const revealElements = document.querySelectorAll('.reveal');
        
        if (revealElements.length === 0) return;

        // Set initial state
        gsap.set(revealElements, { 
            opacity: 0, 
            y: 24,
            willChange: 'transform, opacity'
        });

        // Create intersection observer for performance
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.animateReveal(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '50px'
        });

        // Observe all reveal elements
        revealElements.forEach(el => observer.observe(el));
    }

    animateReveal(element) {
        // Check if element has children to stagger
        const children = element.querySelectorAll('.reveal-child, .brief-card, .meeting-item, .outreach-item');
        
        if (children.length > 0) {
            // Staggered animation for children
            gsap.to(element, {
                opacity: 1,
                y: 0,
                duration: 0.6,
                ease: "power2.out"
            });
            
            gsap.to(children, {
                opacity: 1,
                y: 0,
                duration: 0.6,
                stagger: 0.08,
                ease: "power2.out",
                delay: 0.1
            });
        } else {
            // Simple reveal animation
            gsap.to(element, {
                opacity: 1,
                y: 0,
                duration: 0.6,
                ease: "power2.out"
            });
        }
        
        // Mark as animated
        element.classList.add('animate');
    }

    setupHoverTilt() {
        if (this.respectsReducedMotion) return;

        const tiltCards = document.querySelectorAll('.tilt-card');
        
        tiltCards.forEach(card => {
            this.tiltCards.push(card);
            
            // Set initial 3D properties
            gsap.set(card, {
                transformStyle: 'preserve-3d',
                transformOrigin: 'center center'
            });

            card.addEventListener('mousemove', (e) => this.handleTiltMove(e, card));
            card.addEventListener('mouseleave', () => this.handleTiltLeave(card));
        });
    }

    handleTiltMove(event, card) {
        if (this.respectsReducedMotion) return;

        const rect = card.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        
        // Calculate mouse position relative to card center
        const deltaX = event.clientX - centerX;
        const deltaY = event.clientY - centerY;
        
        // Calculate tilt angles (max 6°)
        const maxTilt = 6;
        const rotateX = -(deltaY / rect.height) * maxTilt;
        const rotateY = (deltaX / rect.width) * maxTilt;
        
        // Calculate translation (max 6px)
        const maxTranslate = 6;
        const translateZ = maxTranslate;
        
        // Apply transformation with power2.out easing
        gsap.to(card, {
            rotateX: rotateX,
            rotateY: rotateY,
            translateZ: translateZ,
            duration: 0.3,
            ease: "power2.out",
            willChange: 'transform'
        });
    }

    handleTiltLeave(card) {
        if (this.respectsReducedMotion) return;

        // Return to neutral position
        gsap.to(card, {
            rotateX: 0,
            rotateY: 0,
            translateZ: 0,
            duration: 0.5,
            ease: "power2.out"
        });
    }

    setupParallax() {
        if (this.respectsReducedMotion) return;

        const parallaxElements = document.querySelectorAll('.parallax, .hero');
        
        if (parallaxElements.length === 0) return;

        // Subtle parallax on scroll
        let ticking = false;
        
        const updateParallax = () => {
            const scrollY = window.pageYOffset;
            
            parallaxElements.forEach(el => {
                const speed = 0.15; // Subtle parallax strength
                const yPos = -(scrollY * speed);
                
                gsap.set(el, {
                    transform: `translateY(${yPos}px)`,
                    willChange: 'transform'
                });
            });
            
            ticking = false;
        };

        const requestParallaxUpdate = () => {
            if (!ticking) {
                requestAnimationFrame(updateParallax);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestParallaxUpdate, { passive: true });
    }

    // Method to disable animations if needed
    disableAnimations() {
        this.respectsReducedMotion = true;
        
        // Remove all active tweens
        gsap.killTweensOf(this.tiltCards);
        
        // Reset all transforms
        this.tiltCards.forEach(card => {
            gsap.set(card, {
                rotateX: 0,
                rotateY: 0,
                translateZ: 0,
                clearProps: 'transform'
            });
        });
    }

    // Method to enable animations
    enableAnimations() {
        this.respectsReducedMotion = false;
        this.setupHoverTilt();
        this.setupParallax();
    }

    // Cleanup method
    destroy() {
        // Kill all tweens
        gsap.killTweensOf('*');
        
        // Remove event listeners
        this.tiltCards.forEach(card => {
            card.removeEventListener('mousemove', this.handleTiltMove);
            card.removeEventListener('mouseleave', this.handleTiltLeave);
        });
        
        this.initialized = false;
    }
}

// Initialize animations
let samFedBizAnimations;

// Initialize when GSAP is loaded
if (window.gsap) {
    samFedBizAnimations = new SamFedBizAnimations();
} else {
    // Wait for GSAP to load
    window.addEventListener('load', () => {
        if (window.gsap) {
            samFedBizAnimations = new SamFedBizAnimations();
        } else {
            console.error('GSAP failed to load');
        }
    });
}

// Listen for reduced motion preference changes
if (window.matchMedia) {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    mediaQuery.addEventListener('change', () => {
        if (samFedBizAnimations) {
            if (mediaQuery.matches) {
                samFedBizAnimations.disableAnimations();
            } else {
                samFedBizAnimations.enableAnimations();
            }
        }
    });
}

// Export for global access
window.SamFedBizAnimations = SamFedBizAnimations;