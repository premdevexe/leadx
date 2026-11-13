<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin settings page. Currently exposes which payment methods are
 * enabled for orders (GPay, PhonePe, Paytm, …).
 */
class SettingsController extends Controller
{
    /**
     * GET /admin/settings
     */
    public function index(): View
    {
        $enabled = Setting::enabledPaymentMethods();

        return view('admin.settings.index', [
            'paymentMethods' => Setting::PAYMENT_METHODS,
            'enabledMethods' => $enabled,
        ]);
    }

    /**
     * PUT /admin/settings
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_methods' => ['array'],
            'payment_methods.*' => ['string', 'in:'.implode(',', array_keys(Setting::PAYMENT_METHODS))],
        ]);

        // A submitted (possibly empty) array always replaces the set, so
        // unchecking the last method disables payments entirely.
        $methods = $data['payment_methods'] ?? [];

        Setting::setStringList(Setting::KEY_PAYMENT_METHODS, $methods);

        return redirect()->route('admin.settings')->with('status', 'Settings saved.');
    }
}
