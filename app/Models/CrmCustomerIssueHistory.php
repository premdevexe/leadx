<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCustomerIssueHistory extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'issue_id',
        'employee_id',
        'old_status',
        'new_status',
        'update_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_id' => 'integer',
            'employee_id' => 'integer',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(CrmCustomerIssue::class, 'issue_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
