<?php

namespace App\Services\Billing;

use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class InvoicePdfService
{
    public function viewData(Invoice $invoice, ?string $templateOverride = null): array
    {
        $invoice->loadMissing(['customer', 'order', 'items.product', 'payments', 'refunds', 'adjustments']);
        $billing = Setting::billing();

        $template = $templateOverride && array_key_exists($templateOverride, Invoice::TEMPLATES)
            ? $templateOverride
            : ($invoice->template && array_key_exists($invoice->template, Invoice::TEMPLATES)
                ? $invoice->template
                : ($billing['default_template'] ?? 'classic_navy'));

        $logoDataUri = null;
        $logoPath = Setting::billingLogoLocalPath();
        if ($logoPath && file_exists($logoPath)) {
            $mime = mime_content_type($logoPath) ?: 'image/png';
            $logoDataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return [
            'invoice' => $invoice,
            'billing' => $billing,
            'template' => $template,
            'currencySymbol' => Setting::currencySymbolFor($invoice->currency),
            'amountInWords' => AmountInWordsService::convert((float) $invoice->grand_total, $invoice->currency),
            'logoDataUri' => $logoDataUri,
        ];
    }

    public function buildPdf(Invoice $invoice, ?string $templateOverride = null)
    {
        $billing = Setting::billing();
        $paper = (string) ($billing['pdf_paper_size'] ?? 'a4');

        return Pdf::loadView('billing.pdf.invoice', $this->viewData($invoice, $templateOverride))
            ->setPaper($paper, 'portrait');
    }

    public function download(Invoice $invoice, ?string $templateOverride = null): Response
    {
        return $this->buildPdf($invoice, $templateOverride)
            ->download($invoice->invoice_number.'.pdf');
    }

    public function stream(Invoice $invoice): Response
    {
        return $this->buildPdf($invoice)
            ->stream($invoice->invoice_number.'.pdf');
    }

    public function sendByEmail(Invoice $invoice, ?string $recipientEmail = null): bool
    {
        $to = trim((string) ($recipientEmail ?: $invoice->customer_email ?: $invoice->customer?->email));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $billing = Setting::billing();
        $companyName = $billing['company_name'] ?? Setting::siteName();
        $currencySymbol = Setting::currencySymbolFor($invoice->currency);
        $subject = sprintf('Invoice %s from %s', $invoice->invoice_number, $companyName);
        $pdfBytes = $this->buildPdf($invoice)->output();

        $body = sprintf(
            "Hello %s,\n\nPlease find attached your invoice %s dated %s for %s%s.\nBalance Due: %s%s\n\n%s\n%s",
            $invoice->customer_name,
            $invoice->invoice_number,
            $invoice->issue_date->format('M d, Y'),
            $currencySymbol,
            number_format((float) $invoice->grand_total, 2),
            $currencySymbol,
            number_format((float) $invoice->balance_due, 2),
            $invoice->payment_terms ?: ($billing['default_payment_terms'] ?? ''),
            $companyName
        );

        Mail::raw($body, function ($message) use ($to, $subject, $pdfBytes, $invoice) {
            $message->to($to)
                ->subject($subject)
                ->attachData($pdfBytes, $invoice->invoice_number.'.pdf', [
                    'mime' => 'application/pdf',
                ]);
        });

        if (class_exists(EmailLog::class)) {
            try {
                EmailLog::create([
                    'event' => 'invoice.sent',
                    'recipient_email' => $to,
                    'subject' => $subject,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable) {
                // EmailLog schema may vary; never fail invoice send on log write
            }
        }

        if ($invoice->status === Invoice::STATUS_DRAFT) {
            $invoice->update([
                'status' => Invoice::STATUS_SENT,
                'sent_at' => now(),
            ]);
        }

        return true;
    }
}
