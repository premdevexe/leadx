<h2>Payment Status Update</h2>
<p>The payment status for order <strong>#{{ $order->order_number }}</strong> has changed.</p>
<p><strong>New Status:</strong> {{ ucfirst($status) }}</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
