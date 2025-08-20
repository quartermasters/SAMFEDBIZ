#!/bin/bash
# samfedbiz.com Docker Startup Script
# Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM
# Quick setup for live testing environment

set -e

echo "🚀 Starting samfedbiz.com Docker Environment"
echo "Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM"
echo "================================================"

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo "❌ docker-compose not found. Please install docker-compose."
    exit 1
fi

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file from template..."
    cp .env.example .env
    echo "✅ Created .env file. You may need to configure API keys for full functionality."
fi

# Build and start containers
echo "🔨 Building Docker containers..."
docker-compose build

echo "🚀 Starting services..."
docker-compose up -d

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Check service status
echo "📋 Service Status:"
echo "=================="
docker-compose ps

echo ""
echo "🎉 samfedbiz.com is now running!"
echo "================================"
echo "🌐 Application:    http://localhost:8080"
echo "🗄️  phpMyAdmin:    http://localhost:8081"
echo "📧 MailHog:        http://localhost:8025"
echo ""
echo "📝 Default Login:"
echo "Email:    admin@samfedbiz.com"
echo "Password: admin123"
echo ""
echo "🛠️  Useful Commands:"
echo "View logs:     docker-compose logs -f app"
echo "Stop services: docker-compose down"
echo "Restart:       docker-compose restart"
echo "Shell access:  docker-compose exec app bash"
echo ""
echo "📊 Database Connection:"
echo "Host: localhost:3306"
echo "Database: samfedbiz"
echo "User: samfedbiz_user"
echo "Password: samfedbiz_pass_2025"
echo ""
echo "💡 Note: Configure API keys in .env for AI features and Google Drive integration"