<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserUiPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_type',
        'user_id',
        'category',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }

    public static function getPreferences(string $userType, int $userId, string $category, array $default = []): array
    {
        $record = static::where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('category', $category)
            ->first();

        return $record ? ($record->preferences ?? $default) : $default;
    }

    public static function setPreferences(string $userType, int $userId, string $category, array $preferences): void
    {
        static::updateOrCreate(
            [
                'user_type' => $userType,
                'user_id' => $userId,
                'category' => $category,
            ],
            [
                'preferences' => $preferences,
            ]
        );
    }
}
