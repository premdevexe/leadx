<h2>Payment Verified!</h2>
<p>Hi {{ $order->customer_name }},</p>
<p>We have verified your payment for order <strong>#{{ $order->order_number }}</strong>.</p>
<p>Your subscription will be activated shortly. Thank you for your business!</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
