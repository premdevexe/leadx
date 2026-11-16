<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin settings page. Exposes site identity, branding & logo upload center,
 * currency, and enabled payment methods.
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
            'logoUrl' => Setting::logoUrl(),
            'logoPath' => Setting::logoPath(),
            'logoTransparentUrl' => Setting::logoTransparentUrl(),
            'logoTransparentPath' => Setting::logoTransparentPath(),
            'faviconUrl' => Setting::faviconUrl(),
            'faviconPath' => Setting::faviconPath(),
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
            // SVG is intentionally excluded: it is an XML format that can
            // carry <script> and executes in the context of every page that
            // renders it. A compromised or careless admin could otherwise
            // XSS all authenticated users through the site logo.
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'logo_transparent' => ['nullable', 'file', 'mimes:png,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,webp', 'max:1024'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_logo_transparent' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        // A submitted (possibly empty) array always replaces the set, so
        // unchecking the last method disables payments entirely.
        Setting::setStringList(Setting::KEY_PAYMENT_METHODS, $data['payment_methods'] ?? []);
        Setting::setCurrency($data['currency']);

        if (array_key_exists('site_name', $data)) {
            Setting::setSiteName((string) $data['site_name']);
        }

        $uploadDir = public_path('uploads/branding');
        if (! file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 1. Primary Site Logo
        if ($request->boolean('remove_logo')) {
            if ($old = Setting::logoPath()) {
                @unlink(public_path($old));
            }
            Setting::setLogo(null);
        } elseif ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            if ($old = Setting::logoPath()) {
                @unlink(public_path($old));
            }
            $file = $request->file('logo');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'logo_'.time().'_'.Str::random(8).'.'.$ext;
            $file->move($uploadDir, $filename);
            Setting::setLogo('uploads/branding/'.$filename);
        }

        // 2. Transparent Logo (Box Section)
        if ($request->boolean('remove_logo_transparent')) {
            if ($old = Setting::logoTransparentPath()) {
                @unlink(public_path($old));
            }
            Setting::setLogoTransparent(null);
        } elseif ($request->hasFile('logo_transparent') && $request->file('logo_transparent')->isValid()) {
            if ($old = Setting::logoTransparentPath()) {
                @unlink(public_path($old));
            }
            $file = $request->file('logo_transparent');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'logo_transparent_'.time().'_'.Str::random(8).'.'.$ext;
            $file->move($uploadDir, $filename);
            Setting::setLogoTransparent('uploads/branding/'.$filename);
        }

        // 3. Favicon
        if ($request->boolean('remove_favicon')) {
            if ($old = Setting::faviconPath()) {
                @unlink(public_path($old));
            }
            Setting::setFavicon(null);
        } elseif ($request->hasFile('favicon') && $request->file('favicon')->isValid()) {
            if ($old = Setting::faviconPath()) {
                @unlink(public_path($old));
            }
            $file = $request->file('favicon');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'ico');
            $filename = 'favicon_'.time().'_'.Str::random(8).'.'.$ext;
            $file->move($uploadDir, $filename);
            Setting::setFavicon('uploads/branding/'.$filename);
        }

        return redirect()->route('admin.settings')->with('status', 'Settings saved.');
    }
}
