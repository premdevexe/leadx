<h2>Payment Pending</h2>
<p>Hi {{ $order->customer_name }},</p>
<p>We have received your order <strong>#{{ $order->order_number }}</strong>.</p>
<p>Payment is currently <strong>{{ ucfirst($order->payment_status ?? 'pending') }}</strong>. Please complete payment to activate your subscription.</p>
<p>If you have already made the payment, please share the transaction details with us so we can verify it.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
