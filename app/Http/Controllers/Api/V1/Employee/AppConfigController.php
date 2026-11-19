<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Mobile app configuration for the Flutter employee client.
 *
 * GET /api/v1/employee/app-config
 *
 * The admin panel is the single source of truth for currency (the `currency`
 * setting, managed at PUT /admin/settings). This endpoint is a read-only
 * projection of that configuration — plus the fixed formatting conventions
 * the web panels already use (`symbol + number_format(amount, 2)`) — so the
 * employee app can format every financial figure exactly like the admin and
 * web panels do. It exposes no new data and changes no admin behaviour; it
 * is guarded by the same employee middleware as the rest of the panel API.
 */
class AppConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'currency' => [
                    'code' => Setting::currency(),
                    'symbol' => Setting::currencySymbol(),
                    // Fixed by the existing web-panel formatting convention:
                    // symbol before the amount, 2 decimal places,
                    // thousands grouped with "," and decimals with ".".
                    'symbol_position' => 'before',
                    'decimal_places' => 2,
                    'thousands_separator' => ',',
                    'decimal_separator' => '.',
                ],
                // Payment rows carry their own currency code (legacy rows may
                // predate a currency change); the app resolves row symbols
                // through this map.
                'currencies' => Setting::CURRENCIES,
                'site_name' => Setting::siteName(),
                'site_logo' => Setting::logoTransparentUrl() ?? Setting::logoUrl(),
                'site_favicon' => Setting::faviconUrl(),
            ],
        ]);
    }
}
