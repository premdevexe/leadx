# LeadX Employee Panel — Flutter Integration Guide

**API base URL:** `https://yourdomain.com/api/v1`
**Auth:** Laravel Sanctum Bearer tokens (one LeadX API application, one token per device session)

This guide covers integrating the 13-page Employee Panel into the LeadX Flutter Android app.
Full OpenAPI spec: `docs/LEADX_EMPLOYEE_API_OPENAPI.yaml`.

---

## 1. Authentication

### 1.1 Login (page: `employee/login`)

```
POST /api/v1/employee/login
Content-Type: application/json

{ "login": "jane.doe", "password": "S3cret!" }
```

Success (`200`):

```json
{ "token_type": "Bearer", "token": "1|abc123..." }
```

- `login` accepts the employee's **username or email**.
- The server rate-limits 5 attempts / 15 minutes per IP; inactive or non-employee accounts are rejected with `422`.
- **Store the token** in `flutter_secure_storage`. Never in shared preferences / plain files.

### 1.2 Every subsequent request

```
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

| Status | Meaning | Action |
|---|---|---|
| 200/201 | Success | — |
| 401 | Missing/invalid/revoked token | Send user back to login |
| 403 | Wrong role (admin/team token), disabled account, or missing permission | Show the `message` from the body |
| 404 | Record does not exist (or is outside the caller's scope) | — |
| 422 | Validation error or cross-ownership attempt | Show `errors.*` field messages |
| 503 | A required integration (e.g. Google Calendar) is not configured on the server | — |

### 1.3 Session bootstrap

```
GET /api/v1/employee/me
```

Returns the identity (`data`), `has_team_data_access`, and the permission booleans the app needs to gate its menu:

```json
{
  "data": { "id": 7, "name": "Jane", "team": { "id": 1, "team_name": "Gem" } },
  "has_team_data_access": false,
  "permissions": { "orders.create": true, "follow_ups.create": true, "follow_ups.edit": true }
}
```

### 1.4 Logout (page: `employee/login`)

```
POST /api/v1/employee/logout     →  { "message": "Logged out." }
```

Revokes the current token; the same token immediately stops working.

---

## 2. Page-to-API map (all 13 pages)

| # | Page slug | API capability |
|---|---|---|
| 1 | `employee/dashboard` | `GET /employee/dashboard` (widgets, recents, 6-month chart) |
| 2 | `employee/orders/create` | `POST /employee/orders` + product picker `GET /products` |
| 3 | `employee/customers` | `GET /employee/customers`, `GET /employee/customers/{id}` |
| 4 | `employee/payments` | `GET /employee/payments`, `GET /employee/payments/{id}` |
| 5 | `employee/subscriptions` | `GET /employee/subscriptions`, `GET /employee/subscriptions/{id}` |
| 6 | `employee/follow-ups` | `GET/POST /employee/follow-ups`, `GET/PUT/DELETE /employee/follow-ups/{id}` |
| 7 | `employee/subscriptions/expiring` | `GET /employee/subscriptions/expiring` |
| 8 | `employee/notifications` | `GET /employee/notifications`, `POST /employee/notifications/{id}/read`, `POST /employee/notifications/read-all` |
| 9 | `employee/calendar` | `GET /employee/calendar`, `POST /employee/calendar/events`, `PUT/DELETE /employee/calendar/events/{id}`, `POST /employee/calendar/sync`, `GET /employee/calendar/connect` |
| 10 | `employee/profile` | `GET /employee/profile` (+ `GET /employee/me`), `PUT /employee/profile` |
| 11 | `employee/customer-issues` | `GET/POST /employee/customer-issues`, `GET/PUT /employee/customer-issues/{id}` |
| 12 | `employee/login` | `POST /employee/login`, `POST /employee/logout` |
| 13 | `employee/orders` | `GET /employee/orders`, `GET /employee/orders/{id}` + actions: `complete`, `closing-approval/resubmit`, `api-key/reveal`, `whatsapp-share`, `activate` |

### View scoping (my_data / team_data)

List endpoints accept `?view=my_data` (default) or `?view=team_data`.
`team_data` is only honored when an admin has granted the employee an
**active Team Data access grant**; otherwise the server silently downgrades
to `my_data` and returns `meta.warning` — display it as a banner.

### Pagination

All list endpoints accept `?page=1&per_page=20` (max 100) and return
`meta: { current_page, last_page, per_page, total, view, warning }` plus
`data: [...]`.

---

## 3. Order creation (page 2 + 13)

```
POST /api/v1/employee/orders
{
  "customer_name": "Grace",
  "customer_phone": "+15551234567",          // or customer_phone_local + customer_country_code
  "product_id": 12,                            // active catalog product
  "closing_value": 90,
  "amount_received": 0,
  "payment_method": "upi"
}
```

- The order is **always assigned to the calling employee** under their
  assigned team; a `team_id` pointing at a different team is rejected (422).
- If `closing_value < product price`, the order lands in
  `closing_value_status = "Requested"` and needs Super Admin approval
  (surface this; resubmit later via
  `POST /employee/orders/{id}/closing-approval/resubmit`).
- The response is a full order resource: `201 { "data": { ...order... } }`.

Order detail (`GET /employee/orders/{id}`) also returns
`api_key_status` (masked only — the plaintext exists only via
`POST /employee/orders/{id}/api-key/reveal`, which the app must gate behind
its own "copy" interaction and must never log) and `whatsapp_share`
(prefilled click-to-chat URL — open it in the default browser; the server
never sends messages itself).

---

## 4. Follow-ups (page 6)

- `POST /employee/follow-ups` — `customer_id` must be a verified customer
  of the employee's own orders (same picker set as the web panel);
  `sync_to_calendar: true` creates the Google event when the employee has a
  connected calendar (result reported in `calendar_sync_status`).
- `PUT /employee/follow-ups/{id}` is body-driven:

```json
{ "action": "reschedule", "scheduled_at": "2026-12-01T09:00:00", "notes": "moved" }
{ "action": "complete", "outcome": "renewed" }
{ "action": "cancel", "notes": "customer unreachable" }
```

- `DELETE /employee/follow-ups/{id}` performs a **managed cancel** (LeadX
  never hard-deletes follow-ups; history is retained).

---

## 5. Calendar (page 9)

- `GET /employee/calendar` → connection status (tokens are never exposed)
  + the employee's local event list.
- `POST /employee/calendar/events` → `{ summary, start_at, end_at? }` →
  `201`; `meta.synced` tells you whether Google confirmed it. If the
  employee has no connected calendar the local event still exists and
  `meta.error = "not_connected"` — call `POST /employee/calendar/sync`
  later to retry (idempotent: no duplicate remote events).
- `GET /employee/calendar/connect` returns the OAuth `url`/`state`/
  `redirect_uri` — open the URL in a webview, then the existing
  `/api/v1/google/calendar/callback` completes the flow.

---

## 6. Notifications (page 8)

`GET /employee/notifications` returns `meta.unread_count` and a `data`
list of `{ id (UUID), type, data, read_at, created_at }`.
The app can poll unread_count on a timer and call
`POST /employee/notifications/{id}/read` when a notification is opened.

---

## 7. Profile (page 10)

`PUT /employee/profile` accepts contact fields and/or a password change:

```json
{ "name": "Jane D.", "phone": "+15550001" }

{
  "current_password": "S3cret!",
  "password": "NewPass123",
  "password_confirmation": "NewPass123"
}
```

A password change **revokes every other token** — other devices of the
same employee must log in again. Role, team, preset, status and
permissions are admin-managed and cannot be sent.

---

## 8. Customer issues (page 11)

`POST /employee/customer-issues` requires the customer to be one of the
employee's verified-order customers; `customer_id` outside that set is
rejected with `422`. Status values: `still_facing`, `fixed`.
Updates that change the status are history-tracked server-side.

---

## 9. Sample Dart client skeleton

```dart
final client = Dio(BaseOptions(
  baseUrl: 'https://yourdomain.com/api/v1',
  contentType: 'application/json',
));
client.interceptors.add(InterceptorsWrapper(
  onRequest: (options, handler) async {
    final token = await secureStorage.read(key: 'leadx_token');
    if (token != null) options.headers['Authorization'] = 'Bearer $token';
    handler.resolve(options);
  },
  onError: (e, handler) {
    if (e.response?.statusCode == 401) {
      // force re-login
    }
    handler.next(e);
  },
));

Future<Map<String, dynamic>> login(String user, String pass) async {
  final r = await client.post('/employee/login',
      data: {'login': user, 'password': pass});
  await secureStorage.write(key: 'leadx_token',
      value: r.data['token'] as String);
  return r.data;
}
```

## 10. Request/response examples

**Orders list (my_data):**
```
GET /api/v1/employee/orders?page=1&per_page=20
200
{
  "meta": { "view": "my_data", "warning": null,
            "current_page": 1, "last_page": 3, "per_page": 20, "total": 57 },
  "data": [ { "id": 41, "order_number": "GEM-2026-000041",
              "customer_name": "Grace", "closing_value": 90,
              "order_status": "pending", "payment_status": "pending" } ]
}
```

**Dashboard:**
```
GET /api/v1/employee/dashboard
200
{
  "meta": { "currency_symbol": "$", "view": "my_data", "warning": null },
  "summary": { "total_orders": 57, "closing_value": 5120.0,
               "verified_revenue": 3200.0, "customers_count": 40,
               "active_subscriptions": 12, "expiring_subscriptions": 3,
               "follow_ups": 8, "monthly_orders": 4 },
  "monthly_sales_chart": [ { "month": "Jun 2026", "orders": 9, "closing_value": 830.0 } ]
}
```

**Expiring subscriptions:**
```
GET /api/v1/employee/subscriptions/expiring
200
{ "meta": { "view": "my_data", "total": 3 },
  "data": [ { "id": 8, "order_id": 41, "status": "active",
              "expires_at": "2026-11-22T00:00:00+00:00" } ] }
```

---

## 11. Security notes for the app

- **Never** send employee ids from the client to act as another employee —
  every endpoint is scoped by the token's own user.
- The plaintext API-key reveal is the only sensitive read; do not cache or
  log it.
- Calendar connection resources never contain OAuth tokens; do not ask for
  them.
- 403 on a `team_data` request means "no active grant" — show
  `meta.warning`, don't treat it as an error state.
