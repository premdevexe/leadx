<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Mail\Mailable;

class PaymentVerifiedMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'payment.verified')->first();

        return $this
            ->subject($template?->subject ?? 'Payment Verified — #'.$this->order->order_number)
            ->markdown('emails.payment.verified')
            ->with(['order' => $this->order, 'template' => $template]);
    }
}
