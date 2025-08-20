#!/bin/bash
# samfedbiz.com Docker Test Script
# Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM
# Validates Docker container functionality

set -e

echo "🧪 Testing samfedbiz.com Docker Environment"
echo "Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM"
echo "=============================================="

# Test application response
echo "📡 Testing application response..."
if curl -s -f http://localhost:8080 > /dev/null; then
    echo "✅ Application responding on port 8080"
else
    echo "❌ Application not responding on port 8080"
    exit 1
fi

# Test database connection
echo "🗄️  Testing database connection..."
if docker-compose exec -T db mysql -u samfedbiz_user -psamfedbiz_pass_2025 -e "SELECT 1;" samfedbiz > /dev/null 2>&1; then
    echo "✅ Database connection successful"
else
    echo "❌ Database connection failed"
    exit 1
fi

# Test phpMyAdmin
echo "🔧 Testing phpMyAdmin..."
if curl -s -f http://localhost:8081 > /dev/null; then
    echo "✅ phpMyAdmin responding on port 8081"
else
    echo "❌ phpMyAdmin not responding on port 8081"
    exit 1
fi

# Test MailHog
echo "📧 Testing MailHog..."
if curl -s -f http://localhost:8025 > /dev/null; then
    echo "✅ MailHog responding on port 8025"
else
    echo "❌ MailHog not responding on port 8025"
    exit 1
fi

# Test health check endpoint
echo "💚 Testing health check..."
if docker-compose exec -T app php /var/www/html/docker/healthcheck.php > /dev/null 2>&1; then
    echo "✅ Health check passed"
else
    echo "❌ Health check failed"
    exit 1
fi

# Test cron jobs setup
echo "⏰ Testing cron configuration..."
if docker-compose exec -T app crontab -l | grep -q "samfedbiz"; then
    echo "✅ Cron jobs configured"
else
    echo "❌ Cron jobs not configured"
    exit 1
fi

# Test file permissions
echo "🔐 Testing file permissions..."
if docker-compose exec -T app test -w /var/log/samfedbiz; then
    echo "✅ Log directory writable"
else
    echo "❌ Log directory not writable"
    exit 1
fi

# Test PHP syntax on key files
echo "🐘 Testing PHP syntax..."
php_files=(
    "/var/www/html/public/index.php"
    "/var/www/html/public/settings/index.php"
    "/var/www/html/public/briefs/index.php"
    "/var/www/html/cron/brief_build.php"
)

for file in "${php_files[@]}"; do
    if docker-compose exec -T app php -l "$file" > /dev/null 2>&1; then
        echo "✅ $(basename "$file") syntax OK"
    else
        echo "❌ $(basename "$file") syntax error"
        exit 1
    fi
done

echo ""
echo "🎉 All tests passed!"
echo "==================="
echo "✅ Application: http://localhost:8080"
echo "✅ Database: localhost:3306"
echo "✅ phpMyAdmin: http://localhost:8081"
echo "✅ MailHog: http://localhost:8025"
echo "✅ Health checks: Passing"
echo "✅ Cron jobs: Configured"
echo "✅ File permissions: Correct"
echo "✅ PHP syntax: Valid"
echo ""
echo "🚀 samfedbiz.com is ready for live testing!"
echo ""
echo "📝 Next steps:"
echo "1. Configure API keys in .env file"
echo "2. Test authentication: admin@samfedbiz.com / admin123"
echo "3. Explore the settings panel"
echo "4. Test brief generation and email features"