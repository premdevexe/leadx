<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone used to determine the "order year" in generated order
    | numbers (TEAMCODE-YEAR-SEQUENCE). Falls back to the application
    | timezone when unset.
    |
    */
    'business_timezone' => env('LEADX_BUSINESS_TIMEZONE', config('app.timezone', 'UTC')),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    |
    | Click-to-chat settings. The backend only ever builds and logs links —
    | it never sends messages itself.
    |
    */
    'whatsapp' => [
        'base_url' => env('LEADX_WHATSAPP_BASE_URL', 'https://wa.me'),
        'default_country_code' => env('LEADX_DEFAULT_COUNTRY_CODE', '1'),
        'message_template' => env('LEADX_WHATSAPP_TEMPLATE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing rules
    |--------------------------------------------------------------------------
    |
    | How expiry dates are computed from a billing cycle. The plan's
    | `billing_cycle` always wins when both it and the order's duration are
    | present; otherwise the order's duration is mapped to a cycle here.
    |
    */

    'billing' => [
        // Expiry horizon per billing cycle.
        'cycles' => [
            'monthly' => ['months' => 1],
            'quarterly' => ['months' => 3],
            'yearly' => ['months' => 12],
        ],

        // Order duration codes that map onto a billing cycle. Unknown codes
        // fall back to 'yearly' (legacy activation behaviour).
        'duration_map' => [
            '3d' => 'monthly',
            '7d' => 'monthly',
            '15d' => 'monthly',
            '30d' => 'monthly',
            '3m' => 'quarterly',
            '6m' => 'quarterly',
            '1y' => 'yearly',
        ],

        // Grace window (in days) for upcoming-expiry / renewal reminders.
        'expiry_reminder_days' => (int) env('LEADX_EXPIRY_REMINDER_DAYS', 7),
        'renewal_reminder_days' => (int) env('LEADX_RENEWAL_REMINDER_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment policy
    |--------------------------------------------------------------------------
    |
    | Activation policy. `activate_on` selects when a subscription may
    | activate relative to the verified payment state:
    |   'paid'    — only once total verified payments reach the amount due
    |   'partial' — also allow activation on verified partial payment,
    |               gated by `minimum_verified_percent` of the closing value
    |
    */

    'payments' => [
        'activate_on' => env('LEADX_PAYMENTS_ACTIVATE_ON', 'paid'),
        'minimum_verified_percent' => (float) env('LEADX_PAYMENTS_MIN_VERIFIED_PERCENT', 100.0),
        // Verified payment rows older than this are treated as stale and
        // require re-verification before they count toward activation.
        'stale_after_days' => (int) env('LEADX_PAYMENTS_STALE_AFTER_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateway
    |--------------------------------------------------------------------------
    |
    | Webhook handling is signature-verified and idempotent. The driver
    | selects the verifier; only `mock` ships out of the box — add a driver
    | for a real gateway without touching the controller.
    |
    */

    'gateway' => [
        'driver' => env('LEADX_GATEWAY_DRIVER', 'mock'),
        'secret' => env('LEADX_GATEWAY_WEBHOOK_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM customer matching
    |--------------------------------------------------------------------------
    |
    | The established unique customer-matching policy, applied in order:
    |   1. exact normalized phone match (same team)
    |   2. exact normalized (phone + email) pair match
    | Only *verified* purchases create — or upgrade a customer to `paying`
    | status. Unpaid enquiries stay `enquiry` and are never counted as
    | paying CRM customers.
    |
    */

    'crm' => [
        'match' => ['phone', 'phone_email_pair'],
        'auto_create_on_verified_purchase' => (bool) env('LEADX_CRM_AUTO_CREATE', true),
    ],

];
