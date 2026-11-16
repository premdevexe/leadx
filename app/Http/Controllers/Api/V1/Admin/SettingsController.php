<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundStatus;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin general settings — API mirror of the web /admin/settings panel.
 *
 * File uploads (logo/favicon) are intentionally out of scope for the app
 * API: an app cannot take meaningfully different branding files than the
 * web panel, and Multipart uploads add a whole attack surface. The
 * non-file settings (currency, site name, enabled payment methods,
 * refund status catalogue) are fully covered.
 */
class SettingsController extends Controller
{
    /**
     * GET /api/v1/admin/settings
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => [
                'site_name' => Setting::siteName(),
                'currency' => Setting::currency(),
                'currency_symbol' => Setting::currencySymbol(),
                'currencies' => Setting::CURRENCIES,
                'payment_method_options' => Setting::PAYMENT_METHODS,
                'enabled_payment_methods' => Setting::enabledPaymentMethods(),
            ],
            'refund_statuses' => RefundStatus::orderBy('id')->get(),
        ]);
    }

    /**
     * PUT /api/v1/admin/settings
     *
     * A submitted (possibly empty) payment_methods array always replaces
     * the set — unchecking the last method disables payments, exactly
     * like the web panel.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'in:'.implode(',', array_keys(Setting::CURRENCIES))],
            'payment_methods' => ['array'],
            'payment_methods.*' => ['string', 'in:'.implode(',', array_keys(Setting::PAYMENT_METHODS))],
        ]);

        Setting::setStringList(Setting::KEY_PAYMENT_METHODS, $data['payment_methods'] ?? []);
        Setting::setCurrency($data['currency']);

        if (array_key_exists('site_name', $data)) {
            Setting::setSiteName((string) $data['site_name']);
        }

        return response()->json([
            'message' => 'Settings updated successfully.',
            'settings' => [
                'site_name' => Setting::siteName(),
                'currency' => Setting::currency(),
                'enabled_payment_methods' => Setting::enabledPaymentMethods(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/settings/refund-statuses
     */
    public function storeRefundStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:refund_statuses,slug'],
            'label' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'in:neutral,amber,blue,emerald,red,purple'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        $status = RefundStatus::create([
            'slug' => strtolower($data['slug']),
            'label' => $data['label'],
            'color' => $data['color'],
            'is_final' => (bool) ($data['is_final'] ?? false),
            'is_active' => true,
        ]);

        return (new \App\Http\Resources\RefundStatusResource($status))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/admin/settings/refund-statuses/{refundStatus}
     */
    public function updateRefundStatus(Request $request, RefundStatus $refundStatus): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'in:neutral,amber,blue,emerald,red,purple'],
            'is_final' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $refundStatus->update([
            'label' => $data['label'],
            'color' => $data['color'],
            'is_final' => (bool) ($data['is_final'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return (new \App\Http\Resources\RefundStatusResource($refundStatus->fresh()))->response();
    }
}
