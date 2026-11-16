@props([
    'id' => 'confirm-dialog-' . Str::random(8),
    'title' => 'Confirm Action',
    'message' => 'Are you sure you want to perform this action? This cannot be undone.',
    'confirmText' => 'Confirm',
    'confirmButtonClass' => 'btn-danger',
    'action' => '#',
    'method' => 'POST',
])

<div id="{{ $id }}" class="leadx-modal-backdrop fixed inset-0 z-50 hidden bg-neutral-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-message">
    <div class="leadx-modal-panel w-full max-w-md bg-white rounded-2xl shadow-2xl border border-neutral-200 overflow-hidden transform transition-all">
        <form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}">
            @if(strtoupper($method) !== 'GET')
                @csrf
                @if(!in_array(strtoupper($method), ['POST']))
                    @method($method)
                @endif
            @endif

            <div class="p-6">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <h3 id="{{ $id }}-title" class="text-lg font-bold text-neutral-900">{{ $title }}</h3>
                <p id="{{ $id }}-message" class="text-sm text-neutral-600 mt-2">{{ $message }}</p>

                {{ $slot }}
            </div>

            <div class="px-6 py-4 bg-neutral-50 border-t border-neutral-100 flex items-center justify-end gap-3">
                <button type="button" class="btn btn-secondary" data-modal-close>
                    Cancel
                </button>
                <button type="submit" class="btn {{ $confirmButtonClass }}">
                    {{ $confirmText }}
                </button>
            </div>
        </form>
    </div>
</div>
