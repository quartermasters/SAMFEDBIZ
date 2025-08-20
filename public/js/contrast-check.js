/**
 * Contrast Check Function
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Requirements:
 * - All text/background pairs must have >=80 grayscale point difference
 * - Meet WCAG AA (4.5:1 normal, 3:1 large text)
 * - Formula: Y = round(0.299*R + 0.587*G + 0.114*B)
 */

class ContrastChecker {
    constructor() {
        this.failures = [];
        this.checks = [];
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.runChecks());
        } else {
            this.runChecks();
        }
    }

    /**
     * Convert RGB values to grayscale using luminance formula
     * Y = round(0.299*R + 0.587*G + 0.114*B)
     */
    rgbToGrayscale(r, g, b) {
        return Math.round(0.299 * r + 0.587 * g + 0.114 * b);
    }

    /**
     * Parse CSS color to RGB values
     */
    parseColor(color) {
        // Handle hex colors
        if (color.startsWith('#')) {
            const hex = color.slice(1);
            if (hex.length === 3) {
                return {
                    r: parseInt(hex[0] + hex[0], 16),
                    g: parseInt(hex[1] + hex[1], 16),
                    b: parseInt(hex[2] + hex[2], 16)
                };
            } else if (hex.length === 6) {
                return {
                    r: parseInt(hex.slice(0, 2), 16),
                    g: parseInt(hex.slice(2, 4), 16),
                    b: parseInt(hex.slice(4, 6), 16)
                };
            }
        }

        // Handle rgb() and rgba() colors
        const rgbMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (rgbMatch) {
            return {
                r: parseInt(rgbMatch[1]),
                g: parseInt(rgbMatch[2]),
                b: parseInt(rgbMatch[3])
            };
        }

        // Handle named colors
        const namedColors = {
            white: { r: 255, g: 255, b: 255 },
            black: { r: 0, g: 0, b: 0 },
            red: { r: 255, g: 0, b: 0 },
            green: { r: 0, g: 128, b: 0 },
            blue: { r: 0, g: 0, b: 255 },
            transparent: { r: 255, g: 255, b: 255 } // Assume white for transparent
        };

        return namedColors[color.toLowerCase()] || { r: 0, g: 0, b: 0 };
    }

    /**
     * Calculate relative luminance for WCAG contrast ratio
     */
    getRelativeLuminance(r, g, b) {
        const toLinear = (val) => {
            val = val / 255;
            return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
        };

        return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
    }

    /**
     * Calculate WCAG contrast ratio
     */
    getContrastRatio(color1, color2) {
        const lum1 = this.getRelativeLuminance(color1.r, color1.g, color1.b);
        const lum2 = this.getRelativeLuminance(color2.r, color2.g, color2.b);
        
        const brightest = Math.max(lum1, lum2);
        const darkest = Math.min(lum1, lum2);
        
        return (brightest + 0.05) / (darkest + 0.05);
    }

    /**
     * Get effective background color (handling transparency)
     */
    getEffectiveBackgroundColor(element) {
        let current = element;
        let bgColor = null;

        while (current && current !== document.body.parentElement) {
            const computedStyle = window.getComputedStyle(current);
            const backgroundColor = computedStyle.backgroundColor;
            
            if (backgroundColor && backgroundColor !== 'rgba(0, 0, 0, 0)' && backgroundColor !== 'transparent') {
                bgColor = backgroundColor;
                break;
            }
            
            current = current.parentElement;
        }

        // Default to white if no background found
        return bgColor || 'rgb(255, 255, 255)';
    }

    /**
     * Check contrast for a specific element
     */
    checkElementContrast(element) {
        const computedStyle = window.getComputedStyle(element);
        const textColor = computedStyle.color;
        const backgroundColor = this.getEffectiveBackgroundColor(element);
        
        // Parse colors
        const textRGB = this.parseColor(textColor);
        const bgRGB = this.parseColor(backgroundColor);
        
        // Calculate grayscale values
        const textGray = this.rgbToGrayscale(textRGB.r, textRGB.g, textRGB.b);
        const bgGray = this.rgbToGrayscale(bgRGB.r, bgRGB.g, bgRGB.b);
        
        // Calculate grayscale difference
        const grayDiff = Math.abs(textGray - bgGray);
        
        // Calculate WCAG contrast ratio
        const contrastRatio = this.getContrastRatio(textRGB, bgRGB);
        
        // Determine if text is large (18pt+ or 14pt+ bold)
        const fontSize = parseFloat(computedStyle.fontSize);
        const fontWeight = computedStyle.fontWeight;
        const isLargeText = fontSize >= 18 || (fontSize >= 14 && (fontWeight === 'bold' || parseInt(fontWeight) >= 700));
        
        // WCAG AA requirements
        const wcagRequirement = isLargeText ? 3.0 : 4.5;
        
        const result = {
            element: element,
            textColor: textColor,
            backgroundColor: backgroundColor,
            textGray: textGray,
            bgGray: bgGray,
            grayDiff: grayDiff,
            contrastRatio: contrastRatio,
            isLargeText: isLargeText,
            wcagRequirement: wcagRequirement,
            passesGrayCheck: grayDiff >= 80,
            passesWCAG: contrastRatio >= wcagRequirement,
            selector: this.getElementSelector(element)
        };

        this.checks.push(result);

        // Record failures
        if (!result.passesGrayCheck || !result.passesWCAG) {
            this.failures.push(result);
        }

        return result;
    }

    /**
     * Generate a CSS selector for an element
     */
    getElementSelector(element) {
        if (element.id) {
            return `#${element.id}`;
        }
        
        if (element.className) {
            const classes = element.className.split(' ').filter(c => c.trim());
            if (classes.length > 0) {
                return `${element.tagName.toLowerCase()}.${classes[0]}`;
            }
        }
        
        return element.tagName.toLowerCase();
    }

    /**
     * Run contrast checks on all text elements
     */
    runChecks() {
        console.log('🔍 Running contrast checks...');
        
        // Reset previous results
        this.failures = [];
        this.checks = [];

        // Find all text-containing elements
        const textElements = document.querySelectorAll(`
            h1, h2, h3, h4, h5, h6, p, span, div, a, button, 
            label, input, textarea, select, li, td, th, 
            .nav-link, .btn-primary, .btn-secondary, .btn-text,
            .program-chip, .status-badge, .chat-message,
            .brief-summary, .meeting-details, .outreach-contact
        `);

        textElements.forEach(element => {
            // Skip elements with no text content or invisible elements
            if (!element.textContent.trim() || 
                window.getComputedStyle(element).display === 'none' ||
                window.getComputedStyle(element).visibility === 'hidden') {
                return;
            }

            this.checkElementContrast(element);
        });

        this.reportResults();
    }

    /**
     * Report check results
     */
    reportResults() {
        console.log(`✅ Contrast checks complete: ${this.checks.length} elements checked`);
        
        if (this.failures.length === 0) {
            console.log('🎉 All contrast checks passed!');
            return true;
        }

        console.warn(`⚠️ ${this.failures.length} contrast failures found:`);
        
        this.failures.forEach((failure, index) => {
            console.group(`Failure ${index + 1}: ${failure.selector}`);
            console.log('Text color:', failure.textColor, `(gray: ${failure.textGray})`);
            console.log('Background color:', failure.backgroundColor, `(gray: ${failure.bgGray})`);
            console.log('Grayscale difference:', failure.grayDiff, `(required: 80)`);
            console.log('WCAG contrast ratio:', failure.contrastRatio.toFixed(2), `(required: ${failure.wcagRequirement})`);
            console.log('Large text:', failure.isLargeText);
            console.log('Element:', failure.element);
            console.groupEnd();
        });

        return false;
    }

    /**
     * Get detailed report for review gates
     */
    getReport() {
        return {
            totalChecks: this.checks.length,
            failures: this.failures.length,
            passRate: ((this.checks.length - this.failures.length) / this.checks.length * 100).toFixed(1),
            details: this.failures.map(f => ({
                selector: f.selector,
                textColor: f.textColor,
                backgroundColor: f.backgroundColor,
                grayDiff: f.grayDiff,
                contrastRatio: f.contrastRatio.toFixed(2),
                wcagRequirement: f.wcagRequirement,
                passesGrayCheck: f.passesGrayCheck,
                passesWCAG: f.passesWCAG
            }))
        };
    }

    /**
     * Highlight failing elements (for debugging)
     */
    highlightFailures() {
        this.failures.forEach(failure => {
            failure.element.style.outline = '2px solid red';
            failure.element.title = `Contrast failure: ${failure.grayDiff} gray diff, ${failure.contrastRatio.toFixed(2)} WCAG ratio`;
        });
    }

    /**
     * Remove failure highlights
     */
    removeHighlights() {
        this.failures.forEach(failure => {
            failure.element.style.outline = '';
            failure.element.title = '';
        });
    }

    /**
     * Utility function for manual color contrast checking
     */
    static checkColors(color1, color2) {
        const checker = new ContrastChecker();
        const rgb1 = checker.parseColor(color1);
        const rgb2 = checker.parseColor(color2);
        
        const gray1 = checker.rgbToGrayscale(rgb1.r, rgb1.g, rgb1.b);
        const gray2 = checker.rgbToGrayscale(rgb2.r, rgb2.g, rgb2.b);
        const grayDiff = Math.abs(gray1 - gray2);
        
        const contrastRatio = checker.getContrastRatio(rgb1, rgb2);
        
        return {
            grayDiff: grayDiff,
            passesGrayCheck: grayDiff >= 80,
            contrastRatio: contrastRatio,
            passesWCAG_Normal: contrastRatio >= 4.5,
            passesWCAG_Large: contrastRatio >= 3.0
        };
    }
}

// Initialize contrast checker
const contrastChecker = new ContrastChecker();

// Export for global access
window.ContrastChecker = ContrastChecker;
window.contrastChecker = contrastChecker;

// Add to window for manual testing
window.checkContrast = ContrastChecker.checkColors;

console.log('✅ Contrast checker initialized');