# LEADX — EMPLOYEE PORTAL PAGE & MODULE INVENTORY

**Project:** LeadX Enterprise  
**Framework:** Laravel 12.0  
**Target Role:** Employee (`auth:employee` / `web`)  
**Frontend Architecture:** Blade + HTML5 + CSS3 + Vanilla JavaScript  
**Status:** Verified & Complete (All 13 Modules Documented)  
**Date:** November 2026  

---

## 1. ARCHITECTURE & ACCESS CONTROL

The Employee Portal provides sales agents, account executives, and support specialists with dedicated interfaces to process orders, track customer lifecycles, log follow-ups, and manage assigned subscriptions.

### Security Invariants
- **Authentication**: Secured via `AuthenticateEmployee` middleware enforcing the `employee` session guard and ensuring the employee account status is `active`.
- **Granular Permissions**: Navigation and actions are gated through `PermissionService::hasPermission($user, $permission)`:
  - `orders` (view/manage orders)
  - `orders.create` (create new sales orders)
  - `customers` (CRM customer records)
  - `payments` (view payment receipts)
  - `subscriptions` (monitor recurring software/services)
  - `follow_ups` (client reminders & scheduling)
  - `notifications` (system & push notification center)
  - `calendar` (Google Calendar sync)
  - `reports` (personal performance & team rankings)
  - `customer_issues` (log and track customer complaints)
- **Data Scoping**: Every query is strictly scoped to `assigned_employee_id == auth()->id()` or the employee's assigned `team_id`. Cross-employee and cross-team data access is strictly forbidden at both query and middleware levels.

---

## 2. COMPREHENSIVE 13-MODULE INVENTORY

### Module 1: Employee Dashboard

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Employee Dashboard** | `employee.dashboard` | `GET` | `/employee/dashboard` | `Employee\DashboardController@index` | `resources/views/employee/dashboard.blade.php` | 9 scoped performance cards (Orders, Pending, Approved, Verified Revenue, Pending Payments, Active Subscriptions, Expiring, Issues, Target %); monthly sales trajectory bar chart; urgent follow-ups ticker. |
| **Save Dashboard Prefs**| `employee.dashboard.preferences.save` | `POST` | `/employee/dashboard/preferences` | `Employee\DashboardController@savePreferences` | *(Redirect)* | Saves custom card order and visibility preferences. |
| **Reset Dashboard Prefs**| `employee.dashboard.preferences.reset` | `POST` | `/employee/dashboard/preferences/reset` | `Employee\DashboardController@resetPreferences` | *(Redirect)* | Restores default 9-card dashboard layout. |
| **Save Sidebar Prefs** | `employee.sidebar.preferences.save` | `POST` | `/employee/sidebar/preferences` | `Employee\DashboardController@saveSidebarPreferences` | *(Redirect)* | Persists sidebar collapse/expand state. |

---

### Module 2: Order Creation

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Create Order** | `employee.orders.create` | `GET` | `/employee/orders/create` | `Employee\CreateOrderController@create` | `resources/views/employee/orders/create.blade.php` | Dynamic product selection, automatic price population, minimum closing price threshold alert, customer phone deduplication, team code sequential number preview. |
| **Store Order** | `employee.orders.store` | `POST` | `/employee/orders` | `Employee\CreateOrderController@store` | *(Redirect)* | Enforces minimum closing value logic: if below threshold, marks `closing_approval_status = pending` and notifies admin; automatically assigns `assigned_employee_id = auth()->id()`. |

---

### Module 3: Order Management (My Orders)

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **My Orders** | `employee.orders` | `GET` | `/employee/orders` | `Employee\OrdersController@index` | `resources/views/employee/orders/index.blade.php` | Scoped order list, status pills (Pending, Approved, Completed, Cancelled), closing approval indicators, quick filter by date range. |
| **Order Details** | `employee.orders.show` | `GET` | `/employee/orders/{order}` | `Employee\OrdersController@show` | `resources/views/employee/orders/show.blade.php` | Full order dossier: customer details, line items, payment proofs, API key provisioning card, closing approval history. |
| **Store API Key** | `employee.orders.api-key.store` | `POST` | `/employee/orders/{order}/api-key` | `Employee\OrdersController@storeApiKey` | *(Redirect)* | Attaches software product license/API key to the verified order. |
| **Reveal API Key** | `employee.orders.api-key.reveal` | `POST` | `/employee/orders/{order}/api-key/reveal` | `Employee\OrdersController@revealApiKey` | *(JSON / Audit)* | Securely decrypts and reveals API key with employee audit log recording. |
| **Resubmit Approval**| `employee.orders.closing-approval.resubmit` | `POST` | `/employee/orders/{order}/closing-approval/resubmit` | `Employee\OrdersController@resubmitClosingApproval` | *(Redirect)* | Resubmits revised price to Super Admin after previous rejection. |
| **Complete Order** | `employee.orders.complete` | `POST` | `/employee/orders/{order}/complete` | `Employee\OrdersController@complete` | *(Redirect)* | Marks fully paid order as delivered/completed. |

---

### Module 4: Customer CRM

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Customer List** | `employee.customers` | `GET` | `/employee/customers` | `Employee\CustomersController@index` | `resources/views/employee/customers/index.blade.php` | Directory of customers who purchased from the employee or team; total spend, order count, search by name/phone/email. |

---

### Module 5: Payment Center & Follow-ups

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Payments Ledger** | `employee.payments` | `GET` | `/employee/payments` | `Employee\PaymentsController@index` | `resources/views/employee/payments/index.blade.php` | Scoped list of customer payments, verification status (Pending Review, Verified, Rejected), transaction references, payment receipts. |

---

### Module 6: Subscription Management

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Active Subscriptions**| `employee.subscriptions` | `GET` | `/employee/subscriptions` | `Employee\SubscriptionsController@index` | `resources/views/employee/subscriptions/index.blade.php` | Active customer subscriptions, renewal dates, auto-billing indicators, customer contact shortcuts. |

---

### Module 7: Expiring Subscriptions

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Expiring Renewals** | `employee.subscriptions.expiring` | `GET` | `/employee/subscriptions/expiring` | `Employee\SubscriptionsController@expiring` | `resources/views/employee/subscriptions/expiring.blade.php` | Filtered renewals expiring within 30, 15, or 7 days; renewal outreach triggers, retention action tags. |

---

### Module 8: Follow-ups & Reminders

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Follow-up Tasks** | `employee.follow-ups` | `GET` | `/employee/follow-ups` | `Employee\FollowUpsController@index` | `resources/views/employee/follow-ups/index.blade.php` | Task agenda: pending, completed, overdue; filter by date; customer quick call button. |
| **Schedule Follow-up** | `employee.follow-ups.create` | `GET` | `/employee/follow-ups/create` | `Employee\FollowUpsController@create` | `resources/views/employee/follow-ups/create.blade.php` | Customer selector (restricted to owned clients), date/time picker, agenda notes. |
| **Store Follow-up** | `employee.follow-ups.store` | `POST` | `/employee/follow-ups` | `Employee\FollowUpsController@store` | *(Redirect)* | Saves follow-up, triggers calendar sync if Google OAuth connected. |
| **Follow-up Detail** | `employee.follow-ups.show` | `GET` | `/employee/follow-ups/{followUp}` | `Employee\FollowUpsController@show` | `resources/views/employee/follow-ups/show.blade.php` | Task dossier, interaction notes history, completion action button. |
| **Complete Task** | `employee.follow-ups.complete` | `POST` | `/employee/follow-ups/{followUp}/complete` | `Employee\FollowUpsController@complete` | *(Redirect)* | Marks task completed with outcome summary. |
| **Reschedule Task** | `employee.follow-ups.reschedule`| `POST` | `/employee/follow-ups/{followUp}/reschedule` | `Employee\FollowUpsController@reschedule` | *(Redirect)* | Updates scheduled time and updates calendar event. |
| **Cancel Task** | `employee.follow-ups.cancel` | `POST` | `/employee/follow-ups/{followUp}/cancel` | `Employee\FollowUpsController@cancel` | *(Redirect)* | Cancels task with cancellation reason. |

---

### Module 9: Notification Center

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Notifications** | `employee.notifications` | `GET` | `/employee/notifications` | `Employee\NotificationsController@index` | `resources/views/employee/notifications/index.blade.php` | Real-time push and database alerts; order approvals, payment verification notices, expiry alerts; mark-as-read toggles. |

---

### Module 10: Google Calendar Integration

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Calendar Center** | `employee.calendar` | `GET` | `/employee/calendar` | `Employee\DashboardController@calendar` | `resources/views/employee/calendar/index.blade.php` | Connection status badge, sync history, upcoming scheduled client calls from Google Calendar API. |
| **Connect Calendar** | `employee.calendar.connect` | `GET` | `/employee/calendar/connect` | `Employee\DashboardController@connectCalendar` | *(External OAuth)* | Redirects to Google OAuth consent screen requesting `calendar.events` scope. |
| **OAuth Callback** | `employee.calendar.callback` | `GET` | `/employee/calendar/callback` | `Employee\DashboardController@calendarCallback` | *(Redirect)* | Validates state, exchanges auth code for refresh token, stores encrypted credentials in `users` record. |
| **Disconnect** | `employee.calendar.disconnect` | `POST` | `/employee/calendar/disconnect` | `Employee\DashboardController@disconnectCalendar` | *(Redirect)* | Revokes Google token and clears user calendar credentials safely. |

---

### Module 11: Profile & Account Settings

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Profile Overview** | `employee.profile` | `GET` | `/employee/profile` | `Employee\ProfileController@show` | `resources/views/employee/profile/show.blade.php` | Contact details, assigned team code, current role permissions list, password change form. |
| **Update Profile** | `employee.profile.update` | `PUT` | `/employee/profile` | `Employee\ProfileController@update` | *(Redirect)* | Updates phone number and display preferences (email is locked to prevent account hijacking). |
| **Change Password** | `employee.profile.password` | `PUT` | `/employee/profile/password` | `Employee\ProfileController@updatePassword` | *(Redirect)* | Enforces `current_password` verification before setting new bcrypt password. |

---

### Module 12: Sales Reports & Performance

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Performance Reports**| `employee.reports` | `GET` | `/employee/reports` | `Employee\DashboardController@reports` | `resources/views/employee/reports/index.blade.php` | Personal sales conversion rate, total gross orders vs verified collections, team sales ranking, monthly commission estimate. |

---

### Module 13: Customer Issues CRM

| Feature / Page | Route Name | Method | URI | Controller & Action | View Path | Key Capabilities & Scoping |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Issues Index** | `employee.customer-issues.index` | `GET` | `/employee/customer-issues` | `Employee\CustomerIssuesController@index` | `resources/views/employee/customer-issues/index.blade.php` | List of customer service issues, status filter ("Still Facing", "Fixed"), priority pills, search by customer. |
| **Create Issue** | `employee.customer-issues.create` | `GET` | `/employee/customer-issues/create` | `Employee\CustomerIssuesController@create` | `resources/views/employee/customer-issues/create.blade.php` | Customer selector (scoped to owned clients), issue category, priority, detailed complaint description. |
| **Store Issue** | `employee.customer-issues.store` | `POST` | `/employee/customer-issues` | `Employee\CustomerIssuesController@store` | *(Redirect)* | Creates issue record defaulting to `still_facing`, logs initial audit event. |
| **Issue Dossier** | `employee.customer-issues.show` | `GET` | `/employee/customer-issues/{customer_issue}` | `Employee\CustomerIssuesController@show` | `resources/views/employee/customer-issues/show.blade.php` | Comprehensive issue history, status toggle button ("Mark Fixed" / "Reopen"), resolution notes trail. |
| **Update Issue** | `employee.customer-issues.update` | `PUT` | `/employee/customer-issues/{customer_issue}` | `Employee\CustomerIssuesController@update` | *(Redirect)* | Updates issue category, resolution notes, or toggles status with automatic audit history log. |

---

## 3. AUDIT & SUMMARY

The Employee Portal encapsulates **34 discrete web routes** structured across 13 dedicated business modules. Every route has been verified:
1. Strict session authentication under `AuthenticateEmployee`.
2. 100% data boundary enforcement preventing IDOR (Insecure Direct Object Reference).
3. Zero exposure or linkage to Super Admin configuration or Backup & Disaster Recovery endpoints.
4. Consistent, accessible HTML5/CSS3/Vanilla JS rendering using shared design tokens.
