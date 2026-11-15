<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Notifications\PaymentVerifiedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The payment-verification pipeline.
 *
 * The only thing that can activate a subscription is a *verified*
 * payment - either one an admin explicitly verified/rejected through the
 * manual flow, or one confirmed by a signed gateway webhook. Unverified
 * screenshots, client-side status claims and pending/failed rows never
 * activate anything.
 *
 * The pipeline is:
 *   1. verify/reject a payment row (manual, admin) or mark it gateway-verified
 *   2. link the payment to the CRM customer and recompute the order's verified total
 *   3. activate (or refuse) the subscription per the configured policy
 *
 * Activation is transaction-safe and idempotent: repeated verify calls
 * and repeated webhooks converge on the same single subscription.
 */
class PaymentService
{
    public function __construct(
        protected CrmCustomerService $crm,
        protected SubscriptionActivator $activator,
    ) {
    }

    /**
     * Manually verify a payment. Idempotent: re-verifying an already
     * verified row only refreshes the notes - it never re-mints a
     * subscription (activation is itself idempotent, keyed on order_id).
     */
    public function verify(Payment $payment, int $adminId, ?string $notes = null): Payment
    {
        if ($payment->payment_status === 'refunded') {
            throw ValidationException::withMessages([
                'payment' => 'A refunded payment cannot be verified.',
            ]);
        }

        if ($payment->isVerified()) {
            if ($notes !== null) {
                $payment->forceFill(['admin_notes' => $notes])->save();
            }

            return $payment;
        }

        return DB::transaction(function () use ($payment, $adminId, $notes) {
            $payment->forceFill([
                'payment_status' => 'paid',
                'verified_by_admin_id' => $adminId,
                'verification_source' => 'admin',
                'verified_at' => now(),
                'admin_notes' => $notes,
            ])->save();

            $this->postVerify($payment, $adminId);

            return $payment->fresh();
        });
    }

    /**
     * Manually reject a payment (e.g. a failed or invalid transfer). A
     * rejected row must never activate a subscription - and if one was
     * earlier activated off a different verified row, that activation is
     * suspended, not silently kept.
     */
    public function reject(Payment $payment, int $adminId, ?string $notes = null): Payment
    {
        if ($payment->payment_status === 'refunded') {
            throw ValidationException::withMessages([
                'payment' => 'A refunded payment is already closed.',
            ]);
        }

        return DB::transaction(function () use ($payment, $adminId, $notes) {
            $payment->forceFill([
                'payment_status' => 'failed',
                'verified_by_admin_id' => $adminId,
                'verification_source' => 'admin',
                'verified_at' => now(),
                'admin_notes' => $notes ?? 'Rejected by admin',
            ])->save();

            $this->postReject($payment);

            return $payment->fresh();
        });
    }

    /**
     * Record a payment confirmed by a signed gateway webhook. Idempotent:
     * the gateway event id is checked against the webhook ledger before
     * the row is touched (see GatewayService), and the payment row itself
     * is keyed on (order, source, reference) so a resend can't mint a
     * second row.
     */
    public function recordGatewayPayment(
        SalesOrder $order,
        string $eventType,
        ?string $transactionReference = null,
        ?string $amount = null,
        ?string $currency = null,
        ?Customer $customer = null,
        ?string $eventReference = null,
    ): Payment {
        $status = $this->statusFor($eventType);
        $verified = in_array($status, ['paid', 'partially_paid'], true);

        $ref = $transactionReference ?: $eventReference;

        return DB::transaction(function () use (
            $order, $ref, $amount, $currency, $customer, $status, $verified
        ) {
            $payment = Payment::firstOrNew([
                'order_id' => $order->id,
                'source' => 'gateway',
                'transaction_reference' => $ref,
            ])->forceFill([
                'payment_method' => 'gateway',
                'amount' => $amount ?? $order->closing_value,
                'currency' => $currency ?? \App\Models\Setting::currency(),
                'payment_date' => now(),
                'payment_status' => $status,
                'customer_id' => $customer?->id,
                'verification_source' => $verified ? 'gateway' : null,
                'verified_at' => $verified ? now() : null,
                'verified_by_admin_id' => null,
            ]);

            $payment->save();

            $this->postVerify($payment, null);

            return $payment;
        });
    }

    protected function postVerify(Payment $payment, ?int $adminId): void
    {
        $order = $payment->order()->with(['team', 'assignedEmployee'])->first();

        if (! $order) {
            return;
        }

        $customer = null;

        // The CRM upsert happens only for verified purchases - pending or
        // failed gateway rows never create or promote a customer.
        if ($payment->isVerified()) {
            try {
                $customer = $this->crm->upsertForPurchase(
                    $order,
                    \App\Support\Money::toCents($payment->amount),
                    $adminId,
                );

                // Link the verified payment to the CRM customer it settled,
                // so the customer's purchase and payment history stay
                // traversable from both sides.
                if ($customer->id !== $payment->customer_id) {
                    $payment->forceFill(['customer_id' => $customer->id])->save();
                }
            } catch (Throwable) {
                // An unresolvable customer never blocks activation; the
                // admin can resolve it manually.
                $customer = null;
            }
        }

        $this->syncOrder($order);
        $this->activator->maybeActivate($order, $adminId, $customer);

        if ($payment->isVerified()) {
            $this->notify($order, new PaymentVerifiedNotification($payment));

            try {
                app(\App\Services\NotificationHubService::class)->notifyPaymentVerified($payment);
            } catch (\Throwable) {
            }

            // Payment emails fire ONLY after the payment is actually
            // verified (Section 5). All non-blocking and admin-toggleable.
            $verifiedContext = [
                'order_id'         => $order->id,
                'customer_id'      => $order->customer_id,
                'customer_email'   => $order->customer_email,
                'customer_name'    => $order->customer_name,
                'team_name'        => $order->team?->team_name,
                'order_number'     => $order->order_number,
                'product_name'     => $order->product_name_snapshot ?? '—',
                'closing_value'    => number_format((float) $order->closing_value, 2),
                'payment_method'   => $payment->payment_method,
                'payment_status'   => 'verified',
                'amount_received'  => number_format((float) $payment->amount, 2),
            ];

            try {
                app(\App\Services\EmailService::class)->dispatch('payment.verified', $verifiedContext + [
                    'to_email' => $order->customer_email,
                    'order'    => $order,
                ]);
            } catch (\Throwable) {
                // Email failure must never break the payment pipeline.
            }

            try {
                app(\App\Services\EmailService::class)->dispatch('payment.receipt', $verifiedContext + [
                    'to_email' => $order->customer_email,
                    'order'    => $order,
                ]);
            } catch (\Throwable) {
            }

            try {
                app(\App\Services\EmailService::class)->dispatch('team.payment_status', $verifiedContext + [
                    'team'   => $order->team,
                    'order'  => $order,
                    'status' => 'verified',
                ]);
            } catch (\Throwable) {
            }

            try {
                if ($order->assigned_employee_id) {
                    app(\App\Services\EmailService::class)->dispatch('employee.payment_update', $verifiedContext + [
                        'order'    => $order,
                        'employee' => $order->team ? \App\Models\User::find($order->assigned_employee_id) : null,
                    ]);
                }
            } catch (\Throwable) {
            }
        }
    }

    protected function postReject(Payment $payment): void
    {
        $order = $payment->order()->first();

        if (! $order) {
            return;
        }

        $this->syncOrder($order);

        // A rejection invalidates any activation that no longer has a
        // sufficient verified total.
        $subscription = $order->subscription;

        if ($subscription && $subscription->status === 'active' && ! $this->activator->policySatisfied($order)) {
            $subscription->forceFill(['status' => 'suspended'])->save();
            $order->forceFill(['order_status' => 'suspended'])->save();
        }

        // Failed-payment alerts (non-blocking, admin-toggleable).
        $failedContext = [
            'order_id'       => $order->id,
            'order_number'   => $order->order_number,
            'customer_name'  => $order->customer_name,
            'customer_email' => $order->customer_email,
            'team_name'      => $order->team?->team_name,
            'product_name'   => $order->product_name_snapshot ?? '—',
            'closing_value'  => number_format((float) $order->closing_value, 2),
            'payment_method' => $payment->payment_method,
        ];

        try {
            app(\App\Services\EmailService::class)->dispatch('admin.payment.failed', $failedContext);
        } catch (\Throwable) {
        }

        try {
            app(\App\Services\EmailService::class)->dispatch('team.payment_status', $failedContext + [
                'team'  => $order->team,
                'order' => $order,
                'status' => 'failed',
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * Keep the order's collected amount and payment status consistent with
     * its verified payments.
     */
    protected function syncOrder(SalesOrder $order): void
    {
        $order->amount_received = $order->verifiedPaymentTotal();
        $order->payment_status = $this->orderPaymentStatusFor($order);
        $order->save();

        SalesAnalyticsService::invalidateCache();
    }

    protected function orderPaymentStatusFor(SalesOrder $order): string
    {
        $dueCents = \App\Support\Money::toCents($order->amountDue());
        $verifiedCents = \App\Support\Money::toCents($order->verifiedPaymentTotal());

        if ($dueCents > 0 && $verifiedCents >= $dueCents) {
            return 'paid';
        }

        if ($verifiedCents > 0) {
            return 'partially_paid';
        }

        if ($order->payments()->where('payment_status', 'failed')->exists()) {
            return 'failed';
        }

        return 'pending';
    }

    /**
     * Map a gateway event type onto a LeadX payment status.
     */
    protected function statusFor(string $eventType): string
    {
        return match (strtolower($eventType)) {
            'payment.captured', 'payment.completed', 'paid', 'success', 'succeeded' => 'paid',
            'payment.partial', 'partially_paid', 'partial' => 'partially_paid',
            'payment.failed', 'failed', 'failure' => 'failed',
            'payment.refunded', 'refunded', 'refund' => 'refunded',
            default => 'pending',
        };
    }

    protected function notify(SalesOrder $order, PaymentVerifiedNotification $notification): void
    {
        $order->team?->notifyNow($notification);
        $order->assignedEmployee?->notifyNow($notification);
    }
}