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
     * A serializable, non-secret subset of the model (for audit logs).
     */
    public function onlyPublic(): array
    {
        return Arr::except($this->toArray(), ['password', 'remember_token']);
    }
}
