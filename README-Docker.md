# samfedbiz.com Docker Live Testing Setup

**Owner:** Quartermasters FZC | **Stakeholder:** AXIVAI.COM

Enhanced Docker configuration for live testing of the complete Federal BD Platform with all Phase 1-5 features including analytics, quality gates, SFBAI chat, and automated brief engine.

## Quick Start

### Prerequisites
- Docker Desktop installed and running
- docker-compose available
- At least 6GB RAM allocated to Docker (enhanced requirements)

### One-Command Setup
```bash
# Build and start the enhanced platform
docker-compose up --build -d

# Wait for initialization (up to 2 minutes)
docker-compose ps
```

### Manual Setup
```bash
# 1. Create environment file
cp .env.example .env

# 2. Build containers with enhanced features
docker-compose build --no-cache

# 3. Start all services
docker-compose up -d

# 4. Check platform health
curl http://localhost:8080/health
```

## Enhanced Services

| Service | URL | Purpose |
|---------|-----|---------|
| **samfedbiz Platform** | http://localhost:8080 | Federal BD Platform with SFBAI chat |
| **Health Check** | http://localhost:8080/health | Platform status and diagnostics |
| **Analytics Dashboard** | http://localhost:8080/analytics | Metrics and performance data |
| **phpMyAdmin** | http://localhost:8081 | Database management |
| **MailHog** | http://localhost:8025 | Email testing (briefs & outreach) |
| **MySQL** | localhost:3306 | Database server |
| **Redis** | localhost:6379 | Caching (optional) |

## Default Credentials

### Application Login
- **Email:** admin@samfedbiz.com
- **Password:** admin123
- **Role:** admin

### Database Access
- **Host:** localhost:3306
- **Database:** samfedbiz
- **User:** samfedbiz_user
- **Password:** samfedbiz_pass_2025
- **Root Password:** root_pass_2025

## Enhanced Platform Features

### 🎯 Core Features (Fully Functional)
- **SFBAI Chat Interface** - AI-powered federal BD intelligence with slash commands
- **TLS Adapter** - Tactical Law Enforcement Support with micro-catalogs
- **OASIS+ Adapter** - Professional services with pools/domains and capability sheets
- **SEWP Adapter** - IT solutions with groups A/B/C and ordering guides
- **Analytics Dashboard** - Engagement, conversion, content, and reliability metrics
- **Quality Gates** - Automated PHP, JS, CSS, accessibility, and security checks
- **Daily Brief Engine** - News scanning, aggregation, and email distribution
- **Outreach Composer** - AI-generated 120-150 word partnership emails

### 📊 Live Testing Endpoints
- **Dashboard:** http://localhost:8080/ (SFBAI chat + program overview)
- **TLS Program:** http://localhost:8080/programs/tls (tactical equipment)
- **OASIS+ Program:** http://localhost:8080/programs/oasis%2B (professional services)
- **SEWP Program:** http://localhost:8080/programs/sewp (IT marketplace)
- **Analytics:** http://localhost:8080/analytics (Chart.js dashboards)
- **Settings:** http://localhost:8080/settings (admin panel)

### 🔧 Optional Configuration
- **AI Integration** - Add OPENAI_API_KEY or GEMINI_API_KEY for full AI features
- **Google OAuth** - GOOGLE_CLIENT_ID/SECRET for calendar integration
- **Production SMTP** - Replace MailHog with real SMTP for production

## Directory Structure

```
samfedbiz/
├── public/          # Web root
├── src/            # PHP classes and services
├── cron/           # Scheduled tasks
├── database/       # Schema and migrations
├── docker/         # Container configurations
├── styles/         # CSS files
└── js/             # JavaScript files
```

## Development Commands

```bash
# View application logs
docker-compose logs -f app

# Access container shell for debugging
docker-compose exec app bash

# Run quality gates manually
docker exec samfedbiz_app /var/www/html/scripts/quality-gates.sh

# Build production assets
docker exec samfedbiz_app /var/www/html/scripts/build-production.sh

# Check platform health
curl -s http://localhost:8080/health | jq

# Restart specific service
docker-compose restart app

# Stop all services
docker-compose down

# Rebuild with latest changes
docker-compose build --no-cache && docker-compose up -d
```

## Cron Jobs

All cron jobs run in Asia/Dubai timezone:
- **News Scan:** Hourly at :15 minutes
- **Solicitations:** 4x daily at :20 minutes
- **Brief Build:** Daily at 06:00
- **Brief Send:** Daily at 06:05
- **Drive Sync:** 4x daily (01:00, 07:00, 13:00, 19:00)

## Security Features

### Implemented
- CSRF protection on all forms
- Role-based access control (admin, ops, viewer)
- Environment variable masking in settings
- Secure password hashing with bcrypt
- SQL injection prevention with PDO prepared statements

### Security Headers
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- X-XSS-Protection: 1; mode=block
- Content-Security-Policy configured

## API Testing

The platform includes these API endpoints:
- `/api/ai/chat` - SFBAI chat interface
- `/api/drive/sync` - Google Drive synchronization
- `/api/briefs/build` - Daily brief generation
- `/api/settings/test-smtp` - Email configuration testing

## Troubleshooting

### Container Won't Start
```bash
# Check Docker logs
docker-compose logs app

# Verify port availability
netstat -tulpn | grep :8080
```

### Database Connection Issues
```bash
# Check MySQL status
docker-compose exec db mysql -u root -p -e "SHOW DATABASES;"

# Reset database
docker-compose down -v
docker-compose up -d
```

### Permission Issues
```bash
# Fix file permissions
docker-compose exec app chown -R www-data:www-data /var/www/html
```

## Production Deployment

For production deployment:

1. **Update .env** with production values
2. **Configure HTTPS** with proper SSL certificates
3. **Set up proper SMTP** for email delivery
4. **Configure monitoring** and log aggregation
5. **Implement backup** strategies for database
6. **Set resource limits** for containers

## Performance Optimization

The platform is optimized for performance:
- **Glassmorphism UI** with hardware acceleration
- **Lazy loading** for heavy components
- **Database indexing** on key columns
- **Static file caching** with proper headers
- **GSAP animations** with requestAnimationFrame

## Support

For technical support or questions about the Federal BD Platform:

1. Check container logs for error messages
2. Verify all required services are running
3. Ensure API keys are properly configured
4. Test SMTP connectivity via settings panel

---

**samfedbiz.com Federal Business Development Platform**  
*All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.*