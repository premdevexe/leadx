# LEADX — EMPLOYEE PANEL REST API: IMPLEMENTATION REPORT

**Date:** November 16, 2026
**Scope:** Complete Employee Panel REST API (`/api/v1/employee/*`) for the single LeadX Flutter Android application
**Base URL:** `https://yourdomain.com/api/v1`
**Status:** Implemented, tested (476 tests passing), **not deployed** (explicit approval required)

---

## 1. The 13 page-to-API mappings

| # | Page slug | API capability |
|---|---|---|
| 1 | `employee/dashboard` | `GET /employee/dashboard` — full widget set: summary cards, recent orders/follow-ups/expiring subs, recent notifications, 6-month sales chart; my_data/team_data views |
| 2 | `employee/orders/create` | `POST /employee/orders` (assigned to the caller, under the caller's own team) + `GET /products` picker (existing) |
| 3 | `employee/customers` | `GET /employee/customers`, `GET /employee/customers/{id}` — CRM customers reachable via the employee's order phones, per view |
| 4 | `employee/payments` | `GET /employee/payments`, `GET /employee/payments/{id}` — read-only (verify/reject stay admin-only) |
| 5 | `employee/subscriptions` | `GET /employee/subscriptions` (status filter), `GET /employee/subscriptions/{id}` — read-only (renew/cancel stay team/admin-only) |
| 6 | `employee/follow-ups` | `GET/POST /employee/follow-ups`, `GET/PUT/DELETE /employee/follow-ups/{id}`; PUT is body-driven `{action: complete\|reschedule\|cancel}`, DELETE = managed cancel (history retained) |
| 7 | `employee/subscriptions/expiring` | `GET /employee/subscriptions/expiring` — active subs expiring ≤ 30 days, same scoping as the web panel |
| 8 | `employee/notifications` | `GET /employee/notifications` (+ `meta.unread_count`), `POST /employee/notifications/{id}/read`, `POST /employee/notifications/read-all` |
| 9 | `employee/calendar` | `GET /employee/calendar` (connection status + personal events), `POST /employee/calendar/events`, `PUT/DELETE /employee/calendar/events/{id}`, `POST /employee/calendar/sync`, `GET /employee/calendar/connect` — **reuses the existing Google OAuth integration** (same `EmployeeGoogleConnection`, same token refresh; no duplicate calendar service) |
| 10 | `employee/profile` | `GET /employee/profile`, `PUT /employee/profile` (name/phone/email and/or password; a password change revokes all other tokens; role/team/preset/status are never updatable) + `GET /employee/me` (identity + permission booleans) |
| 11 | `employee/customer-issues` | `GET/POST /employee/customer-issues`, `GET/PUT /employee/customer-issues/{id}` — reuses `CrmCustomerIssueService` rules verbatim (authorized-customer set, ownership, history tracking) |
| 12 | `employee/login` | `POST /employee/login` (existing endpoint), `POST /employee/logout`, `GET /employee/me` |
| 13 | `employee/orders` | `GET /employee/orders`, `GET /employee/orders/{id}` + panel actions: `complete`, `closing-approval/resubmit`, `api-key/reveal`, `whatsapp-share`, `activate` |

Every one of the 13 pages has working API coverage (verified by `tests/Feature/Api/EmployeePanelApiTest.php`, 32 tests).

## 2. Implemented route list (42 employee-API routes; all under `auth:sanctum` + `ensure-role-active:employee` + `log-employee-api`)

- **Auth:** `POST employee/login` · `POST employee/logout` · `GET employee/me` · `GET employee/profile`
- **Dashboard:** `GET employee/dashboard`
- **Orders:** `GET/POST employee/orders` · `GET employee/orders/{id}` · `POST employee/orders/{id}/complete` · `POST employee/orders/{id}/closing-approval/resubmit` · `POST employee/orders/{id}/api-key/reveal` · `GET employee/orders/{id}/whatsapp-share` · `POST employee/orders/{id}/activate`
- **Customers:** `GET employee/customers` · `GET employee/customers/{id}`
- **Payments:** `GET employee/payments` · `GET employee/payments/{id}`
- **Subscriptions:** `GET employee/subscriptions` · `GET employee/subscriptions/expiring` · `GET employee/subscriptions/{id}`
- **Follow-ups:** `GET/POST employee/follow-ups` · `GET/PUT/DELETE employee/follow-ups/{id}`
- **Notifications:** `GET employee/notifications` · `POST employee/notifications/read-all` · `POST employee/notifications/{id}/read`
- **Calendar:** `GET employee/calendar` · `POST employee/calendar/events` · `PUT/DELETE employee/calendar/events/{id}` · `POST employee/calendar/sync` · `GET employee/calendar/connect`
- **Profile:** `PUT employee/profile`
- **Customer issues:** `GET/POST employee/customer-issues` · `GET/PUT employee/customer-issues/{id}`
- Permission gates (existing presets): `orders.view` on order list/show, `orders.create` on order create, `customers.view`, `payments.view`, `subscriptions.view` on list/show/expiring, `follow_ups.view`/`create`/`edit`.

## 3. Authentication details

- **Laravel Sanctum Bearer tokens** — one token per device session, issued by `POST /employee/login` through the existing `AuthenticationService` (IP rate-limited: 5 attempts / 15 min; inactive accounts and non-employee roles rejected; wrong-role tokens get 403 on every employee route).
- **Strict ownership:** the actor is always resolved from the token (`$request->user('sanctum')`). Client-sent employee/team ids are either ignored or rejected — an employee can never act as another employee (tested).
- **my_data / team_data views:** reads mirror the web panel. `view=team_data` is honored only inside an **active admin Team Data grant window**; without one the server silently downgrades to `my_data` and returns `meta.warning` (same UX as the web redirect).
- **Writes are owner-only:** order creation always assigns to the caller under the caller's team; complete/resubmit/activate are refused on non-owned orders; follow-up/customer-issue/calendar-event mutations are refused across employees.
- **Password change revokes every other token** (session-changing token kept), matching the web panel.

## 4. API Key configuration

The employee API uses **one** auth system (Sanctum) — the app is a single LeadX API application. The customer-facing delivery "API key" is a separate, order-scoped credential (`OrderApiCredential`, AES-encrypted at rest): the employee endpoint `POST /employee/orders/{id}/api-key/reveal` is the only plaintext read, gated by ownership + `orders.view_api_key`, and **never logged**. Storing keys remains admin-only. Google Calendar OAuth secrets stay in `ApiSetting`/env; no OAuth tokens are ever serialized in any response (enforced by resources + a dedicated test).

## 5. Request/response examples

```
POST /api/v1/employee/login   { "login": "jane", "password": "..." }
→ 200 { "token_type": "Bearer", "token": "1|abc…" }

GET /api/v1/employee/orders?page=1&per_page=20&view=my_data   (Authorization: Bearer 1|abc…)
→ 200 {
    "meta": { "view": "my_data", "warning": null, "current_page": 1, "last_page": 3, "per_page": 20, "total": 57 },
    "data": [ { "id": 41, "order_number": "GEM-2026-000041", "customer_name": "Grace",
                 "closing_value": 90, "order_status": "pending", "payment_status": "pending" } ]
  }
```

Full request/response examples for every page: `docs/LEADX_FLUTTER_INTEGRATION_GUIDE.md` (§10). OpenAPI 3.0 spec: `docs/LEADX_EMPLOYEE_API_OPENAPI.yaml`.

## 6. Database migration details

One new migration: `database/migrations/2026_11_26_000001_create_employee_calendar_events_table.php`
- `employee_calendar_events` (id, employee_id FK→users cascade, google_event_id nullable+indexed, summary, description, start_at, end_at, status[synced|error|deleted], last_message, last_synced_at, timestamps) — the local source of truth for personal calendar events so PUT/DELETE address a stable id, ownership is enforceable, and retried writes never duplicate remote events.

No other schema changes; all other endpoints reuse existing tables/services.

## 7. Test results

| Suite | Result |
|---|---|
| Baseline suite (pre-existing, run before implementation) | **427 passed (1,864 assertions)** |
| `tests/Feature/Api/EmployeePanelApiTest.php` (all 13 pages) | **32 passed** |
| `tests/Feature/Api/EmployeeApiSecurityTest.php` (cross-actor security) | **17 passed** |
| **Full suite after implementation** | **476 passed (2,098 assertions), 0 failures** |

Security tests verify: no impersonation via client-sent ids, team/admin tokens rejected on employee routes, cross-employee reads/mutations denied, grant-window enforcement (in/out of window, revocation), viewer-preset 403s on permission-gated routes, individual overrides honored, API-key reveal ownership, no secret leakage in any response, inactive-account lockout, and strict calendar-event ownership.

## 8. Known issues / notes

1. **Pre-existing bug fixed:** `FollowUpService::cancel()` called `recordHistory()` with 4 args (5 required) — any cancel of an existing follow-up threw a 500. Fixed; covered by the existing cancel test paths plus the new DELETE endpoint test.
2. **Shared middleware touched:** `EnsureEmployeePermission` now resolves the actor from a Sanctum token first (session guard fallback unchanged) — required for `permission:*` gates on API routes; web behavior is unchanged (427-test baseline green).
3. **Follow-up DELETE is a managed cancel**, not a hard delete — LeadX retains follow-up history everywhere (rule 8/9 of the follow-up module), so the API mirrors that.
4. **Calendar sync is bounded:** `POST /employee/calendar/sync` retries this employee's errored personal events (no remote id yet) and their pending subscription expiry events; it does not bulk-refetch the remote calendar (keeps token cost predictable). Personal event CRUD is stored locally and synced on write.
5. **`PUT /employee/follow-ups/{id}` is body-driven** (`{action: ...}`) to keep the URL set exactly as specified; the legacy top-level `/api/v1/follow-ups/{id}/complete|reschedule|cancel` endpoints are untouched and still work.
6. The employee order-scoping in the new API mirrors the **web panel** rules (owner, or active team grant view-only). The legacy top-level `GET /api/v1/orders/{id}` uses the stricter owner-only `assertCanAccess` — both are intentional; the employee panel API is the Flutter client's surface.
7. **Not deployed.** No production action taken (per instruction).

## 9. Flutter integration instructions

Full guide: **`docs/LEADX_FLUTTER_INTEGRATION_GUIDE.md`** (login/secure-storage, status-code matrix, per-page request examples, Dart client skeleton). Machine-readable spec: **`docs/LEADX_EMPLOYEE_API_OPENAPI.yaml`**. Quick start:

1. `POST /api/v1/employee/login` with username/email + password → store `token` in `flutter_secure_storage`.
2. Send `Authorization: Bearer <token>` on every request; on 401 → re-login; on 403 → show `message` (often a missing permission or missing team-data grant).
3. Bootstrap the menu from `GET /employee/me` (permission booleans + `has_team_data_access`).
4. Lists: `?view=my_data|team_data&page=N&per_page=M`; always surface `meta.warning` when present (means team_data downgraded).
5. Calendar: create events locally-then-remotely (`meta.synced` tells you Google confirmed); call `POST /employee/calendar/sync` to retry anything pending; `GET /employee/calendar/connect` gives the OAuth URL for the browser/webview flow.
6. Sensitive reads: the order API-key reveal is the only plaintext credential — show-once, never cache/log.
