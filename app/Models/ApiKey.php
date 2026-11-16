<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'api_application_id',
        'name',
        'key_hash',
        'key_prefix',
        'status',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_by_id',
        'created_by_type',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Application owning this API key.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(ApiApplication::class, 'api_application_id');
    }

    /**
     * The admin actor who generated this key.
     */
    public function creator(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }

    /**
     * Determine if this key is expired based on expires_at timestamp.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Current computed status (dynamically detects expiry).
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->status === self::STATUS_REVOKED) {
            return self::STATUS_REVOKED;
        }

        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }

    /**
     * Generate a new API key for the application, superseding/revoking previous keys.
     * Returns an array containing the saved ApiKey model and the one-time plaintext key.
     *
     * @return array{key: self, plain_key: string}
     */
    public static function createKey(ApiApplication $app, ?Admin $admin = null, ?string $name = null): array
    {
        // Enforce single active key: revoke any existing active or disabled keys
        $app->keys()
            ->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_DISABLED])
            ->update([
                'status' => self::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

        // Generate cryptographically secure random key
        $rawEntropy = bin2hex(random_bytes(24)); // 48 chars
        $plainKey = 'lx_live_' . $rawEntropy;
        $hash = hash('sha256', $plainKey);

        // Masked representation for display, e.g. "lx_live_e3b0••••••••••••b855"
        $prefix = substr($plainKey, 0, 12) . '••••••••••••' . substr($plainKey, -4);

        $expiresAt = null;
        if ($app->key_expiry_days > 0) {
            $expiresAt = Carbon::now()->addDays($app->key_expiry_days);
        }

        $apiKey = static::create([
            'api_application_id' => $app->id,
            'name' => $name ?: 'Production Android App Key',
            'key_hash' => $hash,
            'key_prefix' => $prefix,
            'status' => self::STATUS_ACTIVE,
            'expires_at' => $expiresAt,
            'created_by_id' => $admin?->id,
            'created_by_type' => $admin ? get_class($admin) : null,
        ]);

        return [
            'key' => $apiKey,
            'plain_key' => $plainKey,
        ];
    }

    /**
     * Regenerate this key by revoking it and generating a fresh key.
     *
     * @return array{key: self, plain_key: string}
     */
    public function regenerate(?Admin $admin = null): array
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        return static::createKey($this->application, $admin, $this->name);
    }

    /**
     * Revoke this API key permanently.
     */
    public function revoke(): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Toggle active/disabled status.
     */
    public function toggle(): void
    {
        if ($this->status === self::STATUS_REVOKED || $this->isExpired()) {
            return;
        }

        $newStatus = ($this->status === self::STATUS_ACTIVE) ? self::STATUS_DISABLED : self::STATUS_ACTIVE;
        $this->update(['status' => $newStatus]);
    }
}
