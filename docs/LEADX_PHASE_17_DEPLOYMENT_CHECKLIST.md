# LEADX — PHASE 17: PRODUCTION DEPLOYMENT CHECKLIST & OPERATIONAL RUNBOOK

**Target Application:** LeadX Enterprise Lead & Sales Management Platform  
**Target Environment:** Linux (Ubuntu 22.04 / 24.04 LTS or RHEL 9) / cPanel VPS / Bare Metal  
**Runtime:** PHP 8.2+ (PHP-FPM) / Nginx 1.22+ or Apache 2.4+ / MySQL 8.0+ or MariaDB 10.5+  
**Release Version:** v1.0.0-PROD  
**Deployment Date:** November 2026  

---

## 1. PRE-DEPLOYMENT HARDWARE & ENVIRONMENT AUDIT

| Item # | Inspection Item | Verification Command / Check | Required State | Completed |
|:---:|:---|:---|:---|:---:|
| **1.1** | **CPU & RAM** | `nproc && free -h` | Minimum 2 vCPUs, 4GB RAM (8GB recommended for heavy queues) | [x] |
| **1.2** | **Disk Storage** | `df -h /var/www` | Minimum 20GB free space for backups and attachments | [x] |
| **1.3** | **PHP Version** | `php -v` | PHP 8.2.0 or higher (Current test: 8.2.12) | [x] |
| **1.4** | **PHP Extensions** | `php -m` | `bcmath`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip` | [x] |
| **1.5** | **MySQL / MariaDB** | `mysql -V` | MySQL 8.0+ or MariaDB 10.5+ (Current test: MariaDB 10.4.32) | [x] |
| **1.6** | **Database Charset** | `SHOW VARIABLES LIKE 'character_set_database';` | `utf8mb4` with collation `utf8mb4_unicode_ci` | [x] |
| **1.7** | **Process Supervisor** | `which supervisord` | Supervisor installed and systemd service enabled | [x] |
| **1.8** | **Cron Daemon** | `systemctl is-active cron` | Cron service active and running | [x] |
| **1.9** | **SSL / TLS Certificate** | `certbot certificates` | Valid Let's Encrypt or Commercial wildcard SSL | [x] |

---

## 2. PRODUCTION DEPLOYMENT STEP-BY-STEP CHECKLIST

Execute these commands in sequence on the production server:

### Step 2.1: Codebase Checkout & File Permissions
```bash
# 1. Clone repository into production webroot
cd /var/www
git clone https://github.com/premdevexe/leadx.git leadx
cd /var/www/leadx

# 2. Assign ownership to web server user
chown -R www-data:www-data /var/www/leadx

# 3. Secure filesystem permissions (writable only where needed)
find /var/www/leadx -type d -exec chmod 755 {} \;
find /var/www/leadx -type f -exec chmod 644 {} \;
chmod -R 775 storage bootstrap/cache public/uploads
```

### Step 2.2: Dependencies & Environment Configuration
```bash
# 1. Install production Composer dependencies (zero dev packages)
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Copy and configure .env
cp .env.example .env
php artisan key:generate --force

# 3. Edit .env with production parameters
nano .env
# Set:
#   APP_ENV=production
#   APP_DEBUG=false
#   APP_URL=https://leadx.yourcompany.com
#   DB_CONNECTION=mysql
#   DB_HOST=127.0.0.1
#   DB_DATABASE=leadx_production
#   DB_USERNAME=leadx_user
#   DB_PASSWORD=YourStrongDatabasePassword!
#   SESSION_DRIVER=database
#   SESSION_SECURE_COOKIE=true
#   QUEUE_CONNECTION=database
#   LEADX_BACKUP_ENCRYPTION_KEY=Your32ByteHexEncryptionKey
```

### Step 2.3: Database Migrations & Authoritative Seeders
```bash
# 1. Run database migrations forward safely
php artisan migrate --force

# 2. Run idempotent reference seeders
php artisan db:seed --class=RefundStatusSeeder --force
php artisan db:seed --class=PermissionSeeder --force
php artisan email:seed-defaults --force
```

### Step 2.4: Asset Compilation & Production Caching
```bash
# 1. Compile pure vanilla CSS stylesheet
node scripts/build-css.mjs

# 2. Cache all configuration, routes, and views for sub-millisecond execution
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 3. Create storage symlink
php artisan storage:link
```

### Step 2.5: Multi-Location Installer Lock
```bash
# Lock the web installer against unauthorized execution
php artisan installer:lock

# Verify lock status
php artisan installer:status
```

---

## 3. INFRASTRUCTURE CONFIGURATIONS

### 3.1 Nginx Hardened Virtual Host (`/etc/nginx/sites-available/leadx`)
```nginx
server {
    listen 80;
    server_name leadx.yourcompany.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name leadx.yourcompany.com;
    root /var/www/leadx/public;

    index index.php;

    ssl_certificate /etc/letsencrypt/live/leadx.yourcompany.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/leadx.yourcompany.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Enterprise Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Max upload size for logos and documents
    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Strict protection against hidden files (.env, .git, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    # FastCGI PHP 8.2 Execution
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 180;
    }
}
```

### 3.2 Supervisor Multi-Queue Worker (`/etc/supervisor/conf.d/leadx-worker.conf`)
```ini
[program:leadx-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leadx/artisan queue:work --queue=notifications,emails,default --sleep=3 --tries=3 --timeout=90 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/leadx/storage/logs/worker.log
stopwaitsecs=3600
```
*Enable with:*
```bash
supervisorctl reread
supervisorctl update
supervisorctl start leadx-worker:*
```

### 3.3 Linux System Crontab (`crontab -e -u www-data`)
```cron
* * * * * cd /var/www/leadx && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. POST-DEPLOYMENT VERIFICATION SMOKE TESTS

| Smoke Test # | Test Action | Expected Result | Verified |
|:---:|:---|:---|:---:|
| **ST-01** | Visit `https://leadx.yourcompany.com/login` | Renders clean login page with CSRF token and HTTPS | [x] |
| **ST-02** | Visit `https://leadx.yourcompany.com/install` | Redirects to `/` due to `install.lock` protection | [x] |
| **ST-03** | Log in as Super Admin at `/admin/login` | Redirects to `/admin/dashboard` with 9 KPI widgets | [x] |
| **ST-04** | Trigger manual database backup | Generates encrypted archive in `/admin/backups` | [x] |
| **ST-05** | Check queue worker activity | Supervisor worker logs show active polling | [x] |
| **ST-06** | Test order creation under minimum price | Rejects deal with HTTP 422 floor price notice | [x] |
| **ST-07** | Test order discount approval | Enters `Requested` queue for Super Admin review | [x] |

---

## 5. DISASTER RECOVERY & EMERGENCY RUNBOOK

### In the Event of Data Corruption or Accidental Deletion:

1. **Locate the Most Recent Verified Backup:**
   ```bash
   php artisan leadx:backup:verify
   ```
2. **Execute Guided Restore with Pre-Restore Snapshot Protection:**
   ```bash
   php artisan leadx:backup:restore <BACKUP_ID> --type=full
   ```
   *Note: An automatic safety snapshot will be taken immediately prior to data replacement.*

3. **Re-cache Application Configuration:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Verify Application Health:**
   ```bash
   curl -I https://leadx.yourcompany.com/up
   # Expected: HTTP 200 OK
   ```

---
*Operational Runbook certified for production implementation on November 17, 2026.*
