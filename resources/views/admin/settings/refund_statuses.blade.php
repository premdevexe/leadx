@extends('layouts.admin-dashboard')

@section('title', 'Refund Statuses')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Refund Statuses Configuration</h1>
            <p class="mt-1 text-xs text-neutral-500">Configure allowed refund statuses, colors, and finality rules.</p>
        </div>
        <a href="{{ route('admin.settings') }}" class="text-xs text-neutral-600 hover:text-neutral-900 underline">&larr; Back to Settings</a>
    </div>

    @if (session('status'))
        <div class="mt-4 alert alert-success text-xs font-semibold">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 alert alert-danger text-xs font-semibold">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- List of Statuses --}}
        <div class="lg:col-span-2 rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-700">Existing Refund Statuses</h2>
            <div class="mt-4 space-y-0 text-xs">
                @foreach ($statuses as $st)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-neutral-900">{{ $st->label }}</span>
                                <code class="font-mono text-[10px] text-neutral-400">({{ $st->slug }})</code>
                                <span class="badge badge-gray" style="background:var(--c-{{ $st->color }}-100);color:var(--c-{{ $st->color }}-800)">
                                    {{ ucfirst($st->color) }}
                                </span>
                                @if ($st->is_final)
                                    <span class="badge badge-neutral">Final</span>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.settings.refund-statuses.update', $st) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <input type="text" name="label" value="{{ $st->label }}" required class="form-input w-36 text-xs">
                            <select name="color" class="form-select text-xs" style="width:auto">
                                @foreach (['neutral', 'amber', 'blue', 'emerald', 'red', 'purple'] as $c)
                                    <option value="{{ $c }}" @selected($st->color === $c)>{{ ucfirst($c) }}</option>
                                @endforeach
                            </select>
                            <label class="form-check text-[11px] text-neutral-600">
                                <input type="checkbox" name="is_final" value="1" @checked($st->is_final) class="rounded border-neutral-300"> Final
                            </label>
                            <button type="submit" class="btn btn-primary px-2.5 py-1 text-xs font-medium">
                                Save
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Add Status Form --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-neutral-700">Add New Status</h2>
            <form method="POST" action="{{ route('admin.settings.refund-statuses.store') }}" class="mt-4 space-y-3 text-xs">
                @csrf
                <div>
                    <label class="form-label text-[11px]">Slug (Unique identifier)</label>
                    <input type="text" name="slug" placeholder="e.g. chargeback_pending" required
                           class="form-input">
                </div>
                <div>
                    <label class="form-label text-[11px]">Label (Display text)</label>
                    <input type="text" name="label" placeholder="e.g. Chargeback Pending" required
                           class="form-input">
                </div>
                <div>
                    <label class="form-label text-[11px]">Color Badge</label>
                    <select name="color" class="form-select">
                        <option value="neutral">Neutral</option>
                        <option value="amber">Amber</option>
                        <option value="blue">Blue</option>
                        <option value="emerald">Emerald</option>
                        <option value="red">Red</option>
                        <option value="purple">Purple</option>
                    </select>
                </div>
                <div>
                    <label class="form-check text-neutral-700">
                        <input type="checkbox" name="is_final" value="1" class="rounded border-neutral-300">
                        <span>Is Final Status (Cannot transition further once reached)</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-full py-1.5 text-xs font-semibold text-white">
                    Create Refund Status
                </button>
            </form>
        </div>
    </div>
@endsection
