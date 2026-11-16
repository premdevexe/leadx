# LEADX — PHASE 16: AUTOMATED BACKUP & DISASTER RECOVERY REPORT

**Author:** Senior Laravel DevOps Engineer & Application Security Architect  
**Project:** LeadX  
**Framework:** Laravel 12.0  
**PHP Version:** PHP 8.2+  
**Architecture:** Pure Native Laravel Services (No Unmaintained Third-Party Packages)  
**Security Standard:** AES-256-CBC with HMAC-SHA256 Authenticated Encryption  
**Status:** Completed & 100% Verified (21 Dedicated Tests, 140 Assertions)  
**Date:** November 2026  

---

## 1. EXECUTIVE SUMMARY

The Phase 15 Production Readiness Audit identified the complete absence of an automated database and storage backup system as a **Critical Operational Risk (F-01)**. In Phase 16, a robust, enterprise-grade Backup & Disaster Recovery (DR) system was designed and implemented from the ground up.

The system is engineered as an integral part of LeadX, avoiding fragile third-party dependencies while delivering full database dumping, asset archive generation, authenticated cryptographic encryption, multi-cloud storage distribution, automated retention pruning with strict safety invariants, pre-restore automated snapshots, and administrative GUI controls with path-traversal safeguards.

### Key Capabilities
- **Database Dump Engine**: High-fidelity SQL dumper supporting MySQL 8+, MariaDB 10+, and SQLite with transaction isolation, table structure extraction, and row chunk streaming.
- **Storage Archival Engine**: Fast ZIP-based asset packaging covering `storage/app/public`, uploads, and brand assets while strictly filtering out sensitive and volatile files (`.env`, `.git`, session files, cache, application logs, and recursive backup directories).
- **Authenticated Encryption**: Military-grade AES-256-CBC encryption with per-archive random IVs and HMAC-SHA256 authentication headers, protecting against tampering and data leaks at rest.
- **Multi-Cloud Storage**: Seamless distribution to Local Storage, Amazon S3, Cloudflare R2, or remote SFTP endpoints.
- **Safe Retention Pruning**: Automated 30-day retention pruning with a non-negotiable safety invariant: **no archive is ever deleted unless a newer, verified backup exists**.
- **Disaster Recovery & Restore Engine**: Automated pre-restore safety snapshot creation, cryptographic checksum validation, explicit human confirmation gating (`CONFIRM_RESTORE_<ID>`), and tamper-proof audit logging.
- **Full Operational Tooling**: 4 production Artisan CLI commands (`leadx:backup`, `leadx:backup:verify`, `leadx:backup:prune`, `leadx:backup:restore`), automated cron scheduling with overlap protection, and a modern Admin UI.

---

## 2. SYSTEM ARCHITECTURE & COMPONENTS

```
+---------------------------------------------------------------------------------+
|                                 LEADX BACKUP SYSTEM                             |
+---------------------------------------------------------------------------------+
                                      |
         +----------------------------+---------------------------+
         |                                                        |
         v                                                        v
+------------------+                                     +------------------+
|   Admin GUI      |                                     |   Artisan CLI    |
| (Web Controller) |                                     |   & Scheduler    |
+------------------+                                     +------------------+
         |                                                        |
         +----------------------------+---------------------------+
                                      |
                                      v
                          +-----------------------+
                          | BackupManagerService  |
                          +-----------------------+
                                      |
        +-----------------------------+-----------------------------+
        |                             |                             |
        v                             v                             v
+-----------------------+   +-----------------------+   +-----------------------+
|  DatabaseBackupService|   |  StorageBackupService |   |BackupEncryptionService|
|  - MySQL / MariaDB    |   |  - Uploads / Branding |   |  - AES-256-CBC        |
|  - Table streaming    |   |  - Sensitive exclusions|  |  - HMAC-SHA256 Auth   |
+-----------------------+   +-----------------------+   +-----------------------+
        |                             |                             |
        +-----------------------------+-----------------------------+
                                      |
                                      v
                          +-----------------------+
                          | RemoteStorageService  |
                          | (Local, S3, R2, SFTP) |
                          +-----------------------+
                                      |
        +-----------------------------+-----------------------------+
        |                                                           |
        v                                                           v
+-----------------------+                                   +-----------------------+
|    Database Records   |                                   |     Physical Files    |
| (backups, restore_logs|                                   | (storage/app/backups/)|
+-----------------------+                                   +-----------------------+
```

### 2.1 Database Schema
Two dedicated database tables track backup lifecycles and recovery activities:

#### Table: `backups`
- `id` (bigint, primary key)
- `backup_name` (varchar 255): Formatted identifier (e.g. `leadx-backup-full-2026-11-16-153000`)
- `type` (enum): `'full'`, `'database'`, `'storage'`
- `file_path` (varchar 255): Relative storage path within `storage/app/backups/`
- `disk` (varchar 50): Storage destination (`'local'`, `'s3'`, `'r2'`, `'sftp'`)
- `file_size` (bigint): Size of the archive in bytes
- `checksum` (varchar 64): SHA-256 checksum of the final encrypted archive
- `is_encrypted` (boolean): Flag indicating whether encryption was applied
- `is_verified` (boolean): Cryptographic verification flag
- `verified_at` (timestamp, nullable): Verification timestamp
- `status` (enum): `'pending'`, `'in_progress'`, `'completed'`, `'failed'`
- `error_message` (text, nullable): Failure details if execution encountered an error
- `created_by` (bigint unsigned, nullable): Admin ID who triggered the backup (null for cron)
- `created_at`, `updated_at` (timestamps)

#### Table: `backup_restore_logs`
- `id` (bigint, primary key)
- `backup_id` (bigint unsigned, foreign key referencing `backups.id`)
- `restored_by` (bigint unsigned, foreign key referencing `admins.id`)
- `status` (enum): `'pending'`, `'completed'`, `'failed'`
- `details` (json, nullable): Execution metadata, pre-restore snapshot ID, affected tables
- `created_at`, `updated_at` (timestamps)

---

## 3. CORE SERVICE IMPLEMENTATIONS

### 3.1 DatabaseBackupService (`app/Services/Backup/DatabaseBackupService.php`)
- **Capabilities**: Produces a clean, standard SQL dump without requiring system-level `mysqldump` executable dependencies (ensuring seamless operation across shared hosting, Docker, and locked-down VPS environments).
- **Execution Workflow**:
  1. Inspects table schema via `DB::select('SHOW TABLES')` (or `sqlite_master` in SQLite testing).
  2. Extracts `CREATE TABLE` definitions with `DROP TABLE IF EXISTS` prefixes.
  3. Iterates over rows using chunk streaming (`chunk(500)`) to ensure minimal memory consumption even with millions of records.
  4. Wraps table outputs in foreign key check suspensions (`SET FOREIGN_KEY_CHECKS = 0; ... SET FOREIGN_KEY_CHECKS = 1;`).
  5. Compresses output into a gzip or plain SQL file with atomic write patterns.

### 3.2 StorageBackupService (`app/Services/Backup/StorageBackupService.php`)
- **Capabilities**: Packages uploaded media, customer proofs, and branded assets.
- **Strict Security Exclusions**:
  The archiver employs strict exclusion patterns to prevent credential leakage and recursive archive bloat:
  ```php
  protected array $excludedPatterns = [
      '.env',
      '.env.*',
      '.git',
      'node_modules',
      'storage/app/backups',
      'storage/framework/cache',
      'storage/framework/sessions',
      'storage/framework/views',
      'storage/logs',
  ];
  ```

### 3.3 BackupEncryptionService (`app/Services/Backup/BackupEncryptionService.php`)
- **Cryptographic Specifications**:
  - **Cipher**: `AES-256-CBC`
  - **Key Derivation**: Configured via `BACKUP_ENCRYPTION_KEY` in `.env` (falls back securely to `APP_KEY` with distinct domain-separated hashing).
  - **Initialization Vector**: 16 cryptographically secure pseudo-random bytes (`openssl_random_pseudo_bytes(16)`) generated independently for each backup file.
  - **Authentication / Integrity**: `HMAC-SHA256` computed over the initialization vector and ciphertext to prevent padding oracle and bit-flipping attacks.
  - **Archive Header**: The resulting file encapsulates `[HMAC: 32 bytes][IV: 16 bytes][Ciphertext]`.

### 3.4 RemoteStorageService (`app/Services/Backup/RemoteStorageService.php`)
- Transfers completed archives to configured cloud storage disks via Laravel's Filesystem abstraction (`s3`, `r2`, `sftp`).
- Implements validation: if remote credentials are missing or the disk is unavailable, it reports a clear status while retaining the local copy safely.

### 3.5 BackupManagerService (`app/Services/Backup/BackupManagerService.php`)
- Orchestrates the full backup pipeline:
  1. Creates a `BackupRecord` in `in_progress` state.
  2. Runs database dumper and/or storage archiver.
  3. Packages combined artifacts into a single encrypted ZIP archive.
  4. Computes SHA-256 hash and verifies integrity immediately.
  5. Dispatches to remote storage if configured.
  6. Marks record `completed` and `is_verified = true`.
  7. Triggers retention pruning.
- **Safety Pruning Rule**:
  ```php
  // Never prune unless at least one newer verified backup exists!
  $newerVerifiedExists = BackupRecord::where('is_verified', true)
      ->where('status', 'completed')
      ->where('created_at', '>', $candidate->created_at)
      ->exists();

  if ($newerVerifiedExists && $candidate->created_at < now()->subDays($retentionDays)) {
      $this->deleteBackupArchive($candidate);
  }
  ```

### 3.6 RestoreService (`app/Services/Backup/RestoreService.php`)
- Enforces strict disaster recovery protocols:
  1. **Integrity Pre-Check**: Verifies cryptographic checksum and decryptability prior to attempting any system modification.
  2. **Automated Safety Snapshot**: Before executing the restore, takes an automated snapshot of the current database and storage (`leadx-safety-snapshot-before-restore-...`).
  3. **Confirmation Token Enforcement**: Requires human administrative entry of `CONFIRM_RESTORE_<ID>` to guard against accidental triggers.
  4. **Post-Restore State Reconstruction**: Re-inserts and updates the restore audit log into the restored database so recovery history is never wiped out by the restored SQL dump.

---

## 4. ARTISAN CLI COMMANDS & SCHEDULER

Four dedicated Artisan commands provide comprehensive terminal and automated control:

### 4.1 `php artisan leadx:backup`
- **Signature**: `leadx:backup {--database} {--storage} {--type=full}`
- **Description**: Triggers an immediate backup.
- **Usage**:
  ```bash
  # Full backup (database + storage)
  php artisan leadx:backup

  # Database-only backup
  php artisan leadx:backup --database

  # Storage-only backup
  php artisan leadx:backup --storage
  ```

### 4.2 `php artisan leadx:backup:verify`
- **Signature**: `leadx:backup:verify {backup_id?}`
- **Description**: Verifies the file existence, SHA-256 checksum, and cryptographic decryptability of a specific backup or all unverified backups.

### 4.3 `php artisan leadx:backup:prune`
- **Signature**: `leadx:backup:prune {--retention-days=30}`
- **Description**: Prunes expired archives older than the specified retention window, respecting the safety invariant.

### 4.4 `php artisan leadx:backup:restore`
- **Signature**: `leadx:backup:restore {backup_id} {--force}`
- **Description**: Restores the database and/or storage from an existing backup with mandatory safety snapshots and interactive confirmation prompts.

### 4.5 Scheduler Configuration (`routes/console.php`)
Configured with strict task overlap prevention:
```php
// Daily automated encrypted backup at 02:00 UTC
Schedule::command('leadx:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('LeadX Daily Automated Backup Failed');
    });

// Daily retention pruning at 03:00 UTC
Schedule::command('leadx:backup:prune --retention-days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping();
```

---

## 5. SECURITY & ACCESS CONTROLS

1. **Authentication & Guard Enforcement**:
   - Backup management routes are strictly registered under the `admin` guard (`auth:admin`).
   - The Employee portal (`auth:employee`) has zero access to backup routes; requests yield immediate 403 Forbidden or login redirects.
2. **Path Traversal Protection**:
   - The download controller resolves files exclusively using `basename($backup->file_path)` and enforces that the canonical path starts with the configured `storage/app/backups/` root directory.
   - Sibling directory prefixes and directory traversal patterns (`../`, `..\`) are strictly rejected.
3. **Secrets & Credential Hygiene**:
   - Encryption keys and storage credentials are read directly from `.env` or encrypted settings; they are never printed in logs or displayed in UI views.

---

## 6. VERIFICATION & TEST RESULTS

The Backup & Disaster Recovery suite is validated by 21 automated feature tests covering all operational edges:

| Test Class | Test Method | Assertions | Status |
| :--- | :--- | :---: | :---: |
| `BackupAndDisasterRecoveryTest` | `test_backup_encryption_and_decryption_integrity` | 6 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_database_backup_creates_valid_sql_dump` | 5 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_storage_backup_archives_files_and_excludes_sensitive_patterns` | 6 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_backup_manager_creates_full_backup_record` | 8 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_backup_pruning_respects_safety_rule` | 5 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_restore_service_takes_safety_snapshot_and_requires_confirmation` | 7 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_confirmed_restore_executes_and_creates_restore_log` | 8 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_super_admin_can_access_backup_dashboard_and_management_views` | 8 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_super_admin_can_download_and_verify_backups` | 6 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_employee_is_completely_denied_from_all_backup_routes` | 7 | **Passed** |
| `BackupAndDisasterRecoveryTest` | `test_artisan_backup_commands_execute_successfully` | 9 | **Passed** |
| `AdminBackupFrontendTest` | `test_admin_can_open_all_backup_pages_and_filter_history` | 12 | **Passed** |
| `AdminBackupFrontendTest` | `test_empty_backup_and_restore_pages_provide_next_steps` | 6 | **Passed** |
| `AdminBackupFrontendTest` | `test_backup_management_requires_admin_authentication` | 4 | **Passed** |
| `AdminBackupFrontendTest` | `test_backup_settings_are_saved_displayed_and_used_for_pruning` | 8 | **Passed** |
| `AdminBackupFrontendTest` | `test_invalid_time_and_unconfigured_disk_are_rejected_with_old_input` | 7 | **Passed** |
| `AdminBackupFrontendTest` | `test_remote_upload_uses_saved_destination` | 6 | **Passed** |
| `AdminBackupFrontendTest` | `test_create_action_dispatches_chosen_contents_and_reports_partial_failure` | 5 | **Passed** |
| `AdminBackupFrontendTest` | `test_restore_rejects_unverified_archive_wrong_contents_and_missing_confirmation` | 6 | **Passed** |
| `AdminBackupFrontendTest` | `test_confirmed_restore_dispatches_to_service_and_shows_history` | 7 | **Passed** |
| `AdminBackupFrontendTest` | `test_download_rejects_a_sibling_directory_with_the_same_prefix` | 4 | **Passed** |
| **TOTALS** | **21 Tests** | **140 Assertions** | **100% Passed** |

---

## 7. DISASTER RECOVERY RUNBOOK

In the event of a catastrophic failure, server migration, or data corruption, follow this runbook:

1. **Verify Backup Integrity**:
   ```bash
   php artisan leadx:backup:verify <BACKUP_ID>
   ```
2. **Execute Full Restore**:
   ```bash
   php artisan leadx:backup:restore <BACKUP_ID>
   ```
   *Note: The CLI command will automatically generate a safety snapshot before modifying database tables or overwriting files.*
3. **Clear Application Caches**:
   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. **Restart Queue Workers**:
   ```bash
   php artisan queue:restart
   ```
