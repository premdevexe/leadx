@extends('layouts.admin')

@section('title', 'Permissions & Presets')

@section('content')
<div class="space-y-8">
    {{-- Top Action Bar --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900">Permission Presets & Access Control</h1>
            <p class="text-sm text-neutral-500">Manage baseline access presets, assign strategies, and customize individual employee overrides.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.presets.create') }}" class="inline-flex items-center rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-neutral-800 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create New Preset
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Presets Grid --}}
    <div>
        <h2 class="text-lg font-semibold text-neutral-900 mb-4">Baseline Presets</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ($presets as $preset)
                <div class="flex flex-col justify-between rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-neutral-900">{{ $preset->name }}</h3>
                            @if ($preset->is_default)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                    Default (New Employees)
                                </span>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-neutral-500 leading-relaxed">{{ $preset->description ?? 'No description provided.' }}</p>
                        <div class="mt-4 flex items-center gap-2 text-xs text-neutral-600">
                            <span class="font-medium text-neutral-900">{{ $preset->employees_count }}</span> active employees assigned
                        </div>
                    </div>

                    <div class="mt-6 border-t border-neutral-100 pt-4 flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.presets.edit', $preset) }}" class="flex-1 rounded-lg border border-neutral-200 py-1.5 text-center text-xs font-medium text-neutral-700 hover:bg-neutral-50 transition-colors">
                                Edit Permissions
                            </a>
                        </div>

                        {{-- Apply Preset Strategy Form --}}
                        <form method="POST" action="{{ route('admin.presets.apply', $preset) }}" class="mt-1 flex flex-col gap-2 bg-neutral-50 p-2.5 rounded-lg border border-neutral-200">
                            @csrf
                            <label class="text-[11px] font-semibold text-neutral-700">Apply Preset Strategy:</label>
                            <div class="flex gap-2">
                                <select name="strategy" class="flex-1 rounded-md border border-neutral-300 bg-white py-1 px-2 text-xs text-neutral-800 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                                    <option value="all">All Employees</option>
                                    <option value="new_only">New Employees Only</option>
                                </select>
                                <button type="submit" onclick="return confirm('Are you sure you want to apply this strategy?')" class="rounded-md bg-neutral-900 px-3 py-1 text-xs font-medium text-white hover:bg-neutral-800">
                                    Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Individual Employee Overrides Directory --}}
    <div class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-200 px-6 py-4">
            <h2 class="text-base font-semibold text-neutral-900">Individual Employee Permissions & Overrides</h2>
            <p class="text-xs text-neutral-500">Employees receive their preset permissions by default. You can inspect or override permissions individually.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-neutral-600">
                <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500 font-semibold">
                    <tr>
                        <th class="px-6 py-3">Employee</th>
                        <th class="px-6 py-3">Assigned Preset</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Custom Overrides</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $emp)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-6 py-3 font-medium text-neutral-900">
                                {{ $emp->name }}
                                <div class="text-[11px] text-neutral-400">{{ $emp->email }} &bull; {{ $emp->username }}</div>
                            </td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-800">
                                    {{ $emp->preset?->name ?? 'Default Preset' }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                @if ($emp->status === 'active')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                @php $overrideCount = $emp->permissionOverrides->count(); @endphp
                                @if ($overrideCount > 0)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                                        {{ $overrideCount }} Override{{ $overrideCount > 1 ? 's' : '' }} Active
                                    </span>
                                @else
                                    <span class="text-neutral-400">Pure Preset Defaults</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('admin.presets.employee', $emp) }}" class="rounded-lg border border-neutral-200 px-3 py-1.5 font-medium text-neutral-700 hover:bg-neutral-100 hover:text-neutral-900 transition-colors">
                                    Manage Permissions &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-neutral-400">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 30-Day Audit Trail --}}
    <div class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-neutral-900">30-Day Permission Audit Trail</h2>
                <p class="text-xs text-neutral-500">Every permission override and reset is securely logged with timestamps and admin identification.</p>
            </div>
            <span class="text-xs text-neutral-400">{{ $recentHistories->count() }} recent events</span>
        </div>
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-left text-xs text-neutral-600">
                <thead class="sticky top-0 border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500 font-semibold">
                    <tr>
                        <th class="px-6 py-2.5">Timestamp</th>
                        <th class="px-6 py-2.5">Changed By</th>
                        <th class="px-6 py-2.5">Target Employee</th>
                        <th class="px-6 py-2.5">Permission</th>
                        <th class="px-6 py-2.5">Change</th>
                        <th class="px-6 py-2.5">Reason / Context</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentHistories as $log)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-6 py-2.5 whitespace-nowrap text-neutral-400 font-mono text-[11px]">
                                {{ $log->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-6 py-2.5 font-medium text-neutral-900">
                                {{ $log->admin?->name ?? 'System' }}
                            </td>
                            <td class="px-6 py-2.5 text-neutral-800">
                                {{ $log->user?->name ?? 'Unknown Employee' }}
                            </td>
                            <td class="px-6 py-2.5 font-mono text-xs text-neutral-700">
                                {{ $log->permission?->name }}
                            </td>
                            <td class="px-6 py-2.5">
                                @if ($log->old_value === null)
                                    <span class="text-neutral-400">Preset Default</span>
                                @elseif ($log->old_value)
                                    <span class="text-emerald-700">Granted</span>
                                @else
                                    <span class="text-red-700">Denied</span>
                                @endif
                                &rarr;
                                @if ($log->new_value)
                                    <span class="font-semibold text-emerald-700">Granted</span>
                                @else
                                    <span class="font-semibold text-red-700">Denied</span>
                                @endif
                            </td>
                            <td class="px-6 py-2.5 text-neutral-500 max-w-xs truncate">
                                {{ $log->reason ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-neutral-400">No permission modifications logged in the last 30 days.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
