<h2>Failed Payment</h2>
<p>A payment for order <strong>#{{ $order->order_number }}</strong> has failed.</p>
<p><strong>Customer:</strong> {{ $order->customer_name }}</p>
<p><strong>Amount:</strong> {{ number_format((float) $order->closing_value, 2) }}</p>
<p>Please investigate and take appropriate action.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
