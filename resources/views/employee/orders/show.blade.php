@extends('layouts.employee')

@section('title', 'Order '.$order->order_number)

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Order {{ $order->order_number }}</h1>
    <p class="mt-1 text-sm text-neutral-500">Created {{ $order->created_at?->diffForHumans() }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-xs text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Rejection & Resubmission Card (Section 4.2) --}}
    @if ($order->closing_value_status === 'Rejected' && ! ($isViewOnly ?? false))
        <div class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-xs text-red-900 space-y-3">
            <div>
                <strong class="font-bold text-red-800">Closing Value Rejected by Super Admin:</strong>
                <p class="mt-1 text-red-700 italic">"{{ $order->closing_approval_notes ?? 'Price discount not accepted.' }}"</p>
            </div>

            <form method="POST" action="{{ route('employee.orders.closing-approval.resubmit', $order) }}" class="space-y-2 pt-2 border-t border-red-200">
                @csrf
                <p class="font-semibold text-neutral-800">Adjust & Resubmit Closing Value:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] text-neutral-600">New Closing Value (Floor: {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) ($order->minimum_closing_price_snapshot ?? 0), 2) }})</label>
                        <input type="number" step="0.01" min="{{ $order->minimum_closing_price_snapshot ?? 0.01 }}" name="closing_value" value="{{ old('closing_value', $order->closing_value) }}" required
                               class="mt-0.5 w-full rounded border border-neutral-300 px-2.5 py-1.5 text-xs text-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                    </div>
                    <div>
                        <label class="block text-[11px] text-neutral-600">Resubmission Note</label>
                        <input type="text" name="resubmission_notes" placeholder="Explain adjustment to Admin…"
                               class="mt-0.5 w-full rounded border border-neutral-300 px-2.5 py-1.5 text-xs text-neutral-900 focus:outline-none focus:ring-1 focus:ring-neutral-900">
                    </div>
                </div>
                <button type="submit" class="rounded bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800">
                    Resubmit for Approval
                </button>
            </form>
        </div>
    @endif

    @if ($isViewOnly ?? false)
        <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50/80 p-3 text-xs text-indigo-900 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
                <strong>Team Data Mode (View-Only):</strong> You are inspecting a team member's order under an authorized historical window grant. All modification, payment verification, and approval actions are disabled.
            </span>
        </div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">Customer</p>
            <p class="mt-1 text-sm font-medium">{{ $order->customer_name }}</p>
            <p class="text-xs text-neutral-500">{{ $order->customer_phone }}</p>
            @if($order->customer_email)<p class="text-xs text-neutral-500">{{ $order->customer_email }}</p>@endif
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">Product</p>
            <p class="mt-1 text-sm font-medium">{{ $order->product_name_snapshot ?? $order->product_name }}</p>
            <p class="text-xs text-neutral-500">Duration: {{ $order->product_duration_snapshot ?? $order->duration }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">Financials</p>
            <p class="mt-1 text-sm">Value: {{ number_format((float) $order->product_value, 2) }}</p>
            <p class="text-sm">Discount: {{ number_format((float) $order->discount_amount, 2) }}</p>
            <p class="text-sm">Closing: {{ number_format((float) $order->closing_value, 2) }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5">
            <p class="text-xs font-medium text-neutral-500">Status</p>
            <p class="mt-1 text-sm">Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</p>
            <p class="text-sm">Order: {{ ucfirst($order->order_status) }}</p>
            <div class="mt-2 pt-2 border-t border-neutral-100 flex items-center justify-between text-xs">
                <span class="text-neutral-500">Floor Status:</span>
                @if ($order->closing_value_status === 'Requested')
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800">
                        Approval Requested
                    </span>
                @elseif ($order->closing_value_status === 'Rejected')
                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-800">
                        Approval Rejected
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800">
                        Approved ({{ $order->closing_value_status }})
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- API Credentials & Delivery Section --}}
    <div class="mt-6 rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
            <div>
                <h2 class="text-sm font-semibold text-neutral-900">Customer API Credential Delivery</h2>
                <p class="text-xs text-neutral-500">Encrypted customer API key provisioning for activation.</p>
            </div>
            @if ($order->api_key_required_snapshot)
                <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800">
                    API Key Mandatory for Completion
                </span>
            @endif
        </div>

        @php
            $revealed = session('order_api_key_reveal');
            $isThisRevealed = is_array($revealed) && (int) ($revealed['order_id'] ?? 0) === (int) $order->id;
        @endphp

        <div class="mt-4">
            @if ($apiKeyStatus['configured'] ?? false)
                <div class="rounded-lg bg-neutral-50 p-4 border border-neutral-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <span class="text-xs font-medium text-neutral-500">Stored Key:</span>
                            <span class="font-mono text-xs text-neutral-800 ml-2 font-semibold">
                                {{ $isThisRevealed ? $revealed['key'] : $apiKeyStatus['masked_key'] }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (! $isThisRevealed)
                                @canEmployee('orders.view_api_key')
                                    <form method="POST" action="{{ route('employee.orders.api-key.reveal', $order) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                                            Reveal Key
                                        </button>
                                    </form>
                                @endcanEmployee
                            @else
                                @canEmployee('orders.copy_api_key')
                                    <button type="button" onclick="navigator.clipboard.writeText('{{ $revealed['key'] }}'); alert('API Key copied to clipboard!');"
                                            class="rounded bg-neutral-900 px-3 py-1 text-xs font-medium text-white hover:bg-neutral-800">
                                        Copy Plaintext Key
                                    </button>
                                @endcanEmployee
                            @endif
                        </div>
                    </div>
                    <div class="text-[11px] text-neutral-400">
                        Encrypted at rest &bull; Last updated {{ $apiKeyStatus['updated_at'] ?? 'recently' }}
                    </div>
                </div>
            @else
                <p class="text-xs text-neutral-500 italic">No customer API key has been stored for this order yet (configured by Super Admin).</p>
            @endif
        </div>
    </div>

    <div class="mt-6">
        <h2 class="text-sm font-semibold text-neutral-700">Payments</h2>
        @forelse($order->payments as $payment)
            <div class="mt-2 rounded-lg border border-neutral-200 bg-white p-3 text-sm">
                <p>{{ number_format((float) $payment->amount, 2) }} · {{ ucfirst($payment->payment_status) }}</p>
            </div>
        @empty
            <p class="mt-2 text-sm text-neutral-500">No payments recorded.</p>
        @endforelse
    </div>

    <div class="mt-6">
        <h2 class="text-sm font-semibold text-neutral-700">Subscription</h2>
        @if($order->subscription)
            <div class="mt-2 rounded-lg border border-neutral-200 bg-white p-3 text-sm">
                <p>Status: {{ ucfirst($order->subscription->status) }}</p>
                @if($order->subscription->started_at)<p>Started: {{ $order->subscription->started_at }}</p>@endif
                @if($order->subscription->expires_at)<p>Expires: {{ $order->subscription->expires_at }}</p>@endif
            </div>
        @else
            <p class="mt-2 text-sm text-neutral-500">No subscription yet.</p>
        @endif
    </div>

    {{-- Order Fulfillment & WhatsApp Share Card --}}
    @if (! ($isViewOnly ?? false))
        <div class="mt-6 rounded-xl border border-neutral-200 bg-white p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-semibold text-neutral-900">Order Fulfillment & Delivery</h2>

            <div class="flex flex-wrap items-center gap-3">
                @canEmployee('orders.complete')
                    <form method="POST" action="{{ route('employee.orders.complete', $order) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-xs font-semibold text-white hover:bg-neutral-800">
                            Complete Order & Activate
                        </button>
                    </form>
                @endcanEmployee

                @if (isset($whatsappShare) && ($whatsappShare['eligible'] ?? false))
                    <a href="{{ $whatsappShare['url'] }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.035-.458.115-.609.134-.134.298-.347.448-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.521-.075-.149-.669-1.588-.916-2.181-.242-.579-.486-.5-.669-.51l-.57-.01c-.199 0-.52.074-.792.372-.272.297-1.04 1.015-1.04 2.459 0 1.444 1.039 2.834 1.188 3.033.149.198 2.085 3.203 5.075 4.478.709.3 1.256.48 1.685.61.709.224 1.353.19 1.862.115.57-.085 1.758-.719 2.009-1.415.25-.695.25-1.287.175-1.411-.074-.124-.272-.199-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.89a9.825 9.825 0 0 1 2.893 6.994c-.001 5.45-4.436 9.885-9.886 9.885m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0 0 20.885 3.488Z"/>
                        </svg>
                        Open WhatsApp Chat &rarr;
                    </a>
                @endif
            </div>
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ route('employee.orders') }}" class="text-sm text-neutral-600 hover:text-neutral-900">&larr; Back to orders</a>
    </div>
@endsection
