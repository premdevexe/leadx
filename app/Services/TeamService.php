<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamService
{
    /**
     * Create a new team.
     */
    public function create(array $data): Team
    {
        $team = Team::create([
            'team_code' => $data['team_code'],
            'team_name' => $data['team_name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'status' => $data['status'] ?? 'active',
        ]);

        $this->audit($team, 'created', null, $team->toArray());

        return $team;
    }

    /**
     * Update a team.
     *
     * The Team Code is immutable once the team has issued its first order,
     * because every order number already carries the code as a snapshot.
     */
    public function update(Team $team, array $data): Team
    {
        if (array_key_exists('team_code', $data) && $team->hasOrders()
            && $data['team_code'] !== $team->team_code) {
            throw ValidationException::withMessages([
                'team_code' => 'The Team Code cannot be changed once the team has generated orders.',
            ]);
        }

        $fields = ['team_code', 'team_name', 'username', 'status'];
        $old = Arr::only($team->toArray(), $fields);

        $team->fill(Arr::only($data, $fields));

        if (! empty($data['password'])) {
            $team->password = $data['password'];
        }

        $team->save();

        $this->audit($team, 'updated', $old, Arr::only($team->toArray(), $fields));

        return $team->fresh();
    }

    /**
     * Delete a team and cascade its memberships and assignments.
     */
    public function delete(Team $team): void
    {
        $this->audit($team, 'deleted', $team->toArray(), null);

        $team->delete();
    }

    /**
     * Assign an employee to a team. A user may have at most one *active*
     * membership, so any previous active membership is closed first.
     */
    public function assignMember(Team $team, int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->role !== 'employee') {
            throw ValidationException::withMessages([
                'user_id' => 'Only employees may be assigned to teams.',
            ]);
        }

        DB::transaction(function () use ($team, $user) {
            // Close any currently active membership (history is preserved).
            $user->memberships()->whereNull('left_at')->update(['left_at' => now()]);

            \App\Models\TeamMember::create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'joined_at' => now(),
                'left_at' => null,
            ]);

            // Keep the denormalized FK in sync.
            $user->forceFill(['team_id' => $team->id])->save();
        });

        $this->audit($team, 'member_assigned', null, ['user_id' => $user->id]);
    }

    /**
     * Remove an employee from a team. The membership is closed, not destroyed.
     */
    public function removeMember(Team $team, int $userId): void
    {
        $user = User::findOrFail($userId);

        DB::transaction(function () use ($team, $user) {
            $user->memberships()
                ->whereNull('left_at')
                ->where('team_id', $team->id)
                ->update(['left_at' => now()]);

            if ($user->team_id === $team->id) {
                $user->forceFill(['team_id' => null])->save();
            }
        });

        $this->audit($team, 'member_removed', ['user_id' => $user->id], null);
    }

    /**
     * Log an administrative action.
     */
    protected function audit(Team $team, string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'auditable_type' => Team::class,
            'auditable_id' => $team->id,
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()->ip(),
        ]);
    }
}
