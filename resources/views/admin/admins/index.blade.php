@extends('layouts.admin-dashboard')

@section('title', 'Admins')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Admins</h1>
        <a href="{{ route('admin.admins.create') }}"
           class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-neutral-700">
            New admin
        </a>
    </div>

    <p class="mt-1 text-sm text-neutral-500">Panel sign-in accounts. Deletion is not available here — remove an admin from the database if you must.</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-neutral-200 text-left text-xs font-medium text-neutral-500">
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($admins as $admin)
                    <tr class="border-b border-neutral-100 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ $admin->username }}@if (auth('admin')->id() === $admin->id)<span class="ml-2 rounded-md bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-500">you</span>@endif</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $admin->name }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $admin->email }}</td>
                        <td class="px-4 py-3 text-neutral-500">{{ $admin->created_at?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.admins.edit', $admin) }}" class="text-sm text-neutral-600 transition-colors hover:text-neutral-900">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
