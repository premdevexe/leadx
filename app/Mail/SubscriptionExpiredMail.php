<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Mail\Mailable;

class SubscriptionExpiredMail extends Mailable
{
    public function __construct(
        protected Subscription $subscription,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'subscription.expired')->first();

        return $this
            ->subject($template?->subject ?? 'Subscription Expired')
            ->markdown('emails.subscription.expired')
            ->with(['subscription' => $this->subscription, 'template' => $template]);
    }
}
