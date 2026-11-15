<h2>Your API Key</h2>
<p>Hi {{ $order->customer_name }},</p>
<p>Here is your API key for order <strong>#{{ $order->order_number }}</strong>:</p>
<pre style="background:#f4f4f4;padding:12px;border-radius:6px;word-break:break-all;">{{ $apiKey }}</pre>
<p><strong>Keep this key secure.</strong> Do not share it with anyone.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
