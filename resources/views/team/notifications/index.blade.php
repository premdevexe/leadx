@extends('layouts.team')

@section('title', 'Notifications')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Notifications</h1>
    <p class="mt-1 text-sm text-neutral-500">Your recent team notifications.</p>

    <div class="mt-5 flex flex-col gap-3">
        @forelse ($notifications as $notification)
            <div class="card p-4 text-sm">
                <p class="font-medium">{{ $notification->data['title'] ?? 'Notification' }}</p>
                <p class="mt-1 text-neutral-600">{{ $notification->data['message'] ?? '' }}</p>
                <p class="mt-2 text-xs text-neutral-400">{{ $notification->created_at->diffForHumans() }}</p>
            </div>
        @empty
            <p class="text-sm text-neutral-500">No notifications yet.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
