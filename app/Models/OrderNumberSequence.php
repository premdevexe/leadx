<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNumberSequence extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'year',
        'last_sequence',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_number_sequences';

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
