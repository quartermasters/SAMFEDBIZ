#!/usr/bin/env node
/**
 * Automated Contrast Checker
 * samfedbiz.com - Federal BD Platform
 * 
 * Checks contrast ratios in CSS files against WCAG AA standards
 */

const fs = require('fs');
const path = require('path');

// WCAG AA contrast ratios
const WCAG_AA_NORMAL = 4.5;
const WCAG_AA_LARGE = 3.0;

// Color parsing utilities
function parseColor(color) {
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
    
    // Handle rgb() colors
    const rgbMatch = color.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
    if (rgbMatch) {
        return {
            r: parseInt(rgbMatch[1]),
            g: parseInt(rgbMatch[2]),
            b: parseInt(rgbMatch[3])
        };
    }
    
    // Handle rgba() colors
    const rgbaMatch = color.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*[\d.]+\)/);
    if (rgbaMatch) {
        return {
            r: parseInt(rgbaMatch[1]),
            g: parseInt(rgbaMatch[2]),
            b: parseInt(rgbaMatch[3])
        };
    }
    
    // Handle named colors (basic set)
    const namedColors = {
        'white': { r: 255, g: 255, b: 255 },
        'black': { r: 0, g: 0, b: 0 },
        'red': { r: 255, g: 0, b: 0 },
        'green': { r: 0, g: 128, b: 0 },
        'blue': { r: 0, g: 0, b: 255 },
        'yellow': { r: 255, g: 255, b: 0 },
        'cyan': { r: 0, g: 255, b: 255 },
        'magenta': { r: 255, g: 0, b: 255 },
        'gray': { r: 128, g: 128, b: 128 },
        'grey': { r: 128, g: 128, b: 128 }
    };
    
    return namedColors[color.toLowerCase()] || null;
}

function getLuminance(rgb) {
    const { r, g, b } = rgb;
    const [rs, gs, bs] = [r, g, b].map(c => {
        c = c / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

function getContrastRatio(color1, color2) {
    const rgb1 = parseColor(color1);
    const rgb2 = parseColor(color2);
    
    if (!rgb1 || !rgb2) return null;
    
    const l1 = getLuminance(rgb1);
    const l2 = getLuminance(rgb2);
    
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    
    return (lighter + 0.05) / (darker + 0.05);
}

function checkCSSFile(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const issues = [];
    
    // Extract color declarations
    const colorRegex = /(\w+):\s*(#[0-9a-fA-F]{3,6}|rgb\([^)]+\)|rgba\([^)]+\)|[a-zA-Z]+);/g;
    const matches = [...content.matchAll(colorRegex)];
    
    // Group by selectors to find text/background pairs
    const selectors = {};
    let currentSelector = '';
    
    const lines = content.split('\n');
    let inRule = false;
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        
        if (line.includes('{')) {
            currentSelector = line.replace('{', '').trim();
            inRule = true;
            if (!selectors[currentSelector]) {
                selectors[currentSelector] = {};
            }
        } else if (line.includes('}')) {
            inRule = false;
            currentSelector = '';
        } else if (inRule && line.includes(':')) {
            const [property, value] = line.split(':').map(s => s.trim());
            const cleanValue = value.replace(';', '').replace('!important', '').trim();
            
            if (property === 'color') {
                selectors[currentSelector].color = cleanValue;
            } else if (property === 'background-color' || property === 'background') {
                selectors[currentSelector].backgroundColor = cleanValue;
            }
        }
    }
    
    // Check contrast ratios
    for (const [selector, colors] of Object.entries(selectors)) {
        if (colors.color && colors.backgroundColor) {
            const ratio = getContrastRatio(colors.color, colors.backgroundColor);
            
            if (ratio !== null) {
                const isLargeText = selector.includes('h1') || selector.includes('h2') || 
                                 selector.includes('.large') || selector.includes('.title');
                const requiredRatio = isLargeText ? WCAG_AA_LARGE : WCAG_AA_NORMAL;
                
                if (ratio < requiredRatio) {
                    issues.push({
                        selector,
                        foreground: colors.color,
                        background: colors.backgroundColor,
                        ratio: ratio.toFixed(2),
                        required: requiredRatio,
                        type: isLargeText ? 'large' : 'normal',
                        file: filePath
                    });
                }
            }
        }
    }
    
    return issues;
}

function main() {
    const projectRoot = process.argv[2] || '/mnt/d/samfedbiz';
    const stylesDir = path.join(projectRoot, 'public', 'styles');
    
    console.log('🎨 Automated Contrast Checker');
    console.log('=============================');
    console.log(`Scanning: ${stylesDir}`);
    console.log('');
    
    if (!fs.existsSync(stylesDir)) {
        console.error(`❌ Styles directory not found: ${stylesDir}`);
        process.exit(1);
    }
    
    const cssFiles = fs.readdirSync(stylesDir)
        .filter(file => file.endsWith('.css'))
        .map(file => path.join(stylesDir, file));
    
    let totalIssues = 0;
    let filesChecked = 0;
    
    for (const file of cssFiles) {
        filesChecked++;
        const issues = checkCSSFile(file);
        
        if (issues.length > 0) {
            console.log(`❌ ${path.basename(file)}: ${issues.length} contrast issues`);
            
            for (const issue of issues) {
                console.log(`   ${issue.selector}`);
                console.log(`   Foreground: ${issue.foreground}`);
                console.log(`   Background: ${issue.background}`);
                console.log(`   Ratio: ${issue.ratio}:1 (required: ${issue.required}:1 for ${issue.type} text)`);
                console.log('');
            }
            
            totalIssues += issues.length;
        } else {
            console.log(`✅ ${path.basename(file)}: No contrast issues`);
        }
    }
    
    console.log('=============================');
    console.log(`Files checked: ${filesChecked}`);
    console.log(`Total contrast issues: ${totalIssues}`);
    
    if (totalIssues === 0) {
        console.log('🎉 All contrast ratios meet WCAG AA standards!');
        process.exit(0);
    } else {
        console.log(`❌ ${totalIssues} contrast issues need to be fixed`);
        process.exit(1);
    }
}

if (require.main === module) {
    main();
}

module.exports = { getContrastRatio, checkCSSFile };