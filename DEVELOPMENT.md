# samfedbiz.com Development Guide

**Owner:** Quartermasters FZC | **Stakeholder:** AXIVAI.COM

## Quick Start

### 1. Launch Development Environment
```bash
# Start all services
./docker-startup.sh

# Verify everything is working
./docker-test.sh
```

### 2. Access the Platform
- **Application:** http://localhost:8080
- **Login:** admin@samfedbiz.com / admin123

### 3. Test Core Features
1. **Dashboard** - SFBAI chatbox with glassmorphism design
2. **Settings** - Program toggles, OAuth status, subscriber management
3. **Brief Archive** - Daily briefs with reliability indicators
4. **Research Docs** - Document management interface

## Architecture Overview

```
samfedbiz.com Federal BD Platform
├── Phase 0: Foundation (✅ Complete)
│   ├── Database schema with all tables
│   ├── Authentication system (admin/ops/viewer)
│   ├── Program registry with toggles
│   └── Environment variables system
├── Section 1: Dashboard Hero (✅ Complete)
│   ├── Glassmorphism design system
│   ├── GSAP 3.x animations
│   ├── SFBAI chat interface
│   └── Accessibility compliance
├── Section 2: Program Overview (✅ Complete)
│   ├── TLS/OASIS+/SEWP program pages
│   ├── Tabler Icons integration
│   ├── Hover tilt animations
│   └── Context injection for SFBAI
├── Section 3: Holder Profiles (✅ Complete)
│   ├── Micro-catalog/capability sheets
│   ├── Print CSS for one-page catalogs
│   ├── Draft email functionality
│   └── Google Calendar integration
├── Section 4: Solicitations (✅ Complete)
│   ├── Filtering and AI summaries
│   ├── Compliance checklists
│   ├── Normalize() function
│   └── /opps slash command
├── Section 5: Research Docs (✅ Complete)
│   ├── Google Drive sync
│   ├── AI summarization → Notes
│   ├── PII protection in logs
│   └── Search with highlighting
├── Section 6: Daily Briefs (✅ Complete)
│   ├── Build/send/archive engine
│   ├── Asia/Dubai timezone cron jobs
│   ├── Email templates
│   └── Signals & Rumors labeling
└── Section 7: Settings/Admin (✅ Complete)
    ├── Program toggles
    ├── Environment variable masking
    ├── Holder blacklist management
    └── Subscriber management
```

## Key Technologies

### Frontend
- **Design:** Glassmorphism with backdrop-filter effects
- **Animations:** GSAP 3.x with ScrollTrigger and tilt effects
- **Smooth Scroll:** Lenis for buttery smooth scrolling
- **Icons:** Tabler Icons as inline SVG
- **Accessibility:** WCAG AA compliance with focus management

### Backend
- **PHP:** 8.2 with modern practices
- **Database:** MySQL 8.0 with prepared statements
- **Security:** CSRF protection, bcrypt hashing, role-based access
- **APIs:** Google Drive, AI services (OpenAI/Gemini)
- **Email:** SMTP with MailHog for testing

### Infrastructure
- **Containers:** Docker with multi-service setup
- **Cron:** Automated tasks in Asia/Dubai timezone
- **Monitoring:** Health checks and activity logging
- **Cache:** Redis for session storage (optional)

## Security Implementation

### ✅ Implemented
- **Authentication:** Secure login with session management
- **Authorization:** Role-based access (admin/ops/viewer)
- **CSRF Protection:** All forms protected with tokens
- **SQL Injection:** PDO prepared statements throughout
- **Password Security:** bcrypt with salt
- **Environment Variables:** Masked in UI, never exposed client-side
- **Input Validation:** Sanitization and validation on all inputs
- **Security Headers:** CSP, XSS protection, frame options

### 🔒 Security Headers Applied
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY  
X-XSS-Protection: 1; mode=block
Content-Security-Policy: Configured for inline scripts/styles
Referrer-Policy: strict-origin-when-cross-origin
```

## Performance Features

### Frontend Optimizations
- **First Contentful Paint:** <2000ms budget
- **JavaScript Bundle:** <250KB total
- **Static Caching:** 30-day cache for assets
- **Hardware Acceleration:** CSS transforms and opacity
- **Lazy Loading:** Defer heavy components

### Backend Optimizations
- **Database Indexing:** All foreign keys and search fields
- **Query Optimization:** Prepared statements with limits
- **Session Management:** Efficient token handling
- **File Permissions:** Proper Apache/PHP-FPM setup

## Development Workflow

### Making Changes

1. **Edit Files:** Changes sync via Docker volumes
2. **Test Locally:** Use http://localhost:8080
3. **Check Logs:** `docker-compose logs -f app`
4. **Database Changes:** Via phpMyAdmin at http://localhost:8081

### Common Tasks

```bash
# Restart application
docker-compose restart app

# View application logs  
docker-compose logs -f app

# Access container shell
docker-compose exec app bash

# Run PHP commands
docker-compose exec app php -v

# Check cron jobs
docker-compose exec app crontab -l

# Test email delivery
# Check MailHog at http://localhost:8025
```

### Testing Features

#### Authentication System
```bash
# Test login
curl -X POST http://localhost:8080/auth/login.php \
  -d "email=admin@samfedbiz.com&password=admin123"
```

#### API Endpoints
```bash
# Test health check
curl http://localhost:8080/docker/healthcheck.php

# Test CSRF protection
curl -X POST http://localhost:8080/api/settings/test-smtp
# Should return 403 without proper token
```

#### Database Operations
```sql
-- Connect via phpMyAdmin or CLI
-- Check user roles
SELECT name, email, role FROM users;

-- Check program status  
SELECT code, name, enabled FROM programs;

-- Check subscriber stats
SELECT 
  COUNT(*) as total,
  SUM(active) as active,
  SUM(verified) as verified 
FROM subscribers;
```

## Feature Configuration

### AI Integration
```bash
# Add to .env file
OPENAI_API_KEY=sk-your-key-here
GEMINI_API_KEY=your-gemini-key

# Test SFBAI chat functionality
# Login and use the dashboard chatbox
```

### Google Drive Sync
```bash  
# Configure OAuth in .env
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8080/api/oauth/google/callback

# Test in Settings → OAuth Integration
```

### Email Configuration
```bash
# For production SMTP
SMTP_HOST=smtp.your-provider.com
SMTP_USER=noreply@your-domain.com
SMTP_PASS=your-password

# For testing (already configured)
SMTP_HOST=mailhog
SMTP_PORT=1025
```

## Troubleshooting

### Common Issues

#### Port Conflicts
```bash
# Check what's using port 8080
sudo lsof -i :8080

# Use different ports
docker-compose down
# Edit docker-compose.yml ports
docker-compose up -d
```

#### Database Connection
```bash
# Reset database
docker-compose down -v
docker-compose up -d

# Wait for MySQL to initialize (~30 seconds)
```

#### File Permissions
```bash
# Fix ownership
docker-compose exec app chown -R www-data:www-data /var/www/html
```

#### Cron Jobs Not Running
```bash
# Check cron status
docker-compose exec app service cron status

# View cron logs
docker-compose logs app | grep cron
```

### Performance Debugging

#### Check Resource Usage
```bash
# Container stats
docker stats

# Database performance
docker-compose exec db mysqladmin processlist
```

#### Frontend Debugging
```bash
# Check browser developer tools
# Network tab: Verify <2s load times
# Performance tab: Check JavaScript bundle size <250KB
```

## Production Readiness

### Before Deployment
- [ ] Configure production API keys
- [ ] Set up proper SMTP service
- [ ] Configure HTTPS/SSL certificates  
- [ ] Set up database backups
- [ ] Configure monitoring/alerts
- [ ] Test disaster recovery procedures
- [ ] Verify all security headers
- [ ] Performance test under load

### Deployment Checklist
- [ ] Environment variables configured
- [ ] Database schema deployed
- [ ] SSL certificates installed
- [ ] Cron jobs scheduled
- [ ] Monitoring configured
- [ ] Backup procedures tested
- [ ] Security scan completed
- [ ] Performance benchmarks met

---

**samfedbiz.com Federal Business Development Platform**  
*All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.*