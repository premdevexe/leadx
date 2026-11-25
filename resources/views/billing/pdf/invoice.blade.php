<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 24px 28px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #1e293b;
            background: {{ ($isPreview ?? false) ? '#f1f5f9' : '#ffffff' }};
            margin: 0;
            padding: {{ ($isPreview ?? false) ? '24px' : '0' }};
        }
        .preview-toolbar {
            max-width: 794px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f294a;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 8px;
        }
        .preview-toolbar a, .preview-toolbar button {
            background: #15803d;
            color: #ffffff;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            margin-left: 6px;
        }
        .preview-toolbar a.secondary { background: #334155; }
        .invoice-sheet {
            max-width: 794px;
            margin: 0 auto;
            background: #ffffff;
            padding: {{ ($isPreview ?? false) ? '32px' : '0' }};
            {{ ($isPreview ?? false) ? 'border-radius: 8px; box-shadow: 0 4px 20px rgba(15,41,74,0.08);' : '' }}
        }
        .header-bar {
            border-top: 5px solid #0f294a;
            padding-top: 16px;
            margin-bottom: 20px;
        }
        table.layout { width: 100%; border-collapse: collapse; }
        .brand-title { font-size: 20px; font-weight: 700; color: #0f294a; margin: 0 0 4px 0; }
        .muted { color: #64748b; font-size: 10px; }
        .invoice-label {
            font-size: 22px;
            font-weight: 700;
            color: #0f294a;
            letter-spacing: 0.04em;
            text-align: right;
        }
        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 6px;
        }
        .status-paid { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .status-partial { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        .status-overdue { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .status-default { background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; }

        .meta-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #15803d;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 18px;
        }
        .section-heading {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0f294a;
            margin-bottom: 4px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.items thead th {
            background: #0f294a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 8px 10px;
            text-align: left;
        }
        table.items thead th.num, table.items tbody td.num { text-align: right; }
        table.items tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.items tbody tr:nth-child(even) { background: #f8fafc; }

        .totals-wrap { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .words-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 10px;
            color: #14532d;
        }
        table.totals { width: 100%; border-collapse: collapse; }
        table.totals td { padding: 4px 8px; font-size: 11px; }
        table.totals td.val { text-align: right; font-weight: 600; }
        table.totals tr.grand td {
            border-top: 2px solid #0f294a;
            border-bottom: 2px solid #0f294a;
            padding: 7px 8px;
            font-size: 13px;
            font-weight: 700;
            color: #0f294a;
        }
        table.totals tr.balance td {
            color: #15803d;
            font-weight: 700;
            padding-top: 6px;
        }

        .footer-grid {
            border-top: 1px solid #cbd5e1;
            padding-top: 12px;
            margin-top: 18px;
            font-size: 10px;
            color: #475569;
        }
        .verify-box {
            margin-top: 12px;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            font-size: 9px;
            color: #475569;
        }
        @media print {
            body { background: #ffffff; padding: 0; }
            .no-print { display: none !important; }
            .invoice-sheet { box-shadow: none; padding: 0; }
        }
        /* Template: modern_emerald */
        body.tpl-modern_emerald .header-bar {
            background: #0f294a;
            color: #ffffff;
            padding: 18px 20px;
            border-top: none;
            border-bottom: 4px solid #15803d;
            border-radius: 6px;
        }
        body.tpl-modern_emerald .header-bar .brand-title,
        body.tpl-modern_emerald .header-bar .invoice-label,
        body.tpl-modern_emerald .header-bar .muted,
        body.tpl-modern_emerald .header-bar .inv-num {
            color: #ffffff;
        }
        body.tpl-modern_emerald .header-bar .muted { opacity: 0.85; }
        body.tpl-modern_emerald .meta-box {
            border-left: 4px solid #0f294a;
            background: #f0fdf4;
        }
        /* Template: minimal_slate */
        body.tpl-minimal_slate .header-bar {
            border-top: 2px solid #0f294a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 14px;
        }
        body.tpl-minimal_slate .meta-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 2px solid #0f294a;
        }
        body.tpl-minimal_slate table.items thead th {
            background: #f8fafc;
            color: #0f294a;
            border-top: 1px solid #0f294a;
            border-bottom: 2px solid #0f294a;
        }
    </style>
</head>
<body class="tpl-{{ $template ?? 'classic_navy' }}" data-template="{{ $template ?? 'classic_navy' }}">
    @if ($isPreview ?? false)
        <div class="preview-toolbar no-print">
            <span>Invoice Preview — {{ $invoice->invoice_number }}</span>
            <div style="display:flex;align-items:center;">
                <select onchange="window.location='{{ route('admin.billing.invoices.preview', $invoice) }}?template='+this.value" style="padding:5px 8px;border-radius:6px;border:none;font-size:11px;margin-right:6px;">
                    @foreach (\App\Models\Invoice::TEMPLATES as $tplKey => $tplLabel)
                        <option value="{{ $tplKey }}" @selected(($template ?? 'classic_navy') === $tplKey)>{{ $tplLabel }}</option>
                    @endforeach
                </select>
                <a href="{{ route('admin.billing.invoices.show', $invoice) }}" class="secondary">Back</a>
                <button type="button" onclick="window.print()">Print Invoice</button>
                <a href="{{ route('admin.billing.invoices.pdf', ['invoice' => $invoice, 'template' => $template ?? 'classic_navy']) }}">Download PDF</a>
            </div>
        </div>
    @endif

    <div class="invoice-sheet">
        <div class="header-bar">
            <table class="layout">
                <tr>
                    <td style="width: 58%; vertical-align: top;">
                        @if (! empty($logoDataUri))
                            <img src="{{ $logoDataUri }}" alt="{{ $billing['company_name'] ?? 'LeadX' }}" style="max-height: 44px; margin-bottom: 6px;">
                        @else
                            <div class="brand-title">{{ $billing['company_name'] ?? 'LeadX' }}</div>
                        @endif
                        @if (! empty($billing['company_address']))
                            <div class="muted">{!! nl2br(e($billing['company_address'])) !!}</div>
                        @endif
                        @if (($billing['pdf_show_gstin'] ?? true) && ! empty($billing['gstin']))
                            <div class="muted"><strong>GSTIN:</strong> {{ $billing['gstin'] }}</div>
                        @endif
                        @if (($billing['pdf_show_pan'] ?? true) && ! empty($billing['pan']))
                            <div class="muted"><strong>PAN:</strong> {{ $billing['pan'] }}</div>
                        @endif
                    </td>
                    <td style="width: 42%; vertical-align: top; text-align: right;">
                        <div class="invoice-label">INVOICE</div>
                        <div class="inv-num" style="font-size: 13px; font-weight: 700; color: #1e293b; margin-top: 2px;">{{ $invoice->invoice_number }}</div>
                        @php
                            $pillClass = match($invoice->status) {
                                'paid' => 'status-paid',
                                'partially_paid' => 'status-partial',
                                'overdue' => 'status-overdue',
                                default => 'status-default',
                            };
                        @endphp
                        <div><span class="status-pill {{ $pillClass }}">{{ \App\Models\Invoice::STATUSES[$invoice->status] ?? ucfirst($invoice->status) }}</span></div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="meta-box">
            <table class="layout">
                <tr>
                    <td style="width: 55%; vertical-align: top;">
                        <div class="section-heading">Billed To</div>
                        <div style="font-size: 13px; font-weight: 700; color: #0f294a;">{{ $invoice->customer_name }}</div>
                        @if ($invoice->customer_email)
                            <div class="muted">{{ $invoice->customer_email }}</div>
                        @endif
                        @if ($invoice->customer_phone)
                            <div class="muted">{{ $invoice->customer_phone }}</div>
                        @endif
                        @if ($invoice->customer_address)
                            <div class="muted" style="margin-top: 2px;">{!! nl2br(e($invoice->customer_address)) !!}</div>
                        @endif
                    </td>
                    <td style="width: 45%; vertical-align: top; text-align: right;">
                        <div class="section-heading">Invoice Details</div>
                        <div><strong>Issue Date:</strong> {{ $invoice->issue_date->format('M d, Y') }}</div>
                        <div><strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'Upon Receipt' }}</div>
                        @if ($invoice->order)
                            <div><strong>Order Ref:</strong> {{ $invoice->order->order_number }}</div>
                        @endif
                        <div><strong>Currency:</strong> {{ $invoice->currency }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 44%;">Service / Description</th>
                    <th style="width: 16%;">Duration</th>
                    <th class="num" style="width: 10%;">Qty</th>
                    <th class="num" style="width: 15%;">Unit Price</th>
                    <th class="num" style="width: 15%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->description }}</strong>
                        </td>
                        <td>{{ $item->duration ?: '—' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                        <td class="num">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num"><strong>{{ $currencySymbol }}{{ number_format((float) $item->line_total, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-wrap">
            <tr>
                <td style="width: 55%; vertical-align: top; padding-right: 16px;">
                    <div class="words-box">
                        <div class="section-heading" style="color: #15803d;">Amount in Words</div>
                        <strong>{{ $amountInWords }}</strong>
                    </div>

                    @if ($invoice->payment_terms)
                        <div style="margin-top: 10px;">
                            <div class="section-heading">Billing Terms</div>
                            <div class="muted">{{ $invoice->payment_terms }}</div>
                        </div>
                    @endif
                    @if ($invoice->notes)
                        <div style="margin-top: 8px;">
                            <div class="section-heading">Notes</div>
                            <div class="muted">{{ $invoice->notes }}</div>
                        </div>
                    @endif
                </td>
                <td style="width: 45%; vertical-align: top;">
                    <table class="totals">
                        <tr>
                            <td>Subtotal</td>
                            <td class="val">{{ $currencySymbol }}{{ number_format((float) $invoice->subtotal, 2) }}</td>
                        </tr>
                        @if ((float) $invoice->discount_amount > 0)
                            <tr>
                                <td>Discount</td>
                                <td class="val" style="color: #b91c1c;">-{{ $currencySymbol }}{{ number_format((float) $invoice->discount_amount, 2) }}</td>
                            </tr>
                        @endif
                        @if ((float) $invoice->tax_amount > 0)
                            <tr>
                                <td>{{ $billing['default_tax_label'] ?? 'Tax' }} ({{ number_format((float) $invoice->tax_rate, 2) }}%)</td>
                                <td class="val">{{ $currencySymbol }}{{ number_format((float) $invoice->tax_amount, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="grand">
                            <td>Grand Total</td>
                            <td class="val">{{ $currencySymbol }}{{ number_format((float) $invoice->grand_total, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Payments Received</td>
                            <td class="val" style="color: #15803d;">{{ $currencySymbol }}{{ number_format((float) $invoice->amount_paid, 2) }}</td>
                        </tr>
                        @if ((float) $invoice->amount_refunded > 0)
                            <tr>
                                <td>Refunded</td>
                                <td class="val" style="color: #b91c1c;">{{ $currencySymbol }}{{ number_format((float) $invoice->amount_refunded, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="balance">
                            <td>Balance Due</td>
                            <td class="val">{{ $currencySymbol }}{{ number_format((float) $invoice->balance_due, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @if (($billing['pdf_show_digital_verification'] ?? true) && $invoice->verification_hash)
            <div class="verify-box">
                <strong>Digital Verification:</strong>
                Document Hash <code>{{ substr($invoice->verification_hash, 0, 24) }}</code>
                &middot; Issued {{ $invoice->created_at?->format('Y-m-d H:i') }} UTC
            </div>
        @endif

        <div class="footer-grid">
            <table class="layout">
                <tr>
                    <td style="width: 60%; vertical-align: top;">
                        {{ $billing['invoice_footer_text'] ?? 'Thank you for your business.' }}
                    </td>
                    <td style="width: 40%; vertical-align: top; text-align: right;">
                        @if (! empty($billing['support_email']))
                            <div>Support: {{ $billing['support_email'] }}</div>
                        @endif
                        @if (! empty($billing['support_phone']))
                            <div>{{ $billing['support_phone'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
