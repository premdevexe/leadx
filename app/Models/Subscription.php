<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'pending',
        'active',
        'expired',
        'suspended',
        'cancelled',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'team_id',
        'customer_id',
        'product_id',
        'plan_id',
        'billing_cycle',
        'plan_snapshot',
        'price_snapshot',
        'amount_paid',
        'renewal_date',
        'last_renewal_reminder_at',
        'last_expiry_reminder_at',
        'status',
        'started_at',
        'expires_at',
        'duration_snapshot',
        'google_event_id',
        'calendar_sync_status',
        'calendar_sync_message',
        'calendar_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'renewal_date' => 'datetime',
            'price_snapshot' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'last_renewal_reminder_at' => 'datetime',
            'last_expiry_reminder_at' => 'datetime',
            'calendar_synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id', 'id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * The order's delivered customer API key (encrypted), if any.
     */
    public function apiCredential(): HasOne
    {
        return $this->hasOne(OrderApiCredential::class, 'order_id', 'order_id');
    }

    /**
     * Delivery log rows for this subscription's expiry reminders.
     */
    public function expiryNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionExpiryNotification::class);
    }

    /**
     * Is this subscription active now (not lapsed and not cancelled)?
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * The activation date (a date-only alias of started_at) used in the
     * WhatsApp share payload. Null until the subscription is activated.
     */
    public function activationDate(): ?\Illuminate\Support\Carbon
    {
        return $this->started_at?->startOfDay();
    }

    /**
     * The expiry date (a date-only alias of expires_at) used in the WhatsApp
     * share payload.
     */
    public function expiryDate(): ?\Illuminate\Support\Carbon
    {
        return $this->expires_at?->startOfDay();
    }

    /**
     * Google Calendar: does a synced remote event already exist for this
     * subscription? A stored event id makes a retried sync idempotent.
     */
    public function hasCalendarEvent(): bool
    {
        return filled($this->google_event_id);
    }

    /**
     * Google Calendar: is the sync in a pending state (no connected calendar
     * yet, so a retry is expected to pick it up later)?
     */
    public function calendarSyncPending(): bool
    {
        return in_array($this->calendar_sync_status, ['pending', 'not_connected'], true)
            && ! $this->hasCalendarEvent();
    }

    /**
     * Upcoming expiry: active and expiring within the configured window.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeRenewalDue(Builder $query, int $days = 7): Builder
    {
        return $query
            ->whereIn('status', ['active', 'expired'])
            ->whereNotNull('renewal_date')
            ->where('renewal_date', '<=', now()->addDays($days));
    }
}
