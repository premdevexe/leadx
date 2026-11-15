<h2>Subscription Expiry Alert</h2>
<p>Subscription for order <strong>#{{ $order->order_number }}</strong> is expiring.</p>
<p><strong>Customer:</strong> {{ $order->customer_name }}</p>
<p><strong>Expires On:</strong> {{ $subscription->expires_at?->format('M d, Y') ?? '—' }}</p>
<p>Please take appropriate action.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
