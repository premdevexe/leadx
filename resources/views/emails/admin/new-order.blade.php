<h2>New Order Created</h2>
<table>
    <tr><td><strong>Order #</strong></td><td>{{ $order->order_number }}</td></tr>
    <tr><td><strong>Customer</strong></td><td>{{ $order->customer_name }}</td></tr>
    <tr><td><strong>Product</strong></td><td>{{ $order->product_name_snapshot ?? '—' }}</td></tr>
    <tr><td><strong>Team</strong></td><td>{{ $order->team->team_name ?? '—' }}</td></tr>
    <tr><td><strong>Amount</strong></td><td>{{ number_format((float) $order->closing_value, 2) }}</td></tr>
</table>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
