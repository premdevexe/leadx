<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin settings page. Exposes the site identity (name + currency) and
 * which payment methods are enabled for orders (GPay, PhonePe, Paytm, …).
 */
class SettingsController extends Controller
{
    /**
     * GET /admin/settings
     */
    public function index(): View
    {
        return view('admin.settings.index', [
            'paymentMethods' => Setting::PAYMENT_METHODS,
            'enabledMethods' => Setting::enabledPaymentMethods(),
            'siteName' => Setting::siteName(),
            'currencies' => Setting::CURRENCIES,
            'currency' => Setting::currency(),
        ]);
    }

    /**
     * PUT /admin/settings
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'in:'.implode(',', array_keys(Setting::CURRENCIES))],
            'payment_methods' => ['array'],
            'payment_methods.*' => ['string', 'in:'.implode(',', array_keys(Setting::PAYMENT_METHODS))],
        ]);

        // A submitted (possibly empty) array always replaces the set, so
        // unchecking the last method disables payments entirely.
        Setting::setStringList(Setting::KEY_PAYMENT_METHODS, $data['payment_methods'] ?? []);
        Setting::setCurrency($data['currency']);

        if (array_key_exists('site_name', $data)) {
            Setting::setSiteName((string) $data['site_name']);
        }

        return redirect()->route('admin.settings')->with('status', 'Settings saved.');
    }
}
