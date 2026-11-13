<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google OAuth 2.0 (Calendar) — Phase 08, Module 2
    |--------------------------------------------------------------------------
    |
    | Credentials come exclusively from environment variables — nothing is
    | hardcoded. Set these in .env (see docs/google-calendar-setup.md for
    | the Cloud Console walkthrough):
    |
    |   GOOGLE_CLIENT_ID=
    |   GOOGLE_CLIENT_SECRET=
    |   GOOGLE_REDIRECT_URI=
    |
    | The redirect URI must exactly match an authorized redirect URI on the
    | OAuth client in Google Cloud Console.
    |
    */

    'client_id' => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', ''),

    /*
    |--------------------------------------------------------------------------
    | Google Calendar API
    |--------------------------------------------------------------------------
    |
    | The minimum scopes the module needs. We intentionally do NOT request
    | read-write: the app only ever creates/updates its own events and the
    | employee's primary calendar, which `calendar.events` (events scope)
    | plus `calendar.readonly` covers. `offline_access` is required to be
    | able to receive a refresh token.
    |
    */
    'scopes' => [
        'https://www.googleapis.com/auth/calendar.events',
    ],

    // offline_access lets Google issue a refresh token so expired access
    // tokens can be renewed without re-prompting the employee.
    'access_type' => 'offline',
    'prompt' => 'consent',

    // Token endpoints. Overridable for testing; defaults are the live
    // Google endpoints.
    'token_uri' => env('GOOGLE_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
    'auth_uri' => env('GOOGLE_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
    'calendar_api_base' => env('GOOGLE_CALENDAR_API_BASE', 'https://www.googleapis.com/calendar/v3'),

    /*
    |--------------------------------------------------------------------------
    | Follow-up calendar sync (Module 3)
    |--------------------------------------------------------------------------
    */
    'calendar' => [
        // Default event duration in minutes when a follow-up has no explicit
        // next_follow_up_at to bound the window.
        'default_event_minutes' => (int) env('LEADX_FOLLOWUP_EVENT_MINUTES', 30),

        // Reminder: minutes before the start of the event.
        'reminder_minutes' => (int) env('LEADX_FOLLOWUP_REMINDER_MINUTES', 30),
    ],

];
