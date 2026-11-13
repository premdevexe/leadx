<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeGoogleConnection extends Model
{
    use HasFactory;

    /**
     * Connection lifecycle. A revoked/disconnected connection must be
     * re-linked by the employee; it is never auto-repaired.
     *
     * @var list<string>
     */
    public const STATUSES = ['connected', 'revoked', 'disconnected', 'error'];

    protected $fillable = [
        'employee_id',
        'google_email',
        'google_user_id',
        'calendar_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'status',
        'connected_at',
        'last_synced_at',
        'disconnected_at',
    ];

    protected function casts(): array
    {
        return [
            // Encrypt the live credentials at rest (Laravel's built-in
            // 'encrypted' cast uses the app's key). They are never mass-
            // decrypted for serialization — see the resource.
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'connected');
    }

    /**
     * Is the stored access token still usable without a refresh?
     * A ~60s safety margin avoids the exact-expiry race.
     */
    public function accessTokenIsFresh(): bool
    {
        if ($this->status !== 'connected' || $this->access_token_expires_at === null) {
            return false;
        }

        return $this->access_token_expires_at->gt(now()->addSeconds(60));
    }

    /**
     * Is a refresh possible at all? (refresh token present + connected.)
     */
    public function isRefreshable(): bool
    {
        return $this->status === 'connected' && filled($this->refresh_token);
    }
}
