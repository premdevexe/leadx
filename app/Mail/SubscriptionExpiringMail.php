<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Mail\Mailable;

class SubscriptionExpiringMail extends Mailable
{
    public function __construct(
        protected Subscription $subscription,
        protected ?EmailTemplate $template = null,
        protected ?int $daysBefore = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'subscription.expiring')->first();
        $days = $this->daysBefore;

        return $this
            ->subject($template?->subject ?? 'Your Subscription Expires Soon')
            ->markdown('emails.subscription.expiring')
            ->with(['subscription' => $this->subscription, 'daysBefore' => $days, 'template' => $template]);
    }
}
