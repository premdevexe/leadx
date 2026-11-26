<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name_snapshot',
        'product_price_snapshot',
        'product_duration_snapshot',
        'minimum_closing_price_snapshot',
        'api_key_required_snapshot',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'product_price_snapshot' => 'decimal:2',
            'minimum_closing_price_snapshot' => 'decimal:2',
            'api_key_required_snapshot' => 'boolean',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'order_item_id');
    }

    public function apiCredential(): HasOne
    {
        return $this->hasOne(OrderApiCredential::class, 'order_item_id');
    }
}
