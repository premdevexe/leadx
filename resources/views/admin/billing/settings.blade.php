@extends('layouts.admin-dashboard')

@section('title', 'Billing Settings')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Billing &amp; Invoice Settings</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Configure company billing identity, tax defaults, numbering sequence, and PDF layout.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.billing.settings.update') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-neutral-200 bg-white p-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Company Billing Name *</label>
                <input type="text" name="company_name" value="{{ old('company_name', $billing['company_name']) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Support Email</label>
                <input type="email" name="support_email" value="{{ old('support_email', $billing['support_email']) }}" class="form-input">
            </div>
            <div class="sm:col-span-2">
                <label class="form-label">Company Address</label>
                <textarea name="company_address" rows="2" class="form-input">{{ old('company_address', $billing['company_address']) }}</textarea>
            </div>
            <div>
                <label class="form-label">GSTIN (Optional)</label>
                <input type="text" name="gstin" value="{{ old('gstin', $billing['gstin']) }}" class="form-input">
            </div>
            <div>
                <label class="form-label">PAN (Optional)</label>
                <input type="text" name="pan" value="{{ old('pan', $billing['pan']) }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Support Phone</label>
                <input type="text" name="support_phone" value="{{ old('support_phone', $billing['support_phone'] ?? '') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Company Billing Logo</label>
                <input type="file" name="billing_logo" class="form-input">
                @if ($logoUrl)
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ $logoUrl }}" alt="Billing Logo" class="h-10 rounded border border-neutral-200 p-1">
                        <label class="text-xs text-red-600 flex items-center gap-1">
                            <input type="checkbox" name="remove_billing_logo" value="1"> Remove custom logo
                        </label>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="form-label">Invoice Prefix *</label>
                <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $billing['invoice_prefix']) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Starting / Next Sequence *</label>
                <input type="number" min="1" name="next_sequence" value="{{ old('next_sequence', $billing['next_sequence']) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Default Currency *</label>
                <select name="default_currency" class="form-input" required>
                    @foreach ($currencies as $code => $sym)
                        <option value="{{ $code }}" @selected(old('default_currency', $billing['default_currency']) === $code)>{{ $code }} ({{ $sym }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Default Tax Rate (%) *</label>
                <input type="number" step="0.01" min="0" max="100" name="default_tax_rate" value="{{ old('default_tax_rate', $billing['default_tax_rate']) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Tax Label *</label>
                <input type="text" name="default_tax_label" value="{{ old('default_tax_label', $billing['default_tax_label']) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Default Due Days *</label>
                <input type="number" min="0" max="365" name="default_due_days" value="{{ old('default_due_days', $billing['default_due_days']) }}" class="form-input" required>
            </div>
            <div class="sm:col-span-2">
                <label class="form-label">Default Payment Terms</label>
                <input type="text" name="default_payment_terms" value="{{ old('default_payment_terms', $billing['default_payment_terms']) }}" class="form-input">
            </div>
            <div class="sm:col-span-3">
                <label class="form-label mb-2">Default Invoice Template Design *</label>
                @include('admin.billing.partials.template-selector', ['inputName' => 'default_template', 'selected' => old('default_template', $billing['default_template'] ?? 'classic_navy')])
            </div>
            <div>
                <label class="form-label">PDF Paper Size</label>
                <select name="pdf_paper_size" class="form-input">
                    <option value="a4" @selected(($billing['pdf_paper_size'] ?? 'a4') === 'a4')>A4</option>
                    <option value="letter" @selected(($billing['pdf_paper_size'] ?? 'a4') === 'letter')>Letter</option>
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="form-label">Invoice Footer Text</label>
                <textarea name="invoice_footer_text" rows="2" class="form-input">{{ old('invoice_footer_text', $billing['invoice_footer_text']) }}</textarea>
            </div>
            <div class="sm:col-span-3 flex flex-wrap gap-5 pt-2">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="pdf_show_gstin" value="1" @checked($billing['pdf_show_gstin'] ?? true)> Show GSTIN on PDF
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="pdf_show_pan" value="1" @checked($billing['pdf_show_pan'] ?? true)> Show PAN on PDF
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="pdf_show_digital_verification" value="1" @checked($billing['pdf_show_digital_verification'] ?? true)> Show Digital Verification Hash
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Save Billing Settings</button>
        </div>
    </form>
@endsection
