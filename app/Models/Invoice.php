<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SENT => 'Sent',
        self::STATUS_PARTIALLY_PAID => 'Partially Paid',
        self::STATUS_PAID => 'Paid',
        self::STATUS_OVERDUE => 'Overdue',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_REFUNDED => 'Refunded',
    ];

    public const BILLING_CYCLES = [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'half_yearly' => 'Half-Yearly',
        'yearly' => 'Annual',
    ];

    public const TEMPLATES = [
        'classic_navy' => 'Classic Navy & Green (Enterprise)',
        'modern_emerald' => 'Modern Executive Banner',
        'minimal_slate' => 'Minimal Clean Print',
    ];

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'order_id',
        'subscription_id',
        'team_id',
        'created_by_admin_id',
        'created_by_user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'template',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'amount_paid',
        'amount_refunded',
        'balance_due',
        'payment_terms',
        'notes',
        'internal_notes',
        'billing_cycle',
        'billing_cycle_start',
        'billing_cycle_end',
        'sent_at',
        'paid_at',
        'cancelled_at',
        'cancellation_reason',
        'verification_hash',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'billing_cycle_start' => 'date',
            'billing_cycle_end' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id')->latest('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'invoice_id')->latest('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InvoiceAdjustment::class)->latest('id');
    }

    /**
     * Paid, cancelled, and refunded financial records cannot be mutated directly.
     */
    public function isFinanciallyLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
            self::STATUS_REFUNDED,
        ], true);
    }

    public function isOverdue(): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        if (in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PAID, self::STATUS_CANCELLED, self::STATUS_REFUNDED], true)) {
            return false;
        }

        return $this->due_date->isBefore(now()->startOfDay()) && (float) $this->balance_due > 0;
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SENT,
            self::STATUS_PARTIALLY_PAID,
            self::STATUS_OVERDUE,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', self::STATUS_OVERDUE)
                ->orWhere(function (Builder $sub) {
                    $sub->whereIn('status', [self::STATUS_SENT, self::STATUS_PARTIALLY_PAID])
                        ->whereNotNull('due_date')
                        ->where('due_date', '<', now()->startOfDay())
                        ->where('balance_due', '>', 0);
                });
        });
    }
}
