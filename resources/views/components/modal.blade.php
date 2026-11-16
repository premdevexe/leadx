@props([
    'id' => 'modal-' . Str::random(8),
    'title' => '',
    'maxWidth' => 'max-w-lg',
])

<div id="{{ $id }}" class="leadx-modal-backdrop fixed inset-0 z-50 hidden bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="leadx-modal-panel w-full {{ $maxWidth }} bg-white rounded-2xl shadow-2xl border border-neutral-200 overflow-hidden transform transition-all animate-in fade-in zoom-in-95">
        <div class="leadx-modal-header px-6 py-4 border-b border-neutral-100 flex items-center justify-between">
            <h3 id="{{ $id }}-title" class="text-lg font-semibold text-neutral-900">{{ $title }}</h3>
            <button type="button" class="modal-close text-neutral-400 hover:text-neutral-700 p-1 rounded-lg" data-modal-close aria-label="Close modal">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="leadx-modal-body px-6 py-5">
            {{ $slot }}
        </div>

        @if(isset($footer))
            <div class="leadx-modal-footer px-6 py-4 bg-neutral-50 border-t border-neutral-100 flex items-center justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
