<?php

namespace App\Http\Controllers\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingSettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.settings', [
            'billing' => Setting::billing(),
            'currencies' => Setting::CURRENCIES,
            'logoUrl' => Setting::billingLogoUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_address' => 'nullable|string|max:1000',
            'gstin' => 'nullable|string|max:50',
            'pan' => 'nullable|string|max:50',
            'invoice_prefix' => 'required|string|max:20',
            'next_sequence' => 'required|integer|min:1|max:9999999',
            'default_currency' => ['required', Rule::in(array_keys(Setting::CURRENCIES))],
            'default_tax_rate' => 'required|numeric|min:0|max:100',
            'default_tax_label' => 'required|string|max:50',
            'default_payment_terms' => 'nullable|string|max:255',
            'default_due_days' => 'required|integer|min:0|max:365',
            'invoice_footer_text' => 'nullable|string|max:1000',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'default_template' => ['required', Rule::in(array_keys(\App\Models\Invoice::TEMPLATES))],
            'pdf_paper_size' => 'required|in:a4,letter',
            'billing_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $billing = Setting::billing();
        $logoPath = $billing['billing_logo'] ?? null;

        if ($request->boolean('remove_billing_logo') && $logoPath) {
            if (file_exists(public_path($logoPath))) {
                @unlink(public_path($logoPath));
            }
            $logoPath = null;
        }

        if ($request->hasFile('billing_logo') && $request->file('billing_logo')->isValid()) {
            $dir = public_path('uploads/branding');
            File::ensureDirectoryExists($dir);
            $file = $request->file('billing_logo');
            $guessed = strtolower((string) ($file->guessExtension() ?: 'bin'));
            $ext = in_array($guessed, ['jpg', 'jpeg', 'png', 'webp'], true) ? $guessed : 'bin';
            $filename = 'billing_logo_'.time().'_'.Str::random(10).'.'.$ext;
            $file->move($dir, $filename);
            $logoPath = 'uploads/branding/'.$filename;
        }

        Setting::setBilling([
            'company_name' => $validated['company_name'],
            'company_address' => $validated['company_address'] ?? '',
            'gstin' => strtoupper((string) ($validated['gstin'] ?? '')),
            'pan' => strtoupper((string) ($validated['pan'] ?? '')),
            'billing_logo' => $logoPath,
            'invoice_prefix' => strtoupper((string) $validated['invoice_prefix']),
            'next_sequence' => (int) $validated['next_sequence'],
            'default_currency' => $validated['default_currency'],
            'default_tax_rate' => (float) $validated['default_tax_rate'],
            'default_tax_label' => $validated['default_tax_label'],
            'default_payment_terms' => $validated['default_payment_terms'] ?? '',
            'default_due_days' => (int) $validated['default_due_days'],
            'invoice_footer_text' => $validated['invoice_footer_text'] ?? '',
            'support_email' => $validated['support_email'] ?? '',
            'support_phone' => $validated['support_phone'] ?? '',
            'default_template' => $validated['default_template'],
            'pdf_paper_size' => $validated['pdf_paper_size'],
            'pdf_show_gstin' => $request->boolean('pdf_show_gstin'),
            'pdf_show_pan' => $request->boolean('pdf_show_pan'),
            'pdf_show_digital_verification' => $request->boolean('pdf_show_digital_verification'),
        ]);

        Setting::setCurrency($validated['default_currency']);

        return back()->with('success', 'Billing & invoice settings updated.');
    }
}
