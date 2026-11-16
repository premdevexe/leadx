@props([
    'status' => '',
    'type' => null, // success, danger, warning, neutral, info, purple
])

@php
    $statusLower = strtolower(trim((string)$status));
    $resolvedType = $type;

    if (!$resolvedType) {
        $resolvedType = match($statusLower) {
            'active', 'verified', 'success', 'completed', 'paid', 'uploaded', 'fixed' => 'success',
            'pending', 'scheduled', 'requested', 'partially_paid', 'unverified' => 'warning',
            'failed', 'cancelled', 'rejected', 'expired', 'error', 'overdue', 'still_facing' => 'danger',
            'in_progress', 'rescheduled' => 'info',
            'skipped', 'disabled', 'draft', 'inactive' => 'neutral',
            default => 'neutral'
        };
    }

    $label = ucwords(str_replace(['_', '-'], ' ', $status));
@endphp

<span class="badge badge-{{ $resolvedType }} inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium">
    <span class="badge-dot w-1.5 h-1.5 rounded-full bg-current opacity-75"></span>
    <span>{{ $slot->isEmpty() ? $label : $slot }}</span>
</span>
