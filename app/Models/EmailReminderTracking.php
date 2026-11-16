<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailReminderTracking extends Model
{
    protected $table = 'email_reminder_tracking';

    protected $fillable = [
        'subscription_id',
        'type',
        'days_before',
        'cycle_at',
        'scheduled_at',
        'sent_at',
        'status',
        'skip_reason',
    ];

    protected function casts(): array
    {
        return [
            'cycle_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
