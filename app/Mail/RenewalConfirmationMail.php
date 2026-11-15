<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Mail\Mailable;

class RenewalConfirmationMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'subscription.renewed')->first();

        return $this
            ->subject($template?->subject ?? 'Subscription Renewed — #'.$this->order->order_number)
            ->markdown('emails.subscription.renewed')
            ->with(['order' => $this->order, 'subscription' => $this->order->subscription, 'template' => $template]);
    }
}
