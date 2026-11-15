<h2>Subscription Renewed!</h2>
<p>Hi {{ $order->customer_name }},</p>
<p>Your subscription has been renewed successfully.</p>
<table>
    <tr><td><strong>Order #</strong></td><td>{{ $order->order_number }}</td></tr>
    <tr><td><strong>Product</strong></td><td>{{ $order->product_name_snapshot ?? '—' }}</td></tr>
    <tr><td><strong>New Expiry Date</strong></td><td>{{ $order->subscription->expires_at?->format('M d, Y') ?? '—' }}</td></tr>
    <tr><td><strong>Billing Cycle</strong></td><td>{{ $order->subscription->billing_cycle ?? '—' }}</td></tr>
</table>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
