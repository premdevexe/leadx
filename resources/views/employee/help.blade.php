@extends('layouts.employee')

@section('title', 'Help & Documentation')

@section('content')
<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-neutral-900">Help & Documentation</h1>
        <p class="mt-1 text-sm text-neutral-500">Quick guides on order flow, floor pricing, and client credential delivery.</p>
    </div>

    <div class="space-y-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h2 class="text-sm font-semibold text-neutral-900">1. Order Creation Workflow</h2>
            <p class="mt-1 text-xs text-neutral-500 leading-relaxed">
                When closing a deal, choose the active product and duration. If your closing value is below the product's minimum closing price, the system automatically triggers a Closing Value Approval request to Super Admin.
            </p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h2 class="text-sm font-semibold text-neutral-900">2. Customer API Key Delivery</h2>
            <p class="mt-1 text-xs text-neutral-500 leading-relaxed">
                Once an order is fully paid and approved, input the customer's API credential. You can use the instant WhatsApp button to dispatch the activation message and access details directly to the client.
            </p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h2 class="text-sm font-semibold text-neutral-900">3. Team Data Access</h2>
            <p class="mt-1 text-xs text-neutral-500 leading-relaxed">
                Team Data is view-only and requires an active historical date-window authorization from Super Admin. When granted, switch to the Team Data tab on your dashboard.
            </p>
        </div>
    </div>
</div>
@endsection
