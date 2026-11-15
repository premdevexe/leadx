<h2>Welcome {{ $userName }}!</h2>
<p>Thank you for joining {{ config('app.name', 'LeadX') }}. We are excited to have you on board.</p>
<p>You can now place orders and manage your subscriptions through our platform.</p>
<p>If you have any questions, reply to this email or contact us at {{ config('mail.from.address') }}.</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
