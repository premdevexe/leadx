<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Team extends Authenticatable
{
    /** @use HasFactory<TeamFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_code',
        'team_name',
        'username',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Memberships of this team (including historical records).
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Employees currently assigned to this team.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'team_id');
    }

    /**
     * Orders issued to this team.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * Sequence ledger rows for this team.
     */
    public function numberSequences(): HasMany
    {
        return $this->hasMany(OrderNumberSequence::class);
    }

    /**
     * Whether this team has generated at least one order.
     */
    public function hasOrders(): bool
    {
        return $this->orders()->exists();
    }
}
