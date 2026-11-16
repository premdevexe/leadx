@extends('layouts.employee')

@section('title', 'Order '.$order->order_number)

@section('content')
    <h1 class="text-lg">Order {{ $order->order_number }}</h1>
    <p class="mt-1 text-sm text-neutral-500">Created {{ $order->created_at?->diffForHumans() }}</p>

    @if (session('success'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('success') }}</div>
    @endif
    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-xs text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('info'))
        <div class="mt-4 rounded-lg bg-sky-50 px-4 py-3 text-xs text-sky-800">{{ session('info') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-xs text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Rejection & Resubmission Card (Section 4.2) --}}
    @if ($order->closing_value_status === 'Rejected' && ! ($isViewOnly ?? false))
        <div class="mt-4 card p-4 text-xs space-y-3 border-red-300">
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
                               class="mt-0.5 form-input">
                    </div>
                    <div>
                        <label class="block text-[11px] text-neutral-600">Resubmission Note</label>
                        <input type="text" name="resubmission_notes" placeholder="Explain adjustment to Admin…"
                               class="mt-0.5 form-input">
                    </div>
                </div>
                <button type="submit" class="rounded bg-neutral-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-neutral-800">
                    Resubmit for Approval
                </button>
            </form>
        </div>
    @endif

    @if ($isViewOnly ?? false)
        <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-900 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
                <strong>Team Data Mode (View-Only):</strong> You are inspecting a team member's order under an authorized historical window grant. All modification, payment verification, and approval actions are disabled.
            </span>
        </div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Customer</p>
            <p class="mt-1 text-sm font-medium">{{ $order->customer_name }}</p>
            <p class="text-xs text-neutral-500">{{ $order->customer_phone }}</p>
            @if($order->customer_email)<p class="text-xs text-neutral-500">{{ $order->customer_email }}</p>@endif
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Product</p>
            <p class="mt-1 text-sm font-medium">{{ $order->product_name_snapshot ?? $order->product_name }}</p>
            <p class="text-xs text-neutral-500">Duration: {{ $order->product_duration_snapshot ?? $order->duration }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Financials</p>
            <p class="mt-1 text-sm">Value: {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->product_value, 2) }}</p>
            <p class="text-sm">Discount: {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->discount_amount, 2) }}</p>
            <p class="text-sm">Closing: {{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $order->closing_value, 2) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-neutral-500">Status</p>
            <p class="mt-1 text-sm">Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</p>
            <p class="text-sm">Order: {{ ucfirst($order->order_status) }}</p>
            <div class="mt-2 pt-2 border-t border-neutral-100 flex items-center justify-between text-xs">
                <span class="text-neutral-500">Floor Status:</span>
                @if ($order->closing_value_status === 'Requested')
                    <span class="inline-flex items-center rounded-full badge-warning px-2.5 py-0.5 text-[11px] font-semibold">
                        Approval Requested
                    </span>
                @elseif ($order->closing_value_status === 'Rejected')
                    <span class="inline-flex items-center rounded-full badge-danger px-2.5 py-0.5 text-[11px] font-semibold">
                        Approval Rejected
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full badge-success px-2.5 py-0.5 text-[11px] font-semibold">
                        Approved ({{ $order->closing_value_status }})
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- API Credentials & Delivery Section --}}
    <div class="mt-6 card p-5">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
            <div>
                <h2 class="text-sm font-semibold text-neutral-900">Customer API Credential Delivery</h2>
                <p class="text-xs text-neutral-500">Encrypted customer API key provisioning for activation.</p>
            </div>
            @if ($order->api_key_required_snapshot)
                <span class="inline-flex items-center rounded-full badge-purple px-2.5 py-0.5 text-xs font-semibold">
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
            <div class="mt-2 rounded-lg border bg-white p-3 text-sm">
                <p>{{ \App\Models\Setting::currencySymbol() }}{{ number_format((float) $payment->amount, 2) }} · {{ ucfirst($payment->payment_status) }}</p>
            </div>
        @empty
            <p class="mt-2 text-sm text-neutral-500">No payments recorded.</p>
        @endforelse
    </div>

    <div class="mt-6 card p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-neutral-900">Subscription & Calendar Reminders</h2>
            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 border border-amber-200">
                <svg class="h-3 w-3 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                36h Alert Preset
            </span>
        </div>

        @if($order->subscription)
            <div class="mt-3 rounded-lg border bg-neutral-50 p-3 text-xs space-y-1">
                <p><span class="text-neutral-500">Status:</span> <span class="font-medium text-neutral-800">{{ ucfirst($order->subscription->status) }}</span></p>
                @if($order->subscription->started_at)<p><span class="text-neutral-500">Started:</span> <span class="font-medium text-neutral-800">{{ $order->subscription->started_at->format('M d, Y') }}</span></p>@endif
                @if($order->subscription->expires_at)<p><span class="text-neutral-500">Expires:</span> <span class="font-medium text-neutral-800">{{ $order->subscription->expires_at->format('M d, Y') }}</span></p>@endif
                @if($order->subscription->calendar_sync_status)
                    <p><span class="text-neutral-500">Google Sync:</span> <span class="font-medium {{ $order->subscription->calendar_sync_status === 'synced' ? 'text-emerald-700' : 'text-neutral-600' }}">{{ ucfirst($order->subscription->calendar_sync_status) }}</span></p>
                @endif
            </div>
        @else
            <p class="mt-3 text-xs text-neutral-500">No active subscription row for this order yet.</p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <a href="{{ $order->googleCalendarUrl() }}"
               target="_blank"
               onclick="window.open(this.href, 'google_calendar_popup', 'width=750,height=650,left=200,top=100,scrollbars=yes,resizable=yes'); return false;"
               class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">
                <svg class="h-4 w-4" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                Add to Google Calendar (Popup)
            </a>
        </div>
    </div>

    {{-- Order Fulfillment & WhatsApp Share Card --}}
    @if (! ($isViewOnly ?? false))
        <div class="mt-6 card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-neutral-900">Order Fulfillment & Delivery</h2>

            <div class="flex flex-wrap items-center gap-3">
                @if (in_array($order->order_status, ['active', 'confirmed']))
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-semibold text-emerald-800">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Order Completed & Activated
                    </span>
                @else
                    @canEmployee('orders.complete')
                        <form method="POST" action="{{ route('employee.orders.complete', $order) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 text-xs font-semibold text-white hover:bg-neutral-800">
                                Complete Order & Activate
                            </button>
                        </form>
                    @endcanEmployee
                @endif

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
