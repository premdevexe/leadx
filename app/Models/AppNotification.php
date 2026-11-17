<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'target',
        'target_value',
        'action_url',
        'publish_id',
        'created_by',
    ];

    /**
     * Relationship to the user/employee who created the notification.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship to admin who created the notification (if sent by admin).
     */
    public function adminSender(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the sender's display name regardless of whether sender was User or Admin.
     */
    public function getSenderNameAttribute(): string
    {
        if (! $this->created_by) {
            return 'System';
        }

        return $this->sender?->name
            ?? $this->adminSender?->name
            ?? 'Admin (' . $this->created_by . ')';
    }
}
