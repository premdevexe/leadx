<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A customer's own API key, delivered against a specific order.
 *
 * The `key` column is transparently Laravel-encrypted at rest (the `encrypted`
 * cast uses the app key) — the database only ever holds AES-256-CBC
 * ciphertext. Unlike a password the key must remain retrievable by an
 * authorized user, which is why it is encrypted, never hashed.
 *
 * There is exactly one row per order (unique order_id). Replacing the key is
 * an in-place update that re-stamps the auditors (updated_by_* / updated_at);
 * the plaintext is never written to an audit row or a log.
 */
class OrderApiCredential extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'key',
        'updated_by_id',
        'updated_by_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * The raw key is never exposed on a resource; only {@see maskKey()}
     * is safe to surface.
     */
    protected $hidden = [
        'key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Encrypt on save, decrypt on read — ciphertext only in the DB.
            'key' => 'encrypted',
        ];
    }

    /**
     * The order this key was delivered for.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id', 'id');
    }

    /**
     * The actor (Admin or User) who last wrote the key.
     */
    public function updatedBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'updated_by_type', 'updated_by_id');
    }

    /**
     * The order's credential, or null when no key has been delivered yet.
     */
    public static function forOrder(int $orderId): ?self
    {
        return static::where('order_id', $orderId)->first();
    }

    /**
     * Does this order have a stored, usable key?
     */
    public static function hasKeyFor(int $orderId): bool
    {
        $credential = static::forOrder($orderId);

        return $credential !== null && filled($credential->key);
    }

    /**
     * A short, non-reversible indicator ("sk-a…def"), never the full key.
     * Accepts either the decrypted key or the raw (encrypted) value — only
     * the shape is reported, so it is safe to call on a fresh read too.
     */
    public function maskKey(): ?string
    {
        $key = $this->key;

        if (blank($key)) {
            return null;
        }

        $len = strlen($key);

        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($key, 0, 3).'…'.substr($key, -3);
    }

    /**
     * The full plaintext key, or null when the ciphertext is unusable
     * (wrong app key / corrupted row). The value is never logged.
     */
    public function decryptedKey(): ?string
    {
        try {
            $key = $this->key;

            return filled($key) ? $key : null;
        } catch (\Throwable) {
            report('Order API key decryption failed for order '.$this->order_id);

            return null;
        }
    }
}
