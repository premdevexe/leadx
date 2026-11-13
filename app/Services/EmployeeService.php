<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    /**
     * Create a new employee. Optionally assign to a team in one step.
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => $data['role'] ?? 'employee',
                'status' => $data['status'] ?? 'active',
                'team_id' => $data['team_id'] ?? null,
            ]);

            if (! empty($data['team_id'])) {
                $this->assignMembership($user, (int) $data['team_id']);
            }

            $this->audit($user, 'created', null, $user->onlyPublic());

            return $user;
        });
    }

    /**
     * Update an employee.
     */
    public function update(User $employee, array $data): User
    {
        $old = $employee->onlyPublic();

        $employee->fill(Arr::only($data, ['name', 'username', 'email', 'phone', 'password', 'role', 'status']));
        $employee->save();

        if (array_key_exists('team_id', $data)) {
            if ($data['team_id'] === null || (string) $data['team_id'] === '') {
                $this->detachTeam($employee);
            } else {
                $this->assignMembership($employee, (int) $data['team_id']);
            }
        }

        $this->audit($employee, 'updated', $old, $employee->fresh()->onlyPublic());

        return $employee->fresh();
    }

    /**
     * Delete an employee and close their membership history.
     */
    public function delete(User $employee): void
    {
        $this->audit($employee, 'deleted', $employee->onlyPublic(), null);

        $employee->delete();
    }

    /**
     * Detach the employee from any team and close the active membership.
     */
    protected function detachTeam(User $employee): void
    {
        $employee->memberships()->whereNull('left_at')->update(['left_at' => now()]);
        $employee->forceFill(['team_id' => null])->save();
    }

    /**
     * Close any active membership and open a new one on the given team.
     */
    protected function assignMembership(User $employee, int $teamId): void
    {
        $team = \App\Models\Team::find($teamId);

        $employee->memberships()->whereNull('left_at')->update(['left_at' => now()]);

        \App\Models\TeamMember::create([
            'team_id' => $teamId,
            'user_id' => $employee->id,
            'joined_at' => now(),
            'left_at' => null,
        ]);

        $employee->forceFill(['team_id' => $teamId])->save();
    }

    protected function audit(User $employee, string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'auditable_type' => User::class,
            'auditable_id' => $employee->id,
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => request()->ip(),
        ]);
    }
}
