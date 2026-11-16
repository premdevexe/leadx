<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Scope for successful responses (2xx / 3xx).
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 200)->where('status_code', '<', 400);
    }

    /**
     * Scope for failed responses (4xx / 5xx).
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 400);
    }

    /**
     * Apply filter criteria from request parameters.
     *
     * @param array<string, mixed> $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['from_date'])) {
            try {
                $from = Carbon::parse($filters['from_date'])->startOfDay();
                $query->where('created_at', '>=', $from);
            } catch (\Throwable) {}
        }

        if (! empty($filters['to_date'])) {
            try {
                $to = Carbon::parse($filters['to_date'])->endOfDay();
                $query->where('created_at', '<=', $to);
            } catch (\Throwable) {}
        }

        if (! empty($filters['endpoint'])) {
            $endpoint = trim((string) $filters['endpoint']);
            $query->where('endpoint', 'like', "%{$endpoint}%");
        }

        if (! empty($filters['method']) && strtoupper($filters['method']) !== 'ALL') {
            $query->where('method', strtoupper(trim((string) $filters['method'])));
        }

        if (! empty($filters['status']) && strtoupper($filters['status']) !== 'ALL') {
            $status = trim((string) $filters['status']);
            if ($status === '2xx') {
                $query->whereBetween('status_code', [200, 299]);
            } elseif ($status === '4xx') {
                $query->whereBetween('status_code', [400, 499]);
            } elseif ($status === '5xx') {
                $query->whereBetween('status_code', [500, 599]);
            } elseif (is_numeric($status)) {
                $query->where('status_code', (int) $status);
            }
        }

        return $query;
    }
}
