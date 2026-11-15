<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmCustomerIssue extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'customer_id',
        'issue_title',
        'issue_description',
        'status',
        'issue_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'customer_id' => 'integer',
            'issue_date' => 'date',
        ];
    }

    public const STATUS_STILL_FACING = 'still_facing';
    public const STATUS_FIXED = 'fixed';

    /**
     * @return array<string>
     */
    public static function allStatuses(): array
    {
        return [self::STATUS_STILL_FACING, self::STATUS_FIXED];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CrmCustomerIssueHistory::class, 'issue_id')->latest('id');
    }

    public function latestHistory()
    {
        return $this->hasOne(CrmCustomerIssueHistory::class, 'issue_id')->latestOfMany();
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
}
