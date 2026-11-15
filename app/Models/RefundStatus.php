<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'label',
        'color',
        'is_final',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_final' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function defaultStatuses(): array
    {
        return [
            [
                'slug' => 'requested',
                'label' => 'Refund Requested',
                'color' => 'amber',
                'is_final' => false,
                'is_active' => true,
            ],
            [
                'slug' => 'recorded',
                'label' => 'Refund Recorded',
                'color' => 'blue',
                'is_final' => false,
                'is_active' => true,
            ],
            [
                'slug' => 'processed',
                'label' => 'Refund Processed',
                'color' => 'emerald',
                'is_final' => true,
                'is_active' => true,
            ],
            [
                'slug' => 'failed',
                'label' => 'Refund Failed',
                'color' => 'red',
                'is_final' => true,
                'is_active' => true,
            ],
        ];
    }
}
