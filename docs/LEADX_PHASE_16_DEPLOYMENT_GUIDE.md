# LEADX — PHASE 16: PRODUCTION DEPLOYMENT & OPERATIONS GUIDE

**Author:** Senior Laravel DevOps Engineer & Infrastructure Architect  
**Project:** LeadX Enterprise  
**Target Framework:** Laravel 12.0  
**Runtime Requirement:** PHP 8.2+  
**Database:** MySQL 8.0+ / MariaDB 10.5+  
**Operating Systems:** Ubuntu 22.04/24.04 LTS, Debian 12, RHEL 9, AlmaLinux 9  
**Status:** Production Ready  
**Date:** November 2026  

---

## 1. PRE-DEPLOYMENT PREREQUISITES

Before deploying Phase 16 to a staging or production server, ensure the hosting environment satisfies all necessary system packages, PHP extensions, and permissions.

### 1.1 Mandatory System Packages & PHP Extensions
```bash
# Verify PHP version (Must be 8.2 or higher)
php -v

# Required PHP Extensions:
# - php8.2-cli
# - php8.2-fpm
# - php8.2-mysql
# - php8.2-mbstring
# - php8.2-xml
# - php8.2-curl
# - php8.2-zip        (Required for Storage & Backup Archiving)
# - php8.2-bcmath
# - php8.2-intl
# - openssl           (Required for AES-256-CBC Encrypted Backups)
```

Verify that both `zip` and `openssl` are installed and enabled:
```bash
php -m | grep -E 'zip|openssl'
```

---

## 2. ENVIRONMENT CONFIGURATION (`.env`)

Review your production `.env` file to ensure the following Phase 16 environment variables are properly configured.

### 2.1 Core Security & Encryption Keys
```ini
APP_NAME=LeadX
APP_ENV=production
APP_DEBUG=false
APP_URL=https://leadx.yourdomain.com

# Standard Laravel Application Key
APP_KEY=base64:YOUR_APP_KEY_HERE

# Dedicated Backup Encryption Key (Optional - falls back to APP_KEY if omitted)
# Generate a 32-character high-entropy secret string
BACKUP_ENCRYPTION_KEY=your-ultra-secure-32-char-secret-key-here

# Session Security (Enforce HTTPS cookies in production)
SESSION_SECURE_COOKIE=true
```

### 2.2 Backup Storage Destination Configuration
By default, backups are safely written to `storage/app/backups` on the local filesystem. For offsite disaster recovery, configure an external cloud disk:

```ini
# Backup Storage Disk: local | s3 | r2 | sftp
BACKUP_DISK=local

# If using Amazon S3:
AWS_ACCESS_KEY_ID=your-aws-access-key-id
AWS_SECRET_ACCESS_KEY=your-aws-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=leadx-production-backups
AWS_USE_PATH_STYLE_ENDPOINT=false

# If using Cloudflare R2:
# AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
# AWS_BUCKET=leadx-backups
```

### 2.3 Queue & Background Jobs
```ini
# Ensure queue driver is set to database (or redis in high-throughput clusters)
QUEUE_CONNECTION=database
```

---

## 3. STEP-BY-STEP DEPLOYMENT WORKFLOW

### Step 1: Place Application into Maintenance Mode
```bash
cd /var/www/leadx
php artisan down --secret="leadx-admin-bypass-token" --render="errors::503"
```

### Step 2: Pull Latest Code & Install Composer Dependencies
```bash
git fetch origin main
git checkout main
git pull origin main

# Install optimized, production-only dependencies
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

### Step 3: Run Database Migrations
Phase 16 introduces `backups` and `backup_restore_logs` tables without altering or deleting any existing application data:
```bash
php artisan migrate --force
```

### Step 4: Configure Storage Directories & Directory Permissions
Ensure the web server user (`www-data` or `nginx`) owns the storage directory and that the `backups` directory is present and locked down:
```bash
mkdir -p storage/app/backups
mkdir -p storage/app/public
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs

# Set ownership and restrictive permissions
chown -R www-data:www-data /var/www/leadx/storage /var/www/leadx/bootstrap/cache
chmod -R 775 /var/www/leadx/storage /var/www/leadx/bootstrap/cache

# Restrict the backups folder to prevent public web exposure
chmod 700 /var/www/leadx/storage/app/backups
```

### Step 5: Warm Up Laravel Performance Caches
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 6: Bring Application Back Online
```bash
php artisan up
```

---

## 4. SCHEDULER & CRON CONFIGURATION

LeadX relies on the Laravel Scheduler to run automated daily encrypted backups, prune stale archives beyond 30 days, process subscription expirations, and sync reminder emails.

Open the system crontab for the web server user:
```bash
sudo crontab -u www-data -e
```

Add the following standard single line (substituting `/var/www/leadx` with your real deployment path):
```cron
* * * * * cd /var/www/leadx && php artisan schedule:run >> /dev/null 2>&1
```

### Phase 16 Scheduled Automation Tasks
The scheduler automatically executes:
1. **`leadx:backup`** (Daily at 02:00 UTC): Creates an encrypted full database and storage backup. Protected with `->withoutOverlapping()`.
2. **`leadx:backup:prune --retention-days=30`** (Daily at 03:00 UTC): Prunes backups older than 30 days, enforcing the invariant that a newer verified backup must exist. Protected with `->withoutOverlapping()`.
3. **`leadx:subscriptions:process-expiry`** (Daily at 00:05 UTC): Grace-period and expiration handling.
4. **`leadx:subscriptions:send-reminders`** (Daily at 08:00 UTC): Automated client renewal notifications.
5. **`leadx:followups:mark-overdue`** (Daily at 00:15 UTC): Flags overdue follow-up tasks.

---

## 5. SUPERVISOR QUEUE WORKER CONFIGURATION

As established in the Phase 13 & Phase 15 audits, LeadX dispatches background notifications to dedicated queues (`notifications`, `emails`, and `default`).

To prevent starved queues in production, configure **Supervisor** with explicit multi-queue processing:

Create `/etc/supervisor/conf.d/leadx-worker.conf`:
```ini
[program:leadx-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leadx/artisan queue:work --queue=notifications,emails,default --sleep=3 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/leadx/storage/logs/supervisor-worker.log
stopwaitsecs=3600
```

Load and start the worker processes:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start leadx-worker:*
```

After every deployment, always restart the workers so they pick up new code:
```bash
php artisan queue:restart
```

---

## 6. POST-DEPLOYMENT SMOKE TESTING & VERIFICATION

Execute the following smoke tests on the production server to verify complete operational integrity:

### 6.1 CLI Backup Verification Test
```bash
# 1. Trigger an immediate database backup
php artisan leadx:backup --database

# 2. Verify all existing backups
php artisan leadx:backup:verify

# Expected Output:
# [SUCCESS] Backup ID #... verified successfully (Checksum: ..., Decryptable: YES)
```

### 6.2 Pruning Dry-Run / Test
```bash
php artisan leadx:backup:prune --retention-days=30
```

### 6.3 Automated Test Suite Run (Optional on Staging)
```bash
php artisan test --filter=Backup
```

### 6.4 Web GUI Smoke Tests
1. **Super Admin Login**:
   - Navigate to `https://leadx.yourdomain.com/admin/login`
   - Log in with Super Admin credentials.
   - Verify the newly upgraded dashboard loads with all 15 operational metrics, activity stream, and action pipeline.
2. **Backups Dashboard**:
   - Navigate to `/admin/backups`.
   - Verify that the recently generated CLI backup is listed.
   - Click "Verify" to verify cryptographic integrity from the UI.
   - Click "Download" to verify authenticated, secure download delivery.
3. **Employee Portal**:
   - Log in as an active sales employee at `/employee/login`.
   - Verify that the 9-metric scoped dashboard renders cleanly.
   - Attempt to access `/admin/backups` directly and verify an immediate 403 Forbidden or redirect is returned.

---

## 7. ROLLBACK & EMERGENCY DISASTER RECOVERY DRILL

In the event of a catastrophic production issue:

### Scenario A: Roll Back Application Code
```bash
php artisan down
git checkout <PREVIOUS_STABLE_COMMIT>
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

### Scenario B: Full Database & Storage Restore from Backup
```bash
# 1. Locate the desired Backup ID
php artisan leadx:backup:verify

# 2. Run restore command (creates automated safety snapshot first!)
php artisan leadx:backup:restore <BACKUP_ID>

# 3. Clear cache and restart queue
php artisan optimize:clear
php artisan queue:restart
```
