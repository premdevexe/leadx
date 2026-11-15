<h2>Payment Update</h2>
<p>A payment for order <strong>#{{ $order->order_number }}</strong> has been updated.</p>
<p><strong>Status:</strong> {{ ucfirst($payment->payment_status ?? '—') }}</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
