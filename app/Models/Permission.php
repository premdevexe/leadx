<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'module',
        'action',
        'description',
    ];

    public function presetItems(): HasMany
    {
        return $this->hasMany(PermissionPresetItem::class);
    }

    public function employeePermissions(): HasMany
    {
        return $this->hasMany(EmployeePermission::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EmployeePermissionHistory::class);
    }
}
