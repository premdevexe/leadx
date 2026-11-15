<h2>New Order Assigned</h2>
<p>A new order has been created for your team.</p>
<table>
    <tr><td><strong>Order #</strong></td><td>{{ $order->order_number }}</td></tr>
    <tr><td><strong>Customer</strong></td><td>{{ $order->customer_name }}</td></tr>
    <tr><td><strong>Product</strong></td><td>{{ $order->product_name_snapshot ?? '—' }}</td></tr>
    <tr><td><strong>Amount</strong></td><td>{{ number_format((float) $order->closing_value, 2) }}</td></tr>
</table>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
