<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable ledger of follow-up state changes (Phase 08, Module 1).
 */
class FollowUpHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'follow_up_id',
        'status',
        'outcome',
        'notes',
        'scheduled_at',
        'next_follow_up_at',
        'acted_by',
        'acted_by_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    /**
     * The actor (admin or employee) who recorded this transition.
     */
    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'acted_by_type', 'acted_by');
    }
}
