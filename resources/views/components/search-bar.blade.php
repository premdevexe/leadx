@props([
    'placeholder' => 'Search records...',
    'name' => 'search',
    'value' => request('search'),
])

<form method="GET" class="search-bar-form relative flex-1 max-w-sm">
    @foreach(request()->except([$name, 'page']) as $k => $v)
        @if(is_array($v))
            @foreach($v as $subK => $subV)
                <input type="hidden" name="{{ $k }}[{{ $subK }}]" value="{{ $subV }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach

    <div class="relative flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-neutral-400 absolute left-3 pointer-events-none">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <input type="text"
               name="{{ $name }}"
               value="{{ $value }}"
               placeholder="{{ $placeholder }}"
               class="form-input text-sm pl-9 pr-8 py-2 w-full rounded-lg border border-neutral-300 focus:border-neutral-900 focus:ring-1 focus:ring-neutral-900">
        @if($value)
            <a href="{{ request()->url() }}" class="absolute right-2.5 text-neutral-400 hover:text-neutral-700 text-sm font-bold" title="Clear search">&times;</a>
        @endif
    </div>
</form>
