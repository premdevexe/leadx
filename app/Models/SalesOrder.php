<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesOrder extends Model
{
    use HasFactory;

    /**
     * Recognized order lifecycle states.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'pending',
        'confirmed',
        'active',
        'expired',
        'cancelled',
        'suspended',
    ];

    /**
     * Recognized payment states.
     *
     * @var list<string>
     */
    public const PAYMENT_STATUSES = [
        'pending',
        'partially_paid',
        'paid',
        'failed',
        'refunded',
        'partially_refunded',
    ];

    /**
     * Supported subscription durations, as [code => human label].
     *
     * @var array<string, string>
     */
    public const DURATIONS = [
        '3d' => '3 days',
        '7d' => '7 days',
        '15d' => '15 days',
        '30d' => '30 days',
        '3m' => '3 months',
        '6m' => '6 months',
        '1y' => '1 year',
    ];

    /**
     * Compute the subscription expiry date for a given duration code.
     */
    public static function expiryFor(string $duration, ?Carbon $from = null): Carbon
    {
        $from ??= now();

        return match ($duration) {
            '3d' => $from->copy()->addDays(3),
            '7d' => $from->copy()->addDays(7),
            '15d' => $from->copy()->addDays(15),
            '30d' => $from->copy()->addDays(30),
            '3m' => $from->copy()->addMonths(3),
            '6m' => $from->copy()->addMonths(6),
            default => $from->copy()->addYear(), // '1y' + unknown values
        };
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'team_id',
        'team_code_snapshot',
        'assigned_employee_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'product_id',
        'product_name',
        'product_name_snapshot',
        'product_price_snapshot',
        'product_duration_snapshot',
        'minimum_closing_price_snapshot',
        'api_key_required_snapshot',
        'closing_value_status',
        'closing_approval_requested_at',
        'closing_approval_actioned_at',
        'closing_approval_actioned_by',
        'closing_approval_notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'duration',
        'product_value',
        'discount_amount',
        'closing_value',
        'amount_received',
        'payment_status',
        'payment_method',
        'order_status',
        'legacy_flag',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_value' => 'decimal:2',
            'product_price_snapshot' => 'decimal:2',
            'minimum_closing_price_snapshot' => 'decimal:2',
            'api_key_required_snapshot' => 'boolean',
            'discount_amount' => 'decimal:2',
            'closing_value' => 'decimal:2',
            'amount_received' => 'decimal:2',
            'closing_approval_requested_at' => 'datetime',
            'closing_approval_actioned_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function closingApprovalAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'closing_approval_actioned_by');
    }

    public function cancelledByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'cancelled_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function editHistories(): HasMany
    {
        return $this->hasMany(OrderEditHistory::class, 'order_id')->latest('id');
    }

    public function closingValueHistories(): HasMany
    {
        return $this->hasMany(OrderClosingValueHistory::class, 'order_id')->latest('id');
    }

    public function isClosingApprovalPending(): bool
    {
        return $this->closing_value_status === 'Requested';
    }

    public function isClosingApprovalApproved(): bool
    {
        return $this->closing_value_status === 'Accepted';
    }

    public function isClosingApprovalRejected(): bool
    {
        return $this->closing_value_status === 'Rejected';
    }

    public function totalRefunded(): float
    {
        return (float) $this->refunds()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'processed'))
            ->sum('amount');
    }

    public function remainingRefundable(): float
    {
        $paid = (float) $this->amount_received > 0 ? (float) $this->amount_received : (float) $this->verifiedPaymentTotal();

        return max(0.0, round($paid - $this->totalRefunded(), 2));
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\SalesAnalyticsService::invalidateCache());
        static::deleted(fn () => \App\Services\SalesAnalyticsService::invalidateCache());
    }

    /**
     * The team the order was issued for, snapshotted at creation time.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The catalog product this order was created from, if any.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The employee the order is attributed to.
     */
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    /**
     * The subscription tied to this order, if one exists.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'order_id', 'id');
    }

    /**
     * All payment receipts against this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id', 'id');
    }

    /**
     * The customer's delivered API key for this order (encrypted at rest).
     * The key itself is excluded from serialization — only a masked
     * indicator is ever surfaced, and only to authorized actors.
     */
    public function apiCredential(): HasOne
    {
        return $this->hasOne(OrderApiCredential::class, 'order_id', 'id');
    }

    /**
     * The customer's API key, when the caller is authorized to read it.
     * Returns the decrypted plaintext only; it is never logged by callers.
     */
    public function apiKey(): ?string
    {
        return $this->apiCredential?->decryptedKey();
    }

    /**
     * Total amount that has been *verified* by an admin (excludes pending,
     * failed and refunded rows, and payments older than the configured
     * staleness window). Used for activation decisions and revenue
     * analytics — uncollected amounts are never counted.
     */
    public function verifiedPaymentTotal(): string
    {
        $staleAfterDays = (int) config('leadx.payments.stale_after_days', 90);

        if ($this->relationLoaded('payments')) {
            $cutoff = now()->subDays($staleAfterDays);
            $totalCents = 0;
            foreach ($this->payments as $payment) {
                $isVerified = $payment->verified_by_admin_id !== null || $payment->verification_source === 'gateway';
                if (! $isVerified) {
                    continue;
                }
                if (in_array($payment->payment_status, ['failed', 'refunded'], true)) {
                    continue;
                }
                if ($payment->verified_at !== null && $payment->verified_at < $cutoff) {
                    continue;
                }
                $totalCents += \App\Support\Money::toCents($payment->amount);
            }

            return \App\Support\Money::format($totalCents);
        }

        return (string) $this->payments()
            ->where(fn ($q) => $q->whereNotNull('verified_by_admin_id')
                ->orWhere('verification_source', 'gateway'))
            ->whereNotIn('payment_status', ['failed', 'refunded'])
            ->where(fn ($q) => $q->whereNull('verified_at')->orWhere(
                'verified_at',
                '>=',
                now()->subDays($staleAfterDays)
            ))
            ->sum('amount');
    }

    /**
     * The amount that must be verified before this order can activate a
     * subscription: the closing value (or product value when closing is
     * not set).
     */
    public function amountDue(): string
    {
        $due = (float) ($this->closing_value ?: 0) > 0 ? (float) $this->closing_value : (float) $this->product_value;

        return number_format($due, 2, '.', '');
    }

    /**
     * An order may only share via WhatsApp when its subscription is active.
     */
    public function isShareEligible(): bool
    {
        return $this->order_status === 'active'
            && $this->subscription?->status === 'active';
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeShareEligible(Builder $query): Builder
    {
        return $query
            ->where('order_status', 'active')
            ->whereHas('subscription', function (Builder $q): void {
                $q->where('status', 'active');
            });
    }

    /**
     * Generate 1-click Google Calendar Event creation URL for browser popup.
     * Pre-configured with customer details and 36-hour expiry notification reminder.
     */
    public function googleCalendarUrl(): string
    {
        $customerName = $this->customer_name ?? $this->customer?->name ?? 'Customer';
        $orderNumber = $this->order_number ?? ('#' . $this->id);
        $productName = $this->product_name_snapshot ?? $this->product_name ?? 'Subscription';

        $expiryDate = $this->subscription?->expires_at;
        if (! $expiryDate) {
            $days = match ($this->product_duration_snapshot ?? $this->duration) {
                '3d' => 3,
                '7d' => 7,
                '15d' => 15,
                '30d' => 30,
                '3m' => 90,
                '6m' => 180,
                '1y' => 365,
                default => 30,
            };
            $expiryDate = ($this->created_at ?? now())->copy()->addDays($days);
        }

        $start = $expiryDate->copy()->setTime(9, 0, 0);
        $end = $start->copy()->addHour();
        $dates = $start->copy()->utc()->format('Ymd\THis\Z') . '/' . $end->copy()->utc()->format('Ymd\THis\Z');

        $lines = [
            'LeadX Subscription Expiry Reminder',
            'Customer: ' . $customerName,
            'Phone: ' . ($this->customer_phone ?? '—'),
            'Order ID: ' . $orderNumber,
            'Product: ' . $productName,
            'Expiry Date: ' . $expiryDate->toDateString(),
            'Lead Notification: 36 hours before expiry.',
        ];

        $params = [
            'action' => 'TEMPLATE',
            'text' => 'LeadX Expiry: ' . $customerName . ' (' . $orderNumber . ')',
            'dates' => $dates,
            'details' => implode("\n", $lines),
            'location' => 'LeadX Platform',
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }
}
