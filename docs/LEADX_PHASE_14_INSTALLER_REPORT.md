# LEADX — PHASE 14: PROFESSIONAL INSTALLATION WIZARD REPORT

**Application:** LeadX (Laravel 12 / PHP 8.2+ / MySQL & MariaDB)  
**Role:** Senior Laravel Architect & Full-Stack Developer  
**Date:** November 16, 2026  
**Status:** Completed & 100% Verified  

---

## Executive Summary

In Phase 14, a secure, non-destructive, professional installation wizard and administrative recovery center were built for the LeadX application. 

The implementation strictly honors all architectural constraints:
1. **Zero External Frontend Frameworks:** Built with pure HTML5, CSS3 (using LeadX's core design tokens from `public/assets/css/app.css`), and Vanilla JavaScript. No Tailwind, Bootstrap, jQuery, React, or Vue was introduced.
2. **Unified Authentication:** Utilizes LeadX's existing multi-guard authentication system (`admin` and `web` guards). No secondary authentication mechanism was introduced.
3. **Data Preservation Guarantee:** Forward migrations execute with `--force`; destructive commands (`migrate:fresh`, `db:wipe`, `migrate:reset`) are strictly prohibited. Existing database records, customer orders, and team snapshots are never deleted.
4. **Multi-Location Locking:** Seals the installer across `storage/app/installed.lock`, `storage/app/installer/installed.lock`, and `base_path('install.lock')`, preventing unauthorized re-installation even if shared hosting scripts clear the `storage/` directory.
5. **Super Admin Recovery Center:** Provides an authenticated diagnostic portal (`/admin/installer/recovery`) allowing authorized Super Admins to verify telemetry, apply pending migrations, test connectivity, and rebuild caches without risking business data.
6. **Full Test Suite Integrity:** All 400 automated tests passed (1,650 assertions) in 20.63 seconds.

---

## 1. Architecture & Directory Structure

All installer and recovery components are organized into dedicated, isolated namespaces:

```text
app/
├── Console/Commands/
│   ├── InstallerStatusCommand.php         # php artisan installer:status
│   ├── InstallerLockCommand.php           # php artisan installer:lock {--force}
│   └── ResetInstallCommand.php            # php artisan reset:install {--force} {--clear-env}
├── Http/
│   ├── Controllers/
│   │   ├── Install/
│   │   │   └── InstallController.php      # 8-step wizard controller
│   │   └── Admin/
│   │       └── InstallRecoveryController.php # Super Admin diagnostic & recovery portal
│   └── Middleware/
│       └── CheckInstallation.php         # Slug redirection & access control guard
└── Services/
    └── Installer/
        ├── RequirementChecker.php         # Runtime, 11 PHP extensions & directory permissions
        ├── DatabaseManager.php            # PDO live tester & credential sanitization
        ├── EnvironmentManager.php         # Atomic .env reader, updater, backup & APP_KEY safety
        ├── AdminAccountManager.php        # Super Admin dual provisioning (admins & users)
        └── InstallationStateManager.php   # Multi-location locks & telemetry inspector

resources/views/
├── install/
│   ├── partials/
│   │   └── stepper.blade.php              # Responsive 8-step progress stepper
│   ├── requirements.blade.php             # Step 1: Runtime & extensions checklist
│   ├── environment.blade.php              # Step 2: App name, URL, timezone & currency
│   ├── database.blade.php                 # Step 3: DB setup with live Vanilla JS AJAX test
│   ├── migrate.blade.php                  # Step 4: Non-destructive forward migration runner
│   ├── admin.blade.php                    # Step 5: Super Admin provisioning form
│   ├── config.blade.php                   # Step 6: Mail, Pusher, Queue & scheduler
│   ├── verify.blade.php                   # Step 7: Pre-flight health verification
│   └── finish.blade.php                   # Step 8: Success summary & admin login handoff
└── admin/
    └── installer/
        └── recovery.blade.php             # Authenticated recovery & diagnostic center
```

---

## 2. Multi-Step Installation Wizard Flow

| Step | Route | Name | Purpose & Key Protections |
| :--- | :--- | :--- | :--- |
| **1. Requirements** | `GET /install` | `install.requirements` | Audits PHP >= 8.2, 11 extensions (`openssl`, `pdo`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath`, `curl`), and directory permissions (`storage/`, `bootstrap/cache/`, `.env`). Live AJAX re-check supported. |
| **2. Environment** | `GET /install/environment` | `install.environment` | Collects Application Name, Application URL, Timezone, and Currency. Atomically updates `.env` with timestamped backup. |
| **3. Database** | `GET /install/database` | `install.database` | Collects host, port, database name, username, and password. Features a live Vanilla JS AJAX test button (`POST /install/database/test`) with sanitized error messages. Detects existing tables non-destructively. |
| **4. Migrations** | `GET /install/migrate` | `install.migrate` | Executes `Artisan::call('migrate', ['--force' => true])` and idempotent seeders. Displays animated spinner and terminal output. Rejects destructive wipe/fresh flags. |
| **5. Super Admin** | `GET /install/admin` | `install.admin` | Creates or updates the primary administrator in `admins` table (guard: `admin`) and syncs to `users` table (`role: admin`, `status: active`). Hashed with Bcrypt/Argon2id. |
| **6. Configuration** | `GET /install/config` | `install.config` | Optional setup for transactional SMTP mail, Pusher Beams/Channels, Google Calendar OAuth, and background Queue driver (`sync`, `database`, `redis`). |
| **7. Verification** | `GET /install/verify` | `install.verify` | Comprehensive pre-flight health check verifying database connectivity, migration count, storage/cache writeability, admin accounts, and application encryption keys. |
| **8. Completion** | `POST /install/finish` | `install.finish` | Engages multi-location locks, flushes and optimizes caches (`config:clear`, `route:clear`, `view:clear`), displays credentials summary, and provides a direct link to `/admin/login`. |

---

## 3. Security Audit & Hardening

### 3.1 Credential Sanitization
- `DatabaseManager::sanitizeDbError()` masks passwords and sensitive connection parameters in PDO exceptions before displaying them on screen.
- Raw passwords and sensitive tokens are cleared from the session upon finishing the wizard.

### 3.2 Atomic `.env` Management & Key Preservation
- `EnvironmentManager::update()` performs line-by-line regex replacement, preserving custom environment variables, comments, and empty lines.
- Automatically saves a timestamped backup copy to `storage/app/installer/backups/env-backup-<timestamp>.env`.
- **`APP_KEY` Rule:** Never overwrites an existing valid application key. Only generates a new key if `APP_KEY` is completely missing or empty, protecting encrypted database columns and remember-me tokens from corruption.

### 3.3 Multi-Location Locking
`InstallationStateManager` writes lock files to:
1. `storage/app/installed.lock` (primary / backward-compatible)
2. `storage/app/installer/installed.lock` (structured JSON metadata)
3. `base_path('install.lock')` (root-level safeguard against shared hosting storage wipes)

Lock files store audit metadata:
```json
{
  "installed_at": "2026-11-16T14:36:53Z",
  "app_version": "1.0.0",
  "installer_version": "2.0.0",
  "php_version": "8.2.12",
  "locked_by": "web_installer"
}
```

### 3.4 Apache `.htaccess` Protection
Hardening rules were implemented in both root `.htaccess` and `public/.htaccess`:
```apache
# Block access to hidden files, lock files, environment configs and vendor
<FilesMatch "^(\.env|\.git|composer\.|package\.|artisan|.*\.lock)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

Options -Indexes
```

### 3.5 Access Control & Slug Redirection
- **When NOT Installed:** Any request to the root application (`/`, `/dashboard`, etc.) automatically redirects to `/install`.
- **When Installed / Locked:** Direct access to `/install` or `/install/*` is blocked. Browser requests redirect to `/` with an informational notice; AJAX/JSON requests receive HTTP `403 Forbidden`.

---

## 4. Super Admin Recovery & Diagnostics

Requirement 14 specifies a safe recovery process for authorized Super Admins without deleting existing business data.

### 4.1 Web Recovery Center (`/admin/installer/recovery`)
- **Route:** `GET /admin/installer/recovery` (name: `admin.installer.recovery`)
- **Action Endpoint:** `POST /admin/installer/recovery/action` (name: `admin.installer.recovery.action`)
- **Protection:** Guarded by `auth:admin`. Only authenticated Super Admins can access it.
- **Available Non-Destructive Actions:**
  1. **Apply Pending Migrations:** Executes forward migrations (`migrate --force`) safely.
  2. **Clear Caches:** Clears application, configuration, route, and view caches.
  3. **Rebuild Caches:** Compiles configuration, routes, and views for production optimization.
  4. **Test Live DB Connection:** Performs live PDO ping and reports MySQL version and table count.
  5. **Verify APP_KEY:** Confirms application encryption key is present without regenerating.
  6. **Lock / Unlock Installer:** Allows temporary unlocking if the wizard needs review, or re-locking immediately.
  7. **Update Environment Settings:** Safely adjusts application name, URL, and queue drivers with backup.

---

## 5. Artisan CLI Management Commands

Three Artisan commands allow terminal operators and automated CI/CD scripts to manage the installer:

### 1. `php artisan installer:status`
Displays a formatted status table:
```text
=== LeadX Installation & Security Telemetry ===

+--------------------------+--------------------------------------------------------------+
| Metric                   | Value                                                        |
+--------------------------+--------------------------------------------------------------+
| Installer Status         | LOCKED (Production Safe)                                     |
| PHP Version              | 8.2.12                                                       |
| Laravel Version          | 12.72.0                                                      |
| Lock Files Found         | D:\Projects\Leadx\LeadX\storage\app/installed.lock           |
|                          | D:\Projects\Leadx\LeadX\storage\app/installer/installed.lock |
|                          | D:\Projects\Leadx\LeadX\install.lock                         |
| Database Connected       | YES                                                          |
| Database Version         | 10.4.32-MariaDB                                              |
| Database Tables          | 50                                                           |
| Applied Migrations       | 47                                                           |
| Admin Accounts           | 1                                                            |
| Storage Writable         | PASS                                                         |
| Bootstrap Cache Writable | PASS                                                         |
+--------------------------+--------------------------------------------------------------+
```

### 2. `php artisan installer:lock {--force}`
Instantly writes multi-location lock files and seals the web wizard:
```bash
php artisan installer:lock --force
```

### 3. `php artisan reset:install {--force} {--clear-env}`
Safely unlocks the installer without touching database tables:
```bash
# Standard unlock (removes locks & clears caches)
php artisan reset:install --force

# Complete unlock including database credentials in .env (APP_KEY preserved)
php artisan reset:install --force --clear-env
```

---

## 6. Automated Testing & Verification

### 6.1 Installation Test Suites

#### A. Legacy Compatibility Suite (`InstallationWizardTest.php`):
- `test_requirements_page_renders_successfully` — **PASS**
- `test_environment_page_renders_successfully` — **PASS**
- `test_environment_page_renders_with_validation_errors` — **PASS**
- `test_database_page_renders_successfully` — **PASS**
- `test_migrate_page_renders_successfully` — **PASS**
- `test_admin_page_renders_successfully` — **PASS**
- `test_config_page_renders_successfully` — **PASS**
- `test_verify_page_renders_successfully` — **PASS**
- `test_app_css_asset_exists_and_contains_design_tokens` — **PASS**

#### B. Professional Installer Suite (`ProfessionalInstallerTest.php`):
1. `test_requirement_checker_evaluates_system_accurately` — **PASS**
2. `test_database_manager_sanitizes_errors_and_detects_credentials_failure` — **PASS**
3. `test_environment_manager_atomically_updates_preserving_keys` — **PASS**
4. `test_admin_account_manager_creates_and_updates_super_admin` — **PASS**
5. `test_installation_state_manager_multi_location_locks_and_telemetry` — **PASS**
6. `test_middleware_blocks_unauthorized_access_when_installer_is_locked` — **PASS**
7. `test_middleware_redirects_uninstalled_visitors_to_installer` — **PASS**
8. `test_admin_can_access_recovery_center_and_run_safe_actions` — **PASS**
9. `test_unauthenticated_user_cannot_access_recovery_center` — **PASS**
10. `test_artisan_installer_cli_commands` — **PASS**

### 6.2 Full Regression Suite
```bash
php artisan test
```
**Outcome:**
```
Tests:    400 passed (1650 assertions)
Duration: 20.63s
```
**Zero failures, zero regressions across the entire application.**

---

## 7. Production Deployment & Shared Hosting Guide

### 7.1 Fresh Server Setup
1. **Upload Files:** Place all codebase files in `/var/www/leadx` (or above `public_html` in cPanel).
2. **Web Root:** Configure web server document root to point to `/var/www/leadx/public`.
3. **Permissions:**
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   chmod 664 .env
   ```
4. **Browser Wizard:** Navigate to `http://your-domain.com/` — the visitor will be automatically directed to the 8-step wizard.
5. **Complete Setup:** Enter database credentials, verify connection, run migrations, create the Super Admin account, and complete installation.
6. **Automatic Lock:** The installer seals itself permanently upon completion.

### 7.2 Headless / CLI Deployment (No Web Browser)
For automated Docker, Ansible, or CI/CD pipelines:
```bash
# 1. Copy environment template
cp .env.example .env

# 2. Generate application key
php artisan key:generate

# 3. Configure DB in .env and run migrations
php artisan migrate --force
php artisan db:seed --force

# 4. Lock installer permanently
php artisan installer:lock --force
```

---

## 8. Files Changed & Created

| File | Action | Purpose |
| :--- | :--- | :--- |
| `app/Services/Installer/RequirementChecker.php` | **Created** | System runtime, 11 PHP extensions and folder permission validation |
| `app/Services/Installer/DatabaseManager.php` | **Created** | PDO connection testing and error message sanitization |
| `app/Services/Installer/EnvironmentManager.php` | **Created** | Atomic `.env` updates, timestamped backups, and `APP_KEY` safety |
| `app/Services/Installer/AdminAccountManager.php` | **Created** | Super Admin dual provisioning (`admins` & `users` tables) |
| `app/Services/Installer/InstallationStateManager.php` | **Created** | Multi-location lock file resolution and telemetry reporting |
| `app/Console/Commands/InstallerStatusCommand.php` | **Created** | CLI command `php artisan installer:status` |
| `app/Console/Commands/InstallerLockCommand.php` | **Created** | CLI command `php artisan installer:lock` |
| `app/Console/Commands/ResetInstallCommand.php` | **Created** | CLI command `php artisan reset:install` |
| `app/Http/Controllers/Install/InstallController.php` | **Refactored** | 8-step wizard controller with AJAX live testing and atomic state |
| `app/Http/Controllers/Admin/InstallRecoveryController.php` | **Created** | Super Admin recovery and diagnostics portal |
| `app/Http/Middleware/CheckInstallation.php` | **Refactored** | Slug redirection and locked installer access control |
| `routes/web.php` | **Modified** | Added recovery routes under `auth:admin` middleware |
| `resources/views/install/partials/stepper.blade.php` | **Created** | Responsive 8-step progress stepper |
| `resources/views/install/*.blade.php` | **Enhanced** | Responsive UI with LeadX CSS tokens and Vanilla JS AJAX |
| `resources/views/admin/installer/recovery.blade.php` | **Created** | Super Admin recovery dashboard view |
| `.htaccess` & `public/.htaccess` | **Hardened** | Apache rules blocking `.env`, `*.lock`, `artisan`, and backups |
| `tests/Feature/ProfessionalInstallerTest.php` | **Created** | 10 feature tests verifying all installer, security, and recovery specs |
| `docs/LEADX_PHASE_14_INSTALLER_REPORT.md` | **Created** | Complete technical and operational report |
