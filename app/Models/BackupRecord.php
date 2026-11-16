<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BackupRecord extends Model
{
    use HasFactory;

    protected $table = 'backups';

    protected $fillable = [
        'backup_id',
        'backup_type',
        'disk',
        'file_path',
        'file_size',
        'database_status',
        'storage_status',
        'remote_status',
        'integrity_status',
        'checksum',
        'is_encrypted',
        'metadata',
        'error_message',
        'verified_at',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_encrypted' => 'boolean',
            'metadata' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function restoreLogs(): HasMany
    {
        return $this->hasMany(BackupRestoreLog::class, 'backup_id', 'backup_id');
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $size = $bytes / pow(1024, $power);

        return number_format($size, 2) . ' ' . $units[$power];
    }

    public function scopeVerified($query)
    {
        return $query->where('integrity_status', 'verified');
    }
}
