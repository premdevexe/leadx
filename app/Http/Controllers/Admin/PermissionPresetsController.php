<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeePermission;
use App\Models\EmployeePermissionHistory;
use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\PermissionPresetItem;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermissionPresetsController extends Controller
{
    public function __construct(protected PermissionService $permissionService)
    {
    }

    /**
     * Presets index.
     */
    public function index(): View
    {
        $presets = PermissionPreset::withCount('employees')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $employees = User::where('role', 'employee')
            ->with(['preset', 'permissionOverrides'])
            ->orderBy('name')
            ->get();

        $recentHistories = EmployeePermissionHistory::with(['admin', 'user', 'permission'])
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.presets.index', compact('presets', 'employees', 'recentHistories'));
    }

    /**
     * Create preset form.
     */
    public function create(): View
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');

        return view('admin.presets.create', [
            'preset' => new PermissionPreset(),
            'permissionsByModule' => $permissions,
            'assignedPermissionIds' => [],
        ]);
    }

    /**
     * Store a new preset.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permission_presets,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = Str::slug($data['name'], '_');

        if ($request->boolean('is_default')) {
            PermissionPreset::query()->update(['is_default' => false]);
        }

        $preset = PermissionPreset::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_default' => $request->boolean('is_default'),
        ]);

        $allPermissions = Permission::all();
        $selectedIds = $data['permissions'] ?? [];

        foreach ($allPermissions as $perm) {
            PermissionPresetItem::create([
                'preset_id' => $preset->id,
                'permission_id' => $perm->id,
                'granted' => in_array($perm->id, $selectedIds, false),
            ]);
        }

        return redirect()->route('admin.presets')->with('status', "Preset '{$preset->name}' created successfully.");
    }

    /**
     * Edit preset form.
     */
    public function edit(PermissionPreset $preset): View
    {
        $permissionsByModule = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        $assignedPermissionIds = $preset->items()->where('granted', true)->pluck('permission_id')->all();

        return view('admin.presets.edit', compact('preset', 'permissionsByModule', 'assignedPermissionIds'));
    }

    /**
     * Update an existing preset.
     */
    public function update(Request $request, PermissionPreset $preset): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permission_presets', 'name')->ignore($preset->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        if ($request->boolean('is_default') && ! $preset->is_default) {
            PermissionPreset::where('id', '!=', $preset->id)->update(['is_default' => false]);
        }

        $preset->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_default' => $request->boolean('is_default'),
        ]);

        $allPermissions = Permission::all();
        $selectedIds = $data['permissions'] ?? [];

        foreach ($allPermissions as $perm) {
            PermissionPresetItem::updateOrCreate(
                ['preset_id' => $preset->id, 'permission_id' => $perm->id],
                ['granted' => in_array($perm->id, $selectedIds, false)]
            );
        }

        return redirect()->route('admin.presets')->with('status', "Preset '{$preset->name}' updated successfully.");
    }

    /**
     * Apply preset using 1 of 3 strategies (all, new_only, selected).
     */
    public function apply(Request $request, PermissionPreset $preset): RedirectResponse
    {
        $data = $request->validate([
            'strategy' => ['required', 'in:all,new_only,selected'],
            'employee_ids' => ['required_if:strategy,selected', 'array'],
            'employee_ids.*' => ['integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->permissionService->applyPresetToEmployees(
            $preset,
            $data['strategy'],
            $data['employee_ids'] ?? [],
            auth('admin')->id(),
            $data['reason'] ?? null
        );

        $strategyLabel = match ($data['strategy']) {
            'all' => 'all employees',
            'new_only' => 'new employees only (set as default)',
            'selected' => 'selected employees',
        };

        return redirect()->route('admin.presets')->with('status', "Preset '{$preset->name}' applied successfully to {$strategyLabel}.");
    }

    /**
     * Employee permission overrides and 30-day history view.
     */
    public function employee(User $employee): View
    {
        $employee->load(['preset', 'team']);
        $effectivePermissions = $this->permissionService->getEffectivePermissions($employee);
        $permissionsByModule = collect($effectivePermissions)->groupBy('module');

        $presets = PermissionPreset::orderBy('name')->get();

        $history = EmployeePermissionHistory::where('user_id', $employee->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['admin', 'permission'])
            ->orderByDesc('created_at')
            ->get();

        $teamGrants = \App\Models\EmployeeTeamAccessGrant::where('user_id', $employee->id)
            ->with('admin')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.presets.employee', compact('employee', 'permissionsByModule', 'presets', 'history', 'teamGrants'));
    }

    /**
     * Update individual overrides for an employee.
     */
    public function updateEmployee(Request $request, User $employee): RedirectResponse
    {
        $data = $request->validate([
            'preset_id' => ['nullable', 'integer', 'exists:permission_presets,id'],
            'permissions' => ['sometimes', 'array'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('preset_id', $data) && $data['preset_id'] != $employee->preset_id) {
            $employee->update(['preset_id' => $data['preset_id']]);
        }

        $allPermissions = Permission::all();
        $submitted = $request->input('permissions', []);

        foreach ($allPermissions as $perm) {
            $key = (string) $perm->id;
            if (isset($submitted[$key])) {
                $val = $submitted[$key];
                if ($val === 'preset') {
                    $this->permissionService->removeEmployeeOverride($employee, $perm->id, auth('admin')->id(), $data['reason'] ?? 'Reverted to preset');
                } elseif ($val === 'grant') {
                    $this->permissionService->setEmployeeOverride($employee, $perm->id, true, auth('admin')->id(), $data['reason'] ?? 'Admin granted override');
                } elseif ($val === 'deny') {
                    $this->permissionService->setEmployeeOverride($employee, $perm->id, false, auth('admin')->id(), $data['reason'] ?? 'Admin denied override');
                }
            }
        }

        return redirect()->route('admin.presets.employee', $employee)->with('status', 'Employee permissions updated successfully.');
    }

    /**
     * Reset employee to preset defaults.
     */
    public function resetEmployee(Request $request, User $employee): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->permissionService->resetEmployeeToPreset(
            $employee,
            auth('admin')->id(),
            $request->input('reason') ?? 'Reset all permissions to preset defaults'
        );

        return redirect()->route('admin.presets.employee', $employee)->with('status', 'Employee permissions reset to preset defaults.');
    }
}
