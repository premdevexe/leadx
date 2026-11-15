# LEADX — PHASE 1: COMPREHENSIVE TECHNICAL AUDIT REPORT

**Project:** LeadX Enterprise Lead & Sales Management Platform  
**Audit Stage:** Phase 1 — Comprehensive Architecture, Database, Security & Codebase Audit  
**Target Platform:** Laravel 12.x / PHP 8.2+ / Tailwind CSS v4 / SQLite & MySQL  
**Audit Scope:** Audit-Only (Zero Code / Schema / Package / Data Modifications Applied)  
**Report Location:** `docs/LEADX_PHASE_1_AUDIT_REPORT.md`  
**Date of Audit:** November 15, 2026  

---

## 1. EXECUTIVE SUMMARY

### 1.1 Project Overview & Architectural Health
LeadX is a specialized enterprise lead conversion, sales order fulfillment, and subscription management system built on **Laravel 12.72.0** and **PHP 8.2.12**. The application manages multi-tier team sales operations, customer purchase histories, payment tracking, automated subscription lifecycles, customer API key fulfillment, and Google Calendar event synchronization.

The existing codebase exhibits a high level of architectural discipline across several domains:
1. **Domain Services**: Business logic is isolated into dedicated service classes (`OrderService`, `OrderNumberService`, `PaymentService`, `SubscriptionActivator`, `OrderCredentialService`, `WhatsappShareService`, `CrmCustomerService`, `GoogleOAuthService`).
2. **Immutable Data Snapshots**: The platform protects historical financial integrity by snapshotting product names, prices, and durations (`product_name_snapshot`, `product_price_snapshot`, `product_duration_snapshot`) on `sales_orders` and `subscriptions` at the moment of creation.
3. **Exact Decimal Accounting**: Monetary calculations use exact integer-cent conversions via `App\Support\Money` and `DECIMAL(15,2)` database columns, eliminating floating-point rounding errors.
4. **Credential Security**: Customer API credentials, Google OAuth tokens, and SMTP credentials use Laravel's AES-256 `encrypted` Eloquent casts at rest. Plaintext API keys are never echoed in listing endpoints, audit logs, or notifications.
5. **Existing Test Suite**: An extensive test suite exists with **222 passing tests and 884 assertions** across 30 test files.

---

### 1.2 Critical Audit Findings (Must-Know Before Phase 2)

| Severity | Issue Description | Location in Codebase | Architectural Impact |
|:---|:---|:---|:---|
| **CRITICAL** | **Order Sequence Number Burning on GET Requests** | `App\Http\Controllers\Employee\CreateOrderController::create` & `App\Http\Controllers\Team\CreateOrderController::create` | Visiting the "Create Order" page calls `$numbers->next($team)` inside the GET action to display a preview string. This executes `SELECT ... FOR UPDATE` and atomically increments `order_number_sequences.last_sequence`, burning and permanently wasting sequence numbers every time the page is loaded or refreshed. When the form is submitted, a second sequence number is consumed. |
| **CRITICAL** | **Cross-Employee IDOR & API Key Exposure in Team Context** | `App\Http\Controllers\Api\V1\OrdersController` & `App\Services\OrderCredentialService::canManage` | In the API layer, an employee belonging to a team can access **any** order created by any other employee in that same team (`assigned_employee_id == actor->id || team_id == actor->team_id`). Crucially, `OrderCredentialService::canManage()` allows any employee in the team to reveal the decrypted customer API key for any team order. This violates the future security rule requiring complete Employee private data isolation. |
| **HIGH** | **Legacy Team Login Architecture To Be Deprecated** | `routes/web.php`, `routes/api.php`, `App\Models\Team`, `config/auth.php` | The system currently maintains a 3-way authentication structure (Super Admin, Team, Employee). The target architecture strictly requires **only Super Admin Login and Individual Employee Login** (no separate Team Login, no Customer Login). The `team` web guard, session routes (`/team/login`), and API routes (`/api/v1/team/*`) must be safely retired without disrupting team organizational grouping. |
| **HIGH** | **Dynamic Permission Engine Missing** | Entire Application | No role-based or permission tables (`permissions`, `permission_presets`, `employee_permissions`, `permission_histories`) exist. Authorization is currently hardcoded via guard checks (`admin`, `employee.web`, `ensure-role-active`) and ad-hoc controller conditionals. |
| **HIGH** | **Product Management Lacks Required Business Fields** | `database/migrations/2026_11_15_000002_create_products_table.php`, `App\Models\Product` | The `products` table contains `name`, `price`, `duration`, and `status`. It is **missing** `min_closing_price` (Minimum Closing Price) and `api_key_required` (API Key Required boolean flag). |
| **HIGH** | **Closing Value Approval Workflow Missing** | `sales_orders` table, `App\Models\SalesOrder`, Controllers | Closing values are accepted immediately upon order creation without approval. There are no approval statuses (`requested`, `accept`, `reject`), no Super Admin override reasons, and no rejection resubmission mechanisms. |
| **HIGH** | **Refund Management System Missing** | `database/migrations/`, `App\Models\Payment`, `App\Models\SalesOrder` | `refunded` currently exists only as an enum string on `payments.payment_status` and `sales_orders.payment_status`. There is no `refunds` table, no partial refund logic, no refund reason tracking, and no order cancellation approval workflow. |
| **MEDIUM** | **Pusher Integration Architecture Mismatch** | `config/services.php`, `App\Services\BeamsService.php` | The existing Pusher implementation utilizes **Pusher Beams** (Web Push Notifications via HTTP REST API) for push notifications. It does **not** implement **Pusher Channels** (real-time WebSocket broadcasting). No `pusher/pusher-php-server` or `laravel-echo` packages are installed, and `config/broadcasting.php` does not exist. |
| **MEDIUM** | **Employee Dashboard Blade View Extends Wrong Layout** | `resources/views/employee/dashboard.blade.php` | The employee dashboard view declares `@extends('layouts.team')` instead of `@extends('layouts.employee')`. While other employee views correctly extend `layouts.employee`, this causes header and navigation inconsistencies on the employee landing page. |
| **LOW** | **7 Pre-Existing Test Failures in Baseline Suite** | `tests/Feature/NotificationResendTest.php`, `tests/Feature/WebPortal/TeamPortalTest.php`, `tests/Feature/WebPortal/EmployeePortalTest.php`, `tests/Feature/Api/OrderCreationTest.php`, `tests/Feature/Api/WhatsappShareTest.php` | Baseline execution reveals 7 failing tests out of 229 due to minor formatting mismatches, team notification null references, and test mock changes from previous phase development. |

---

## 2. PROJECT TECHNOLOGY STACK

### 2.1 Core Framework & Runtime
- **PHP Version:** `8.2.12 (cli)` (ZTS Visual C++ 2019 x64). Project `composer.json` specifies `"php": "^8.2"`.
- **Laravel Framework:** `12.72.0` (Skeleton `laravel/laravel: ^12.0`).
- **Composer Dependencies:**
  - `laravel/framework`: `^12.0`
  - `laravel/sanctum`: `^4.0` (API Token Authentication)
  - `laravel/tinker`: `^2.10.1`
  - Dev dependencies: `phpunit/phpunit: ^11.5.50`, `mockery/mockery: ^1.6`, `fakerphp/faker: ^1.23`, `laravel/pail: ^1.2.2`, `laravel/pint: ^1.24`, `laravel/sail: ^1.41`, `nunomaduro/collision: ^8.6`.
  - Packages **NOT Installed**: `pusher/pusher-php-server`, `spatie/laravel-permission`, `google/apiclient` (custom HTTP client used).
- **Frontend & Node.js Dependencies:**
  - `vite`: `^7.0.7`
  - `laravel-vite-plugin`: `^2.0.0`
  - `tailwindcss`: `^4.0.0`
  - `@tailwindcss/vite`: `^4.0.0`
  - `axios`: `^1.11.0`
  - `concurrently`: `^9.0.1`
  - Packages **NOT Installed**: `laravel-echo`, `pusher-js`, `alpinejs`, `vue`, `react`.
- **Frontend Architecture:** Server-rendered **Blade templates** styled with **Tailwind CSS v4**. JavaScript uses lightweight vanilla JS modules for responsive sidebar drawer toggling, mobile overlay backdrop management, and desktop collapse persistence via `localStorage`.

### 2.2 Database & Storage Drivers
- **Active Connection:** `sqlite` configured in `.env` and `.env.example` (`database/database.sqlite`).
- **Testing Connection:** In-memory SQLite (`:memory:`) configured in `phpunit.xml` (`<env name="DB_CONNECTION" value="sqlite"/>`, `<env name="DB_DATABASE" value=":memory:"/>`). Testing runs in memory and never touches the physical database.
- **Production Drivers:** `config/database.php` contains complete configuration for MySQL (`utf8mb4_unicode_ci`), PostgreSQL, and SQL Server.
- **Session Driver:** `database` (stored in `sessions` table) with 120-minute lifetime.
- **Cache Store:** `database` (stored in `cache` and `cache_locks` tables).
- **Queue Connection:** `database` (stored in `jobs`, `job_batches`, and `failed_jobs` tables).
- **Filesystem Disk:** `local` (`storage/app/private`).

### 2.3 Scheduler & Daemon Configuration
The application scheduler is defined in `routes/console.php` with 6 active tasks:
1. `ProcessSubscriptionsExpiry::class` — Daily at `00:05` (marks expired subscriptions).
2. `MarkFollowUpsOverdue::class` — Daily at `00:10` (flips past-due CRM follow-ups to overdue).
3. `SendSubscriptionReminders::class` — Daily at `08:00` (dispatches database notifications for subscriptions expiring soon).
4. `SendTeamExpiryReminders::class` — Daily at `config('leadx.notifications.expiry_reminder_time', '07:30')` (team-scoped notifications).
5. `SendEmailSubscriptionReminders::class` — Hourly (`withoutOverlapping`) with database idempotency via `email_reminder_tracking`.
6. `SubscriptionCalendarService::retryPending()` — Daily at `01:00` (`withoutOverlapping`) to retry failed Google Calendar expiry syncs.

---

## 3. EXISTING ARCHITECTURE

### 3.1 Architectural Pattern & Layering
LeadX is structured using an enterprise **Layered Controller-Service-Model** pattern:
- **Presentation Layer (Web):** Server-rendered Blade views organized by role: `resources/views/admin/`, `resources/views/team/`, `resources/views/employee/`.
- **Presentation Layer (API):** Versioned REST controllers under `app/Http/Controllers/Api/V1/` backed by Form Request validation (`app/Http/Requests/`) and Eloquent API Resources (`app/Http/Resources/`).
- **Domain Service Layer:** Core business rules are encapsulated in `app/Services/`, completely isolating domain logic from HTTP transports.
- **Persistence Layer:** Eloquent models in `app/Models/` with explicit mass-assignment guards, encrypted casts, and model lifecycle hooks.

### 3.2 Key Domain Services
- `OrderNumberService` (`app/Services/OrderNumberService.php`): Generates immutable, team-scoped order numbers of the format `TEAMCODE-YEAR-SEQUENCE` using transactional table locks.
- `OrderService` (`app/Services/OrderService.php`): Handles order creation, product resolution, price and duration snapshotting, and actor authorization checks.
- `PaymentService` (`app/Services/PaymentService.php`): Governs payment verification, rejection, and gateway reconciliation.
- `SubscriptionActivator` (`app/Services/SubscriptionActivator.php`): Idempotent subscription activation pipeline keyed by `order_id`.
- `BillingRules` (`app/Services/BillingRules.php`): Calculates exact expiry horizons for monthly, quarterly, and yearly billing cycles.
- `OrderCredentialService` (`app/Services/OrderCredentialService.php`): Manages customer API key delivery, encryption at rest, masked status views, and authorized plaintext reveals.
- `WhatsappShareService` (`app/Services/WhatsappShareService.php`): Generates prefilled click-to-chat links with automated secret scrubbing.
- `CrmCustomerService` (`app/Services/CrmCustomerService.php`): Implements normalized phone and phone+email customer matching.
- `GoogleOAuthService` & `GoogleCalendarService` (`app/Services/Google/`): Employee personal Google Calendar OAuth token exchange and event management.
- `BeamsService` (`app/Services/BeamsService.php`): Web push notification delivery via Pusher Beams REST API.
- `EmailService` (`app/Services/EmailService.php`): Database-backed dynamic SMTP mailer with template interpolation.
- `SalesAnalyticsService` (`app/Services/SalesAnalyticsService.php`): Integer-cent revenue analytics with automated cache invalidation.

---

## 4. AUTHENTICATION AUDIT

### 4.1 Authentication Guards & Providers
The authentication configuration in `config/auth.php` defines 4 session guards and 3 user providers:

```
Guards:
├── web      -> provider: users   -> model: App\Models\User
├── admin    -> provider: admins  -> model: App\Models\Admin
├── team     -> provider: teams   -> model: App\Models\Team
└── employee -> provider: users   -> model: App\Models\User

Providers:
├── users   -> Eloquent -> App\Models\User
├── admins  -> Eloquent -> App\Models\Admin
└── teams   -> Eloquent -> App\Models\Team
```

### 4.2 Super Admin Authentication Flow
- **Routes:**
  - `GET /admin/login` (`admin.login`) -> `AdminLoginController@showLoginForm`
  - `POST /admin/login` (`admin.login.attempt`) -> `AdminLoginController@login`
  - `POST /admin/logout` (`admin.logout`) -> `AdminLoginController@logout`
  - `POST /api/v1/admin/login` -> `Api\V1\Admin\AuthenticationController@login`
- **Security & Throttling:**
  - Login identifier supports username or email (`Admin::where('username', $login)->orWhere('email', $login)`).
  - Rate-limited to **5 attempts per 900 seconds** (15 minutes) keyed by IP + identifier via `RateLimiter`.
  - Session regenerated upon successful authentication (`$request->session()->regenerate()`).
  - Web authentication invokes `Auth::guard('admin')->login($admin, $request->boolean('remember'))`.
  - API authentication issues a Sanctum token named `leadx-admin`.
- **Middleware:** `App\Http\Middleware\AuthenticateAdmin` verifies `auth('admin')->check()`. Unauthenticated web requests redirect to `/admin/login`; API requests return `401 Unauthenticated`.
- **Status Column:** The `admins` table currently has **no status column**. All admin records in the database are permanently considered active.

### 4.3 Existing Employee Authentication Flow
- **Routes:**
  - `GET /employee/login` (`employee.login`) -> `Employee\LoginController@showLoginForm`
  - `POST /employee/login` (`employee.login.attempt`) -> `Employee\LoginController@login`
  - `POST /employee/logout` (`employee.logout`) -> `Employee\LoginController@logout`
  - `POST /api/v1/employee/login` -> `Api\V1\Employee\AuthenticationController@login`
- **Web Implementation Mechanism:**
  - Looks up `User` where `username` or `email` matches.
  - Verifies `$user->status === 'active'` and `$user->role === 'employee'`.
  - In `Employee\LoginController::login`, the controller stores the primary key directly in session: `$request->session()->put('employee_id', $user->id)`.
  - Middleware `App\Http\Middleware\AuthenticateEmployeeWeb` reads `$request->session()->get('employee_id')`, verifies the active user, and binds the employee via `$request->setUserResolver(fn () => $employee)`.
- **API Implementation:** Issued Sanctum token named `leadx-employee`. Guarded by `auth:sanctum` and `EnsureRoleActive` with parameter `'employee'`.

### 4.4 Existing Team Authentication (To Be Deprecated)
- **Routes:** `/team/login`, `/team/logout`, `/api/v1/team/login`, `/api/v1/team/logout`.
- Uses `Team` model (`username`, `password`, `team_code`, `status`).
- Uses `team.web` middleware (`AuthenticateTeamWeb`) which reads `session('team_id')`.
- Target requirement: **Zero separate Team Login**. Team accounts will cease to be authenticatable entities; `teams` will function purely as organizational attribution and order sequence groupings.

### 4.5 Strategy to Unify Employee Auth Without Breaking Super Admin
Super Admin and Employee authentication are already cleanly partitioned:
1. **Guards:** Super Admin uses `guard('admin')` pointing to the `admins` table. Employee auth operates against the `users` table.
2. **URLs:** Super Admin routes are strictly under `/admin/*`. Employee routes are strictly under `/employee/*`.
3. **Session State:** Super Admin session uses Laravel's standard guard session key (`login_admin_*`). Employee session uses either `employee_id` or `guard('employee')`.
4. **Conclusion:** Upgrading Employee Authentication and introducing Dynamic Permissions will have **zero side-effects** on the Super Admin login pipeline.

---

## 5. DATABASE INVENTORY & AUDIT

The LeadX database schema currently comprises **39 migration files** spanning batches 1 through 12. Below is the comprehensive technical inventory of all production tables.

### 5.1 Production Table Inventory

| Table Name | Primary Key | Foreign Keys / Linkages | Important Columns | Key Indexes | Status & Reuse Plan |
|:---|:---|:---|:---|:---|:---|
| `users` | `id` (bigint) | `team_id` -> `teams.id` (nullOnDelete) | `name`, `username`, `email`, `phone`, `password`, `role`, `status`, `team_id` | Unique(`email`), Unique(`username`), Index(`team_id`) | **Reusable.** Primary entity for Individual Employees. Needs permission linkages. |
| `admins` | `id` (bigint) | None | `name`, `username`, `email`, `password`, `remember_token` | Unique(`email`), Unique(`username`) | **Reusable.** Stores Super Admin credentials. Isolated from user tables. |
| `teams` | `id` (bigint) | None | `team_code`, `team_name`, `username`, `password`, `status` | Unique(`team_code`), Unique(`username`), Index(`status`) | **Reusable.** Organizational entity for order prefixes and performance grouping. `username`/`password` will become unused. |
| `team_members` | `id` (bigint) | `team_id` -> `teams.id` (cascade), `user_id` -> `users.id` (cascade) | `team_id`, `user_id`, `joined_at`, `left_at` | Index(`team_id`, `left_at`), Index(`user_id`) | **Reusable.** Historical ledger of employee team memberships. |
| `audit_logs` | `id` (bigint) | Polymorphic (`auditable_type`, `auditable_id`) | `event`, `old_values`, `new_values`, `ip` | Index(`auditable_type`, `auditable_id`) | **Reusable.** General audit ledger. |
| `products` | `id` (bigint) | None | `name`, `price`, `duration`, `status` | Unique(`name`), Index(`status`) | **Extend.** Missing `min_closing_price` and `api_key_required`. |
| `plans` | `id` (bigint) | `product_id` -> `products.id` (nullOnDelete) | `name`, `billing_cycle`, `duration_code`, `price`, `status` | Unique(`name`), Index(`billing_cycle`), Index(`status`) | **Reusable.** Subscription pricing plan catalog. |
| `sales_orders` | `id` (bigint) | `team_id`, `assigned_employee_id`, `product_id` (soft index) | `order_number`, `team_code_snapshot`, `customer_name`, `customer_phone`, `customer_email`, `product_name_snapshot`, `product_price_snapshot`, `product_duration_snapshot`, `product_value`, `discount_amount`, `closing_value`, `amount_received`, `payment_status`, `order_status` | Unique(`order_number`), Index(`team_id`, `order_status`), Index(`assigned_employee_id`), Index(`product_id`) | **Extend.** Missing closing value approval fields, cancellation reasons, and Super Admin edit audit reason. |
| `order_number_sequences` | `id` (bigint) | `team_id` -> `teams.id` (cascade) | `team_id`, `year`, `last_sequence` | Unique(`team_id`, `year`) | **Reusable.** High-water-mark ledger for atomic order number generation. |
| `subscriptions` | `id` (bigint) | `order_id` (unique), `team_id`, `customer_id`, `product_id`, `plan_id` | `plan_snapshot`, `price_snapshot`, `amount_paid`, `billing_cycle`, `status`, `started_at`, `expires_at`, `renewal_date`, `duration_snapshot`, `google_event_id`, `calendar_sync_status` | Unique(`order_id`), Index(`status`), Index(`expires_at`), Index(`renewal_date`), Index(`team_id`) | **Reusable.** Subscription lifecycle and calendar sync ledger. |
| `payments` | `id` (bigint) | `order_id`, `customer_id`, `verified_by_admin_id` | `payment_method`, `transaction_reference`, `amount`, `currency`, `payment_date`, `payment_status`, `source`, `verification_source`, `verified_at`, `admin_notes` | Index(`order_id`), Index(`customer_id`), Index(`payment_status`), Index(`transaction_reference`) | **Reusable.** Ledger of receipts. Refund status string exists here. |
| `order_api_credentials` | `id` (bigint) | `order_id` (unique) | `key` (encrypted), `updated_by_id`, `updated_by_type` | Unique(`order_id`) | **Reusable.** Secure encrypted at-rest storage for customer API keys. |
| `customers` | `id` (bigint) | `team_id`, `created_by_admin_id` | `name`, `email`, `phone`, `normalized_phone`, `normalized_email`, `status`, `purchases_count`, `total_purchased`, `last_purchase_at` | Unique(`team_id`, `normalized_phone`, `normalized_email`), Index(`status`) | **Reusable.** CRM customer registry. |
| `webhook_events` | `id` (bigint) | `order_id` | `gateway`, `event_id`, `event_type`, `status`, `payload`, `processed_at` | Unique(`gateway`, `event_id`), Index(`gateway`, `order_id`) | **Reusable.** Webhook idempotency ledger. |
| `whatsapp_share_logs` | `id` (bigint) | `order_id` | `actor_id`, `actor_type`, `normalized_phone`, `message`, `shared_api_key`, `status` | Index(`order_id`) | **Reusable.** Click-to-chat WhatsApp link preparation log. |
| `push_notification_logs` | `id` (bigint) | None | `title`, `body`, `interests`, `publish_id`, `status`, `error` | Standard | **Reusable.** Pusher Beams delivery log. |
| `notifications` | `id` (uuid) | Polymorphic (`notifiable_type`, `notifiable_id`) | `type`, `data`, `read_at` | Index(`notifiable_type`, `notifiable_id`) | **Reusable.** Native Laravel database notification table. |
| `settings` | `id` (bigint) | None | `key`, `value` | Unique(`key`) | **Reusable.** System-wide key-value settings. |
| `follow_ups` | `id` (bigint) | `customer_id`, `employee_id`, `team_id` | `scheduled_at`, `status`, `notes`, `google_event_id` | Index(`scheduled_at`), Index(`employee_id`), Index(`status`) | **Reusable.** CRM follow-up records. |
| `follow_up_histories` | `id` (bigint) | `follow_up_id`, `actor_id` | `action`, `notes`, `old_scheduled_at`, `new_scheduled_at` | Index(`follow_up_id`) | **Reusable.** Follow-up audit history. |
| `employee_google_connections` | `id` (bigint) | `employee_id` -> `users.id` (unique) | `google_email`, `access_token` (encrypted), `refresh_token` (encrypted), `token_expires_at`, `status` | Unique(`employee_id`) | **Reusable.** Google Calendar OAuth token storage. |
| `subscription_expiry_notifications` | `id` (bigint) | `subscription_id`, `user_id` | `target_expiry_date`, `channel`, `sent_at` | Unique(`subscription_id`, `user_id`, `target_expiry_date`, `channel`) | **Reusable.** Deduplication ledger for subscription expiry notices. |
| `email_settings` | `id` (bigint) | None | `key`, `value` (encrypted JSON) | Unique(`key`) | **Reusable.** Dynamic SMTP credentials. |
| `email_templates` | `id` (bigint) | None | `key`, `name`, `subject`, `body`, `is_active` | Unique(`key`), Index(`is_active`, `key`) | **Reusable.** Customizable email templates. |
| `email_notification_preferences` | `id` (bigint) | None | `event_key`, `label`, `category`, `template_key`, `is_enabled` | Unique(`event_key`) | **Reusable.** Event-level email toggle preferences. |
| `email_logs` | `id` (bigint) | `related_order_id`, `related_subscription_id`, `related_customer_id` | `recipient_email`, `event_key`, `template_key`, `subject`, `status`, `sent_at` | Index(`status`, `created_at`), Index(`related_order_id`, `event_key`) | **Reusable.** Email dispatch audit trail. |
| `email_reminder_tracking` | `id` (bigint) | `subscription_id` -> `subscriptions.id` (cascade) | `type`, `days_before`, `scheduled_at`, `sent_at`, `status` | Unique(`subscription_id`, `type`, `days_before`) | **Reusable.** Idempotent email reminder tracking. |

### 5.2 Missing Tables (Required for New Architecture)
1. `permissions`: System capabilities (module, slug, display name, description).
2. `permission_presets`: Admin-curated templates (e.g., "Junior Sales Rep", "Senior Account Exec", "Team Lead").
3. `permission_preset_items`: Mapping of permissions assigned to each preset.
4. `employee_permissions`: Active permissions assigned directly to an individual employee (with grant/revoke override state).
5. `employee_permission_histories`: 30-day immutable rolling audit ledger of every permission change.
6. `employee_team_access_grants`: Super Admin approved historical team access windows (`employee_id`, `team_id`, `start_date`, `end_date`, `granted_by_admin_id`, `revoked_at`).
7. `refunds`: Distinct financial refund ledger (`order_id`, `payment_id`, `amount`, `refund_type` [full/partial], `reason`, `status`, `processed_by_admin_id`).

---

## 6. PRODUCT MANAGEMENT AUDIT

### 6.1 Verification of Required Fields
We inspected `database/migrations/2026_11_15_000002_create_products_table.php`, `database/migrations/2026_11_15_000004_product_duration_instead_of_description.php`, and `App\Models\Product`:

- **Product Name:** **EXISTS** (`name` VARCHAR, Unique).
- **Product Price:** **EXISTS** (`price` DECIMAL(15,2), Default 0).
- **Duration:** **EXISTS** (`duration` VARCHAR(20), Default `'1y'`, validated against `SalesOrder::DURATIONS`: 3d, 7d, 15d, 30d, 3m, 6m, 1y).
- **Status:** **EXISTS** (`status` VARCHAR(20), Default `'active'`, Index, values: `active`, `inactive`).
- **Minimum Closing Price:** **NOT VERIFIED / MISSING.** Does not exist in migrations, model, or validation.
- **API Key Required (Yes/No):** **NOT VERIFIED / MISSING.** Does not exist in migrations, model, or validation.

### 6.2 Product CRUD & Order Linkage Implementation
- **Controller:** `App\Http\Controllers\Admin\ProductsController` implements complete CRUD: `index`, `create`, `store`, `edit`, `update`, `toggleStatus`, and `destroy`.
- **Soft Deactivation / History Preservation:** In `destroy()`, if existing orders reference the product (`SalesOrder::where('product_id', $product->id)->count() > 0`), the product is safely deactivated (`status = 'inactive'`) rather than hard deleted.
- **Snapshot Integrity:** When an order is created, `OrderService::createForTeam` reads the authoritative product from the database and copies its attributes into `product_name_snapshot`, `product_price_snapshot`, and `product_duration_snapshot`. Subsequent price changes or edits to the product catalog never modify past orders.

### 6.3 Recommended Minimum Changes
1. Create an additive migration:
   ```php
   Schema::table('products', function (Blueprint $table) {
       $table->decimal('min_closing_price', 15, 2)->nullable()->after('price');
       $table->boolean('api_key_required')->default(false)->after('duration');
   });
   ```
2. Update `App\Models\Product`: Add `min_closing_price` and `api_key_required` to `$fillable` and `$casts`.
3. Update `ProductsController@validated`: Add validation rules (`'min_closing_price' => ['nullable', 'numeric', 'min:0', 'lte:price']`, `'api_key_required' => ['boolean']`).
4. Update `resources/views/admin/products/_form.blade.php`: Add inputs for Minimum Closing Price and API Key Required toggle.

---

## 7. ORDER MANAGEMENT AUDIT

### 7.1 Order ID Generation & Verification
- **Required Format:** `TEAMCODE-YEAR-SEQUENCE` (e.g., `CLA-2026-000001`).
- **Actual Implementation:** **ALREADY FULLY IMPLEMENTED** in `App\Services\OrderNumberService`.
  ```php
  // App\Services\OrderNumberService::next
  return sprintf(
      '%s-%d-%s',
      $team->team_code,
      $year,
      str_pad((string) $sequence, self::SEQUENCE_WIDTH, '0', STR_PAD_LEFT)
  );
  ```
- **Concurrency & Race Conditions:**
  - Employs atomic `SELECT ... FOR UPDATE` inside `DB::transaction`.
  - Increments `order_number_sequences.last_sequence`.
  - Catches `UniqueConstraintViolationException` on race conditions when initializing a new `(team_id, year)` row and retries up to 5 times.
  - Numbers are immutable high-water marks and are never reused.

### 7.2 Critical Bug Discovered: Sequence Leakage
In `App\Http\Controllers\Employee\CreateOrderController::create` and `App\Http\Controllers\Team\CreateOrderController::create`:
```php
// Lines 22-25 in Employee\CreateOrderController.php
$numbers = app(OrderNumberService::class);
$previewNumber = $employee->team
    ? $numbers->next($employee->team)
    : '—';
```
Calling `$numbers->next($team)` in a `GET` request handler **permanently increments and burns** the sequence number in the database ledger simply to render a preview label on the form. If an employee opens the create page and navigates away without submitting, that sequence number is burned forever. If they submit the form, `store()` calls `$numbers->next($team)` a second time, leaving permanent gaps in the order sequence.

**Required Architectural Fix:** Add a read-only preview method `OrderNumberService::peekNext(Team $team)` that executes `SELECT last_sequence` without incrementing or locking the ledger.

### 7.3 Order Attributes & Lifecycle Logic
- **Attribution:** Automatically attributes `team_id`, `team_code_snapshot`, and `assigned_employee_id`.
- **Pricing & Discounts:** Calculates `discount_amount = max(0, product_value - closing_value)`.
- **Order Edit Restrictions:**
  - Admin can update orders in `Admin\OrdersController@update`. However, **no edit reason is required** and no audit log is created.
  - Employee has no edit route (satisfies requirement that Employee cannot edit after creation).
- **Missing Workflow:**
  - Closing Value Approval: No `closing_value_status` (`requested`, `accepted`, `rejected`).
  - Order Cancellation: Super Admin cannot record a mandatory cancellation reason.

---

## 8. PAYMENT AND SUBSCRIPTION AUDIT

### 8.1 Payment Verification Pipeline
- Payments are logged in `payments` (`source` = `manual` | `gateway`).
- Unverified payments cannot activate a subscription. Only an admin via `PaymentService::verify` (`verified_by_admin_id`) or a signed webhook (`verification_source = 'gateway'`) marks `isVerified() = true`.
- Partial payments are recorded as `payment_status = 'partially_paid'`. Activation policy (`config('leadx.payments.activate_on')`) defaults to `'paid'` (requiring 100% verification), with support for `'partial'` activation gated by `minimum_verified_percent`.

### 8.2 Subscription Activation & Snapshotting
- Handled idempotently by `App\Services\SubscriptionActivator::maybeActivate`.
- Keyed on `order_id` (unique index prevents duplicate subscriptions).
- Calculates exact expiry via `App\Services\BillingRules::calculateExpiry` (supports calendar clamping and exact day offsets).
- Freezes `plan_snapshot`, `price_snapshot`, and `duration_snapshot`.
- Triggers CRM customer upgrade (`enquiry` -> `paying`) via `CrmCustomerService`.
- Automatically schedules Google Calendar expiry sync.

### 8.3 Refund Architecture (Current vs Required)
- **Current State:** The database and models only recognize `'refunded'` as a string status on `payments` and `sales_orders`. There is no dedicated refund workflow.
- **Required State:**
  - Super Admin order cancellation must **not** trigger an automatic refund.
  - Full refund and partial refund records must be tracked in a dedicated `refunds` table with mandatory refund reasons and dynamic refund status tracking.

---

## 9. EXISTING FRONTEND AUDIT

### 9.1 Admin Panel UI Structure
- **Master Layout:** `resources/views/layouts/admin.blade.php`.
- **Design System:** Built using Tailwind CSS v4 utility classes with neutral grayscale palettes (`bg-neutral-50`, `border-neutral-200`, `text-neutral-900`, `bg-neutral-900`).
- **Sidebar:** Collapsible on desktop with width transition; transforms to an off-canvas overlay drawer on mobile (`< 768px`) with touch backdrop. Desktop state persists in `localStorage` under `leadx-sidebar-desktop`.
- **Header:** Sticky header with sidebar toggle button, breadcrumb, active username, and logout form.
- **Views Implemented:**
  - Dashboard (`admin.dashboard`)
  - Teams (`admin.teams.*`)
  - Orders (`admin.orders.*` — index, create, show, edit)
  - Products (`admin.products.*` — index, create, edit, _form)
  - Customers (`admin.customers.*` — index, show)
  - Employees (`admin.employees.*` — index, create, edit, _form)
  - Admins (`admin.admins.*` — index, create, edit, _form)
  - Notifications (`admin.notifications.index`)
  - Email Management (`admin.email.index`)
  - General Settings (`admin.settings.index`)

### 9.2 Employee Portal UI Structure
- **Master Layout:** `resources/views/layouts/employee.blade.php`.
- Shares the exact design tokens, sidebar toggle mechanics, responsive breakpoints, and styling as the Admin layout.
- **Views Implemented:**
  - `employee.dashboard` (Note: erroneously extends `layouts.team`)
  - `employee.orders.create`
  - `employee.orders.index`
  - `employee.orders.show`
  - `employee.customers.index`
  - `employee.payments.index`
  - `employee.subscriptions.index`
  - `employee.followups.index`
  - `employee.notifications.index`
- **Reusability Recommendation:** The Employee Portal does not need a visual redesign. The existing Blade layout (`layouts/employee.blade.php`) and table components can be reused directly. We only need to fix `employee/dashboard.blade.php` to extend `layouts.employee`, add the "My Data / Team Data" toggle bar, and condition action buttons based on dynamic permissions.

---

## 10. NOTIFICATION AND INTEGRATION AUDIT

### 10.1 Pusher Integration (Beams vs Channels)
- **Status:** **Partially Implemented (Pusher Beams Only).**
- **Pusher Package:** `pusher/pusher-php-server` is **NOT INSTALLED** in `composer.json`.
- **Broadcast Driver:** `BROADCAST_CONNECTION=log` in `.env.example`. No `config/broadcasting.php` or `routes/channels.php` exists.
- **Pusher Beams:** Implemented in `App\Services\BeamsService` using Laravel's HTTP client to call `https://{instanceId}.pushnotifications.pusher.com/publish_api/v1/instances/{instanceId}/publishes`. Web push logs are stored in `push_notification_logs`.
- **Evaluation against Future Requirements:** To support real-time Pusher notifications over authenticated private channels as requested in Section 11.I, `pusher/pusher-php-server` and `laravel-echo` will need to be installed in a future phase, or the existing Pusher Beams architecture must be explicitly utilized for web push alerts.

### 10.2 WhatsApp Integration
- **Status:** **Fully Implemented (Manual Click-to-Chat Architecture).**
- **Implementation:** `App\Services\WhatsappShareService`.
- **Mechanics:** Generates `https://wa.me/{phone}?text={message}` deep links.
- **Safety Controls:**
  - Only generates links for eligible orders (`order_status === 'active'` and `subscription->status === 'active'`).
  - Pre-fills order details using customer phone normalized to international format (E.164).
  - API keys are excluded by default and can only be folded in if explicitly authorized.
  - Logs link generation in `whatsapp_share_logs` with automated secret scrubbing (passwords, tokens, API keys are redacted from logs).
  - Matches the requirement: "Generate a prefilled message for manual review and sending; the backend never sends messages itself."

### 10.3 Google Calendar Integration
- **Status:** **Fully Implemented (Backend OAuth & Event Sync).**
- **Implementation:** `App\Services\Google\GoogleOAuthService`, `GoogleCalendarService`, `SubscriptionCalendarService`.
- **Mechanics:** Personal OAuth 2.0 connection per employee (`employee_google_connections`). Tokens are encrypted at rest. Synchronizes CRM follow-up meetings and creates subscription expiry alert events with 24-hour lead reminders. Daily scheduler retries pending events.

### 10.4 Email Integration
- **Status:** **Fully Implemented (DB-backed Dynamic SMTP & Templates).**
- **Implementation:** `App\Services\EmailService`, `email_settings`, `email_templates`, `email_notification_preferences`, `email_logs`, `email_reminder_tracking`.
- **Mechanics:** Full admin-manageable SMTP configuration, HTML email templates with double-brace placeholder interpolation, event-driven triggers, and hourly expiry reminder commands.

---

## 11. SECURITY AUDIT

### 11.1 Access Control & Middleware
- Super Admin routes use `AuthenticateAdmin` (`auth('admin')->check()`).
- Employee web routes use `AuthenticateEmployeeWeb` (`session('employee_id')`).
- API routes use Laravel Sanctum with `EnsureRoleActive` (`admin`, `team`, `employee`).
- **CSRF Protection:** Verified active across all web POST, PUT, PATCH, and DELETE routes via Laravel's default web middleware group.

### 11.2 IDOR & Employee Data Isolation Vulnerabilities
1. **API Order Listing Leakage (`App\Http\Controllers\Api\V1\OrdersController@index`):**
   ```php
   } elseif ($actor instanceof User) {
       // Employees see orders in their team (or they own).
       $query->where(function ($q) use ($actor): void {
           $q->where('assigned_employee_id', $actor->id)
               ->orWhere('team_id', $actor->team_id);
       });
   }
   ```
   An employee calling `GET /api/v1/orders` receives orders created by other employees in their team. The API does not enforce separation between "My Data" and "Team Data".
2. **API Key Reveal Permission Leak (`App\Services\OrderCredentialService@canManage`):**
   ```php
   if ($actor instanceof User) {
       $isOwner = (int) ($order->assigned_employee_id ?? 0) === (int) $actor->id;
       $isTeamMember = (int) ($actor->team_id ?? 0) === (int) ($order->team_id ?? 0);
       return $isOwner || $isTeamMember;
   }
   ```
   Any employee can reveal the plaintext customer API key for **any order** belonging to their team simply by knowing the order ID, even if they did not create or own the order. This directly violates the required data isolation rule.
3. **No View-Only Constraint on Team Data:**
   In `Api\V1\OrdersController@activate`, any employee can activate an order belonging to their team. Team Data is not restricted to read-only mode.

### 11.3 Injection, Mass Assignment & Encryption
- **SQL Injection:** No raw unescaped SQL queries found. Query parameters are bound via PDO. Wildcard characters (`%`, `_`) in search filters are properly escaped.
- **Mass Assignment:** All models (`User`, `Admin`, `Team`, `SalesOrder`, `Product`, `Payment`, `Subscription`, `OrderApiCredential`) define explicit `$fillable` arrays. Sensitive fields (`password`, `key`) are in `$hidden`.
- **Encryption at Rest:** Verified using Laravel `encrypted` casts on `order_api_credentials.key`, `employee_google_connections.access_token`, `employee_google_connections.refresh_token`, and `email_settings.value`.

---

## 12. FEATURE GAP ANALYSIS

| Feature Requirement | Existing Status | Existing Files / Tables | Required Changes | Risk Level |
|:---|:---|:---|:---|:---|
| **Dual Login Only (Super Admin + Employee)** | Partially Implemented | `routes/web.php`, `routes/api.php`, `config/auth.php`, `teams` | Deprecate and remove Team web login (`/team/login`) and Team API login (`/api/v1/team/login`). Keep `teams` table as organizational grouping only. | **Low** |
| **Individual Employee Accounts** | Already Implemented | `users` table, `App\Models\User`, `EmployeeService` | Preserve existing model. Link to dynamic permissions and current team. | **Low** |
| **Automatic Current Team Assignment** | Already Implemented | `users.team_id`, `TeamMember`, `EmployeeService::create` | Current team is automatically populated on employee creation and order creation. | **Low** |
| **Employee Dashboard (My Data vs Team Data Switch)** | Partially Implemented | `resources/views/employee/dashboard.blade.php`, `Employee\DashboardController` | Add "My Data" vs "Team Data" toggle. Fix `@extends('layouts.team')` bug to `@extends('layouts.employee')`. Implement team aggregated metrics. | **Medium** |
| **Team Data View-Only Enforcement** | Not Implemented | `App\Services\OrderService`, `Api\V1\OrdersController`, `Employee\OrdersController` | Block all write/update/activate actions on Team Data. Ensure employees can only view team orders in read-only mode. | **High** |
| **Previous Team Access with Date Range** | Not Implemented | None | Create `employee_team_access_grants` table. Require Super Admin authorization with explicit start/end dates. Enforce in query scopes. | **Medium** |
| **Dynamic Permission Engine (Global, Module, Preset, History)** | Not Implemented | None | Create `permissions`, `permission_presets`, `permission_preset_items`, `employee_permissions`, and `employee_permission_histories` tables. Build permission evaluator service and middleware. | **High** |
| **Product Minimum Closing Price** | Not Implemented | `database/migrations/*products*`, `Product.php`, `ProductsController` | Add `min_closing_price` to `products` table, model, validation, and forms. Enforce validation during order creation. | **Medium** |
| **Product API Key Required Flag** | Not Implemented | `database/migrations/*products*`, `Product.php`, `ProductsController` | Add `api_key_required` boolean to `products` table, model, and forms. Enforce order completion check. | **Medium** |
| **Order Number Generation (TEAMCODE-YEAR-SEQ)** | Already Implemented | `App\Services\OrderNumberService`, `order_number_sequences` | Already implements `CLA-2026-000001` format atomically. **Must fix sequence burn on GET preview.** | **High** |
| **Employee Order Creation** | Already Implemented | `Employee\CreateOrderController`, `OrderService::createForTeam` | Attributes employee ID and team ID automatically. Price snapshots already preserved. Add min closing price validation. | **Medium** |
| **Closing Value Approval Workflow** | Not Implemented | `sales_orders` table, `App\Models\SalesOrder`, `OrdersController` | Add approval columns (`closing_value_status`, `approved_by`, `reason`). Support Requested, Accept, Reject, and Resubmit flows. | **High** |
| **API Key Entry & At-Rest Encryption** | Already Implemented | `order_api_credentials`, `OrderApiCredential`, `OrderCredentialService` | Encrypted at rest via AES-256. Plaintext masked. Super Admin manual entry working. | **Low** |
| **Separate "Show API Key" & "Copy API Key" Permissions** | Not Implemented | `OrderCredentialService`, `OrderCredentialController` | Split permission into `orders.show_api_key` and `orders.copy_api_key`. Restrict employee access to own orders only. | **High** |
| **WhatsApp Prefilled Sharing** | Already Implemented | `WhatsappShareService`, `whatsapp_share_logs` | Generates prefilled `wa.me` links for manual review. Scrubs secrets in logs. Only shareable when order is active. | **Low** |
| **Order Cancellation by Super Admin with Reason** | Not Implemented | `sales_orders` table, `Admin\OrdersController` | Add `cancellation_reason`, `cancelled_by_admin_id`, `cancelled_at` to `sales_orders`. Create cancel modal and controller action. | **Medium** |
| **Refund Management (Full/Partial + Reason)** | Not Implemented | None | Create `refunds` table. Decouple cancellation from refund. Implement manual full and partial refunds with mandatory reason. | **High** |
| **Real-Time Pusher Notifications** | Partially Implemented | `BeamsService`, `push_notification_logs` | Web Push via Pusher Beams exists. Real-time WebSocket broadcasting over private channels requires installing `pusher-php-server` and `laravel-echo`. | **Medium** |

---

## 13. PROPOSED DATABASE CHANGES

All database modifications in subsequent phases must be **purely additive migrations**. No existing tables will be dropped or existing columns renamed.

### 13.1 Tables to Extend via Additive Migrations

#### 1. `products`
```php
Schema::table('products', function (Blueprint $table) {
    $table->decimal('min_closing_price', 15, 2)->nullable()->after('price');
    $table->boolean('api_key_required')->default(false)->after('duration');
});
```

#### 2. `sales_orders`
```php
Schema::table('sales_orders', function (Blueprint $table) {
    // Closing Value Approval Workflow
    $table->string('closing_value_status', 20)->default('accepted')->index()->after('closing_value'); // requested | accepted | rejected
    $table->unsignedBigInteger('closing_value_approved_by_id')->nullable()->after('closing_value_status');
    $table->text('closing_value_reason')->nullable()->after('closing_value_approved_by_id');
    $table->timestamp('closing_value_approved_at')->nullable()->after('closing_value_reason');

    // Super Admin Order Edit Audit Reason
    $table->text('admin_edit_reason')->nullable()->after('legacy_flag');

    // Order Cancellation Workflow
    $table->unsignedBigInteger('cancelled_by_admin_id')->nullable()->after('order_status');
    $table->text('cancellation_reason')->nullable()->after('cancelled_by_admin_id');
    $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
});
```

### 13.2 Genuinely New Tables to Create

#### 1. `permissions`
Defines system capabilities.
- `id` (bigint, pk)
- `module` (varchar 50, index) — e.g., `orders`, `customers`, `payments`, `api_keys`, `teams`
- `slug` (varchar 100, unique) — e.g., `orders.create`, `orders.view_team`, `api_keys.show`, `api_keys.copy`
- `name` (varchar 150)
- `description` (text, nullable)
- `timestamps`

#### 2. `permission_presets`
Curated templates managed by Super Admin.
- `id` (bigint, pk)
- `name` (varchar 100, unique) — e.g., `Standard Sales Rep`, `Senior Account Executive`
- `description` (text, nullable)
- `is_default` (boolean, default false)
- `timestamps`

#### 3. `permission_preset_items`
Pivot table between presets and permissions.
- `id` (bigint, pk)
- `preset_id` (unsignedBigInteger, fk -> `permission_presets.id` cascade)
- `permission_id` (unsignedBigInteger, fk -> `permissions.id` cascade)
- Unique: `['preset_id', 'permission_id']`

#### 4. `employee_permissions`
The active, effective permission state assigned to each employee.
- `id` (bigint, pk)
- `employee_id` (unsignedBigInteger, fk -> `users.id` cascade)
- `permission_id` (unsignedBigInteger, fk -> `permissions.id` cascade)
- `is_granted` (boolean, default true)
- `preset_id` (unsignedBigInteger, nullable, fk -> `permission_presets.id` nullOnDelete)
- `granted_by_admin_id` (unsignedBigInteger, nullable, fk -> `admins.id` nullOnDelete)
- Unique: `['employee_id', 'permission_id']`
- Index: `['employee_id', 'is_granted']`

#### 5. `employee_permission_histories`
Immutable 30-day rolling audit trail of every grant, revoke, or preset application.
- `id` (bigint, pk)
- `employee_id` (unsignedBigInteger, fk -> `users.id` cascade)
- `permission_id` (unsignedBigInteger, nullable)
- `permission_slug` (varchar 100)
- `action` (varchar 20) — `granted`, `revoked`, `preset_applied`
- `reason` (text, nullable)
- `actor_admin_id` (unsignedBigInteger, nullable)
- `created_at` (timestamp, index)

#### 6. `employee_team_access_grants`
Super Admin authorized date-range access to previous team records.
- `id` (bigint, pk)
- `employee_id` (unsignedBigInteger, fk -> `users.id` cascade)
- `team_id` (unsignedBigInteger, fk -> `teams.id` cascade)
- `start_date` (date)
- `end_date` (date)
- `granted_by_admin_id` (unsignedBigInteger)
- `revoked_at` (timestamp, nullable)
- `timestamps`
- Index: `['employee_id', 'team_id', 'revoked_at']`

#### 7. `refunds`
Financial refund ledger decoupled from cancellation.
- `id` (bigint, pk)
- `order_id` (unsignedBigInteger, fk -> `sales_orders.id` cascade)
- `payment_id` (unsignedBigInteger, nullable, fk -> `payments.id` nullOnDelete)
- `amount` (decimal 15,2)
- `currency` (varchar 10, default 'INR')
- `refund_type` (varchar 20) — `full`, `partial`
- `status` (varchar 20, default 'processed') — `pending`, `processed`, `failed`
- `reason` (text) — mandatory
- `processed_by_admin_id` (unsignedBigInteger)
- `timestamps`
- Index: `['order_id', 'status']`

---

## 14. FILE-LEVEL INTEGRATION PLAN

### 14.1 Existing Files to Reuse Without Breaking Changes
- `app/Support/Money.php` — Exact decimal accounting.
- `app/Support/TeamCode.php` — Team code validation rules.
- `app/Services/OrderNumberService.php` — Core sequence number engine (add read-only `peekNext` method).
- `app/Services/SubscriptionActivator.php` — Subscription activation pipeline.
- `app/Services/BillingRules.php` — Subscription duration calculation.
- `app/Services/PaymentService.php` — Manual & gateway payment verification.
- `app/Services/CrmCustomerService.php` — CRM customer matching policy.
- `app/Services/WhatsappShareService.php` — WhatsApp click-to-chat generator.
- `app/Services/Google/*` — Google Calendar OAuth and event sync.
- `app/Services/EmailService.php` — SMTP email notification system.
- `app/Models/OrderApiCredential.php` — Encrypted API key storage.
- `resources/views/layouts/admin.blade.php` — Admin layout.
- `resources/views/layouts/employee.blade.php` — Employee layout.

### 14.2 Files Requiring Modification in Future Phases
- `app/Http/Controllers/Employee/CreateOrderController.php` — Replace `$numbers->next($team)` in `create()` with `peekNext()`; validate `closing_value >= min_closing_price`.
- `app/Http/Controllers/Team/CreateOrderController.php` — Deprecate/retire or fix `peekNext()`.
- `app/Http/Controllers/Admin/OrdersController.php` — Add mandatory reason to `update()`; add cancel action with mandatory cancellation reason; add refund management.
- `app/Http/Controllers/Admin/ProductsController.php` — Add validation and handling for `min_closing_price` and `api_key_required`.
- `app/Models/Product.php` — Add fillable and cast attributes.
- `app/Models/SalesOrder.php` — Add closing value approval, cancellation, and refund relations.
- `app/Services/OrderCredentialService.php` — Restrict employee access to their own orders only; remove cross-employee team key reveal.
- `resources/views/employee/dashboard.blade.php` — Change `@extends('layouts.team')` to `@extends('layouts.employee')`; add My Data vs Team Data switch.
- `routes/web.php` & `routes/api.php` — Retire team login routes; add permission management and refund routes.

### 14.3 New Files to Create in Future Phases
- `app/Services/PermissionService.php` — Dynamic permission evaluator and cache layer.
- `app/Services/RefundService.php` — Refund processing and financial ledger management.
- `app/Http/Controllers/Admin/PermissionsController.php` — Admin permission and preset management.
- `app/Http/Controllers/Admin/RefundsController.php` — Order refund creation and status management.
- `app/Http/Middleware/CheckEmployeePermission.php` — Granular capability authorization middleware.
- `app/Models/Permission.php`, `PermissionPreset.php`, `EmployeePermission.php`, `EmployeePermissionHistory.php`, `EmployeeTeamAccessGrant.php`, `Refund.php`.
- Views for Permission Management and Refund Modals.

---

## 15. RISK ASSESSMENT & MITIGATION STRATEGY

| Risk Description | Severity | Probability | Impact | Mitigation Strategy |
|:---|:---:|:---:|:---|:---|
| **Sequence Number Leakage** | **CRITICAL** | High | Gaps in sequence numbers; legal/accounting audit violations. | Introduce `OrderNumberService::peekNext()` for form rendering. Never invoke `next()` on `GET` requests. |
| **Cross-Employee Data & API Key Leakage** | **CRITICAL** | High | Privacy violation; unauthorized credential exposure between reps. | Update `OrderCredentialService::canManage` and `assertCanAccess` to enforce strict employee ownership on sensitive operations. |
| **Permission Check Performance Overhead** | **HIGH** | Medium | Increased database query latency on every HTTP request. | Cache effective employee permissions in Redis/cache store using `employee_permissions:{id}` with instant invalidation upon update. |
| **Regression During Team Login Deprecation** | **MEDIUM** | Medium | Broken routes or bookmarks if team logins are suddenly dropped. | Implement a deprecation notice and redirect `/team/login` to `/employee/login`. Keep `teams` table intact. |
| **Financial Discrepancy from Incomplete Refunds** | **HIGH** | Low | Over-refunding or corrupted revenue analytics. | Validate `total_refunded <= total_verified_payments` in `RefundService`. Enforce database transactions for all refund inserts. |

---

## 16. DEPENDENCY-BASED IMPLEMENTATION ROADMAP

```
Phase 1: Comprehensive Audit & Risk Identification (COMPLETED)
    │
    ▼
Phase 2: Employee Authentication Cleanup & Team Login Deprecation
    │      - Retire team login routes (/team/login, /api/v1/team/login)
    │      - Standardize Employee session auth using employee guard
    │      - Fix sequence leakage in CreateOrderController (introduce peekNext)
    ▼
Phase 3: Dynamic Permission Engine
    │      - Migrations: permissions, permission_presets, employee_permissions, histories
    │      - Permission evaluator service, caching, and middleware
    │      - Admin UI for global permissions, presets, and individual overrides
    ▼
Phase 4: Team Data Access & Historical Date-Range Grants
    │      - Migration: employee_team_access_grants
    │      - Super Admin authorization workflow for previous team access
    │      - Strict Read-Only policy enforcement on all Team Data views
    ▼
Phase 5: Employee Dashboard & Sidebar Overhaul
    │      - Fix employee.dashboard extending layouts.team bug
    │      - Implement My Data / Team Data interactive switch
    │      - Dynamic sidebar navigation driven by active permissions
    ▼
Phase 6: Product Pricing & Minimum Closing Price Integration
    │      - Migration: add min_closing_price, api_key_required to products
    │      - Update Product CRUD, validation, and admin views
    ▼
Phase 7: Employee Order Creation Enhancement
    │      - Attribute Employee ID and Team ID automatically
    │      - Enforce closing_value >= min_closing_price validation
    │      - Preserve immutable product name, price, and duration snapshots
    ▼
Phase 8: Closing Value Approval Workflow
    │      - Add approval fields to sales_orders
    │      - Implement Requested, Accept, Reject states with reason tracking
    │      - Re-submission flow for rejected orders
    ▼
Phase 9: API Key Delivery & Granular Permissions
    │      - Validate API Key requirement before order completion
    │      - Implement separate "Show API Key" vs "Copy API Key" permissions
    │      - Mandatory reason logging for Super Admin order edits
    ▼
Phase 10: Real-Time Notifications & WhatsApp Sharing
    │      - Evaluate Pusher Channels vs Pusher Beams
    │      - Connect database notifications and authenticated private channels
    │      - Preserve existing click-to-chat WhatsApp link generation
    ▼
Phase 11: Cancellation & Refund Subsystem
    │      - Super Admin order cancellation with mandatory reason
    │      - Migration & Model: refunds table
    │      - Full and Partial refund workflows with reason tracking
    ▼
Phase 12: Comprehensive Security Testing & Release Verification
           - IDOR automated penetration tests
           - Full unit and integration test suite execution
           - Production release readiness audit
```

---

## 17. TESTING RECOMMENDATIONS

1. **Pre-Existing Test Fixes:** Resolve the 7 baseline test failures identified during the audit before creating new feature suites.
2. **Order Sequence Tests:** Add tests verifying that accessing `GET /employee/orders/create` or `GET /admin/orders/create` does **not** increment `order_number_sequences.last_sequence`.
3. **Data Isolation Tests:** Add explicit feature tests verifying that Employee A receives `403 Forbidden` when attempting to access Employee B's private orders or reveal Employee B's customer API keys.
4. **Closing Value Validation Tests:** Verify order creation fails with `422 Unprocessable Entity` when `closing_value < product.min_closing_price`.
5. **Permission Caching Tests:** Verify permission modifications take effect immediately without requiring user re-login.

---

## 18. UNVERIFIED ITEMS & QUESTIONS FOR USER

1. **Real-Time Notifications Architecture:** The project currently implements **Pusher Beams** (Web Push via HTTP Client) rather than **Pusher Channels** (WebSockets via `pusher-php-server` and `laravel-echo`). Does the final requirement expect real-time in-app WebSocket toast alerts (Pusher Channels), or should we continue expanding the existing Pusher Beams web push infrastructure?
2. **Preset Application Policy:** When a Super Admin updates an existing Permission Preset, should the system:
   - (A) Automatically sync and update all employees currently assigned to that preset, or
   - (B) Prompt the admin to choose whether to update existing employees or apply only to future assignments?
3. **Legacy Team Portal Transition:** Are there any existing live team credentials currently being used in production that require a scheduled notification period before `/team/login` is disabled?

---

*End of LeadX Phase 1 Technical Audit Report.*
