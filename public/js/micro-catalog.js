/**
 * Micro-Catalog Builder JavaScript
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles micro-catalog generation, printing, and interactions
 */

class MicroCatalogBuilder {
    constructor() {
        this.initializeEventListeners();
        this.setupPrintOptimization();
        this.initializeAnimations();
    }

    initializeEventListeners() {
        // Prime selection cards hover effects
        this.setupPrimeCardHovers();
        
        // Catalog action buttons
        this.setupCatalogActions();
        
        // Print preview handling
        this.setupPrintHandling();
    }

    setupPrimeCardHovers() {
        const primeCards = document.querySelectorAll('.prime-card');
        
        primeCards.forEach(card => {
            card.addEventListener('mouseenter', (e) => {
                gsap.to(card, {
                    scale: 1.02,
                    rotateY: 2,
                    rotateX: 1,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
            
            card.addEventListener('mouseleave', (e) => {
                gsap.to(card, {
                    scale: 1,
                    rotateY: 0,
                    rotateX: 0,
                    duration: 0.3,
                    ease: "power2.out"
                });
            });
            
            // Add click enhancement
            card.addEventListener('click', (e) => {
                if (e.target.tagName !== 'BUTTON') {
                    const button = card.querySelector('.prime-select-btn');
                    if (button) {
                        button.click();
                    }
                }
            });
        });
    }

    setupCatalogActions() {
        // Print button enhancement
        const printBtn = document.querySelector('[onclick="window.print()"]');
        if (printBtn) {
            printBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handlePrint();
            });
        }

        // PDF export button feedback
        const pdfBtn = document.querySelector('[type="submit"][value="generate_pdf"]');
        if (pdfBtn) {
            pdfBtn.addEventListener('click', (e) => {
                this.showExportFeedback(pdfBtn);
            });
        }
    }

    setupPrintHandling() {
        // Optimize layout before printing
        window.addEventListener('beforeprint', () => {
            this.optimizeForPrint();
        });
        
        // Restore layout after printing
        window.addEventListener('afterprint', () => {
            this.restoreFromPrint();
        });
    }

    handlePrint() {
        // Add print preparation
        document.body.classList.add('preparing-print');
        
        // Small delay to ensure styles are applied
        setTimeout(() => {
            window.print();
            document.body.classList.remove('preparing-print');
        }, 100);
    }

    optimizeForPrint() {
        // Ensure all animations are completed
        gsap.set('.reveal', { opacity: 1, y: 0 });
        
        // Hide interactive elements
        document.body.classList.add('print-mode');
        
        // Ensure proper page breaks
        this.handlePageBreaks();
    }

    restoreFromPrint() {
        document.body.classList.remove('print-mode');
    }

    handlePageBreaks() {
        const sections = document.querySelectorAll('.catalog-section');
        sections.forEach((section, index) => {
            // Add page break before certain sections
            if (index > 0 && section.querySelector('.capabilities-grid')) {
                section.style.pageBreakBefore = 'always';
            }
        });
    }

    showExportFeedback(button) {
        const originalText = button.innerHTML;
        button.innerHTML = `
            <svg class="animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <path d="M12 8l-4 4h3v4h2v-4h3l-4-4z"/>
            </svg>
            Preparing...
        `;
        button.disabled = true;
        
        // Reset after form submission
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 3000);
    }

    initializeAnimations() {
        // Stagger reveal animation for capability cards
        const capabilityCards = document.querySelectorAll('.capability-card');
        if (capabilityCards.length > 0) {
            gsap.from(capabilityCards, {
                y: 40,
                opacity: 0,
                duration: 0.6,
                stagger: 0.1,
                scrollTrigger: {
                    trigger: '.capabilities-grid',
                    start: 'top 80%',
                    toggleActions: 'play none none reverse'
                }
            });
        }

        // Animate BOM table rows
        const bomRows = document.querySelectorAll('.bom-table tbody tr');
        if (bomRows.length > 0) {
            gsap.from(bomRows, {
                x: -20,
                opacity: 0,
                duration: 0.4,
                stagger: 0.05,
                scrollTrigger: {
                    trigger: '.bom-table',
                    start: 'top 85%',
                    toggleActions: 'play none none reverse'
                }
            });
        }

        // Animate delivery metrics
        const deliveryMetric = document.querySelector('.delivery-metric');
        if (deliveryMetric) {
            gsap.from(deliveryMetric, {
                scale: 0.8,
                opacity: 0,
                duration: 0.6,
                ease: "back.out(1.7)",
                scrollTrigger: {
                    trigger: deliveryMetric,
                    start: 'top 85%',
                    toggleActions: 'play none none reverse'
                }
            });
        }
    }

    // Utility method to get CSRF token
    getCSRFToken() {
        const tokenInput = document.querySelector('input[name="csrf_token"]');
        return tokenInput ? tokenInput.value : '';
    }

    // Method to handle capability tag interactions
    setupCapabilityTags() {
        const capabilityTags = document.querySelectorAll('.capability-tag');
        
        capabilityTags.forEach(tag => {
            tag.addEventListener('click', () => {
                // Add visual feedback
                gsap.to(tag, {
                    scale: 0.95,
                    duration: 0.1,
                    yoyo: true,
                    repeat: 1
                });
                
                // Could implement filtering or search functionality here
                console.log('Capability selected:', tag.textContent);
            });
        });
    }

    // Method to enhance part number interactions
    setupPartNumbers() {
        const partNumbers = document.querySelectorAll('.part-number');
        
        partNumbers.forEach(part => {
            part.addEventListener('click', () => {
                // Copy to clipboard
                navigator.clipboard.writeText(part.textContent).then(() => {
                    this.showTooltip(part, 'Copied to clipboard!');
                });
            });
        });
    }

    showTooltip(element, message) {
        const tooltip = document.createElement('div');
        tooltip.className = 'copy-tooltip';
        tooltip.textContent = message;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        gsap.fromTo(tooltip, 
            { opacity: 0, y: 10 },
            { opacity: 1, y: 0, duration: 0.2 }
        );
        
        setTimeout(() => {
            gsap.to(tooltip, {
                opacity: 0,
                y: -10,
                duration: 0.2,
                onComplete: () => tooltip.remove()
            });
        }, 2000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new MicroCatalogBuilder();
});

// Export for potential external use
window.MicroCatalogBuilder = MicroCatalogBuilder;