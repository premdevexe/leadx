@php
    $steps = [
        1 => ['title' => 'Requirements', 'route' => 'install.requirements'],
        2 => ['title' => 'Environment', 'route' => 'install.environment'],
        3 => ['title' => 'Database', 'route' => 'install.database'],
        4 => ['title' => 'Migrations', 'route' => 'install.migrate'],
        5 => ['title' => 'Admin Account', 'route' => 'install.admin'],
        6 => ['title' => 'Configuration', 'route' => 'install.config'],
        7 => ['title' => 'Verify', 'route' => 'install.verify'],
        8 => ['title' => 'Complete', 'route' => null],
    ];
    $currentStep = $currentStep ?? 1;
@endphp

<div class="installer-stepper" style="margin-bottom: 2rem; overflow-x: auto; padding-bottom: 0.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; min-width: 520px; gap: 0.5rem;">
        @foreach ($steps as $number => $step)
            @php
                $isCompleted = $number < $currentStep;
                $isActive = $number === $currentStep;
                $isPending = $number > $currentStep;
            @endphp
            <div style="display: flex; flex-direction: column; align-items: center; flex: 1; position: relative;">
                <div style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 2rem;
                    height: 2rem;
                    border-radius: var(--radius-full, 9999px);
                    font-size: 0.75rem;
                    font-weight: 700;
                    transition: all 0.2s ease;
                    @if ($isCompleted)
                        background: var(--c-green-700, #15803d);
                        color: var(--c-white, #ffffff);
                        border: 2px solid var(--c-green-700, #15803d);
                    @elseif ($isActive)
                        background: var(--c-neutral-900, #171717);
                        color: var(--c-white, #ffffff);
                        border: 2px solid var(--c-neutral-900, #171717);
                        box-shadow: 0 0 0 3px var(--c-neutral-200, #e5e5e5);
                    @else
                        background: var(--c-neutral-100, #f5f5f5);
                        color: var(--c-neutral-400, #a3a3a3);
                        border: 2px solid var(--c-neutral-200, #e5e5e5);
                    @endif
                ">
                    @if ($isCompleted)
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" style="width: 1rem; height: 1rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    @else
                        {{ $number }}
                    @endif
                </div>
                <span style="
                    font-size: 0.6875rem;
                    font-weight: {{ $isActive ? '700' : '500' }};
                    color: {{ $isActive ? 'var(--c-neutral-900, #171717)' : ($isCompleted ? 'var(--c-neutral-700, #404040)' : 'var(--c-neutral-400, #a3a3a3)') }};
                    margin-top: 0.35rem;
                    white-space: nowrap;
                ">
                    {{ $step['title'] }}
                </span>
            </div>
            @if ($number < 8)
                <div style="
                    flex: 1;
                    height: 2px;
                    margin-bottom: 1.25rem;
                    background: {{ $number < $currentStep ? 'var(--c-green-700, #15803d)' : 'var(--c-neutral-200, #e5e5e5)' }};
                    transition: background 0.3s ease;
                "></div>
            @endif
        @endforeach
    </div>
</div>
