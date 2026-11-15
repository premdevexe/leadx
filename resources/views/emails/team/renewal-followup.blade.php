<h2>Renewal Follow-up Required</h2>
<p>Customer <strong>{{ $subscription->order->customer_name ?? '—' }}</strong>'s subscription for <strong>{{ $subscription->order->product_name_snapshot ?? '—' }}</strong> is approaching renewal.</p>
<p><strong>Renewal Date:</strong> {{ $subscription->renewal_date?->format('M d, Y') ?? '—' }}</p>
<p>Please follow up with the customer to ensure a smooth renewal.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
