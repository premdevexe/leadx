<h2>[ALERT] Email Delivery Failure</h2>
<p>LeadX failed to deliver an email. Please investigate.</p>
<p><strong>Recipient:</strong> {{ $recipient ?? '—' }}</p>
<p><strong>Subject:</strong> {{ $subject ?? '—' }}</p>
<p><strong>Error:</strong> {{ $error ?? 'Unknown error' }}</p>
<p><strong>Log ID:</strong> {{ $log_id ?? '—' }}</p>
<p>Thanks,<br>{{ config('app.name', 'LeadX') }} Team</p>
