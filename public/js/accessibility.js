/**
 * Accessibility Focus System
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Requirements:
 * - 2px solid #14B8A6 outline
 * - 2px offset
 * - Proper tab order
 * - ARIA roles for chat
 */

class AccessibilityManager {
    constructor() {
        this.focusableElements = [];
        this.keyboardNavigation = true;
        this.lastFocusedElement = null;
        
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupAccessibility());
        } else {
            this.setupAccessibility();
        }
    }

    setupAccessibility() {
        this.setupFocusStyles();
        this.setupTabOrder();
        this.setupKeyboardNavigation();
        this.setupARIALabels();
        this.setupSkipLinks();
        this.setupFocusTrapping();
        this.setupReducedMotion();
        
        console.log('✅ Accessibility system initialized');
    }

    /**
     * Set up consistent focus styles
     */
    setupFocusStyles() {
        // Create focus styles if not already defined
        if (!document.getElementById('a11y-focus-styles')) {
            const style = document.createElement('style');
            style.id = 'a11y-focus-styles';
            style.textContent = `
                /* Focus outline system */
                :focus {
                    outline: 2px solid #14B8A6 !important;
                    outline-offset: 2px !important;
                    border-radius: 4px;
                }

                /* Remove default focus for mouse users */
                :focus:not(.focus-visible) {
                    outline: none !important;
                }

                /* Enhanced focus for interactive elements */
                button:focus,
                input:focus,
                select:focus,
                textarea:focus,
                a:focus,
                [tabindex]:focus {
                    outline: 2px solid #14B8A6 !important;
                    outline-offset: 2px !important;
                    box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.2) !important;
                    transition: box-shadow 0.2s ease !important;
                }

                /* Chat-specific focus styles */
                .chat-input:focus {
                    outline: 2px solid #14B8A6 !important;
                    outline-offset: 2px !important;
                    box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.15) !important;
                }

                .chat-send:focus,
                .action-btn:focus,
                .suggestion-btn:focus {
                    outline: 2px solid #14B8A6 !important;
                    outline-offset: 2px !important;
                    transform: scale(1.02) !important;
                }

                /* Skip link styles */
                .skip-link {
                    position: absolute;
                    top: -40px;
                    left: 6px;
                    background: #14B8A6;
                    color: white;
                    padding: 8px 16px;
                    text-decoration: none;
                    border-radius: 4px;
                    z-index: 1000;
                    font-weight: 500;
                    transition: top 0.3s ease;
                }

                .skip-link:focus {
                    top: 6px;
                    outline: 2px solid white !important;
                    outline-offset: 2px !important;
                }

                /* Screen reader only content */
                .sr-only {
                    position: absolute !important;
                    width: 1px !important;
                    height: 1px !important;
                    padding: 0 !important;
                    margin: -1px !important;
                    overflow: hidden !important;
                    clip: rect(0, 0, 0, 0) !important;
                    white-space: nowrap !important;
                    border: 0 !important;
                }

                /* Focus indicators for custom components */
                .tilt-card:focus {
                    outline: 2px solid #14B8A6 !important;
                    outline-offset: 2px !important;
                    transform: translateY(-2px) !important;
                }

                .program-chip:focus {
                    outline: 2px solid #14B8A6 !important;
                    outline-offset: 2px !important;
                    transform: translateY(-1px) !important;
                }

                /* High contrast mode support */
                @media (prefers-contrast: high) {
                    :focus {
                        outline: 3px solid currentColor !important;
                        outline-offset: 2px !important;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Set up logical tab order
     */
    setupTabOrder() {
        // Define tab order priority
        const tabOrderElements = [
            '.skip-link',
            '.nav-link',
            '.program-chip',
            '#chat-input',
            '.chat-send',
            '.suggestion-btn',
            '.action-btn',
            '.btn-primary',
            '.btn-secondary',
            '.btn-text',
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ];

        let tabIndex = 1;
        
        tabOrderElements.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                if (element.tabIndex === 0 || element.tabIndex === -1) {
                    element.tabIndex = tabIndex++;
                }
            });
        });

        // Store focusable elements for navigation
        this.focusableElements = document.querySelectorAll(`
            a[href]:not([disabled]),
            button:not([disabled]),
            input:not([disabled]),
            select:not([disabled]),
            textarea:not([disabled]),
            [tabindex]:not([tabindex="-1"])
        `);
    }

    /**
     * Set up keyboard navigation
     */
    setupKeyboardNavigation() {
        // Track keyboard vs mouse usage
        document.addEventListener('keydown', (e) => {
            this.keyboardNavigation = true;
            document.body.classList.add('keyboard-navigation');
        });

        document.addEventListener('mousedown', () => {
            this.keyboardNavigation = false;
            document.body.classList.remove('keyboard-navigation');
        });

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 'F6':
                    e.preventDefault();
                    this.cycleFocusRegions(e.shiftKey);
                    break;
                case 'Escape':
                    this.handleEscape();
                    break;
                case '/':
                    if (!this.isInInput(e.target)) {
                        e.preventDefault();
                        this.focusChatInput();
                    }
                    break;
            }
        });

        // Chat-specific keyboard shortcuts
        this.setupChatKeyboardShortcuts();
    }

    setupChatKeyboardShortcuts() {
        const chatInput = document.getElementById('chat-input');
        if (!chatInput) return;

        chatInput.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 'ArrowUp':
                    if (chatInput.value === '' && e.target.selectionStart === 0) {
                        e.preventDefault();
                        this.recallLastMessage();
                    }
                    break;
                case 'Tab':
                    if (chatInput.value.startsWith('/')) {
                        e.preventDefault();
                        this.autocompleteSlashCommand();
                    }
                    break;
            }
        });
    }

    /**
     * Set up ARIA labels and roles
     */
    setupARIALabels() {
        // Chat interface ARIA labels
        const chatbox = document.querySelector('.sfbai-chatbox');
        if (chatbox) {
            chatbox.setAttribute('role', 'complementary');
            chatbox.setAttribute('aria-label', 'SFBAI Chat Assistant');
        }

        const chatInput = document.getElementById('chat-input');
        if (chatInput) {
            chatInput.setAttribute('aria-label', 'Chat with SFBAI assistant');
            chatInput.setAttribute('aria-describedby', 'chat-help-text');
        }

        const chatResponse = document.getElementById('chat-response');
        if (chatResponse) {
            chatResponse.setAttribute('role', 'log');
            chatResponse.setAttribute('aria-live', 'polite');
            chatResponse.setAttribute('aria-label', 'Chat conversation history');
        }

        // Navigation ARIA labels
        const nav = document.querySelector('.nav-main');
        if (nav) {
            nav.setAttribute('role', 'navigation');
            nav.setAttribute('aria-label', 'Main navigation');
        }

        // Program chips
        document.querySelectorAll('.program-chip').forEach((chip, index) => {
            chip.setAttribute('role', 'button');
            chip.setAttribute('aria-pressed', 'false');
            chip.setAttribute('aria-label', `Select ${chip.textContent.trim()} program`);
        });

        // Action buttons
        document.querySelectorAll('.action-btn').forEach(btn => {
            if (!btn.getAttribute('aria-label')) {
                btn.setAttribute('aria-label', btn.textContent.trim());
            }
        });

        // Status badges
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.setAttribute('aria-label', `Status: ${badge.textContent.trim()}`);
        });

        // Add missing alt text
        document.querySelectorAll('img:not([alt])').forEach(img => {
            img.setAttribute('alt', '');
        });

        // Form labels
        document.querySelectorAll('input, select, textarea').forEach(input => {
            if (!input.getAttribute('aria-label') && !input.getAttribute('aria-labelledby')) {
                const label = document.querySelector(`label[for="${input.id}"]`);
                if (!label && input.placeholder) {
                    input.setAttribute('aria-label', input.placeholder);
                }
            }
        });
    }

    /**
     * Set up skip links
     */
    setupSkipLinks() {
        // Add skip link if not present
        if (!document.querySelector('.skip-link')) {
            const skipLink = document.createElement('a');
            skipLink.href = '#main-content';
            skipLink.className = 'skip-link';
            skipLink.textContent = 'Skip to main content';
            
            document.body.insertBefore(skipLink, document.body.firstChild);
        }

        // Add main content ID if not present
        const mainContent = document.querySelector('main, .hero, .dashboard-content');
        if (mainContent && !mainContent.id) {
            mainContent.id = 'main-content';
        }
    }

    /**
     * Set up focus trapping for modals
     */
    setupFocusTrapping() {
        this.trapFocus = (container) => {
            const focusableElements = container.querySelectorAll(`
                a[href]:not([disabled]),
                button:not([disabled]),
                input:not([disabled]),
                select:not([disabled]),
                textarea:not([disabled]),
                [tabindex]:not([tabindex="-1"])
            `);

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            container.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement.focus();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                }
            });
        };
    }

    /**
     * Set up reduced motion support
     */
    setupReducedMotion() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        
        const handleReducedMotion = (mq) => {
            if (mq.matches) {
                document.body.classList.add('reduced-motion');
            } else {
                document.body.classList.remove('reduced-motion');
            }
        };

        handleReducedMotion(prefersReducedMotion);
        prefersReducedMotion.addEventListener('change', handleReducedMotion);
    }

    /**
     * Utility methods
     */
    cycleFocusRegions(reverse = false) {
        const regions = ['nav', 'main', 'complementary'];
        const currentRegion = document.activeElement.closest('[role]')?.getAttribute('role');
        const currentIndex = regions.indexOf(currentRegion);
        const nextIndex = reverse 
            ? (currentIndex - 1 + regions.length) % regions.length
            : (currentIndex + 1) % regions.length;
        
        const nextRegion = document.querySelector(`[role="${regions[nextIndex]}"]`);
        if (nextRegion) {
            const firstFocusable = nextRegion.querySelector(`
                a[href]:not([disabled]),
                button:not([disabled]),
                input:not([disabled])
            `);
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }
    }

    handleEscape() {
        // Close any open modals or dropdowns
        const activeModal = document.querySelector('.modal.active, .dropdown.open');
        if (activeModal) {
            this.closeModal(activeModal);
            return;
        }

        // Clear chat input if focused
        const chatInput = document.getElementById('chat-input');
        if (document.activeElement === chatInput && chatInput.value) {
            chatInput.value = '';
            return;
        }

        // Return focus to main content
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            mainContent.focus();
        }
    }

    focusChatInput() {
        const chatInput = document.getElementById('chat-input');
        if (chatInput) {
            chatInput.focus();
        }
    }

    isInInput(element) {
        return element.tagName === 'INPUT' || 
               element.tagName === 'TEXTAREA' || 
               element.contentEditable === 'true';
    }

    recallLastMessage() {
        // This would integrate with the chat system to recall previous messages
        if (window.sfbaiChat && window.sfbaiChat.messageHistory.length > 0) {
            const lastUserMessage = window.sfbaiChat.messageHistory
                .filter(msg => msg.role === 'user')
                .pop();
            
            if (lastUserMessage) {
                document.getElementById('chat-input').value = lastUserMessage.content;
            }
        }
    }

    autocompleteSlashCommand() {
        // This would show available slash commands
        console.log('Autocomplete slash commands');
    }

    closeModal(modal) {
        modal.classList.remove('active', 'open');
        
        // Return focus to the element that opened the modal
        if (this.lastFocusedElement) {
            this.lastFocusedElement.focus();
            this.lastFocusedElement = null;
        }
    }

    /**
     * Announce important changes to screen readers
     */
    announce(message, priority = 'polite') {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', priority);
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = message;
        
        document.body.appendChild(announcement);
        
        // Remove after announcement
        setTimeout(() => {
            if (announcement.parentNode) {
                announcement.parentNode.removeChild(announcement);
            }
        }, 1000);
    }

    /**
     * Get accessibility report for review gates
     */
    getReport() {
        const issues = [];
        
        // Check for missing alt text
        const imagesWithoutAlt = document.querySelectorAll('img:not([alt])');
        if (imagesWithoutAlt.length > 0) {
            issues.push(`${imagesWithoutAlt.length} images missing alt text`);
        }

        // Check for missing ARIA labels
        const interactiveWithoutLabels = document.querySelectorAll(`
            button:not([aria-label]):not([aria-labelledby]),
            input:not([aria-label]):not([aria-labelledby])
        `).length;
        
        if (interactiveWithoutLabels > 0) {
            issues.push(`${interactiveWithoutLabels} interactive elements missing labels`);
        }

        // Check for proper heading hierarchy
        const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6'));
        let headingIssues = 0;
        let lastLevel = 0;
        
        headings.forEach(heading => {
            const level = parseInt(heading.tagName.charAt(1));
            if (level > lastLevel + 1) {
                headingIssues++;
            }
            lastLevel = level;
        });

        if (headingIssues > 0) {
            issues.push(`${headingIssues} heading hierarchy issues`);
        }

        return {
            totalIssues: issues.length,
            issues: issues,
            focusableElements: this.focusableElements.length,
            keyboardNavigationEnabled: this.keyboardNavigation
        };
    }

    /**
     * Test keyboard navigation (for manual testing)
     */
    testKeyboardNavigation() {
        console.log('Testing keyboard navigation...');
        
        let index = 0;
        const interval = setInterval(() => {
            if (index >= this.focusableElements.length) {
                clearInterval(interval);
                console.log('Keyboard navigation test complete');
                return;
            }
            
            this.focusableElements[index].focus();
            console.log(`Focused: ${this.focusableElements[index].tagName} - ${this.focusableElements[index].textContent?.slice(0, 50) || 'No text'}`);
            index++;
        }, 1000);
    }
}

// Initialize accessibility manager
const accessibilityManager = new AccessibilityManager();

// Export for global access
window.AccessibilityManager = AccessibilityManager;
window.accessibilityManager = accessibilityManager;

console.log('✅ Accessibility manager initialized');