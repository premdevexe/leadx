@extends('layouts.admin-dashboard')

@section('title', 'Mobile Push Notifications')

@section('content')
<div class="space-y-6">
    {{-- Header & Sub-Navigation Tabs --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-neutral-200">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-neutral-900">Mobile Push Notifications</h1>
            <p class="text-xs text-neutral-500 mt-1">Compose and send real-time alerts to the LeadX Flutter mobile app via Pusher Beams.</p>
        </div>
        <div class="flex items-center gap-3">
            @if($isBeamsConfigured)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Pusher Beams: Connected
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Beams: Not Configured
                </span>
            @endif

            {{-- Tabs --}}
            <div class="inline-flex p-1 rounded-lg bg-neutral-100 text-xs font-medium">
                <a href="{{ route('admin.notifications.index') }}" class="px-3 py-1.5 rounded-md bg-white text-neutral-900 shadow-sm font-semibold">
                    App Notifications
                </a>
                <a href="{{ route('admin.notifications') }}" class="px-3 py-1.5 rounded-md text-neutral-600 hover:text-neutral-900 transition-colors">
                    Web Push
                </a>
            </div>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <strong class="font-semibold">Success!</strong> {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm flex items-start gap-3">
            <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
                <strong class="font-semibold">Error!</strong> {{ session('error') }}
            </div>
        </div>
    @endif

    {{-- Composer & Live Preview Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Composer Form --}}
        <div class="lg:col-span-7 bg-white rounded-xl border border-neutral-200 shadow-sm p-6">
            <div class="flex items-center gap-2 pb-4 mb-5 border-b border-neutral-100">
                <svg class="w-5 h-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                </svg>
                <h2 class="text-sm font-semibold text-neutral-900">Compose Push Notification</h2>
            </div>

            <form action="{{ route('admin.notifications.send') }}" method="POST" class="space-y-4">
                @csrf

                {{-- Target Audience --}}
                <div>
                    <label for="targetSelect" class="block text-xs font-semibold text-neutral-700 mb-1.5">Target Audience *</label>
                    <select name="target" id="targetSelect" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900 outline-none" onchange="toggleUserSelect()">
                        <option value="hello" {{ old('target', 'hello') == 'hello' ? 'selected' : '' }}>General Subscribers (Interest: 'hello')</option>
                        <option value="all" {{ old('target') == 'all' ? 'selected' : '' }}>All App Devices ('hello' + 'all')</option>
                        <option value="user" {{ old('target') == 'user' ? 'selected' : '' }}>Specific Employee / User (Direct Interest: 'user_{id}')</option>
                    </select>
                    @error('target') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Specific User Dropdown (conditional) --}}
                <div id="userSelectContainer" class="{{ old('target') == 'user' ? '' : 'hidden' }}">
                    <label for="userSelect" class="block text-xs font-semibold text-neutral-700 mb-1.5">Select Employee / User *</label>
                    <select name="user_id" id="userSelect" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900 outline-none">
                        <option value="">-- Choose Employee --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Notification Title --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="notifTitle" class="block text-xs font-semibold text-neutral-700">Notification Title *</label>
                        <span class="text-[11px] text-neutral-400"><span id="titleCount">0</span>/120</span>
                    </div>
                    <input type="text" name="title" id="notifTitle" 
                           class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900 outline-none" 
                           placeholder="e.g. New Order Assigned" 
                           value="{{ old('title') }}" 
                           oninput="updatePreview()" required maxlength="120">
                    @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Notification Body --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="notifBody" class="block text-xs font-semibold text-neutral-700">Message Body *</label>
                        <span class="text-[11px] text-neutral-400"><span id="bodyCount">0</span>/500</span>
                    </div>
                    <textarea name="body" id="notifBody" rows="3" 
                              class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900 outline-none resize-y" 
                              placeholder="e.g. You have a new pending closing approval for Order #1049." 
                              oninput="updatePreview()" required maxlength="500">{{ old('body') }}</textarea>
                    @error('body') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Deep Link Action Route --}}
                <div>
                    <label for="actionRoute" class="block text-xs font-semibold text-neutral-700 mb-1.5">On-Tap App Action (Deep Link)</label>
                    <select name="action_route" id="actionRoute" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900 outline-none">
                        <option value="orders" {{ old('action_route') == 'orders' ? 'selected' : '' }}>Open Orders Screen</option>
                        <option value="customers" {{ old('action_route') == 'customers' ? 'selected' : '' }}>Open Customers (CRM) Screen</option>
                        <option value="follow_ups" {{ old('action_route') == 'follow_ups' ? 'selected' : '' }}>Open Follow-ups Screen</option>
                        <option value="calendar" {{ old('action_route') == 'calendar' ? 'selected' : '' }}>Open Calendar Screen</option>
                        <option value="home" {{ old('action_route', 'home') == 'home' ? 'selected' : '' }}>Open App Dashboard</option>
                    </select>
                    <p class="text-[11px] text-neutral-400 mt-1">When tapped on Android, Flutter routes the employee directly to this module.</p>
                </div>

                {{-- Submit Button --}}
                <div class="pt-2">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-neutral-900 text-white text-xs font-semibold hover:bg-neutral-800 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Dispatch Push Notification
                    </button>
                </div>
            </form>
        </div>

        {{-- Live Mobile Tray Preview --}}
        <div class="lg:col-span-5 bg-white rounded-xl border border-neutral-200 shadow-sm p-6 flex flex-col">
            <div class="flex items-center gap-2 pb-4 mb-5 border-b border-neutral-100">
                <svg class="w-5 h-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
                </svg>
                <h2 class="text-sm font-semibold text-neutral-900">Mobile Tray Preview</h2>
            </div>

            <div class="flex-1 flex items-center justify-center bg-neutral-50 rounded-xl p-6 border border-neutral-100">
                {{-- Phone Tray Container --}}
                <div class="w-full max-w-[320px] bg-white rounded-2xl shadow-lg border border-neutral-200/80 p-4 space-y-2">
                    {{-- App Header --}}
                    <div class="flex items-center justify-between text-[11px] text-neutral-400">
                        <div class="flex items-center gap-1.5">
                            <span class="w-5 h-5 rounded-md bg-neutral-900 text-white font-bold flex items-center justify-center text-[10px]">
                                LX
                            </span>
                            <span class="font-semibold text-neutral-700 uppercase tracking-wide text-[10px]">{{ \App\Models\Setting::siteName() ?: 'LeadX' }}</span>
                        </div>
                        <span class="text-[10px]">Just now</span>
                    </div>

                    {{-- Notification Content --}}
                    <div>
                        <h4 class="text-xs font-bold text-neutral-900 line-clamp-1" id="previewTitle">
                            Notification Title
                        </h4>
                        <p class="text-xs text-neutral-600 mt-0.5 line-clamp-3 leading-relaxed" id="previewBody">
                            Message body preview will appear here in real time as you type...
                        </p>
                    </div>

                    {{-- Tray Footer Action --}}
                    <div class="pt-2 border-t border-neutral-100 flex items-center justify-between text-[10px] text-neutral-400">
                        <span>Tap to open in app</span>
                        <svg class="w-3.5 h-3.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-neutral-100 text-xs text-neutral-500 flex items-center justify-between">
                <span>Target Channel:</span>
                <span class="font-mono text-neutral-800 font-semibold" id="previewChannel">Interest: "hello"</span>
            </div>
        </div>
    </div>

    {{-- Notification Logs History Table --}}
    <div class="bg-white rounded-xl border border-neutral-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-neutral-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-sm font-semibold text-neutral-900">Sent Notifications History</h3>
            </div>
            <span class="text-xs text-neutral-500 font-medium">Total: {{ $notifications->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-neutral-50 border-b border-neutral-100 text-neutral-500 uppercase font-semibold text-[10px] tracking-wider">
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Title & Message</th>
                        <th class="px-4 py-3">Audience</th>
                        <th class="px-4 py-3">Target Info</th>
                        <th class="px-4 py-3">Publish ID</th>
                        <th class="px-4 py-3">Action Route</th>
                        <th class="px-4 py-3 text-right">Sent By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 text-neutral-700">
                    @forelse($notifications as $item)
                        <tr class="hover:bg-neutral-50/70 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-500 font-mono">
                                {{ $item->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <span class="font-semibold text-neutral-900 block truncate">{{ $item->title }}</span>
                                <span class="text-neutral-500 block truncate text-[11px]">{{ $item->body }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($item->target === 'user')
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        Single User
                                    </span>
                                @elseif($item->target === 'hello')
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                        Subscribers ('hello')
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-neutral-100 text-neutral-700 border border-neutral-200">
                                        Broadcast ('all')
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-[11px]">
                                {{ $item->target_value ? 'ID: ' . $item->target_value : 'Broadcast' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-[11px] text-neutral-500">
                                @if($item->publish_id)
                                    <span class="bg-neutral-100 px-1.5 py-0.5 rounded border border-neutral-200">{{ Str::limit($item->publish_id, 16) }}</span>
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-neutral-600">
                                <span class="inline-flex px-1.5 py-0.5 rounded bg-neutral-100 text-[10px] font-medium">
                                    {{ $item->action_url ?: 'home' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-neutral-900">
                                {{ $item->sender_name }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-neutral-400">
                                <svg class="w-8 h-8 mx-auto text-neutral-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                No app push notifications dispatched yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($notifications->hasPages())
            <div class="p-4 border-t border-neutral-100 bg-neutral-50">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>

<script>
    function toggleUserSelect() {
        const target = document.getElementById('targetSelect').value;
        const container = document.getElementById('userSelectContainer');
        const channelPreview = document.getElementById('previewChannel');

        if (target === 'user') {
            container.classList.remove('hidden');
            channelPreview.innerText = 'Interest: "user_{id}"';
        } else if (target === 'hello') {
            container.classList.add('hidden');
            channelPreview.innerText = 'Interest: "hello"';
        } else {
            container.classList.add('hidden');
            channelPreview.innerText = 'Broadcast: ["hello", "all"]';
        }
    }

    function updatePreview() {
        const titleInput = document.getElementById('notifTitle');
        const bodyInput = document.getElementById('notifBody');

        const title = titleInput.value || 'Notification Title';
        const body = bodyInput.value || 'Message body preview will appear here in real time as you type...';

        document.getElementById('previewTitle').innerText = title;
        document.getElementById('previewBody').innerText = body;

        document.getElementById('titleCount').innerText = titleInput.value.length;
        document.getElementById('bodyCount').innerText = bodyInput.value.length;
    }

    // Initialize state on load
    document.addEventListener('DOMContentLoaded', function() {
        toggleUserSelect();
        updatePreview();
    });
</script>
@endsection
