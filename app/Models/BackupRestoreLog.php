<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestoreLog extends Model
{
    use HasFactory;

    protected $table = 'backup_restore_logs';

    protected $fillable = [
        'backup_id',
        'initiated_by_admin_id',
        'status',
        'restore_type',
        'environment',
        'pre_restore_backup_id',
        'started_at',
        'completed_at',
        'log_output',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'initiated_by_admin_id');
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(BackupRecord::class, 'backup_id', 'backup_id');
    }
}
