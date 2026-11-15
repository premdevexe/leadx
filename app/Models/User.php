<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'team_id',
        'preset_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The team this employee is currently assigned to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * All membership records, including historical ones.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * The employee's personal Google Calendar OAuth connection (Phase 08).
     * One row per employee; a team/admin login has no such column and can
     * never own a personal calendar link.
     */
    public function googleCalendarConnection(): HasOne
    {
        return $this->hasOne(EmployeeGoogleConnection::class, 'employee_id');
    }

    /**
     * The permission preset assigned to this employee.
     */
    public function preset(): BelongsTo
    {
        return $this->belongsTo(PermissionPreset::class, 'preset_id');
    }

    /**
     * Individual permission overrides.
     */
    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(EmployeePermission::class, 'user_id');
    }

    /**
     * History of permission modifications for this employee.
     */
    public function permissionHistories(): HasMany
    {
        return $this->hasMany(EmployeePermissionHistory::class, 'user_id');
    }

    /**
     * Team data historical access grants.
     */
    public function teamAccessGrants(): HasMany
    {
        return $this->hasMany(EmployeeTeamAccessGrant::class, 'user_id');
    }

    /**
     * Check if this user has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return app(\App\Services\PermissionService::class)->hasPermission($this, $permissionName);
    }

    /**
     * Check if this employee currently has active team data access.
     */
    public function hasActiveTeamAccess(): bool
    {
        return app(\App\Services\PermissionService::class)->hasActiveTeamAccess($this);
    }

    /**
     * A serializable, non-secret subset of the model (for audit logs).
     */
    public function onlyPublic(): array
    {
        return Arr::except($this->toArray(), ['password', 'remember_token']);
    }
}
