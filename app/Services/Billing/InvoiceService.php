<?php

namespace App\Services\Billing;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceAdjustment;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        protected InvoiceNumberService $numbers,
        protected BillingAuditService $audit,
    ) {}

    /**
     * Create a new invoice inside a database transaction with server-side math.
     *
     * @param  array<string, mixed>  $data
     */
    public function createInvoice(array $data, ?Admin $admin = null, ?User $employee = null): Invoice
    {
        return DB::transaction(function () use ($data, $admin, $employee) {
            $customer = Customer::findOrFail((int) $data['customer_id']);
            $order = ! empty($data['order_id']) ? SalesOrder::find((int) $data['order_id']) : null;
            $subscription = ! empty($data['subscription_id']) ? Subscription::find((int) $data['subscription_id']) : null;

            $billing = Setting::billing();
            $currency = strtoupper((string) ($data['currency'] ?? $billing['default_currency'] ?? Setting::currency()));
            $template = ! empty($data['template']) && array_key_exists($data['template'], Invoice::TEMPLATES)
                ? (string) $data['template']
                : (string) ($billing['default_template'] ?? 'classic_navy');
            $issueDate = Carbon::parse($data['issue_date'] ?? now()->toDateString());
            $dueDate = ! empty($data['due_date'])
                ? Carbon::parse($data['due_date'])
                : $issueDate->copy()->addDays((int) ($billing['default_due_days'] ?? 14));

            $computed = $this->computeTotals(
                $data['items'] ?? [],
                (string) ($data['discount_type'] ?? 'fixed'),
                (float) ($data['discount_value'] ?? 0),
                (float) ($data['tax_rate'] ?? ($billing['default_tax_rate'] ?? 0))
            );

            $status = ($data['status'] ?? Invoice::STATUS_DRAFT) === Invoice::STATUS_SENT
                ? Invoice::STATUS_SENT
                : Invoice::STATUS_DRAFT;

            $invoiceNumber = $this->numbers->nextInvoiceNumber();

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
                'subscription_id' => $subscription?->id,
                'team_id' => $customer->team_id ?? $order?->team_id,
                'created_by_admin_id' => $admin?->id,
                'created_by_user_id' => $employee?->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'customer_address' => $data['customer_address'] ?? null,
                'status' => $status,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'currency' => $currency,
                'template' => $template,
                'subtotal' => $computed['subtotal'],
                'discount_type' => $computed['discount_type'],
                'discount_value' => $computed['discount_value'],
                'discount_amount' => $computed['discount_amount'],
                'tax_rate' => $computed['tax_rate'],
                'tax_amount' => $computed['tax_amount'],
                'grand_total' => $computed['grand_total'],
                'amount_paid' => 0,
                'amount_refunded' => 0,
                'balance_due' => $computed['grand_total'],
                'payment_terms' => $data['payment_terms'] ?? ($billing['default_payment_terms'] ?? null),
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'billing_cycle' => $data['billing_cycle'] ?? null,
                'billing_cycle_start' => $data['billing_cycle_start'] ?? null,
                'billing_cycle_end' => $data['billing_cycle_end'] ?? null,
                'sent_at' => $status === Invoice::STATUS_SENT ? now() : null,
                'verification_hash' => $this->generateVerificationHash($invoiceNumber, $customer->id, $computed['grand_total']),
            ]);

            $this->syncItems($invoice, $computed['items']);

            $this->audit->log($invoice, 'invoice.created', [], [
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'grand_total' => $invoice->grand_total,
                'status' => $invoice->status,
            ]);

            return $invoice->load(['items', 'customer', 'order', 'payments']);
        });
    }

    /**
     * Update an existing editable invoice. Paid/cancelled/refunded invoices are locked.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInvoice(Invoice $invoice, array $data, ?Admin $admin = null): Invoice
    {
        if ($invoice->isFinanciallyLocked() || (float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoices with recorded payments or finalized status cannot be modified directly. Issue a Credit Note or Adjustment instead.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data) {
            $oldSnapshot = [
                'grand_total' => $invoice->grand_total,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date?->toDateString(),
            ];

            $customer = ! empty($data['customer_id'])
                ? Customer::findOrFail((int) $data['customer_id'])
                : $invoice->customer;

            $computed = $this->computeTotals(
                $data['items'] ?? [],
                (string) ($data['discount_type'] ?? $invoice->discount_type),
                (float) ($data['discount_value'] ?? $invoice->discount_value),
                (float) ($data['tax_rate'] ?? $invoice->tax_rate)
            );

            $status = $data['status'] ?? $invoice->status;
            if (! in_array($status, [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT], true)) {
                $status = $invoice->status;
            }

            $invoice->update([
                'customer_id' => $customer?->id ?? $invoice->customer_id,
                'order_id' => array_key_exists('order_id', $data) ? (! empty($data['order_id']) ? (int) $data['order_id'] : null) : $invoice->order_id,
                'customer_name' => $customer?->name ?? $invoice->customer_name,
                'customer_email' => $customer?->email ?? $invoice->customer_email,
                'customer_phone' => $customer?->phone ?? $invoice->customer_phone,
                'customer_address' => $data['customer_address'] ?? $invoice->customer_address,
                'status' => $status,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'currency' => ! empty($data['currency']) ? strtoupper((string) $data['currency']) : $invoice->currency,
                'template' => ! empty($data['template']) && array_key_exists($data['template'], Invoice::TEMPLATES)
                    ? (string) $data['template']
                    : $invoice->template,
                'subtotal' => $computed['subtotal'],
                'discount_type' => $computed['discount_type'],
                'discount_value' => $computed['discount_value'],
                'discount_amount' => $computed['discount_amount'],
                'tax_rate' => $computed['tax_rate'],
                'tax_amount' => $computed['tax_amount'],
                'grand_total' => $computed['grand_total'],
                'balance_due' => max(0, round($computed['grand_total'] - (float) $invoice->amount_paid, 2)),
                'payment_terms' => $data['payment_terms'] ?? $invoice->payment_terms,
                'notes' => $data['notes'] ?? $invoice->notes,
                'internal_notes' => $data['internal_notes'] ?? $invoice->internal_notes,
                'sent_at' => ($status === Invoice::STATUS_SENT && ! $invoice->sent_at) ? now() : $invoice->sent_at,
                'verification_hash' => $this->generateVerificationHash($invoice->invoice_number, (int) ($customer?->id ?? 0), $computed['grand_total']),
            ]);

            $this->syncItems($invoice, $computed['items']);

            $this->audit->log($invoice, 'invoice.updated', $oldSnapshot, [
                'grand_total' => $invoice->grand_total,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date?->toDateString(),
            ]);

            return $invoice->fresh(['items', 'customer', 'order', 'payments']);
        });
    }

    public function markAsSent(Invoice $invoice): Invoice
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED || $invoice->status === Invoice::STATUS_REFUNDED) {
            throw ValidationException::withMessages(['invoice' => 'Cancelled or refunded invoices cannot be published.']);
        }

        if ($invoice->status === Invoice::STATUS_DRAFT) {
            $invoice->update([
                'status' => Invoice::STATUS_SENT,
                'sent_at' => now(),
            ]);

            $this->audit->log($invoice, 'invoice.sent', ['status' => 'draft'], ['status' => 'sent']);
        }

        return $invoice->fresh();
    }

    public function cancelInvoice(Invoice $invoice, string $reason): Invoice
    {
        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_REFUNDED], true) || (float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoices with confirmed payments cannot be cancelled. Record a refund or credit note instead.',
            ]);
        }

        $oldStatus = $invoice->status;
        $invoice->update([
            'status' => Invoice::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => trim($reason),
            'balance_due' => 0,
        ]);

        $this->audit->log($invoice, 'invoice.cancelled', ['status' => $oldStatus], [
            'status' => Invoice::STATUS_CANCELLED,
            'reason' => trim($reason),
        ]);

        return $invoice->fresh();
    }

    /**
     * Record a confirmed or pending payment against an invoice inside a DB transaction.
     * Never marks an invoice as paid without a confirmed payment record.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(Invoice $invoice, array $data, ?Admin $admin = null, ?User $employee = null): Payment
    {
        if (in_array($invoice->status, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED], true)) {
            throw ValidationException::withMessages(['payment' => 'Cannot record payment for a cancelled or refunded invoice.']);
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        $remaining = round((float) $invoice->balance_due, 2);
        if ($amount > $remaining + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount cannot exceed remaining balance due ('.number_format($remaining, 2).').',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $admin, $employee, $amount) {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $isConfirmed = $admin !== null || (bool) ($data['confirmed'] ?? false);

            $payment = Payment::create([
                'order_id' => $lockedInvoice->order_id,
                'invoice_id' => $lockedInvoice->id,
                'customer_id' => $lockedInvoice->customer_id,
                'payment_method' => (string) $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'amount' => $amount,
                'currency' => $lockedInvoice->currency,
                'payment_date' => ! empty($data['payment_date']) ? Carbon::parse($data['payment_date']) : now(),
                'payment_status' => $isConfirmed ? 'paid' : 'pending',
                'source' => 'manual',
                'verified_by_admin_id' => $admin?->id,
                'recorded_by_user_id' => $employee?->id,
                'verification_source' => $isConfirmed ? 'admin' : null,
                'verified_at' => $isConfirmed ? now() : null,
                'admin_notes' => $data['notes'] ?? null,
            ]);

            $this->recalculateInvoicePaymentStatus($lockedInvoice);

            $this->audit->log($lockedInvoice, 'payment.recorded', [], [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->payment_method,
                'reference' => $payment->transaction_reference,
                'confirmed' => $isConfirmed,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Confirm a pending payment and update the invoice status.
     */
    public function confirmPayment(Payment $payment, Admin $admin, ?string $notes = null): Payment
    {
        if (in_array($payment->payment_status, ['refunded', 'failed'], true)) {
            throw ValidationException::withMessages([
                'payment' => 'A refunded or failed payment cannot be confirmed.',
            ]);
        }

        return DB::transaction(function () use ($payment, $admin, $notes) {
            $payment->update([
                'payment_status' => 'paid',
                'verified_by_admin_id' => $admin->id,
                'verification_source' => 'admin',
                'verified_at' => now(),
                'admin_notes' => $notes ?? $payment->admin_notes,
            ]);

            if ($payment->invoice) {
                $this->recalculateInvoicePaymentStatus($payment->invoice);
            }

            $this->audit->log($payment, 'payment.verified', ['payment_status' => 'pending'], [
                'payment_status' => 'paid',
                'verified_by_admin_id' => $admin->id,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Record a refund against a paid/partially paid invoice inside a DB transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordRefund(Invoice $invoice, array $data, Admin $admin): Refund
    {
        $amount = round((float) $data['amount'], 2);
        $netPaid = round((float) $invoice->amount_paid - (float) $invoice->amount_refunded, 2);

        if ($amount <= 0 || $amount > $netPaid + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount must be between 0.01 and the net paid amount ('.number_format($netPaid, 2).').',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $admin, $amount) {
            $lockedInvoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $refund = Refund::create([
                'order_id' => $lockedInvoice->order_id,
                'invoice_id' => $lockedInvoice->id,
                'payment_id' => ! empty($data['payment_id']) ? (int) $data['payment_id'] : null,
                'customer_id' => $lockedInvoice->customer_id,
                'admin_id' => $admin->id,
                'amount' => $amount,
                'type' => $amount >= (float) $lockedInvoice->amount_paid ? 'full' : 'partial',
                'status' => $data['status'] ?? 'processed',
                'gateway_reference' => $data['gateway_reference'] ?? null,
                'processed_at' => now(),
                'reason' => (string) $data['reason'],
            ]);

            $totalRefunded = round((float) $lockedInvoice->refunds()->sum('amount'), 2);
            $newStatus = $totalRefunded >= (float) $lockedInvoice->amount_paid
                ? Invoice::STATUS_REFUNDED
                : $lockedInvoice->status;

            $lockedInvoice->update([
                'amount_refunded' => $totalRefunded,
                'status' => $newStatus,
            ]);

            $this->audit->log($lockedInvoice, 'refund.recorded', [], [
                'refund_id' => $refund->id,
                'amount' => $refund->amount,
                'reason' => $refund->reason,
                'invoice_status' => $newStatus,
            ]);

            return $refund;
        });
    }

    /**
     * Create an immutable Credit Note / Debit Note / Adjustment for a finalized invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAdjustment(Invoice $invoice, array $data, Admin $admin): InvoiceAdjustment
    {
        return DB::transaction(function () use ($invoice, $data, $admin) {
            $adjustment = InvoiceAdjustment::create([
                'adjustment_number' => $this->numbers->nextAdjustmentNumber(),
                'invoice_id' => $invoice->id,
                'type' => (string) ($data['type'] ?? 'credit_note'),
                'amount' => round((float) $data['amount'], 2),
                'reason' => (string) $data['reason'],
                'created_by_admin_id' => $admin->id,
            ]);

            $this->audit->log($invoice, 'invoice.adjustment_created', [], [
                'adjustment_number' => $adjustment->adjustment_number,
                'type' => $adjustment->type,
                'amount' => $adjustment->amount,
                'reason' => $adjustment->reason,
            ]);

            return $adjustment;
        });
    }

    /**
     * Recompute amount_paid, balance_due, and invoice status strictly from confirmed payments.
     */
    public function recalculateInvoicePaymentStatus(Invoice $invoice): Invoice
    {
        $confirmedPaid = round((float) $invoice->payments()
            ->where('payment_status', 'paid')
            ->where(function ($q) {
                $q->whereNotNull('verified_by_admin_id')
                    ->orWhere('verification_source', 'gateway');
            })
            ->sum('amount'), 2);

        $grandTotal = round((float) $invoice->grand_total, 2);
        $balanceDue = max(0, round($grandTotal - $confirmedPaid, 2));

        $status = $invoice->status;
        if ((float) $invoice->amount_refunded >= $confirmedPaid && $confirmedPaid > 0) {
            $status = Invoice::STATUS_REFUNDED;
        } elseif ($confirmedPaid >= $grandTotal && $grandTotal > 0) {
            $status = Invoice::STATUS_PAID;
        } elseif ($confirmedPaid > 0) {
            $status = Invoice::STATUS_PARTIALLY_PAID;
        } elseif ($invoice->due_date && $invoice->due_date->isBefore(now()->startOfDay()) && $invoice->status !== Invoice::STATUS_DRAFT) {
            $status = Invoice::STATUS_OVERDUE;
        }

        $invoice->update([
            'amount_paid' => $confirmedPaid,
            'balance_due' => $balanceDue,
            'status' => $status,
            'paid_at' => $status === Invoice::STATUS_PAID ? ($invoice->paid_at ?? now()) : null,
        ]);

        // Keep linked SalesOrder & Customer totals in sync if linked
        if ($invoice->order_id && $invoice->order) {
            $invoice->order->update([
                'amount_received' => $confirmedPaid,
                'payment_status' => match ($status) {
                    Invoice::STATUS_PAID => 'paid',
                    Invoice::STATUS_PARTIALLY_PAID => 'partially_paid',
                    Invoice::STATUS_REFUNDED => 'refunded',
                    default => $invoice->order->payment_status,
                },
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * Mark sent/partially_paid invoices past due_date with balance_due > 0 as overdue.
     */
    public function syncOverdueInvoices(): int
    {
        return Invoice::query()
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIALLY_PAID])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->where('balance_due', '>', 0)
            ->update(['status' => Invoice::STATUS_OVERDUE]);
    }

    /**
     * Generate a recurring renewal invoice for a subscription, preventing duplicates for the same cycle.
     */
    public function generateRecurringInvoice(Subscription $subscription, ?Admin $admin = null): ?Invoice
    {
        if (! $subscription->customer_id) {
            throw ValidationException::withMessages(['subscription' => 'Subscription has no linked customer.']);
        }

        $cycle = $this->normalizeCycle($subscription->billing_cycle ?? 'monthly');
        $cycleStart = ($subscription->renewal_date ?? $subscription->expires_at ?? now())->copy()->startOfDay();
        $cycleEnd = $this->cycleEndFor($cycle, $cycleStart);

        // Prevent duplicate invoice for the exact same subscription + billing cycle
        $existing = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('billing_cycle_start', $cycleStart->toDateString())
            ->whereDate('billing_cycle_end', $cycleEnd->toDateString())
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->first();

        if ($existing) {
            return null;
        }

        $unitPrice = (float) ($subscription->price_snapshot ?? $subscription->product?->price ?? $subscription->amount_paid ?? 0);
        $productName = $subscription->product?->name ?? $subscription->plan_snapshot ?? 'Subscription Renewal';

        $invoice = $this->createInvoice([
            'customer_id' => $subscription->customer_id,
            'order_id' => $subscription->order_id,
            'subscription_id' => $subscription->id,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => now()->toDateString(),
            'due_date' => $cycleStart->copy()->addDays(7)->toDateString(),
            'billing_cycle' => $cycle,
            'billing_cycle_start' => $cycleStart->toDateString(),
            'billing_cycle_end' => $cycleEnd->toDateString(),
            'notes' => sprintf(
                'Recurring %s renewal (%s to %s)',
                Invoice::BILLING_CYCLES[$cycle] ?? ucfirst($cycle),
                $cycleStart->format('M d, Y'),
                $cycleEnd->format('M d, Y')
            ),
            'items' => [[
                'product_id' => $subscription->product_id,
                'description' => $productName.' — '.(Invoice::BILLING_CYCLES[$cycle] ?? ucfirst($cycle)).' Renewal',
                'duration' => $subscription->duration_snapshot ?? Invoice::BILLING_CYCLES[$cycle] ?? $cycle,
                'quantity' => 1,
                'unit_price' => $unitPrice,
            ]],
        ], $admin);

        // Advance subscription's next renewal_date
        $subscription->update([
            'renewal_date' => $cycleEnd->copy()->addDay(),
            'last_renewal_reminder_at' => now(),
        ]);

        return $invoice;
    }

    /**
     * Server-side computation of line items, subtotal, discount, tax, and grand total.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<string, mixed>
     */
    protected function computeTotals(array $rawItems, string $discountType, float $discountValue, float $taxRate): array
    {
        if (empty($rawItems)) {
            throw ValidationException::withMessages(['items' => 'At least one product or service item is required.']);
        }

        $items = [];
        $subtotal = 0.0;

        foreach ($rawItems as $idx => $row) {
            $qty = max(0.01, round((float) ($row['quantity'] ?? 1), 2));
            $unitPrice = max(0.0, round((float) ($row['unit_price'] ?? 0), 2));
            $product = ! empty($row['product_id']) ? Product::find((int) $row['product_id']) : null;
            $description = trim((string) ($row['description'] ?? $product?->name ?? 'Service'));
            if ($description === '') {
                $description = $product?->name ?? 'Service Item';
            }

            $lineTotal = round($qty * $unitPrice, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'product_id' => $product?->id,
                'description' => $description,
                'duration' => $row['duration'] ?? $product?->duration,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => 0,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => $lineTotal,
                'sort_order' => $idx,
            ];
        }

        $subtotal = round($subtotal, 2);
        $discountType = $discountType === 'percent' ? 'percent' : 'fixed';
        $discountValue = max(0.0, round($discountValue, 2));

        $discountAmount = $discountType === 'percent'
            ? round($subtotal * min(100.0, $discountValue) / 100, 2)
            : min($subtotal, $discountValue);

        $taxableBase = max(0.0, round($subtotal - $discountAmount, 2));
        $taxRate = max(0.0, min(100.0, round($taxRate, 2)));
        $taxAmount = round($taxableBase * $taxRate / 100, 2);
        $grandTotal = round($taxableBase + $taxAmount, 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();
        foreach ($items as $item) {
            InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
        }
    }

    protected function generateVerificationHash(string $invoiceNumber, int $customerId, float $grandTotal): string
    {
        return hash('sha256', implode('|', [
            $invoiceNumber,
            $customerId,
            number_format($grandTotal, 2, '.', ''),
            (string) config('app.key'),
        ]));
    }

    protected function normalizeCycle(string $cycle): string
    {
        return match (strtolower($cycle)) {
            'monthly', '30d', '1m' => 'monthly',
            'quarterly', '3m', '90d' => 'quarterly',
            'half_yearly', 'half-yearly', '6m', '180d' => 'half_yearly',
            default => 'yearly',
        };
    }

    protected function cycleEndFor(string $cycle, Carbon $start): Carbon
    {
        return match ($cycle) {
            'monthly' => $start->copy()->addMonth()->subDay(),
            'quarterly' => $start->copy()->addMonths(3)->subDay(),
            'half_yearly' => $start->copy()->addMonths(6)->subDay(),
            default => $start->copy()->addYear()->subDay(),
        };
    }
}
