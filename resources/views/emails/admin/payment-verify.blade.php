<h2>Payment Verification Required</h2>
<p>A payment for order <strong>#{{ $order->order_number }}</strong> needs verification.</p>
<p><strong>Customer:</strong> {{ $order->customer_name }}</p>
<p><strong>Amount:</strong> {{ number_format((float) $order->closing_value, 2) }}</p>
<p>Please verify the payment from the admin panel.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
