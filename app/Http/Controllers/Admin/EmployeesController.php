<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EmployeesController extends Controller
{
    public function __construct(protected EmployeeService $employees)
    {
    }

    /**
     * List employees.
     */
    public function index(): View
    {
        $employees = User::with('team')
            ->where('role', 'employee')
            ->orderBy('username')
            ->get();

        return view('admin.employees.index', compact('employees'));
    }

    /**
     * Show the create-employee form.
     */
    public function create(): View
    {
        return view('admin.employees.create', [
            'employee' => new User(['role' => 'employee', 'status' => 'active']),
            'teams' => Team::orderBy('team_name')->get(),
        ]);
    }

    /**
     * Store a new employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        // An empty select value means "no team".
        $data['team_id'] = $request->input('team_id') ?: null;

        $this->employees->create($data);

        return redirect()->route('admin.employees')->with('status', 'Employee created.');
    }

    /**
     * Show the edit-employee form.
     */
    public function edit(User $employee): View
    {
        $employee->load('team');

        return view('admin.employees.edit', [
            'employee' => $employee,
            'teams' => Team::orderBy('team_name')->get(),
        ]);
    }

    /**
     * Update an employee.
     */
    public function update(Request $request, User $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);

        // Only send team_id when the form contained it, so unselecting the
        // team (empty value) clears the assignment instead of skipping it.
        if ($request->has('team_id')) {
            $data['team_id'] = $request->input('team_id') ?: null;
        }

        $this->employees->update($employee, $data);

        return redirect()->route('admin.employees')->with('status', 'Employee updated.');
    }

    /**
     * Delete an employee (closes membership history).
     */
    public function destroy(User $employee): RedirectResponse
    {
        $this->employees->delete($employee);

        return redirect()->route('admin.employees')->with('status', 'Employee deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?User $employee = null): array
    {
        $isEdit = $employee !== null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', $isEdit ? Rule::unique('users', 'username')->ignore($employee->id) : 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', $isEdit ? Rule::unique('users', 'email')->ignore($employee->id) : 'unique:users,email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => [$isEdit ? 'sometimes' : 'required', 'required_with:password_confirmation', 'confirmed', Password::min(8)],
            'status' => ['sometimes', 'in:active,inactive'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // role is locked to employee — this panel manages employees only.
        $data['role'] = 'employee';

        return $data;
    }
}
