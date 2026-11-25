# LeadX Installation Guide

**Version:** 1.0
**Last Updated:** 2025
**Application:** LeadX Laravel 12

---

## Table of Contents

1. [Web Server Document Root](#web-server-document-root)
2. [Server Requirements](#server-requirements)
3. [Installation Steps](#installation-steps)
4. [Database Configuration](#database-configuration)
5. [Initial Admin Setup](#initial-admin-setup)
6. [Environment Configuration](#environment-configuration)
7. [Scheduler Configuration](#scheduler-configuration)
8. [Queue Worker Configuration](#queue-worker-configuration)
9. [Storage Permissions](#storage-permissions)
10. [Production Setup](#production-setup)
11. [Troubleshooting](#troubleshooting)

---

## Web Server Document Root

Configuring the correct document root is critical to prevent exposing `.env`, `config/`, `vendor/`, `database/`, or `docs/` over HTTP.

1. **PREFERRED (VPS / Nginx / Apache vhost):** Point your web server document root directly at `<repo>/public` and delete or ignore the root `index.php` shim. This requires zero `.htaccess` reliance.
2. **FALLBACK (shared cPanel hosting only):** If your host forces document root to the repository root, you **must** run Apache with both `mod_rewrite` enabled and `AllowOverride All` active so the root `.htaccess` rules protect sensitive directories and rewrite into `public/`.
   - **Mandatory verification test:** After deploying, visit `https://<your-site>/composer.json` in a browser or via `curl -I`. It **must** return HTTP `403` or `404`. If `composer.json` downloads, your server is misconfigured and all project files are publicly readable.
3. **Nginx users:** Nginx ignores `.htaccess` files entirely — you **must** set `root /path/to/LeadX/public;` in your server block (never the repository root).

---

## 1. Server Requirements

### Minimum Requirements

- **PHP:** 8.2 or higher
- **Laravel:** 12.x
- **Database:** MySQL 8.0+ or MariaDB 10.5+
- **Composer:** Latest stable version
- **Web Server:** Nginx or Apache with URL rewriting

### Required PHP Extensions

```bash
php -m | grep -E "openssl|pdo|mbstring|tokenizer|json|ctype|fileinfo|bcmath|xml"
```

Required extensions:
- OpenSSL
- PDO MySQL
- Mbstring
- Tokenizer
- JSON
- Ctype
- Fileinfo
- BCMath (for financial calculations)
- XML

### Recommended Server Configuration

```ini
; php.ini settings
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
max_execution_time = 60
```

---

## 2. Installation Steps

### Option A: Web-Based Installer (Recommended)

1. **Upload files** to your web server
2. **Set document root** to the `public/` directory
3. **Visit** `http://your-domain.com/install` in your browser
4. **Follow the wizard:**
   - Step 1: Environment Requirements Check
   - Step 2: Application Configuration
   - Step 3: Database Configuration
   - Step 4: Database Installation (run migrations)
   - Step 5: Create Super Admin
   - Step 6: System Configuration (optional integrations)
   - Step 7: Final Verification
   - Step 8: Complete Installation

### Option B: Manual Installation (CLI)

```bash
# 1. Clone repository
git clone <repository-url> LeadX
cd LeadX

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Create environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Configure database in .env
# Edit .env file with your database credentials:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=leadx
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Run migrations
php artisan migrate --force

# 7. Seed database (optional)
php artisan db:seed --force

# 8. Create storage link
php artisan storage:link

# 9. Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 10. Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 3. Database Configuration

### Create Database

```sql
CREATE DATABASE leadx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'leadx_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON leadx.* TO 'leadx_user'@'localhost';
FLUSH PRIVILEGES;
```

### .env Database Settings

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=leadx
DB_USERNAME=leadx_user
DB_PASSWORD=secure_password
```

### Test Connection

```bash
php artisan db:show
```

---

## 4. Initial Admin Setup

### Using the Installer

The installer will guide you through creating the first Super Admin account.

### Manual Creation (CLI)

```bash
php artisan tinker

>>> \App\Models\User::create([
...     'name' => 'Admin User',
...     'email' => 'admin@example.com',
...     'username' => 'admin',
...     'password' => \Illuminate\Support\Facades\Hash::make('secure_password'),
...     'role' => 'admin',
...     'is_active' => true,
...     'email_verified_at' => now(),
... ]);
```

### Admin Access

- **URL:** `http://your-domain.com/admin/login`
- **Username:** Your chosen username
- **Password:** Your chosen password

---

## 5. Environment Configuration

### Application Settings (.env)

```env
APP_NAME="LeadX"
APP_ENV=production
APP_KEY=base64:GENERATED_BY_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://your-domain.com

APP_TIMEZONE=UTC
APP_CURRENCY=USD
APP_CURRENCY_SYMBOL=$
APP_LOCALE=en
```

### Security Settings

```env
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

---

## 6. Scheduler Configuration

### Enable Laravel Scheduler

Add to crontab (`crontab -e`):

```bash
* * * * * cd /path/to/LeadX && php artisan schedule:run --no-interaction >> /dev/null 2>&1
```

### Scheduled Tasks

LeadX includes the following scheduled tasks:

- **Subscription expiry notifications** - Checks for expiring subscriptions
- **Cleanup jobs** - Removes old logs and temporary files
- **Queue processing** - Processes queued jobs (if using database/redis queue)

---

## 7. Queue Worker Configuration

### Using Database Queue

```bash
# .env
QUEUE_CONNECTION=database

# Create queue table
php artisan queue:table
php artisan migrate

# Run worker
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

### Supervisor Configuration (Production)

Create `/etc/supervisor/conf.d/leadx-worker.conf`:

```ini
[program:leadx-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/LeadX/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/LeadX/storage/logs/worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start leadx-worker:*
```

---

## 8. Storage Permissions

### Set Correct Permissions

```bash
# Set ownership (adjust www-data to your web server user)
sudo chown -R www-data:www-data storage bootstrap/cache

# Set permissions
sudo chmod -R 775 storage bootstrap/cache

# Create symbolic link for public uploads
php artisan storage:link
```

### Verify Permissions

```bash
ls -la storage/
# Should show: drwxrwxr-x  www-data www-data

ls -la public/storage  # (if link exists)
# Should show: lrwxrwxrwx  public/storage -> ../storage/app/public
```

---

## 9. Production Setup

### Security Checklist

- [ ] Set `APP_DEBUG=false` in .env
- [ ] Set strong `APP_KEY` (run `php artisan key:generate`)
- [ ] Use HTTPS (set `SESSION_SECURE_COOKIE=true`)
- [ ] Restrict `.env` file access (add to web server config)
- [ ] Disable directory listing
- [ ] Use firewall to restrict database access
- [ ] Regular backups of database and storage

### Performance Optimization

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### Web Server Configuration

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/LeadX/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(env|log|sql)$ {
        deny all;
    }
}
```

---

## 10. Troubleshooting

### Installation Won't Start

**Symptom:** Redirected to `/install` even after installation

**Solution:** Check for lock file:
```bash
ls -la storage/app/installed.lock
# If exists and you want to reinstall:
rm storage/app/installed.lock
```

### Database Connection Fails

**Check:**
1. MySQL/MariaDB is running
2. Credentials in `.env` are correct
3. Database exists
4. User has proper permissions

```bash
# Test connection manually
mysql -u username -p -h hostname database_name
```

### Migration Fails

**Solution:**
```bash
# Check migration status
php artisan migrate:status

# Rollback and retry
php artisan migrate:rollback
php artisan migrate --force
```

### Permission Denied Errors

**Solution:**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 404 Errors on Routes

**Solution:**
```bash
# Clear and rebuild route cache
php artisan route:clear
php artisan route:cache
```

### 500 Internal Server Error

**Check:**
1. Laravel logs: `storage/logs/laravel.log`
2. Web server error logs
3. `.env` file is readable
4. `APP_KEY` is set

```bash
# View recent logs
tail -f storage/logs/laravel.log

# Clear all caches
php artisan optimize:clear
```

### Assets Not Loading

**Solution:**
```bash
# Ensure public/assets/ exists and contains CSS/JS
ls -la public/assets/css/app.css
ls -la public/assets/js/app.js

# If missing, rebuild (no build step needed - assets are already in public/)
```

---

## Support

For issues and questions:
- Check the [LeadX Documentation](docs/)
- Review the [Migration Report](docs/LEADX_PURE_FRONTEND_MIGRATION_REPORT.md)
- Check Laravel logs in `storage/logs/`

---

*Guide generated for LeadX Laravel 12.*
