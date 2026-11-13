<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FollowUp extends Model
{
    use HasFactory;

    public const TYPES = [
        'general',
        'subscription_renewal',
        'payment',
        'technical_support',
        'feedback',
        'retention',
    ];

    public const STATUSES = [
        'scheduled',
        'completed',
        'rescheduled',
        'cancelled',
        'overdue',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * Google Calendar sync states, stored on the row so a retried request
     * can never create a duplicate event.
     *
     * @var list<string>
     */
    public const CALENDAR_SYNC = [
        'synced',
        'not_synced',
        'sync_error',
        'not_connected',
    ];

    protected $fillable = [
        'customer_id',
        'team_id',
        'assigned_employee_id',
        'created_by',
        'created_by_type',
        'title',
        'description',
        'follow_up_type',
        'scheduled_at',
        'completed_at',
        'status',
        'outcome',
        'next_follow_up_at',
        'priority',
        'order_id',
        'google_event_id',
        'calendar_sync_status',
        'calendar_sync_message',
        'calendar_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'calendar_synced_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    /**
     * The actor who created this follow-up (admin or employee), morphed.
     */
    public function createdByMorph(): MorphTo
    {
        return $this->morphTo('createdByMorph', 'created_by_type', 'created_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(FollowUpHistory::class);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('assigned_employee_id', $employeeId);
    }

    /**
     * True when this follow-up already has a Google event attached.
     * This is the idempotency guard for Module 3.
     */
    public function hasCalendarEvent(): bool
    {
        return filled($this->google_event_id);
    }

    public function markOverdue(): bool
    {
        if ($this->status !== 'scheduled' || $this->scheduled_at === null || $this->scheduled_at->isFuture()) {
            return false;
        }

        return $this->forceFill(['status' => 'overdue'])->save();
    }
}
