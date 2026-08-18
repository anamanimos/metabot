#!/bin/bash
# ==============================================================================
# KONFIGURASI NGINX UNTUK DOMAIN meta.damaijaya.my.id (PHP 8.3 FPM)
# ==============================================================================

sudo tee /etc/nginx/sites-available/meta.damaijaya.my.id > /dev/null << 'EOF'
server {
    listen 80;
    listen 8100;
    server_name meta.damaijaya.my.id;

    root /var/www/meta.damaijaya.my.id/public;
    index index.php index.html index.htm;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Symlink ke sites-enabled
sudo ln -sf /etc/nginx/sites-available/meta.damaijaya.my.id /etc/nginx/sites-enabled/

# Test & Reload Nginx
sudo nginx -t
sudo systemctl reload nginx

echo "✅ Nginx Virtual Host untuk meta.damaijaya.my.id Berhasil Diaktifkan!"
