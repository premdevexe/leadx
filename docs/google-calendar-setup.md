# Google Calendar Integration — Setup Guide (Phase 08, Module 2)

This backend module lets each **employee** connect their **own** Google
account so that customer follow-ups can be mirrored as events on their
personal calendar. No frontend is part of this module.

## 1. Google Cloud Console

1. **Create / pick a project** at <https://console.cloud.google.com>.
   The project must belong to the organization that is allowed to use the
   Calendar API (external developer projects are restricted by Google; see
   the note in §4 if you hit the "restricted" error).

2. **Enable the API**:
   - Open *APIs & Services → Enabled APIs*.
   - Search for **Google Calendar API** → **Enable**.

3. **Configure the OAuth consent screen** (*APIs & Services → OAuth consent
   screen*):
   - User type: **Internal** if every employee is on the same Google
     Workspace organization as the Cloud project. Use **External** (with a
     support email and test users while unverified) if your employees are on
     personal/consumer Google accounts.
   - No sensitive/restricted scopes are required — the Calendar API scope
     alone is enough.

4. **Create the OAuth client** (*APIs & Services → Credentials*):
   - *Create credentials → OAuth client ID*.
   - Application type: **Web application**.
   - **Authorized redirect URIs** must exactly match `GOOGLE_REDIRECT_URI`,
     e.g. `https://api.yourdomain.com/api/v1/google/calendar/callback`.
     (In development: `http://localhost:8000/api/v1/google/calendar/callback`
     — `localhost` is allowed over plain HTTP by Google.)
   - Copy the **Client ID** and **Client secret**.

5. **Domain verification (recommended)**: if the redirect URI is on your own
   domain, follow the *domain verification* instructions on the same
   credentials page so Google can only send codes to your server.

## 2. Environment variables

Add to `.env` (never hardcode credentials):

```dotenv
GOOGLE_CLIENT_ID=1234567890-abcdef.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX...
GOOGLE_REDIRECT_URI=https://api.yourdomain.com/api/v1/google/calendar/callback
```

Optional overrides (defaults shown):

```dotenv
# GOOGLE_TOKEN_URI=https://oauth2.googleapis.com/token
# GOOGLE_AUTH_URI=https://accounts.google.com/o/oauth2/auth
# GOOGLE_CALENDAR_API_BASE=https://www.googleapis.com/calendar/v3

# Follow-up event defaults
# LEADX_FOLLOWUP_EVENT_MINUTES=30
# LEADX_FOLLOWUP_REMINDER_MINUTES=30
```

Then run `php artisan config:clear` and restart the queue/scheduler if running.

## 3. How the flow works

| Step | Endpoint | Auth |
|---|---|---|
| 1. Get connect URL | `GET /api/v1/employee/google/calendar/connect` | employee Bearer token |
| 2. Browser redirect to Google; user consents | (Google) | — |
| 3. Google redirects browser | `GET /api/v1/google/calendar/callback?code=…&state=…` | **none** (browser navigation) |
| 4. Check status | `GET /api/v1/employee/google/calendar` | employee token |
| 5. Disconnect | `POST /api/v1/employee/google/calendar/disconnect` | employee token |

Security properties:

- **State validation / CSRF**: every connect URL carries an opaque,
  single-use `state` (40 random chars, 10-minute TTL). The callback verifies
  it with `hash_equals`, consumes it, and binds the flow to the employee who
  started it. A replayed or forged state fails the exchange.
- **Team login isolation**: the callback is unauthenticated by design and
  resolves the employee *only* from the state. The employee-guarded
  endpoints additionally reject any non-employee role — a team's shared
  login can never attach a personal Google Calendar.
- **Scopes**: only `calendar.events` + `userinfo.email` (to display which
  Google account is linked). No `calendar.readonly`-beyond or
  `calendar.events.readonly` extras, no contacts/calendar lists.
- **Refresh tokens**: requested with `access_type=offline` +
  `prompt=consent`. Access tokens auto-refresh in the background; a revoked
  refresh token marks the connection `revoked` (employee must reconnect).
- **At-rest encryption**: `access_token` / `refresh_token` are stored with
  Laravel's `encrypted` cast (AES-256 via `APP_KEY`). Tokens are **never**
  included in any API response; the resource exposes only email, status and
  expiry metadata.
- **Disposal**: `APP_KEY` rotation invalidates stored tokens (rows become
  undecryptable and surface as sync errors, prompting a reconnect).

## 4. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `403 access_denied` / `invalid_scope` | A scope was not granted; reconnect with `prompt=consent` (already the default). |
| `redirect_uri_mismatch` | The `GOOGLE_REDIRECT_URI` env value does not *exactly* match an authorized redirect URI (scheme, port, trailing path). |
| `invalid_grant` on refresh | The employee revoked the app (Google account → Security → Third-party access) or the connection was disconnected on their Google side. The row is marked `revoked`; employee reconnects. |
| "This API is restricted" | Consumer Google accounts cannot use the Calendar API from an external project; use an internal project or Workspace-licensed accounts. |
| `503 not configured` from `/connect` | `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` are empty; fill `.env` and clear config cache. |

## 5. Scheduler

`leadx:mark-follow-ups-overdue` runs daily at 00:10 (registered in
`routes/console.php`) and flips any past-due scheduled follow-ups to
`overdue`. Ensure `php artisan schedule:run` is running in production.
