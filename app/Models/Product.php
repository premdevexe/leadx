<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * Recognized product states.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'active',
        'inactive',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'minimum_closing_price',
        'duration',
        'api_key_required',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'minimum_closing_price' => 'decimal:2',
            'api_key_required' => 'boolean',
        ];
    }

    /**
     * Orders created from this product.
     */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\SalesAnalyticsService::invalidateCache());
        static::deleted(fn () => \App\Services\SalesAnalyticsService::invalidateCache());
    }
}
