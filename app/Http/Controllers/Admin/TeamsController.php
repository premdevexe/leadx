<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use App\Support\TeamCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeamsController extends Controller
{
    public function __construct(protected TeamService $teams)
    {
    }

    /**
     * List teams with their active members (real data only).
     */
    public function index(): View
    {
        $teams = Team::with(['members' => fn ($q) => $q->whereNull('left_at')->with('user')])
            ->withCount('orders')
            ->orderBy('id')
            ->get();

        $unassigned = User::where('role', 'employee')->whereNull('team_id')->orderBy('username')->get();

        return view('admin.teams.index', compact('teams', 'unassigned'));
    }

    /**
     * Show the create-team form.
     */
    public function create(): View
    {
        return view('admin.teams.create', ['team' => new Team(), 'employees' => $this->employees()]);
    }

    /**
     * Store a new team and optionally assign employees to it.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['user_ids' => $this->cleanUserIds($request->input('user_ids'))]);

        $data = $request->validate([
            'team_code' => [
                'required', 'string', 'max:50',
                'regex:'.TeamCode::PATTERN,
                'unique:teams,team_code',
            ],
            'team_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:teams,username'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'status' => ['sometimes', 'in:active,inactive'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'team_code.regex' => 'Team Code must match: '.TeamCode::formatHint(),
        ]);

        $team = $this->teams->create($data);

        $this->syncMembers($team, $request->input('user_ids', []));

        return redirect()->route('admin.teams')->with('status', 'Team created.');
    }

    /**
     * Show the edit-team form.
     */
    public function edit(Team $team): View
    {
        $team->load(['members' => fn ($q) => $q->whereNull('left_at')]);
        $team->loadCount('orders');

        return view('admin.teams.edit', [
            'team' => $team,
            'employees' => $this->employees(),
        ]);
    }

    /**
     * Update a team and sync its employee assignments.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        $request->merge(['user_ids' => $this->cleanUserIds($request->input('user_ids'))]);

        // Once a team has generated orders its code is frozen (every order
        // number carries it); only the exact current value may be submitted.
        $teamCodeRules = [
            'required', 'string', 'max:50', 'regex:'.TeamCode::PATTERN,
        ];

        $teamCodeRules[] = $team->hasOrders()
            ? Rule::in([$team->team_code])
            : Rule::unique('teams', 'team_code')->ignore($team->id);

        $data = $request->validate([
            'team_code' => $teamCodeRules,
            'team_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('teams', 'username')->ignore($team->id)],
            'password' => ['sometimes', 'required', 'confirmed', Password::min(8)],
            'status' => ['sometimes', 'in:active,inactive'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'team_code.regex' => 'Team Code must match: '.TeamCode::formatHint(),
            'team_code.in' => 'The Team Code cannot be changed once the team has generated orders.',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->teams->update($team, $data);

        // A submitted (possibly empty) user_ids always syncs membership so an
        // admin can remove all members by unchecking the whole group.
        if ($request->has('user_ids')) {
            $this->syncMembers($team, $request->input('user_ids', []));
        }

        return redirect()->route('admin.teams')->with('status', 'Team updated.');
    }

    /**
     * Delete a team (memberships cascade; employees become unassigned).
     */
    public function destroy(Team $team): RedirectResponse
    {
        $this->teams->delete($team);

        return redirect()->route('admin.teams')->with('status', 'Team deleted.');
    }

    /**
     * Assign exactly the given employee ids to the team:
     * remove members that lost their checkbox, add new ones.
     */
    protected function syncMembers(Team $team, array $userIds): void
    {
        $userIds = array_map('intval', $userIds);

        // Remove active members not in the new set.
        foreach ($team->members()->whereNull('left_at')->pluck('user_id') as $current) {
            if (! in_array((int) $current, $userIds, true)) {
                $this->teams->removeMember($team, (int) $current);
            }
        }

        // Add newly checked members.
        foreach ($userIds as $userId) {
            $already = $team->members()->whereNull('left_at')->where('user_id', $userId)->exists();

            if (! $already) {
                $this->teams->assignMember($team, $userId);
            }
        }
    }

    protected function employees()
    {
        return User::where('role', 'employee')->orderBy('username')->get();
    }

    /**
     * Drop empty values (the sentinel checkbox) from the submitted ids.
     *
     * @param  mixed  $ids
     * @return int[]
     */
    protected function cleanUserIds(mixed $ids): array
    {
        $ids = is_array($ids) ? $ids : [];

        return array_values(array_filter(
            $ids,
            fn ($id) => $id !== '' && $id !== null
        ));
    }
}
