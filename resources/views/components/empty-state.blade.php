@props([
    'title' => 'No records found',
    'description' => 'There are currently no items matching your criteria.',
    'icon' => null,
])

<div class="empty-state-card text-center py-12 px-4 border border-dashed border-neutral-300 rounded-xl bg-neutral-50/50">
    <div class="empty-state-icon mx-auto w-12 h-12 flex items-center justify-center rounded-full bg-neutral-100 text-neutral-400 mb-3">
        @if($icon)
            {!! $icon !!}
        @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
            </svg>
        @endif
    </div>
    <h3 class="empty-state-title text-base font-semibold text-neutral-800">{{ $title }}</h3>
    <p class="empty-state-description text-sm text-neutral-500 max-w-sm mx-auto mt-1">{{ $description }}</p>
    @if(isset($action))
        <div class="empty-state-action mt-4">
            {{ $action }}
        </div>
    @endif
</div>
