@extends('layouts.admin')

@section('title', 'Permissions: ' . $employee->name)

@section('content')
<div class="max-w-5xl space-y-8">
    {{-- Top Bar --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900">{{ $employee->name }}</h1>
                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-semibold text-neutral-800">
                    {{ $employee->email }}
                </span>
            </div>
            <p class="text-sm text-neutral-500">Configure base preset assignment, customize individual permission overrides, and inspect 30-day audit log.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.presets') }}" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                &larr; Back to Presets
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Assigned Preset & Reset Card --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Preset Assignment --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h2 class="text-sm font-semibold text-neutral-900 mb-2">Base Preset Assignment</h2>
            <p class="text-xs text-neutral-500 mb-4">Select the baseline role for this employee. Permissions default to this preset unless individually overridden below.</p>

            <form method="POST" action="{{ route('admin.presets.employee.update', $employee) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="flex items-center gap-2">
                    <select name="preset_id" class="flex-1 rounded-lg border border-neutral-300 py-1.5 px-3 text-xs text-neutral-800 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                        @foreach ($presets as $p)
                            <option value="{{ $p->id }}" {{ $employee->preset_id == $p->id ? 'selected' : '' }}>
                                {{ $p->name }} {{ $p->is_default ? '(Default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800">
                        Change Preset
                    </button>
                </div>
            </form>
        </div>

        {{-- Reset Overrides Card --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <h2 class="text-sm font-semibold text-neutral-900 mb-2">Reset to Preset Defaults</h2>
                <p class="text-xs text-neutral-500 mb-4">Clears all individual custom permission overrides and reverts this employee back strictly to their assigned preset.</p>
            </div>
            <form method="POST" action="{{ route('admin.presets.employee.reset', $employee) }}">
                @csrf
                <div class="flex items-center gap-2">
                    <input type="text" name="reason" placeholder="Reason for reset (optional)" class="flex-1 rounded-lg border border-neutral-300 py-1 px-3 text-xs text-neutral-800 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                    <button type="submit" onclick="return confirm('Are you sure you want to clear all overrides for this employee?')"
                            class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                        Reset All Overrides
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Historical Team Data Access Grants Card --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
            <div>
                <h2 class="text-base font-semibold text-neutral-900">Historical Team Data Access</h2>
                <p class="text-xs text-neutral-500">Super Admin can grant temporary view-only access to team orders for a historical date range.</p>
            </div>
            @if ($employee->hasActiveTeamAccess())
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                    Active Grant in Effect
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-600">
                    No Active Team Grant
                </span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.employees.team-access.grant', $employee) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end bg-neutral-50 p-4 rounded-lg border border-neutral-200">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-neutral-700">Start Date</label>
                <input type="date" name="start_date" required value="{{ now()->subDays(30)->toDateString() }}" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white py-1.5 px-3 text-xs text-neutral-800 focus:outline-none focus:ring-1 focus:ring-neutral-900">
            </div>
            <div>
                <label class="block text-xs font-semibold text-neutral-700">End Date</label>
                <input type="date" name="end_date" required value="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white py-1.5 px-3 text-xs text-neutral-800 focus:outline-none focus:ring-1 focus:ring-neutral-900">
            </div>
            <div>
                <button type="submit" class="w-full rounded-md bg-neutral-900 py-1.5 px-4 text-xs font-semibold text-white hover:bg-neutral-800">
                    Grant Access Window
                </button>
            </div>
        </form>

        {{-- Active / Past Grants Table --}}
        @if (isset($teamGrants) && $teamGrants->isNotEmpty())
            <div class="overflow-x-auto pt-2">
                <table class="w-full text-left text-xs text-neutral-600">
                    <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500 font-semibold">
                        <tr>
                            <th class="px-4 py-2">Window Range</th>
                            <th class="px-4 py-2">Granted By</th>
                            <th class="px-4 py-2">Granted Date</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($teamGrants as $grant)
                            <tr>
                                <td class="px-4 py-2 font-medium text-neutral-900">
                                    {{ $grant->start_date->format('M d, Y') }} &rarr; {{ $grant->end_date->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-2 text-neutral-700">{{ $grant->admin?->name ?? 'Admin' }}</td>
                                <td class="px-4 py-2 text-neutral-500">{{ $grant->created_at->format('M d, Y H:i') }}</td>
                                <td class="px-4 py-2">
                                    @if ($grant->revoked_at !== null)
                                        <span class="inline-flex items-center rounded bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800">
                                            Revoked on {{ $grant->revoked_at->format('M d, Y') }}
                                        </span>
                                    @elseif ($grant->isActive())
                                        <span class="inline-flex items-center rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-600">
                                            Expired
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @if ($grant->isActive())
                                        <form method="POST" action="{{ route('admin.employees.team-access.revoke', $grant) }}" class="inline">
                                            @csrf
                                            <button type="submit" onclick="return confirm('Revoke this access grant immediately?')" class="rounded border border-red-200 px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-50">
                                                Revoke Now
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Overrides Form --}}
    <form method="POST" action="{{ route('admin.presets.employee.update', $employee) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-3 gap-2">
                <div>
                    <h2 class="text-base font-semibold text-neutral-900">Effective Permissions & Individual Overrides</h2>
                    <p class="text-xs text-neutral-500">Each permission shows its current effective status and source. You can override individual permissions or keep preset behavior.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" name="reason" placeholder="Audit note / reason for changes" class="rounded-lg border border-neutral-300 py-1.5 px-3 text-xs text-neutral-800 focus:outline-none focus:ring-1 focus:ring-neutral-900 w-64">
                    <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                        Save Overrides
                    </button>
                </div>
            </div>

            @foreach ($permissionsByModule as $module => $perms)
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-500 border-b border-neutral-100 pb-1">
                        {{ ucfirst(str_replace('_', ' ', $module)) }} Module
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach ($perms as $perm)
                            <div class="flex items-center justify-between rounded-lg border {{ $perm['is_override'] ? 'border-amber-300 bg-amber-50/30' : 'border-neutral-200 bg-white' }} p-3">
                                <div class="max-w-xs">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-medium text-xs text-neutral-900">{{ $perm['name'] }}</span>
                                        @if ($perm['granted'])
                                            <span class="inline-flex items-center rounded bg-emerald-100 px-1.5 py-0.2 text-[10px] font-semibold text-emerald-800">Granted</span>
                                        @else
                                            <span class="inline-flex items-center rounded bg-red-100 px-1.5 py-0.2 text-[10px] font-semibold text-red-800">Denied</span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-neutral-500 leading-tight mt-0.5">{{ $perm['description'] }}</p>
                                    <div class="text-[10px] text-neutral-400 mt-1">Source: {{ $perm['source'] }}</div>
                                </div>

                                <div class="shrink-0">
                                    <select name="permissions[{{ $perm['id'] }}]" class="rounded-lg border border-neutral-300 bg-white py-1 px-2 text-xs text-neutral-800 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                                        <option value="preset" {{ ! $perm['is_override'] ? 'selected' : '' }}>Use Preset</option>
                                        <option value="grant" {{ $perm['is_override'] && $perm['granted'] ? 'selected' : '' }}>Always Grant</option>
                                        <option value="deny" {{ $perm['is_override'] && ! $perm['granted'] ? 'selected' : '' }}>Always Deny</option>
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end pt-4 border-t border-neutral-100">
                <button type="submit" class="rounded-lg bg-neutral-900 px-6 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800">
                    Save Permission Overrides
                </button>
            </div>
        </div>
    </form>

    {{-- 30-Day Audit History for this Employee --}}
    <div class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-neutral-900">30-Day Audit History for {{ $employee->name }}</h2>
                <p class="text-xs text-neutral-500">Every change made to this employee's permissions over the last 30 days.</p>
            </div>
            <span class="text-xs text-neutral-400">{{ $history->count() }} events logged</span>
        </div>
        <div class="overflow-x-auto max-h-80">
            <table class="w-full text-left text-xs text-neutral-600">
                <thead class="sticky top-0 border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500 font-semibold">
                    <tr>
                        <th class="px-6 py-2.5">Date & Time</th>
                        <th class="px-6 py-2.5">Admin</th>
                        <th class="px-6 py-2.5">Permission</th>
                        <th class="px-6 py-2.5">Change</th>
                        <th class="px-6 py-2.5">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($history as $h)
                        <tr class="hover:bg-neutral-50/50">
                            <td class="px-6 py-2.5 whitespace-nowrap text-neutral-400 font-mono text-[11px]">
                                {{ $h->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-6 py-2.5 font-medium text-neutral-900">
                                {{ $h->admin?->name ?? 'System' }}
                            </td>
                            <td class="px-6 py-2.5 font-mono text-xs text-neutral-700">
                                {{ $h->permission?->name }}
                            </td>
                            <td class="px-6 py-2.5">
                                @if ($h->old_value === null)
                                    <span class="text-neutral-400">Preset</span>
                                @elseif ($h->old_value)
                                    <span class="text-emerald-700">Granted</span>
                                @else
                                    <span class="text-red-700">Denied</span>
                                @endif
                                &rarr;
                                @if ($h->new_value)
                                    <span class="font-semibold text-emerald-700">Granted</span>
                                @else
                                    <span class="font-semibold text-red-700">Denied</span>
                                @endif
                            </td>
                            <td class="px-6 py-2.5 text-neutral-500">
                                {{ $h->reason ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-neutral-400">No permission modifications recorded for this employee in the last 30 days.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
