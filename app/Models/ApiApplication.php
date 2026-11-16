<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_url',
        'version',
        'is_enabled',
        'rate_limit_per_minute',
        'key_expiry_days',
        'ip_allowlist',
        'maintenance_mode',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'maintenance_mode' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'key_expiry_days' => 'integer',
        ];
    }

    /**
     * Relationship to API keys.
     */
    public function keys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Singleton accessor for the primary LeadX Android App integration.
     */
    public static function leadx(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'LeadX Android App',
                'base_url' => url('/api/v1'),
                'version' => 'v1',
                'is_enabled' => true,
                'rate_limit_per_minute' => 60,
                'key_expiry_days' => 90,
                'ip_allowlist' => null,
                'maintenance_mode' => false,
            ]
        );
    }

    /**
     * Retrieve the currently active and non-expired API key.
     */
    public function activeKey(): ?ApiKey
    {
        $key = $this->keys()
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($key && $key->isExpired()) {
            $key->update(['status' => 'expired']);
            return null;
        }

        return $key;
    }

    /**
     * Determine if an IP address is permitted under the current allowlist.
     */
    public function isIpAllowed(?string $ip): bool
    {
        if (blank($this->ip_allowlist) || blank($ip)) {
            return true;
        }

        $lines = preg_split('/[\r\n,]+/', (string) $this->ip_allowlist, -1, PREG_SPLIT_NO_EMPTY);
        $allowedIps = array_map('trim', $lines);

        foreach ($allowedIps as $allowed) {
            if ($allowed === $ip) {
                return true;
            }

            // Simple CIDR matching (e.g. 192.168.1.0/24)
            if (str_contains($allowed, '/')) {
                [$subnet, $bits] = explode('/', $allowed);
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                if ($ipLong !== false && $subnetLong !== false) {
                    $mask = -1 << (32 - (int) $bits);
                    $subnetMasked = $subnetLong & $mask;
                    if (($ipLong & $mask) === $subnetMasked) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
