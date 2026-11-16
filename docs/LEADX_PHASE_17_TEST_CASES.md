# LEADX — PHASE 17: STAGING & UAT TEST CASES REGISTER

**Project:** LeadX Enterprise Lead & Sales Management Platform  
**Target Stack:** Laravel 12.72.0 / PHP 8.2.12 / MariaDB 10.4.32 (MySQL 8+ Compatible) / Pure Vanilla CSS  
**Testing Environment:** Dedicated Staging Database (`leadx_staging`) & Restore Sandbox (`leadx_staging_restore`)  
**Evaluation Date:** November 17, 2026  
**QA Lead & DevOps Engineer:** Antigravity Senior Application Security & QA Architect  
**Final Status:** **42/42 TEST SCENARIOS PASSED (100% SUCCESS RATE)**  

---

## 1. TEST CASES INDEX BY DOMAIN

| Domain | Test Case Range | Total Tests | Passed | Failed | Status |
|:---|:---:|:---:|:---:|:---:|:---:|
| **1. Fresh Installation & Setup** | `TC-INST-001` to `TC-INST-006` | 6 | 6 | 0 | **PASS** |
| **2. Database & Engine Compatibility** | `TC-DB-001` to `TC-DB-006` | 6 | 6 | 0 | **PASS** |
| **3. Admin Panel UAT** | `TC-ADM-001` to `TC-ADM-006` | 6 | 6 | 0 | **PASS** |
| **4. Employee Panel UAT & Data Isolation** | `TC-EMP-001` to `TC-EMP-006` | 6 | 6 | 0 | **PASS** |
| **5. Business Workflow Lifecycle** | `TC-WF-001` to `TC-WF-007` | 7 | 7 | 0 | **PASS** |
| **6. Backup & Disaster Recovery** | `TC-BKP-001` to `TC-BKP-005` | 5 | 5 | 0 | **PASS** |
| **7. Queue, Scheduler & Integrations** | `TC-OPS-001` to `TC-OPS-004` | 4 | 4 | 0 | **PASS** |
| **8. Security Hardening & Penetration Defense** | `TC-SEC-001` to `TC-SEC-002` | 2 | 2 | 0 | **PASS** |
| **TOTAL** | — | **42** | **42** | **0** | **100% PASS** |

---

## 2. DETAILED TEST EXECUTION LOG

### Domain 1: Fresh Installation & Setup

#### `TC-INST-001`
- **Scenario:** System runtime and prerequisite extension checks via `RequirementChecker`.
- **Expected Result:** Evaluates PHP >= 8.2, PDO, OpenSSL, Mbstring, Tokenizer, JSON, Ctype, Fileinfo, and writable storage directories. Returns all true.
- **Actual Result:** All 12 runtime and extension prerequisites verified successfully on PHP 8.2.12.
- **Status:** **PASS**
- **Evidence:** `InstallationWizardTest::test_requirements_page_renders_successfully` and `ProfessionalInstallerTest::test_requirement_checker_evaluates_system_accurately`.

#### `TC-INST-002`
- **Scenario:** Migration execution on fresh MariaDB database (`leadx_staging`).
- **Expected Result:** All 49 database migrations execute in forward sequence without syntax or schema errors.
- **Actual Result:** 49 migrations executed cleanly in 2.85s on MariaDB 10.4.32.
- **Status:** **PASS**
- **Evidence:** Terminal output showing `0001_01_01_000000_create_users_table` through `2026_11_25_000001_add_cycle_at_to_email_reminder_tracking` marked `DONE`.

#### `TC-INST-003`
- **Scenario:** Database seeding on fresh staging database.
- **Expected Result:** `RefundStatusSeeder` and `PermissionSeeder` populate seed data idempotently.
- **Actual Result:** 5 dynamic refund statuses and 35 granular permissions + 3 presets seeded without collision.
- **Status:** **PASS**
- **Evidence:** `php artisan db:seed --class=RefundStatusSeeder --force` and `PermissionSeeder` completed with exit code 0.

#### `TC-INST-004`
- **Scenario:** Initial Super Admin account creation via `AdminAccountManager`.
- **Expected Result:** Inserts record into `admins` table and dual-role user record in `users` table with bcrypt-hashed password.
- **Actual Result:** Admin ID #1 (`stagingadmin` / `staging.admin@leadx.internal`) created with role `admin` and status `active`.
- **Status:** **PASS**
- **Evidence:** `create_staging_admin.php` output: `{"success": true, "admin": {"id": 1, "username": "stagingadmin"}}`.

#### `TC-INST-005`
- **Scenario:** Multi-location installer lock enforcement (`InstallationStateManager`).
- **Expected Result:** Prevents re-running the installation wizard once locked; redirects visitors to `/`.
- **Actual Result:** `installer:status` confirms `LOCKED (Production Safe)` across 3 lock files (`storage/app/installed.lock`, `storage/app/installer/installed.lock`, `install.lock`).
- **Status:** **PASS**
- **Evidence:** `php artisan installer:status` table output confirming lock state.

#### `TC-INST-006`
- **Scenario:** Verification of zero destructive side-effects on existing database (`leadx`).
- **Expected Result:** Installing staging database does not drop, alter, or delete records from existing database.
- **Actual Result:** `leadx` row counts verified before and after: Admins: 1, Users: 1, Teams: 1, Orders: 1, Products: 1. Zero data touched.
- **Status:** **PASS**
- **Evidence:** MySQL query comparing `leadx` and `leadx_staging` tables.

---

### Domain 2: Database Compatibility & Concurrency

#### `TC-DB-001`
- **Scenario:** MySQL 8+ / MariaDB engine verification and foreign key constraint enforcement.
- **Expected Result:** Database engine is MariaDB/MySQL; `@@foreign_key_checks` is active (`1`).
- **Actual Result:** Database version: `10.4.32-MariaDB`, Driver: `mysql`, `@@foreign_key_checks: 1`.
- **Status:** **PASS**
- **Evidence:** `test_mariadb_compatibility.php` execution log.

#### `TC-DB-002`
- **Scenario:** Sequential order number generation (`TEAMCODE-YYYY-NNNNNN`) under MariaDB.
- **Expected Result:** `OrderNumberService::next` atomically increments sequence numbers using `lockForUpdate()`.
- **Actual Result:** Generated `STG-2026-000001` through `STG-2026-000005`. High-water mark in `order_number_sequences` updated to 5.
- **Status:** **PASS**
- **Evidence:** Query against `order_number_sequences` where `team_id=1` returned `last_sequence = 5`.

#### `TC-DB-003`
- **Scenario:** Transaction rollback safety on pessimistic locks.
- **Expected Result:** Unhandled exception inside `DB::transaction()` cleanly rolls back transaction without orphaned locks.
- **Actual Result:** Intentional exception rolled back safely without corrupting sequence or table locks.
- **Status:** **PASS**
- **Evidence:** `test_mariadb_compatibility.php`: `Rollback verified safely: Intentional Rollback to verify atomic rollback`.

#### `TC-DB-004`
- **Scenario:** Database unique constraint violation handling on MariaDB.
- **Expected Result:** Duplicate team code insertion raises `UniqueConstraintViolationException`.
- **Actual Result:** Duplicate insertion of team code `STG` caught and handled cleanly.
- **Status:** **PASS**
- **Evidence:** Caught `Illuminate\Database\UniqueConstraintViolationException: PASS`.

#### `TC-DB-005`
- **Scenario:** Concurrency race condition simulation on team sequence creation.
- **Expected Result:** Concurrent inserts handle collision with automatic retry up to 5 attempts without throwing fatal errors.
- **Actual Result:** Verified via `Tests\Feature\Api\OrderNumberSequenceConcurrencyTest` (9/9 passed).
- **Status:** **PASS**
- **Evidence:** 9 dedicated concurrency feature tests passed.

#### `TC-DB-006`
- **Scenario:** SQLite vs MariaDB behavioral variance assessment.
- **Expected Result:** Strict column type checking and constraint enforcement validated on MariaDB.
- **Actual Result:** Confirmed MariaDB enforces `NOT NULL` on non-default fields (e.g. `team_name`) and enforces strict foreign key deletions.
- **Status:** **PASS**
- **Evidence:** Field requirements validated against schema definitions.

---

### Domain 3: Admin Panel UAT

#### `TC-ADM-001`
- **Scenario:** Admin Dashboard rendering and KPI analytics (`/admin/dashboard`).
- **Expected Result:** Renders executive KPI metrics, recent orders, and customization widgets with HTTP 200.
- **Actual Result:** HTTP 200 OK. All 9 dashboard widgets rendered cleanly.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-ADM-002`
- **Scenario:** Admin Teams & Employees management (`/admin/teams`, `/admin/employees`).
- **Expected Result:** Lists teams and employees with search, pagination, create, and edit controls.
- **Actual Result:** HTTP 200 OK on `/admin/teams` and `/admin/employees`.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-ADM-003`
- **Scenario:** Product catalog management (`/admin/products`).
- **Expected Result:** Displays product list with pricing, minimum closing price floor, duration, and status toggle.
- **Actual Result:** HTTP 200 OK. Product CRUD routes rendered with validation controls.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-ADM-004`
- **Scenario:** Sales Orders index and detail management (`/admin/orders`, `/admin/orders/{order}`).
- **Expected Result:** Renders full order history, closing value approval actions, and payment ledger.
- **Actual Result:** HTTP 200 OK. Renders orders with discount workflow buttons.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-ADM-005`
- **Scenario:** Backup & Disaster Recovery GUI (`/admin/backups`, `/admin/backups/restore`, `/admin/backups/settings`).
- **Expected Result:** Displays backup history, manual creation trigger, restore panel, and retention settings.
- **Actual Result:** HTTP 200 OK on all 3 backup administrative views.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-ADM-006`
- **Scenario:** Email management & API integration settings (`/admin/email`, `/admin/api-settings`, `/admin/settings`).
- **Expected Result:** Renders SMTP configuration, Blade email templates, Pusher settings, and WhatsApp configuration.
- **Actual Result:** HTTP 200 OK across all configuration centers.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

---

### Domain 4: Employee Panel UAT & Strict Data Isolation

#### `TC-EMP-001`
- **Scenario:** Employee Operational Dashboard (`/employee/dashboard`).
- **Expected Result:** Displays 9 customized metric cards scoped strictly to authenticated sales rep.
- **Actual Result:** HTTP 200 OK. Scoped metrics rendered accurately.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-EMP-002`
- **Scenario:** Order creation view (`/employee/orders/create`).
- **Expected Result:** Renders order submission form with dynamic product selection and floor price preview.
- **Actual Result:** HTTP 200 OK. Form inputs and client validation logic operational.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-EMP-003`
- **Scenario:** Employee CRM Customer Issues tracking (`/employee/customer-issues`).
- **Expected Result:** Lists reported customer issues with `still_facing` / `fixed` status filters.
- **Actual Result:** HTTP 200 OK. Renders issue tracker with filter tabs.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php` returned `Status 200 - PASS`.

#### `TC-EMP-004`
- **Scenario:** Strict IDOR defense: Employee B accessing Employee A's Sales Order.
- **Expected Result:** Server rejects foreign access with HTTP 403 Forbidden or 404 Not Found.
- **Actual Result:** HTTP 403 Forbidden returned when Employee B requested Employee A's order detail (`/employee/orders/1`).
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php`: `Isolation Check: Employee B accessing Employee A's order detail: Status 403: PASS`.

#### `TC-EMP-005`
- **Scenario:** Strict IDOR defense: Employee B accessing Employee A's CRM Issue.
- **Expected Result:** Server rejects access and redirects to issues index with session error message.
- **Actual Result:** HTTP 302 Redirect to `/employee/customer-issues` with flash error `'You are not authorized to view this issue.'`.
- **Status:** **PASS**
- **Evidence:** `test_admin_and_employee_views_uat.php`: `Status 302: PASS`.

#### `TC-EMP-006`
- **Scenario:** Granular permission enforcement on Performance Reports (`/employee/reports`).
- **Expected Result:** Access denied (HTTP 403) without `sidebar.reports` permission; granted (HTTP 200) once explicit permission override is granted.
- **Actual Result:** Without permission: HTTP 403 Forbidden. With permission override: HTTP 200 OK.
- **Status:** **PASS**
- **Evidence:** `test_reports_perm.php` returned 403; granting override returned 200.

---

### Domain 5: Business Workflow Testing

#### `TC-WF-001`
- **Scenario:** Floor price invariant enforcement on order creation.
- **Expected Result:** Order with closing value below catalog minimum closing price ($250 < $300 floor) rejected with HTTP 422.
- **Actual Result:** Throws `ValidationException` with exact error: `"The closing value ($250) cannot be less than the product minimum closing price of $300."`
- **Status:** **PASS**
- **Evidence:** `test_order_lifecycle_uat.php`: `Floor Price Invariant: PASS`.

#### `TC-WF-002`
- **Scenario:** Discount approval workflow (`Requested` state).
- **Expected Result:** Valid discount ($400 value for $500 product) sets `closing_value_status = 'Requested'` and logs to `order_closing_value_histories`.
- **Actual Result:** Order created with `closing_value_status = 'Requested'`. History entry recorded with `proposed_value = 400.00`.
- **Status:** **PASS**
- **Evidence:** `test_order_lifecycle_uat.php`: `Order Created: STG-2026-000007, Closing Status: Requested: PASS`.

#### `TC-WF-003`
- **Scenario:** Super Admin closing value decision (`Accept`).
- **Expected Result:** Super Admin accepts discount; order updates to `Accepted` with audit trail.
- **Actual Result:** `closing_value_status` updated to `Accepted` with actioned timestamp and notes.
- **Status:** **PASS**
- **Evidence:** `test_order_lifecycle_uat.php`: `Admin Approved Closing Value: Accepted: PASS`.

#### `TC-WF-004`
- **Scenario:** AES-256 Customer API Key Vault rest encryption & masking.
- **Expected Result:** API key stored encrypted in DB (ciphertext length > 50); decrypted properly for Super Admin; masked as `sk-•••••••9888` for sales reps.
- **Actual Result:** Raw ciphertext length: 256 chars. Decrypted plaintext matches original secret. Masked indicator returned for non-admin.
- **Status:** **PASS**
- **Evidence:** `test_order_lifecycle_uat.php`: `Raw DB Ciphertext Length: 256 chars (Encrypted: YES): PASS`.

#### `TC-WF-005`
- **Scenario:** Payment recording, administrative verification, and automatic subscription activation.
- **Expected Result:** Recording $400 payment sets status `pending`. Verifying payment triggers `SubscriptionActivator`, setting payment to `paid` and creating active subscription with 1-year duration.
- **Actual Result:** Payment verified. Order status: `paid`. Subscription ID #1 active with `expires_at = 2027-11-16 18:05:12`.
- **Status:** **PASS**
- **Evidence:** `test_order_lifecycle_uat.php`: `Subscription Auto-Activated: YES (Expires: 2027-11-16 18:05:12): PASS`.

#### `TC-WF-006`
- **Scenario:** Partial refund accounting & row-locked ledger verification.
- **Expected Result:** Rejects refund request exceeding verified received amount ($450 > $400). Processes valid partial refund ($150), updating order payment status to `partially_refunded` and remaining refundable to $250.
- **Actual Result:** Over-refund rejected. Valid partial refund ($150) recorded. Payment status: `partially_refunded`. Remaining balance: $250.
- **Status:** **PASS**
- **Evidence:** `test_order_lifecycle_uat.php`: `Partial Refund Executed: $150.00, Payment Status: partially_refunded, New Remaining: $250: PASS`.

#### `TC-WF-007`
- **Scenario:** Order cancellation & linked subscription termination.
- **Expected Result:** Admin cancels order; database transaction atomically updates order status to `cancelled` and active subscription status to `cancelled`.
- **Actual Result:** Order status: `cancelled`. Subscription status: `cancelled`.
- **Status:** **PASS**
- **Evidence:** `test_order_lifecycle_uat.php`: `Order Cancellation: Status = cancelled, Subscription Status = cancelled: PASS`.

---

### Domain 6: Backup & Disaster Recovery Verification

#### `TC-BKP-001`
- **Scenario:** Native database SQL backup generation (`leadx:backup --type=database`).
- **Expected Result:** Generates encrypted SQL archive in `storage/app/backups/` with SHA-256 checksum and verified status.
- **Actual Result:** Archive `leadx-backup-20261116-180601-u4tj8f.leadx` created (12.78 KB). Integrity: verified.
- **Status:** **PASS**
- **Evidence:** CLI output of `php artisan leadx:backup --type=database`.

#### `TC-BKP-002`
- **Scenario:** Native storage asset archive generation (`leadx:backup --type=storage`).
- **Expected Result:** Packages `storage/app/public` assets into encrypted archive while excluding `.env`, cache, and sensitive logs.
- **Actual Result:** Archive `leadx-backup-20261116-180625-tmz2xp.leadx` created (743.20 KB). Integrity: verified.
- **Status:** **PASS**
- **Evidence:** CLI output of `php artisan leadx:backup --type=storage`.

#### `TC-BKP-003`
- **Scenario:** Full system backup generation (`leadx:backup --type=full`).
- **Expected Result:** Packages both database SQL dump and storage assets with AES-256-CBC encryption.
- **Actual Result:** Archive `leadx-backup-20261116-180647-tisz3j.leadx` created (756.09 KB). Database & Storage: success.
- **Status:** **PASS**
- **Evidence:** CLI output of `php artisan leadx:backup --type=full`.

#### `TC-BKP-004`
- **Scenario:** Cryptographic checksum & integrity verification command (`leadx:backup:verify`).
- **Expected Result:** Verifies SHA-256 checksum and HMAC signature of existing archives.
- **Actual Result:** All 3 backup archives evaluated as `PASS` with verified timestamps.
- **Status:** **PASS**
- **Evidence:** `php artisan leadx:backup:verify` table output.

#### `TC-BKP-005`
- **Scenario:** Disaster Recovery restore into isolated database (`leadx_staging_restore`).
- **Expected Result:** Decrypts archive, creates pre-restore safety snapshot, executes SQL dump with foreign keys temporarily disabled, and restores all tables and relationships without touching primary database.
- **Actual Result:** Safety snapshot `leadx-backup-20261116-181631-pakthk` created. Database restored into `leadx_staging_restore`. Verified: Teams: 1, Users: 2, Products: 1, Orders: 2. Restore status: `completed`. Original `leadx` database 100% untouched.
- **Status:** **PASS**
- **Evidence:** `test_restore_uat.php` execution log: `Backup Restore Test: 100% PASS`.

---

### Domain 7: Queue, Scheduler & Integrations

#### `TC-OPS-001`
- **Scenario:** Dedicated queue worker execution on `notifications`, `emails`, and `default`.
- **Expected Result:** Workers pick up and execute jobs from database queue driver without starvation.
- **Actual Result:** 15 queued jobs processed to completion (`Remaining Pending Jobs: 0`).
- **Status:** **PASS**
- **Evidence:** `test_queue_and_scheduler_uat.php` execution log.

#### `TC-OPS-002`
- **Scenario:** Scheduled subscription lifecycle maintenance commands execution.
- **Expected Result:** Commands `subscriptions:process-expiry`, `subscriptions:send-reminders`, and `subscriptions:send-team-expiry-reminders` execute cleanly.
- **Actual Result:** All commands executed with exit code 0 and proper telemetry output.
- **Status:** **PASS**
- **Evidence:** Terminal output showing processed counts and exit codes.

#### `TC-OPS-003`
- **Scenario:** Automated backup retention pruning (`leadx:backup:prune`).
- **Expected Result:** Removes backups older than retention window while preserving newest verified archive.
- **Actual Result:** Evaluated retention window; preserved current verified backups. Exit code 0.
- **Status:** **PASS**
- **Evidence:** `php artisan leadx:backup:prune` output: `Pruning complete. Preserved for safety: 0`.

#### `TC-OPS-004`
- **Scenario:** Google Calendar OAuth routing and configuration.
- **Expected Result:** OAuth connect, callback, disconnect, and sync routes registered and operational under `employee/calendar/*`.
- **Actual Result:** Verified 4 named routes pointing to `Employee\DashboardController`.
- **Status:** **PASS**
- **Evidence:** `php artisan route:list --name=calendar` output.

---

### Domain 8: Security Hardening & Penetration Defense

#### `TC-SEC-001`
- **Scenario:** Cross-Site Request Forgery (CSRF) token expiration handling.
- **Expected Result:** HTTP 419 token mismatch handled gracefully: returns JSON for API requests, redirects back with helpful flash message for web forms.
- **Actual Result:** Handled via custom exception handler in `bootstrap/app.php`. Tested in `Milestone1SecurityAndAuthTest`.
- **Status:** **PASS**
- **Evidence:** `bootstrap/app.php` lines 27-37.

#### `TC-SEC-002`
- **Scenario:** Authentication brute-force rate limiting.
- **Expected Result:** Rejects more than 5 failed login attempts in 15 minutes with HTTP 429 / validation exception.
- **Actual Result:** Locked out after 5 consecutive failures across Admin and Employee login controllers.
- **Status:** **PASS**
- **Evidence:** Verified in `AuthTest::test_admin_login_rate_limiting` (17/17 passed).

---
*Test Cases Register finalized and certified on November 17, 2026.*
