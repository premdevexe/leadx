<h2>Order Confirmed</h2>
<p>Thank you for your order, {{ $order->customer_name }}!</p>
<table>
    <tr><td><strong>Order #</strong></td><td>{{ $order->order_number }}</td></tr>
    <tr><td><strong>Product</strong></td><td>{{ $order->product_name_snapshot ?? $order->product_name ?? '—' }}</td></tr>
    <tr><td><strong>Duration</strong></td><td>{{ $order->product_duration_snapshot ?? $order->duration ?? '—' }}</td></tr>
    <tr><td><strong>Amount</strong></td><td>{{ number_format((float) $order->closing_value, 2) }}</td></tr>
    <tr><td><strong>Payment Method</strong></td><td>{{ $order->payment_method ?? '—' }}</td></tr>
    <tr><td><strong>Status</strong></td><td>{{ ucfirst($order->payment_status ?? 'pending') }}</td></tr>
</table>
<p>Your order has been recorded and will be processed shortly. You will receive further updates as your payment is verified and your subscription is activated.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
