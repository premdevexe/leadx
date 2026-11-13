<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'gateway',
        'event_id',
        'order_id',
        'event_type',
        'status',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Idempotency: true when this gateway event id has already been processed.
     */
    public function scopeAlreadyProcessed(Builder $query, string $gateway, string $eventId): Builder
    {
        return $query
            ->where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->where('status', 'processed');
    }

    public function isDuplicateFor(string $gateway, string $eventId): bool
    {
        return self::where('gateway', $gateway)
            ->where('event_id', $eventId)
            ->where('status', 'processed')
            ->exists();
    }
}
