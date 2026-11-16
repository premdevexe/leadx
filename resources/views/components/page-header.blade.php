@props([
    'title' => '',
    'subtitle' => '',
    'breadcrumbs' => [],
])

<div class="page-header-block mb-6">
    @if(!empty($breadcrumbs))
        <x-breadcrumb :items="$breadcrumbs" />
    @endif

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mt-2">
        <div>
            <h1 class="page-title text-2xl font-bold tracking-tight text-neutral-900">{{ $title }}</h1>
            @if($subtitle)
                <p class="page-subtitle text-sm text-neutral-500 mt-1">{{ $subtitle }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="page-actions flex items-center flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
