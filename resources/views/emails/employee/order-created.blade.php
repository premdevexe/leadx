<h2>New Order Assigned to You</h2>
<p>Order <strong>#{{ $order->order_number }}</strong> has been assigned to you.</p>
<p><strong>Customer:</strong> {{ $order->customer_name }}</p>
<p><strong>Product:</strong> {{ $order->product_name_snapshot ?? '—' }}</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
