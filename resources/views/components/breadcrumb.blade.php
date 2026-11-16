@props(['items' => []])

@if(!empty($items))
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        @foreach($items as $item)
            <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" {{ $loop->last ? 'aria-current=page' : '' }}>
                @if(!$loop->last && !empty($item['url']))
                    <a href="{{ $item['url'] }}" class="breadcrumb-link">{{ $item['label'] }}</a>
                    <span class="breadcrumb-separator">/</span>
                @else
                    <span class="breadcrumb-current">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
