<h2>Payment Receipt</h2>
<p>Hi {{ $order->customer_name }},</p>
<p>Here is your payment receipt for order <strong>#{{ $order->order_number }}</strong>.</p>
<table>
    <tr><td><strong>Amount Paid</strong></td><td>{{ number_format((float) $order->closing_value, 2) }}</td></tr>
    <tr><td><strong>Payment Method</strong></td><td>{{ $order->payment_method ?? '—' }}</td></tr>
    <tr><td><strong>Status</strong></td><td>{{ ucfirst($order->payment_status ?? '—') }}</td></tr>
</table>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
