@php
    $currencySymbol = $currencySymbol ?? \App\Models\Setting::currencySymbol();
    $durationsMap = \App\Models\SalesOrder::DURATIONS;
    $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
    $showSubscriptionCol = $showSubscriptionCol ?? false;
@endphp

@if ($items->isNotEmpty())
    <div class="mt-3 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full text-left text-xs">
            <thead class="bg-neutral-50 border-b border-neutral-200 text-[10px] uppercase tracking-wider text-neutral-500">
                <tr>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2 text-left">Duration</th>
                    <th class="px-3 py-2 text-right">Qty</th>
                    <th class="px-3 py-2 text-right">Unit Price</th>
                    <th class="px-3 py-2 text-right">Line Total</th>
                    @if ($showSubscriptionCol)
                        <th class="px-3 py-2 text-right">Subscription</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($items as $item)
                    @php
                        $durLabel = $durationsMap[$item->product_duration_snapshot] ?? ($item->product_duration_snapshot ?? '1 year');
                        $sub = $showSubscriptionCol ? $item->subscription : null;
                    @endphp
                    <tr>
                        <td class="px-3 py-2 font-medium text-neutral-900">
                            {{ $item->product_name_snapshot }}
                            @if ($item->api_key_required_snapshot)
                                <span class="ml-1 inline-flex items-center rounded bg-purple-50 px-1.5 py-0.5 text-[10px] font-medium text-purple-700 border border-purple-200">API Key</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-neutral-600">{{ $durLabel }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-neutral-600">{{ $currencySymbol }}{{ number_format((float) $item->unit_price_snapshot, 2) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-neutral-900">{{ $currencySymbol }}{{ number_format((float) $item->line_total, 2) }}</td>
                        @if ($showSubscriptionCol)
                            <td class="px-3 py-2 text-right">
                                @if ($sub)
                                    <span class="badge {{ $sub->status === 'active' ? 'badge-success' : 'badge-neutral' }}">
                                        {{ ucfirst($sub->status) }}
                                    </span>
                                    @if ($sub->expires_at)
                                        <span class="block text-[10px] text-neutral-400">Exp: {{ $sub->expires_at->toDateString() }}</span>
                                    @endif
                                @else
                                    <span class="text-neutral-400">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
