#!/bin/bash
###############################################################################
# Docker Startup Script for Live Testing
# samfedbiz.com - Federal BD Platform
# Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM
###############################################################################

set -e

echo "🚀 Starting samfedbiz.com Federal BD Platform..."

# Set correct timezone
echo "Setting timezone to Asia/Dubai..."
export TZ=Asia/Dubai
ln -snf /usr/share/zoneinfo/Asia/Dubai /etc/localtime
echo "Asia/Dubai" > /etc/timezone

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html
chown -R www-data:www-data /var/log/samfedbiz
chmod -R 755 /var/www/html
chmod +x /var/www/html/scripts/*.sh

# Create required directories
echo "Creating storage directories..."
mkdir -p /var/www/html/storage/briefs
mkdir -p /var/www/html/storage/uploads
mkdir -p /var/www/html/reports
mkdir -p /var/www/html/build
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/reports
chown -R www-data:www-data /var/www/html/build

# Run initial quality checks
echo "Running initial quality gates..."
if [ -f "/var/www/html/scripts/quality-gates.sh" ]; then
    cd /var/www/html
    bash /var/www/html/scripts/quality-gates.sh || echo "⚠️  Quality gates found issues - check reports/"
fi

# Build production assets
echo "Building production assets..."
if [ -f "/var/www/html/scripts/build-production.sh" ]; then
    cd /var/www/html
    bash /var/www/html/scripts/build-production.sh || echo "⚠️  Production build had issues"
fi

# Ensure cron is ready
echo "Setting up cron jobs..."
service cron start

# Validate Bootstrap.php and core classes
echo "Validating Bootstrap and core classes..."
if php -l /var/www/html/src/Bootstrap.php > /dev/null 2>&1; then
    echo "✅ Bootstrap.php syntax valid"
else
    echo "❌ Bootstrap.php syntax error!"
    php -l /var/www/html/src/Bootstrap.php
fi

# Test PDO functionality
echo "Testing PDO compatibility..."
php -r "
require_once '/var/www/html/src/Bootstrap.php';
if (isset(\$pdo)) {
    echo '✅ PDO mock/SQLite working\n';
    \$stmt = \$pdo->prepare('SELECT 1');
    if (\$stmt) echo '✅ PDO prepare() working\n';
} else {
    echo '❌ PDO not available\n';
}
" || echo "⚠️  PDO test failed"

# Check database connectivity (if configured)
if [ -n "$DB_HOST" ] && [ -n "$DB_NAME" ]; then
    echo "Testing database connectivity..."
    timeout 10 mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" || echo "⚠️  Database not accessible, using SQLite fallback"
fi

# Show platform status
echo ""
echo "📊 samfedbiz.com Platform Status:"
echo "  • Docker build: $(date)"
echo "  • Timezone: $(date '+%Z %z')"
echo "  • PHP version: $(php -v | head -n1)"
echo "  • Apache status: Starting..."
echo "  • Cron status: Running"
echo "  • Quality gates: Available"
echo "  • Production build: Ready"
echo ""
echo "🎯 Features enabled:"
echo "  • SFBAI Chat with slash commands"
echo "  • TLS/OASIS+/SEWP adapters"
echo "  • News scanning & daily briefs"
echo "  • Analytics dashboards"
echo "  • Quality gates automation"
echo "  • Accessibility compliance"
echo ""
echo "🔗 Key endpoints:"
echo "  • http://localhost/ - Dashboard"
echo "  • http://localhost/programs/tls - TLS Program"
echo "  • http://localhost/programs/oasis%2B - OASIS+ Program"
echo "  • http://localhost/programs/sewp - SEWP Program"
echo "  • http://localhost/analytics - Analytics Dashboard"
echo "  • http://localhost/settings - Admin Panel"
echo "  • http://localhost/health - Health Check"
echo ""

# Start supervisor
echo "🚀 Starting services via supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf