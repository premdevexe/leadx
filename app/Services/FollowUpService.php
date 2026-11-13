<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\FollowUpHistory;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\Google\GoogleCalendarService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer follow-up management (Phase 08, Module 1) with Google Calendar
 * sync (Module 3).
 *
 * Business rules enforced here:
 * - Only verified CRM customers may be referenced (rule 1 & 2).
 * - The team is always derived from the customer (rule 4), so a follow-up
 *   can never drift onto a foreign team.
 * - Assignments must land on an active employee of that team (rule 3).
 * - Every transition writes a history row (rules 8 & 9).
 */
class FollowUpService
{
    public function __construct(protected GoogleCalendarService $calendar)
    {
    }

    /*
    |-----------------------------------------------------------------------
    | Access control
    |-----------------------------------------------------------------------
    */

    /**
     * @param Model|mixed $actor
     */
    public function assertCanView(FollowUp $followUp, mixed $actor): void
    {
        if ($actor instanceof Admin) {
            return; // Super Admin sees everything (rule 7).
        }

        if ($actor instanceof Team) {
            // A team token may only see its own team's follow-ups (rule 6).
            if ((int) $followUp->team_id !== (int) $actor->id) {
                $this->deny('You may only view follow-ups from your team.');
            }

            return;
        }

        if ($actor instanceof User) {
            // An employee sees follow-ups they are assigned to, or that
            // belong to their team's customers (rule 5).
            $isAssigned = (int) $followUp->assigned_employee_id === (int) $actor->id;
            $isTeam = $actor->team_id !== null && (int) $followUp->team_id === (int) $actor->team_id;

            if (! $isAssigned && ! $isTeam) {
                $this->deny('You may only view follow-ups from your team.');
            }

            return;
        }

        $this->deny('Unauthorized actor.');
    }

    /**
     * Manage = create/complete/reschedule/cancel. Employees may only act on
     * follow-ups assigned to themselves (rule 5).
     *
     * @param Model|mixed $actor
     */
    public function assertCanManage(FollowUp $followUp, mixed $actor): void
    {
        if ($actor instanceof Admin) {
            return;
        }

        if ($actor instanceof Team) {
            if ((int) $followUp->team_id !== (int) $actor->id) {
                $this->deny('You may only manage follow-ups from your team.');
            }

            return;
        }

        if ($actor instanceof User) {
            fwrite(STDERR, "DBG-MANAGE: fu=".($followUp->id).' assigned='.var_export($followUp->assigned_employee_id, true).' actorId='.var_export($actor->id, true).' actorName='.($actor->name ?? '?')."\n");
            if ((int) $followUp->assigned_employee_id !== (int) $actor->id) {
                $this->deny('You may only manage follow-ups assigned to you.');
            }

            return;
        }

        $this->deny('Unauthorized actor.');
    }

    /**
     * Filter a follow-up query down to what an actor may see.
     *
     * @template TModel
     * @param \Illuminate\Database\Eloquent\Builder<TModel> $query
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    public function scopeForActor($query, mixed $actor): \Illuminate\Database\Eloquent\Builder
    {
        if ($actor instanceof Team) {
            $query->forTeam($actor->id);
        } elseif ($actor instanceof User) {
            $query->where(function ($q) use ($actor): void {
                $q->where('assigned_employee_id', $actor->id);

                if ($actor->team_id !== null) {
                    $q->orWhere('team_id', $actor->team_id);
                }
            });
        }

        // Admin: no restriction.

        return $query;
    }

    /*
    |-----------------------------------------------------------------------
    | Commands
    |-----------------------------------------------------------------------
    */

    /**
     * Create a follow-up. The follow-up is always persisted first; the
     * calendar sync (when requested) then runs and its outcome is recorded
     * on the same row. A failed sync never rolls back the follow-up.
     *
     * @param array<string, mixed> $data
     */
    public function store(mixed $actor, array $data): FollowUp
    {
        // Rules 1 & 2: the customer must exist and be a verified purchaser.
        // Non-admin actors may only target customers of their own team;
        // an invalid / unverified / foreign customer is a 422, not a 404.
        $customerQuery = Customer::query()->verifiedPurchases();

        if ($actor instanceof Team) {
            $customerQuery->where('team_id', $actor->id);
        } elseif ($actor instanceof User) {
            $customerQuery->where('team_id', $actor->team_id);
        }

        $customer = $customerQuery->find((int) $data['customer_id']);

        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'The customer must be a verified CRM customer within your team.',
            ]);
        }

        $team = $customer->team_id !== null ? Team::find($customer->team_id) : null;

        // Rule 4: team is derived from the customer, not free-form input.
        $teamId = $team?->id;

        // Rule 3: the assigned employee (when given) must be active and
        // belong to the same team when the customer has one.
        $employeeId = $this->resolveAssignment($data['assigned_employee_id'] ?? null, $teamId, $actor);

        $order = ! empty($data['order_id']) ? SalesOrder::find((int) $data['order_id']) : null;

        $followUp = DB::transaction(function () use ($customer, $teamId, $employeeId, $actor, $order, $data): FollowUp {
            $followUp = FollowUp::create([
                'customer_id' => $customer->id,
                'team_id' => $teamId,
                'assigned_employee_id' => $employeeId,
                'created_by' => $this->actorId($actor),
                'created_by_type' => $this->actorType($actor),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'follow_up_type' => $data['follow_up_type'] ?? 'general',
                'scheduled_at' => isset($data['scheduled_at']) ? $data['scheduled_at'] : null,
                'status' => 'scheduled',
                'next_follow_up_at' => isset($data['next_follow_up_at']) ? $data['next_follow_up_at'] : null,
                'priority' => $data['priority'] ?? 'medium',
                'order_id' => $order?->id,
                'calendar_sync_status' => 'not_synced',
            ]);

            $this->recordHistory($followUp, 'scheduled', $actor, $data['notes'] ?? null, $followUp->scheduled_at);

            return $followUp;
        });

        // Module 3: create the calendar event on the assigned employee's
        // account. The follow-up already exists, so the sync bookkeeping
        // writes onto the persisted row. When no employee account is
        // available the calendar service records a `not_connected` state on
        // the follow-up itself (never failing the create).
        if (! empty($data['sync_to_calendar'])) {
            $target = $employeeId ? User::find($employeeId) : ($actor instanceof User ? $actor : null);
            $this->calendar->syncCreate($followUp, $target);
        }

        return $followUp->fresh();
    }

    /**
     * Complete a follow-up, recording the outcome and notes.
     *
     * @param Model|mixed $actor
     */
    public function complete(FollowUp $followUp, mixed $actor, ?string $outcome, ?string $notes): FollowUp
    {
        $this->assertCanManage($followUp, $actor);

        if ($followUp->status === 'cancelled') {
            $this->deny('A cancelled follow-up cannot be completed.');
        }

        $followUp->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'outcome' => $outcome,
        ])->save();

        $this->recordHistory($followUp, 'completed', $actor, $notes, null, $outcome);

        // Module 3: a completion is recorded in LeadX; the Google event is
        // retained as history (not deleted or mutated) unless it was never
        // created in the first place.
        return $followUp->fresh();
    }

    /**
     * Reschedule: new time on LeadX + the same event moved in Google.
     *
     * @param Model|mixed $actor
     */
    public function reschedule(FollowUp $followUp, mixed $actor, string $scheduledAt, ?string $nextFollowUpAt, ?string $notes): FollowUp
    {
        $this->assertCanManage($followUp, $actor);

        if ($followUp->status === 'completed') {
            $this->deny('A completed follow-up cannot be rescheduled. Create a new follow-up instead.');
        }

        $followUp->forceFill([
            'status' => 'rescheduled',
            'scheduled_at' => $scheduledAt,
            'next_follow_up_at' => $nextFollowUpAt,
        ])->save();

        $this->recordHistory($followUp, 'rescheduled', $actor, $notes, $scheduledAt, null, $nextFollowUpAt);
        $this->calendar->syncUpdate($followUp, $this->syncTarget($followUp, $actor));

        return $followUp->fresh();
    }

    /**
     * Cancel: status + remote event deleted; LeadX history is retained.
     *
     * @param Model|mixed $actor
     */
    public function cancel(FollowUp $followUp, mixed $actor, ?string $notes): FollowUp
    {
        $this->assertCanManage($followUp, $actor);

        if ($followUp->status === 'completed') {
            $this->deny('A completed follow-up cannot be cancelled.');
        }

        $followUp->forceFill(['status' => 'cancelled'])->save();
        $this->recordHistory($followUp, 'cancelled', $actor, $notes);
        $this->calendar->syncCancel($followUp, $this->syncTarget($followUp, $actor));

        return $followUp->fresh();
    }

    /**
     * Flip all due-but-unhandled scheduled follow-ups to `overdue` (status
     * is a derived state, recomputed by the daily command).
     */
    public function markOverdue(): int
    {
        return FollowUp::query()
            ->overdue()
            ->get()
            ->each(function (FollowUp $followUp): void {
                $followUp->forceFill(['status' => 'overdue'])->save();
            })
            ->count();
    }

    /*
    |-----------------------------------------------------------------------
    | Internals
    |-----------------------------------------------------------------------
    */

    /**
     * The employee whose Google account a calendar write goes to: the
     * assigned employee, falling back to the actor when the actor is the
     * employee themselves.
     */
    protected function syncTarget(FollowUp $followUp, mixed $actor): ?User
    {
        if ($followUp->assigned_employee_id !== null) {
            return User::find($followUp->assigned_employee_id);
        }

        return $actor instanceof User ? $actor : null;
    }

    /**
     * @return int|null the validated employee id, or null when unassigned
     */
    protected function resolveAssignment(?string $employeeId, ?int $teamId, mixed $actor): ?int
    {
        if (blank($employeeId)) {
            // Default: an employee actor self-assigns.
            return $actor instanceof User ? $actor->id : null;
        }

        $employee = User::query()->find((int) $employeeId);

        if ($employee === null || $employee->status !== 'active') {
            throw ValidationException::withMessages([
                'assigned_employee_id' => 'The assigned employee does not exist or is not active.',
            ]);
        }

        // A team-scoped follow-up may only be assigned to that team's
        // employees; an admin assigning across teams must have the
        // customer un-teamed to be allowed.
        if ($teamId !== null && (int) $employee->team_id !== $teamId) {
            throw ValidationException::withMessages([
                'assigned_employee_id' => 'The assigned employee is not a member of the customer\'s team.',
            ]);
        }

        return $employee->id;
    }

    protected function recordHistory(FollowUp $followUp, string $status, mixed $actor, ?string $notes, ?string $scheduledAt, ?string $outcome = null, ?string $nextFollowUpAt = null): void
    {
        FollowUpHistory::create([
            'follow_up_id' => $followUp->id,
            'status' => $status,
            'outcome' => $outcome,
            'notes' => $notes,
            'scheduled_at' => $scheduledAt,
            'next_follow_up_at' => $nextFollowUpAt,
            'acted_by' => $this->actorId($actor),
            'acted_by_type' => $this->actorType($actor),
            'created_at' => now(),
        ]);
    }

    protected function actorId(mixed $actor): ?int
    {
        return $actor instanceof Model ? (int) $actor->id : null;
    }

    protected function actorType(mixed $actor): ?string
    {
        if ($actor instanceof Admin) {
            return Admin::class;
        }

        if ($actor instanceof Team) {
            return Team::class;
        }

        if ($actor instanceof User) {
            return User::class;
        }

        return null;
    }

    protected function deny(string $reason): never
    {
        throw ValidationException::withMessages(['follow_up' => $reason]);
    }
}
