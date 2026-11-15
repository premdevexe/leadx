<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderClosingValueHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'actor_type',
        'actor_id',
        'status',
        'previous_value',
        'proposed_value',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'previous_value' => 'decimal:2',
            'proposed_value' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }
}
