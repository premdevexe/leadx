<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAdjustment extends Model
{
    public const TYPES = [
        'credit_note' => 'Credit Note',
        'debit_note' => 'Debit Note',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = [
        'adjustment_number',
        'invoice_id',
        'type',
        'amount',
        'reason',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
