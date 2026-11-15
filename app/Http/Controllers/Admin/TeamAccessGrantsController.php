<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamAccessGrantsController extends Controller
{
    public function __construct(protected PermissionService $permissionService)
    {
    }

    /**
     * Grant historical team data access to an employee.
     */
    public function store(Request $request, User $employee): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        EmployeeTeamAccessGrant::create([
            'user_id' => $employee->id,
            'admin_id' => auth('admin')->id(),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ]);

        // Ensure employee has team_data.view permission override
        $this->permissionService->setEmployeeOverride(
            $employee,
            'team_data.view',
            true,
            auth('admin')->id(),
            "Granted Team Data access window ({$data['start_date']} to {$data['end_date']})"
        );

        return back()->with('status', "Team Data access granted for {$employee->name} ({$data['start_date']} to {$data['end_date']}).");
    }

    /**
     * Revoke active team data access grant immediately.
     */
    public function revoke(Request $request, EmployeeTeamAccessGrant $grant): RedirectResponse
    {
        $grant->update(['revoked_at' => now()]);

        return back()->with('status', 'Team Data access grant revoked immediately.');
    }
}
