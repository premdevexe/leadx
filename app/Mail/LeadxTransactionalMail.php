<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Envelope;

/**
 * Base envelope used by all LeadX transactional emails. The `subject`
 * field is injected by each Mailable so templates can carry
 * `{{…}}` placeholders that are resolved before the message is built.
 */
abstract class LeadxTransactionalMail extends Mailable
{
    abstract public function getSubject(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->getSubject());
    }
}
