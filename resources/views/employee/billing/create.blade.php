@extends('layouts.employee')

@section('title', 'Create Invoice')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold tracking-tight">Create Invoice</h1>
        <a href="{{ route('employee.billing.index') }}" class="text-sm text-neutral-600 hover:text-neutral-900">&larr; Back</a>
    </div>

    <form method="POST" action="{{ route('employee.billing.store') }}" class="mt-5 space-y-5">
        @csrf
        <div class="rounded-xl border border-neutral-200 bg-white p-5 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="form-label">Customer *</label>
                <select name="customer_id" class="form-input" required>
                    <option value="">Select customer...</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} {{ $c->phone ? '('.$c->phone.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Linked Order (Optional)</label>
                <select name="order_id" class="form-input">
                    <option value="">None</option>
                    @foreach ($orders as $o)
                        <option value="{{ $o->id }}">{{ $o->order_number }} — {{ $o->customer_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Issue Date *</label>
                <input type="date" name="issue_date" value="{{ now()->toDateString() }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" value="{{ now()->addDays((int) ($billing['default_due_days'] ?? 14))->toDateString() }}" class="form-input">
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5 space-y-3">
            <h2 class="text-sm font-semibold text-neutral-800">Service Item</h2>
            <div class="grid gap-3 sm:grid-cols-4">
                <select name="items[0][product_id]" class="form-input text-xs" onchange="const o=this.options[this.selectedIndex]; if(o.value){ document.getElementById('emp-desc').value=o.dataset.name; document.getElementById('emp-price').value=o.dataset.price; }">
                    <option value="">Custom Item</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ $p->price }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <input type="text" id="emp-desc" name="items[0][description]" class="form-input text-xs" placeholder="Description *" required>
                <input type="number" step="0.01" min="0.01" name="items[0][quantity]" value="1" class="form-input text-xs" required>
                <input type="number" step="0.01" min="0" id="emp-price" name="items[0][unit_price]" value="0" class="form-input text-xs" required>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label text-xs">Tax Rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ $billing['default_tax_rate'] ?? 0 }}" class="form-input text-xs">
                </div>
                <div>
                    <label class="form-label text-xs">Notes</label>
                    <input type="text" name="notes" class="form-input text-xs">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Create Invoice</button>
        </div>
    </form>
@endsection
