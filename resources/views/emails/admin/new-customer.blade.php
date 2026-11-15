<h2>New Customer Registration</h2>
<p>A new customer has registered:</p>
<p><strong>Name:</strong> {{ $customer->name ?? '—' }}</p>
<p><strong>Email:</strong> {{ $customer->email ?? '—' }}</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
