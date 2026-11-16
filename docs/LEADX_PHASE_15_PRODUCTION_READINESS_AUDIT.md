# LEADX — PHASE 15: PRODUCTION READINESS AUDIT REPORT

**Auditor:** Senior Laravel DevOps Engineer, Application Security Auditor & QA Architect  
**Project:** LeadX  
**Framework:** Laravel 12.0  
**PHP Version Target:** PHP 8.2+  
**Database:** MySQL / MariaDB (Tested with SQLite & MySQL drivers)  
**Frontend Architecture:** Blade + Pure HTML5 + CSS3 + Vanilla JavaScript  
**Audit Date:** November 2026  
**Audit Type:** Independent Evidence-Based Production Readiness Audit (Audit-Only)  

---

## SECTION A: EXECUTIVE SUMMARY

### 1. Overall Production Readiness Score: **86 / 100**

The LeadX application demonstrates exceptional software engineering quality, robust architectural patterns, and stringent security controls across its business workflows. The test suite is extensive and passing at 100% (400 tests, 1,654 assertions, 0 failures).

However, before deploying to a live production environment handling real customer data and financial transactions, **four high-risk operational and infrastructural gaps must be addressed**:
1. **Absence of an Automated Database & Storage Backup System (Operational Risk: Critical)**
2. **Multi-Queue Worker Configuration Dependency (Operational Risk: High)**
3. **Google Calendar OAuth Redirect URI Configuration Sensitivity (Integration Risk: Medium)**
4. **Scheduler Task Overlap & Failure Alerting Gaps (Reliability Risk: Medium)**

### 2. Readiness Score Breakdown

| Evaluation Category | Max Score | Awarded Score | Status | Key Notes |
| :--- | :---: | :---: | :---: | :--- |
| **Architecture & Workflows** | 20 | 20 | **Passed** | Clean layered architecture (Controllers, FormRequests, Services, Models, Blade). All 16 critical business workflows verified. |
| **Authentication & Authorization** | 15 | 15 | **Passed** | Multi-guard separation (Admin vs Employee/Team), rate limiting, IDOR prevention, active status enforcement, strict permission gating. |
| **Queue & Background Jobs** | 15 | 13 | **Minor Gap** | Asynchronous jobs (`SendWebPushNotificationJob`, `SendEmailJob`) are robust with exponential backoff and deduplication, but use separate queue names (`notifications`, `emails`) requiring explicit worker queue flags. |
| **Automated Testing Suite** | 15 | 15 | **Passed** | 400 feature and unit tests passing with zero failures. HTTP calls and external services mocked properly. |
| **Scheduler & Lifecycle Automation** | 10 | 8 | **Minor Gap** | Automated lifecycle commands functioning with database idempotency, but lack `withoutOverlapping()` on 4 daily tasks and lack scheduler failure alerting. |
| **Database Design & Concurrency** | 10 | 10 | **Passed** | 48 clean migrations, composite indexes, pessimistic locking (`lockForUpdate`) on order sequences, atomic transactions. |
| **Backup & Disaster Recovery** | 10 | 0 | **Critical Gap** | No automated database backup, no storage backup, no restore mechanism implemented. |
| **Installation & Deployment Security** | 5 | 5 | **Passed** | Modular 8-step installer, multi-location lock files (`installed.lock`), hardened `.htaccess` rules, Super Admin recovery center. |
| **TOTAL SCORE** | **100** | **86** | **READY AFTER CRITICAL FIXES** | Application is staging-ready and requires operational tooling (Backups, Queue worker supervisor configuration, Cron) prior to commercial go-live. |

---

## SECTION B: COMPREHENSIVE FINDINGS TABLE

| ID | Module / Area | Finding | Severity | Evidence | Recommendation |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **F-01** | **Backup & DR** | No automated database or storage backup system exists. | **CRITICAL** | `composer.json` contains no backup package (e.g. `spatie/laravel-backup`); no backup artisan commands in `app/Console/Commands`. Only `.env.backup` is created by the installer. | Implement an automated daily database and storage backup artisan command or install `spatie/laravel-backup` with remote S3/SFTP storage and retention policies. |
| **F-02** | **Queue Worker** | Dedicated queues (`notifications`, `emails`) are starved if queue worker is started with default flags. | **HIGH** | `SendWebPushNotificationJob.php:68` sets `$this->onQueue('notifications')`; `EmailService.php:209` dispatches to `->onQueue('emails')`. Running `php artisan queue:work` without `--queue` processes only `default`. | In production Supervisor configuration and systemd service files, explicitly execute: `php artisan queue:work --queue=notifications,emails,default --sleep=3 --tries=3 --timeout=60`. |
| **F-03** | **Google OAuth** | Google OAuth callback URL mismatch between web UI route and OAuth service configuration. | **MEDIUM** | `config/google.php:25` defaults to `http://127.0.0.1:8000/api/v1/google/calendar/callback`. Web route is `employee/calendar/callback`. Mismatched hosts (`localhost` vs `127.0.0.1`) or routes cause Google `redirect_uri_mismatch`. | Set `GOOGLE_REDIRECT_URI` in `.env` to the exact production URL (`https://yourdomain.com/api/v1/google/calendar/callback`) and whitelist the identical URL in Google Cloud Console Credentials. |
| **F-04** | **Scheduler** | Four daily scheduled commands lack `withoutOverlapping()` protection. | **MEDIUM** | In `routes/console.php:18-29`, `ProcessSubscriptionsExpiry`, `SendSubscriptionReminders`, `MarkFollowUpsOverdue`, and `SendTeamExpiryReminders` do not have `->withoutOverlapping()`. | Add `->withoutOverlapping()` and `->onFailure(...)` logging to all scheduled commands in `routes/console.php`. |
| **F-05** | **Session Security** | Session cookie `secure` flag depends on unverified environment variable. | **MEDIUM** | `config/session.php:172` sets `'secure' => env('SESSION_SECURE_COOKIE')`. If omitted from `.env` in production, cookies transmit over plain HTTP. | Ensure `SESSION_SECURE_COOKIE=true` is enforced in production `.env` whenever HTTPS is active. |
| **F-06** | **Nginx Security** | Root `.htaccess` protections are bypassed on Nginx servers if root points to project root instead of `public/`. | **MEDIUM** | `.htaccess` denies access to `.env`, but Nginx ignores `.htaccess`. If Nginx root is misconfigured, `.env` and SQLite DB are downloadable. | Enforce Nginx server block `root /var/www/leadx/public;` and add location blocks denying `/\.(?!well-known).*` and hidden files. |
| **F-07** | **Queue Engine** | Redis queue driver requires non-bundled PHP extension `ext-redis`. | **LOW** | `composer.json` does not include `predis/predis`. Switching to `QUEUE_CONNECTION=redis` requires the native `ext-redis` C extension. | For Redis caching/queues, compile/enable `ext-redis` or install `predis/predis` via Composer. Default to `QUEUE_CONNECTION=database` on standard shared/VPS hosts. |
| **F-08** | **File Uploads** | Branding uploads use client-supplied extension instead of guessed MIME extension. | **LOW** | `app/Http/Controllers/Admin/SettingsController.php:85,102,119` uses `$file->getClientOriginalExtension()`. | Replace with `$file->guessExtension()` or `$file->extension()` for canonical file naming. Note: MIME types are already verified by validation rules. |
| **F-09** | **Queue & Jobs** | Asynchronous Push Notification job with after-commit safety and deduplication verified. | **PASSED** | `SendWebPushNotificationJob` implements `ShouldQueue`, `ShouldQueueAfterCommit`, `$this->afterCommit = true`, 10-minute cache deduplication, and exponential backoff `[5, 15, 30]`. | Implementation adheres to all Phase 13 performance requirements. |
| **F-10** | **Concurrency** | Atomic order number generation with pessimistic database locking verified. | **PASSED** | `OrderNumberService::nextSequence` locks sequence rows using `lockForUpdate()` within transactions and handles unique constraint collisions with up to 5 retries. | Prevents duplicate order numbers under high concurrency. |
| **F-11** | **Authentication** | Multi-guard authentication with rate limiting and session regeneration verified. | **PASSED** | `AdminLoginController`, `Employee\LoginController`, and `Auth\LoginController` enforce 5 attempts per 15-minute window via `RateLimiter` and call `$request->session()->regenerate()`. | Immune to credential stuffing and session fixation. |
| **F-12** | **Authorization** | Strict employee scoping and IDOR prevention verified. | **PASSED** | `OrdersController`, `CustomersController`, and `CrmCustomerIssueService` enforce assigned employee scoping or verified Super Admin Team Data access grants. | Non-authorized employees cannot read or manipulate foreign orders, CRM records, or customer issues. |
| **F-13** | **Webhook Security**| Payment gateway webhook HMAC signature verification and idempotency verified. | **PASSED** | `GatewayWebhookController` and `GatewayService` verify signatures before processing and record deliveries in `webhook_events` to reject replay attacks. | Prevents spoofed payment activations and duplicate ledger entries. |
| **F-14** | **Installation** | Multi-step installer and multi-location lock verification verified. | **PASSED** | 8-step wizard, locks checked across `storage/app/installed.lock`, `storage/app/installer/installed.lock`, and `install.lock`. Recovery center protected by `admin` guard. | Full protection against re-installation attacks. |

---

## SECTION C: MISSING FEATURES & GAPS

Only genuinely absent capabilities are reported below:

### 1. Automated Database & File Backup System (Critical Operational Gap)
- **Current State:** The application possesses no mechanism to take scheduled snapshots of the database (MySQL/MariaDB) or uploaded files (branding logos, favicons). The only existing backup is the `.env` backup created by the Installer in `storage/app/installer/backups/`.
- **Production Risk:** Server hardware failure, database corruption, ransomware, or accidental administrative deletion would result in permanent data loss.
- **Required Remedy:** Deploy an automated database dump routine running on daily cron, storing encrypted backups in an offsite location (e.g. AWS S3, Cloudflare R2, or remote SFTP storage) with a 30-day retention rotation.

### 2. Automated Disaster Recovery / Restore Tooling
- **Current State:** No automated or guided restore verification scripts exist.
- **Required Remedy:** Provide a documented, tested artisan command (e.g. `backup:restore`) or runbook to verify database dumps and test restore integrity in a staging sandbox.

### 3. Scheduler Failure Alerting
- **Current State:** If scheduled tasks in `routes/console.php` fail or terminate unexpectedly, there is no email or notification hook triggering an alert to the Super Admin.
- **Required Remedy:** Attach `->onFailure(fn () => EmailService::dispatchSystemAlert(...))` to vital console schedule definitions.

---

## SECTION D: SECURITY VULNERABILITIES & CODE AUDIT

### 1. Authentication & Multi-Guard Role Separation (PASSED)
- **Super Admin Guard:** Operates on `admins` table via `AuthenticateAdmin` middleware. Password stored using `'password' => 'hashed'` (Bcrypt/Argon2id).
- **Employee & Team Guards:** Operates on `users` table via `AuthenticateEmployeeWeb` and `AuthenticateTeamWeb` middleware. Status (`active`/`inactive`) and role (`employee`/`team`) validated at login and on every request via `EnsureRoleActive`.
- **Sanctum API Tokens:** Tokens scoped to actor type. Revocation and expiration supported.

### 2. Rate Limiting & Brute Force Protection (PASSED)
- All login controllers (`AdminLoginController.php:38`, `Employee\LoginController.php:30`, `Auth\LoginController.php:42`) employ Laravel's `RateLimiter`:
  ```php
  $key = 'admin:'.strtolower($data['login']).'|'.$request->ip();
  if (RateLimiter::tooManyAttempts($key, 5)) {
      throw ValidationException::withMessages(['login' => 'Too many login attempts...']);
  }
  ```
- Locked out for 900 seconds (15 minutes) after 5 consecutive failures.

### 3. Authorization & IDOR Protection (PASSED)
- **Employee Orders:** `app/Http/Controllers/Employee/OrdersController.php:109`
  ```php
  abort_if((int) $order->assigned_employee_id !== (int) $employee->id, 403, 'Unauthorized access to this order.');
  ```
  Viewing team orders requires an explicit `EmployeeTeamAccessGrant` active within `start_date` and `end_date` and not revoked.
- **Customer CRM Records:** `app/Http/Controllers/Employee/CustomersController.php:42` scopes customer queries strictly to distinct phones from orders assigned to the logged-in employee.
- **Customer Issues:** `app/Services/CrmCustomerIssueService.php:41` prevents updating any issue where `(int) $issue->employee_id !== (int) $employee->id`.
- **API Credentials:** Plaintext API keys can only be retrieved via `reveal` (`OrderCredentialController.php:84`) by actors authorized by `OrderCredentialService::canManage()`. Keys are never logged in application or HTTP access logs.

### 4. Injection, CSRF, and XSS Safeguards (PASSED)
- **SQL Injection:** Eloquent ORM and parameterized queries utilized throughout; raw queries properly escaped.
- **CSRF:** All state-changing web routes guarded by `VerifyCsrfToken`. Custom 419 error handler in `bootstrap/app.php:28-34` provides user-friendly redirection.
- **XSS:** Blade `{{ }}` escaping employed across all views. Branding upload settings validate strict MIME types (`png, jpg, jpeg, webp, ico`) and block SVG uploads to eliminate script injection vectors.

### 5. Webhook Security & Idempotency (PASSED)
- `GatewayService.php` enforces HMAC signature validation via `GatewayWebhookVerifier`.
- Invalid signatures throw `GatewaySignatureException` returning HTTP 401.
- Valid events are recorded in `webhook_events` table by unique `(gateway, event_id)` preventing duplicate processing or double-crediting of revenue.

---

## SECTION E: PRODUCTION DEPLOYMENT CHECKLIST

### 1. Architecture Comparison: VPS vs Shared Hosting (cPanel)

| Production Component | VPS / Cloud Server (Recommended) | Shared Hosting (cPanel) |
| :--- | :--- | :--- |
| **Web Server** | Nginx or Apache with full virtual host configuration | Apache / LiteSpeed with `.htaccess` support |
| **Document Root** | Set directly to `/var/www/leadx/public` | Place LeadX files in private folder (e.g. `/home/user/leadx_app/`); symlink or copy `public/` into `/home/user/public_html/` |
| **Queue Worker** | Supervisor daemon running `php artisan queue:work` | Cron job running `php artisan queue:work --stop-when-empty` every minute |
| **Task Scheduler** | System crontab: `* * * * * cd /var/www/leadx && php artisan schedule:run >> /dev/null 2>&1` | cPanel Cron interface: `* * * * * php /home/user/leadx_app/artisan schedule:run > /dev/null 2>&1` |
| **Process Monitoring**| Systemd or Supervisor (auto-restarts on crash) | cPanel Process Manager or rely on minute cron execution |
| **Redis Support** | Native Redis service + `ext-redis` extension | Rarely available; must use `QUEUE_CONNECTION=database` and `CACHE_STORE=database` |

---

### 2. VPS Deployment Instructions (Ubuntu / Debian + Nginx + PHP 8.2-FPM)

#### Step 1: Server Prerequisites & PHP Extensions
Install PHP 8.2 and required extensions:
```bash
sudo apt update && sudo apt install -y nginx mysql-server supervisor \
    php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl \
    php8.2-zip php8.2-bcmath php8.2-intl php8.2-redis
```

#### Step 2: Nginx Virtual Host Hardening
Create `/etc/nginx/sites-available/leadx.conf`:
```nginx
server {
    listen 80;
    server_name leadx.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name leadx.yourdomain.com;

    root /var/www/leadx/public;
    index index.php index.html;

    # SSL Configuration (Let's Encrypt Certbot)
    ssl_certificate /etc/letsencrypt/live/leadx.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/leadx.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Block access to hidden files (.env, .git)
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Block access to installer lock and backup files
    location ~* \.(lock|backup|log|sql)$ {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    client_max_body_size 10M;
}
```

#### Step 3: Production Supervisor Queue Configuration
Create `/etc/supervisor/conf.d/leadx-worker.conf`:
```ini
[program:leadx-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leadx/artisan queue:work --queue=notifications,emails,default --sleep=3 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/leadx/storage/logs/worker.log
stopwaitsecs=3600
```
Update and start Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start leadx-worker:*
```

#### Step 4: System Crontab Configuration
Run `sudo crontab -u www-data -e` and append:
```cron
* * * * * cd /var/www/leadx && php artisan schedule:run >> /dev/null 2>&1
```

#### Step 5: Production Deployment & Zero-Downtime Script
```bash
#!/bin/bash
set -e

cd /var/www/leadx
php artisan down || true

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

php artisan up
echo "LeadX deployed successfully!"
```

---

### 3. Shared Hosting (cPanel) Deployment Instructions

1. **Upload Files:** Upload application files to a private folder outside public webroot: `/home/username/leadx_app/`.
2. **Setup Webroot:** Symlink or copy the contents of `/home/username/leadx_app/public/` to `/home/username/public_html/`. If copying, update `index.php` paths:
   ```php
   require __DIR__.'/../leadx_app/vendor/autoload.php';
   $app = require_once __DIR__.'/../leadx_app/bootstrap/app.php';
   ```
3. **Queue Processing via Cron:** In cPanel Cron Jobs, add a job running every minute:
   ```cron
   * * * * * php /home/username/leadx_app/artisan queue:work --stop-when-empty --queue=notifications,emails,default > /dev/null 2>&1
   ```
4. **Scheduler via Cron:** In cPanel Cron Jobs, add:
   ```cron
   * * * * * php /home/username/leadx_app/artisan schedule:run > /dev/null 2>&1
   ```

---

## SECTION F: TEST RESULTS & VERIFICATION

### 1. Test Execution Statistics
Full test suite executed in Phase 15 audit:
- **Total Tests Executed:** 400
- **Passed:** 400 (100%)
- **Failed:** 0
- **Skipped / Incomplete:** 0
- **Total Assertions:** 1,654
- **Execution Time:** 22.97 seconds

### 2. Test Quality & Coverage Analysis
- **Queue & Async Testing (`PushNotificationQueueTest`, `Phase13PerformanceTest`):** Verifies that `SendWebPushNotificationJob` dispatches after transaction commit, honors retry attempts, deduplicates duplicate requests, and mocks Pusher Beams HTTP endpoints without making live network requests.
- **Installer Testing (`InstallationWizardTest`, `ProfessionalInstallerTest`):** Verifies requirements checking, environment file backup, database connection validation, migration execution, administrator creation, lock file placement, and unauthorized access blocking.
- **Workflow Testing (`OrderCreationWorkflowTest`, `OrderNumberSequenceTest`):** Verifies concurrency, sequence ledger isolation by team and year, product snapshotting, and transaction integrity.
- **Security Testing (`EmployeeOrderManagementTest`, `CrmCustomerIssuesTest`):** Verifies multi-guard isolation, cross-employee IDOR prevention, and role-based access control.

---

## SECTION G: END-TO-END BUSINESS WORKFLOW VERIFICATION

All 16 core business workflows were traced through source code and verified:

1. **Super Admin Login:** Authenticates against `admins` table using Bcrypt hash verification, session regeneration, and IP/login rate limiting. Verified in `AdminLoginController.php`.
2. **Employee Login:** Authenticates against `users` table where `role='employee'` and `status='active'`, rate-limited to 5 attempts per 15 minutes. Verified in `Employee\LoginController.php`.
3. **Employee Order Creation:** Implemented in `CreateOrderController` and `OrderService::create`. Wrapped in atomic `DB::transaction`. Snapshots product catalogue details and safely assigns team sequence order number.
4. **Minimum Closing Price Validation:** Evaluated against `minimum_closing_price_snapshot`. If closing price is below the threshold, order requires Super Admin approval.
5. **Closing Value Approval & Rejection:** Super Admin approves/rejects in `Admin\OrdersController.php`. Updates status, logs history, and dispatches in-app notifications.
6. **API Key Management & Authorized Reveal:** API keys are encrypted at rest with AES-256 (`'encrypted'` cast). Masks keys in lists. Plaintext reveal requires explicit permission and is not logged.
7. **Payment Verification:** Handled in `Admin\PaymentsController::verify`. Transitions payment status, updates order verified revenue, and marks customer verified.
8. **Subscription Activation:** Handled by `SubscriptionActivator::activate`. Calculated from product duration (`1m`, `3m`, `6m`, `1y`), generates subscription record, and activates calendar sync.
9. **Customer CRM Access:** `Admin\CustomersController` and `Employee\CustomersController` enforce scoping to employee's assigned orders or active Team Data grant window.
10. **Customer Issues Management:** Scoped to customers with whom the employee has established order relationships; state transitions tracked in `crm_customer_issue_histories`.
11. **Order Cancellation:** Atomic transaction requiring cancellation reason; updates order status and revokes scheduled Google Calendar events.
12. **Full & Partial Refunds:** `PaymentService::recordRefund` tracks refund ledger, adjusts order balances, and cascades subscription status changes on full refund.
13. **Expiry Reminders:** `SendTeamExpiryReminders` identifies active subscriptions expiring tomorrow; dispatches in-app and push notices to active team members once per cycle via unique database index.
14. **Google Calendar Synchronization:** `SubscriptionCalendarService` syncs expiry events to employee's primary calendar using OAuth refresh tokens; retries pending events via daily cron.
15. **Team Data Access & Revocation:** `EmployeeTeamAccessGrant` enables time-bounded team visibility with instant revocation via `revoked_at`.
16. **Dashboard & Permission Customization:** Widget preferences persisted per admin/employee; route and action permissions enforced by `EnsureEmployeePermission`.

---

## SECTION H: RECOMMENDED NEXT PHASES

| Priority | Phase Recommendation | Goal | Estimated Effort |
| :---: | :--- | :--- | :---: |
| **P1** | **Phase 16: Automated Backup & Disaster Recovery System** | Implement automated daily database and storage backups, AES-256 backup encryption, S3/SFTP offsite transmission, 30-day retention pruning, and a verified `artisan backup:restore` command. | 1 Day |
| **P2** | **Phase 17: Production Infrastructure & Supervisor Hardening** | Finalize Supervisor worker configurations, systemd units, Nginx hardening scripts, and deployment pipeline scripts for target production hosting. | 0.5 Day |
| **P3** | **Phase 18: Scheduler Reliability & Observability** | Add `withoutOverlapping()` across all scheduled tasks, failure alert hooks to notify Super Admin via email, and queue health monitoring dashboard. | 0.5 Day |
| **P4** | **Phase 19: Google OAuth Production Whitelisting & Staging Verification** | Whitelist production domain in Google Cloud Console, verify live calendar sync with staging accounts, and test SSL termination. | 0.5 Day |

---

## SECTION I: FINAL VERDICT

```
================================================================================
FINAL VERDICT: READY AFTER CRITICAL FIXES (STAGING-READY)
READINESS SCORE: 86 / 100
================================================================================
```

### Rationale:
The application's core codebase is exceptionally mature, secure, performant, and well-tested (400 passing tests with 0 failures). There are no blocking application logic bugs, no authentication bypasses, no SQL injection flaws, and no data integrity risks.

However, deployment to a live **commercial production environment** must be held until **Phase 16 (Automated Backup & Disaster Recovery System)** and the **Queue Worker Multi-Queue Configuration** are deployed. Without automated backups and explicit queue worker configuration, operational risks (data loss and stalled background notifications) exist.

**The codebase is immediately ready for deployment to a Staging / UAT environment.**
