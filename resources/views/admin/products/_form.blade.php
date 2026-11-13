<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-xs font-medium text-neutral-500">Product name</label>
        <input type="text" name="name" value="{{ old('name', $product->name) }}" required
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">Price</label>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}"
               class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
        @error('price') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">Duration</label>
        <select name="duration" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            @foreach (\App\Models\SalesOrder::DURATIONS as $code => $label)
                <option value="{{ $code }}" @selected(old('duration', $product->duration ?? '1y') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        @error('duration') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-neutral-500">Status</label>
        <select name="status" class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:outline-none">
            @foreach (\App\Models\Product::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $product->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" id="product-submit-btn" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
        {{ $product->exists ? 'Save changes' : 'Create product' }}
    </button>
    <a href="{{ route('admin.products') }}" class="text-sm text-neutral-600 hover:text-neutral-900">Cancel</a>
</div>

<script>
    (function () {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function () {
                const btn = document.getElementById('product-submit-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('opacity-75', 'cursor-not-allowed');
                    btn.innerHTML = '<span class="inline-block animate-spin mr-1">↻</span> Saving…';
                }
            });
        }
    })();
</script>
