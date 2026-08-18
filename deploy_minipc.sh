#!/bin/bash
# ==============================================================================
# SCRIPT DEPLOYMENT OTOMATIS IGBOT UNTUK MINI PC UBUNTU (HP T620)
# Target Directory: /var/www/meta.damaijaya.my.id
# Cron Job: Jam 00:00 WIB (Auto Bot & Rolling 29-Day Buffer)
# ==============================================================================

set -e

PROJECT_DIR="/var/www/meta.damaijaya.my.id"
cd $PROJECT_DIR

echo "⚙️ Creating Systemd Service 'igbot-web' (Port 8085)..."
sudo tee /etc/systemd/system/igbot-web.service > /dev/null << EOF
[Unit]
Description=IGBot Laravel Server (meta.damaijaya.my.id)
After=network.target

[Service]
User=$USER
WorkingDirectory=/var/www/meta.damaijaya.my.id
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8085
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable igbot-web
sudo systemctl restart igbot-web

# Setup Cron Job Jam 00:00 WIB Otomatis Jalankan Bot & Replenish Buffer 29 Hari
echo "⏰ Setting up Cron Job Jam 00:00 WIB for Auto Bot & Rolling 29-Day Buffer..."
(crontab -l 2>/dev/null | grep -v 'bot:run-daily' ; echo "0 0 * * * cd /var/www/meta.damaijaya.my.id && php artisan bot:run-daily >> /var/www/meta.damaijaya.my.id/storage/logs/cron_bot.log 2>&1") | crontab -

echo "=========================================================="
echo "✅ CRON JOB 00:00 WIB & ROLLING BUFFER 29 HARI BERHASIL DISIAPKAN!"
echo "=========================================================="
