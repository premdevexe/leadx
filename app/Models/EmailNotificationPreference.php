<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailNotificationPreference extends Model
{
    protected $fillable = [
        'event_key',
        'label',
        'category',
        'template_key',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
