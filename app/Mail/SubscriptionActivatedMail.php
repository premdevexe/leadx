<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Subscription;
use Illuminate\Mail\Mailable;

class SubscriptionActivatedMail extends Mailable
{
    public function __construct(
        protected Subscription $subscription,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'subscription.activated')->first();

        return $this
            ->subject($template?->subject ?? 'Subscription Activated')
            ->markdown('emails.subscription.activated')
            ->with(['subscription' => $this->subscription, 'template' => $template]);
    }
}
