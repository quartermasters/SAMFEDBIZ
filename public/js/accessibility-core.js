/**
 * Core Accessibility Features (Minified)
 * samfedbiz.com - Federal BD Platform
 */

(function() {
    'use strict';
    
    // Core accessibility enhancements
    function initAccessibility() {
        // Focus management
        let lastFocused = null;
        document.addEventListener('focusin', e => {
            if (!e.target.closest('[role="dialog"]')) lastFocused = e.target;
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', e => {
            if (e.key === 'Tab') document.body.classList.add('user-is-tabbing');
        });
        document.addEventListener('mousedown', () => {
            document.body.classList.remove('user-is-tabbing');
        });
        
        // Skip links
        document.querySelectorAll('.skip-link').forEach(link => {
            link.addEventListener('click', e => {
                const target = document.querySelector(link.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.focus();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Enhance interactive elements
        document.querySelectorAll('[role="button"]:not(button)').forEach(btn => {
            if (!btn.hasAttribute('tabindex')) btn.setAttribute('tabindex', '0');
            btn.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    btn.click();
                }
            });
        });
        
        // Form enhancements
        document.querySelectorAll('input, select, textarea').forEach(ctrl => {
            if (['button', 'submit', 'reset', 'hidden'].includes(ctrl.type)) return;
            
            const id = ctrl.id;
            const label = id ? document.querySelector(`label[for="${id}"]`) : null;
            const ariaLabel = ctrl.getAttribute('aria-label');
            const ariaLabelledby = ctrl.getAttribute('aria-labelledby');
            
            if (!label && !ariaLabel && !ariaLabelledby) {
                console.warn('Form control missing label:', ctrl);
            }
        });
        
        // Announce dynamic changes
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.className = 'sr-only';
        announcer.id = 'announcer';
        document.body.appendChild(announcer);
        
        window.announce = function(text) {
            announcer.textContent = text;
            setTimeout(() => announcer.textContent = '', 1000);
        };
    }
    
    // Quick contrast check
    function checkContrast(fg, bg) {
        const getLum = (r, g, b) => {
            const [rs, gs, bs] = [r, g, b].map(c => {
                c = c / 255;
                return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
        };
        
        const parseRGB = (color) => {
            const match = color.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
            return match ? [parseInt(match[1]), parseInt(match[2]), parseInt(match[3])] : null;
        };
        
        const fgRGB = parseRGB(fg);
        const bgRGB = parseRGB(bg);
        
        if (!fgRGB || !bgRGB) return null;
        
        const l1 = getLum(...fgRGB);
        const l2 = getLum(...bgRGB);
        
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccessibility);
    } else {
        initAccessibility();
    }
    
    // Export for use
    window.a11y = { checkContrast, announce: () => {} };
})();