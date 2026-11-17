<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'ip',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Ensure the polymorphic type resolves safely, even if legacy logs contained arbitrary strings.
     */
    public function getAuditableTypeAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value === 'api_request') {
            return ApiRequestLog::class;
        }

        $resolvedClass = Model::getActualClassNameForMorph($value);

        if (! class_exists($resolvedClass)) {
            return ApiRequestLog::class;
        }

        return $value;
    }

    /**
     * The audited record (user, team, ...).
     */
    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'auditable_type', 'auditable_id');
    }
}
