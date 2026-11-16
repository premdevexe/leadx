# LEADX — SUPER ADMIN PAGE & MODULE INVENTORY

**Project:** LeadX Enterprise  
**Framework:** Laravel 12.0  
**Target Role:** Super Admin (`auth:admin`)  
**Frontend Architecture:** Blade + HTML5 + CSS3 + Vanilla JavaScript  
**Status:** Verified & Complete  
**Date:** November 2026  

---

## 1. OVERVIEW & GOVERNANCE

The Super Admin portal in LeadX is the centralized command and control hub for organizational management, sales processing, financial verification, team performance, system configuration, and disaster recovery.

All Super Admin web routes are secured under the `admin` guard with `App\Http\Middleware\AuthenticateAdmin`, ensuring complete multi-guard isolation from the employee and team portals. All destructive operations incorporate CSRF protection, permission checks, and confirmation dialogues.

---

## 2. COMPREHENSIVE MODULE INVENTORY

### Module 1: Dashboard & Analytics Overview

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Admin Dashboard** | `admin.dashboard` | `GET` | `/admin/dashboard` | `Admin\DashboardController@index` | `resources/views/admin/dashboard.blade.php` | 15 KPI metric counters, urgent action pipeline, 6-month sales trajectory bar chart, real-time activity stream, integration status badges. |
| **Save Preferences** | `admin.dashboard.preferences.save` | `POST` | `/admin/dashboard/preferences` | `Admin\DashboardController@savePreferences` | *(Redirect)* | Persists dashboard widget arrangement, hidden/visible cards, and custom alert thresholds. |
| **Reset Preferences** | `admin.dashboard.preferences.reset` | `POST` | `/admin/dashboard/preferences/reset` | `Admin\DashboardController@resetPreferences` | *(Redirect)* | Resets dashboard cards to system default state. |
| **Analytics Overview** | `admin.reports` | `GET` | `/admin/reports` | `Admin\ReportsController@index` | `resources/views/admin/reports/index.blade.php` | Deep analytics on gross revenue, team conversion rates, refund percentages, and product performance. |

---

### Module 2: Employee Management

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Employee Directory** | `admin.employees` | `GET` | `/admin/employees` | `Admin\EmployeesController@index` | `resources/views/admin/employees/index.blade.php` | Paginated list of staff, team badges, active status toggle, inline delete trigger with modal. |
| **Add Employee** | `admin.employees.create` | `GET` | `/admin/employees/create` | `Admin\EmployeesController@create` | `resources/views/admin/employees/create.blade.php` | Employee onboarding form: name, email, password, team selection, role preset assignment. |
| **Store Employee** | `admin.employees.store` | `POST` | `/admin/employees` | `Admin\EmployeesController@store` | *(Redirect)* | Validates input, provisions user, assigns default permissions, dispatches welcome notification. |
| **Edit Employee** | `admin.employees.edit` | `GET` | `/admin/employees/{employee}/edit` | `Admin\EmployeesController@edit` | `resources/views/admin/employees/edit.blade.php` | Profile editor, status toggle (Active/Inactive), team transfer, password reset fields. |
| **Update Employee** | `admin.employees.update` | `PUT` | `/admin/employees/{employee}` | `Admin\EmployeesController@update` | *(Redirect)* | Updates profile data, handles team transfers, and refreshes session cache. |
| **Delete Employee** | `admin.employees.destroy` | `DELETE` | `/admin/employees/{employee}` | `Admin\EmployeesController@destroy` | *(Redirect)* | Soft/hard delete with referential integrity checks (prevents deleting employees with active orders). |
| **Revoke Team Access** | `admin.employees.team-access.revoke` | `POST` | `/admin/employees/team-access/{grant}/revoke` | `Admin\TeamAccessGrantsController@revoke` | *(Redirect)* | Revokes temporary cross-team access grants. |

---

### Module 3: Team Management

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Team Directory** | `admin.teams` | `GET` | `/admin/teams` | `Admin\TeamsController@index` | `resources/views/admin/teams/index.blade.php` | List of sales teams, team codes, member count, total sales volume, status badges. |
| **Create Team** | `admin.teams.create` | `GET` | `/admin/teams/create` | `Admin\TeamsController@create` | `resources/views/admin/teams/create.blade.php` | Team setup form: team name, unique team code (e.g. `ALPHA`), lead selection. |
| **Store Team** | `admin.teams.store` | `POST` | `/admin/teams` | `Admin\TeamsController@store` | *(Redirect)* | Validates unique uppercase team code, creates team record. |
| **Edit Team** | `admin.teams.edit` | `GET` | `/admin/teams/{team}/edit` | `Admin\TeamsController@edit` | `resources/views/admin/teams/edit.blade.php` | Edit team details, team code lock status (locked if orders exist). |
| **Update Team** | `admin.teams.update` | `PUT` | `/admin/teams/{team}` | `Admin\TeamsController@update` | *(Redirect)* | Saves team modifications. |
| **Delete Team** | `admin.teams.destroy` | `DELETE` | `/admin/teams/{team}` | `Admin\TeamsController@destroy` | *(Redirect)* | Deletes team if no assigned employees or orders remain. |

---

### Module 4: Product Catalog Management

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Product List** | `admin.products` | `GET` | `/admin/products` | `Admin\ProductsController@index` | `resources/views/admin/products/index.blade.php` | Product catalog table, pricing tiers, minimum closing thresholds, API key requirements. |
| **Create Product** | `admin.products.create` | `GET` | `/admin/products/create` | `Admin\ProductsController@create` | `resources/views/admin/products/create.blade.php` | Name, description, default price, minimum allowable closing price, recurring billing cycles. |
| **Store Product** | `admin.products.store` | `POST` | `/admin/products` | `Admin\ProductsController@store` | *(Redirect)* | Validates pricing constraints and creates catalog product. |
| **Edit Product** | `admin.products.edit` | `GET` | `/admin/products/{product}/edit` | `Admin\ProductsController@edit` | `resources/views/admin/products/edit.blade.php` | Update pricing rules, active status, minimum closing values, API key toggles. |
| **Update Product** | `admin.products.update` | `PUT` | `/admin/products/{product}` | `Admin\ProductsController@update` | *(Redirect)* | Commits product catalog updates. |
| **Delete Product** | `admin.products.destroy` | `DELETE` | `/admin/products/{product}` | `Admin\ProductsController@destroy` | *(Redirect)* | Deactivates or removes product. |

---

### Module 5: Order Processing & Closing Approval

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **All Orders** | `admin.orders` | `GET` | `/admin/orders` | `Admin\OrdersController@index` | `resources/views/admin/orders/index.blade.php` | Global orders ledger, multi-status filters (Pending, Approved, Rejected, Cancelled), team code filtering, search. |
| **Create Order** | `admin.orders.create` | `GET` | `/admin/orders/create` | `Admin\OrdersController@create` | `resources/views/admin/orders/create.blade.php` | Direct admin order creation form, customer lookup, product pricing override. |
| **Store Order** | `admin.orders.store` | `POST` | `/admin/orders` | `Admin\OrdersController@store` | *(Redirect)* | Validates closing price, checks customer duplicates, generates sequential order number. |
| **Order Details** | `admin.orders.show` | `GET` | `/admin/orders/{order}` | `Admin\OrdersController@show` | `resources/views/admin/orders/show.blade.php` | Comprehensive order dossier: customer details, item breakdown, payment logs, proof attachments, closing approval controls. |
| **Edit Order** | `admin.orders.edit` | `GET` | `/admin/orders/{order}/edit` | `Admin\OrdersController@edit` | `resources/views/admin/orders/edit.blade.php` | Modify order line items, discount amounts, delivery notes (restricted if paid). |
| **Update Order** | `admin.orders.update` | `PUT` | `/admin/orders/{order}` | `Admin\OrdersController@update` | *(Redirect)* | Saves updated order parameters. |
| **Approve Closing Value**| `admin.orders.approve`| `POST` | `/admin/orders/{order}/approve`| `Admin\OrdersController@approve` | *(Redirect)* | Approves an order where closing price was below product minimum threshold. |
| **Reject Closing Value** | `admin.orders.reject` | `POST` | `/admin/orders/{order}/reject` | `Admin\OrdersController@reject` | *(Redirect)* | Rejects closing price, prompts employee for price revision. |

---

### Module 6: Customer CRM

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Customer Directory** | `admin.customers` | `GET` | `/admin/customers` | `Admin\CustomersController@index` | `resources/views/admin/customers/index.blade.php` | Centralized customer CRM table, lifetime value, total orders count, verification status badges. |
| **Customer Dossier** | `admin.customers.show` | `GET` | `/admin/customers/{customer}` | `Admin\CustomersController@show` | `resources/views/admin/customers/show.blade.php` | Customer 360-degree profile: contact details, purchase timeline, active subscriptions, payment history, support notes. |

---

### Module 7: Payment, Verification & Refund Management

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Record Payment** | `admin.payments.store` | `POST` | `/admin/orders/{order}/payments` | `Admin\PaymentsController@store` | *(Redirect)* | Records manual wire transfer, cash, or card payment against an order. |
| **Verify Payment** | `admin.payments.verify` | `POST` | `/admin/payments/{payment}/verify` | `Admin\PaymentsController@verify` | *(Redirect)* | Verifies transaction proof, marks payment verified, updates order status to Completed. |
| **Reject Payment** | `admin.payments.reject` | `POST` | `/admin/payments/{payment}/reject` | `Admin\PaymentsController@reject` | *(Redirect)* | Rejects invalid payment slip with mandatory audit rejection reason. |
| **Process Refund** | `admin.refunds.store` | `POST` | `/admin/payments/{payment}/refund` | `Admin\RefundsController@store` | *(Redirect)* | Initiates full or partial refund against verified payment, records accounting notes. |

---

### Module 8: Subscription Management

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Subscription List** | `admin.subscriptions` | `GET` | `/admin/subscriptions` | `Admin\SubscriptionsController@index` | `resources/views/admin/subscriptions/index.blade.php` | Active, grace-period, expired, and cancelled recurring subscription plans with billing cycle markers. |
| **Subscription Dossier**| `admin.subscriptions.show`| `GET` | `/admin/subscriptions/{subscription}`| `Admin\SubscriptionsController@show` | `resources/views/admin/subscriptions/show.blade.php` | Renewal date schedule, customer info, linked orders, manual expiration trigger. |

---

### Module 9: Role & Preset Management

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Preset Directory** | `admin.presets` | `GET` | `/admin/presets` | `Admin\PresetsController@index` | `resources/views/admin/presets/index.blade.php` | Role presets list (e.g. Senior Closer, Junior Agent, Support Specialist) and assigned member counts. |
| **Create Preset** | `admin.presets.create` | `GET` | `/admin/presets/create` | `Admin\PresetsController@create` | `resources/views/admin/presets/create.blade.php` | Preset builder form with granular permission checkboxes across orders, customers, and reports. |
| **Store Preset** | `admin.presets.store` | `POST` | `/admin/presets` | `Admin\PresetsController@store` | *(Redirect)* | Validates and persists new permission template. |
| **Edit Preset** | `admin.presets.edit` | `GET` | `/admin/presets/{preset}/edit` | `Admin\PresetsController@edit` | `resources/views/admin/presets/edit.blade.php` | Modify existing permission presets. |
| **Update Preset** | `admin.presets.update` | `PUT` | `/admin/presets/{preset}` | `Admin\PresetsController@update` | *(Redirect)* | Updates template and cascades permissions to assigned employees. |

---

### Module 10: Admin Governance & System Settings

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Admin Accounts** | `admin.admins` | `GET` | `/admin/admins` | `Admin\AdminsController@index` | `resources/views/admin/admins/index.blade.php` | List of administrative users with active status and audit creation metadata. |
| **Create Admin** | `admin.admins.create` | `GET` | `/admin/admins/create` | `Admin\AdminsController@create` | `resources/views/admin/admins/create.blade.php` | Super Admin provisioning form with secure credential generation. |
| **Edit Admin** | `admin.admins.edit` | `GET` | `/admin/admins/{admin}/edit` | `Admin\AdminsController@edit` | `resources/views/admin/admins/edit.blade.php` | Update administrative privileges and credentials. |
| **System Settings** | `admin.settings` | `GET` | `/admin/settings` | `Admin\SettingsController@index` | `resources/views/admin/settings/index.blade.php` | Company branding, primary logo, transparent logo, favicon upload, system timezone, currency symbol. |
| **Update Settings** | `admin.settings.update` | `PUT` | `/admin/settings` | `Admin\SettingsController@update` | *(Redirect)* | Saves company parameters and refreshes configuration cache. |

---

### Module 11: Notification Hub & External Integrations

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Notifications Hub** | `admin.notifications` | `GET` | `/admin/notifications` | `Admin\NotificationsController@index` | `resources/views/admin/notifications/index.blade.php` | Manual broadcast notification dispatch, history log, delivery failure rates. |
| **Send Broadcast** | `admin.notifications.send` | `POST` | `/admin/notifications/send` | `Admin\NotificationsController@send` | *(Redirect)* | Broadcasts asynchronous push notification via Pusher Beams queue. |
| **API Settings** | `admin.api-settings.index` | `GET` | `/admin/api-settings` | `Admin\ApiSettingsController@index` | `resources/views/admin/api-settings/index.blade.php` | Dynamic credential configuration tabs for Pusher Beams, Google OAuth, and WhatsApp Cloud API. |
| **Update Pusher** | `admin.api-settings.pusher.update` | `PUT` | `/admin/api-settings/pusher` | `Admin\ApiSettingsController@updatePusher` | *(Redirect)* | Validates and updates Pusher Beams Instance ID and Secret Key. |
| **Update Google** | `admin.api-settings.google.update` | `PUT` | `/admin/api-settings/google` | `Admin\ApiSettingsController@updateGoogle` | *(Redirect)* | Validates and updates Google OAuth Client ID, Secret, and Redirect URI. |

---

### Module 12: Email Governance & Lifecycle Reminders

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Email Management** | `admin.email.index` | `GET` | `/admin/email` | `Admin\EmailSettingsController@index` | `resources/views/admin/email/index.blade.php` | SMTP configuration, outgoing email logs, template editor, test email sender, automated reminder schedule. |
| **Update SMTP** | `admin.email.smtp.update` | `PUT` | `/admin/email/smtp` | `Admin\EmailSettingsController@updateSmtp` | *(Redirect)* | Reconfigures mail host, port, username, password, encryption (`tls`/`ssl`), and sender identity. |
| **Test SMTP** | `admin.email.test` | `POST` | `/admin/email/test` | `Admin\EmailSettingsController@testEmail` | *(Redirect)* | Dispatches live test email to verify SMTP handshake and TLS certificate. |
| **Update Reminders** | `admin.email.reminders.update` | `PUT` | `/admin/email/reminders` | `Admin\EmailSettingsController@updateReminders` | *(Redirect)* | Adjusts automated subscription expiration warning offsets (e.g. 7 days, 3 days, 1 day before expiry). |

---

### Module 13: Automated Backup & Disaster Recovery Center

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Backups Dashboard** | `admin.backups.index` | `GET` | `/admin/backups` | `Admin\BackupController@index` | `resources/views/admin/backups/index.blade.php` | History of all archives, size, disk, encryption status, SHA-256 checksum, manual backup trigger modal. |
| **Create Backup** | `admin.backups.store` | `POST` | `/admin/backups` | `Admin\BackupController@store` | *(Redirect)* | Triggers immediate Database, Storage, or Full encrypted backup dispatch. |
| **Backup Details** | `admin.backups.show` | `GET` | `/admin/backups/{backup}` | `Admin\BackupController@show` | `resources/views/admin/backups/show.blade.php` | Cryptographic verification status, SHA-256 integrity hash, disk storage details, download button. |
| **Download Backup** | `admin.backups.download` | `GET` | `/admin/backups/{backup}/download` | `Admin\BackupController@download` | *(File Stream)* | Path-traversal protected binary stream download for authorized Super Admins. |
| **Verify Backup** | `admin.backups.verify` | `POST` | `/admin/backups/{backup}/verify` | `Admin\BackupController@verify` | *(Redirect)* | Re-verifies HMAC-SHA256 signature and decryptability on demand. |
| **Restore Center** | `admin.backups.restore` | `GET` | `/admin/backups/restore` | `Admin\BackupController@restoreView` | `resources/views/admin/backups/restore.blade.php` | Disaster recovery workspace: displays verified archives, historical restore audit log, danger warning banner. |
| **Perform Restore** | `admin.backups.restore.perform`| `POST` | `/admin/backups/restore` | `Admin\BackupController@performRestore`| *(Redirect)* | Validates confirmation token (`CONFIRM_RESTORE_<ID>`), executes pre-restore safety snapshot, and restores state. |
| **Backup Settings** | `admin.backups.settings` | `GET` | `/admin/backups/settings` | `Admin\BackupController@settings` | `resources/views/admin/backups/settings.blade.php` | Daily schedule time (UTC), retention days (e.g. 30 days), destination disk selector (Local, S3, R2, SFTP). |
| **Update Settings** | `admin.backups.settings.update`| `PUT` | `/admin/backups/settings` | `Admin\BackupController@updateSettings`| *(Redirect)* | Saves automated backup parameters. |
| **Delete Backup** | `admin.backups.destroy` | `DELETE` | `/admin/backups/{backup}` | `Admin\BackupController@destroy` | *(Redirect)* | Deletes backup file from storage and removes database record (subject to safety rule). |

---

### Module 14: Recovery Center (Installer & Health)

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Elements & Capabilities |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Installer Recovery** | `admin.installer.recovery` | `GET` | `/admin/installer/recovery` | `Admin\InstallerRecoveryController@index` | `resources/views/admin/installer/recovery.blade.php` | Diagnostic dashboard: PHP version, mandatory extensions, directory write permissions, installer lock state. |
| **Unlock Installer** | `admin.installer.unlock` | `POST` | `/admin/installer/unlock` | `Admin\InstallerRecoveryController@unlock` | *(Redirect)* | Temporarily unlocks installer for maintenance without deleting database data. |
| **Relock Installer** | `admin.installer.relock` | `POST` | `/admin/installer/relock` | `Admin\InstallerRecoveryController@relock` | *(Redirect)* | Re-engages installer lockfile (`storage/app/installed.lock`). |

---

## 3. AUDIT & SUMMARY

The Super Admin interface comprises **58 discrete web routes** spanning 14 functional operational domains. Every route is verified with active feature tests ensuring:
1. Complete authentication enforcement (`AuthenticateAdmin` middleware).
2. Proper CSRF protection on state-modifying requests.
3. Strict parameter validation via Form Requests.
4. Consistent visual presentation using the Phase 16 Blade component library and responsive layout.
