# Google Calendar OAuth Configuration

Instructions for configuring Google Cloud Console and the LeadX environment for the Google Calendar OAuth 2.0 integration.

## OAuth Callback Route

The single OAuth callback route for Google Calendar is:

```
GET /api/v1/google/calendar/callback
```

This is the **only** URL Google will redirect to after the employee consents. It is handled by [`GoogleCalendarController::callback()`](app/Http/Controllers/Api/V1/Employee/GoogleCalendarController.php) and uses the signed single-use `state` parameter (stored in cache) to identify the employee — no Bearer token is required because the flow is a browser navigation from Google.

A secondary web-callback exists for HTML browser redirects:

```
GET /employee/calendar/callback
```

This is handled by [`EmployeeDashboardController::calendarCallback()`](app/Http/Controllers/Employee/DashboardController.php) and performs the same OAuth state validation and token exchange. Both routes share the same `GoogleOAuthService`.

## Exact Authorized Redirect URI (Local Development)

```
http://127.0.0.1:8000/api/v1/google/calendar/callback
```

**Do NOT use `localhost`** — the application's `config/google.php` default fallback uses `127.0.0.1`. Google treats `localhost` and `127.0.0.1` as distinct origins.

## Google Cloud Console Configuration

### Step-by-step

1. Go to [Google Cloud Console → APIs & Services → Credentials](https://console.cloud.google.com/apis/credentials)
2. Find your OAuth 2.0 Client ID (type: **Web application**), or create a new one:
   - *Create credentials → OAuth client ID*
   - Application type: **Web application**
3. Under **Authorized redirect URIs**, add:

   ```
   http://127.0.0.1:8000/api/v1/google/calendar/callback
   ```

   For production, also add:

   ```
   https://your-production-domain.com/api/v1/google/calendar/callback
   ```

4. Copy the **Client ID** and **Client Secret** (you will never see the secret again).

### Authorized JavaScript Origins

The OAuth authorization code flow uses server-side redirects (not JavaScript origins). **No authorized JavaScript origin is required** for this implementation. The employee's browser is redirected to Google's authorization endpoint directly.

If you use a JavaScript SDK for other Google APIs in the future, add:

```
http://127.0.0.1:8000
```

for local development.

## Environment Variables

Add these to your `.env` file:

```dotenv
# Required — Google Cloud Console → Credentials → OAuth 2.0 Client ID
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-YOUR_SECRET

# Must exactly match the Authorized Redirect URI in Google Cloud Console
# Local development:
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/api/v1/google/calendar/callback
# Production (example):
# GOOGLE_REDIRECT_URI=https://your-production-domain.com/api/v1/google/calendar/callback

# Optional — override only if needed
# GOOGLE_TOKEN_URI=https://oauth2.googleapis.com/token
# GOOGLE_AUTH_URI=https://accounts.google.com/o/oauth2/auth
# GOOGLE_REVOKE_URI=https://oauth2.googleapis.com/revoke
# GOOGLE_CALENDAR_API_BASE=https://www.googleapis.com/calendar/v3
# GOOGLE_API_KEY=  (optional API key for Calendar API; sent via X-Goog-Api-Key header)
```

### Security notes

- **Never** commit `GOOGLE_CLIENT_SECRET` to version control.
- The admin settings panel (`/admin/api-settings`) stores `client_secret` encrypted at rest in the `api_settings` table. When loaded at runtime via `ApiSetting::bootstrapRuntimeConfig()`, it overrides the `.env` value in the Laravel config.
- Use `GOOGLE_REDIRECT_URI` for different values in local and production — it is read from the environment at config load time.

## Configuration Cache

After changing `.env` values, run:

```bash
php artisan config:clear
```

If the application uses `config:cache` in production, rebuild the cache:

```bash
php artisan config:cache
```

## OAuth Flow Summary

```
Employee clicks "Connect Google Calendar"
        │
        ▼
GET /employee/calendar/connect  (web)  ── or ──  GET /api/v1/employee/google/calendar/connect  (API)
        │
        ▼  (GoogleOAuthService::authorizationUrl)
Google authorization URL with:
  - client_id
  - redirect_uri = config('google.redirect_uri')
  - state (single-use, 10-min TTL, bound to employee)
        │
        ▼  (Employee's browser navigates to Google)
Google consent screen
        │
        ▼  (Google redirects browser)
GET /api/v1/google/calendar/callback?code=...&state=...
        │
        ▼  (GoogleOAuthService::completeFromCallback)
  - Validates state (CSRF/replay protection)
  - Exchanges code for tokens (same redirect_uri)
  - Stores encrypted tokens in employee_google_connections
        │
        ▼
Redirect to /employee/calendar → "Connected"
```

## Production Configuration

1. Set `APP_URL=https://your-domain.com` in `.env`
2. Set `GOOGLE_REDIRECT_URI=https://your-domain.com/api/v1/google/calendar/callback`
3. Add the production redirect URI to Google Cloud Console → Authorized redirect URIs
4. Ensure HTTPS is enforced at the load balancer / web server level
5. Run `php artisan config:cache` after deployment

## Testing

The OAuth tests in [`tests/Feature/Api/GoogleCalendarOauthTest.php`](tests/Feature/Api/GoogleCalendarOauthTest.php) use `Http::fake()` to mock the Google token endpoint. The admin settings test in [`tests/Feature/AdminApiSettingsTest.php`](tests/Feature/AdminApiSettingsTest.php) verifies that redirect URIs are correctly stored and loaded into the Laravel config at runtime.

Run tests:

```bash
php artisan test tests/Feature/Api/GoogleCalendarOauthTest.php
php artisan test tests/Feature/AdminApiSettingsTest.php
```

## Troubleshooting

| Symptom | Cause / Fix |
|---|---|
| `redirect_uri_mismatch` | `GOOGLE_REDIRECT_URI` does not exactly match a URI in Google Cloud Console. Check scheme (`http` vs `https`), hostname (`127.0.0.1` vs `localhost`), port, and trailing path. Run `php artisan config:clear`. |
| `invalid_grant` | Employee revoked the app from their Google account. The connection is marked `revoked` and the employee must reconnect. |
| `503 not configured` | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, or `GOOGLE_REDIRECT_URI` is empty. Fill `.env` and clear config cache. |
| State validation fails | The state TTL is 10 minutes. If the employee takes too long to consent, restart the flow. |
