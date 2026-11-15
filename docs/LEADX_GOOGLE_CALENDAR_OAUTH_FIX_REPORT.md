# LEADX — Google Calendar OAuth Fix Report

## 1. Root Cause

**Three conflicting redirect URIs** across the codebase caused Google's `redirect_uri_mismatch` error (HTTP 400). The critical issue was in the **Admin Settings UI** (`resources/views/admin/api-settings/index.blade.php:156`), where the default value for the redirect URI field was the **connect endpoint URL** (`/employee/calendar/connect`) instead of the **OAuth callback URL** (`/api/v1/google/calendar/callback`).

When an administrator opened the API settings page and saved without changing the redirect URI field, the wrong URL was persisted to the database and then loaded into the Laravel config at runtime. The OAuth authorization request then sent this wrong redirect URI to Google, which rejected it because no such redirect URI was registered in Google Cloud Console.

The `.env.example` also had `localhost` as the host instead of `127.0.0.1`, which would cause a mismatch if copied to a fresh `.env` (since `config/google.php` defaults to `127.0.0.1`).

## 2. Exact Incorrect Configuration

### Admin settings view (before fix)

```php
<!-- resources/views/admin/api-settings/index.blade.php:156 -->
value="{{ old('redirect_uri', $google['redirect_uri'] ?? url('/employee/calendar/connect')) }}"
```

This defaulted to `http://127.0.0.1:8000/employee/calendar/connect` — a **web page route**, not an OAuth callback endpoint. Google Cloud Console has no such redirect URI registered.

### `.env.example` (before fix)

```
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/google/calendar/callback
```

Uses `localhost`, but `config/google.php` fallback uses `127.0.0.1`. These are distinct origins to Google.

### Admin test (before fix)

```php
// tests/Feature/AdminApiSettingsTest.php:81
'redirect_uri' => 'https://leadx.test/employee/calendar/connect',
```

Also used the wrong path.

## 3. Exact Corrected Configuration

### Admin settings view (after fix)

```php
<!-- resources/views/admin/api-settings/index.blade.php:156 -->
value="{{ old('redirect_uri', $google['redirect_uri'] ?? url('/api/v1/google/calendar/callback')) }}"
```

Defaults to the correct OAuth callback URL: `http://127.0.0.1:8000/api/v1/google/calendar/callback`.

### `.env.example` (after fix)

```
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/api/v1/google/calendar/callback
```

Uses `127.0.0.1`, matching `config/google.php`.

### Admin test (after fix)

```php
// tests/Feature/AdminApiSettingsTest.php:81
'redirect_uri' => 'https://leadx.test/api/v1/google/calendar/callback',
```

Uses the correct callback path.

## 4. Actual OAuth Callback URI

```
http://127.0.0.1:8000/api/v1/google/calendar/callback
```

- **Route**: `GET /api/v1/google/calendar/callback`
- **Controller**: `App\Http\Controllers\Api\V1\Employee\GoogleCalendarController::callback()`
- **Route file**: `routes/api.php:96`
- **Config source**: `config('google.redirect_uri')` → `env('GOOGLE_REDIRECT_URI', 'http://127.0.0.1:8000/api/v1/google/calendar/callback')`
- **Web fallback**: `GET /employee/calendar/callback` (same service, HTML redirect response)

Both callbacks use the same `GoogleOAuthService::completeFromCallback()` method and the same `config('google.redirect_uri')` value for the token exchange.

## 5. Files Changed

| File | Change |
|---|---|
| [`.env.example`](.env.example) | Corrected `localhost` → `127.0.0.1` in `GOOGLE_REDIRECT_URI` |
| [`resources/views/admin/api-settings/index.blade.php`](resources/views/admin/api-settings/index.blade.php) | Fixed default redirect URI from `/employee/calendar/connect` to `/api/v1/google/calendar/callback`; added help text |
| [`tests/Feature/AdminApiSettingsTest.php`](tests/Feature/AdminApiSettingsTest.php) | Fixed test redirect URI; added redirect_uri assertions |
| [`docs/GOOGLE_CALENDAR_OAUTH_CONFIGURATION.md`](docs/GOOGLE_CALENDAR_OAUTH_CONFIGURATION.md) | Created comprehensive OAuth configuration documentation |
| [`docs/LEADX_GOOGLE_CALENDAR_OAUTH_FIX_REPORT.md`](docs/LEADX_GOOGLE_CALENDAR_OAUTH_FIX_REPORT.md) | This report |

## 6. Routes Verified

| Method | Path | Controller | Purpose |
|---|---|---|---|
| GET | `/employee/calendar` | `EmployeeDashboardController::calendar` | Calendar status page |
| GET | `/employee/calendar/connect` | `EmployeeDashboardController::connectCalendar` | Web: start OAuth flow |
| GET | `/employee/calendar/callback` | `EmployeeDashboardController::calendarCallback` | Web: OAuth callback (HTML redirect) |
| POST | `/employee/calendar/disconnect` | `EmployeeDashboardController::disconnectCalendar` | Web: disconnect |
| GET | `/api/v1/employee/google/calendar/connect` | `GoogleCalendarController::connect` | API: start OAuth flow |
| GET | `/api/v1/employee/google/calendar` | `GoogleCalendarController::status` | API: connection status |
| POST | `/api/v1/employee/google/calendar/disconnect` | `GoogleCalendarController::disconnect` | API: disconnect |
| GET|POST | `/api/v1/google/calendar/callback` | `GoogleCalendarController::callback` | API: OAuth callback |

## 7. Security Improvements

The following security properties were verified in the existing codebase and confirmed intact:

- **OAuth state validation**: 40-char random state, 10-minute TTL, single-use, bound to employee ID
- **CSRF prevention**: State is consumed on first use; replay attacks fail
- **Token encryption**: `access_token` and `refresh_token` use Laravel's `encrypted` cast (AES-256 via `APP_KEY`)
- **Credential isolation**: Tokens are never serialized in API responses (see `EmployeeGoogleConnectionResource`)
- **Per-employee ownership**: `requireEmployee()` rejects team logins and non-employee roles
- **Client secret isolation**: Never sent to frontend; only transmitted to Google's token endpoint over HTTPS
- **No token logging**: Error reports use sanitized status codes; raw tokens never appear in logs

No security regressions were introduced by this fix.

## 8. Tests Executed and Results

```
Tests: 37 passed (142 assertions)
```

| Test Suite | Tests | Result |
|---|---|---|
| `AdminApiSettingsTest` | 6 | All passed |
| `GoogleCalendarOauthTest` | 8 | All passed |
| `GoogleCalendarPopupAndReminderTest` | 7 | All passed |
| `OrderDeliveryAndExpiryAutomationTest` | 16 | All passed |

## 9. Google Cloud Console Manual Action Required

Go to **Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client ID → Authorized redirect URIs** and ensure the following URI is listed:

```
http://127.0.0.1:8000/api/v1/google/calendar/callback
```

For production, also add:

```
https://your-production-domain.com/api/v1/google/calendar/callback
```

(Replace with your actual production domain.)

The admin settings page at `/admin/api-settings` now defaults to the correct callback URL, so administrators who update Google credentials through the UI will automatically set the right redirect URI.

## 10. Local Testing Instructions

1. Ensure `.env` has the correct values:

   ```dotenv
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=your-client-secret
   GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/api/v1/google/calendar/callback
   ```

2. Clear config cache:

   ```bash
   php artisan config:clear
   ```

3. Add the redirect URI to Google Cloud Console (see section 9).

4. Log in as an employee, navigate to `/employee/calendar`, click "Connect Google Calendar".

5. After consent, you should be redirected back to `/employee/calendar` with a success message — not a `redirect_uri_mismatch` error.

6. Run tests:

   ```bash
   php artisan test tests/Feature/Api/GoogleCalendarOauthTest.php
   php artisan test tests/Feature/AdminApiSettingsTest.php
   ```

## 11. Production Configuration Instructions

1. Set environment variables:

   ```dotenv
   APP_URL=https://your-domain.com
   GOOGLE_CLIENT_ID=production-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=production-client-secret
   GOOGLE_REDIRECT_URI=https://your-domain.com/api/v1/google/calendar/callback
   ```

2. Add the production redirect URI to Google Cloud Console.

3. Rebuild config cache:

   ```bash
   php artisan config:cache
   ```

4. Ensure the application enforces HTTPS (load balancer / nginx / .htaccess).

5. Run the test suite to verify:

   ```bash
   php artisan test
   ```
