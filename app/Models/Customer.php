<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use HasFactory, Notifiable;

    public const STATUSES = [
        'enquiry',
        'paying',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'normalized_phone',
        'normalized_email',
        'status',
        'team_id',
        'created_by_admin_id',
        'purchases_count',
        'total_purchased',
        'last_purchase_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'team_id' => 'integer',
            'purchases_count' => 'integer',
            'total_purchased' => 'decimal:2',
            'last_purchase_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Subscriptions that resolve to this CRM customer (by id match).
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'customer_id');
    }

    /**
     * Payments attributed to this customer.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopePaying(Builder $query): Builder
    {
        return $query->where('status', 'paying');
    }

    /**
     * CRM customers with at least one *verified* purchase. This is the set
     * a follow-up may target (Phase 08, Module 1, rule 1): an enquiry that
     * has never purchased is not a valid follow-up customer. A row is
     * promoted to `paying` only through the verified-purchase pipeline, so
     * status `paying` is the authoritative "has a verified purchase" flag.
     */
    public function scopeVerifiedPurchases(Builder $query): Builder
    {
        return $query->where('status', 'paying')
            ->where('purchases_count', '>', 0);
    }
}
