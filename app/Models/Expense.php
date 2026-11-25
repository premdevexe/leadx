<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'infrastructure' => 'Cloud & Infrastructure',
        'software' => 'Software & SaaS',
        'marketing' => 'Marketing & Ads',
        'payroll' => 'Payroll & Contractors',
        'office' => 'Office & Operations',
        'travel' => 'Travel & Events',
        'taxes' => 'Taxes & Compliance',
        'other' => 'Other',
    ];

    protected $fillable = [
        'expense_number',
        'category',
        'title',
        'vendor',
        'amount',
        'currency',
        'expense_date',
        'payment_method',
        'reference',
        'receipt_path',
        'notes',
        'recorded_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id');
    }
}
