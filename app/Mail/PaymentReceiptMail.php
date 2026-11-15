<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\SalesOrder;
use Illuminate\Mail\Mailable;

class PaymentReceiptMail extends Mailable
{
    public function __construct(
        protected SalesOrder $order,
        protected ?EmailTemplate $template = null,
    ) {
    }

    public function build(): self
    {
        $template = $this->template
            ?? EmailTemplate::where('key', 'payment.receipt')->first();

        return $this
            ->subject($template?->subject ?? 'Payment Receipt — #'.$this->order->order_number)
            ->markdown('emails.payment.receipt')
            ->with(['order' => $this->order, 'template' => $template]);
    }
}
