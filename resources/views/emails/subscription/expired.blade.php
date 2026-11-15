<h2>Subscription Expired</h2>
<p>Hi {{ $subscription->order->customer_name ?? 'Customer' }},</p>
<p>Your subscription for <strong>{{ $subscription->order->product_name_snapshot ?? '—' }}</strong> expired on <strong>{{ $subscription->expires_at?->format('M d, Y') ?? '—' }}</strong>.</p>
<p>Renew now to restore access.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
