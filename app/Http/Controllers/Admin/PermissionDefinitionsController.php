<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\PermissionPresetItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermissionDefinitionsController extends Controller
{
    /**
     * List all permission definitions grouped by module, with preset usage stats.
     */
    public function index(): View
    {
        $permissions = Permission::with('presetItems')
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        $presets = PermissionPreset::orderBy('name')->get();

        return view('admin.permissions.index', compact('permissions', 'presets'));
    }

    /**
     * Create a new permission definition and optionally grant it in selected presets.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', Rule::unique('permissions', 'name')],
            'module' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'action' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'preset_ids' => ['sometimes', 'array'],
            'preset_ids.*' => ['integer', 'exists:permission_presets,id'],
        ]);

        $permission = Permission::create([
            'name' => $data['name'],
            'module' => $data['module'],
            'action' => $data['action'],
            'description' => $data['description'] ?? null,
        ]);

        foreach ($data['preset_ids'] ?? [] as $presetId) {
            PermissionPresetItem::create([
                'preset_id' => $presetId,
                'permission_id' => $permission->id,
                'granted' => true,
            ]);
        }

        return redirect()->route('admin.permissions.index')
            ->with('status', "Permission '{$permission->name}' created".(
                isset($data['preset_ids']) && $data['preset_ids']
                    ? ' and granted in '.count($data['preset_ids']).' preset(s).'
                    : '.'
            ));
    }

    /**
     * Delete a permission definition. Preset items and employee overrides
     * are cascade-removed via foreign keys.
     */
    public function destroy(Permission $permission): RedirectResponse
    {
        $name = $permission->name;
        $permission->delete();

        return redirect()->route('admin.permissions.index')
            ->with('status', "Permission '{$name}' deleted (any preset grants and employee overrides for it were removed).");
    }
}
