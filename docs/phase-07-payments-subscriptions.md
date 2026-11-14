# Phase 07 — Payment Management & Subscription Activation

Backend-only implementation of payment verification, subscription activation,
CRM customer integration, and sales analytics. All existing Admin / Team /
Employee authentication, the Order ID generation, and the Customer / Product /
Order models are preserved untouched.

## 1. What was built

### Migrations (all additive — no existing tables modified)

| Migration | Purpose |
|---|---|
| `2026_11_16_000001_create_plans_table` | Subscription plans (billing cycle, price, product link) |
| `2026_11_16_000002_create_customers_table` | CRM customers with purchase history + matching keys |
| `2026_11_16_000003_create_payments_table` | Payment receipts with verification fields |
| `2026_11_16_000004_create_webhook_events_table` | Gateway webhook idempotency ledger |
| `2026_11_16_000006_add_billing_columns_to_subscriptions_table` | `customer_id`, `product_id`, `plan_id`, `billing_cycle`, `plan_snapshot`, `price_snapshot`, `amount_paid`, `renewal_date`, reminder tracking |
| `2026_11_16_000007_add_customer_email_to_sales_orders_table` | Email capture for CRM matching |
| `2026_11_16_000009_create_notifications_table` | Framework notifications table for the `database` channel |

### Models
- **Payment** — methods (`upi`, `bank_transfer`, `gateway`, `other`),
  statuses (`pending`, `paid`, `partially_paid`, `failed`, `refunded`),
  `verification_source` (admin | gateway), `isVerified()` gate.
- **Customer** — Notifiable, `enquiry`/`paying` status, purchase history,
  normalized phone/email matching keys, composite unique index
  `(team_id, normalized_phone, normalized_email)` so repeat purchases
  never duplicate.
- **Plan**, **WebhookEvent**, extended **SalesOrder** (`payments()`,
  `verifiedPaymentTotal()`, `amountDue()`) and **Subscription** (billing
  fields, reminder scopes, `isActive()`).

### Services (the activation pipeline)
- **PaymentService** — verify / reject / record-gateway-payment. The only
  path to activation. Pending and failed rows never activate.
- **SubscriptionActivator** — idempotent, transaction-safe activation
  keyed on the unique `order_id`; freezes plan/price snapshots.
- **BillingRules** — expiry calculation: monthly / quarterly / yearly,
  day-accurate for short durations, month-clamped for calendar periods.
- **CrmCustomerService** — the matching policy: normalized phone, then
  phone+email pair. Upgrades `enquiry` → `paying` only on verified
  purchase.
- **GatewayService** + **AbstractGateway** / **MockGateway** — HMAC
  signature verification and two-layered idempotency (event ledger +
  keyed payment row).
- **SalesAnalyticsService** — all figures in exact DECIMAL(15,2) integer
  cents via **Money** helper; uncollected/pending/failed never count.
- **SubscriptionService** — read scoping, renew (requires *new* verified
  payment), cancel, and reminder dispatch.

### Notifications
`PaymentVerified`, `SubscriptionActivated`, `SubscriptionExpiring`,
`SubscriptionExpired`, `RenewalReminder` — all on the `database` channel
with `notifyNow()`.

### API endpoints (`/api/v1`)
- `GET|POST admin/payments`, `GET admin/payments/{id}`,
  `POST admin/payments/{id}/verify`, `POST admin/payments/{id}/reject`
  (behind `auth:sanctum` + `ensure-role-active:admin`).
- `GET subscriptions`, `GET subscriptions/{id}`,
  `POST subscriptions/{id}/renew`, `POST subscriptions/{id}/cancel`
  (behind `auth:sanctum`, scoped per actor in the controller).
- `POST gateway/webhook` (unauthenticated; signature-verified).

### Commands & schedule
- `subscriptions:process-expiry` — daily, flips lapsed subs to `expired`.
- `subscriptions:send-reminders` — daily, fires expiry + renewal
  reminders once per window.

## 2. Activation rules (verified behaviour)
- Pending payment ⇒ subscription stays `pending`. ✔ tested
- Verified sufficient payment ⇒ activates with snapshots + CRM upsert. ✔
- Failed payment ⇒ never activates. ✔
- Partial payment ⇒ refused under default `paid` policy; activates only
  when `activate_on=partial` and meets `minimum_verified_percent`. ✔
- Duplicate webhook ⇒ no duplicate subscription/payment. ✔
- Rejection ⇒ suspends a previously active subscription. ✔

## 3. Test results (actual, `php artisan test`)

```
Tests: 162 passed (703 assertions)
```

New Phase 07 suites (all passing):
- `PaymentVerificationTest` — 9 tests (36 assertions)
- `GatewayWebhookTest` — 5 tests (22 assertions)
- `SubscriptionManagementTest` — 5 tests (16 assertions)
- `CrmCustomerTest` — 3 tests (11 assertions)
- `SalesAnalyticsTest` — 4 tests (11 assertions)

No existing suite regressed.

## 4. Notes / follow-ups
- The `MockGateway` is a reference driver; a real gateway (Razorpay /
  Stripe / etc.) plugs in by extending `AbstractGateway` and setting
  `LEADX_GATEWAY_DRIVER`. No controller changes needed.
- `LEADX_PAYMENTS_ACTIVATE_ON` (default `paid`),
  `LEADX_PAYMENTS_MIN_VERIFIED_PERCENT` (default 100) and
  `LEADX_PAYMENTS_STALE_AFTER_DAYS` (default 90) drive policy.
- Customer notifications use the `database` channel; wire a real
  delivery channel (mail/WhatsApp) later without touching the pipeline.
