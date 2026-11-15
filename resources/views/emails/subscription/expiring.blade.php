<h2>Subscription Expiring Soon</h2>
<p>Hi {{ $subscription->order->customer_name ?? 'Customer' }},</p>
<p>Your subscription for <strong>{{ $subscription->order->product_name_snapshot ?? '—' }}</strong> will expire on <strong>{{ $subscription->expires_at?->format('M d, Y') ?? '—' }}</strong> ({{ $daysBefore ?? 7 }} days from now).</p>
<p>Renew now to avoid service interruption.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
