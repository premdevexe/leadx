<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Delivery log for the team-only "expires tomorrow" reminder.
 *
 * One row per (subscription, expiry cycle). The unique (subscription_id,
 * expiry_at) index is the once-per-cycle guarantee: a renewal produces a new
 * expires_at, which is a distinct cycle key, so its notice may fire again;
 * a repeated scheduler run on the same cycle hits the constraint and is
 * skipped rather than double-notified.
 *
 * `recipient_user_ids` (JSON) records exactly who was notified so the
 * team-members-only rule is auditable. The key is never stored here.
 */
class SubscriptionExpiryNotification extends Model
{
    /**
     * Lifecycle states for a delivery attempt.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'sent',
        'skipped_no_recipients',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'expiry_at',
        'status',
        'recipient_count',
        'recipient_user_ids',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiry_at' => 'datetime',
            'sent_at' => 'datetime',
            'recipient_count' => 'integer',
            'recipient_user_ids' => 'array',
        ];
    }

    /**
     * The subscription this notice was for.
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Has the "expires tomorrow" notice already fired for this expiry
     * cycle? The scheduler consults this before notifying so a second run
     * on the same day is a no-op.
     */
    public static function alreadySentForCycle(Subscription $subscription): bool
    {
        return static::where('subscription_id', $subscription->id)
            ->where('expiry_at', $subscription->expires_at?->toDateTimeString())
            ->exists();
    }

    /**
     * Record a delivery (or a no-recipient skip) for this cycle. Returns
     * the created log row. Callers wrap this so the unique constraint is the
     * authoritative guard; a duplicate on the same cycle throws and is
     * treated as "already sent".
     *
     * @param list<int> $recipientUserIds
     */
    public static function record(Subscription $subscription, string $status, array $recipientUserIds, \Illuminate\Support\Carbon $sentAt): self
    {
        return static::create([
            'subscription_id' => $subscription->id,
            'expiry_at' => $subscription->expires_at,
            'status' => $status,
            'recipient_count' => count($recipientUserIds),
            'recipient_user_ids' => $recipientUserIds,
            'sent_at' => $sentAt,
        ]);
    }
}
