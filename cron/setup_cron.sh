#!/bin/bash
# Cron Setup Script for samfedbiz.com
# Owner: Quartermasters FZC | Stakeholder: AXIVAI.COM
# Sets up cron jobs and log directories in Asia/Dubai timezone

echo "Setting up samfedbiz.com cron jobs..."

# Create log directory
sudo mkdir -p /var/log/samfedbiz
sudo chown www-data:www-data /var/log/samfedbiz
sudo chmod 755 /var/log/samfedbiz

# Create individual log files with proper permissions
sudo touch /var/log/samfedbiz/news_scan.log
sudo touch /var/log/samfedbiz/solicitations_ingest.log
sudo touch /var/log/samfedbiz/brief_build.log
sudo touch /var/log/samfedbiz/brief_send.log
sudo touch /var/log/samfedbiz/drive_sync.log
sudo touch /var/log/samfedbiz/db_maintenance.log

# Set permissions
sudo chown www-data:www-data /var/log/samfedbiz/*.log
sudo chmod 644 /var/log/samfedbiz/*.log

# Make PHP scripts executable
chmod +x /mnt/d/samfedbiz/cron/*.php

# Test PHP scripts for syntax errors
echo "Testing PHP scripts for syntax errors..."
php -l /mnt/d/samfedbiz/cron/news_scan.php
php -l /mnt/d/samfedbiz/cron/solicitations_ingest.php
php -l /mnt/d/samfedbiz/cron/brief_build.php
php -l /mnt/d/samfedbiz/cron/brief_send.php
php -l /mnt/d/samfedbiz/cron/drive_sync.php
php -l /mnt/d/samfedbiz/cron/db_maintenance.php

# Install crontab (backup existing first)
crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S) 2>/dev/null || true
crontab /mnt/d/samfedbiz/cron/crontab.txt

echo "Cron jobs installed successfully!"
echo "Current crontab:"
crontab -l

echo ""
echo "IMPORTANT NOTES:"
echo "1. All cron jobs run in Asia/Dubai timezone"
echo "2. Logs are stored in /var/log/samfedbiz/"
echo "3. Brief build runs at 06:00 Dubai time"
echo "4. Brief send runs at 06:05 Dubai time"
echo "5. Drive sync runs 4x daily (01:00, 07:00, 13:00, 19:00)"
echo "6. News scan runs hourly at :15 minutes"
echo "7. Solicitations ingest runs 4x daily at :20 minutes"
echo ""
echo "To monitor cron jobs:"
echo "  sudo tail -f /var/log/samfedbiz/brief_build.log"
echo "  sudo tail -f /var/log/samfedbiz/brief_send.log"
echo ""
echo "Setup completed!"