<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="form-label">Product name</label>
        <input type="text" name="name" value="{{ old('name', $product->name) }}" required
               class="form-input mt-1 w-full">
        @error('name') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Price ({{ \App\Models\Setting::currencySymbol() }})</label>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}"
               class="form-input mt-1 w-full">
        @error('price') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Minimum Closing Price (Floor Price) ({{ \App\Models\Setting::currencySymbol() }})</label>
        <input type="number" step="0.01" min="0" name="minimum_closing_price" value="{{ old('minimum_closing_price', $product->minimum_closing_price ?? 0) }}"
               class="form-input mt-1 w-full">
        <p class="form-hint">Orders closed below this floor trigger Super Admin Closing Approval workflow.</p>
        @error('minimum_closing_price') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Duration</label>
        <select name="duration" class="form-select mt-1 w-full">
            @foreach (\App\Models\SalesOrder::DURATIONS as $code => $label)
                <option value="{{ $code }}" @selected(old('duration', $product->duration ?? '1y') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        @error('duration') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Status</label>
        <select name="status" class="form-select mt-1 w-full">
            @foreach (\App\Models\Product::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $product->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        @error('status') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2 pt-1">
        <label class="form-check">
            <input type="checkbox" name="api_key_required" value="1" @checked(old('api_key_required', $product->api_key_required ?? false))
                   class="rounded border-neutral-300 text-neutral-900 focus:ring-0">
            <span class="text-xs font-medium text-neutral-700">API Key Required to complete/deliver orders for this product</span>
        </label>
    </div>
</div>

<div class="mt-6 flex flex-wrap items-center gap-3">
    <button type="submit" id="product-submit-btn" class="btn btn-primary px-4 py-2 text-sm font-medium">
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
                    btn.innerHTML = '<span class="inline-block animate-spin mr-1">&#x21BB;</span> Saving&hellip;';
                }
            });
        }
    })();
</script>
