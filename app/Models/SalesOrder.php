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
        'subscription_plan',
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
            'discount_amount' => 'decimal:2',
            'closing_value' => 'decimal:2',
            'amount_received' => 'decimal:2',
        ];
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
     * Total amount that has been *verified* by an admin (excludes pending,
     * failed and refunded rows, and payments older than the configured
     * staleness window). Used for activation decisions and revenue
     * analytics — uncollected amounts are never counted.
     */
    public function verifiedPaymentTotal(): string
    {
        $staleAfterDays = (int) config('leadx.payments.stale_after_days', 90);

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
}
