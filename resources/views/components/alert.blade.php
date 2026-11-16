@props([
    'type' => 'info', // success, danger, warning, info
    'title' => null,
    'dismissible' => true,
])

@php
    $typeMap = [
        'success' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'text' => 'text-green-800', 'icon' => '✓'],
        'danger'  => ['bg' => 'bg-red-50',   'border' => 'border-red-200',   'text' => 'text-red-800',   'icon' => '✕'],
        'error'   => ['bg' => 'bg-red-50',   'border' => 'border-red-200',   'text' => 'text-red-800',   'icon' => '✕'],
        'warning' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-800', 'icon' => '⚠'],
        'info'    => ['bg' => 'bg-blue-50',  'border' => 'border-blue-200',  'text' => 'text-blue-800',  'icon' => 'ℹ'],
    ];

    $cfg = $typeMap[$type] ?? $typeMap['info'];
@endphp

<div class="alert-box flex items-start gap-3 p-4 mb-4 rounded-lg border {{ $cfg['bg'] }} {{ $cfg['border'] }} {{ $cfg['text'] }}" role="alert">
    <span class="alert-icon text-lg font-bold leading-none select-none">{{ $cfg['icon'] }}</span>
    <div class="alert-body flex-1 text-sm">
        @if($title)
            <div class="font-semibold mb-1">{{ $title }}</div>
        @endif
        <div class="alert-message">{{ $slot }}</div>
    </div>
    @if($dismissible)
        <button type="button" class="alert-close text-current opacity-60 hover:opacity-100 p-1" onclick="this.closest('.alert-box').remove()" aria-label="Close">
            &times;
        </button>
    @endif
</div>
