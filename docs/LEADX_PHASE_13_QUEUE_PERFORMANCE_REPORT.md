# LEADX — PHASE 13: ASYNCHRONOUS QUEUE & PERFORMANCE OPTIMIZATION REPORT

**Application:** LeadX (Laravel 12 / PHP 8.2+ / MySQL)  
**Role:** Senior Laravel Performance Engineer  
**Date:** November 16, 2026  
**Status:** Completed & Verified  

---

## Executive Summary

In Phase 13, critical business workflows—specifically order creation, closing value approval, payment verification, subscription activation, order cancellation, and refund processing—were decoupled from external Pusher Beams HTTP network latency.

Previously, synchronous HTTP calls to Pusher Beams (`https://<instance_id>.pushnotifications.pusher.com`) during order creation blocked the PHP-FPM process for an average of **2,956.05 ms (~3.0 seconds)** per order. By implementing the asynchronous `App\Jobs\SendWebPushNotificationJob`, configuring transactional `afterCommit` guarantees, deduplicating transient alerts, optimizing database composite indexing, and mocking external network calls in automated testing, the following performance milestones were achieved:

| Metric | Before Optimization | After Optimization | Improvement |
| :--- | :--- | :--- | :--- |
| **Average Order Creation Time** | **2,956.05 ms** | **25.62 ms** | **99.13% reduction (115.4x speedup)** |
| **PHPUnit Test Suite Duration** | **> 355.00 s** | **14.59 s** | **95.89% reduction (24.3x speedup)** |
| **Test Suite Pass Rate** | Intermittent timeouts | **100% (390 passed, 0 failed)** | 1,598 assertions verified |
| **Transaction Integrity** | Synced calls fired even on rollback | **Guaranteed `afterCommit`** | Rolled-back orders never dispatch |
| **Credential Safety** | Raw URLs in stack traces | **Secret Masking (`***REDACTED***`)** | Pusher keys sanitized in all logs |

---

## 1. Audit of Existing Implementation

### 1.1 Critical Workflows Identified
Prior to Phase 13, `App\Services\NotificationHubService` directly invoked `App\Services\BeamsService::publishWebNotification()` synchronously within:
1. **Order Creation (`notifyOrderCreated`):** Fired 2 synchronous HTTP calls (one to `employee-{id}` and one to `admin`).
2. **Closing Value Approval (`notifyClosingApprovalRequested` & `notifyClosingApprovalDecision`):** Synchronous HTTP calls on closing request submission and admin decision.
3. **Payment Verification (`notifyPaymentVerified`):** Synchronous HTTP call on transaction verification.
4. **Subscription Activation (`notifySubscriptionActivated`):** Synchronous HTTP call when access keys are provisioned.
5. **Order Cancellation & Refunds (`notifyOrderCancelled` & `notifyRefundProcessed`):** Synchronous HTTP calls blocking customer service workflows.

### 1.2 Root Cause of Latency & Test Slowness
- `BeamsService::publishWebNotification` initiates a Guzzle HTTP POST request to Pusher's cloud gateway.
- In production, network latency, TLS handshakes, and Pusher API processing introduced 1.2s to 3.5s of blocking latency on the checkout thread.
- If Pusher experienced gateway hiccups or rate limits, the customer checkout stalled or timed out with a 504 Gateway error.
- During PHPUnit test runs, every test creating an order executed live HTTP requests to Pusher, ballooning total suite execution time to over 5 minutes.

---

## 2. Jobs Created: `SendWebPushNotificationJob`

### 2.1 Implementation Details
**File:** [app/Jobs/SendWebPushNotificationJob.php](file:///d:/Projects/Leadx/LeadX/app/Jobs/SendWebPushNotificationJob.php)

The job is engineered to fulfill enterprise reliability and security standards:

```php
namespace App\Jobs;

use App\Services\BeamsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
...

class SendWebPushNotificationJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 15;

    public function backoff(): array
    {
        return [5, 15, 30];
    }
```

### 2.2 Key Features
1. **Asynchronous Execution:** Implements `ShouldQueue` on the dedicated `notifications` queue.
2. **Transactional Safety (`ShouldQueueAfterCommit` & `$afterCommit = true`):** The job is held in memory until the database transaction successfully commits. If an order creation or payment verification fails and rolls back, the push notification is discarded automatically.
3. **Intelligent Deduplication:**
   - Evaluates a 10-minute cache window (`push_dedup_<key>`).
   - Prevents duplicate push notifications if a network retry or rapid double-submit occurs.
4. **Differentiated Error Classification:**
   - **Transient Server Errors (5xx / Connection Timeouts):** Throws a `RuntimeException`, triggering Laravel's exponential retry backoff (`[5s, 15s, 30s]`).
   - **Permanent Client Errors (4xx, e.g., invalid interest syntax):** Logged safely as warnings without re-throwing, preventing poisoned queues and endless worker churn.
5. **Secret Sanitization in Logs:**
   - The `sanitizeError()` helper and `failed()` hook redact `services.pusher.server_key` and `services.pusher.instance_id` to prevent credential leakage in error logs or Bugsnag/Sentry trackers.

---

## 3. NotificationHubService Refactoring

**File:** [app/Services/NotificationHubService.php](file:///d:/Projects/Leadx/LeadX/app/Services/NotificationHubService.php)

The internal method `sendBeamsSafely()` was refactored from a synchronous call into an asynchronous queue dispatch:

```php
protected function sendBeamsSafely(
    string $title,
    string $body,
    array $interests,
    ?string $deduplicationKey = null
): void {
    if (empty($interests)) {
        return;
    }

    try {
        SendWebPushNotificationJob::dispatch(
            title: $title,
            body: $body,
            interests: $interests,
            deduplicationKey: $deduplicationKey,
        );
    } catch (\Throwable $e) {
        Log::error('Failed to dispatch SendWebPushNotificationJob to queue', [
            'title' => $title,
            'interests' => $interests,
            'error' => $e->getMessage(),
        ]);
    }
}
```

### Preserved Functionality
- **In-App Notifications:** Continue to persist synchronously in the `notifications` database table. The UI badge updates immediately.
- **Email Notifications:** Retained via existing notification channels.
- **Pusher Beams:** Pusher Beams protocol and format are fully preserved. Pusher was **not** replaced with Pusher Channels or Reverb.

---

## 4. Database Optimization

### 4.1 Query Pattern Audit
An audit of queries executed by the employee dashboard, employee orders page, and team summaries revealed frequent queries filtering by employee and sorting by date:
```sql
SELECT * FROM `sales_orders` 
WHERE `assigned_employee_id` = ? 
ORDER BY `created_at` DESC 
LIMIT 25;
```
Before this phase, `assigned_employee_id` only had a single-column foreign key index. Queries with `ORDER BY created_at DESC` required a `Using filesort` step in MySQL.

### 4.2 Migration Added
**Migration:** [database/migrations/2026_11_24_000003_add_assigned_employee_created_at_index_to_sales_orders_table.php](file:///d:/Projects/Leadx/LeadX/database/migrations/2026_11_24_000003_add_assigned_employee_created_at_index_to_sales_orders_table.php)

```php
Schema::table('sales_orders', function (Blueprint $table) {
    $table->index(['assigned_employee_id', 'created_at'], 'sales_orders_employee_created_idx');
});
```

- **Down Migration:** Safely drops `'sales_orders_employee_created_idx'` on rollback.
- **Duplicate Prevention:** Verified no overlapping composite index existed.

---

## 5. Test Environment Optimization

**File:** [tests/TestCase.php](file:///d:/Projects/Leadx/LeadX/tests/TestCase.php)

### 5.1 The Issue with Test Isolation
In Laravel, `Http::fake()` appends stubs. If a base test setup registers a naive wildcard stub at index 0, tests that supply custom `Http::fake()` mappings (e.g., verifying 504 retry errors or OAuth token payloads) are shadowed.

### 5.2 The Solution
Implemented a custom `HttpFactory` inside `tests/TestCase.php` that intercepts `*.pushnotifications.pusher.com/*` as a fallback at the **end** of the evaluation chain:
```php
$factory = new class($dispatcher) extends HttpFactory {
    public function createPendingRequest()
    {
        return tap($this->newPendingRequest(), function ($request) {
            $fallback = function ($req) {
                if (str_contains($req->url(), 'pushnotifications.pusher.com')) {
                    return HttpFactory::response(['publishId' => 'pub-test-fake-id'], 200);
                }
                return null;
            };

            $stubs = ($this->stubCallbacks ?? new Collection)->concat([$fallback]);

            $request
                ->stub($stubs)
                ->preventStrayRequests($this->preventStrayRequests)
                ->allowStrayRequests($this->allowedStrayRequestUrls);
        });
    }
};
```
### 5.3 Outcome
- Normal feature tests run offline with zero internet dependencies.
- Tests that explicitly mock Pusher Beams or Google OAuth (such as `NotificationResendTest` and `GoogleCalendarOauthTest`) maintain full priority and order of their custom stubs.
- Test suite runtime decreased from **355s to 14.59s**.

---

## 6. Performance Verification & Measurements

### 6.1 Order Creation Response Time
Measurements were conducted on the local MySQL environment using [benchmark.php](file:///C:/Users/Dell/.gemini/antigravity/brain/149cbe77-961b-42e7-9131-29c515b885fd/scratch/benchmark.php) and [benchmark_after.php](file:///C:/Users/Dell/.gemini/antigravity/brain/149cbe77-961b-42e7-9131-29c515b885fd/scratch/benchmark_after.php).

#### Synchronous Pusher (BEFORE):
```
Run 1: 3,046.27 ms (Order: BMK-2026-000001)
Run 2: 2,891.47 ms (Order: BMK-2026-000002)
Run 3: 2,930.42 ms (Order: BMK-2026-000003)
Average Order Creation Time (BEFORE): 2,956.05 ms
```

#### Asynchronous Queue Processing (AFTER):
```
Run 1: 65.12 ms (Order: BMK-2026-000009)
Run 2: 17.19 ms (Order: BMK-2026-000010)
Run 3: 16.56 ms (Order: BMK-2026-000011)
Run 4: 14.85 ms (Order: BMK-2026-000012)
Run 5: 14.39 ms (Order: BMK-2026-000013)
Average Order Creation Time (AFTER): 25.62 ms
```

### 6.2 Queue Worker Execution
Queue processing was verified using `php artisan queue:work database --queue=notifications,default`:
```
2026-11-16 12:11:42 App\Jobs\SendWebPushNotificationJob .................... RUNNING
2026-11-16 12:11:44 App\Jobs\SendWebPushNotificationJob ................ 1s DONE
2026-11-16 12:11:44 App\Jobs\SendWebPushNotificationJob .................... RUNNING
2026-11-16 12:11:46 App\Jobs\SendWebPushNotificationJob ................ 2s DONE
```
All jobs completed without blocking the web application.

---

## 7. Testing & Quality Assurance

### 7.1 Dedicated Feature Test Suite
**File:** [tests/Feature/AsyncQueuePushNotificationTest.php](file:///d:/Projects/Leadx/LeadX/tests/Feature/AsyncQueuePushNotificationTest.php)

8 tests covering all performance and reliability requirements:

| # | Test Name | Assertion | Result |
| :--- | :--- | :--- | :--- |
| 1 | `test_order_creation_dispatches_push_notification_job_to_queue` | Asserts job is pushed to `notifications` queue with correct title, payload, and interests | **PASS (0.36s)** |
| 2 | `test_in_app_database_notifications_persist_synchronously` | Asserts in-app notification is inserted immediately into `notifications` table | **PASS (0.02s)** |
| 3 | `test_rolled_back_transactions_do_not_dispatch_push_notifications` | Asserts `afterCommit` prevents push notification dispatch on transaction rollback | **PASS (0.02s)** |
| 4 | `test_push_notification_job_executes_successfully` | Asserts `SendWebPushNotificationJob::handle()` successfully formats Pusher request | **PASS (0.04s)** |
| 5 | `test_duplicate_notifications_are_prevented_by_dedup_key` | Asserts cache dedup suppresses rapid identical jobs within 10-minute window | **PASS (0.02s)** |
| 6 | `test_transient_server_error_triggers_retry_exception` | Asserts HTTP 504 from Pusher throws `RuntimeException` for worker retry backoff | **PASS (0.02s)** |
| 7 | `test_permanent_client_error_does_not_retry_indefinitely` | Asserts HTTP 422 does not throw, preventing endless worker retry loops | **PASS (0.02s)** |
| 8 | `test_failed_method_sanitizes_secrets_from_log` | Asserts secret keys are redacted (`***REDACTED***`) in error logs | **PASS (0.02s)** |

### 7.2 Full Application Regression Test
```bash
php artisan test
```
**Result:**
```
Tests:    390 passed (1598 assertions)
Duration: 14.59s
```

---

## 8. Queue Configuration & Production Operations Guide

### 8.1 Configuration Overview
LeadX supports the standard Laravel queue drivers configured in `config/queue.php`.

- **Local Development:**
  In `.env`:
  ```ini
  QUEUE_CONNECTION=sync
  ```
  *(Or `QUEUE_CONNECTION=database` when running a local worker).*

- **Production Environment:**
  In `.env`:
  ```ini
  QUEUE_CONNECTION=database
  ```
  *(Or `redis` for high-throughput deployments).*

### 8.2 Production Worker Command
To process queued notifications with priority:
```bash
php artisan queue:work --queue=notifications,default --tries=3 --timeout=30 --backoff=5,15,30
```

### 8.3 Supervisor Configuration (Production)
Save as `/etc/supervisor/conf.d/leadx-worker.conf`:
```ini
[program:leadx-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/leadx/artisan queue:work database --queue=notifications,default --sleep=3 --tries=3 --timeout=30 --max-time=3600
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

### 8.4 Post-Deployment Worker Restart
Whenever code is deployed or updated, restart workers gracefully:
```bash
php artisan queue:restart
```

### 8.5 Failed Job Monitoring & Inspection
- **List failed jobs:**
  ```bash
  php artisan queue:failed
  ```
- **Inspect a failed job:**
  ```bash
  php artisan queue:failed <id>
  ```
- **Retry a failed job:**
  ```bash
  php artisan queue:retry <id>
  ```
- **Retry all failed jobs:**
  ```bash
  php artisan queue:retry all
  ```
- **Flush resolved failed jobs:**
  ```bash
  php artisan queue:forget <id>
  php artisan queue:flush
  ```

---

## 9. Summary of Files Changed & Created

| File | Status | Description |
| :--- | :--- | :--- |
| `app/Jobs/SendWebPushNotificationJob.php` | **Created** | Asynchronous queue job with retry backoff, timeout, secret redaction, and dedup |
| `database/migrations/2026_11_24_000003_add_assigned_employee_created_at_index_to_sales_orders_table.php` | **Created** | Composite index on `sales_orders(assigned_employee_id, created_at)` |
| `app/Services/NotificationHubService.php` | **Modified** | Replaced synchronous `sendBeamsSafely` with async job dispatch |
| `tests/TestCase.php` | **Modified** | Extended `HttpFactory` to mock Pusher fallback while preserving custom test stubs |
| `tests/Feature/AsyncQueuePushNotificationTest.php` | **Created** | 8 dedicated feature tests for async queue push notifications |
| `docs/LEADX_PHASE_13_QUEUE_PERFORMANCE_REPORT.md` | **Created** | Comprehensive technical and performance report |

---

## 10. Remaining Limitations & Recommendations

1. **Redis Queue for Scale:** While the `database` queue driver is reliable and requires zero extra infrastructure, transitioning to `redis` (`QUEUE_CONNECTION=redis`) is recommended if order volume exceeds 10,000 orders/day.
2. **Pusher Rate Limit Monitoring:** Although Pusher Beams allows substantial web push throughput, monitor production Pusher API logs for any 429 Too Many Requests status codes during peak flash sale campaigns.
3. **Queue Health Checks:** Configure Laravel Health or Laravel Pulse to monitor queue depth and latency on the `notifications` queue in real time.
