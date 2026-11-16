<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A personal Google Calendar event created through the employee panel API.
 *
 * The row is the local source of truth for the employee's own events: it
 * carries the Google event id (idempotency + update/delete addressing) and
 * the ownership that every read/mutation endpoint enforces.
 */
class EmployeeCalendarEvent extends Model
{
    use HasFactory;

    public const STATUS_DELETED = 'deleted';

    public const STATUSES = ['synced', 'error', 'deleted'];

    protected $fillable = [
        'employee_id',
        'google_event_id',
        'summary',
        'description',
        'start_at',
        'end_at',
        'status',
        'last_message',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_DELETED);
    }

    public function hasRemoteEvent(): bool
    {
        return filled($this->google_event_id);
    }
}
