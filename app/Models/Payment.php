<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * Recognized payment methods.
     *
     * @var list<string>
     */
    public const METHODS = [
        'upi',
        'bank_transfer',
        'gateway',
        'other',
    ];

    /**
     * Recognized payment statuses (mirrors the order-level statuses).
     *
     * @var list<string>
     */
    public const STATUSES = [
        'pending',
        'paid',
        'partially_paid',
        'failed',
        'refunded',
    ];

    /**
     * Where the payment record came from.
     *
     * @var list<string>
     */
    public const SOURCES = [
        'manual',
        'gateway',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'customer_id',
        'payment_method',
        'transaction_reference',
        'amount',
        'currency',
        'payment_date',
        'payment_status',
        'source',
        'verified_by_admin_id',
        'verification_source',
        'verified_at',
        'admin_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }

    /**
     * A payment counts as verified only when it was explicitly verified —
     * by an admin (manual) or by a signed gateway webhook. An unverified
     * screenshot or client-side status is never sufficient.
     */
    public function isVerified(): bool
    {
        if (in_array($this->payment_status, ['failed', 'refunded'], true)) {
            return false;
        }

        return $this->verified_by_admin_id !== null || $this->verification_source === 'gateway';
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('verified_by_admin_id');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_by_admin_id');
    }

    public function scopeForOrder(Builder $query, int|SalesOrder $order): Builder
    {
        return $query->where('order_id', $order instanceof SalesOrder ? $order->id : $order);
    }
}
