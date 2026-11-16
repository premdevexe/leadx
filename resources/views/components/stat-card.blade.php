@props([
    'title' => '',
    'value' => '0',
    'subtitle' => null,
    'badge' => null,
    'badgeType' => 'neutral', // success, danger, warning, neutral, info
    'icon' => null,
    'accent' => null, // e.g. blue, green, amber, red, purple
])

<div class="stat-card p-5 bg-white rounded-xl border border-neutral-200 shadow-xs relative overflow-hidden transition-all hover:border-neutral-300">
    @if($accent)
        <div class="absolute top-0 left-0 right-0 h-1 bg-{{ $accent }}-500"></div>
    @endif
    <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ $title }}</span>
        @if($badge)
            <span class="badge badge-{{ $badgeType }} text-xs font-medium">{{ $badge }}</span>
        @elseif($icon)
            <div class="stat-icon-wrapper p-2 rounded-lg bg-neutral-100 text-neutral-700">
                {!! $icon !!}
            </div>
        @endif
    </div>

    <div class="mt-3 flex items-baseline justify-between">
        <span class="text-2xl font-bold tracking-tight text-neutral-900 tabular-nums">{{ $value }}</span>
    </div>

    @if($subtitle)
        <p class="mt-1 text-xs text-neutral-500">{{ $subtitle }}</p>
    @endif
</div>
