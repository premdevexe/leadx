<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A public, unauthenticated share link for a single order.
 *
 * The {@see $token} is the only secret protecting the public page — it plays
 * the same role as a password-reset link: 40 random characters, so it is
 * unguessable and safe to embed in a URL, while staying well within the
 * column's 64-char width.
 *
 * Lifecycle:
 * - {@see createForOrder()} mints a fresh link for an order.
 * - {@see isLive()} is the single gate the public route checks: not revoked
 *   and not (yet) expired.
 * - {@see revoke()} / reactivation keep the audit row but disable access.
 * - When a logged-in admin or the owning employee opens the link, the page
 *   auto-redirects to their respective portal view; only guests (and
 *   non-owners) see the public page itself.
 *
 * The API-key opt-in ({@see $include_api_key}) is honored ONLY for
 * share-eligible orders (active order + active subscription) — the public
 * controller re-checks that before ever surfacing the credential.
 */
class PublicOrderShare extends Model
{
    use HasFactory;

    /**
     * Length of the bearer-token secret (in characters). The token is a flat
     * run of random characters — 40 keeps it unguessable while staying well
     * inside the column's 64-char width.
     */
    public const TOKEN_LENGTH = 40;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'token',
        'created_by_id',
        'created_by_type',
        'include_api_key',
        'expires_at',
        'revoked_at',
        'revoked_by_id',
        'revoked_by_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'include_api_key' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Mint a fresh link for an order.
     *
     * @param mixed $actor       Admin | User | Team (polymorphic creator)
     * @param bool $includeApiKey opt-in to fold the customer API key into the page
     * @param \Illuminate\Support\Carbon|null $expiresAt optional hard expiry
     *
     * @return static
     */
    public static function createForOrder(SalesOrder $order, mixed $actor, bool $includeApiKey = false, ?\Illuminate\Support\Carbon $expiresAt = null): self
    {
        return static::create([
            'order_id' => $order->id,
            'token' => Str::random(static::TOKEN_LENGTH),
            'created_by_id' => $actor?->getKey(),
            'created_by_type' => $actor ? $actor::class : null,
            'include_api_key' => $includeApiKey,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * The order this link points at.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * The actor (Admin / User / Team) who created the link.
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }

    /**
     * The actor who revoked the link (if it was ever revoked).
     */
    public function revokedBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'revoked_by_type', 'revoked_by_id');
    }

    /**
     * Is this link currently usable? Not revoked, and either unbounded or
     * still within its expiry window. This is the single predicate the
     * public route consults; every other check (order existence, etc.) is
     * layered on top by the controller.
     */
    public function isLive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Revoke this link. Idempotent: revoking an already-revoked link
     * re-stamps the revoker but keeps the original revocation time.
     */
    public function revoke(mixed $actor): void
    {
        $this->forceFill([
            'revoked_at' => $this->revoked_at ?? now(),
            'revoked_by_id' => $actor?->getKey(),
            'revoked_by_type' => $actor ? $actor::class : null,
        ])->save();
    }

    /**
     * The full public URL for this link, absolute so it can be pasted into
     * chat tools and email.
     */
    public function url(): string
    {
        return url()->toRoute('public.orders.show', $this->token);
    }
}
