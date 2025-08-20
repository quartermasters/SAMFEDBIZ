#!/bin/bash
###############################################################################
# Quality Gates Automation Script
# samfedbiz.com - Federal BD Platform
# Owner: Quartermasters FZC
# Stakeholder: AXIVAI.COM
#
# Runs comprehensive quality checks including PHP lint, JS validation,
# CSS linting, HTML validation, contrast checking, and accessibility audits
###############################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/mnt/d/samfedbiz"
PUBLIC_DIR="$PROJECT_ROOT/public"
SRC_DIR="$PROJECT_ROOT/src"
STYLES_DIR="$PUBLIC_DIR/styles"
JS_DIR="$PUBLIC_DIR/js"
REPORTS_DIR="$PROJECT_ROOT/reports"

# Performance budgets
MAX_JS_SIZE_KB=250
MAX_CSS_SIZE_KB=100
MAX_PHP_ERRORS=0

# Create reports directory
mkdir -p "$REPORTS_DIR"

echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}  samfedbiz.com Quality Gates Automation  ${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""

###############################################################################
# PHP Lint Check
###############################################################################
echo -e "${YELLOW}📋 Running PHP Lint Checks...${NC}"

php_errors=0
php_files_checked=0

# Find all PHP files and check syntax
while IFS= read -r -d '' file; do
    php_files_checked=$((php_files_checked + 1))
    if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "${RED}❌ PHP Syntax Error in: $file${NC}"
        php -l "$file" 2>&1 | grep -v "No syntax errors detected"
        php_errors=$((php_errors + 1))
    fi
done < <(find "$PUBLIC_DIR" "$SRC_DIR" -name "*.php" -type f -print0 2>/dev/null)

if [ $php_errors -eq 0 ]; then
    echo -e "${GREEN}✅ PHP Lint: All $php_files_checked files passed${NC}"
else
    echo -e "${RED}❌ PHP Lint: $php_errors errors in $php_files_checked files${NC}"
fi

echo "" # Add spacing

###############################################################################
# JavaScript Validation
###############################################################################
echo -e "${YELLOW}🔍 Running JavaScript Validation...${NC}"

js_errors=0
js_files_checked=0
total_js_size=0

# Check if Node.js is available for syntax checking
if command -v node >/dev/null 2>&1; then
    while IFS= read -r -d '' file; do
        js_files_checked=$((js_files_checked + 1))
        file_size=$(stat -c%s "$file" 2>/dev/null || stat -f%z "$file" 2>/dev/null || echo 0)
        total_js_size=$((total_js_size + file_size))
        
        if ! node -c "$file" > /dev/null 2>&1; then
            echo -e "${RED}❌ JS Syntax Error in: $file${NC}"
            js_errors=$((js_errors + 1))
        fi
    done < <(find "$JS_DIR" -name "*.js" -type f ! -name "*.min.js" -print0 2>/dev/null)
    
    total_js_size_kb=$((total_js_size / 1024))
    
    if [ $js_errors -eq 0 ]; then
        echo -e "${GREEN}✅ JavaScript: All $js_files_checked files passed syntax check${NC}"
    else
        echo -e "${RED}❌ JavaScript: $js_errors syntax errors found${NC}"
    fi
    
    # Check bundle size
    if [ $total_js_size_kb -le $MAX_JS_SIZE_KB ]; then
        echo -e "${GREEN}✅ JavaScript Bundle: ${total_js_size_kb}KB ≤ ${MAX_JS_SIZE_KB}KB budget${NC}"
    else
        echo -e "${RED}❌ JavaScript Bundle: ${total_js_size_kb}KB > ${MAX_JS_SIZE_KB}KB budget${NC}"
        js_errors=$((js_errors + 1))
    fi
else
    echo -e "${YELLOW}⚠️  Node.js not available, skipping JS syntax checks${NC}"
fi

echo "" # Add spacing

###############################################################################
# CSS Validation
###############################################################################
echo -e "${YELLOW}🎨 Running CSS Validation...${NC}"

css_errors=0
css_files_checked=0
total_css_size=0

while IFS= read -r -d '' file; do
    css_files_checked=$((css_files_checked + 1))
    file_size=$(stat -c%s "$file" 2>/dev/null || stat -f%z "$file" 2>/dev/null || echo 0)
    total_css_size=$((total_css_size + file_size))
    
    # Basic CSS validation - check for obvious syntax errors
    if grep -q "}" "$file" && grep -q "{" "$file"; then
        # Count braces to check for balance
        open_braces=$(grep -o "{" "$file" | wc -l)
        close_braces=$(grep -o "}" "$file" | wc -l)
        
        if [ "$open_braces" -ne "$close_braces" ]; then
            echo -e "${RED}❌ CSS Syntax Error: Unbalanced braces in $file${NC}"
            css_errors=$((css_errors + 1))
        fi
    fi
done < <(find "$STYLES_DIR" -name "*.css" -type f ! -name "*.min.css" -print0 2>/dev/null)

total_css_size_kb=$((total_css_size / 1024))

if [ $css_errors -eq 0 ]; then
    echo -e "${GREEN}✅ CSS: All $css_files_checked files passed basic validation${NC}"
else
    echo -e "${RED}❌ CSS: $css_errors syntax errors found${NC}"
fi

# Check CSS bundle size
if [ $total_css_size_kb -le $MAX_CSS_SIZE_KB ]; then
    echo -e "${GREEN}✅ CSS Bundle: ${total_css_size_kb}KB ≤ ${MAX_CSS_SIZE_KB}KB budget${NC}"
else
    echo -e "${YELLOW}⚠️  CSS Bundle: ${total_css_size_kb}KB > ${MAX_CSS_SIZE_KB}KB budget${NC}"
fi

echo "" # Add spacing

###############################################################################
# HTML5 Validation (Basic)
###############################################################################
echo -e "${YELLOW}📄 Running HTML5 Validation...${NC}"

html_errors=0
html_files_checked=0

# Basic HTML validation - check for common issues
while IFS= read -r -d '' file; do
    html_files_checked=$((html_files_checked + 1))
    
    # Check for DOCTYPE
    if ! grep -q "<!DOCTYPE html>" "$file"; then
        echo -e "${RED}❌ HTML5: Missing DOCTYPE in $file${NC}"
        html_errors=$((html_errors + 1))
    fi
    
    # Check for lang attribute
    if ! grep -q 'html lang=' "$file"; then
        echo -e "${RED}❌ HTML5: Missing lang attribute in $file${NC}"
        html_errors=$((html_errors + 1))
    fi
    
    # Check for charset meta tag
    if ! grep -q 'charset=' "$file"; then
        echo -e "${RED}❌ HTML5: Missing charset meta tag in $file${NC}"
        html_errors=$((html_errors + 1))
    fi
    
    # Check for viewport meta tag
    if ! grep -q 'viewport' "$file"; then
        echo -e "${YELLOW}⚠️  HTML5: Missing viewport meta tag in $file${NC}"
    fi
    
done < <(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l "<!DOCTYPE html>" {} \; | head -20)

if [ $html_errors -eq 0 ]; then
    echo -e "${GREEN}✅ HTML5: All $html_files_checked files passed basic validation${NC}"
else
    echo -e "${RED}❌ HTML5: $html_errors validation errors found${NC}"
fi

echo "" # Add spacing

###############################################################################
# Accessibility Check
###############################################################################
echo -e "${YELLOW}♿ Running Accessibility Checks...${NC}"

a11y_errors=0

# Check for common accessibility issues in PHP files
echo "Checking for accessibility compliance..."

# Check for missing alt attributes
missing_alt_count=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l "<img" {} \; | xargs grep "<img" | grep -v 'alt=' | wc -l)
if [ $missing_alt_count -gt 0 ]; then
    echo -e "${RED}❌ A11y: $missing_alt_count img tags missing alt attributes${NC}"
    a11y_errors=$((a11y_errors + 1))
fi

# Check for form inputs without labels
unlabeled_inputs=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l "<input" {} \; | xargs grep "<input" | grep -v 'aria-label\|aria-labelledby' | grep -v 'type="hidden"\|type="submit"\|type="button"' | wc -l)
if [ $unlabeled_inputs -gt 0 ]; then
    echo -e "${YELLOW}⚠️  A11y: $unlabeled_inputs input elements may be missing labels${NC}"
fi

# Check for skip links
skip_links=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l "skip-link" {} \; | wc -l)
total_html_files=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l "<!DOCTYPE html>" {} \; | wc -l)

if [ $skip_links -lt $total_html_files ]; then
    missing_skip=$((total_html_files - skip_links))
    echo -e "${YELLOW}⚠️  A11y: $missing_skip pages may be missing skip links${NC}"
fi

# Check for ARIA roles
aria_usage=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l 'role=' {} \; | wc -l)
echo -e "${GREEN}✅ A11y: $aria_usage pages use ARIA roles${NC}"

if [ $a11y_errors -eq 0 ]; then
    echo -e "${GREEN}✅ Accessibility: Basic checks passed${NC}"
else
    echo -e "${RED}❌ Accessibility: $a11y_errors critical issues found${NC}"
fi

echo "" # Add spacing

###############################################################################
# Security Check
###############################################################################
echo -e "${YELLOW}🔒 Running Security Checks...${NC}"

security_errors=0

# Check for potential security issues
echo "Checking for security vulnerabilities..."

# Check for SQL injection vulnerabilities (looking for direct SQL)
sql_direct=$(find "$PUBLIC_DIR" "$SRC_DIR" -name "*.php" -type f -exec grep -l '\$_GET\|\$_POST' {} \; | xargs grep -n 'SELECT\|INSERT\|UPDATE\|DELETE' | grep -v 'prepare\|PDO' | wc -l)
if [ $sql_direct -gt 0 ]; then
    echo -e "${RED}❌ Security: $sql_direct potential SQL injection vulnerabilities${NC}"
    security_errors=$((security_errors + 1))
fi

# Check for XSS vulnerabilities (unescaped output)
xss_potential=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l 'echo \$_\|print \$_' {} \; | wc -l)
if [ $xss_potential -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Security: $xss_potential files with potential XSS vulnerabilities${NC}"
fi

# Check for hardcoded credentials
hardcoded_creds=$(find "$PUBLIC_DIR" "$SRC_DIR" -name "*.php" -type f -exec grep -i -n 'password.*=.*["\'][^"\']*["\']' {} \; | grep -v 'password_hash\|password_verify' | wc -l)
if [ $hardcoded_creds -gt 0 ]; then
    echo -e "${RED}❌ Security: $hardcoded_creds potential hardcoded credentials${NC}"
    security_errors=$((security_errors + 1))
fi

# Check for proper CSRF protection
csrf_usage=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l 'csrf' {} \; | wc -l)
form_files=$(find "$PUBLIC_DIR" -name "*.php" -type f -exec grep -l '<form' {} \; | wc -l)
echo -e "${GREEN}✅ Security: CSRF protection found in $csrf_usage files${NC}"

if [ $security_errors -eq 0 ]; then
    echo -e "${GREEN}✅ Security: Basic checks passed${NC}"
else
    echo -e "${RED}❌ Security: $security_errors critical issues found${NC}"
fi

echo "" # Add spacing

###############################################################################
# Performance Budget Check
###############################################################################
echo -e "${YELLOW}⚡ Running Performance Budget Checks...${NC}"

perf_errors=0

# Check total asset sizes
echo "Checking asset sizes against performance budgets..."

# JavaScript budget check (already done above)
if [ $total_js_size_kb -gt $MAX_JS_SIZE_KB ]; then
    perf_errors=$((perf_errors + 1))
fi

# CSS budget check
if [ $total_css_size_kb -gt $MAX_CSS_SIZE_KB ]; then
    echo -e "${YELLOW}⚠️  Performance: CSS bundle ${total_css_size_kb}KB exceeds ${MAX_CSS_SIZE_KB}KB budget${NC}"
fi

# Check for large images
large_images=$(find "$PUBLIC_DIR" -name "*.jpg" -o -name "*.jpeg" -o -name "*.png" -o -name "*.gif" | xargs du -k 2>/dev/null | awk '$1 > 500' | wc -l)
if [ $large_images -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Performance: $large_images images larger than 500KB found${NC}"
fi

if [ $perf_errors -eq 0 ]; then
    echo -e "${GREEN}✅ Performance: All budgets within limits${NC}"
else
    echo -e "${RED}❌ Performance: $perf_errors budget violations${NC}"
fi

echo "" # Add spacing

###############################################################################
# Generate Report
###############################################################################
echo -e "${YELLOW}📊 Generating Quality Report...${NC}"

total_errors=$((php_errors + js_errors + css_errors + html_errors + a11y_errors + security_errors + perf_errors))

cat > "$REPORTS_DIR/quality-report.txt" << EOF
samfedbiz.com Quality Gates Report
Generated: $(date)
===============================================

PHP Lint Check:
- Files checked: $php_files_checked
- Errors: $php_errors

JavaScript Validation:
- Files checked: $js_files_checked
- Syntax errors: $js_errors
- Bundle size: ${total_js_size_kb}KB (Budget: ${MAX_JS_SIZE_KB}KB)

CSS Validation:
- Files checked: $css_files_checked
- Syntax errors: $css_errors
- Bundle size: ${total_css_size_kb}KB (Budget: ${MAX_CSS_SIZE_KB}KB)

HTML5 Validation:
- Files checked: $html_files_checked
- Errors: $html_errors

Accessibility:
- Critical errors: $a11y_errors
- Missing alt attributes: $missing_alt_count
- ARIA usage: $aria_usage pages

Security:
- Critical issues: $security_errors
- CSRF protection: $csrf_usage files

Performance:
- Budget violations: $perf_errors
- Large images: $large_images

TOTAL ERRORS: $total_errors
===============================================
EOF

###############################################################################
# Summary
###############################################################################
echo -e "${BLUE}============================================${NC}"
echo -e "${BLUE}           Quality Gates Summary           ${NC}"
echo -e "${BLUE}============================================${NC}"

if [ $total_errors -eq 0 ]; then
    echo -e "${GREEN}🎉 ALL QUALITY GATES PASSED! 🎉${NC}"
    echo -e "${GREEN}The codebase meets all quality standards.${NC}"
    exit_code=0
else
    echo -e "${RED}❌ QUALITY GATES FAILED${NC}"
    echo -e "${RED}Total errors found: $total_errors${NC}"
    echo ""
    echo -e "${YELLOW}Issues by category:${NC}"
    [ $php_errors -gt 0 ] && echo -e "  • PHP: $php_errors errors"
    [ $js_errors -gt 0 ] && echo -e "  • JavaScript: $js_errors errors"
    [ $css_errors -gt 0 ] && echo -e "  • CSS: $css_errors errors"
    [ $html_errors -gt 0 ] && echo -e "  • HTML5: $html_errors errors"
    [ $a11y_errors -gt 0 ] && echo -e "  • Accessibility: $a11y_errors errors"
    [ $security_errors -gt 0 ] && echo -e "  • Security: $security_errors errors"
    [ $perf_errors -gt 0 ] && echo -e "  • Performance: $perf_errors errors"
    
    exit_code=1
fi

echo ""
echo -e "${BLUE}Report saved to: $REPORTS_DIR/quality-report.txt${NC}"
echo -e "${BLUE}============================================${NC}"

exit $exit_code